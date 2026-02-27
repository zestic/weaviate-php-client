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

namespace Weaviate\Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Weaviate\Connection\HttpConnection;
use Weaviate\Exceptions\WeaviateConnectionException;

/**
 * Tests for exception handling improvements in HttpConnection
 *
 * These tests specifically demonstrate that the refactored code catches
 * ALL exceptions (not just PSR-18 specific exceptions) and logs them appropriately.
 *
 * This addresses the gap in the original code where non-PSR-18 exceptions
 * would not be caught or logged.
 */
class HttpConnectionExceptionHandlingTest extends TestCase
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private HttpConnection $connection;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->connection = new HttpConnection(
            'https://weaviate.example.com',
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            null,
            [],
            null,
            $this->logger
        );
    }

    /**
     * Test that RuntimeException from HTTP client is caught and logged
     *
     * Old code would let this through uncaught.
     * New code catches it and logs it.
     *
     * @test
     */
    public function testRuntimeExceptionFromHttpClientIsCaughtAndLogged(): void
    {
        $exception = new \RuntimeException('HTTP client internal error');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('HTTP request failed'),
                $this->anything()
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        try {
            $this->connection->get('/test');
            $this->fail('Expected WeaviateConnectionException to be thrown');
        } catch (WeaviateConnectionException $e) {
            $this->assertStringContainsString('HTTP client internal error', $e->getMessage());
        }
    }

    /**
     * Test that InvalidArgumentException is caught and logged
     *
     * @test
     */
    public function testInvalidArgumentExceptionIsCaughtAndLogged(): void
    {
        $exception = new \InvalidArgumentException('Invalid parameter passed to HTTP client');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that LogicException is caught and logged
     *
     * @test
     */
    public function testLogicExceptionIsCaughtAndLogged(): void
    {
        $exception = new \LogicException('HTTP client in invalid state');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that custom exceptions are caught and logged
     *
     * @test
     */
    public function testCustomExceptionIsCaughtAndLogged(): void
    {
        $exception = new class extends \Exception {
            public function __construct()
            {
                parent::__construct('Custom HTTP error');
            }
        };

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->anything(),
                $this->callback(function ($context) use ($exception) {
                    return isset($context['exception']) &&
                           $context['exception'] === $exception;
                })
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that logging context includes URL for debugging
     *
     * @test
     */
    public function testErrorLogContextIncludesUrl(): void
    {
        $exception = new \Exception('Test error');
        $expectedUrl = 'https://weaviate.example.com/test';

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->anything(),
                $this->callback(function ($context) use ($expectedUrl) {
                    return isset($context['url']) &&
                           strpos($context['url'], '/test') !== false;
                })
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that all HTTP methods (GET, POST, PUT, PATCH, DELETE) catch exceptions
     *
     * @test
     * @dataProvider httpMethodsProvider
     */
    public function testAllHttpMethodsCatchExceptions(string $method, callable $methodCall): void
    {
        $exception = new \RuntimeException('HTTP error');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->streamFactory->method('createStream')
            ->willReturn($this->createMock(\Psr\Http\Message\StreamInterface::class));

        $this->expectException(WeaviateConnectionException::class);
        $methodCall($this->connection);
    }

    /**
     * Data provider for HTTP methods
     */
    public static function httpMethodsProvider(): array
    {
        return [
            'GET' => [
                'GET',
                fn($connection) => $connection->get('/test')
            ],
            'POST' => [
                'POST',
                fn($connection) => $connection->post('/test', [])
            ],
            'PUT' => [
                'PUT',
                fn($connection) => $connection->put('/test', [])
            ],
            'PATCH' => [
                'PATCH',
                fn($connection) => $connection->patch('/test', [])
            ],
            'DELETE' => [
                'DELETE',
                fn($connection) => $connection->delete('/test')
            ],
            'DELETE with data' => [
                'DELETE with data',
                fn($connection) => $connection->deleteWithData('/test', [])
            ],
            'HEAD' => [
                'HEAD',
                fn($connection) => $connection->head('/test')
            ],
        ];
    }

    /**
     * Test that error message is preserved when converting exception
     *
     * @test
     */
    public function testErrorMessageIsPreservedInConvertedException(): void
    {
        $originalMessage = 'Original error: connection refused';
        $exception = new \Exception($originalMessage);

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($exception);

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        try {
            $this->connection->get('/test');
            $this->fail('Expected exception to be thrown');
        } catch (WeaviateConnectionException $e) {
            $this->assertStringContainsString($originalMessage, $e->getMessage());
        }
    }

    /**
     * Test that multiple successive exceptions are all caught and logged
     *
     * @test
     */
    public function testMultipleExceptionsAreAllCaughtAndLogged(): void
    {
        $mockRequest = $this->createMock(RequestInterface::class);

        $this->logger->expects($this->exactly(3))
            ->method('error');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        // First exception
        $this->httpClient->expects($this->atLeastOnce())
            ->method('sendRequest')
            ->willThrowException(new \RuntimeException('Error 1'));

        try {
            $this->connection->get('/test1');
        } catch (WeaviateConnectionException $e) {
            // Expected
        }

        // Create a fresh mock for the second call
        $httpClient2 = $this->createMock(ClientInterface::class);
        $httpClient2->method('sendRequest')
            ->willThrowException(new \RuntimeException('Error 2'));

        $connection2 = new HttpConnection(
            'https://weaviate.example.com',
            $httpClient2,
            $this->requestFactory,
            $this->streamFactory,
            null,
            [],
            null,
            $this->logger
        );

        try {
            $connection2->get('/test2');
        } catch (WeaviateConnectionException $e) {
            // Expected
        }

        // Create a fresh mock for the third call
        $httpClient3 = $this->createMock(ClientInterface::class);
        $httpClient3->method('sendRequest')
            ->willThrowException(new \RuntimeException('Error 3'));

        $connection3 = new HttpConnection(
            'https://weaviate.example.com',
            $httpClient3,
            $this->requestFactory,
            $this->streamFactory,
            null,
            [],
            null,
            $this->logger
        );

        try {
            $connection3->get('/test3');
        } catch (WeaviateConnectionException $e) {
            // Expected
        }
    }
}
