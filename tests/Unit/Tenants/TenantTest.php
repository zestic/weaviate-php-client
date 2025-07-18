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

class TenantTest extends TestCase
{
    /**
     * @covers \Weaviate\Tenants\Tenant::__construct
     * @covers \Weaviate\Tenants\Tenant::getName
     * @covers \Weaviate\Tenants\Tenant::getActivityStatus
     */
    public function testCanCreateTenantWithDefaults(): void
    {
        $tenant = new Tenant('tenant1');

        $this->assertEquals('tenant1', $tenant->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenant->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::__construct
     * @covers \Weaviate\Tenants\Tenant::getName
     * @covers \Weaviate\Tenants\Tenant::getActivityStatus
     */
    public function testCanCreateTenantWithActivityStatus(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);

        $this->assertEquals('tenant1', $tenant->getName());
        $this->assertEquals(TenantActivityStatus::INACTIVE, $tenant->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::withActivityStatus
     */
    public function testCanCreateTenantWithDifferentActivityStatus(): void
    {
        $tenant = new Tenant('tenant1');
        $newTenant = $tenant->withActivityStatus(TenantActivityStatus::OFFLOADED);

        $this->assertEquals('tenant1', $newTenant->getName());
        $this->assertEquals(TenantActivityStatus::OFFLOADED, $newTenant->getActivityStatus());

        // Original tenant should be unchanged
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenant->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::toArray
     */
    public function testCanConvertToArray(): void
    {
        $tenant = new Tenant('tenant1', TenantActivityStatus::INACTIVE);
        $array = $tenant->toArray();

        $this->assertEquals([
            'name' => 'tenant1',
            'activityStatus' => 'COLD'
        ], $array);
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::fromArray
     */
    public function testCanCreateFromArray(): void
    {
        $array = [
            'name' => 'tenant1',
            'activityStatus' => 'FROZEN'
        ];

        $tenant = Tenant::fromArray($array);

        $this->assertEquals('tenant1', $tenant->getName());
        $this->assertEquals(TenantActivityStatus::OFFLOADED, $tenant->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::fromArray
     */
    public function testCanCreateFromArrayWithDefaults(): void
    {
        $array = ['name' => 'tenant1'];

        $tenant = Tenant::fromArray($array);

        $this->assertEquals('tenant1', $tenant->getName());
        $this->assertEquals(TenantActivityStatus::ACTIVE, $tenant->getActivityStatus());
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::__construct
     */
    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name cannot be empty');

        new Tenant('');
    }

    /**
     * @covers \Weaviate\Tenants\Tenant::fromArray
     */
    public function testFromArrayThrowsExceptionForMissingName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tenant name is required');

        Tenant::fromArray([]);
    }
}
