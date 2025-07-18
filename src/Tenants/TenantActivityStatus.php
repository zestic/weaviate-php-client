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
    /**
     * The tenant is fully active and can be used for all operations.
     * Data is stored locally and immediately accessible.
     */
    case ACTIVE = 'ACTIVE';

    /**
     * The tenant is not active but files are stored locally.
     * Data operations are not available but can be quickly reactivated.
     */
    case INACTIVE = 'INACTIVE';

    /**
     * The tenant is not active and files are stored on cloud storage.
     * Data operations are not available and reactivation takes longer.
     */
    case OFFLOADED = 'OFFLOADED';

    /**
     * The tenant is in the process of being offloaded to cloud storage.
     * This is a transitional state and cannot be set directly.
     */
    case OFFLOADING = 'OFFLOADING';

    /**
     * The tenant is in the process of being activated from cloud storage.
     * This is a transitional state and cannot be set directly.
     */
    case ONLOADING = 'ONLOADING';

    /**
     * Create a TenantActivityStatus from a string value
     *
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
     * Check if this status can be set by users (not transitional)
     *
     * @return bool True if the status can be set directly
     */
    public function isWritable(): bool
    {
        return match ($this) {
            self::ACTIVE, self::INACTIVE, self::OFFLOADED => true,
            self::OFFLOADING, self::ONLOADING => false,
        };
    }

    /**
     * Check if this is a transitional status
     *
     * @return bool True if the status is transitional
     */
    public function isTransitional(): bool
    {
        return match ($this) {
            self::OFFLOADING, self::ONLOADING => true,
            self::ACTIVE, self::INACTIVE, self::OFFLOADED => false,
        };
    }
}
