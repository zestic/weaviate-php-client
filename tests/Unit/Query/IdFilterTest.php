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

namespace Weaviate\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use Weaviate\Query\IdFilter;

class IdFilterTest extends TestCase
{
    public function testEqual(): void
    {
        $filter = new IdFilter();
        $result = $filter->equal('123e4567-e89b-12d3-a456-426614174000');

        $expected = [
            'path' => ['id'],
            'operator' => 'Equal',
            'valueText' => '123e4567-e89b-12d3-a456-426614174000'
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testNotEqual(): void
    {
        $filter = new IdFilter();
        $result = $filter->notEqual('123e4567-e89b-12d3-a456-426614174000');

        $expected = [
            'path' => ['id'],
            'operator' => 'NotEqual',
            'valueText' => '123e4567-e89b-12d3-a456-426614174000'
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testContainsAny(): void
    {
        $filter = new IdFilter();
        $ids = [
            '123e4567-e89b-12d3-a456-426614174000',
            '987fcdeb-51a2-43d1-9f12-345678901234'
        ];
        $result = $filter->containsAny($ids);

        $expected = [
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueText' => $ids
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testContainsAnyWithSingleId(): void
    {
        $filter = new IdFilter();
        $ids = ['123e4567-e89b-12d3-a456-426614174000'];
        $result = $filter->containsAny($ids);

        $expected = [
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueText' => $ids
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testContainsAnyWithEmptyArray(): void
    {
        $filter = new IdFilter();
        $result = $filter->containsAny([]);

        $expected = [
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueText' => []
        ];

        $this->assertEquals($expected, $result->toArray());
    }
}
