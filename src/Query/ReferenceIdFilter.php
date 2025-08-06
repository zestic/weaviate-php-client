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
 * ID filter for cross-referenced objects
 *
 * This class provides filtering capabilities on IDs of cross-referenced objects.
 * It wraps an IdFilter and formats the output for cross-reference GraphQL queries.
 */
class ReferenceIdFilter extends Filter
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

    /**
     * @param array<string> $values
     */
    public function containsAny(array $values): self
    {
        $this->idFilter->containsAny($values);
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
