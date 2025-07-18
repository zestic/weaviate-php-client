<?php

declare(strict_types=1);

/*
 * Copyright 2024 Zestic
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
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\AuthInterface;
use Weaviate\Retry\RetryHandler;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\InsufficientPermissionsException;
use Weaviate\Exceptions\NotFoundException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

class HttpConnectionTest extends TestCase
{
    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     */
    public function testCanCreateConnection(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->assertInstanceOf(HttpConnection::class, $connection);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testCanMakeGetRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('__toString')->willReturn('{"result": "success"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'http://localhost:8080/v1/schema')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals(['result' => 'success'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     */
    public function testCanMakePostRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"id": "123"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', 'http://localhost:8080/v1/objects')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"name":"test"}')
            ->willReturn($requestStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturnSelf();

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->post('/v1/objects', ['name' => 'test']);

        $this->assertEquals(['id' => '123'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::applyAuth
     */
    public function testCanMakeGetRequestWithAuthentication(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $auth = $this->createMock(AuthInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $authenticatedRequest = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('__toString')->willReturn('{"result": "success"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'http://localhost:8080/v1/schema')
            ->willReturn($request);

        // Verify that auth is applied to the request
        $auth->expects($this->once())
            ->method('apply')
            ->with($request)
            ->willReturn($authenticatedRequest);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($authenticatedRequest)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            $auth,
            []
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals(['result' => 'success'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::head
     */
    public function testCanMakeHeadRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(200);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('HEAD', 'http://localhost:8080/v1/objects/Organization/123')
            ->willReturn($request);

        $request->method('withHeader')->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->head('/v1/objects/Organization/123');

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::head
     */
    public function testHeadRequestReturnsFalseForNotFound(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(404);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('HEAD', 'http://localhost:8080/v1/objects/Organization/nonexistent')
            ->willReturn($request);

        $request->method('withHeader')->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->head('/v1/objects/Organization/nonexistent');

        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::head
     */
    public function testHeadRequestReturnsFalseOnException(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('HEAD', 'http://localhost:8080/v1/objects/Organization/123')
            ->willReturn($request);

        $request->method('withHeader')->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willThrowException(new \Exception('Network error'));

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->head('/v1/objects/Organization/123');

        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestWithQueryParameters(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('__toString')->willReturn('{"result": "success"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'http://localhost:8080/v1/schema?param1=value1&param2=value2')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->get('/v1/schema', ['param1' => 'value1', 'param2' => 'value2']);

        $this->assertEquals(['result' => 'success'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestReturnsEmptyArrayForEmptyResponse(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('__toString')->willReturn('');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals([], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     */
    public function testPostRequestWithEmptyData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"id": "123"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', 'http://localhost:8080/v1/objects')
            ->willReturn($request);

        // Should not create stream for empty data
        $streamFactory->expects($this->never())
            ->method('createStream');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->post('/v1/objects', []);

        $this->assertEquals(['id' => '123'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::put
     */
    public function testCanMakePutRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"updated": true}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('PUT', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"name":"updated"}')
            ->willReturn($requestStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturnSelf();

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->put('/v1/objects/123', ['name' => 'updated']);

        $this->assertEquals(['updated' => true], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::put
     */
    public function testPutRequestWithEmptyData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"updated": true}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('PUT', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        // Should not create stream for empty data
        $streamFactory->expects($this->never())
            ->method('createStream');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->put('/v1/objects/123', []);

        $this->assertEquals(['updated' => true], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::patch
     */
    public function testCanMakePatchRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"patched": true}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('PATCH', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"name":"patched"}')
            ->willReturn($requestStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturnSelf();

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->patch('/v1/objects/123', ['name' => 'patched']);

        $this->assertEquals(['patched' => true], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::patch
     */
    public function testPatchRequestWithEmptyData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseStream);
        $responseStream->method('__toString')->willReturn('{"patched": true}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('PATCH', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        // Should not create stream for empty data
        $streamFactory->expects($this->never())
            ->method('createStream');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->patch('/v1/objects/123', []);

        $this->assertEquals(['patched' => true], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::delete
     */
    public function testCanMakeDeleteRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(204);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->delete('/v1/objects/123');

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::delete
     */
    public function testDeleteRequestReturnsFalseForErrorStatus(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(404);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://localhost:8080/v1/objects/123')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->delete('/v1/objects/123');

        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::deleteWithData
     */
    public function testCanMakeDeleteWithDataRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://localhost:8080/v1/objects')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"where":{"path":["id"],"operator":"Equal","valueText":"123"}}')
            ->willReturn($requestStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturnSelf();

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $data = ['where' => ['path' => ['id'], 'operator' => 'Equal', 'valueText' => '123']];
        $result = $connection->deleteWithData('/v1/objects', $data);

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::deleteWithData
     */
    public function testDeleteWithDataRequestWithEmptyData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(200);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://localhost:8080/v1/objects')
            ->willReturn($request);

        // Should not create stream for empty data
        $streamFactory->expects($this->never())
            ->method('createStream');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->deleteWithData('/v1/objects', []);

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::deleteWithData
     */
    public function testDeleteWithDataReturnsFalseForErrorStatus(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $response->method('getStatusCode')->willReturn(400);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $result = $connection->deleteWithData('/v1/objects', []);

        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::applyHeaders
     */
    public function testApplyAdditionalHeaders(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $stream->method('__toString')->willReturn('{"result": "success"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        // Verify that additional headers are applied
        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            ['X-Custom-Header' => 'custom-value', 'X-API-Key' => 'secret-key']
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals(['result' => 'success'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::handleErrorResponse
     */
    public function testGetRequestThrowsNotFoundExceptionFor404(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(404);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')->with('X-Request-URL')->willReturn('');
        $stream->method('__toString')->willReturn('{"error": "not found"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(NotFoundException::class);
        $connection->get('/v1/schema/NonExistent');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::handleErrorResponse
     */
    public function testGetRequestThrowsInsufficientPermissionsExceptionFor403(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(403);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')->with('X-Request-URL')->willReturn('');
        $stream->method('__toString')->willReturn('{"error": "forbidden"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(InsufficientPermissionsException::class);
        $connection->get('/v1/schema');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::handleErrorResponse
     */
    public function testGetRequestThrowsUnexpectedStatusCodeExceptionForOtherErrors(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(500);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')->with('X-Request-URL')->willReturn('');
        $stream->method('__toString')->willReturn('{"error": "internal server error"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(UnexpectedStatusCodeException::class);
        $connection->get('/v1/schema');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestThrowsWeaviateConnectionExceptionForNetworkError(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $networkException = new class ('Network error') extends \Exception implements NetworkExceptionInterface {
            public function getRequest(): \Psr\Http\Message\RequestInterface
            {
                throw new \RuntimeException('Not implemented');
            }
        };

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($networkException);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(WeaviateConnectionException::class);
        $connection->get('/v1/schema');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestThrowsWeaviateConnectionExceptionForRequestError(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestException = new class ('Request error') extends \Exception implements RequestExceptionInterface {
            public function getRequest(): \Psr\Http\Message\RequestInterface
            {
                throw new \RuntimeException('Not implemented');
            }
        };

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($requestException);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(WeaviateConnectionException::class);
        $connection->get('/v1/schema');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     */
    public function testPostRequestThrowsRuntimeExceptionForJsonEncodingFailure(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        // Create data that will fail JSON encoding (resource type)
        $resource = fopen('php://memory', 'r');
        $invalidData = ['resource' => $resource];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON data');
        $connection->post('/v1/objects', $invalidData);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::put
     */
    public function testPutRequestThrowsRuntimeExceptionForJsonEncodingFailure(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        // Create data that will fail JSON encoding (resource type)
        $resource = fopen('php://memory', 'r');
        $invalidData = ['resource' => $resource];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON data');
        $connection->put('/v1/objects/123', $invalidData);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::patch
     */
    public function testPatchRequestThrowsRuntimeExceptionForJsonEncodingFailure(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        // Create data that will fail JSON encoding (resource type)
        $resource = fopen('php://memory', 'r');
        $invalidData = ['resource' => $resource];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON data');
        $connection->patch('/v1/objects/123', $invalidData);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::deleteWithData
     */
    public function testDeleteWithDataThrowsRuntimeExceptionForJsonEncodingFailure(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        // Create data that will fail JSON encoding (resource type)
        $resource = fopen('php://memory', 'r');
        $invalidData = ['resource' => $resource];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode JSON data');
        $connection->deleteWithData('/v1/objects', $invalidData);

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestWithRetryHandler(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $retryHandler = $this->createMock(RetryHandler::class);

        $retryHandler->expects($this->once())
            ->method('execute')
            ->with('GET /v1/schema', $this->isCallable())
            ->willReturn(['result' => 'success']);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            [],
            $retryHandler
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals(['result' => 'success'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     */
    public function testPostRequestWithRetryHandler(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $retryHandler = $this->createMock(RetryHandler::class);

        $retryHandler->expects($this->once())
            ->method('execute')
            ->with('POST /v1/objects', $this->isCallable())
            ->willReturn(['id' => '123']);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            [],
            $retryHandler
        );

        $result = $connection->post('/v1/objects', ['name' => 'test']);

        $this->assertEquals(['id' => '123'], $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     * @covers \Weaviate\Connection\HttpConnection::handleErrorResponse
     */
    public function testPostRequestThrowsNotFoundExceptionFor404(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);
        $responseStream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(404);
        $response->method('getBody')->willReturn($responseStream);
        $response->method('getHeaderLine')->with('X-Request-URL')->willReturn('');
        $responseStream->method('__toString')->willReturn('{"error": "not found"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->willReturn($requestStream);

        $request->method('withBody')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(NotFoundException::class);
        $connection->post('/v1/objects', ['name' => 'test']);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::post
     */
    public function testPostRequestThrowsWeaviateConnectionExceptionForNetworkError(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $requestStream = $this->createMock(StreamInterface::class);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $streamFactory->expects($this->once())
            ->method('createStream')
            ->willReturn($requestStream);

        $request->method('withBody')->willReturnSelf();
        $request->method('withHeader')->willReturnSelf();

        $networkException = new class ('Network error') extends \Exception implements NetworkExceptionInterface {
            public function getRequest(): \Psr\Http\Message\RequestInterface
            {
                throw new \RuntimeException('Not implemented');
            }
        };

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException($networkException);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(WeaviateConnectionException::class);
        $connection->post('/v1/objects', ['name' => 'test']);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     */
    public function testCanCreateConnectionWithAllParameters(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $auth = $this->createMock(AuthInterface::class);
        $retryHandler = $this->createMock(RetryHandler::class);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            $auth,
            ['X-Custom-Header' => 'value'],
            $retryHandler
        );

        $this->assertInstanceOf(HttpConnection::class, $connection);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::handleErrorResponse
     */
    public function testHandleErrorResponseWithCustomUrl(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getStatusCode')->willReturn(500);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaderLine')->with('X-Request-URL')->willReturn('http://custom-url.com/v1/schema');
        $stream->method('__toString')->willReturn('{"error": "server error"}');

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn($request);

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory,
            null,
            []
        );

        $this->expectException(UnexpectedStatusCodeException::class);
        $connection->get('/v1/schema');
    }
}
