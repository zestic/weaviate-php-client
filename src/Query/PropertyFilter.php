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

use DateTime;

/**
 * Property-based filter for Weaviate GraphQL queries
 *
 * This class provides filtering capabilities on object properties using various
 * operators. It automatically handles type detection and uses the appropriate
 * GraphQL value field (valueText, valueInt, valueNumber, etc.).
 *
 * @example Basic property filtering
 * ```php
 * // String equality
 * $filter = Filter::byProperty('name')->equal('John Doe');
 *
 * // Numeric comparisons
 * $filter = Filter::byProperty('age')->greaterThan(18);
 * $filter = Filter::byProperty('price')->lessThan(100.0);
 *
 * // Pattern matching
 * $filter = Filter::byProperty('email')->like('*@example.com');
 *
 * // Null checks
 * $filter = Filter::byProperty('deletedAt')->isNull(true);
 *
 * // Array containment
 * $filter = Filter::byProperty('tags')->containsAny(['php', 'javascript']);
 * ```
 */
class PropertyFilter extends Filter
{
    public function __construct(
        private readonly string $property
    ) {
    }

    /**
     * Filter for exact equality
     *
     * @param mixed $value The value to match exactly
     * @return $this
     */
    public function equal(mixed $value): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'Equal',
            $this->getValueKey($value) => $value
        ];
        return $this;
    }

    /**
     * Filter for inequality
     *
     * @param mixed $value The value to exclude
     * @return $this
     */
    public function notEqual(mixed $value): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'NotEqual',
            $this->getValueKey($value) => $value
        ];
        return $this;
    }

    /**
     * Filter using pattern matching
     *
     * Supports wildcards (*) for pattern matching.
     * Use * at the beginning, end, or both sides of the pattern.
     *
     * @param string $pattern The pattern to match (supports * wildcards)
     * @return $this
     */
    public function like(string $pattern): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'Like',
            'valueText' => $pattern
        ];
        return $this;
    }

    /**
     * Filter for null values
     *
     * @param bool $isNull True to find null values, false to find non-null values
     * @return $this
     */
    public function isNull(bool $isNull = true): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'IsNull',
            'valueBoolean' => $isNull
        ];
        return $this;
    }

    /**
     * Filter for values greater than the specified value
     *
     * @param mixed $value The minimum value (exclusive)
     * @return $this
     */
    public function greaterThan(mixed $value): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'GreaterThan',
            $this->getValueKey($value) => $value
        ];
        return $this;
    }

    /**
     * Filter for values less than the specified value
     *
     * @param mixed $value The maximum value (exclusive)
     * @return $this
     */
    public function lessThan(mixed $value): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'LessThan',
            $this->getValueKey($value) => $value
        ];
        return $this;
    }

    /**
     * Filter for arrays that contain any of the specified values
     *
     * @param array<mixed> $values Array of values to check for containment
     * @return $this
     */
    public function containsAny(array $values): self
    {
        $this->conditions = [
            'path' => [$this->property],
            'operator' => 'ContainsAny',
            'valueText' => $values
        ];
        return $this;
    }

    /**
     * Determine the appropriate GraphQL value key based on the value type
     *
     * This method maps PHP types to the corresponding GraphQL value fields
     * used by Weaviate's API.
     *
     * @param mixed $value The value to determine the key for
     * @return string The appropriate GraphQL value key
     */
    private function getValueKey(mixed $value): string
    {
        return match (true) {
            is_string($value) => 'valueText',
            is_int($value) => 'valueInt',
            is_float($value) => 'valueNumber',
            is_bool($value) => 'valueBoolean',
            $value instanceof DateTime => 'valueDate',
            default => 'valueText' // Default fallback
        };
    }
}
