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

namespace Weaviate\Tests\Integration\Collections;

use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class CollectionWorkflowTest extends TestCase
{
    private WeaviateClient $client;

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create client (no auth needed for test instance)
        $this->client = new WeaviateClient($connection);
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     * @covers \Weaviate\Collections\Collections::exists
     * @covers \Weaviate\Collections\Collections::delete
     * @covers \Weaviate\Collections\Collections::create
     * @covers \Weaviate\Collections\Collections::get
     * @covers \Weaviate\Collections\Collection::tenants
     * @covers \Weaviate\Collections\Collection::withTenant
     * @covers \Weaviate\Collections\Collection::data
     * @covers \Weaviate\Data\DataOperations::create
     * @covers \Weaviate\Data\DataOperations::get
     * @covers \Weaviate\Data\DataOperations::update
     * @covers \Weaviate\Data\DataOperations::delete
     */
    public function testCanCreateAndManageOrganizationCollection(): void
    {
        $collectionName = 'TestOrganization';

        // Clean up any existing collection
        if ($this->client->collections()->exists($collectionName)) {
            $this->client->collections()->delete($collectionName);
        }

        // Verify collection doesn't exist
        $this->assertFalse($this->client->collections()->exists($collectionName));

        // Create collection
        $result = $this->client->collections()->create($collectionName, [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']],
                ['name' => 'createdAt', 'dataType' => ['date']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);

        $this->assertEquals($collectionName, $result['class']);

        // Verify collection exists
        $this->assertTrue($this->client->collections()->exists($collectionName));

        // Create tenant
        $this->client->collections()->get($collectionName)
            ->tenants()
            ->create([['name' => 'test-tenant']]);

        // Create object with tenant
        $orgId = '123e4567-e89b-12d3-a456-426614174000';
        $result = $this->client->collections()->get($collectionName)
            ->withTenant('test-tenant')
            ->data()
            ->create([
                'id' => $orgId,
                'name' => 'Test Organization',
                'createdAt' => '2024-01-01T00:00:00Z'
            ]);

        $this->assertEquals($orgId, $result['id']);

        // Retrieve object
        $retrieved = $this->client->collections()->get($collectionName)
            ->withTenant('test-tenant')
            ->data()
            ->get($orgId);

        $this->assertEquals('Test Organization', $retrieved['properties']['name']);

        // Update object
        $updated = $this->client->collections()->get($collectionName)
            ->withTenant('test-tenant')
            ->data()
            ->update($orgId, [
                'name' => 'Updated Organization'
            ]);

        $this->assertEquals('Updated Organization', $updated['properties']['name']);

        // Delete object
        $deleted = $this->client->collections()->get($collectionName)
            ->withTenant('test-tenant')
            ->data()
            ->delete($orgId);

        $this->assertTrue($deleted);

        // Clean up collection
        $this->assertTrue($this->client->collections()->delete($collectionName));
        $this->assertFalse($this->client->collections()->exists($collectionName));
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     * @covers \Weaviate\Collections\Collections::exists
     * @covers \Weaviate\Collections\Collections::delete
     * @covers \Weaviate\Collections\Collections::create
     * @covers \Weaviate\Collections\Collections::get
     * @covers \Weaviate\Collections\Collection::tenants
     * @covers \Weaviate\Collections\Collection::withTenant
     * @covers \Weaviate\Collections\Collection::data
     * @covers \Weaviate\Data\DataOperations::create
     * @covers \Weaviate\Data\DataOperations::get
     */
    public function testCanWorkWithMultipleTenants(): void
    {
        $collectionName = 'TestMultiTenant';

        // Clean up any existing collection
        if ($this->client->collections()->exists($collectionName)) {
            $this->client->collections()->delete($collectionName);
        }

        // Create collection with multi-tenancy
        $this->client->collections()->create($collectionName, [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']],
                ['name' => 'value', 'dataType' => ['int']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);

        // Create tenants
        $this->client->collections()->get($collectionName)
            ->tenants()
            ->create([
                ['name' => 'tenant1'],
                ['name' => 'tenant2']
            ]);

        // Create objects in different tenants
        $tenant1Id = '111e4567-e89b-12d3-a456-426614174000';
        $tenant2Id = '222e4567-e89b-12d3-a456-426614174000';

        // Tenant 1 object
        $this->client->collections()->get($collectionName)
            ->withTenant('tenant1')
            ->data()
            ->create([
                'id' => $tenant1Id,
                'name' => 'Tenant 1 Object',
                'value' => 100
            ]);

        // Tenant 2 object
        $this->client->collections()->get($collectionName)
            ->withTenant('tenant2')
            ->data()
            ->create([
                'id' => $tenant2Id,
                'name' => 'Tenant 2 Object',
                'value' => 200
            ]);

        // Verify tenant isolation - each tenant can only see their own objects
        $tenant1Object = $this->client->collections()->get($collectionName)
            ->withTenant('tenant1')
            ->data()
            ->get($tenant1Id);

        $tenant2Object = $this->client->collections()->get($collectionName)
            ->withTenant('tenant2')
            ->data()
            ->get($tenant2Id);

        $this->assertEquals('Tenant 1 Object', $tenant1Object['properties']['name']);
        $this->assertEquals(100, $tenant1Object['properties']['value']);

        $this->assertEquals('Tenant 2 Object', $tenant2Object['properties']['name']);
        $this->assertEquals(200, $tenant2Object['properties']['value']);

        // Clean up
        $this->client->collections()->delete($collectionName);
    }

    protected function tearDown(): void
    {
        // Clean up any test collections that might have been left behind
        $testCollections = ['TestOrganization', 'TestMultiTenant'];

        foreach ($testCollections as $collection) {
            if ($this->client->collections()->exists($collection)) {
                $this->client->collections()->delete($collection);
            }
        }
    }
}
