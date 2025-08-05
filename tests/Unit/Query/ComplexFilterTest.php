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
use Weaviate\Query\Filter;

/**
 * @covers \Weaviate\Query\Filter
 * @covers \Weaviate\Query\PropertyFilter
 */
class ComplexFilterTest extends TestCase
{
    /**
     * Test deeply nested filters with multiple levels of AND/OR combinations
     *
     * @covers \Weaviate\Query\Filter::allOf
     * @covers \Weaviate\Query\Filter::anyOf
     */
    public function testDeeplyNestedFilters(): void
    {
        // Create a complex nested filter structure:
        // (category = 'tech' AND (status = 'published' OR featured = true)) AND viewCount > 500
        $complexFilter = Filter::allOf([
            Filter::byProperty('category')->equal('tech'),
            Filter::anyOf([
                Filter::byProperty('status')->equal('published'),
                Filter::byProperty('featured')->equal(true)
            ]),
            Filter::byProperty('viewCount')->greaterThan(500)
        ]);

        $result = $complexFilter->toArray();

        $this->assertEquals('And', $result['operator']);
        $this->assertCount(3, $result['operands']);

        // First operand: category = 'tech'
        $this->assertEquals([
            'path' => ['category'],
            'operator' => 'Equal',
            'valueText' => 'tech'
        ], $result['operands'][0]);

        // Second operand: nested OR condition
        $nestedOr = $result['operands'][1];
        $this->assertEquals('Or', $nestedOr['operator']);
        $this->assertCount(2, $nestedOr['operands']);

        // Third operand: viewCount > 500
        $this->assertEquals([
            'path' => ['viewCount'],
            'operator' => 'GreaterThan',
            'valueInt' => 500
        ], $result['operands'][2]);
    }

    /**
     * Test mixed AND/OR filters with various data types
     *
     * @covers \Weaviate\Query\Filter::allOf
     * @covers \Weaviate\Query\Filter::anyOf
     */
    public function testMixedAndOrFilters(): void
    {
        // Complex filter: (name LIKE '*John*' OR email LIKE '*@example.com') AND (age > 18 AND active = true)
        $mixedFilter = Filter::allOf([
            Filter::anyOf([
                Filter::byProperty('name')->like('*John*'),
                Filter::byProperty('email')->like('*@example.com')
            ]),
            Filter::allOf([
                Filter::byProperty('age')->greaterThan(18),
                Filter::byProperty('active')->equal(true)
            ])
        ]);

        $result = $mixedFilter->toArray();

        $this->assertEquals('And', $result['operator']);
        $this->assertCount(2, $result['operands']);

        // First operand should be OR condition
        $firstOperand = $result['operands'][0];
        $this->assertEquals('Or', $firstOperand['operator']);
        $this->assertCount(2, $firstOperand['operands']);

        // Second operand should be AND condition
        $secondOperand = $result['operands'][1];
        $this->assertEquals('And', $secondOperand['operator']);
        $this->assertCount(2, $secondOperand['operands']);
    }

    /**
     * Test special character handling in filter values
     *
     * @covers \Weaviate\Query\PropertyFilter::equal
     * @covers \Weaviate\Query\PropertyFilter::like
     */
    public function testSpecialCharacterHandling(): void
    {
        // Test various special characters that might cause issues in GraphQL
        $specialCases = [
            'quotes' => 'John "The Great" Doe',
            'apostrophes' => "John's Article",
            'backslashes' => 'Path\\to\\file',
            'newlines' => "Line 1\nLine 2",
            'unicode' => 'Café München 🚀',
            'mixed' => 'Special: "quotes", \'apostrophes\', & symbols!'
        ];

        foreach ($specialCases as $case => $value) {
            $filter = Filter::byProperty('title')->equal($value);
            $result = $filter->toArray();

            $this->assertEquals([
                'path' => ['title'],
                'operator' => 'Equal',
                'valueText' => $value
            ], $result, "Failed for case: {$case}");
        }
    }

    /**
     * Test DateTime filters with various date operations
     *
     * @covers \Weaviate\Query\PropertyFilter::equal
     * @covers \Weaviate\Query\PropertyFilter::greaterThan
     * @covers \Weaviate\Query\PropertyFilter::lessThan
     */
    public function testDateTimeFilters(): void
    {
        $testDate = new DateTime('2024-01-15T10:30:00Z');
        $startDate = new DateTime('2024-01-01T00:00:00Z');
        $endDate = new DateTime('2024-12-31T23:59:59Z');

        // Test exact date match
        $exactFilter = Filter::byProperty('createdAt')->equal($testDate);
        $result = $exactFilter->toArray();

        $this->assertEquals([
            'path' => ['createdAt'],
            'operator' => 'Equal',
            'valueDate' => $testDate
        ], $result);

        // Test date range filter
        $rangeFilter = Filter::allOf([
            Filter::byProperty('createdAt')->greaterThan($startDate),
            Filter::byProperty('createdAt')->lessThan($endDate)
        ]);

        $rangeResult = $rangeFilter->toArray();
        $this->assertEquals('And', $rangeResult['operator']);
        $this->assertCount(2, $rangeResult['operands']);

        // Verify date range operands
        $this->assertEquals([
            'path' => ['createdAt'],
            'operator' => 'GreaterThan',
            'valueDate' => $startDate
        ], $rangeResult['operands'][0]);

        $this->assertEquals([
            'path' => ['createdAt'],
            'operator' => 'LessThan',
            'valueDate' => $endDate
        ], $rangeResult['operands'][1]);
    }

