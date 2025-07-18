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
 * Represents a tenant update request in Weaviate
 *
 * This class is specifically designed for tenant update operations.
 * It allows activity statuses that are valid for updates (ACTIVE, INACTIVE, OFFLOADED),
 * but prevents setting transitional states directly.
 *
 * @example
 * ```php
 * // Update tenant to inactive
 * $tenantUpdate = new TenantUpdate('customer-123', TenantActivityStatus::INACTIVE);
 *
 * // Update tenant to offloaded
 * $tenantUpdate = new TenantUpdate('customer-456', TenantActivityStatus::OFFLOADED);
 *
 * // Convert to array for API call
 * $data = $tenantUpdate->toArray();
 * ```
 */
class TenantUpdate
{
    /**
     * Create a new tenant update request
     *
     * @param string $name The tenant name (must not be empty)
     * @param TenantActivityStatus $activityStatus The new activity status
     * @throws \InvalidArgumentException If the name is empty or status is invalid for updates
     */
    public function __construct(
        private readonly string $name,
        private readonly TenantActivityStatus $activityStatus
    ) {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException('Tenant name cannot be empty');
        }

        // Only allow statuses that can be set directly (not transitional states)
        if (!$activityStatus->isWritable()) {
            throw new \InvalidArgumentException(
                'Tenant update only supports ACTIVE, INACTIVE, and OFFLOADED statuses. ' .
                'Transitional statuses (OFFLOADING, ONLOADING) cannot be set directly.'
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
     * Get the tenant's new activity status
     *
     * @return TenantActivityStatus The activity status
     */
    public function getActivityStatus(): TenantActivityStatus
    {
        return $this->activityStatus;
    }

    /**
     * Convert the tenant update request to an array representation
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
            TenantActivityStatus::OFFLOADED => 'FROZEN',
            // These cases should never occur due to constructor validation, but PHPStan requires them
            TenantActivityStatus::OFFLOADING,
            TenantActivityStatus::ONLOADING => throw new \InvalidArgumentException(
                'Invalid status for tenant update: ' . $this->activityStatus->value
            ),
        };
    }

    /**
     * Create a TenantUpdate from a Tenant object
     *
     * @param Tenant $tenant The tenant to convert
     * @return TenantUpdate The tenant update request
     * @throws \InvalidArgumentException If the tenant's status is invalid for updates
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getName(), $tenant->getActivityStatus());
    }
}
