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
use Weaviate\Tenants\Tenant;
use Weaviate\Tenants\TenantActivityStatus;
use Weaviate\Tenants\TenantUpdate;

/**
 * @covers \Weaviate\Tenants\TenantUpdate
 */
class TenantUpdateTest extends TestCase
{
    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     * @covers \Weaviate\Tenants\TenantUpdate::getName
     * @covers \Weaviate\Tenants\TenantUpdate::getActivityStatus
     */
    public function testCanCreateTenantUpdateWithActiveStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::ACTIVE);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     * @covers \Weaviate\Tenants\TenantUpdate::getName
     * @covers \Weaviate\Tenants\TenantUpdate::getActivityStatus
     */
    public function testCanCreateTenantUpdateWithInactiveStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::INACTIVE);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::INACTIVE, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     * @covers \Weaviate\Tenants\TenantUpdate::getName
     * @covers \Weaviate\Tenants\TenantUpdate::getActivityStatus
     */
    public function testCanCreateTenantUpdateWithOffloadedStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::OFFLOADED);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::OFFLOADED, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     */
    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        new TenantUpdate('', TenantActivityStatus::ACTIVE);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     */
    public function testThrowsExceptionForWhitespaceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        new TenantUpdate('   ', TenantActivityStatus::ACTIVE);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     */
    public function testThrowsExceptionForOffloadingStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant update only supports ACTIVE, INACTIVE, and OFFLOADED statuses');

        new TenantUpdate('tenant1', TenantActivityStatus::OFFLOADING);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::__construct
     */
    public function testThrowsExceptionForOnloadingStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant update only supports ACTIVE, INACTIVE, and OFFLOADED statuses');

        new TenantUpdate('tenant1', TenantActivityStatus::ONLOADING);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::toArray
     * @covers \Weaviate\Tenants\TenantUpdate::getApiStatusValue
     */
    public function testCanConvertToArrayWithActiveStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::ACTIVE);
        $array = $tenantUpdate->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'HOT'
        ], $array);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::toArray
     */
    public function testCanConvertToArrayWithInactiveStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::INACTIVE);
        $array = $tenantUpdate->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'COLD'
        ], $array);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::toArray
     */
    public function testCanConvertToArrayWithOffloadedStatus(): void
    {
        $tenantUpdate = new TenantUpdate('tenant1', TenantActivityStatus::OFFLOADED);
        $array = $tenantUpdate->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'FROZEN'
        ], $array);
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::fromTenant
     */
    public function testCanCreateFromTenantWithActiveStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);
        $tenantUpdate = TenantUpdate::fromTenant($tenant);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::fromTenant
     */
    public function testCanCreateFromTenantWithInactiveStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);
        $tenantUpdate = TenantUpdate::fromTenant($tenant);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::INACTIVE, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::fromTenant
     */
    public function testCanCreateFromTenantWithOffloadedStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::OFFLOADED);
        $tenantUpdate = TenantUpdate::fromTenant($tenant);

        $this->assertEquals('tenant1', $tenantUpdate->getName());
        $this->assertEquals(TenantActivityStatus::OFFLOADED, $tenantUpdate->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\TenantUpdate::fromTenant
     */
    public function testFromTenantThrowsExceptionForTransitionalStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::OFFLOADING);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant update only supports ACTIVE, INACTIVE, and OFFLOADED statuses');

        TenantUpdate::fromTenant($tenant);
    }
}
