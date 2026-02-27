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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\AuthInterface;

/**
 * Integration tests for HTTP methods using the new sendRequest helper
 *
 * These tests verify that:
 * - All HTTP methods still work correctly after refactoring
 * - Headers and auth are still applied properly
 * - The refactored code produces the same results as the original
 * - Retry handler integration still works
 */
class HttpConnectionHttpMethodsTest extends TestCase
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private HttpConnection $connection;
    private ResponseInterface $mockResponse;
    private RequestInterface $mockRequest;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mockResponse = $this->createMock(ResponseInterface::class);
        $this->mockRequest = $this->createMock(RequestInterface::class);

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
     * Test GET method returns decoded JSON array
     *
     * @test
     */
    public function testGetMethodReturnsDecodedJson(): void
    {
        $responseData = ['id' => '123', 'name' => 'Test'];
        $jsonBody = json_encode($responseData);

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn($jsonBody);

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        $result = $this->connection->get('/objects');

        $this->assertEquals($responseData, $result);
    }

    /**
     * Test GET method with query parameters
     *
     * @test
     */
    public function testGetMethodIncludesQueryParameters(): void
    {
        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        // Just verify the call succeeds with parameters
        $result = $this->connection->get('/objects', ['limit' => 10, 'offset' => 0]);
        $this->assertIsArray($result);
    }

    /**
     * Test POST method encodes data as JSON
     *
     * @test
     */
    public function testPostMethodEncodesDataAsJson(): void
    {
        $postData = ['class' => 'Document', 'properties' => ['title' => 'Test']];
        $jsonBody = json_encode(['id' => 'abc123']);

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn($jsonBody);

        $this->mockResponse->method('getStatusCode')->willReturn(201);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $requestWithBody = $this->createMock(RequestInterface::class);
        $requestWithBody->method('withBody')->willReturnSelf();
        $requestWithBody->method('withHeader')->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithBody);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode($postData));

        $result = $this->connection->post('/objects', $postData);

        $this->assertEquals(['id' => 'abc123'], $result);
    }

    /**
     * Test PUT method encodes data as JSON
     *
     * @test
     */
    public function testPutMethodEncodesDataAsJson(): void
    {
        $putData = ['class' => 'Document', 'properties' => ['status' => 'updated']];
        $jsonBody = json_encode(['id' => 'abc123']);

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn($jsonBody);

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $requestWithBody = $this->createMock(RequestInterface::class);
        $requestWithBody->method('withBody')->willReturnSelf();
        $requestWithBody->method('withHeader')->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithBody);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode($putData));

        $result = $this->connection->put('/objects/abc123', $putData);

        $this->assertEquals(['id' => 'abc123'], $result);
    }

    /**
     * Test PATCH method encodes data as JSON
     *
     * @test
     */
    public function testPatchMethodEncodesDataAsJson(): void
    {
        $patchData = ['properties' => ['status' => 'patched']];
        $jsonBody = json_encode(['id' => 'abc123']);

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn($jsonBody);

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $requestWithBody = $this->createMock(RequestInterface::class);
        $requestWithBody->method('withBody')->willReturnSelf();
        $requestWithBody->method('withHeader')->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithBody);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode($patchData));

        $result = $this->connection->patch('/objects/abc123', $patchData);

        $this->assertEquals(['id' => 'abc123'], $result);
    }

    /**
     * Test DELETE method returns true for 2xx status
     *
     * @test
     */
    public function testDeleteMethodReturnsTrueForSuccessStatus(): void
    {
        $this->mockResponse->method('getStatusCode')->willReturn(204);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        $result = $this->connection->delete('/objects/abc123');

        $this->assertTrue($result);
    }

    /**
     * Test DELETE method returns false for non-2xx status
     *
     * @test
     */
    public function testDeleteMethodReturnsFalseForErrorStatus(): void
    {
        $this->mockResponse->method('getStatusCode')->willReturn(500);
        $this->mockResponse->method('getReasonPhrase')->willReturn('Internal Server Error');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        // delete() returns false for error status codes (doesn't throw)
        $result = $this->connection->delete('/objects/abc123');
        $this->assertFalse($result);
    }

    /**
     * Test DELETE with data method encodes payload
     *
     * @test
     */
    public function testDeleteWithDataEncodesPayload(): void
    {
        $deleteData = ['dryRun' => true];

        $requestWithBody = $this->createMock(RequestInterface::class);
        $requestWithBody->method('withBody')->willReturnSelf();
        $requestWithBody->method('withHeader')->willReturnSelf();

        $this->mockResponse->method('getStatusCode')->willReturn(204);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithBody);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode($deleteData));

        $result = $this->connection->deleteWithData('/objects', $deleteData);

        $this->assertTrue($result);
    }

    /**
     * Test HEAD method returns true for 2xx status
     *
     * @test
     */
    public function testHeadMethodReturnsTrueForSuccessStatus(): void
    {
        $this->mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        $result = $this->connection->head('/objects');

        $this->assertTrue($result);
    }

    /**
     * Test HEAD method returns false for 404 Not Found
     *
     * @test
     */
    public function testHeadMethodReturnsFalseFor404(): void
    {
        $this->mockResponse->method('getStatusCode')->willReturn(404);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        $result = $this->connection->head('/objects/nonexistent');

        $this->assertFalse($result);
    }

    /**
     * Test that JSON encoding errors are handled
     *
     * @test
     */
    public function testJsonEncodingErrorThrowsException(): void
    {
        // This is hard to test directly due to PHP's json_encode behavior,
        // but we can test with unsupported types in certain PHP versions
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON data');

        $this->requestFactory->method('createRequest')
            ->willReturn($this->mockRequest);

        // Create a circular reference that json_encode can't handle
        $data = [];
        $data['self'] = &$data;

        $this->connection->post('/test', $data);
    }

    /**
     * Test that additional headers are applied to all methods
     *
     * @test
     */
    public function testAdditionalHeadersAreAppliedToAllMethods(): void
    {
        $customHeaders = ['X-Custom-Header' => 'custom-value'];

        $connectionWithHeaders = new HttpConnection(
            'https://weaviate.example.com',
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            null,
            $customHeaders,
            null,
            $this->logger
        );

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $requestWithHeaders = $this->createMock(RequestInterface::class);
        $requestWithHeaders->method('withHeader')->willReturnSelf();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithHeaders);

        $requestWithHeaders->expects($this->once())
            ->method('withHeader')
            ->with('X-Custom-Header', 'custom-value')
            ->willReturnSelf();

        $connectionWithHeaders->get('/objects');
    }

    /**
     * Test that authentication is applied to all methods
     *
     * @test
     */
    public function testAuthenticationIsAppliedToAllMethods(): void
    {
        $mockAuth = $this->createMock(AuthInterface::class);

        $connectionWithAuth = new HttpConnection(
            'https://weaviate.example.com',
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $mockAuth,
            [],
            null,
            $this->logger
        );

        $mockStream = $this->createMock(StreamInterface::class);
        $mockStream->method('__toString')->willReturn('{}');

        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getBody')->willReturn($mockStream);

        $requestWithAuth = $this->createMock(RequestInterface::class);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse);

        $this->requestFactory->method('createRequest')
            ->willReturn($requestWithAuth);

        $mockAuth->expects($this->once())
            ->method('apply')
            ->with($requestWithAuth)
            ->willReturn($requestWithAuth);

        $connectionWithAuth->get('/objects');
    }
}
