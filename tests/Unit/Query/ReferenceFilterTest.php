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
use Weaviate\Query\Filter;
use Weaviate\Query\ReferenceFilter;

/**
 * @covers \Weaviate\Query\ReferenceFilter
 * @covers \Weaviate\Query\Filter::byRef
 */
class ReferenceFilterTest extends TestCase
{
    /**
     * @covers \Weaviate\Query\Filter::byRef
     */
    public function testCanCreateReferenceFilter(): void
    {
        $filter = Filter::byRef('hasCategory');

        $this->assertInstanceOf(ReferenceFilter::class, $filter);
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byProperty
     */
    public function testCanFilterByReferencedProperty(): void
    {
        $filter = Filter::byRef('hasCategory')->byProperty('title')->equal('Technology');

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['title'],
                'operator' => 'Equal',
                'valueText' => 'Technology'
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byProperty
     */
    public function testCanFilterByReferencedPropertyWithLike(): void
    {
        $filter = Filter::byRef('hasCategory')->byProperty('title')->like('*Sport*');

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['title'],
                'operator' => 'Like',
                'valueText' => '*Sport*'
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byProperty
     */
    public function testCanFilterByReferencedPropertyWithNumericComparison(): void
    {
        $filter = Filter::byRef('hasAuthor')->byProperty('age')->greaterThan(25);

        $expected = [
            'path' => ['hasAuthor'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['age'],
                'operator' => 'GreaterThan',
                'valueInt' => 25
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byId
     */
    public function testCanFilterByReferencedId(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $filter = Filter::byRef('hasCategory')->byId()->equal($uuid);

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
     * @covers \Weaviate\Query\ReferenceFilter::byId
     */
    public function testCanFilterByReferencedIdWithMultipleValues(): void
    {
        $uuids = [
            '123e4567-e89b-12d3-a456-426614174000',
            '987fcdeb-51a2-43d1-9f12-345678901234'
        ];
        $filter = Filter::byRef('hasCategory')->byId()->containsAny($uuids);

        $expected = [
            'path' => ['hasCategory'],
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
     * @covers \Weaviate\Query\ReferenceFilter::toArray
     */
    public function testToArrayWithoutFilterReturnsEmptyValueObject(): void
    {
        $filter = new ReferenceFilter('hasCategory');

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => []
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byProperty
     */
    public function testCanFilterByReferencedPropertyWithNullCheck(): void
    {
        $filter = Filter::byRef('hasCategory')->byProperty('deletedAt')->isNull(true);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['deletedAt'],
                'operator' => 'IsNull',
                'valueBoolean' => true
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferenceFilter::byProperty
     */
    public function testCanFilterByReferencedPropertyWithArrayContainment(): void
    {
        $filter = Filter::byRef('hasCategory')->byProperty('tags')->containsAny(['php', 'javascript']);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['tags'],
                'operator' => 'ContainsAny',
                'valueText' => ['php', 'javascript']
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }
}
