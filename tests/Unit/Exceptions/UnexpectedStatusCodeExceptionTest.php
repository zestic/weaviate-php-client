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
    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
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

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
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

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['operation' => 'create_collection', 'collection' => 'Article'];
        $exception = new UnexpectedStatusCodeException('Bad request', 400, null, $context);

        $resultContext = $exception->getContext();
        $this->assertSame('create_collection', $resultContext['operation']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame(400, $resultContext['status_code']);
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::getStatusCode
     */
    public function testGetStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 422);

        $this->assertSame(422, $exception->getStatusCode());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::getResponse
     */
    public function testGetResponse(): void
    {
        $response = ['body' => 'Error response'];
        $exception = new UnexpectedStatusCodeException('Error', 400, $response);

        $this->assertSame($response, $exception->getResponse());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::getResponse
     */
    public function testGetResponseReturnsNullWhenNotSet(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 400);

        $this->assertNull($exception->getResponse());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponse(): void
    {
        $statusCode = 409;
        $response = [
            'body' => '{"error": "Conflict"}',
            'headers' => ['Content-Type' => 'application/json']
        ];
        $context = ['operation' => 'update'];

        $exception = UnexpectedStatusCodeException::fromResponse($statusCode, $response, $context);

        $this->assertStringContainsString('HTTP 409', $exception->getMessage());
        $this->assertStringContainsString('Conflict', $exception->getMessage());
        $this->assertSame($statusCode, $exception->getStatusCode());
        $this->assertSame($response, $exception->getResponse());

        $resultContext = $exception->getContext();
        $this->assertSame('update', $resultContext['operation']);
        $this->assertSame($statusCode, $resultContext['status_code']);
        $this->assertSame($response, $resultContext['response']);
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithExplanation(): void
    {
        $exception = UnexpectedStatusCodeException::fromResponse(401, ['body' => 'Unauthorized']);

        $this->assertStringContainsString('HTTP 401', $exception->getMessage());
        $this->assertStringContainsString('Authentication is required or has failed', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithUnknownStatusCode(): void
    {
        $exception = UnexpectedStatusCodeException::fromResponse(999, ['body' => 'Unknown error']);

        $this->assertStringContainsString('HTTP 999', $exception->getMessage());
        $this->assertStringNotContainsString('Authentication', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new UnexpectedStatusCodeException('HTTP error', 500, null, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithPreviousException(): void
    {
        $previous = new \Exception('Connection failed');
        $exception = UnexpectedStatusCodeException::fromResponse(500, ['body' => 'Error'], [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testWithZeroStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 0);

        $this->assertSame(0, $exception->getStatusCode());
        $this->assertSame(0, $exception->getCode());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithEmptyResponse(): void
    {
        $exception = UnexpectedStatusCodeException::fromResponse(500, []);

        $this->assertSame([], $exception->getResponse());
        $this->assertStringContainsString('HTTP 500', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithNullResponse(): void
    {
        $exception = UnexpectedStatusCodeException::fromResponse(404, null);

        $this->assertNull($exception->getResponse());
        $this->assertStringContainsString('HTTP 404', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::fromResponse
     */
    public function testFromResponseWithCommonStatusCodes(): void
    {
        $testCases = [
            400 => 'Bad Request: The request was invalid or malformed',
            401 => 'Unauthorized: Authentication is required or has failed',
            403 => 'Forbidden: Insufficient permissions to perform this operation',
            404 => 'Not Found: The requested resource does not exist',
            409 => 'Conflict: The request conflicts with the current state of the resource',
            413 => 'Payload Too Large: Try to decrease the batch size',
            422 => 'Unprocessable Entity: The request was well-formed but contains semantic errors',
            429 => 'Too Many Requests: Rate limit exceeded',
            500 => 'Internal Server Error: An unexpected error occurred on the server',
            502 => 'Bad Gateway: The server received an invalid response from an upstream server',
            503 => 'Service Unavailable: The server is temporarily unavailable',
            504 => 'Gateway Timeout: The server did not receive a timely response from an upstream server'
        ];

        foreach ($testCases as $statusCode => $expectedText) {
            $exception = UnexpectedStatusCodeException::fromResponse($statusCode, ['body' => 'Error']);
            
            $this->assertStringContainsString("HTTP {$statusCode}", $exception->getMessage());
            $this->assertStringContainsString($expectedText, $exception->getMessage());
            $this->assertSame($statusCode, $exception->getStatusCode());
        }
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testContextIncludesStatusCode(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 418);

        $context = $exception->getContext();
        $this->assertSame(418, $context['status_code']);
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testContextIncludesResponseWhenProvided(): void
    {
        $response = ['body' => 'test', 'headers' => []];
        $exception = new UnexpectedStatusCodeException('Error', 400, $response);

        $context = $exception->getContext();
        $this->assertSame($response, $context['response']);
    }

    /**
     * @covers \Weaviate\Exceptions\UnexpectedStatusCodeException::__construct
     */
    public function testContextDoesNotIncludeResponseWhenNull(): void
    {
        $exception = new UnexpectedStatusCodeException('Error', 400, null);

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('response', $context);
    }
}
