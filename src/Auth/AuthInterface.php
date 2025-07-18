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
 * Interface for authentication mechanisms
 *
 * Defines the contract for authentication implementations in the Weaviate PHP client.
 * Authentication implementations modify HTTP requests to include the necessary
 * credentials for accessing Weaviate instances.
 *
 * Current implementations:
 * - ApiKey: Bearer token authentication for API keys
 *
 * Future implementations may include:
 * - OAuth2: OAuth 2.0 authentication
 * - OIDC: OpenID Connect authentication
 * - Custom: Custom authentication schemes
 *
 * @example Implementing custom authentication
 * ```php
 * class CustomAuth implements AuthInterface
 * {
 *     public function __construct(private string $token) {}
 *
 *     public function apply(RequestInterface $request): RequestInterface
 *     {
 *         return $request->withHeader('X-Custom-Auth', $this->token);
 *     }
 * }
 * ```
 */
interface AuthInterface
{
    /**
     * Apply authentication to a request
     *
     * @param RequestInterface $request The HTTP request
     * @return RequestInterface The modified request with authentication
     */
    public function apply(RequestInterface $request): RequestInterface;
}
