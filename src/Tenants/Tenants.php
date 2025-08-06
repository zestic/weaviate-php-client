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

use Weaviate\Connection\ConnectionInterface;
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\InsufficientPermissionsException;

/**
 * Tenant management API for Weaviate collections
 *
 * Provides comprehensive CRUD operations for managing tenants in multi-tenant collections.
 * This class handles tenant creation, retrieval, updates, and deletion, as well as
 * activity status management.
 *
 * @example Basic tenant operations
 * ```php
 * $client = WeaviateClient::connectToLocal();
 * $collection = $client->collections()->get('MyCollection');
 * $tenants = $collection->tenants();
 *
 * // Create tenants
 * $tenants->create(['tenant1', 'tenant2']);
 * $tenants->create(new Tenant('tenant3', TenantActivityStatus::INACTIVE));
 *
 * // Retrieve tenants
 * $allTenants = $tenants->get();
 * $specificTenant = $tenants->getByName('tenant1');
 *
 * // Update tenant status
 * $tenants->activate('tenant3');
 * $tenants->deactivate('tenant1');
 * $tenants->offload('tenant2');
 *
 * // Remove tenants
 * $tenants->remove(['tenant1', 'tenant2']);
 * ```
 */
class Tenants
{
    /**
     * Create a new tenant management instance
     *
     * @param ConnectionInterface $connection The Weaviate connection
     * @param string $collectionName The name of the collection
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $collectionName
    ) {
    }

    /**
     * Create one or more tenants
     *
     * @param string|Tenant|TenantCreate|array<string|Tenant|TenantCreate> $tenants
     *        Tenant(s) to create. Can be:
     *        - A string (tenant name, will be created as ACTIVE)
     *        - A Tenant object
     *        - A TenantCreate object
     *        - An array of any of the above
     * @throws \InvalidArgumentException If input is invalid
     */
    public function create(string|Tenant|TenantCreate|array $tenants): void
    {
        $tenantData = $this->normalizeTenantInput($tenants, 'create');

        $this->connection->post(
            "/v1/schema/{$this->collectionName}/tenants",
            $tenantData
        );
    }

    /**
     * Remove one or more tenants
     *
     * @param string|Tenant|array<string|Tenant> $tenants
     *        Tenant(s) to remove. Can be:
     *        - A string (tenant name)
     *        - A Tenant object
     *        - An array of strings or Tenant objects
     * @throws \InvalidArgumentException If input is invalid
     */
    public function remove(string|Tenant|array $tenants): void
    {
        $tenantNames = $this->extractTenantNames($tenants);

        $this->connection->deleteWithData(
            "/v1/schema/{$this->collectionName}/tenants",
            $tenantNames
        );
    }

    /**
     * Get all tenants for this collection
     *
     * @return array<string, Tenant> Array of tenants indexed by name
     */
    public function get(): array
    {
        $response = $this->connection->get("/v1/schema/{$this->collectionName}/tenants");

        $tenants = [];
        foreach ($response as $tenantData) {
            $tenant = Tenant::fromArray($tenantData);
            $tenants[$tenant->getName()] = $tenant;
        }

        return $tenants;
    }

    /**
     * Get a specific tenant by name
     *
     * @param string|Tenant $tenant The tenant name or Tenant object
     * @return Tenant|null The tenant if found, null otherwise
     * @throws WeaviateConnectionException For network or connection errors
     * @throws UnexpectedStatusCodeException For HTTP errors other than 404
     * @throws InsufficientPermissionsException For permission errors
     */
    public function getByName(string|Tenant $tenant): ?Tenant
    {
        $tenantName = $tenant instanceof Tenant ? $tenant->getName() : $tenant;

        try {
            $response = $this->connection->get("/v1/schema/{$this->collectionName}/tenants/{$tenantName}");
            return Tenant::fromArray($response);
        } catch (NotFoundException) {
            // Return null for 404 Not Found - tenant doesn't exist
            return null;
        }
        // Let other exceptions (network errors, permission errors, etc.) bubble up
        // as they indicate actual problems that should be handled by the caller
    }

    /**
     * Get multiple tenants by their names
     *
     * @param array<string|Tenant> $tenants Array of tenant names or Tenant objects
     * @return array<string, Tenant> Array of found tenants indexed by name
     */
    public function getByNames(array $tenants): array
    {
        $tenantNames = $this->extractTenantNames($tenants);
        $allTenants = $this->get();

        return array_intersect_key($allTenants, array_flip($tenantNames));
    }

    /**
     * Check if a tenant exists
     *
     * @param string|Tenant $tenant The tenant name or Tenant object
     * @return bool True if the tenant exists, false if it doesn't exist
     * @throws WeaviateConnectionException For network or connection errors
     * @throws UnexpectedStatusCodeException For HTTP errors other than 404
     * @throws InsufficientPermissionsException For permission errors
     */
    public function exists(string|Tenant $tenant): bool
    {
        $tenantName = $tenant instanceof Tenant ? $tenant->getName() : $tenant;

        return $this->connection->head("/v1/schema/{$this->collectionName}/tenants/{$tenantName}");
    }

