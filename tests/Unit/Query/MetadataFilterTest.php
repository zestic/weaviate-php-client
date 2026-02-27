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

class MetadataFilterTest extends TestCase
{
    /**
     * Test ID-based filtering with single ID
     *
     */
    public function testIdFilterEqual(): void
    {
        $testId = '123e4567-e89b-12d3-a456-426614174000';
        $filter = Filter::byId()->equal($testId);

        $result = $filter->toArray();

        $this->assertEquals([
            'path' => ['id'],
            'operator' => 'Equal',
            'valueText' => $testId
        ], $result);
    }

    /**
     * Test ID-based filtering with exclusion
     *
     */
    public function testIdFilterNotEqual(): void
    {
        $excludeId = '987fcdeb-51a2-43d1-9f12-345678901234';
        $filter = Filter::byId()->notEqual($excludeId);

        $result = $filter->toArray();

        $this->assertEquals([
            'path' => ['id'],
            'operator' => 'NotEqual',
            'valueText' => $excludeId
        ], $result);
    }

    /**
     * Test ID-based filtering with multiple IDs
     *
     */
    public function testIdFilterContainsAny(): void
    {
        $testIds = [
            '123e4567-e89b-12d3-a456-426614174000',
            '987fcdeb-51a2-43d1-9f12-345678901234',
            'abcdef12-3456-7890-abcd-ef1234567890'
        ];

        $filter = Filter::byId()->containsAny($testIds);
        $result = $filter->toArray();

        $this->assertEquals([
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueText' => $testIds
        ], $result);
    }

    /**
     * Test timestamp-based filtering for creation dates
     *
     */
    public function testTimestampFiltering(): void
    {
        $startDate = new DateTime('2024-01-01T00:00:00Z');
        $endDate = new DateTime('2024-12-31T23:59:59Z');

        // Test created after specific date
        $createdAfterFilter = Filter::byProperty('_creationTimeUnix')->greaterThan($startDate);
        $result = $createdAfterFilter->toArray();

        $this->assertEquals([
            'path' => ['_creationTimeUnix'],
            'operator' => 'GreaterThan',
            'valueDate' => $startDate
        ], $result);

        // Test created before specific date
        $createdBeforeFilter = Filter::byProperty('_creationTimeUnix')->lessThan($endDate);
        $beforeResult = $createdBeforeFilter->toArray();

        $this->assertEquals([
            'path' => ['_creationTimeUnix'],
            'operator' => 'LessThan',
            'valueDate' => $endDate
        ], $beforeResult);
    }

    /**
     * Test filtering by last update timestamp
     *
     */
    public function testLastUpdateFiltering(): void
    {
        $lastWeek = new DateTime('-7 days');

        $recentlyUpdatedFilter = Filter::byProperty('_lastUpdateTimeUnix')->greaterThan($lastWeek);
        $result = $recentlyUpdatedFilter->toArray();

        $this->assertEquals([
            'path' => ['_lastUpdateTimeUnix'],
            'operator' => 'GreaterThan',
            'valueDate' => $lastWeek
        ], $result);
    }

    /**
     * Test filtering by vector certainty/distance
     *
     */
    public function testVectorCertaintyFiltering(): void
    {
        // Test minimum certainty threshold
        $minCertaintyFilter = Filter::byProperty('_certainty')->greaterThan(0.8);
        $result = $minCertaintyFilter->toArray();

        $this->assertEquals([
            'path' => ['_certainty'],
            'operator' => 'GreaterThan',
            'valueNumber' => 0.8
        ], $result);

        // Test maximum distance threshold
        $maxDistanceFilter = Filter::byProperty('_distance')->lessThan(0.2);
        $distanceResult = $maxDistanceFilter->toArray();

        $this->assertEquals([
            'path' => ['_distance'],
            'operator' => 'LessThan',
            'valueNumber' => 0.2
        ], $distanceResult);
    }

    /**
     * Test complex metadata combinations
     *
     */
    public function testComplexMetadataFiltering(): void
    {
        $testId = '123e4567-e89b-12d3-a456-426614174000';
        $minDate = new DateTime('2024-01-01T00:00:00Z');
        $minCertainty = 0.7;

        // Combine ID, timestamp, and certainty filters
        $complexFilter = Filter::allOf([
            Filter::byId()->notEqual($testId),
            Filter::byProperty('_creationTimeUnix')->greaterThan($minDate),
            Filter::byProperty('_certainty')->greaterThan($minCertainty)
        ]);

        $result = $complexFilter->toArray();

        $this->assertEquals('And', $result['operator']);
        $this->assertCount(3, $result['operands']);

        // Verify ID exclusion
        $this->assertEquals([
            'path' => ['id'],
            'operator' => 'NotEqual',
            'valueText' => $testId
        ], $result['operands'][0]);

        // Verify timestamp filter
        $this->assertEquals([
            'path' => ['_creationTimeUnix'],
            'operator' => 'GreaterThan',
            'valueDate' => $minDate
        ], $result['operands'][1]);

        // Verify certainty filter
        $this->assertEquals([
            'path' => ['_certainty'],
            'operator' => 'GreaterThan',
            'valueNumber' => $minCertainty
        ], $result['operands'][2]);
    }

