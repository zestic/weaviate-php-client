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

namespace Weaviate\Collections;

use Weaviate\Connection\ConnectionInterface;
use Weaviate\Data\DataOperations;

/**
 * Individual collection operations
 */
class Collection
{
    private ?string $tenant = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $name
    ) {
    }

    /**
     * Set the tenant for multi-tenancy operations
     */
    public function withTenant(string $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    /**
     * Get the current tenant
     */
    public function getTenant(): ?string
    {
        return $this->tenant;
    }

    /**
     * Get data operations for this collection
     */
    public function data(): DataOperations
    {
        return new DataOperations($this->connection, $this->name, $this->tenant);
    }

    /**
     * Get tenant operations for this collection
     */
    public function tenants(): TenantOperations
    {
        return new TenantOperations($this->connection, $this->name);
    }
}
