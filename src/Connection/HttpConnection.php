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

namespace Weaviate\Connection;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Weaviate\Exceptions\NotFoundException;

/**
 * HTTP connection implementation for Weaviate
 */
class HttpConnection implements ConnectionInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() === 404) {
            throw new NotFoundException();
        }

        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    public function post(string $path, array $data = []): array
    {
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

        $response = $this->httpClient->sendRequest($request);
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    public function put(string $path, array $data = []): array
    {
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

        $response = $this->httpClient->sendRequest($request);
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    public function patch(string $path, array $data = []): array
    {
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

        $response = $this->httpClient->sendRequest($request);
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    public function delete(string $path): bool
    {
        $url = $this->baseUrl . $path;
        $request = $this->requestFactory->createRequest('DELETE', $url);
        $response = $this->httpClient->sendRequest($request);

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