    /**
     * Test filtering by classification metadata
     *
     */
    public function testClassificationMetadata(): void
    {
        // Test filtering by classification status
        $classifiedFilter = Filter::byProperty('_classification')->equal('completed');
        $result = $classifiedFilter->toArray();

        $this->assertEquals([
            'path' => ['_classification'],
            'operator' => 'Equal',
            'valueText' => 'completed'
        ], $result);

        // Test filtering by classification confidence
        $confidenceFilter = Filter::byProperty('_classifyPropertyValue')->greaterThan(0.9);
        $confidenceResult = $confidenceFilter->toArray();

        $this->assertEquals([
            'path' => ['_classifyPropertyValue'],
            'operator' => 'GreaterThan',
            'valueNumber' => 0.9
        ], $confidenceResult);
    }

    /**
     * Test tenant-aware metadata filtering
     *
     */
    public function testTenantMetadataFiltering(): void
    {
        $tenantId = 'tenant-123';

        // Note: In practice, tenant filtering is handled at the query level,
        // but we can test property-based tenant filtering for completeness
        $tenantFilter = Filter::byProperty('tenantId')->equal($tenantId);
        $result = $tenantFilter->toArray();

        $this->assertEquals([
            'path' => ['tenantId'],
            'operator' => 'Equal',
            'valueText' => $tenantId
        ], $result);
    }

    /**
     * Test filtering by object version/revision metadata
     *
     */
    public function testVersionMetadataFiltering(): void
    {
        // Test filtering by specific version
        $versionFilter = Filter::byProperty('_version')->equal(5);
        $result = $versionFilter->toArray();

        $this->assertEquals([
            'path' => ['_version'],
            'operator' => 'Equal',
            'valueInt' => 5
        ], $result);

        // Test filtering by minimum version
        $minVersionFilter = Filter::byProperty('_version')->greaterThan(3);
        $minResult = $minVersionFilter->toArray();

        $this->assertEquals([
            'path' => ['_version'],
            'operator' => 'GreaterThan',
            'valueInt' => 3
        ], $minResult);
    }

    /**
     * Test filtering by object size/length metadata
     *
     */
    public function testObjectSizeFiltering(): void
    {
        // Test filtering by maximum object size
        $maxSizeFilter = Filter::byProperty('_objectSize')->lessThan(1024);
        $result = $maxSizeFilter->toArray();

        $this->assertEquals([
            'path' => ['_objectSize'],
            'operator' => 'LessThan',
            'valueInt' => 1024
        ], $result);

        // Test filtering by minimum content length
        $minLengthFilter = Filter::byProperty('_contentLength')->greaterThan(100);
        $lengthResult = $minLengthFilter->toArray();

        $this->assertEquals([
            'path' => ['_contentLength'],
            'operator' => 'GreaterThan',
            'valueInt' => 100
        ], $lengthResult);
    }

    /**
     * Test comprehensive metadata query combining multiple metadata fields
     *
     */
    public function testComprehensiveMetadataQuery(): void
    {
        $excludeIds = [
            '123e4567-e89b-12d3-a456-426614174000',
            '987fcdeb-51a2-43d1-9f12-345678901234'
        ];
        $minDate = new DateTime('2024-01-01T00:00:00Z');
        $maxDate = new DateTime('2024-12-31T23:59:59Z');

        // Complex metadata query:
        // (ID not in excludeIds) AND
        // (created between dates) AND
        // (high certainty OR recent update) AND
        // (version > 1)
        $comprehensiveFilter = Filter::allOf([
            Filter::byId()->containsAny($excludeIds), // Note: This would be negated in practice
            Filter::allOf([
                Filter::byProperty('_creationTimeUnix')->greaterThan($minDate),
                Filter::byProperty('_creationTimeUnix')->lessThan($maxDate)
            ]),
            Filter::anyOf([
                Filter::byProperty('_certainty')->greaterThan(0.8),
                Filter::byProperty('_lastUpdateTimeUnix')->greaterThan(new DateTime('-24 hours'))
            ]),
            Filter::byProperty('_version')->greaterThan(1)
        ]);

        $result = $comprehensiveFilter->toArray();

        $this->assertEquals('And', $result['operator']);
        $this->assertCount(4, $result['operands']);

        // Verify the structure contains all expected metadata filters
        $this->assertArrayHasKey('operator', $result['operands'][1]); // Date range AND
        $this->assertArrayHasKey('operator', $result['operands'][2]); // Certainty/Update OR

        // Verify version filter
        $this->assertEquals([
            'path' => ['_version'],
            'operator' => 'GreaterThan',
            'valueInt' => 1
        ], $result['operands'][3]);
    }
}
