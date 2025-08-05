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
use Weaviate\Query\QueryBuilder;
use Weaviate\Tenants\Tenants;

/**
 * Individual collection operations
 *
 * Represents a specific Weaviate collection and provides access to data operations
 * and tenant-specific functionality. This class is obtained through the Collections
 * API and provides a fluent interface for working with collection data.
 *
 * @example Basic data operations
 * ```php
 * $client = WeaviateClient::connectToLocal();
 * $collection = $client->collections()->get('Article');
 *
 * // Create an object
 * $result = $collection->data()->create([
 *     'title' => 'My Article',
 *     'content' => 'Article content...'
 * ]);
 *
 * // Get an object
 * $article = $collection->data()->get($result['id']);
 *
 * // Update an object
 * $collection->data()->update($result['id'], [
 *     'title' => 'Updated Title'
 * ]);
 * ```
 *
 * @example Multi-tenant operations
 * ```php
 * $collection = $client->collections()->get('Article');
 *
 * // Work with specific tenant
 * $tenantCollection = $collection->withTenant('tenant-123');
 * $result = $tenantCollection->data()->create([
 *     'title' => 'Tenant-specific article'
 * ]);
 * ```
 */
class Collection
{
    private ?string $tenant = null;
    private ?string $defaultQueryFields = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $name
    ) {
    }

    /**
     * Set the tenant for multi-tenancy operations
     *
     * Returns a new Collection instance with the specified tenant,
     * leaving the original collection unchanged.
     */
    public function withTenant(string $tenant): static
    {
        $clone = clone $this;
        $clone->tenant = $tenant;
        $clone->defaultQueryFields = $this->defaultQueryFields;
        return $clone;
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
        return new DataOperations($this->connection, $this->name, $this->tenant, $this->defaultQueryFields);
    }

    /**
     * Get tenant operations for this collection
     */
    public function tenants(): Tenants
    {
        return new Tenants($this->connection, $this->name);
    }

    /**
     * Get query builder for this collection
     *
     * Creates a QueryBuilder instance that allows building and executing
     * GraphQL queries with filtering, property selection, and limits.
     */
    public function query(): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this->connection, $this->name, $this->tenant);

        // Set collection-specific default fields if configured
        if ($this->defaultQueryFields !== null) {
            $queryBuilder->setDefaultFields($this->defaultQueryFields);
        }

        return $queryBuilder;
    }

    /**
     * Set default query fields for this collection
     *
     * These fields will be returned by default when no specific properties
     * are requested in queries.
     *
     * @param string $fields Space-separated list of field names
     * @return $this
     */
    public function setDefaultQueryFields(string $fields): self
    {
        $this->defaultQueryFields = $fields;
        return $this;
    }
}
