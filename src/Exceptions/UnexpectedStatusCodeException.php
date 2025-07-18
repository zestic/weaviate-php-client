<?php

declare(strict_types=1);

/*
 * Copyright 2025 Zestic
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

namespace Weaviate\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Is raised when Weaviate returns an unexpected HTTP status code.
 *
 * This exception is thrown when the server responds with a status code
 * that indicates an error condition. The response body and headers are
 * preserved for debugging purposes.
 *
 * @example Handling unexpected status codes
 * ```php
 * try {
 *     $client->collections()->create('Invalid Name!', []);
 * } catch (UnexpectedStatusCodeException $e) {
 *     echo "HTTP {$e->getStatusCode()}: {$e->getMessage()}";
 *
 *     // Get the full response for debugging
 *     $response = $e->getResponse();
 *     if ($response) {
 *         echo "Response body: " . $response['body'];
 *     }
 * }
 * ```
 */
class UnexpectedStatusCodeException extends WeaviateBaseException
{
    /** @var array<string, mixed>|null */
    private ?array $response = null;
    private int $statusCode = 0;

    /**
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $response Response data (body, headers, etc.)
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        ?array $response = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->response = $response;

        // Add status code to context
        $context['status_code'] = $statusCode;
        if ($response !== null) {
            $context['response'] = $response;
        }

        parent::__construct($message, $context, $statusCode, $previous);
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response data
     *
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * Create from PSR-7 response
     *
     * @param string $message Error message
     * @param ResponseInterface $response PSR-7 response
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromResponse(
        string $message,
        ResponseInterface $response,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Try to decode JSON response
        $decodedBody = null;
        if (!empty($body)) {
            $decoded = json_decode($body, true);
            $decodedBody = $decoded !== null ? $decoded : $body;
        }

        $responseData = [
            'status_code' => $statusCode,
            'headers' => $response->getHeaders(),
            'body' => $decodedBody,
            'raw_body' => $body,
        ];

        // Add helpful error explanations for common status codes
        $explanation = self::getStatusCodeExplanation($statusCode);
        if ($explanation) {
            $message .= '. ' . $explanation;
        }

        return new self($message, $statusCode, $responseData, $context, $previous);
    }

    /**
     * Get explanation for common HTTP status codes
     */
    protected static function getStatusCodeExplanation(int $statusCode): ?string
    {
        return match ($statusCode) {
            400 => 'Bad Request: The request was invalid or malformed',
            401 => 'Unauthorized: Authentication is required or has failed',
            403 => 'Forbidden: Insufficient permissions to perform this operation',
            404 => 'Not Found: The requested resource does not exist',
            409 => 'Conflict: The request conflicts with the current state of the resource',
            413 => 'Payload Too Large: Try to decrease the batch size or increase the maximum request size ' .
                   'on your Weaviate server',
            422 => 'Unprocessable Entity: The request was well-formed but contains semantic errors',
            429 => 'Too Many Requests: Rate limit exceeded, please slow down your requests',
            500 => 'Internal Server Error: An error occurred on the Weaviate server',
            502 => 'Bad Gateway: Weaviate server received an invalid response from upstream',
            503 => 'Service Unavailable: Weaviate server is temporarily unavailable',
            504 => 'Gateway Timeout: Weaviate server did not receive a timely response from upstream',
            default => null,
        };
    }
}
