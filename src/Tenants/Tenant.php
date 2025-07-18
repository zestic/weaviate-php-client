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
 * Represents a tenant in Weaviate
 *
 * A tenant is an isolated data partition within a multi-tenant collection.
 * Each tenant has a unique name and an activity status that determines
 * how its data is stored and accessed.
 *
 * @example Basic usage
 * ```php
 * // Create an active tenant
 * $tenant = new Tenant('customer-123');
 *
 * // Create an inactive tenant
 * $tenant = new Tenant('customer-456', TenantActivityStatus::INACTIVE);
 *
 * // Create a new tenant with different status
 * $offloadedTenant = $tenant->withActivityStatus(TenantActivityStatus::OFFLOADED);
 * ```
 *
 * @example Array conversion
 * ```php
 * $tenant = new Tenant('customer-123', TenantActivityStatus::INACTIVE);
 *
 * // Convert to array for API calls
 * $array = $tenant->toArray();
 * // ['name' => 'customer-123', 'activityStatus' => 'INACTIVE']
 *
 * // Create from API response
 * $tenant = Tenant::fromArray($array);
 * ```
 */
class Tenant
{
    /**
     * Create a new tenant
     *
     * @param string $name The tenant name (must not be empty)
     * @param TenantActivityStatus $activityStatus The tenant's activity status
     * @throws \InvalidArgumentException If the name is empty
     */
    public function __construct(
        private readonly string $name,
        private readonly TenantActivityStatus $activityStatus = TenantActivityStatus::ACTIVE
    ) {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Tenant name cannot be empty');
        }
    }

    /**
     * Get the tenant name
     *
     * @return string The tenant name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tenant's activity status
     *
     * @return TenantActivityStatus The activity status
     */
    public function getActivityStatus(): TenantActivityStatus
    {
        return $this->activityStatus;
    }

    /**
     * Create a new tenant with a different activity status
     *
     * This method returns a new Tenant instance with the specified activity status,
     * leaving the original tenant unchanged (immutable pattern).
     *
     * @param TenantActivityStatus $activityStatus The new activity status
     * @return Tenant A new tenant instance with the updated status
     */
    public function withActivityStatus(TenantActivityStatus $activityStatus): self
    {
        return new self($this->name, $activityStatus);
    }

    /**
     * Convert the tenant to an array representation
     *
     * This is useful for API calls and serialization.
     *
     * @return array<string, string> Array with 'name' and 'activityStatus' keys
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'activityStatus' => $this->getApiStatusValue(),
        ];
    }

    /**
     * Get the API status value (maps modern names to legacy API values)
     *
     * @return string The status value expected by the Weaviate API
     */
    private function getApiStatusValue(): string
    {
        return match ($this->activityStatus) {
            TenantActivityStatus::ACTIVE => 'HOT',
            TenantActivityStatus::INACTIVE => 'COLD',
            TenantActivityStatus::OFFLOADED => 'FROZEN',
            TenantActivityStatus::OFFLOADING => 'OFFLOADING',
            TenantActivityStatus::ONLOADING => 'ONLOADING',
        };
    }

    /**
     * Create a tenant from an array representation
     *
     * This is useful for creating tenants from API responses.
     *
     * @param array<string, mixed> $data Array containing tenant data
     * @return Tenant The created tenant
     * @throws \InvalidArgumentException If required data is missing or invalid
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Tenant name is required');
        }

        $activityStatus = TenantActivityStatus::ACTIVE;
        if (isset($data['activityStatus']) && is_string($data['activityStatus'])) {
            $activityStatus = TenantActivityStatus::fromString($data['activityStatus']);
        }

        return new self($data['name'], $activityStatus);
    }
}
