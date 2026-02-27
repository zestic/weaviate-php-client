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
use Weaviate\Query\PropertyFilter;
use Weaviate\Query\IdFilter;

class FilterTest extends TestCase
{
    public function testByPropertyReturnsPropertyFilter(): void
    {
        $filter = Filter::byProperty('name');

        $this->assertInstanceOf(PropertyFilter::class, $filter);
    }

    public function testByIdReturnsIdFilter(): void
    {
        $filter = Filter::byId();

        $this->assertInstanceOf(IdFilter::class, $filter);
    }

    public function testAllOfCombinesFiltersWithAndOperator(): void
    {
        $filter1 = Filter::byProperty('name')->equal('John');
        $filter2 = Filter::byProperty('age')->greaterThan(18);

        $combinedFilter = Filter::allOf([$filter1, $filter2]);

        $expected = [
            'operator' => 'And',
            'operands' => [
                [
                    'path' => ['name'],
                    'operator' => 'Equal',
                    'valueText' => 'John'
                ],
                [
                    'path' => ['age'],
                    'operator' => 'GreaterThan',
                    'valueInt' => 18
                ]
            ]
        ];

        $this->assertEquals($expected, $combinedFilter->toArray());
    }

    public function testAnyOfCombinesFiltersWithOrOperator(): void
    {
        $filter1 = Filter::byProperty('status')->equal('active');
        $filter2 = Filter::byProperty('status')->equal('pending');

        $combinedFilter = Filter::anyOf([$filter1, $filter2]);

        $expected = [
            'operator' => 'Or',
            'operands' => [
                [
                    'path' => ['status'],
                    'operator' => 'Equal',
                    'valueText' => 'active'
                ],
                [
                    'path' => ['status'],
                    'operator' => 'Equal',
                    'valueText' => 'pending'
                ]
            ]
        ];

        $this->assertEquals($expected, $combinedFilter->toArray());
    }

    public function testAllOfWithSingleFilter(): void
    {
        $filter = Filter::byProperty('name')->equal('John');
        $combinedFilter = Filter::allOf([$filter]);

        $expected = [
            'operator' => 'And',
            'operands' => [
                [
                    'path' => ['name'],
                    'operator' => 'Equal',
                    'valueText' => 'John'
                ]
            ]
        ];

        $this->assertEquals($expected, $combinedFilter->toArray());
    }

    public function testAnyOfWithSingleFilter(): void
    {
        $filter = Filter::byProperty('name')->equal('John');
        $combinedFilter = Filter::anyOf([$filter]);

        $expected = [
            'operator' => 'Or',
            'operands' => [
                [
                    'path' => ['name'],
                    'operator' => 'Equal',
                    'valueText' => 'John'
                ]
            ]
        ];

        $this->assertEquals($expected, $combinedFilter->toArray());
    }

    public function testNestedFilters(): void
    {
        $innerFilter = Filter::anyOf([
            Filter::byProperty('status')->equal('active'),
            Filter::byProperty('status')->equal('pending')
        ]);

        $outerFilter = Filter::allOf([
            Filter::byProperty('name')->equal('John'),
            $innerFilter
        ]);

        $expected = [
            'operator' => 'And',
            'operands' => [
                [
                    'path' => ['name'],
                    'operator' => 'Equal',
                    'valueText' => 'John'
                ],
                [
                    'operator' => 'Or',
                    'operands' => [
                        [
                            'path' => ['status'],
                            'operator' => 'Equal',
                            'valueText' => 'active'
                        ],
                        [
                            'path' => ['status'],
                            'operator' => 'Equal',
                            'valueText' => 'pending'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $outerFilter->toArray());
    }
}
