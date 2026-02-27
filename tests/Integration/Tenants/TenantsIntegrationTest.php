<?php

declare(strict_types=1);

/*
 * Copyright 2025 Zestic
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

namespace Weaviate\Tests\Integration\Tenants;

use Weaviate\Exceptions\NotFoundException;
use Weaviate\Factory\WeaviateClientFactory;
use Weaviate\Tenants\Tenant;
use Weaviate\Tenants\TenantActivityStatus;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;

class TenantsIntegrationTest extends TestCase
{
    private WeaviateClient $client;
    private string $collectionName;

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $this->client = WeaviateClientFactory::connectToLocal($host);
        $this->collectionName = 'TestTenantCollection_' . uniqid();

        // Create a multi-tenant collection for testing
        $this->client->collections()->create($this->collectionName, [
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'content', 'dataType' => ['text']],
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->client) && isset($this->collectionName)) {
            try {
                $this->client->collections()->delete($this->collectionName);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    public function testCanCreateAndRetrieveTenants(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create tenants
        $tenants->create(['tenant1', 'tenant2']);

        // Retrieve all tenants
        $allTenants = $tenants->get();

        $this->assertCount(2, $allTenants);
        $this->assertArrayHasKey('tenant1', $allTenants);
        $this->assertArrayHasKey('tenant2', $allTenants);
        $this->assertEquals(TenantActivityStatus::ACTIVE, $allTenants['tenant1']->getActivityStatus());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $allTenants['tenant2']->getActivityStatus());
    }

    public function testCanCreateTenantWithSpecificActivityStatus(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create tenant with inactive status
        $tenant = new Tenant('inactive_tenant', TenantActivityStatus::INACTIVE);
        $tenants->create($tenant);

        // Retrieve the tenant
        $retrievedTenant = $tenants->getByName('inactive_tenant');

        $this->assertNotNull($retrievedTenant);
        if ($retrievedTenant !== null) {
            $this->assertEquals('inactive_tenant', $retrievedTenant->getName());
            $this->assertEquals(TenantActivityStatus::INACTIVE, $retrievedTenant->getActivityStatus());
        }
    }

    public function testCanCheckTenantExists(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create tenant
        $tenants->create('test_tenant');

        // Check existence
        $this->assertTrue($tenants->exists('test_tenant'));
        $this->assertFalse($tenants->exists('non_existent_tenant'));
    }

    public function testCanActivateAndDeactivateTenant(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create inactive tenant
        $tenant = new Tenant('status_test_tenant', TenantActivityStatus::INACTIVE);
        $tenants->create($tenant);

        // Activate tenant
        $tenants->activate('status_test_tenant');
        $activeTenant = $tenants->getByName('status_test_tenant');
        if ($activeTenant !== null) {
            $this->assertEquals(TenantActivityStatus::ACTIVE, $activeTenant->getActivityStatus());
        }

        // Deactivate tenant
        $tenants->deactivate('status_test_tenant');
        $inactiveTenant = $tenants->getByName('status_test_tenant');
        if ($inactiveTenant !== null) {
            $this->assertEquals(TenantActivityStatus::INACTIVE, $inactiveTenant->getActivityStatus());
        }
    }

    public function testCanRemoveTenant(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create tenant
        $tenants->create('removable_tenant');
        $this->assertTrue($tenants->exists('removable_tenant'));

        // Remove tenant
        $tenants->remove('removable_tenant');
        $this->assertFalse($tenants->exists('removable_tenant'));
    }

    public function testTenantDataIsolation(): void
    {
        $collection = $this->client->collections()->get($this->collectionName);
        $tenants = $collection->tenants();

        // Create tenants
        $tenants->create(['tenant_a', 'tenant_b']);

        // Ensure tenants are active
        $tenants->activate(['tenant_a', 'tenant_b']);

        // Add data to tenant A
        $tenantA = $collection->withTenant('tenant_a');
        $resultA = $tenantA->data()->create([
            'title' => 'Tenant A Article',
            'content' => 'This belongs to tenant A'
        ]);

        $this->assertNotEmpty($resultA);
        $this->assertArrayHasKey('id', $resultA);

        // Try to retrieve the data we just created
        try {
            $tenantAData = $tenantA->data()->get($resultA['id']);
            $this->assertArrayHasKey('properties', $tenantAData);
            $this->assertEquals('Tenant A Article', $tenantAData['properties']['title']);
        } catch (NotFoundException $e) {
            $this->fail('Could not retrieve data from tenant A: ' . $e->getMessage());
        }

        // Add data to tenant B
        $tenantB = $collection->withTenant('tenant_b');
        $resultB = $tenantB->data()->create([
            'title' => 'Tenant B Article',
            'content' => 'This belongs to tenant B'
        ]);

        $this->assertNotEmpty($resultB);
        $this->assertArrayHasKey('id', $resultB);

        // Try to retrieve tenant B data
        try {
            $tenantBData = $tenantB->data()->get($resultB['id']);
            $this->assertArrayHasKey('properties', $tenantBData);
            $this->assertEquals('Tenant B Article', $tenantBData['properties']['title']);
        } catch (NotFoundException $e) {
            $this->fail('Could not retrieve data from tenant B: ' . $e->getMessage());
        }

        // Verify tenant A cannot access tenant B's data
        try {
            $crossTenantData = $tenantA->data()->get($resultB['id']);
            $this->fail(
                'Tenant A should not be able to access tenant B\'s data, but got: ' .
                json_encode($crossTenantData)
            );
        } catch (NotFoundException $e) {
            // This is expected - tenant isolation is working
            $this->assertTrue(true, 'Tenant isolation is working correctly');
        }
    }
}
