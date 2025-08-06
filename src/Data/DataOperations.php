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

namespace Weaviate\Data;

use Weaviate\Connection\ConnectionInterface;
use Weaviate\Query\Filter;
use Weaviate\Query\QueryBuilder;

/**
 * Data operations for objects in collections
 */
class DataOperations
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $className,
        private readonly ?string $tenant = null,
        private readonly ?string $defaultQueryFields = null
    ) {
    }

    /**
     * Get the current tenant
     */
    public function getTenant(): ?string
    {
        return $this->tenant;
    }

    /**
     * Create a new object
     *
     * @param array<string, mixed> $properties Object properties
     * @return array<string, mixed> Created object data
     */
    public function create(array $properties): array
    {
        $data = [
            'class' => $this->className,
            'properties' => $this->extractProperties($properties),
        ];

        if (isset($properties['id'])) {
            $data['id'] = $properties['id'];
        }

        if ($this->tenant !== null) {
            $data['tenant'] = $this->tenant;
        }

        return $this->connection->post('/v1/objects', $data);
    }

    /**
     * Get an object by ID
     *
     * @param string $id Object ID
     * @return array<string, mixed> Object data
     */
    public function get(string $id): array
    {
        $path = "/v1/objects/{$this->className}/{$id}";

        if ($this->tenant !== null) {
            $path .= "?tenant=" . urlencode($this->tenant);
        }

        return $this->connection->get($path);
    }

    /**
     * Update an object
     *
     * @param string $id Object ID
     * @param array<string, mixed> $properties Updated properties
     * @return array<string, mixed> Updated object data
     */
    public function update(string $id, array $properties): array
    {
        $path = "/v1/objects/{$this->className}/{$id}";

        if ($this->tenant !== null) {
            $path .= "?tenant={$this->tenant}";
        }

        $data = [
            'properties' => $properties,
        ];

        // For multi-tenant collections, include tenant in the request body
        if ($this->tenant !== null) {
            $data['tenant'] = $this->tenant;
        }

        $this->connection->patch($path, $data);

        // PATCH may return empty response, so fetch the updated object
        return $this->get($id);
    }

    /**
     * Delete an object
     *
     * @param string $id Object ID
     * @return bool Success status
     */
    public function delete(string $id): bool
    {
        $path = "/v1/objects/{$this->className}/{$id}";

        if ($this->tenant !== null) {
            $path .= "?tenant={$this->tenant}";
        }

        return $this->connection->delete($path);
    }

    /**
     * Fetch objects using filters and optional limit
     *
     * @param Filter|null $filters Optional filter to apply
     * @param int|null $limit Optional limit on number of results
     * @return array<int, array<string, mixed>> Array of matching objects
     */
    public function fetchObjects(?Filter $filters = null, ?int $limit = null): array
    {
        $query = new QueryBuilder($this->connection, $this->className, $this->tenant);

        if ($this->defaultQueryFields !== null) {
            $query->setDefaultFields($this->defaultQueryFields);
        }

        if ($filters) {
            $query->where($filters);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->fetchObjects();
    }

    /**
     * Find objects by criteria
     *
     * This is a convenience method that converts simple key-value criteria
     * into appropriate filters and executes the query.
     *
     * @param array<string, mixed> $criteria Key-value pairs to filter by
     * @param int|null $limit Optional limit on number of results
     * @return array<int, array<string, mixed>> Array of matching objects
     */
    public function findBy(array $criteria, ?int $limit = null): array
    {
        $filters = [];
        foreach ($criteria as $property => $value) {
            if ($value === null) {
                $filters[] = Filter::byProperty($property)->isNull(true);
            } else {
                $filters[] = Filter::byProperty($property)->equal($value);
            }
        }

        $filter = count($filters) === 1 ? $filters[0] : Filter::allOf($filters);

        return $this->fetchObjects($filter, $limit);
    }

    /**
     * Find a single object by criteria
     *
     * This method finds the first object matching the given criteria.
     * Returns null if no matching object is found.
     *
     * @param array<string, mixed> $criteria Key-value pairs to filter by
     * @return array<string, mixed>|null The matching object or null if not found
     */
    public function findOneBy(array $criteria): ?array
    {
        $results = $this->findBy($criteria, 1);
        return $results[0] ?? null;
    }

    /**
     * Add a cross-reference between objects
     *
     * @param string $fromUuid The UUID of the source object
     * @param string $fromProperty The property name that holds the reference
     * @param string $to The UUID of the target object to reference
     * @return bool Success status
     */
    public function referenceAdd(string $fromUuid, string $fromProperty, string $to): bool
    {
        $path = "/v1/objects/{$this->className}/{$fromUuid}/references/{$fromProperty}";

        if ($this->tenant !== null) {
            $path .= "?tenant={$this->tenant}";
        }

        $data = [
            'beacon' => "weaviate://localhost/{$to}"
        ];

        try {
            $this->connection->post($path, $data);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Delete a cross-reference between objects
     *
     * @param string $fromUuid The UUID of the source object
     * @param string $fromProperty The property name that holds the reference
     * @param string $to The UUID of the target object to unreference
     * @return bool Success status
     */
    public function referenceDelete(string $fromUuid, string $fromProperty, string $to): bool
    {
        $path = "/v1/objects/{$this->className}/{$fromUuid}/references/{$fromProperty}";

        if ($this->tenant !== null) {
            $path .= "?tenant={$this->tenant}";
        }

        $data = [
            'beacon' => "weaviate://localhost/{$to}"
        ];

        try {
            $this->connection->deleteWithData($path, $data);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Replace cross-references for a property
     *
     * @param string $fromUuid The UUID of the source object
     * @param string $fromProperty The property name that holds the reference
     * @param string|array<string> $to The UUID(s) of the target object(s) to reference
     * @return bool Success status
     */
    public function referenceReplace(string $fromUuid, string $fromProperty, string|array $to): bool
    {
        $path = "/v1/objects/{$this->className}/{$fromUuid}/references/{$fromProperty}";

        if ($this->tenant !== null) {
            $path .= "?tenant={$this->tenant}";
        }

        $beacons = is_array($to)
            ? array_map(fn($uuid) => "weaviate://localhost/{$uuid}", $to)
            : ["weaviate://localhost/{$to}"];

        $data = $beacons;

        try {
            $this->connection->put($path, $data);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Add multiple cross-references in batch
     *
     * @param array<array{fromUuid: string, fromProperty: string, to: string}> $references
     *        Array of reference definitions
     * @return array<string, bool> Results indexed by a key combining fromUuid and fromProperty
     */
    public function referenceAddMany(array $references): array
    {
        $results = [];

        foreach ($references as $ref) {
            // PHPStan knows the array structure, so we don't need isset() checks
            $key = "{$ref['fromUuid']}.{$ref['fromProperty']}";
            $results[$key] = $this->referenceAdd(
                $ref['fromUuid'],
                $ref['fromProperty'],
                $ref['to']
            );
        }

        return $results;
    }

    /**
     * Extract properties from input data, excluding special fields like 'id'
     *
     * @param array<string, mixed> $data Input data
     * @return array<string, mixed> Properties only
     */
    private function extractProperties(array $data): array
    {
        $properties = $data;
        unset($properties['id']);
        return $properties;
    }
}
