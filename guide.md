# Weaviate PHP Client - Phase 1 TDD Implementation Guide

## License
This project is licensed under the Apache License 2.0. See the LICENSE file for details.

## Overview
This guide provides a Test-Driven Development (TDD) approach for implementing Phase 1 of the Weaviate PHP Client. Instead of focusing on implementation details, we'll let the tests drive our understanding of what needs to be built.

## Phase 1 Goals
- Core client with multi-tenancy support (MVP)
- Connection layer with authentication
- Collections API for basic CRUD operations
- Data operations with tenant support

## TDD Approach

### 1. Test Structure Setup

First, we'll establish our testing framework and directory structure:

```
tests/
├── Unit/
│   ├── Connection/
│   ├── Collections/
│   ├── Data/
│   └── Auth/
├── Integration/
│   ├── Collections/
│   └── Data/
└── TestCase.php
```

### 2. Core Client Tests

#### Test: WeaviateClient Construction
```php
<?php
// tests/Unit/WeaviateClientTest.php

namespace Weaviate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;

class WeaviateClientTest extends TestCase
{
    public function testCanCreateClientWithConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    public function testCanCreateClientWithConnectionAndAuth(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $auth = $this->createMock(AuthInterface::class);
        $client = new WeaviateClient($connection, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    public function testCollectionsReturnsCollectionsInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $collections = $client->collections();

        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    public function testSchemaReturnsSchemaInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $schema = $client->schema();

        $this->assertInstanceOf(\Weaviate\Schema\Schema::class, $schema);
    }
}
```

#### Test: Connection Layer
```php
<?php
// tests/Unit/Connection/HttpConnectionTest.php

namespace Weaviate\Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\AuthInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class HttpConnectionTest extends TestCase
{
    public function testCanCreateConnection(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(HttpConnection::class, $connection);
    }

    public function testCanMakeGetRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn('{"result": "success"}');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $result = $connection->get('/v1/schema');

        $this->assertEquals(['result' => 'success'], $result);
    }

    public function testCanMakePostRequest(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn('{"id": "123"}');

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        $connection = new HttpConnection(
            'http://localhost:8080',
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $result = $connection->post('/v1/objects', ['name' => 'test']);

        $this->assertEquals(['id' => '123'], $result);
    }
}
```

### 3. Authentication Tests

#### Test: API Key Authentication
```php
<?php
// tests/Unit/Auth/ApiKeyTest.php

namespace Weaviate\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Weaviate\Auth\ApiKey;
use Psr\Http\Message\RequestInterface;

class ApiKeyTest extends TestCase
{
    public function testCanCreateApiKeyAuth(): void
    {
        $auth = new ApiKey('my-secret-key');

        $this->assertInstanceOf(ApiKey::class, $auth);
    }

    public function testAppliesAuthorizationHeader(): void
    {
        $auth = new ApiKey('my-secret-key');
        $request = $this->createMock(RequestInterface::class);

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer my-secret-key')
            ->willReturnSelf();

        $auth->apply($request);
    }
}
```

### 4. Collections API Tests

#### Test: Collections Management
```php
<?php
// tests/Unit/Collections/CollectionsTest.php

namespace Weaviate\Tests\Unit\Collections;

use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collections;
use Weaviate\Collections\Collection;
use Weaviate\Connection\ConnectionInterface;

class CollectionsTest extends TestCase
{
    public function testCanCheckIfCollectionExists(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Organization')
            ->willReturn(['class' => 'Organization']);

        $collections = new Collections($connection);

        $exists = $collections->exists('Organization');

        $this->assertTrue($exists);
    }

    public function testReturnsFalseWhenCollectionDoesNotExist(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Organization')
            ->willThrowException(new \Weaviate\Exceptions\NotFoundException());

        $collections = new Collections($connection);

        $exists = $collections->exists('Organization');

        $this->assertFalse($exists);
    }

    public function testCanCreateCollection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('post')
            ->with('/v1/schema', [
                'class' => 'Organization',
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']]
                ],
                'multiTenancyConfig' => ['enabled' => true]
            ])
            ->willReturn(['class' => 'Organization']);

        $collections = new Collections($connection);

        $result = $collections->create('Organization', [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);

        $this->assertEquals(['class' => 'Organization'], $result);
    }

    public function testCanGetCollection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $collections = new Collections($connection);
        $collection = $collections->get('Organization');

        $this->assertInstanceOf(Collection::class, $collection);
    }
}
```

#### Test: Individual Collection Operations
```php
<?php
// tests/Unit/Collections/CollectionTest.php

namespace Weaviate\Tests\Unit\Collections;

use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collection;
use Weaviate\Data\DataOperations;
use Weaviate\Connection\ConnectionInterface;

class CollectionTest extends TestCase
{
    public function testCanSetTenant(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');

        $result = $collection->withTenant('tenant1');

        $this->assertSame($collection, $result);
        $this->assertEquals('tenant1', $collection->getTenant());
    }

    public function testDataReturnsDataOperations(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');

        $data = $collection->data();

        $this->assertInstanceOf(DataOperations::class, $data);
    }

    public function testDataOperationsReceiveTenant(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');
        $collection->withTenant('tenant1');

        $data = $collection->data();

        $this->assertEquals('tenant1', $data->getTenant());
    }
}
```

