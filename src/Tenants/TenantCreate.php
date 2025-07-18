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
 * Represents a tenant to be created in Weaviate
 *
 * This class is specifically designed for tenant creation operations.
 * It only allows activity statuses that are valid for new tenants
 * (ACTIVE and INACTIVE), preventing creation with transitional states.
 *
 * @example
 * ```php
 * // Create an active tenant
 * $tenantCreate = new TenantCreate('customer-123');
 *
 * // Create an inactive tenant
 * $tenantCreate = new TenantCreate('customer-456', TenantActivityStatus::INACTIVE);
 *
 * // Convert to array for API call
 * $data = $tenantCreate->toArray();
 * ```
 */
class TenantCreate
{
    /**
     * Create a new tenant creation request
     *
     * @param string $name The tenant name (must not be empty)
     * @param TenantActivityStatus $activityStatus The initial activity status
     * @throws \InvalidArgumentException If the name is empty or status is invalid for creation
     */
    public function __construct(
        private readonly string $name,
        private readonly TenantActivityStatus $activityStatus = TenantActivityStatus::ACTIVE
    ) {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Tenant name cannot be empty');
        }

        // Only allow statuses that are valid for tenant creation
        if (!in_array($activityStatus, [TenantActivityStatus::ACTIVE, TenantActivityStatus::INACTIVE], true)) {
            throw new \InvalidArgumentException(
                'Tenant creation only supports ACTIVE and INACTIVE statuses. ' .
                'Other statuses are read-only or transitional.'
            );
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
     * Get the tenant's initial activity status
     *
     * @return TenantActivityStatus The activity status
     */
    public function getActivityStatus(): TenantActivityStatus
    {
        return $this->activityStatus;
    }

    /**
     * Convert the tenant creation request to an array representation
     *
     * This is used for API calls to Weaviate.
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
            // These cases should never occur due to constructor validation, but PHPStan requires them
            TenantActivityStatus::OFFLOADED,
            TenantActivityStatus::OFFLOADING,
            TenantActivityStatus::ONLOADING => throw new \InvalidArgumentException(
                'Invalid status for tenant creation: ' . $this->activityStatus->value
            ),
        };
    }

    /**
     * Create a TenantCreate from a Tenant object
     *
     * @param Tenant $tenant The tenant to convert
     * @return TenantCreate The tenant creation request
     * @throws \InvalidArgumentException If the tenant's status is invalid for creation
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getName(), $tenant->getActivityStatus());
    }
}
