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
use Psr\Http\Client\ClientInterface;
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
}
