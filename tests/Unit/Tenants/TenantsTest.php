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

namespace Weaviate\Tests\Unit\Tenants;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Tenants\Tenant;
use Weaviate\Tenants\TenantActivityStatus;
use Weaviate\Tenants\TenantCreate;
use Weaviate\Tenants\TenantUpdate;
use Weaviate\Tenants\Tenants;

class TenantsTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private Tenants $tenants;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->tenants = new Tenants($this->connection, 'TestCollection');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::__construct
     */
    public function testCanConstruct(): void
    {
        $this->assertInstanceOf(Tenants::class, $this->tenants);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::create
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanCreateSingleTenantFromString(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'HOT']]
            );

        $this->tenants->create('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::create
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanCreateSingleTenantFromTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'COLD']]
            );

        $this->tenants->create($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::create
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanCreateSingleTenantFromTenantCreateObject(): void
    {
        $tenantCreate = new TenantCreate('tenant1', TenantActivityStatus::INACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'COLD']]
            );

        $this->tenants->create($tenantCreate);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::create
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanCreateMultipleTenantsFromArray(): void
    {
        $tenants = [
            'tenant1',
            new Tenant('tenant2', TenantActivityStatus::INACTIVE),
            new TenantCreate('tenant3', TenantActivityStatus::INACTIVE)
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant2', 'activityStatus' => 'COLD'],
                    ['name' => 'tenant3', 'activityStatus' => 'COLD']
                ]
            );

        $this->tenants->create($tenants);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::remove
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanRemoveSingleTenantFromString(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->with('/v1/schema/TestCollection/tenants', ['tenant1']);

        $this->tenants->remove('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::remove
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanRemoveMultipleTenantsFromArray(): void
    {
        $tenants = ['tenant1', new Tenant('tenant2'), 'tenant3'];

        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->with('/v1/schema/TestCollection/tenants', ['tenant1', 'tenant2', 'tenant3']);

        $this->tenants->remove($tenants);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::get
     */
    public function testCanGetAllTenants(): void
    {
        $responseData = [
            ['name' => 'tenant1', 'activityStatus' => 'ACTIVE'],
            ['name' => 'tenant2', 'activityStatus' => 'INACTIVE']
        ];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants')
            ->willReturn($responseData);

        $result = $this->tenants->get();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('tenant1', $result);
        $this->assertArrayHasKey('tenant2', $result);
        $this->assertInstanceOf(Tenant::class, $result['tenant1']);
        $this->assertEquals('tenant1', $result['tenant1']->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $result['tenant1']->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByName
     */
    public function testCanGetTenantByName(): void
    {
        $responseData = ['name' => 'tenant1', 'activityStatus' => 'ACTIVE'];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willReturn($responseData);

        $result = $this->tenants->getByName('tenant1');

        $this->assertInstanceOf(Tenant::class, $result);
        $this->assertEquals('tenant1', $result->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $result->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByName
     */
    public function testGetTenantByNameReturnsNullWhenNotFound(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants/nonexistent')
            ->willThrowException(new \Weaviate\Exceptions\NotFoundException('Tenant not found'));

        $result = $this->tenants->getByName('nonexistent');

        $this->assertNull($result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByName
     */
    public function testCanGetTenantByNameWithTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);
        $responseData = ['name' => 'tenant1', 'activityStatus' => 'ACTIVE'];

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willReturn($responseData);

        $result = $this->tenants->getByName($tenant);

        $this->assertInstanceOf(Tenant::class, $result);
        $this->assertEquals('tenant1', $result->getName());
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByName
     */
    public function testGetByNameThrowsExceptionOnConnectionError(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willThrowException(new \Weaviate\Exceptions\WeaviateConnectionException('Network error'));

        $this->expectException(\Weaviate\Exceptions\WeaviateConnectionException::class);
        $this->expectExceptionMessage('Network error');

        $this->tenants->getByName('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::exists
     */
    public function testCanCheckIfTenantExists(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('head')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willReturn(true);

        $result = $this->tenants->exists('tenant1');

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::exists
     */
    public function testReturnsFalseWhenTenantDoesNotExist(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('head')
            ->with('/v1/schema/TestCollection/tenants/nonexistent')
            ->willReturn(false);

        $result = $this->tenants->exists('nonexistent');

        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::exists
     */
    public function testCanCheckIfTenantExistsWithTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('head')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willReturn(true);

        $result = $this->tenants->exists($tenant);

        $this->assertTrue($result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::exists
     */
    public function testExistsThrowsExceptionOnConnectionError(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('head')
            ->with('/v1/schema/TestCollection/tenants/tenant1')
            ->willThrowException(new \Weaviate\Exceptions\WeaviateConnectionException('Network error'));

        $this->expectException(\Weaviate\Exceptions\WeaviateConnectionException::class);
        $this->expectExceptionMessage('Network error');

        $this->tenants->exists('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::activate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanActivateTenant(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'HOT']]
            );

        $this->tenants->activate('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::deactivate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanDeactivateTenant(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'COLD']]
            );

        $this->tenants->deactivate('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::offload
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanOffloadTenant(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'FROZEN']]
            );

        $this->tenants->offload('tenant1');
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByNames
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanGetTenantsByNames(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants')
            ->willReturn([
                ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                ['name' => 'tenant2', 'activityStatus' => 'COLD'],
                ['name' => 'tenant3', 'activityStatus' => 'FROZEN']
            ]);

        $result = $this->tenants->getByNames(['tenant1', 'tenant3']);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('tenant1', $result);
        $this->assertArrayHasKey('tenant3', $result);
        $this->assertArrayNotHasKey('tenant2', $result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::update
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanUpdateSingleTenant(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'COLD']]
            );

        $this->tenants->update($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::update
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanUpdateWithTenantUpdateObject(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::OFFLOADED);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'FROZEN']]
            );

        $this->tenants->update($tenantUpdate);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::activate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanActivateMultipleTenants(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant2', 'activityStatus' => 'HOT']
                ]
            );

        $this->tenants->activate(['tenant1', 'tenant2']);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::deactivate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanDeactivateMultipleTenants(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'COLD'],
                    ['name' => 'tenant2', 'activityStatus' => 'COLD']
                ]
            );

        $this->tenants->deactivate(['tenant1', 'tenant2']);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::offload
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanOffloadMultipleTenants(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'FROZEN'],
                    ['name' => 'tenant2', 'activityStatus' => 'FROZEN']
                ]
            );

        $this->tenants->offload(['tenant1', 'tenant2']);
    }



    /**
     * @covers \Weaviate\Tenants\Tenants::create
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanCreateMixedTenantTypes(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);
        $tenantCreate = new TenantCreate('tenant2', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant0', 'activityStatus' => 'HOT'], // string
                    ['name' => 'tenant1', 'activityStatus' => 'COLD'], // Tenant object
                    ['name' => 'tenant2', 'activityStatus' => 'HOT']   // TenantCreate object
                ]
            );

        $this->tenants->create(['tenant0', $tenant, $tenantCreate]);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::update
     * @covers \Weaviate\Tenants\Tenants::normalizeTenantInput
     */
    public function testCanUpdateMixedTenantTypes(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);
        $tenantUpdate = new TenantUpdate('tenant2', TenantActivityStatus::OFFLOADED);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'COLD'],   // Tenant object
                    ['name' => 'tenant2', 'activityStatus' => 'FROZEN']  // TenantUpdate object
                ]
            );

        $this->tenants->update([$tenant, $tenantUpdate]);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::remove
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanRemoveSingleTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->with('/v1/schema/TestCollection/tenants', ['tenant1']);

        $this->tenants->remove($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::remove
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanRemoveMixedTenantTypes(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('deleteWithData')
            ->with('/v1/schema/TestCollection/tenants', ['tenant0', 'tenant1', 'tenant2']);

        $this->tenants->remove(['tenant0', $tenant, 'tenant2']);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::activate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanActivateSingleTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'HOT']]
            );

        $this->tenants->activate($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::deactivate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanDeactivateSingleTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'COLD']]
            );

        $this->tenants->deactivate($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::offload
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanOffloadSingleTenantObject(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [['name' => 'tenant1', 'activityStatus' => 'FROZEN']]
            );

        $this->tenants->offload($tenant);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::activate
     * @covers \Weaviate\Tenants\Tenants::updateTenantStatus
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanActivateMixedTenantTypes(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant0', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant2', 'activityStatus' => 'HOT']
                ]
            );

        $this->tenants->activate(['tenant0', $tenant, 'tenant2']);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByNames
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanGetTenantsByNamesWithMixedTypes(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants')
            ->willReturn([
                ['name' => 'tenant0', 'activityStatus' => 'HOT'],
                ['name' => 'tenant1', 'activityStatus' => 'COLD'],
                ['name' => 'tenant2', 'activityStatus' => 'FROZEN'],
                ['name' => 'tenant3', 'activityStatus' => 'HOT']
            ]);

        $result = $this->tenants->getByNames(['tenant0', $tenant, 'tenant2']);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('tenant0', $result);
        $this->assertArrayHasKey('tenant1', $result);
        $this->assertArrayHasKey('tenant2', $result);
        $this->assertArrayNotHasKey('tenant3', $result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::getByNames
     * @covers \Weaviate\Tenants\Tenants::extractTenantNames
     */
    public function testCanGetTenantsByNamesWithSingleTenant(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);

        $this->connection
            ->expects($this->once())
            ->method('get')
            ->with('/v1/schema/TestCollection/tenants')
            ->willReturn([
                ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                ['name' => 'tenant2', 'activityStatus' => 'COLD']
            ]);

        $result = $this->tenants->getByNames([$tenant]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('tenant1', $result);
        $this->assertArrayNotHasKey('tenant2', $result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::existsBatch
     */
    public function testCanCheckMultipleTenantsExist(): void
    {
        $this->connection
            ->expects($this->exactly(3))
            ->method('head')
            ->willReturnMap([
                ['/v1/schema/TestCollection/tenants/tenant1', true],
                ['/v1/schema/TestCollection/tenants/tenant2', false],
                ['/v1/schema/TestCollection/tenants/tenant3', true]
            ]);

        $result = $this->tenants->existsBatch(['tenant1', 'tenant2', 'tenant3']);

        $expected = [
            'tenant1' => true,
            'tenant2' => false,
            'tenant3' => true
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::createBatch
     */
    public function testCanCreateMultipleTenantsInBatch(): void
    {
        $tenantNames = ['tenant1', 'tenant2', 'tenant3'];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant2', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant3', 'activityStatus' => 'HOT']
                ]
            );

        $this->tenants->createBatch($tenantNames);
    }

    /**
     * @covers \Weaviate\Tenants\Tenants::activateBatch
     */
    public function testCanActivateMultipleTenantsInBatch(): void
    {
        $tenantNames = ['tenant1', 'tenant2', 'tenant3'];

        $this->connection
            ->expects($this->once())
            ->method('put')
            ->with(
                '/v1/schema/TestCollection/tenants',
                [
                    ['name' => 'tenant1', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant2', 'activityStatus' => 'HOT'],
                    ['name' => 'tenant3', 'activityStatus' => 'HOT']
                ]
            );

        $this->tenants->activateBatch($tenantNames);
    }
}
