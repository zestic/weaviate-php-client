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
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;

class NotFoundExceptionTest extends TestCase
{
    public function testCanCreateNotFoundException(): void
    {
        $exception = new NotFoundException();

        $this->assertInstanceOf(UnexpectedStatusCodeException::class, $exception);
        $this->assertSame('Resource not found', $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());
    }

    public function testCanCreateWithCustomMessage(): void
    {
        $message = 'Collection not found';
        $exception = new NotFoundException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());
    }

    public function testCanCreateWithResourceType(): void
    {
        $exception = new NotFoundException('Not found', 'collection');

        $context = $exception->getContext();
        $this->assertSame('collection', $context['resource_type']);
        $this->assertSame(404, $context['status_code']);
    }

    public function testCanCreateWithResourceId(): void
    {
        $exception = new NotFoundException('Not found', 'object', 'obj-123');

        $context = $exception->getContext();
        $this->assertSame('object', $context['resource_type']);
        $this->assertSame('obj-123', $context['resource_id']);
    }

    public function testCanCreateWithContext(): void
    {
        $context = ['collection' => 'Article', 'tenant' => 'tenant1'];
        $exception = new NotFoundException('Not found', 'object', 'obj-123', $context);

        $resultContext = $exception->getContext();
        $this->assertSame('object', $resultContext['resource_type']);
        $this->assertSame('obj-123', $resultContext['resource_id']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame('tenant1', $resultContext['tenant']);
    }

    public function testForCollection(): void
    {
        $collectionName = 'Article';

        $exception = NotFoundException::forCollection($collectionName);

        $this->assertStringContainsString("Collection 'Article' not found", $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());

        $context = $exception->getContext();
        $this->assertSame('collection', $context['resource_type']);
        $this->assertSame($collectionName, $context['resource_id']);
    }

    public function testForObject(): void
    {
        $objectId = 'obj-123';

        $exception = NotFoundException::forObject($objectId);

        $this->assertStringContainsString("Object 'obj-123' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('object', $context['resource_type']);
        $this->assertSame($objectId, $context['resource_id']);
    }

    public function testForObjectWithCollection(): void
    {
        $objectId = 'obj-123';
        $collectionName = 'Article';

        $exception = NotFoundException::forObject($objectId, $collectionName);

        $this->assertStringContainsString(
            "Object 'obj-123' not found in collection 'Article'",
            $exception->getMessage()
        );

        $context = $exception->getContext();
        $this->assertSame('object', $context['resource_type']);
        $this->assertSame($objectId, $context['resource_id']);
        $this->assertSame($collectionName, $context['collection']);
    }

    public function testForTenant(): void
    {
        $tenantName = 'tenant123';

        $exception = NotFoundException::forTenant($tenantName);

        $this->assertStringContainsString("Tenant 'tenant123' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('tenant', $context['resource_type']);
        $this->assertSame($tenantName, $context['resource_id']);
    }

    public function testForTenantWithCollection(): void
    {
        $tenantName = 'tenant123';
        $collectionName = 'Article';

        $exception = NotFoundException::forTenant($tenantName, $collectionName);

        $this->assertStringContainsString(
            "Tenant 'tenant123' not found in collection 'Article'",
            $exception->getMessage()
        );

        $context = $exception->getContext();
        $this->assertSame('tenant', $context['resource_type']);
        $this->assertSame($tenantName, $context['resource_id']);
        $this->assertSame($collectionName, $context['collection']);
    }

    public function testFromResponse(): void
    {
        $message = 'Resource not found';
        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockStream = $this->createMock(\Psr\Http\Message\StreamInterface::class);

        $mockStream->method('__toString')->willReturn('{"error": "Not found"}');
        $mockResponse->method('getStatusCode')->willReturn(404);
        $mockResponse->method('getBody')->willReturn($mockStream);
        $mockResponse->method('getHeaders')->willReturn(['Content-Type' => ['application/json']]);

        $context = ['operation' => 'get'];

        $exception = NotFoundException::fromResponse($message, $mockResponse, $context);

        $this->assertStringContainsString('Not found', $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());

        $resultContext = $exception->getContext();
        $this->assertSame('get', $resultContext['operation']);
    }

    public function testDefaultStatusCodeIs404(): void
    {
        $exception = new NotFoundException('Test message');

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame(404, $exception->getCode());
    }

    public function testForCollectionWithEmptyName(): void
    {
        $exception = NotFoundException::forCollection('');

        $this->assertStringContainsString("Collection '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    public function testForObjectWithEmptyId(): void
    {
        $exception = NotFoundException::forObject('');

        $this->assertStringContainsString("Object '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    public function testForTenantWithEmptyName(): void
    {
        $exception = NotFoundException::forTenant('');

        $this->assertStringContainsString("Tenant '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    public function testForObjectWithSpecialCharacters(): void
    {
        $objectId = 'obj-with-special-chars!@#';
        $collectionName = 'Special Collection';

        $exception = NotFoundException::forObject($objectId, $collectionName);

        $this->assertStringContainsString($objectId, $exception->getMessage());
        $this->assertStringContainsString($collectionName, $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($objectId, $context['resource_id']);
        $this->assertSame($collectionName, $context['collection']);
    }

    public function testWithNullResourceType(): void
    {
        $exception = new NotFoundException('Not found', null, 'id-123');

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('resource_type', $context);
        $this->assertSame('id-123', $context['resource_id']);
    }

    public function testWithNullResourceId(): void
    {
        $exception = new NotFoundException('Not found', 'collection', null);

        $context = $exception->getContext();
        $this->assertSame('collection', $context['resource_type']);
        $this->assertArrayNotHasKey('resource_id', $context);
    }

    public function testForObjectWithNullCollection(): void
    {
        $exception = NotFoundException::forObject('obj-123', null);

        $this->assertStringContainsString("Object 'obj-123' not found", $exception->getMessage());
        $this->assertStringNotContainsString('in collection', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('collection', $context);
    }

    public function testForTenantWithNullCollection(): void
    {
        $exception = NotFoundException::forTenant('tenant123', null);

        $this->assertStringContainsString("Tenant 'tenant123' not found", $exception->getMessage());
        $this->assertStringNotContainsString('in collection', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('collection', $context);
    }
}
