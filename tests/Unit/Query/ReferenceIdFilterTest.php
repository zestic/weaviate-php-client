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
use Weaviate\Query\ReferenceIdFilter;

/**
 * @covers \Weaviate\Query\ReferenceIdFilter
 */
class ReferenceIdFilterTest extends TestCase
{
    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::__construct
     */
    public function testCanBeConstructed(): void
    {
        $filter = new ReferenceIdFilter('hasCategory');

        $this->assertInstanceOf(ReferenceIdFilter::class, $filter);
    }

    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::equal
     * @covers \Weaviate\Query\ReferenceIdFilter::toArray
     */
    public function testEqualFilter(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $filter = new ReferenceIdFilter('hasCategory');
        $result = $filter->equal($uuid);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['id'],
                'operator' => 'Equal',
                'valueText' => $uuid
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::notEqual
     * @covers \Weaviate\Query\ReferenceIdFilter::toArray
     */
    public function testNotEqualFilter(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $filter = new ReferenceIdFilter('hasAuthor');
        $result = $filter->notEqual($uuid);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasAuthor'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['id'],
                'operator' => 'NotEqual',
                'valueText' => $uuid
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::containsAny
     * @covers \Weaviate\Query\ReferenceIdFilter::toArray
     */
    public function testContainsAnyFilter(): void
    {
        $uuids = [
            '123e4567-e89b-12d3-a456-426614174000',
            '987fcdeb-51a2-43d1-9f12-345678901234'
        ];
        $filter = new ReferenceIdFilter('hasCategories');
        $result = $filter->containsAny($uuids);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategories'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['id'],
                'operator' => 'ContainsAny',
                'valueText' => $uuids
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::containsAny
     */
    public function testContainsAnyWithEmptyArray(): void
    {
        $filter = new ReferenceIdFilter('hasCategories');
        $result = $filter->containsAny([]);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategories'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['id'],
                'operator' => 'ContainsAny',
                'valueText' => []
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceIdFilter::equal
     */
    public function testFluentInterface(): void
    {
        $filter = new ReferenceIdFilter('hasCategory');
        $uuid1 = '123e4567-e89b-12d3-a456-426614174000';
        $uuid2 = '987fcdeb-51a2-43d1-9f12-345678901234';

        // Test that methods can be chained
        $result = $filter->equal($uuid1)->notEqual($uuid2);

        $this->assertSame($filter, $result);
    }
}
