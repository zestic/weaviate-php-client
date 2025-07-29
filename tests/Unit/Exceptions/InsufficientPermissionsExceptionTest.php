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
use Weaviate\Exceptions\InsufficientPermissionsException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;

class InsufficientPermissionsExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testCanCreateInsufficientPermissionsException(): void
    {
        $exception = new InsufficientPermissionsException();

        $this->assertInstanceOf(UnexpectedStatusCodeException::class, $exception);
        $this->assertSame('Insufficient permissions to perform this operation', $exception->getMessage());
        $this->assertSame(403, $exception->getStatusCode());

        $context = $exception->getContext();
        $this->assertSame('insufficient_permissions', $context['error_type']);
        $this->assertSame(403, $context['status_code']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testCanCreateWithCustomMessage(): void
    {
        $message = 'Access denied to collection';
        $exception = new InsufficientPermissionsException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(403, $exception->getStatusCode());
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testCanCreateWithResponse(): void
    {
        $response = [
            'body' => '{"error": "Access denied"}',
            'headers' => ['Content-Type' => 'application/json']
        ];
        $exception = new InsufficientPermissionsException('Access denied', $response);

        $this->assertSame($response, $exception->getResponse());

        $context = $exception->getContext();
        $this->assertSame($response, $context['response']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['user_id' => 'user123', 'resource' => 'collection'];
        $exception = new InsufficientPermissionsException('Access denied', null, $context);

        $resultContext = $exception->getContext();
        $this->assertSame('user123', $resultContext['user_id']);
        $this->assertSame('collection', $resultContext['resource']);
        $this->assertSame('insufficient_permissions', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testCanCreateWithCustomContext(): void
    {
        $message = 'Access denied to resource';
        $context = ['user_role' => 'reader', 'resource' => 'collections/Article'];
        $exception = new InsufficientPermissionsException($message, null, $context);

        $this->assertStringContainsString($message, $exception->getMessage());
        $this->assertSame(403, $exception->getStatusCode());

        $resultContext = $exception->getContext();
        $this->assertSame('reader', $resultContext['user_role']);
        $this->assertSame('collections/Article', $resultContext['resource']);
        $this->assertSame('insufficient_permissions', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::fromResponse
     */
    public function testFromResponse(): void
    {
        $message = 'Access denied';
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(403);
        $mockResponse->method('getBody')->willReturn('{"error": "Forbidden"}');
        $mockResponse->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);

        $context = ['operation' => 'delete'];

        $exception = InsufficientPermissionsException::fromResponse($message, $mockResponse, $context);

        $this->assertStringContainsString($message, $exception->getMessage());
        $this->assertSame(403, $exception->getStatusCode());

        $resultContext = $exception->getContext();
        $this->assertSame('delete', $resultContext['operation']);
        $this->assertSame('insufficient_permissions', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forRbacRestriction
     */
    public function testForRbacRestrictionWithDifferentOperation(): void
    {
        $operation = 'delete';
        $resource = 'collections/Article';
        $context = ['user_role' => 'reader'];

        $exception = InsufficientPermissionsException::forRbacRestriction($operation, $resource, $context);

        $this->assertStringContainsString('RBAC policy prevents', $exception->getMessage());
        $this->assertStringContainsString($operation, $exception->getMessage());
        $this->assertStringContainsString($resource, $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($operation, $resultContext['operation']);
        $this->assertSame($resource, $resultContext['resource']);
        $this->assertSame('reader', $resultContext['user_role']);
        $this->assertSame('rbac_restriction', $resultContext['error_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Auth error');
        $exception = new InsufficientPermissionsException('Access denied', null, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forRbacRestriction
     */
    public function testForRbacRestrictionWithPreviousException(): void
    {
        $previous = new \Exception('Token expired');
        $exception = InsufficientPermissionsException::forRbacRestriction('read', 'collections', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forRbacRestriction
     */
    public function testForRbacRestrictionWithEmptyContext(): void
    {
        $exception = InsufficientPermissionsException::forRbacRestriction('create', 'schemas');

        $context = $exception->getContext();
        $this->assertSame('create', $context['operation']);
        $this->assertSame('schemas', $context['resource']);
        $this->assertSame('rbac_restriction', $context['error_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testSuggestionsAreIncluded(): void
    {
        $exception = new InsufficientPermissionsException();

        $context = $exception->getContext();
        $this->assertIsArray($context['suggestions']);
        $this->assertContains('Verify your API key has the required permissions', $context['suggestions']);
        $this->assertContains('Contact your administrator to grant necessary permissions', $context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forRbacRestriction
     */
    public function testForRbacRestrictionWithEmptyOperation(): void
    {
        $exception = InsufficientPermissionsException::forRbacRestriction('', 'collections');

        $this->assertStringContainsString('RBAC policy prevents', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['operation']);
        $this->assertSame('collections', $context['resource']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::__construct
     */
    public function testDefaultStatusCodeIs403(): void
    {
        $exception = new InsufficientPermissionsException('Test message');

        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame(403, $exception->getCode());
    }
}
