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
use Weaviate\Tenants\TenantCreate;

class TenantCreateTest extends TestCase
{
    public function testCanCreateTenantCreateWithDefaults(): void
    {
        $tenantCreate = new TenantCreate('tenant1');

        $this->assertEquals('tenant1', $tenantCreate->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenantCreate->getActivityStatus());
    }

    public function testCanCreateTenantCreateWithActiveStatus(): void
    {
        $tenantCreate = new TenantCreate('tenant1', TenantActivityStatus::ACTIVE);

        $this->assertEquals('tenant1', $tenantCreate->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenantCreate->getActivityStatus());
    }

    public function testCanCreateTenantCreateWithInactiveStatus(): void
    {
        $tenantCreate = new TenantCreate('tenant1', TenantActivityStatus::INACTIVE);

        $this->assertEquals('tenant1', $tenantCreate->getName());
        $this->assertEquals(TenantActivityStatus::INACTIVE, $tenantCreate->getActivityStatus());
    }

    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        new TenantCreate('');
    }

    public function testThrowsExceptionForWhitespaceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        new TenantCreate('   ');
    }

    public function testThrowsExceptionForOffloadedStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant creation only supports ACTIVE and INACTIVE statuses');

        new TenantCreate('tenant1', TenantActivityStatus::OFFLOADED);
    }

    public function testThrowsExceptionForOffloadingStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant creation only supports ACTIVE and INACTIVE statuses');

        new TenantCreate('tenant1', TenantActivityStatus::OFFLOADING);
    }

    public function testThrowsExceptionForOnloadingStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant creation only supports ACTIVE and INACTIVE statuses');

        new TenantCreate('tenant1', TenantActivityStatus::ONLOADING);
    }

    public function testCanConvertToArrayWithActiveStatus(): void
    {
        $tenantCreate = new TenantCreate('tenant1', TenantActivityStatus::ACTIVE);
        $array = $tenantCreate->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'HOT'
        ], $array);
    }

    public function testCanConvertToArrayWithInactiveStatus(): void
    {
        $tenantCreate = new TenantCreate('tenant1', TenantActivityStatus::INACTIVE);
        $array = $tenantCreate->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'COLD'
        ], $array);
    }

    public function testCanCreateFromTenantWithActiveStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::ACTIVE);
        $tenantCreate = TenantCreate::fromTenant($tenant);

        $this->assertEquals('tenant1', $tenantCreate->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenantCreate->getActivityStatus());
    }

    public function testCanCreateFromTenantWithInactiveStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);
        $tenantCreate = TenantCreate::fromTenant($tenant);

        $this->assertEquals('tenant1', $tenantCreate->getName());
        $this->assertEquals(TenantActivityStatus::INACTIVE, $tenantCreate->getActivityStatus());
    }

    public function testFromTenantThrowsExceptionForInvalidStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::OFFLOADED);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant creation only supports ACTIVE and INACTIVE statuses');

        TenantCreate::fromTenant($tenant);
    }
}
