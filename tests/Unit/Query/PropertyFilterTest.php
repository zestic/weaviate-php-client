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

use DateTime;
use PHPUnit\Framework\TestCase;
use Weaviate\Query\PropertyFilter;

/**
 * @covers \Weaviate\Query\PropertyFilter
 */
class PropertyFilterTest extends TestCase
{
    /**
     * @covers \Weaviate\Query\PropertyFilter::__construct
     * @covers \Weaviate\Query\PropertyFilter::equal
     */
    public function testEqualWithString(): void
    {
        $filter = new PropertyFilter('name');
        $result = $filter->equal('John Doe');

        $expected = [
            'path' => ['name'],
            'operator' => 'Equal',
            'valueText' => 'John Doe'
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::equal
     */
    public function testEqualWithInteger(): void
    {
        $filter = new PropertyFilter('age');
        $result = $filter->equal(25);

        $expected = [
            'path' => ['age'],
            'operator' => 'Equal',
            'valueInt' => 25
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::equal
     */
    public function testEqualWithFloat(): void
    {
        $filter = new PropertyFilter('price');
        $result = $filter->equal(19.99);

        $expected = [
            'path' => ['price'],
            'operator' => 'Equal',
            'valueNumber' => 19.99
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::equal
     */
    public function testEqualWithBoolean(): void
    {
        $filter = new PropertyFilter('isActive');
        $result = $filter->equal(true);

        $expected = [
            'path' => ['isActive'],
            'operator' => 'Equal',
            'valueBoolean' => true
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::equal
     */
    public function testEqualWithDateTime(): void
    {
        $filter = new PropertyFilter('createdAt');
        $date = new DateTime('2023-01-01T00:00:00Z');
        $result = $filter->equal($date);

        $expected = [
            'path' => ['createdAt'],
            'operator' => 'Equal',
            'valueDate' => $date
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::notEqual
     */
    public function testNotEqual(): void
    {
        $filter = new PropertyFilter('status');
        $result = $filter->notEqual('inactive');

        $expected = [
            'path' => ['status'],
            'operator' => 'NotEqual',
            'valueText' => 'inactive'
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::like
     */
    public function testLike(): void
    {
        $filter = new PropertyFilter('name');
        $result = $filter->like('*John*');

        $expected = [
            'path' => ['name'],
            'operator' => 'Like',
            'valueText' => '*John*'
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::isNull
     */
    public function testIsNullTrue(): void
    {
        $filter = new PropertyFilter('deletedAt');
        $result = $filter->isNull(true);

        $expected = [
            'path' => ['deletedAt'],
            'operator' => 'IsNull',
            'valueBoolean' => true
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::isNull
     */
    public function testIsNullFalse(): void
    {
        $filter = new PropertyFilter('deletedAt');
        $result = $filter->isNull(false);

        $expected = [
            'path' => ['deletedAt'],
            'operator' => 'IsNull',
            'valueBoolean' => false
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::isNull
     */
    public function testIsNullDefaultsToTrue(): void
    {
        $filter = new PropertyFilter('deletedAt');
        $result = $filter->isNull();

        $expected = [
            'path' => ['deletedAt'],
            'operator' => 'IsNull',
            'valueBoolean' => true
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::greaterThan
     */
    public function testGreaterThan(): void
    {
        $filter = new PropertyFilter('age');
        $result = $filter->greaterThan(18);

        $expected = [
            'path' => ['age'],
            'operator' => 'GreaterThan',
            'valueInt' => 18
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::lessThan
     */
    public function testLessThan(): void
    {
        $filter = new PropertyFilter('price');
        $result = $filter->lessThan(100.0);

        $expected = [
            'path' => ['price'],
            'operator' => 'LessThan',
            'valueNumber' => 100.0
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @covers \Weaviate\Query\PropertyFilter::containsAny
     */
    public function testContainsAny(): void
    {
        $filter = new PropertyFilter('tags');
        $result = $filter->containsAny(['php', 'javascript', 'python']);

        $expected = [
            'path' => ['tags'],
            'operator' => 'ContainsAny',
            'valueText' => ['php', 'javascript', 'python']
        ];

        $this->assertEquals($expected, $result->toArray());
    }
}
