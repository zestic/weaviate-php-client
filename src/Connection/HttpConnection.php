<?php

declare(strict_types=1);

/*
 * Copyright 2025-2026 Zestic
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Weaviate\Connection;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Log\LoggerInterface;
use Weaviate\Auth\AuthInterface;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\InsufficientPermissionsException;
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Retry\RetryHandler;

/**
 * HTTP connection implementation for Weaviate
 *
 * Handles all HTTP communication with Weaviate server including:
 * - Request/response handling for all HTTP methods (GET, POST, PUT, PATCH, DELETE)
 * - Authentication header application
 * - Custom header support for API keys and client identification
 * - JSON encoding/decoding
 * - Error handling and status code processing
 *
 * This class is typically not used directly but through the WeaviateClient
 * connection helper methods.
 *
 * @example Manual connection creation
 * ```php
 * use GuzzleHttp\Client;
 * use GuzzleHttp\Psr7\HttpFactory;
 * use Weaviate\Connection\HttpConnection;
 * use Weaviate\Auth\ApiKey;
 *
 * $httpClient = new Client();
 * $httpFactory = new HttpFactory();
 *
 * $connection = new HttpConnection(
 *     'https://my-weaviate.com',
 *     $httpClient,
 *     $httpFactory,
 *     $httpFactory,
 *     new ApiKey('my-api-key'),
 *     ['X-Custom-Header' => 'value']
 * );
 * ```
 */
