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

/**
 * Data operations for objects in collections
 */
class DataOperations
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $className,
        private readonly ?string $tenant = null
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
