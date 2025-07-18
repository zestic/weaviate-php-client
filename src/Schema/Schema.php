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

namespace Weaviate\Schema;

use Weaviate\Connection\ConnectionInterface;

/**
 * Schema management API
 *
 * Provides comprehensive schema management capabilities including collection CRUD operations,
 * property management, and schema validation. This class enables programmatic management
 * of Weaviate collections and their configurations.
 *
 * @example Basic schema operations
 * ```php
 * $client = WeaviateClient::connectToLocal();
 * $schema = $client->schema();
 *
 * // Check if collection exists
 * if (!$schema->exists('Article')) {
 *     // Create collection
 *     $schema->create([
 *         'class' => 'Article',
 *         'properties' => [
 *             ['name' => 'title', 'dataType' => ['text']],
 *             ['name' => 'content', 'dataType' => ['text']],
 *         ]
 *     ]);
 * }
 *
 * // Get specific collection schema
 * $articleSchema = $schema->get('Article');
 *
 * // Add property to existing collection
 * $schema->addProperty('Article', [
 *     'name' => 'author',
 *     'dataType' => ['text']
 * ]);
 * ```
 */
class Schema
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Get the complete schema or a specific collection schema
     *
     * @param string|null $className Optional collection name to get specific schema
     * @return array<string, mixed> Schema data
     */
    public function get(?string $className = null): array
    {
        if ($className !== null) {
            return $this->connection->get("/v1/schema/{$className}");
        }

        return $this->connection->get('/v1/schema');
    }

    /**
     * Check if a collection exists
     *
     * @param string $className Collection name
     * @return bool True if collection exists
     */
    public function exists(string $className): bool
    {
        try {
            $this->connection->get("/v1/schema/{$className}");
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Create a new collection
     *
     * @param array<string, mixed> $classDefinition Collection definition
     * @return array<string, mixed> Created collection data
     * @throws \InvalidArgumentException If class definition is invalid
     */
    public function create(array $classDefinition): array
    {
        $this->validateClassDefinition($classDefinition);

        return $this->connection->post('/v1/schema', $classDefinition);
    }

    /**
     * Update an existing collection
     *
     * @param string $className Collection name
     * @param array<string, mixed> $updates Updates to apply
     * @return array<string, mixed> Updated collection data
     */
    public function update(string $className, array $updates): array
    {
        $this->connection->put("/v1/schema/{$className}", $updates);

        // Return updated schema
        return $this->get($className);
    }

    /**
     * Delete a collection
     *
     * @param string $className Collection name
     * @return bool True if deletion was successful
     */
    public function delete(string $className): bool
    {
        return $this->connection->delete("/v1/schema/{$className}");
    }

    /**
     * Add a property to an existing collection
     *
     * @param string $className Collection name
     * @param array<string, mixed> $property Property definition
     * @return array<string, mixed> Updated collection data
     * @throws \InvalidArgumentException If property definition is invalid
     */
    public function addProperty(string $className, array $property): array
    {
        $this->validatePropertyDefinition($property);

        $this->connection->post("/v1/schema/{$className}/properties", $property);

        // Return updated schema
        return $this->get($className);
    }

    /**
     * Update a property in an existing collection
     *
     * @param string $className Collection name
     * @param string $propertyName Property name
     * @param array<string, mixed> $updates Updates to apply
     * @return array<string, mixed> Updated collection data
     */
    public function updateProperty(string $className, string $propertyName, array $updates): array
    {
        $this->connection->put("/v1/schema/{$className}/properties/{$propertyName}", $updates);

        // Return updated schema
        return $this->get($className);
    }

    /**
     * Delete a property from an existing collection
     *
     * @param string $className Collection name
     * @param string $propertyName Property name
     * @return bool True if deletion was successful
     */
    public function deleteProperty(string $className, string $propertyName): bool
    {
        return $this->connection->delete("/v1/schema/{$className}/properties/{$propertyName}");
    }

    /**
     * Get a specific property from a collection
     *
     * @param string $className Collection name
     * @param string $propertyName Property name
     * @return array<string, mixed> Property definition
     */
    public function getProperty(string $className, string $propertyName): array
    {
        $schema = $this->get($className);

        foreach ($schema['properties'] ?? [] as $property) {
            if ($property['name'] === $propertyName) {
                return $property;
            }
        }

        throw new \InvalidArgumentException("Property '{$propertyName}' not found in collection '{$className}'");
    }

    /**
     * Validate class definition
     *
     * @param array<string, mixed> $classDefinition
     * @throws \InvalidArgumentException
     */
    private function validateClassDefinition(array $classDefinition): void
    {
        if (!isset($classDefinition['class']) || !is_string($classDefinition['class'])) {
            throw new \InvalidArgumentException('Class definition must include a "class" field with string value');
        }

        if (empty($classDefinition['class'])) {
            throw new \InvalidArgumentException('Class name cannot be empty');
        }

        // Validate properties if provided
        if (isset($classDefinition['properties'])) {
            if (!is_array($classDefinition['properties'])) {
                throw new \InvalidArgumentException('Properties must be an array');
            }

            foreach ($classDefinition['properties'] as $property) {
                $this->validatePropertyDefinition($property);
            }
        }
    }

    /**
     * Validate property definition
     *
     * @param array<string, mixed> $property
     * @throws \InvalidArgumentException
     */
    private function validatePropertyDefinition(array $property): void
    {
        if (!isset($property['name']) || !is_string($property['name'])) {
            throw new \InvalidArgumentException('Property definition must include a "name" field with string value');
        }

        if (empty($property['name'])) {
            throw new \InvalidArgumentException('Property name cannot be empty');
        }

        if (!isset($property['dataType']) || !is_array($property['dataType'])) {
            throw new \InvalidArgumentException('Property definition must include a "dataType" field with array value');
        }

        if (empty($property['dataType'])) {
            throw new \InvalidArgumentException('Property dataType cannot be empty');
        }

        // Validate data types
        $validDataTypes = [
            'text', 'string', 'int', 'number', 'boolean', 'date', 'uuid',
            'geoCoordinates', 'phoneNumber', 'blob', 'object', 'text[]',
            'string[]', 'int[]', 'number[]', 'boolean[]', 'date[]', 'uuid[]'
        ];

        foreach ($property['dataType'] as $dataType) {
            if (!in_array($dataType, $validDataTypes, true) && !$this->isReferenceDataType($dataType)) {
                throw new \InvalidArgumentException("Invalid data type: {$dataType}");
            }
        }
    }

    /**
     * Check if data type is a reference to another class
     *
     * @param string $dataType
     * @return bool
     */
    private function isReferenceDataType(string $dataType): bool
    {
        // Reference data types are typically class names (start with uppercase)
        return ctype_upper($dataType[0] ?? '');
    }
}
