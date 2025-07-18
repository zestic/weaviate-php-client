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
use Weaviate\Tenants\TenantActivityStatus;

class TenantActivityStatusTest extends TestCase
{
    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus
     */
    public function testHasAllRequiredStatuses(): void
    {
        $this->assertEquals('ACTIVE', TenantActivityStatus::ACTIVE->value);
        $this->assertEquals('INACTIVE', TenantActivityStatus::INACTIVE->value);
        $this->assertEquals('OFFLOADED', TenantActivityStatus::OFFLOADED->value);
        $this->assertEquals('OFFLOADING', TenantActivityStatus::OFFLOADING->value);
        $this->assertEquals('ONLOADING', TenantActivityStatus::ONLOADING->value);
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::fromString
     */
    public function testCanCreateFromString(): void
    {
        $this->assertEquals(TenantActivityStatus::ACTIVE, TenantActivityStatus::fromString('ACTIVE'));
        $this->assertEquals(TenantActivityStatus::INACTIVE, TenantActivityStatus::fromString('INACTIVE'));
        $this->assertEquals(TenantActivityStatus::OFFLOADED, TenantActivityStatus::fromString('OFFLOADED'));
        $this->assertEquals(TenantActivityStatus::OFFLOADING, TenantActivityStatus::fromString('OFFLOADING'));
        $this->assertEquals(TenantActivityStatus::ONLOADING, TenantActivityStatus::fromString('ONLOADING'));
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::fromString
     */
    public function testCanCreateFromLegacyNames(): void
    {
        // Test legacy HOT -> ACTIVE mapping
        $this->assertEquals(TenantActivityStatus::ACTIVE, TenantActivityStatus::fromString('HOT'));

        // Test legacy COLD -> INACTIVE mapping
        $this->assertEquals(TenantActivityStatus::INACTIVE, TenantActivityStatus::fromString('COLD'));

        // Test legacy FROZEN -> OFFLOADED mapping
        $this->assertEquals(TenantActivityStatus::OFFLOADED, TenantActivityStatus::fromString('FROZEN'));
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::fromString
     */
    public function testFromStringIsCaseInsensitive(): void
    {
        $this->assertEquals(TenantActivityStatus::ACTIVE, TenantActivityStatus::fromString('active'));
        $this->assertEquals(TenantActivityStatus::ACTIVE, TenantActivityStatus::fromString('hot'));
        $this->assertEquals(TenantActivityStatus::INACTIVE, TenantActivityStatus::fromString('inactive'));
        $this->assertEquals(TenantActivityStatus::INACTIVE, TenantActivityStatus::fromString('cold'));
        $this->assertEquals(TenantActivityStatus::OFFLOADED, TenantActivityStatus::fromString('offloaded'));
        $this->assertEquals(TenantActivityStatus::OFFLOADED, TenantActivityStatus::fromString('frozen'));
        $this->assertEquals(TenantActivityStatus::OFFLOADING, TenantActivityStatus::fromString('offloading'));
        $this->assertEquals(TenantActivityStatus::ONLOADING, TenantActivityStatus::fromString('onloading'));
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::fromString
     */
    public function testFromStringThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant activity status: INVALID');

        TenantActivityStatus::fromString('INVALID');
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::isWritable
     */
    public function testCanIdentifyWritableStatuses(): void
    {
        $this->assertTrue(TenantActivityStatus::ACTIVE->isWritable());
        $this->assertTrue(TenantActivityStatus::INACTIVE->isWritable());
        $this->assertTrue(TenantActivityStatus::OFFLOADED->isWritable());
        $this->assertFalse(TenantActivityStatus::OFFLOADING->isWritable());
        $this->assertFalse(TenantActivityStatus::ONLOADING->isWritable());
    }

    /**
     * @covers \Weaviate\Tenants\TenantActivityStatus::isTransitional
     */
    public function testCanIdentifyTransitionalStatuses(): void
    {
        $this->assertFalse(TenantActivityStatus::ACTIVE->isTransitional());
        $this->assertFalse(TenantActivityStatus::INACTIVE->isTransitional());
        $this->assertFalse(TenantActivityStatus::OFFLOADED->isTransitional());
        $this->assertTrue(TenantActivityStatus::OFFLOADING->isTransitional());
        $this->assertTrue(TenantActivityStatus::ONLOADING->isTransitional());
    }
}
