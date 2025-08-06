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
 * Cross-reference filter for Weaviate GraphQL queries
 *
 * This class provides filtering capabilities on cross-referenced properties.
 * It allows filtering objects based on properties of their referenced objects,
 * matching the Python client v4 API patterns.
 *
 * @example Basic cross-reference filtering
 * ```php
 * // Filter by cross-referenced property
 * $filter = Filter::byRef('hasCategory')->byProperty('title')->like('*Sport*');
 *
 * // Filter by cross-referenced ID
 * $filter = Filter::byRef('hasAuthor')->byId()->equal('123e4567-e89b-12d3-a456-426614174000');
 *
 * // Complex cross-reference filtering
 * $filter = Filter::byRef('hasCategory')->byProperty('status')->equal('active');
 * ```
 */
class ReferenceFilter
{
    private string $linkOn;
    private ?PropertyFilter $propertyFilter = null;
    private ?IdFilter $idFilter = null;

    public function __construct(string $linkOn)
    {
        $this->linkOn = $linkOn;
    }

    /**
     * Filter by a property of the referenced object
     *
     * @param string $property The property name in the referenced object
     * @return ReferencePropertyFilter A property filter for the referenced object
     */
    public function byProperty(string $property): ReferencePropertyFilter
    {
        return new ReferencePropertyFilter($this->linkOn, $property);
    }

    /**
     * Filter by the ID of the referenced object
     *
     * @return ReferenceIdFilter An ID filter for the referenced object
     */
    public function byId(): ReferenceIdFilter
    {
        return new ReferenceIdFilter($this->linkOn);
    }

    /**
     * Convert the filter to array format for GraphQL
     *
     * @return array<string, mixed> The filter in GraphQL format
     */
    public function toArray(): array
    {
        if ($this->propertyFilter !== null) {
            $propertyConditions = $this->propertyFilter->toArray();
            return [
                'path' => [$this->linkOn],
                'operator' => 'Equal',
                'valueObject' => $propertyConditions
            ];
        }

        if ($this->idFilter !== null) {
            $idConditions = $this->idFilter->toArray();
            return [
                'path' => [$this->linkOn],
                'operator' => 'Equal',
                'valueObject' => $idConditions
            ];
        }

        // Default case - should not happen in normal usage
        return [
            'path' => [$this->linkOn],
            'operator' => 'Equal',
            'valueObject' => []
        ];
    }
}

/**
 * Property filter for cross-referenced objects
 */
class ReferencePropertyFilter
{
    private string $linkOn;
    private PropertyFilter $propertyFilter;

    public function __construct(string $linkOn, string $property)
    {
        $this->linkOn = $linkOn;
        $this->propertyFilter = new PropertyFilter($property);
    }

    public function equal(mixed $value): self
    {
        $this->propertyFilter->equal($value);
        return $this;
    }

    public function notEqual(mixed $value): self
    {
        $this->propertyFilter->notEqual($value);
        return $this;
    }

    public function like(string $value): self
    {
        $this->propertyFilter->like($value);
        return $this;
    }

    public function greaterThan(int|float $value): self
    {
        $this->propertyFilter->greaterThan($value);
        return $this;
    }

    public function greaterThanEqual(int|float $value): self
    {
        $this->propertyFilter->greaterThanEqual($value);
        return $this;
    }

    public function lessThan(int|float $value): self
    {
        $this->propertyFilter->lessThan($value);
        return $this;
    }

    public function lessThanEqual(int|float $value): self
    {
        $this->propertyFilter->lessThanEqual($value);
        return $this;
    }

    public function isNull(bool $value): self
    {
        $this->propertyFilter->isNull($value);
        return $this;
    }

    public function containsAny(array $values): self
    {
        $this->propertyFilter->containsAny($values);
        return $this;
    }

    public function containsAll(array $values): self
    {
        $this->propertyFilter->containsAll($values);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'path' => [$this->linkOn],
            'operator' => 'Equal',
            'valueObject' => $this->propertyFilter->toArray()
        ];
    }
}

/**
 * ID filter for cross-referenced objects
 */
class ReferenceIdFilter
{
    private string $linkOn;
    private IdFilter $idFilter;

    public function __construct(string $linkOn)
    {
        $this->linkOn = $linkOn;
        $this->idFilter = new IdFilter();
    }

    public function equal(string $value): self
    {
        $this->idFilter->equal($value);
        return $this;
    }

    public function notEqual(string $value): self
    {
        $this->idFilter->notEqual($value);
        return $this;
    }

    public function containsAny(array $values): self
    {
        $this->idFilter->containsAny($values);
        return $this;
    }

    public function containsAll(array $values): self
    {
        $this->idFilter->containsAll($values);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'path' => [$this->linkOn],
            'operator' => 'Equal',
            'valueObject' => $this->idFilter->toArray()
        ];
    }
}
