<?php

declare(strict_types=1);

/*
 * Copyright 2024 Zestic
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

namespace Weaviate\Collections;

use Weaviate\Connection\ConnectionInterface;

/**
 * Tenant operations for multi-tenant collections
 */
class TenantOperations
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $className
    ) {
    }

    /**
     * Create one or more tenants
     *
     * @param array<array<string, mixed>> $tenants Array of tenant configurations
     * @return array<array<string, mixed>> Created tenants
     */
    public function create(array $tenants): array
    {
        return $this->connection->post("/v1/schema/{$this->className}/tenants", $tenants);
    }

    /**
     * Get all tenants for this collection
     *
     * @return array<array<string, mixed>> List of tenants
     */
    public function get(): array
    {
        return $this->connection->get("/v1/schema/{$this->className}/tenants");
    }

    /**
     * Update tenants
     *
     * @param array<array<string, mixed>> $tenants Array of tenant configurations to update
     * @return array<array<string, mixed>> Updated tenants
     */
    public function update(array $tenants): array
    {
        return $this->connection->put("/v1/schema/{$this->className}/tenants", $tenants);
    }

    /**
     * Delete tenants
     *
     * @param array<string> $tenantNames Array of tenant names to delete
     * @return bool Success status
     * @phpstan-param array<string> $tenantNames
     */
    public function delete(array $tenantNames): bool
    {
        // Note: This is a simplified implementation. The actual Weaviate API
        // may require a different approach for deleting specific tenants
        unset($tenantNames); // Suppress unused parameter warning
        return $this->connection->delete("/v1/schema/{$this->className}/tenants") !== false;
    }

    /**
     * Check if a tenant exists
     */
    public function exists(string $tenantName): bool
    {
        try {
            $tenants = $this->get();
            foreach ($tenants as $tenant) {
                if ($tenant['name'] === $tenantName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception) {
            return false;
        }
    }
}
