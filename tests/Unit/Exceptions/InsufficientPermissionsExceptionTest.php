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
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forApiKey
     */
    public function testForApiKey(): void
    {
        $operation = 'create_collection';
        $context = ['api_key_id' => 'key123'];

        $exception = InsufficientPermissionsException::forApiKey($operation, $context);

        $this->assertStringContainsString('API key does not have permission', $exception->getMessage());
        $this->assertStringContainsString($operation, $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($operation, $resultContext['operation']);
        $this->assertSame('api_key', $resultContext['auth_type']);
        $this->assertSame('key123', $resultContext['api_key_id']);
        $this->assertSame('api_key_insufficient', $resultContext['error_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forTenant
     */
    public function testForTenant(): void
    {
        $tenantName = 'tenant123';
        $operation = 'read_data';
        $context = ['collection' => 'Article'];

        $exception = InsufficientPermissionsException::forTenant($tenantName, $operation, $context);

        $this->assertStringContainsString('Insufficient permissions for tenant', $exception->getMessage());
        $this->assertStringContainsString($tenantName, $exception->getMessage());
        $this->assertStringContainsString($operation, $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($tenantName, $resultContext['tenant']);
        $this->assertSame($operation, $resultContext['operation']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame('tenant_access_denied', $resultContext['error_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forRbacRestriction
     */
    public function testForRbacRestriction(): void
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
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forApiKey
     */
    public function testForApiKeyWithPreviousException(): void
    {
        $previous = new \Exception('Token expired');
        $exception = InsufficientPermissionsException::forApiKey('read', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forTenant
     */
    public function testForTenantWithEmptyContext(): void
    {
        $exception = InsufficientPermissionsException::forTenant('tenant1', 'write');

        $context = $exception->getContext();
        $this->assertSame('tenant1', $context['tenant']);
        $this->assertSame('write', $context['operation']);
        $this->assertSame('tenant_access_denied', $context['error_subtype']);
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
        $this->assertContains('Check if you have access to the specified tenant', $context['suggestions']);
        $this->assertContains('Contact your administrator to grant necessary permissions', $context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forApiKey
     */
    public function testForApiKeyWithEmptyOperation(): void
    {
        $exception = InsufficientPermissionsException::forApiKey('');

        $this->assertStringContainsString('API key does not have permission', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertSame('', $context['operation']);
    }

    /**
     * @covers \Weaviate\Exceptions\InsufficientPermissionsException::forTenant
     */
    public function testForTenantWithEmptyTenantName(): void
    {
        $exception = InsufficientPermissionsException::forTenant('', 'read');

        $this->assertStringContainsString('Insufficient permissions for tenant', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertSame('', $context['tenant']);
        $this->assertSame('read', $context['operation']);
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
