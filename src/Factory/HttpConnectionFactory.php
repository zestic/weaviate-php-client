<?php

declare(strict_types=1);

namespace Weaviate\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Weaviate\Auth\AuthInterface;
use Weaviate\Retry\RetryHandler;
use Weaviate\Connection\HttpConnection;

class HttpConnectionFactory
{
    /**
     * Create an HttpConnection with sensible defaults for missing dependencies.
     *
     * @param string $baseUrl
     * @param AuthInterface|null $auth
     * @param array<string,string> $additionalHeaders
     * @param ClientInterface|null $httpClient
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param RetryHandler|null $retryHandler
     * @param LoggerInterface|null $logger
     *
     * @return HttpConnection
     */
    public static function create(
        string $baseUrl,
        ?AuthInterface $auth = null,
        array $additionalHeaders = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?RetryHandler $retryHandler = null,
        ?LoggerInterface $logger = null,
    ): HttpConnection {
        // Provide default implementations when none are supplied
        if ($httpClient === null) {
            $httpClient = new Client();
        }

        if ($requestFactory === null || $streamFactory === null) {
            $httpFactory = new HttpFactory();
            $requestFactory = $requestFactory ?? $httpFactory;
            $streamFactory = $streamFactory ?? $httpFactory;
        }

        return new HttpConnection(
            $baseUrl,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $auth,
            $additionalHeaders,
            $retryHandler,
            $logger
        );
    }
}
