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
use Weaviate\Exceptions\NotFoundException;

/**
 * Collections management API
 *
 * Provides methods to create, read, update, and delete Weaviate collections (schemas).
 * Collections define the structure of your data objects and their properties.
 *
 * @example
 * ```php
 * $client = WeaviateClient::connectToLocal();
 * $collections = $client->collections();
 *
 * // Check if collection exists
 * if (!$collections->exists('Article')) {
 *     // Create collection
 *     $collections->create('Article', [
 *         'properties' => [
 *             ['name' => 'title', 'dataType' => ['text']],
 *             ['name' => 'content', 'dataType' => ['text']],
 *         ]
 *     ]);
 * }
 *
 * // Get collection instance for data operations
 * $collection = $collections->get('Article');
 * ```
 */
class Collections
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Check if a collection exists
     */
    public function exists(string $name): bool
    {
        try {
            $this->connection->get("/v1/schema/{$name}");
            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    /**
     * Create a new collection
     *
     * @param string $name Collection name
     * @param array<string, mixed> $config Collection configuration
     * @return array<string, mixed> Created collection data
     */
    public function create(string $name, array $config = []): array
    {
        $data = array_merge([
            'class' => $name,
        ], $config);

        return $this->connection->post('/v1/schema', $data);
    }

    /**
     * Get a collection instance
     */
    public function get(string $name): Collection
    {
        return new Collection($this->connection, $name);
    }

    /**
     * Delete a collection
     */
    public function delete(string $name): bool
    {
        return $this->connection->delete("/v1/schema/{$name}");
    }
}