### 5. Data Operations Tests

#### Test: CRUD Operations with Multi-Tenancy
```php
<?php
// tests/Unit/Data/DataOperationsTest.php

namespace Weaviate\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Weaviate\Data\DataOperations;
use Weaviate\Connection\ConnectionInterface;

class DataOperationsTest extends TestCase
{
    public function testCanCreateObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('post')
            ->with('/v1/objects', [
                'class' => 'Organization',
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'properties' => [
                    'name' => 'ACME Corp',
                    'createdAt' => '2024-01-01T00:00:00Z'
                ],
                'tenant' => 'tenant1'
            ])
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'class' => 'Organization'
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->create([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'ACME Corp',
            'createdAt' => '2024-01-01T00:00:00Z'
        ]);

        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $result['id']);
    }

    public function testCanGetObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1')
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'class' => 'Organization',
                'properties' => ['name' => 'ACME Corp']
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->get('123e4567-e89b-12d3-a456-426614174000');

        $this->assertEquals('ACME Corp', $result['properties']['name']);
    }

    public function testCanUpdateObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('patch')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1', [
                'properties' => ['name' => 'Updated Corp']
            ])
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'properties' => ['name' => 'Updated Corp']
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->update('123e4567-e89b-12d3-a456-426614174000', [
            'name' => 'Updated Corp'
        ]);

        $this->assertEquals('Updated Corp', $result['properties']['name']);
    }

    public function testCanDeleteObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('delete')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1')
            ->willReturn(true);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->delete('123e4567-e89b-12d3-a456-426614174000');

        $this->assertTrue($result);
    }
}
```

### 6. Integration Tests

#### Test: End-to-End Workflow
```php
<?php
// tests/Integration/Collections/CollectionWorkflowTest.php

namespace Weaviate\Tests\Integration\Collections;

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\ApiKey;

class CollectionWorkflowTest extends TestCase
{
    private WeaviateClient $client;

    protected function setUp(): void
    {
        // This test requires a running Weaviate instance
        $this->client = new WeaviateClient(
            new HttpConnection('http://localhost:8080'),
            new ApiKey($_ENV['WEAVIATE_API_KEY'] ?? '')
        );
    }

    public function testCanCreateAndManageOrganizationCollection(): void
    {
        // Clean up any existing collection
        if ($this->client->collections()->exists('TestOrganization')) {
            $this->client->collections()->delete('TestOrganization');
        }

        // Create collection
        $result = $this->client->collections()->create('TestOrganization', [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']],
                ['name' => 'createdAt', 'dataType' => ['date']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);

        $this->assertEquals('TestOrganization', $result['class']);

        // Verify collection exists
        $this->assertTrue($this->client->collections()->exists('TestOrganization'));

        // Create tenant
        $this->client->collections()->get('TestOrganization')
            ->tenants()
            ->create(['name' => 'test-tenant']);

        // Create object with tenant
        $orgId = '123e4567-e89b-12d3-a456-426614174000';
        $result = $this->client->collections()->get('TestOrganization')
            ->withTenant('test-tenant')
            ->data()
            ->create([
                'id' => $orgId,
                'name' => 'Test Organization',
                'createdAt' => '2024-01-01T00:00:00Z'
            ]);

        $this->assertEquals($orgId, $result['id']);

        // Retrieve object
        $retrieved = $this->client->collections()->get('TestOrganization')
            ->withTenant('test-tenant')
            ->data()
            ->get($orgId);

        $this->assertEquals('Test Organization', $retrieved['properties']['name']);

        // Clean up
        $this->client->collections()->delete('TestOrganization');
    }
}
```

## TDD Implementation Process

### Step 1: Write Failing Tests
Start by writing tests for the interfaces and expected behavior. All tests should fail initially since no implementation exists.

### Step 2: Create Minimal Implementation
Create just enough code to make the tests pass. Focus on:
- Interface contracts
- Method signatures
- Basic return types

### Step 3: Refactor and Improve
Once tests pass, refactor the implementation while keeping tests green:
- Add error handling
- Improve performance
- Add validation

### Step 4: Add More Tests
As you discover edge cases and requirements, add more tests:
- Error conditions
- Edge cases
- Performance requirements

## Key Testing Principles

1. **Test Behavior, Not Implementation**: Focus on what the code should do, not how it does it.

2. **Use Mocks for External Dependencies**: Mock HTTP clients, authentication, etc. to isolate units under test.

3. **Integration Tests for Real Scenarios**: Use integration tests to verify the complete workflow against a real Weaviate instance.

4. **Test Error Conditions**: Ensure proper error handling for network failures, authentication errors, etc.

5. **Test Multi-Tenancy**: Verify tenant isolation and proper tenant parameter passing.

## Running Tests

```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit

# Integration tests (requires running Weaviate)
./vendor/bin/phpunit tests/Integration

# All tests
./vendor/bin/phpunit

# With coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Next Steps

1. Set up the project structure and testing framework
2. Write the failing tests outlined above
3. Implement minimal classes to make tests pass
4. Iterate on implementation while keeping tests green
5. Add more comprehensive tests as requirements become clearer

This TDD approach ensures that:
- We build exactly what's needed
- The API is usable and intuitive
- We have comprehensive test coverage from day one
- Refactoring is safe and confident