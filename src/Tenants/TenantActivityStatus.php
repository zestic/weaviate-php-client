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

namespace Weaviate\Tenants;

/**
 * Tenant activity status enumeration
 *
 * Defines the possible activity states for a tenant in Weaviate.
 * These statuses control how the tenant's data is stored and accessed.
 *
 * @example
 * ```php
 * // Create tenant with specific activity status
 * $tenant = new Tenant('my-tenant', TenantActivityStatus::INACTIVE);
 *
 * // Check if status is writable
 * if ($tenant->getActivityStatus()->isWritable()) {
 *     // Can modify tenant status
 * }
 * ```
 */
enum TenantActivityStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case OFFLOADED = 'OFFLOADED';
    case OFFLOADING = 'OFFLOADING';
    case ONLOADING = 'ONLOADING';

    /**
     * @param string $value The status string
     * @return TenantActivityStatus
     * @throws \InvalidArgumentException If the status is invalid
     */
    public static function fromString(string $value): self
    {
        return match (strtoupper($value)) {
            'ACTIVE', 'HOT' => self::ACTIVE,  // HOT is legacy name for ACTIVE
            'INACTIVE', 'COLD' => self::INACTIVE,  // COLD is legacy name for INACTIVE
            'OFFLOADED', 'FROZEN' => self::OFFLOADED,  // FROZEN is legacy name for OFFLOADED
            'OFFLOADING' => self::OFFLOADING,
            'ONLOADING' => self::ONLOADING,
            default => throw new \InvalidArgumentException("Invalid tenant activity status: {$value}")
        };
    }

    /**
     * Returns whether this status can be set directly by tenant update operations.
     */
    public function isWritable(): bool
    {
        return match ($this) {
            self::ACTIVE, self::INACTIVE, self::OFFLOADED => true,
            self::OFFLOADING, self::ONLOADING => false,
        };
    }

    /**
     * Returns whether this status is a transitional state.
     */
    public function isTransitional(): bool
    {
        return match ($this) {
            self::OFFLOADING, self::ONLOADING => true,
            self::ACTIVE, self::INACTIVE, self::OFFLOADED => false,
        };
    }
}
