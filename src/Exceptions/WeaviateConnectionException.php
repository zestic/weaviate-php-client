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

use Throwable;

/**
 * Is raised when a connection to Weaviate fails.
 *
 * This exception is thrown when the client cannot establish or maintain
 * a connection to the Weaviate server. Common causes include:
 * - Server is not running
 * - Network connectivity issues
 * - DNS resolution failures
 * - SSL/TLS certificate issues
 *
 * @example Handling connection errors
 * ```php
 * try {
 *     $client = WeaviateClient::connectToLocal('localhost:8080');
 *     $client->collections()->exists('Test');
 * } catch (WeaviateConnectionException $e) {
 *     echo "Connection failed: " . $e->getMessage();
 *
 *     // Check if it's a specific connection issue
 *     $context = $e->getContext();
 *     if (isset($context['curl_error'])) {
 *         echo "cURL error: " . $context['curl_error'];
 *     }
 * }
 * ```
 */
class WeaviateConnectionException extends WeaviateBaseException
{
    /**
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context (URL, curl errors, etc.)
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Failed to connect to Weaviate',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Create from a network/HTTP error
     *
     * @param string $url The URL that failed
     * @param string $error The error message
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromNetworkError(
        string $url,
        string $error,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Failed to connect to Weaviate at {$url}: {$error}",
            [
                'url' => $url,
                'network_error' => $error,
                'type' => 'network_error'
            ],
            $previous
        );
    }

    /**
     * Create from a timeout error
     *
     * @param string $url The URL that timed out
     * @param float $timeout The timeout value in seconds
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromTimeout(
        string $url,
        float $timeout,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Connection to Weaviate at {$url} timed out after {$timeout} seconds",
            [
                'url' => $url,
                'timeout' => $timeout,
                'type' => 'timeout'
            ],
            $previous
        );
    }

    /**
     * Create from SSL/TLS error
     *
     * @param string $url The URL with SSL issues
     * @param string $sslError The SSL error message
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function fromSslError(
        string $url,
        string $sslError,
        ?Throwable $previous = null
    ): self {
        return new self(
            "SSL/TLS error connecting to Weaviate at {$url}: {$sslError}",
            [
                'url' => $url,
                'ssl_error' => $sslError,
                'type' => 'ssl_error'
            ],
            $previous
        );
    }
}
