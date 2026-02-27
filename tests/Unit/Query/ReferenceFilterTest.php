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

class ReferenceFilterTest extends TestCase
{
    public function testCanCreateReferenceFilter(): void
    {
        $filter = Filter::byRef('hasCategory');

        $this->assertInstanceOf(ReferenceFilter::class, $filter);
    }

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

    public function testConstructorSetsLinkOn(): void
    {
        $filter = new ReferenceFilter('hasAuthor');

        // Test that the linkOn is properly set by checking toArray output
        $result = $filter->toArray();
        $this->assertEquals(['hasAuthor'], $result['path']);
    }

    public function testToArrayWithPropertyFilter(): void
    {
        $filter = new ReferenceFilter('hasCategory');
        $propertyFilter = $filter->byProperty('title');
        $propertyFilter->equal('Technology');

        // The ReferenceFilter itself should return the default case
        // since it doesn't store the property filter internally
        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => []
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    public function testToArrayWithIdFilter(): void
    {
        $filter = new ReferenceFilter('hasCategory');
        $idFilter = $filter->byId();
        $idFilter->equal('123e4567-e89b-12d3-a456-426614174000');

        // The ReferenceFilter itself should return the default case
        // since it doesn't store the id filter internally
        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => []
        ];

        $this->assertEquals($expected, $filter->toArray());
    }

    public function testByPropertyReturnsReferencePropertyFilter(): void
    {
        $filter = new ReferenceFilter('hasCategory');
        $propertyFilter = $filter->byProperty('title');

        $this->assertInstanceOf(\Weaviate\Query\ReferencePropertyFilter::class, $propertyFilter);
    }

    public function testByIdReturnsReferenceIdFilter(): void
    {
        $filter = new ReferenceFilter('hasCategory');
        $idFilter = $filter->byId();

        $this->assertInstanceOf(\Weaviate\Query\ReferenceIdFilter::class, $idFilter);
    }

    public function testToArrayWithPropertyFilterSet(): void
    {
        $filter = new ReferenceFilter('hasCategory');

        // Use reflection to set the private propertyFilter property
        $reflection = new \ReflectionClass($filter);
        $propertyFilterProperty = $reflection->getProperty('propertyFilter');
        $propertyFilterProperty->setAccessible(true);

        $mockPropertyFilter = $this->createMock(\Weaviate\Query\PropertyFilter::class);
        $mockPropertyFilter->method('toArray')->willReturn([
            'path' => ['title'],
            'operator' => 'Equal',
            'valueText' => 'Technology'
        ]);

        $propertyFilterProperty->setValue($filter, $mockPropertyFilter);

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

    public function testToArrayWithIdFilterSet(): void
    {
        $filter = new ReferenceFilter('hasCategory');

        // Use reflection to set the private idFilter property
        $reflection = new \ReflectionClass($filter);
        $idFilterProperty = $reflection->getProperty('idFilter');
        $idFilterProperty->setAccessible(true);

        $mockIdFilter = $this->createMock(\Weaviate\Query\IdFilter::class);
        $mockIdFilter->method('toArray')->willReturn([
            'path' => ['id'],
            'operator' => 'Equal',
            'valueText' => '123e4567-e89b-12d3-a456-426614174000'
        ]);

        $idFilterProperty->setValue($filter, $mockIdFilter);

        $expected = [
            'path' => ['hasCategory'],
            'operator' => 'Equal',
            'valueObject' => [
                'path' => ['id'],
                'operator' => 'Equal',
                'valueText' => '123e4567-e89b-12d3-a456-426614174000'
            ]
        ];

        $this->assertEquals($expected, $filter->toArray());
    }
}
