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
 * Base filter class for building Weaviate GraphQL queries
 *
 * This class provides the foundation for creating filters that match the
 * Python client v4 API patterns. It supports property-based filtering,
 * ID filtering, and complex filter combinations.
 *
 * @example Basic property filtering
 * ```php
 * // Simple property filter
 * $filter = Filter::byProperty('name')->equal('John Doe');
 *
 * // Multiple conditions with AND
 * $filter = Filter::allOf([
 *     Filter::byProperty('status')->equal('active'),
 *     Filter::byProperty('age')->greaterThan(18)
 * ]);
 *
 * // Multiple conditions with OR
 * $filter = Filter::anyOf([
 *     Filter::byProperty('status')->equal('active'),
 *     Filter::byProperty('status')->equal('pending')
 * ]);
 * ```
 *
 * @example ID filtering
 * ```php
 * // Filter by specific ID
 * $filter = Filter::byId()->equal('123e4567-e89b-12d3-a456-426614174000');
 *
 * // Filter by multiple IDs
 * $filter = Filter::byId()->containsAny([
 *     '123e4567-e89b-12d3-a456-426614174000',
 *     '987fcdeb-51a2-43d1-9f12-345678901234'
 * ]);
 * ```
 */
class Filter
{
    /**
     * @var array<string, mixed>
     */
    protected array $conditions = [];

    /**
     * Create a property-based filter
     *
     * This method creates a PropertyFilter instance that allows filtering
     * on object properties using various operators like equal, greaterThan, etc.
     *
     * @param string $property The property name to filter on
     * @return PropertyFilter A property filter instance
     */
    public static function byProperty(string $property): PropertyFilter
    {
        return new PropertyFilter($property);
    }

    /**
     * Create an ID-based filter
     *
     * This method creates an IdFilter instance that allows filtering
     * on object IDs using operators like equal, notEqual, containsAny.
     *
     * @return IdFilter An ID filter instance
     */
    public static function byId(): IdFilter
    {
        return new IdFilter();
    }

    /**
     * Combine multiple filters with AND logic
     *
     * Creates a filter that matches objects where ALL of the provided
     * filters are true. This is equivalent to the & operator in the Python client.
     *
     * @param array<Filter> $filters Array of filters to combine with AND
     * @return static A combined filter with AND logic
     */
    public static function allOf(array $filters): static
    {
        $filter = new static();
        $filter->conditions = [
            'operator' => 'And',
            'operands' => array_map(fn(Filter $f) => $f->toArray(), $filters)
        ];
        return $filter;
    }

    /**
     * Combine multiple filters with OR logic
     *
     * Creates a filter that matches objects where ANY of the provided
     * filters are true. This is equivalent to the | operator in the Python client.
     *
     * @param array<Filter> $filters Array of filters to combine with OR
     * @return static A combined filter with OR logic
     */
    public static function anyOf(array $filters): static
    {
        $filter = new static();
        $filter->conditions = [
            'operator' => 'Or',
            'operands' => array_map(fn(Filter $f) => $f->toArray(), $filters)
        ];
        return $filter;
    }

    /**
     * Convert the filter to an array representation
     *
     * This method converts the filter conditions into the array format
     * expected by Weaviate's GraphQL API.
     *
     * @return array<string, mixed> The filter conditions as an array
     */
    public function toArray(): array
    {
        return $this->conditions;
    }
}