    /**
     * Test numeric range filters with integers and floats
     *
     * @covers \Weaviate\Query\PropertyFilter::greaterThan
     * @covers \Weaviate\Query\PropertyFilter::lessThan
     */
    public function testNumericRangeFilters(): void
    {
        // Test integer range
        $integerRange = Filter::allOf([
            Filter::byProperty('age')->greaterThan(18),
            Filter::byProperty('age')->lessThan(65)
        ]);

        $intResult = $integerRange->toArray();
        $this->assertEquals('And', $intResult['operator']);

        $this->assertEquals([
            'path' => ['age'],
            'operator' => 'GreaterThan',
            'valueInt' => 18
        ], $intResult['operands'][0]);

        $this->assertEquals([
            'path' => ['age'],
            'operator' => 'LessThan',
            'valueInt' => 65
        ], $intResult['operands'][1]);

        // Test float range
        $floatRange = Filter::allOf([
            Filter::byProperty('price')->greaterThan(9.99),
            Filter::byProperty('price')->lessThan(999.99)
        ]);

        $floatResult = $floatRange->toArray();
        $this->assertEquals('And', $floatResult['operator']);

        $this->assertEquals([
            'path' => ['price'],
            'operator' => 'GreaterThan',
            'valueNumber' => 9.99
        ], $floatResult['operands'][0]);

        $this->assertEquals([
            'path' => ['price'],
            'operator' => 'LessThan',
            'valueNumber' => 999.99
        ], $floatResult['operands'][1]);
    }

    /**
     * Test array containment with complex values
     *
     * @covers \Weaviate\Query\PropertyFilter::containsAny
     */
    public function testComplexArrayContainment(): void
    {
        // Test with mixed array values
        $mixedArray = ['php', 'javascript', 'python', 'go'];
        $filter = Filter::byProperty('technologies')->containsAny($mixedArray);

        $result = $filter->toArray();
        $this->assertEquals([
            'path' => ['technologies'],
            'operator' => 'ContainsAny',
            'valueText' => $mixedArray
        ], $result);

        // Test with special characters in array values
        $specialArray = ['C++', 'C#', 'F#', 'Objective-C'];
        $specialFilter = Filter::byProperty('languages')->containsAny($specialArray);

        $specialResult = $specialFilter->toArray();
        $this->assertEquals([
            'path' => ['languages'],
            'operator' => 'ContainsAny',
            'valueText' => $specialArray
        ], $specialResult);
    }

    /**
     * Test null handling in complex filters
     *
     * @covers \Weaviate\Query\PropertyFilter::isNull
     */
    public function testNullHandlingInComplexFilters(): void
    {
        // Test active records filter (deletedAt IS NULL AND status = 'active')
        $activeRecordsFilter = Filter::allOf([
            Filter::byProperty('deletedAt')->isNull(true),
            Filter::byProperty('status')->equal('active')
        ]);

        $result = $activeRecordsFilter->toArray();
        $this->assertEquals('And', $result['operator']);
        $this->assertCount(2, $result['operands']);

        $this->assertEquals([
            'path' => ['deletedAt'],
            'operator' => 'IsNull',
            'valueBoolean' => true
        ], $result['operands'][0]);

        $this->assertEquals([
            'path' => ['status'],
            'operator' => 'Equal',
            'valueText' => 'active'
        ], $result['operands'][1]);

        // Test soft-deleted records filter (deletedAt IS NOT NULL)
        $deletedRecordsFilter = Filter::byProperty('deletedAt')->isNull(false);
        $deletedResult = $deletedRecordsFilter->toArray();

        $this->assertEquals([
            'path' => ['deletedAt'],
            'operator' => 'IsNull',
            'valueBoolean' => false
        ], $deletedResult);
    }

    /**
     * Test extremely deep nesting (5+ levels)
     *
     * @covers \Weaviate\Query\Filter::allOf
     * @covers \Weaviate\Query\Filter::anyOf
     */
    public function testExtremelyDeepNesting(): void
    {
        // Create a 5-level deep nested filter
        $deepFilter = Filter::allOf([
            Filter::byProperty('level1')->equal('value1'),
            Filter::anyOf([
                Filter::byProperty('level2a')->equal('value2a'),
                Filter::allOf([
                    Filter::byProperty('level3a')->equal('value3a'),
                    Filter::anyOf([
                        Filter::byProperty('level4a')->equal('value4a'),
                        Filter::allOf([
                            Filter::byProperty('level5a')->equal('value5a'),
                            Filter::byProperty('level5b')->equal('value5b')
                        ])
                    ])
                ])
            ])
        ]);

        $result = $deepFilter->toArray();

        // Verify the structure exists and is properly nested
        $this->assertEquals('And', $result['operator']);
        $this->assertCount(2, $result['operands']);

        // Verify first level
        $this->assertEquals([
            'path' => ['level1'],
            'operator' => 'Equal',
            'valueText' => 'value1'
        ], $result['operands'][0]);

        // Verify second level is OR
        $level2 = $result['operands'][1];
        $this->assertEquals('Or', $level2['operator']);
        $this->assertCount(2, $level2['operands']);

        // Verify the deep nesting continues
        $this->assertArrayHasKey('operator', $level2['operands'][1]);
        $this->assertEquals('And', $level2['operands'][1]['operator']);
    }
}
