<?php

declare(strict_types=1);

namespace Weaviate\Factory;

use Weaviate\Auth\AuthInterface;
use Weaviate\Exceptions\WeaviateInvalidInputException;
use Weaviate\Factory\HttpConnectionFactory;
use Weaviate\Retry\RetryHandler;
use Weaviate\WeaviateClient;

/**
 * Factory responsible for creating configured `WeaviateClient` instances.
 *
 * This replaces the old static helper methods that previously lived on
 * `WeaviateClient`. Since the project is pre-1.0 we are free to make this
 * breaking change and remove the old helpers entirely.
 */
class WeaviateClientFactory
{
    /**
     * Create a client for a local Weaviate instance.
     *
     * @param string $host host with optional port ("localhost:8080"). A
     *                     scheme will be prepended if missing.
     * @param AuthInterface|null $auth optional authentication credentials.
     *
     * @return WeaviateClient
     */
    public static function connectToLocal(string $host = 'localhost:8080', ?AuthInterface $auth = null): WeaviateClient
    {
        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'http://' . $host;
        }

        $retryHandler = RetryHandler::forConnection();

        $connection = HttpConnectionFactory::create(
            $host,
            $auth,
            [],
            null,
            null,
            null,
            $retryHandler,
            null
        );

        return new WeaviateClient($connection, $auth);
    }

    /**
     * Create a client for a Weaviate Cloud (WCD) cluster.
     *
     * @param string $clusterUrl cluster hostname or URL
     * @param AuthInterface $auth authentication credentials (required for WCD)
     * @return WeaviateClient
     */
    public static function connectToWeaviateCloud(string $clusterUrl, AuthInterface $auth): WeaviateClient
    {
        $parsed = self::parseWeaviateCloudUrl($clusterUrl);
        $httpsUrl = 'https://' . $parsed;

        $retryHandler = RetryHandler::forConnection();

        $connection = HttpConnectionFactory::create(
            $httpsUrl,
            $auth,
            [],
            null,
            null,
            null,
            $retryHandler,
            null
        );

        return new WeaviateClient($connection, $auth);
    }

    /**
     * Create a client using fully-custom parameters.
     *
     * @param string $host
     * @param int $port
     * @param bool $secure
     * @param AuthInterface|null $auth
     * @param array<string,string> $headers
     * @return WeaviateClient
     */
    public static function connectToCustom(
        string $host,
        int $port = 8080,
        bool $secure = false,
        ?AuthInterface $auth = null,
        array $headers = []
    ): WeaviateClient {
        if ($port < 1 || $port > 65535) {
            throw WeaviateInvalidInputException::forParameter(
                'port',
                $port,
                'Port must be between 1 and 65535'
            );
        }

        $scheme = $secure ? 'https' : 'http';
        $url = "{$scheme}://{$host}:{$port}";

        $retryHandler = RetryHandler::forConnection();
        $connection = HttpConnectionFactory::create(
            $url,
            $auth,
            $headers,
            null,
            null,
            null,
            $retryHandler,
            null
        );

        return new WeaviateClient($connection, $auth);
    }

    /**
     * Normalize a cloud URL by stripping scheme, trailing slashes and paths.
     *
     * @param string $clusterUrl
     * @return string
     */
    private static function parseWeaviateCloudUrl(string $clusterUrl): string
    {
        if (str_starts_with($clusterUrl, 'http://') || str_starts_with($clusterUrl, 'https://')) {
            $parsed = parse_url($clusterUrl);
            if ($parsed === false || !isset($parsed['host'])) {
                throw new \InvalidArgumentException('Invalid cluster URL provided');
            }
            $clusterUrl = $parsed['host'];
        }

        return rtrim($clusterUrl, '/');
    }
}
