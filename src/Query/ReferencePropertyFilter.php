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
 * Property filter for cross-referenced objects
 *
 * This class provides filtering capabilities on properties of cross-referenced objects.
 * It wraps a PropertyFilter and formats the output for cross-reference GraphQL queries.
 */
class ReferencePropertyFilter extends Filter
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

    public function lessThan(int|float $value): self
    {
        $this->propertyFilter->lessThan($value);
        return $this;
    }

    public function isNull(bool $value): self
    {
        $this->propertyFilter->isNull($value);
        return $this;
    }

    /**
     * @param array<mixed> $values
     */
    public function containsAny(array $values): self
    {
        $this->propertyFilter->containsAny($values);
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