    /**
     * Check if multiple tenants exist
     *
     * @param array<string|Tenant> $tenants Array of tenant names or Tenant objects
     * @return array<string, bool> Array mapping tenant names to existence status
     */
    public function existsBatch(array $tenants): array
    {
        $results = [];

        foreach ($tenants as $tenant) {
            $tenantName = $tenant instanceof Tenant ? $tenant->getName() : $tenant;
            $results[$tenantName] = $this->exists($tenantName);
        }

        return $results;
    }

    /**
     * Create multiple tenants in batch
     *
     * @param array<string> $tenantNames Array of tenant names to create
     * @return void
     */
    public function createBatch(array $tenantNames): void
    {
        $this->create($tenantNames);
    }

    /**
     * Activate multiple tenants in batch
     *
     * @param array<string> $tenantNames Array of tenant names to activate
     * @return void
     */
    public function activateBatch(array $tenantNames): void
    {
        $tenants = [];
        foreach ($tenantNames as $name) {
            $tenants[] = new TenantUpdate($name, TenantActivityStatus::ACTIVE);
        }

        $this->update($tenants);
    }

    /**
     * Update one or more tenants
     *
     * @param Tenant|TenantUpdate|array<Tenant|TenantUpdate> $tenants
     *        Tenant(s) to update. Can be:
     *        - A Tenant object
     *        - A TenantUpdate object
     *        - An array of Tenant or TenantUpdate objects
     * @throws \InvalidArgumentException If input is invalid
     */
    public function update(Tenant|TenantUpdate|array $tenants): void
    {
        $tenantData = $this->normalizeTenantInput($tenants, 'update');

        $this->connection->put(
            "/v1/schema/{$this->collectionName}/tenants",
            $tenantData
        );
    }

    /**
     * Activate one or more tenants
     *
     * @param string|Tenant|array<string|Tenant> $tenants Tenant(s) to activate
     */
    public function activate(string|Tenant|array $tenants): void
    {
        $this->updateTenantStatus($tenants, TenantActivityStatus::ACTIVE);
    }

    /**
     * Deactivate one or more tenants
     *
     * @param string|Tenant|array<string|Tenant> $tenants Tenant(s) to deactivate
     */
    public function deactivate(string|Tenant|array $tenants): void
    {
        $this->updateTenantStatus($tenants, TenantActivityStatus::INACTIVE);
    }

    /**
     * Offload one or more tenants to cloud storage
     *
     * @param string|Tenant|array<string|Tenant> $tenants Tenant(s) to offload
     */
    public function offload(string|Tenant|array $tenants): void
    {
        $this->updateTenantStatus($tenants, TenantActivityStatus::OFFLOADED);
    }

    /**
     * Update tenant status for multiple tenants
     *
     * @param string|Tenant|array<string|Tenant> $tenants
     * @param TenantActivityStatus $status
     */
    private function updateTenantStatus(string|Tenant|array $tenants, TenantActivityStatus $status): void
    {
        $tenantNames = $this->extractTenantNames($tenants);
        $updates = [];

        foreach ($tenantNames as $name) {
            $updates[] = (new TenantUpdate($name, $status))->toArray();
        }

        // ConnectionInterface expects array<string, mixed> but we need to send indexed array for tenant updates
        // This is correct for the Weaviate API which expects an array of tenant objects
        /** @var array<string, mixed> $updates */
        $this->connection->put(
            "/v1/schema/{$this->collectionName}/tenants",
            $updates
        );
    }

    /**
     * Extract tenant names from various input formats
     *
     * @param string|Tenant|array<string|Tenant> $tenants
     * @return array<string>
     */
    private function extractTenantNames(string|Tenant|array $tenants): array
    {
        if (is_string($tenants)) {
            return [$tenants];
        }

        if ($tenants instanceof Tenant) {
            return [$tenants->getName()];
        }

        $names = [];
        foreach ($tenants as $tenant) {
            $names[] = $tenant instanceof Tenant ? $tenant->getName() : $tenant;
        }

        return $names;
    }

    /**
     * Normalize tenant input to array format for API calls
     *
     * @param mixed $tenants
     * @param string $operation 'create' or 'update'
     * @return array<array<string, string>>
     */
    private function normalizeTenantInput(mixed $tenants, string $operation): array
    {
        if (!is_array($tenants)) {
            $tenants = [$tenants];
        }

        $normalized = [];
        foreach ($tenants as $tenant) {
            if (is_string($tenant)) {
                if ($operation === 'create') {
                    $normalized[] = (new TenantCreate($tenant))->toArray();
                } else {
                    throw new \InvalidArgumentException('String tenant names are not supported for updates');
                }
            } elseif ($tenant instanceof Tenant) {
                if ($operation === 'create') {
                    $normalized[] = TenantCreate::fromTenant($tenant)->toArray();
                } else {
                    $normalized[] = TenantUpdate::fromTenant($tenant)->toArray();
                }
            } elseif ($tenant instanceof TenantCreate && $operation === 'create') {
                $normalized[] = $tenant->toArray();
            } elseif ($tenant instanceof TenantUpdate && $operation === 'update') {
                $normalized[] = $tenant->toArray();
            } else {
                throw new \InvalidArgumentException('Invalid tenant input type for ' . $operation);
            }
        }

        return $normalized;
    }
}
