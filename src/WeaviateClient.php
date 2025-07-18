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

namespace Weaviate;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Weaviate\Auth\AuthInterface;
use Weaviate\Collections\Collections;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Connection\HttpConnection;
use Weaviate\Schema\Schema;
use Weaviate\Retry\RetryHandler;
use Weaviate\Exceptions\WeaviateInvalidInputException;

/**
 * Main Weaviate client class
 *
 * The primary entry point for interacting with Weaviate vector database.
 * Provides convenient static methods for connecting to different Weaviate deployments
 * and access to all Weaviate APIs through a fluent interface.
 *
 * @example Basic usage with local Weaviate
 * ```php
 * use Weaviate\WeaviateClient;
 * use Weaviate\Auth\ApiKey;
 *
 * // Connect to local Weaviate instance
 * $client = WeaviateClient::connectToLocal('localhost:18080');
 *
 * // Work with collections
 * $collections = $client->collections();
 * if (!$collections->exists('Article')) {
 *     $collections->create('Article', [
 *         'properties' => [
 *             ['name' => 'title', 'dataType' => ['text']],
 *             ['name' => 'content', 'dataType' => ['text']],
 *         ]
 *     ]);
 * }
 *
 * // Work with data
 * $article = $collections->get('Article');
 * $result = $article->data()->create([
 *     'title' => 'My Article',
 *     'content' => 'Article content here...'
 * ]);
 * ```
 *
 * @example Weaviate Cloud connection
 * ```php
 * $client = WeaviateClient::connectToWeaviateCloud(
 *     'my-cluster.weaviate.network',
 *     new ApiKey('your-wcd-api-key')
 * );
 * ```
 *
 * @example Custom deployment with headers
 * ```php
 * $client = WeaviateClient::connectToCustom(
 *     'my-server.com',
 *     9200,
 *     true, // HTTPS
 *     new ApiKey('api-key'),
 *     ['X-OpenAI-Api-Key' => 'your-openai-key']
 * );
 * ```
 */
class WeaviateClient
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly ?AuthInterface $auth = null
    ) {
    }

    /**
     * Connect to a local Weaviate instance
     *
     * @param string $host Host and port (e.g., 'localhost:8080', 'localhost:18080')
     * @param AuthInterface|null $auth Optional authentication
     * @return self
     */
    public static function connectToLocal(
        string $host = 'localhost:8080',
        ?AuthInterface $auth = null
    ): self {
        // Ensure the host has a scheme
        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'http://' . $host;
        }

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create retry handler optimized for local connections
        $retryHandler = RetryHandler::forConnection();

        // Create connection
        $connection = new HttpConnection(
            $host,
            $httpClient,
            $httpFactory,
            $httpFactory,
            $auth,
            [],
            $retryHandler
        );

        return new self($connection, $auth);
    }

    /**
     * Connect to a Weaviate Cloud (WCD) instance
     *
     * @param string $clusterUrl The WCD cluster URL or hostname (e.g., 'my-cluster.weaviate.network')
     * @param AuthInterface $auth Authentication credentials (required for Weaviate Cloud)
     * @return self
     */
    public static function connectToWeaviateCloud(
        string $clusterUrl,
        AuthInterface $auth
    ): self {
        // Parse the cluster URL to handle common cases
        $parsedUrl = self::parseWeaviateCloudUrl($clusterUrl);

        // Weaviate Cloud always uses HTTPS on port 443
        $httpsUrl = 'https://' . $parsedUrl;

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create retry handler optimized for cloud connections
        $retryHandler = RetryHandler::forConnection();

        // Create connection with authentication (required for WCD)
        $connection = new HttpConnection(
            $httpsUrl,
            $httpClient,
            $httpFactory,
            $httpFactory,
            $auth,
            [],
            $retryHandler
        );

        return new self($connection, $auth);
    }

    /**
     * Connect to a Weaviate instance with custom connection parameters
     *
     * @param string $host The host to connect to (e.g., 'localhost', 'my-server.com')
     * @param int $port The port to connect to (e.g., 8080, 443)
     * @param bool $secure Whether to use HTTPS (true) or HTTP (false)
     * @param AuthInterface|null $auth Optional authentication credentials
     * @param array<string, string> $headers Additional headers to include in requests
     * @return self
     */
    public static function connectToCustom(
        string $host,
        int $port = 8080,
        bool $secure = false,
        ?AuthInterface $auth = null,
        array $headers = []
    ): self {
        // Validate port
        if ($port < 1 || $port > 65535) {
            throw WeaviateInvalidInputException::forParameter(
                'port',
                $port,
                'Port must be between 1 and 65535'
            );
        }

        // Build the URL
        $scheme = $secure ? 'https' : 'http';
        $url = "{$scheme}://{$host}:{$port}";

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create retry handler optimized for custom connections
        $retryHandler = RetryHandler::forConnection();

        // Create connection
        $connection = new HttpConnection(
            $url,
            $httpClient,
            $httpFactory,
            $httpFactory,
            $auth,
            $headers,
            $retryHandler
        );

        return new self($connection, $auth);
    }

    /**
     * Parse Weaviate Cloud cluster URL to handle common formats
     *
     * @param string $clusterUrl Raw cluster URL input
     * @return string Cleaned cluster hostname
     */
    private static function parseWeaviateCloudUrl(string $clusterUrl): string
    {
        // Handle the common case of copy/pasting a URL instead of the hostname
        if (str_starts_with($clusterUrl, 'http://') || str_starts_with($clusterUrl, 'https://')) {
            $parsed = parse_url($clusterUrl);
            if ($parsed === false || !isset($parsed['host'])) {
                throw new \InvalidArgumentException('Invalid cluster URL provided');
            }
            $clusterUrl = $parsed['host'];
        }

        // Remove any trailing slashes or paths
        $clusterUrl = rtrim($clusterUrl, '/');

        return $clusterUrl;
    }

    /**
     * Get the authentication instance
     */
    public function getAuth(): ?AuthInterface
    {
        return $this->auth;
    }

    /**
     * Get collections API
     */
    public function collections(): Collections
    {
        return new Collections($this->connection);
    }

    /**
     * Get schema API
     */
    public function schema(): Schema
    {
        return new Schema($this->connection);
    }
}
