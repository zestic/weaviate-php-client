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
    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testCanCreateNotFoundException(): void
    {
        $exception = new NotFoundException();

        $this->assertInstanceOf(UnexpectedStatusCodeException::class, $exception);
        $this->assertSame('Resource not found', $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testCanCreateWithCustomMessage(): void
    {
        $message = 'Collection not found';
        $exception = new NotFoundException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(404, $exception->getStatusCode());
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testCanCreateWithResourceType(): void
    {
        $exception = new NotFoundException('Not found', 'collection');

        $context = $exception->getContext();
        $this->assertSame('collection', $context['resource_type']);
        $this->assertSame(404, $context['status_code']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testCanCreateWithResourceId(): void
    {
        $exception = new NotFoundException('Not found', 'object', 'obj-123');

        $context = $exception->getContext();
        $this->assertSame('object', $context['resource_type']);
        $this->assertSame('obj-123', $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
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

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forCollection
     */
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

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forObject
     */
    public function testForObject(): void
    {
        $objectId = 'obj-123';

        $exception = NotFoundException::forObject($objectId);

        $this->assertStringContainsString("Object 'obj-123' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('object', $context['resource_type']);
        $this->assertSame($objectId, $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forObject
     */
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

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forTenant
     */
    public function testForTenant(): void
    {
        $tenantName = 'tenant123';

        $exception = NotFoundException::forTenant($tenantName);

        $this->assertStringContainsString("Tenant 'tenant123' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('tenant', $context['resource_type']);
        $this->assertSame($tenantName, $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forTenant
     */
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

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forProperty
     */
    public function testForProperty(): void
    {
        $propertyName = 'title';
        $collectionName = 'Article';

        $exception = NotFoundException::forProperty($propertyName, $collectionName);

        $this->assertStringContainsString(
            "Property 'title' not found in collection 'Article'",
            $exception->getMessage()
        );

        $context = $exception->getContext();
        $this->assertSame('property', $context['resource_type']);
        $this->assertSame($propertyName, $context['resource_id']);
        $this->assertSame($collectionName, $context['collection']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testDefaultStatusCodeIs404(): void
    {
        $exception = new NotFoundException('Test message');

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame(404, $exception->getCode());
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forCollection
     */
    public function testForCollectionWithEmptyName(): void
    {
        $exception = NotFoundException::forCollection('');

        $this->assertStringContainsString("Collection '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forObject
     */
    public function testForObjectWithEmptyId(): void
    {
        $exception = NotFoundException::forObject('');

        $this->assertStringContainsString("Object '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forTenant
     */
    public function testForTenantWithEmptyName(): void
    {
        $exception = NotFoundException::forTenant('');

        $this->assertStringContainsString("Tenant '' not found", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forProperty
     */
    public function testForPropertyWithEmptyNames(): void
    {
        $exception = NotFoundException::forProperty('', '');

        $this->assertStringContainsString("Property '' not found in collection ''", $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('', $context['resource_id']);
        $this->assertSame('', $context['collection']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testWithNullResourceType(): void
    {
        $exception = new NotFoundException('Not found', null, 'id-123');

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('resource_type', $context);
        $this->assertSame('id-123', $context['resource_id']);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::__construct
     */
    public function testWithNullResourceId(): void
    {
        $exception = new NotFoundException('Not found', 'collection', null);

        $context = $exception->getContext();
        $this->assertSame('collection', $context['resource_type']);
        $this->assertArrayNotHasKey('resource_id', $context);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forObject
     */
    public function testForObjectWithNullCollection(): void
    {
        $exception = NotFoundException::forObject('obj-123', null);

        $this->assertStringContainsString("Object 'obj-123' not found", $exception->getMessage());
        $this->assertStringNotContainsString('in collection', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('collection', $context);
    }

    /**
     * @covers \Weaviate\Exceptions\NotFoundException::forTenant
     */
    public function testForTenantWithNullCollection(): void
    {
        $exception = NotFoundException::forTenant('tenant123', null);

        $this->assertStringContainsString("Tenant 'tenant123' not found", $exception->getMessage());
        $this->assertStringNotContainsString('in collection', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertArrayNotHasKey('collection', $context);
    }
}
