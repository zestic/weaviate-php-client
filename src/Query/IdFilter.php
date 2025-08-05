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

namespace Weaviate\Query;

/**
 * ID-based filter for Weaviate GraphQL queries
 *
 * This class provides filtering capabilities on object IDs. It's specifically
 * designed for filtering by Weaviate object identifiers and supports operations
 * like exact matching, exclusion, and multiple ID selection.
 *
 * @example Basic ID filtering
 * ```php
 * // Filter by specific ID
 * $filter = Filter::byId()->equal('123e4567-e89b-12d3-a456-426614174000');
 *
 * // Exclude specific ID
 * $filter = Filter::byId()->notEqual('123e4567-e89b-12d3-a456-426614174000');
 *
 * // Filter by multiple IDs
 * $filter = Filter::byId()->containsAny([
 *     '123e4567-e89b-12d3-a456-426614174000',
 *     '987fcdeb-51a2-43d1-9f12-345678901234'
 * ]);
 * ```
 */
class IdFilter extends Filter
{
    /**
     * Filter for exact ID match
     *
     * @param string $id The object ID to match exactly
     * @return $this
     */
    public function equal(string $id): self
    {
        $this->conditions = [
            'path' => ['id'],
            'operator' => 'Equal',
            'valueText' => $id
        ];
        return $this;
    }

    /**
     * Filter to exclude a specific ID
     *
     * @param string $id The object ID to exclude
     * @return $this
     */
    public function notEqual(string $id): self
    {
        $this->conditions = [
            'path' => ['id'],
            'operator' => 'NotEqual',
            'valueText' => $id
        ];
        return $this;
    }

    /**
     * Filter for objects with IDs matching any of the provided values
     *
     * This is useful for retrieving multiple specific objects by their IDs
     * in a single query.
     *
     * @param array<string> $ids Array of object IDs to match
     * @return $this
     */
    public function containsAny(array $ids): self
    {
        $this->conditions = [
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueText' => $ids
        ];
        return $this;
    }
}
