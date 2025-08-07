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
use Weaviate\Query\ReferencePropertyFilter;

/**
 * @covers \Weaviate\Query\ReferencePropertyFilter
 */
class ReferencePropertyFilterTest extends TestCase
{
    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::__construct
     */
    public function testCanBeConstructed(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'title');

        $this->assertInstanceOf(ReferencePropertyFilter::class, $filter);
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::equal
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testEqualFilter(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'title');
        $result = $filter->equal('Technology');

        $this->assertSame($filter, $result);

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
     * @covers \Weaviate\Query\ReferencePropertyFilter::notEqual
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testNotEqualFilter(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'status');
        $result = $filter->notEqual('inactive');

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['status'],
                'operator' => 'NotEqual',
                'valueText' => 'inactive'
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::like
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testLikeFilter(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'title');
        $result = $filter->like('*Sport*');

        $this->assertSame($filter, $result);

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
     * @covers \Weaviate\Query\ReferencePropertyFilter::greaterThan
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testGreaterThanFilterWithInteger(): void
    {
        $filter = new ReferencePropertyFilter('hasAuthor', 'age');
        $result = $filter->greaterThan(25);

        $this->assertSame($filter, $result);

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
     * @covers \Weaviate\Query\ReferencePropertyFilter::greaterThan
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testGreaterThanFilterWithFloat(): void
    {
        $filter = new ReferencePropertyFilter('hasProduct', 'price');
        $result = $filter->greaterThan(99.99);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasProduct'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['price'],
                'operator' => 'GreaterThan',
                'valueNumber' => 99.99
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::lessThan
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testLessThanFilterWithInteger(): void
    {
        $filter = new ReferencePropertyFilter('hasAuthor', 'age');
        $result = $filter->lessThan(65);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasAuthor'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['age'],
                'operator' => 'LessThan',
                'valueInt' => 65
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::lessThan
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testLessThanFilterWithFloat(): void
    {
        $filter = new ReferencePropertyFilter('hasProduct', 'price');
        $result = $filter->lessThan(50.0);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasProduct'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['price'],
                'operator' => 'LessThan',
                'valueNumber' => 50.0
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::isNull
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testIsNullFilterTrue(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'deletedAt');
        $result = $filter->isNull(true);

        $this->assertSame($filter, $result);

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
     * @covers \Weaviate\Query\ReferencePropertyFilter::isNull
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testIsNullFilterFalse(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'deletedAt');
        $result = $filter->isNull(false);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['deletedAt'],
                'operator' => 'IsNull',
                'valueBoolean' => false
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::containsAny
     * @covers \Weaviate\Query\ReferencePropertyFilter::toArray
     */
    public function testContainsAnyFilter(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'tags');
        $result = $filter->containsAny(['php', 'javascript', 'python']);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['tags'],
                'operator' => 'ContainsAny',
                'valueText' => ['php', 'javascript', 'python']
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::containsAny
     */
    public function testContainsAnyWithEmptyArray(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'tags');
        $result = $filter->containsAny([]);

        $this->assertSame($filter, $result);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['tags'],
                'operator' => 'ContainsAny',
                'valueText' => []
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::equal
     */
    public function testFluentInterface(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'title');

        // Test that methods can be chained
        $result = $filter->equal('Technology')->notEqual('Sports');

        $this->assertSame($filter, $result);
    }

    /**
     * @covers \Weaviate\Query\ReferencePropertyFilter::equal
     */
    public function testEqualWithDifferentTypes(): void
    {
        $filter = new ReferencePropertyFilter('hasCategory', 'count');

        // Test with integer
        $filter->equal(42);
        $result = $filter->toArray();
        $this->assertEquals(42, $result['valueObject']['valueInt']);

        // Test with boolean
        $filter->equal(true);
        $result = $filter->toArray();
        $this->assertTrue($result['valueObject']['valueBoolean']);

        // Test with null
        $filter->equal(null);
        $result = $filter->toArray();
        $this->assertNull($result['valueObject']['valueText']);
    }
}
