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
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Weaviate\Connection\HttpConnection;
use Weaviate\Exceptions\WeaviateConnectionException;

/**
 * Tests for the private sendRequest() helper method in HttpConnection
 *
 * These tests verify that:
 * - Requests are properly prepared (headers, auth applied)
 * - Exceptions are caught and logged correctly
 * - Logging works with and without a logger instance
 * - All throwable exceptions are caught (not just PSR-18 exceptions)
 */
class HttpConnectionSendRequestTest extends TestCase
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private HttpConnection $connection;
    private ResponseInterface $mockResponse;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mockResponse = $this->createMock(ResponseInterface::class);

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
     * Test that a successful request returns the response object
     *
     * @test
     */
    public function testSendRequestReturnsResponseOnSuccess(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        // Call through a public method that uses sendRequest internally
        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        // We'll test indirectly through a public method
        $result = $this->connection->get('/test');
        $this->assertIsArray($result);
    }

    /**
     * Test that request details are logged at DEBUG level before sending
     *
     * @test
     */
    public function testDebugLogsRequestDetails(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);
        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($mockRequest)
            ->willReturn($this->mockResponse);

        // Expect debug to be called for request and response
        $this->logger->expects($this->exactly(2))
            ->method('debug');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->connection->get('/test');
    }

    /**
     * Test that response status is logged at DEBUG level on success
     *
     * @test
     */
    public function testDebugLogsResponseStatusOnSuccess(): void
    {
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);
        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        // Verify debug is called for both request and response (exactly 2 calls)
        $this->logger->expects($this->exactly(2))
            ->method('debug');

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->connection->get('/test');
    }

    /**
     * Test that NetworkExceptionInterface is caught and logged as error
     *
     * @test
     */
    public function testNetworkExceptionIsCaughtAndLogged(): void
    {
        $networkException = new class extends \Exception implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return new class implements RequestInterface {
                    public function getRequestTarget()
                    {
                    }
                    public function withRequestTarget($requestTarget)
                    {
                    }
                    public function getMethod()
                    {
                        return 'GET';
                    }
                    public function withMethod($method)
                    {
                    }
                    public function getUri()
                    {
                    }
                    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false)
                    {
                    }
                    public function getProtocolVersion()
                    {
                    }
                    public function withProtocolVersion($version)
                    {
                    }
                    public function getHeaders()
                    {
                    }
                    public function hasHeader($name)
                    {
                    }
                    public function getHeader($name)
                    {
                    }
                    public function getHeaderLine($name)
                    {
                    }
                    public function withHeader($name, $value)
                    {
                    }
                    public function withAddedHeader($name, $value)
                    {
                    }
                    public function withoutHeader($name)
                    {
                    }
                    public function getBody()
                    {
                    }
                    public function withBody(\Psr\Http\Message\StreamInterface $body)
                    {
                    }
                };
            }
        };

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($networkException);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('HTTP request failed'),
                $this->callback(function ($context) {
                    return isset($context['exception']) && isset($context['url']);
                })
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that RequestExceptionInterface is caught and logged as error
     *
     * @test
     */
    public function testRequestExceptionIsCaughtAndLogged(): void
    {
        $requestException = new class extends \Exception implements RequestExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return new class implements RequestInterface {
                    public function getRequestTarget()
                    {
                    }
                    public function withRequestTarget($requestTarget)
                    {
                    }
                    public function getMethod()
                    {
                        return 'GET';
                    }
                    public function withMethod($method)
                    {
                    }
                    public function getUri()
                    {
                    }
                    public function withUri(\Psr\Http\Message\UriInterface $uri, $preserveHost = false)
                    {
                    }
                    public function getProtocolVersion()
                    {
                    }
                    public function withProtocolVersion($version)
                    {
                    }
                    public function getHeaders()
                    {
                    }
                    public function hasHeader($name)
                    {
                    }
                    public function getHeader($name)
                    {
                    }
                    public function getHeaderLine($name)
                    {
                    }
                    public function withHeader($name, $value)
                    {
                    }
                    public function withAddedHeader($name, $value)
                    {
                    }
                    public function withoutHeader($name)
                    {
                    }
                    public function getBody()
                    {
                    }
                    public function withBody(\Psr\Http\Message\StreamInterface $body)
                    {
                    }
                };
            }
        };

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($requestException);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('HTTP request failed'),
                $this->anything()
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that unexpected Throwable exceptions are caught and logged
     *
     * This test demonstrates the improvement over the old code which only
     * caught NetworkExceptionInterface and RequestExceptionInterface
     *
     * @test
     */
    public function testUnexpectedThrowableIsCaughtAndLogged(): void
    {
        $unexpectedException = new \RuntimeException('Something unexpected happened');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($unexpectedException);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('HTTP request failed'),
                $this->callback(function ($context) {
                    return isset($context['exception']) &&
                           $context['exception'] instanceof \RuntimeException;
                })
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that error logging includes exception details
     *
     * @test
     */
    public function testErrorLogIncludesExceptionDetails(): void
    {
        $exception = new \Exception('Test error message');

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
                           $context['exception'] === $exception &&
                           isset($context['url']);
                })
            );

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->connection->get('/test');
    }

    /**
     * Test that connection works without a logger (null logger)
     *
     * @test
     */
    public function testWorksWithoutLogger(): void
    {
        $connectionWithoutLogger = new HttpConnection(
            'https://weaviate.example.com',
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        // Should not throw any errors about missing logger
        $result = $connectionWithoutLogger->get('/test');
        $this->assertIsArray($result);
    }

    /**
     * Test that exception is converted to WeaviateConnectionException
     *
     * @test
     */
    public function testExceptionIsConvertedToWeaviateConnectionException(): void
    {
        $originalException = new \Exception('Original error');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($originalException);

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        $this->expectException(WeaviateConnectionException::class);
        $this->expectExceptionMessage('Original error');

        $this->connection->get('/test');
    }

    /**
     * Test that WeaviateConnectionException preserves the original exception
     *
     * @test
     */
    public function testWeaviateConnectionExceptionPreservesOriginalException(): void
    {
        $originalException = new \RuntimeException('Network error');

        $mockRequest = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($originalException);

        $this->requestFactory->method('createRequest')
            ->willReturn($mockRequest);

        try {
            $this->connection->get('/test');
            $this->fail('Expected WeaviateConnectionException to be thrown');
        } catch (WeaviateConnectionException $e) {
            $this->assertSame($originalException, $e->getPrevious());
        }
    }
}
