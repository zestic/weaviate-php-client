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

namespace Weaviate\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * API Key authentication implementation
 *
 * Provides Bearer token authentication for Weaviate using API keys.
 * This is the most common authentication method for Weaviate Cloud
 * and self-hosted instances with authentication enabled.
 *
 * The API key is sent as a Bearer token in the Authorization header
 * of all HTTP requests.
 *
 * @example Basic usage
 * ```php
 * use Weaviate\Auth\ApiKey;
 * use Weaviate\WeaviateClient;
 *
 * $auth = new ApiKey('your-api-key-here');
 * $client = WeaviateClient::connectToWeaviateCloud(
 *     'my-cluster.weaviate.network',
 *     $auth
 * );
 * ```
 *
 * @example With local instance
 * ```php
 * $auth = new ApiKey('your-local-api-key');
 * $client = WeaviateClient::connectToLocal('localhost:8080', $auth);
 * ```
 */
class ApiKey implements AuthInterface
{
    public function __construct(
        private readonly string $apiKey
    ) {
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
    }
}
