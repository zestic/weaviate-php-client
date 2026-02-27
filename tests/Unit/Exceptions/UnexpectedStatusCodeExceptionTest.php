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

namespace Weaviate\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\WeaviateBaseException;

class UnexpectedStatusCodeExceptionTest extends TestCase
{
    public function testCanCreateUnexpectedStatusCodeException(): void
    {
        $exception = new UnexpectedStatusCodeException('Server error', 500);

        $this->assertInstanceOf(WeaviateBaseException::class, $exception);
        $this->assertSame('Server error', $exception->getMessage());
        $this->assertSame(500, $exception->getStatusCode());
        $this->assertSame(500, $exception->getCode());

        $context = $exception->getContext();
        $this->assertSame(500, $context['status_code']);
    }

    public function testCanCreateWithResponse(): void
    {
        $response = [
            'body' => '{"error": "Internal server error"}',
            'headers' => ['Content-Type' => 'application/json']
        ];
        $exception = new UnexpectedStatusCodeException('Server error', 500, $response);

        $this->assertSame($response, $exception->getResponse());

        $context = $exception->getContext();
        $this->assertSame($response, $context['response']);
        $this->assertSame(500, $context['status_code']);
    }

    public function testCanCreateWithContext(): void
    {
        $context = ['operation' => 'create_collection', 'collection' => 'Article'];
        $exception = new UnexpectedStatusCodeException('Bad request', 400, null, $context);

        $resultContext = $exception->getContext();
        $this->assertSame('create_collection', $resultContext['operation']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame(400, $resultContext['status_code']);
    }

    public function testGetStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 422);

        $this->assertSame(422, $exception->getStatusCode());
    }

    public function testGetResponse(): void
    {
        $response = ['body' => 'Error response'];
        $exception = new UnexpectedStatusCodeException('Error', 400, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    public function testGetResponseReturnsNullWhenNotSet(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 400);

        $this->assertNull($exception->getResponse());
    }

    public function testFromResponse(): void
    {
        $message = 'Conflict error';
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('{"error": "Conflict"}');
        $mockResponse->method('getStatusCode')->willReturn(409);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);

        $context = ['operation' => 'update'];

        $exception = UnexpectedStatusCodeException::fromResponse($message, $mockResponse, $context);

        $this->assertStringContainsString($message, $exception->getMessage());
        $this->assertSame(409, $exception->getStatusCode());

        $resultContext = $exception->getContext();
        $this->assertSame('update', $resultContext['operation']);
        $this->assertSame(409, $resultContext['status_code']);
    }

    public function testFromResponseWithExplanation(): void
    {
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('Unauthorized');
        $mockResponse->method('getStatusCode')->willReturn(401);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        $exception = UnexpectedStatusCodeException::fromResponse('Auth failed', $mockResponse);

        $this->assertStringContainsString('Auth failed', $exception->getMessage());
        $this->assertSame(401, $exception->getStatusCode());
    }

    public function testFromResponseWithUnknownStatusCode(): void
    {
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('Unknown error');
        $mockResponse->method('getStatusCode')->willReturn(999);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        $exception = UnexpectedStatusCodeException::fromResponse('Unknown error', $mockResponse);

        $this->assertStringContainsString('Unknown error', $exception->getMessage());
        $this->assertSame(999, $exception->getStatusCode());
    }

    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new UnexpectedStatusCodeException('HTTP error', 500, null, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testFromResponseWithPreviousException(): void
    {
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('Error');
        $mockResponse->method('getStatusCode')->willReturn(500);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        $previous = new \Exception('Connection failed');
        $exception = UnexpectedStatusCodeException::fromResponse('Server error', $mockResponse, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithZeroStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 0);

        $this->assertSame(0, $exception->getStatusCode());
        $this->assertSame(0, $exception->getCode());
    }

    public function testFromResponseWithEmptyBody(): void
    {
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('');
        $mockResponse->method('getStatusCode')->willReturn(500);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn([]);

        $exception = UnexpectedStatusCodeException::fromResponse('Server error', $mockResponse);

        $this->assertSame(500, $exception->getStatusCode());
        $this->assertStringContainsString('Server error', $exception->getMessage());
    }

    public function testContextIncludesStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 418);

        $context = $exception->getContext();
        $this->assertSame(418, $context['status_code']);
    }

    public function testContextIncludesResponseWhenProvided(): void
    {
        $response = ['body' => 'test', 'headers' => []];
        $exception = new UnexpectedStatusCodeException('Error', 400, $response);

        $context = $exception->getContext();
        $this->assertSame($response, $context['response']);
    }

    public function testContextDoesNotIncludeResponseWhenNull(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 400, null);

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('response', $context);
    }
}