class HttpConnection implements ConnectionInterface
{
    /**
     * @param array<string, string> $additionalHeaders
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?AuthInterface $auth = null,
        private readonly array $additionalHeaders = [],
        private readonly ?RetryHandler $retryHandler = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function delete(string $path): bool
    {
        $url = $this->baseUrl . $path;
        $request = $this->requestFactory->createRequest('DELETE', $url);
        $response = $this->sendRequest($request, "DELETE {$path}", $url);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    public function deleteWithData(string $path, array $data = []): bool
    {
        $url = $this->baseUrl . $path;
        $request = $this->requestFactory->createRequest('DELETE', $url);

        if (!empty($data)) {
            $json = json_encode($data);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode JSON data');
            }
            $stream = $this->streamFactory->createStream($json);
            $request = $request->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        $response = $this->sendRequest($request, "DELETE {$path}", $url);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    public function get(string $path, array $params = []): array
    {
        $operation = "GET {$path}";

        $executeRequest = function () use ($path, $params, $operation): array {
            $url = $this->baseUrl . $path;
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }

            $request = $this->requestFactory->createRequest('GET', $url);
            $response = $this->sendRequest($request, $operation, $url);

            // Handle HTTP error status codes
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->handleErrorResponse($response, $operation);
            }

            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        };

        // Use retry handler if available
        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $executeRequest);
        }

        return $executeRequest();
    }

    public function head(string $path): bool
    {
        $operation = "HEAD {$path}";

        $executeRequest = function () use ($path, $operation): bool {
            $url = $this->baseUrl . $path;
            $request = $this->requestFactory->createRequest('HEAD', $url);
            $response = $this->sendRequest($request, $operation, $url);

            $statusCode = $response->getStatusCode();

            // Return true for successful status codes
            if ($statusCode >= 200 && $statusCode < 300) {
                return true;
            }

            // Return false for 404 Not Found (resource doesn't exist)
            if ($statusCode === 404) {
                return false;
            }

            // For other HTTP error status codes, throw appropriate exceptions
            $this->handleErrorResponse($response, $operation);

            // This line should never be reached due to handleErrorResponse throwing
            return false;
        };

        // Use retry handler if available
        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $executeRequest);
        }

        return $executeRequest();
    }

    public function patch(string $path, array $data = []): array
    {
        $operation = "PATCH {$path}";

        $executeRequest = function () use ($path, $data, $operation): array {
            $url = $this->baseUrl . $path;
            $request = $this->requestFactory->createRequest('PATCH', $url);

            if (!empty($data)) {
                $json = json_encode($data);
                if ($json === false) {
                    throw new \RuntimeException('Failed to encode JSON data');
                }
                $stream = $this->streamFactory->createStream($json);
                $request = $request->withBody($stream)
                    ->withHeader('Content-Type', 'application/json');
            }

            $response = $this->sendRequest($request, $operation, $url);

            // Handle HTTP error status codes
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->handleErrorResponse($response, $operation);
            }

            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        };

        // Use retry handler if available
        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $executeRequest);
        }

        return $executeRequest();
    }

    public function post(string $path, array $data = []): array
    {
        $operation = "POST {$path}";

        $executeRequest = function () use ($path, $data, $operation): array {
            $url = $this->baseUrl . $path;
            $request = $this->requestFactory->createRequest('POST', $url);

            if (!empty($data)) {
                $json = json_encode($data);
                if ($json === false) {
                    throw new \RuntimeException('Failed to encode JSON data');
                }
                $stream = $this->streamFactory->createStream($json);
                $request = $request->withBody($stream)
                    ->withHeader('Content-Type', 'application/json');
            }

            $response = $this->sendRequest($request, $operation, $url);

            // Handle HTTP error status codes
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->handleErrorResponse($response, $operation);
            }

            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        };

        // Use retry handler if available
        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $executeRequest);
        }

        return $executeRequest();
    }

    public function put(string $path, array $data = []): array
    {
        $operation = "PUT {$path}";

        $executeRequest = function () use ($path, $data, $operation): array {
            $url = $this->baseUrl . $path;
            $request = $this->requestFactory->createRequest('PUT', $url);

            if (!empty($data)) {
                $json = json_encode($data);
                if ($json === false) {
                    throw new \RuntimeException('Failed to encode JSON data');
                }
                $stream = $this->streamFactory->createStream($json);
                $request = $request->withBody($stream)
                    ->withHeader('Content-Type', 'application/json');
            }

            $response = $this->sendRequest($request, $operation, $url);

            // Handle HTTP error status codes
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->handleErrorResponse($response, $operation);
            }

            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        };

        // Use retry handler if available
        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $executeRequest);
        }

        return $executeRequest();
    }

    /**
     * Send an HTTP request with logging and exception handling
     *
     * Applies headers and authentication, logs the request and response,
     * and handles all exceptions by converting them to WeaviateConnectionException.
     *
     * @param \Psr\Http\Message\RequestInterface $request The prepared HTTP request
     * @param string $operation Operation description for logging (e.g., "GET /objects")
     * @param string $url The full URL being requested
     * @return \Psr\Http\Message\ResponseInterface
     * @throws WeaviateConnectionException If the HTTP request fails
     */
    private function sendRequest(
        \Psr\Http\Message\RequestInterface $request,
        string $operation,
        string $url
    ): \Psr\Http\Message\ResponseInterface {
        // Apply headers and authentication
        $request = $this->applyHeaders($request);
        $request = $this->applyAuth($request);

        // Log the outgoing request
        $this->logger?->debug('Sending HTTP request', [
            'method' => $request->getMethod(),
            'url' => $url,
            'operation' => $operation,
        ]);

        try {
            $response = $this->httpClient->sendRequest($request);

            // Log successful response
            $this->logger?->debug('HTTP response received', [
                'status_code' => $response->getStatusCode(),
                'url' => $url,
                'operation' => $operation,
            ]);

            return $response;
        } catch (\Throwable $e) {
            // Log the exception with full context
            $this->logger?->error('HTTP request failed', [
                'exception' => $e,
                'url' => $url,
                'operation' => $operation,
                'message' => $e->getMessage(),
            ]);

            // Convert to WeaviateConnectionException
            throw WeaviateConnectionException::fromNetworkError($url, $e->getMessage(), $e);
        }
    }

    /**
     * Apply authentication to a request if auth is configured
     */
    private function applyAuth(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface
    {
        return $this->auth !== null ? $this->auth->apply($request) : $request;
    }

    /**
     * Apply additional headers to a request
     */
    private function applyHeaders(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\RequestInterface
    {
        foreach ($this->additionalHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    /**
     * Handle HTTP error responses by throwing appropriate exceptions
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $operation
     * @throws UnexpectedStatusCodeException
     * @throws InsufficientPermissionsException
     * @throws NotFoundException
     */
    private function handleErrorResponse(\Psr\Http\Message\ResponseInterface $response, string $operation): void
    {
        $statusCode = $response->getStatusCode();

        // Create context for the error
        $context = [
            'operation' => $operation,
            'url' => (string) $response->getHeaderLine('X-Request-URL') ?: $this->baseUrl,
        ];

        // Handle specific status codes with specialized exceptions
        switch ($statusCode) {
            case 403:
                $message = "Insufficient permissions to perform this operation";
                throw InsufficientPermissionsException::fromResponse($message, $response, $context);

            case 404:
                $message = "Resource not found";
                throw NotFoundException::fromResponse($message, $response, $context);

            default:
                // For all other error status codes, use the general exception
                $message = "HTTP request failed with status {$statusCode}";
                throw UnexpectedStatusCodeException::fromResponse($message, $response, $context);
        }
    }
}
