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

namespace Weaviate\Tests\Integration\Query;

use DateTime;
use Weaviate\Query\Filter;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Integration tests for complex nested filters against real Weaviate instance
 *
 * @group integration
 */
class NestedFilterIntegrationTest extends TestCase
{
    private WeaviateClient $client;
    private string $testClassName = 'NestedFilterTestClass';

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection (no auth for test instance)
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create client
        $this->client = new WeaviateClient($connection);

        $this->createTestCollection();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCollection();
    }

    /**
     * Create test collection with appropriate schema
     */
    private function createTestCollection(): void
    {
        $schema = [
            'class' => $this->testClassName,
            'invertedIndexConfig' => [
                'indexNullState' => true
            ],
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'category', 'dataType' => ['text']],
                ['name' => 'status', 'dataType' => ['text']],
                ['name' => 'featured', 'dataType' => ['boolean']],
                ['name' => 'viewCount', 'dataType' => ['int']],
                ['name' => 'rating', 'dataType' => ['number']],
                [
                    'name' => 'publishedAt',
                    'dataType' => ['date'],
                    'invertedIndexConfig' => [
                        'indexNullState' => true
                    ]
                ],
                ['name' => 'tags', 'dataType' => ['text[]']],
                ['name' => 'author', 'dataType' => ['text']],
                ['name' => 'active', 'dataType' => ['boolean']],
                ['name' => 'priority', 'dataType' => ['int']]
            ]
        ];

        $this->client->schema()->create($schema);
    }

    /**
     * Insert test data for complex filtering scenarios
     */
    private function insertTestData(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $testObjects = [
            [
                'title' => 'Advanced AI Techniques',
                'category' => 'technology',
                'status' => 'published',
                'featured' => true,
                'viewCount' => 1500,
                'rating' => 4.8,
                'publishedAt' => '2024-01-15T10:00:00Z',
                'tags' => ['ai', 'machine-learning', 'technology'],
                'author' => 'Dr. Smith',
                'active' => true,
                'priority' => 5
            ],
            [
                'title' => 'Web Development Basics',
                'category' => 'technology',
                'status' => 'published',
                'featured' => false,
                'viewCount' => 800,
                'rating' => 4.2,
                'publishedAt' => '2024-02-01T14:30:00Z',
                'tags' => ['web', 'html', 'css', 'javascript'],
                'author' => 'Jane Doe',
                'active' => true,
                'priority' => 3
            ],
            [
                'title' => 'Draft Article',
                'category' => 'technology',
                'status' => 'draft',
                'featured' => false,
                'viewCount' => 0,
                'rating' => 0.0,
                'publishedAt' => null,
                'tags' => ['draft'],
                'author' => 'John Writer',
                'active' => false,
                'priority' => 1
            ],
            [
                'title' => 'Cooking Masterclass',
                'category' => 'lifestyle',
                'status' => 'published',
                'featured' => true,
                'viewCount' => 2200,
                'rating' => 4.9,
                'publishedAt' => '2024-01-20T09:15:00Z',
                'tags' => ['cooking', 'food', 'lifestyle'],
                'author' => 'Chef Gordon',
                'active' => true,
                'priority' => 4
            ],
            [
                'title' => 'Travel Guide Europe',
                'category' => 'travel',
                'status' => 'published',
                'featured' => false,
                'viewCount' => 1200,
                'rating' => 4.5,
                'publishedAt' => '2024-03-10T16:45:00Z',
                'tags' => ['travel', 'europe', 'guide'],
                'author' => 'Travel Expert',
                'active' => true,
                'priority' => 2
            ]
        ];

        foreach ($testObjects as $object) {
            $collection->data()->create($object);
        }

        // Wait for indexing
        sleep(2);
    }

    /**
     * Test complex nested filters against real Weaviate
     *
     * @group integration
     */
    public function testComplexNestedFiltersAgainstRealWeaviate(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Complex filter: (category = 'technology' AND (status = 'published' OR featured = true)) AND viewCount > 500
        $complexFilter = Filter::allOf([
            Filter::byProperty('category')->equal('technology'),
            Filter::anyOf([
                Filter::byProperty('status')->equal('published'),
                Filter::byProperty('featured')->equal(true)
            ]),
            Filter::byProperty('viewCount')->greaterThan(500)
        ]);

        $results = $collection->query()
            ->where($complexFilter)
            ->returnProperties(['title', 'category', 'status', 'featured', 'viewCount'])
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        // Verify all results match the filter criteria
        foreach ($results as $result) {
            $this->assertEquals('technology', $result['category']);
            $this->assertGreaterThan(500, $result['viewCount']);
            $this->assertTrue(
                $result['status'] === 'published' || $result['featured'] === true,
                'Result should match either published status or featured flag'
            );
        }
    }

    /**
     * Test deeply nested filters with multiple levels
     *
     * @group integration
     */
    public function testDeeplyNestedFilters(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // 4-level nested filter:
        // active = true AND (
        //   (category = 'technology' AND viewCount > 500) OR
        //   (category = 'lifestyle' AND rating > 4.5)
        // ) AND priority >= 3
        $deepFilter = Filter::allOf([
            Filter::byProperty('active')->equal(true),
            Filter::anyOf([
                Filter::allOf([
                    Filter::byProperty('category')->equal('technology'),
                    Filter::byProperty('viewCount')->greaterThan(500)
                ]),
                Filter::allOf([
                    Filter::byProperty('category')->equal('lifestyle'),
                    Filter::byProperty('rating')->greaterThan(4.5)
                ])
            ]),
            Filter::byProperty('priority')->greaterThan(2)
        ]);

        $results = $collection->query()
            ->where($deepFilter)
            ->returnProperties(['title', 'category', 'viewCount', 'rating', 'priority', 'active'])
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        // Verify all results match the complex criteria
        foreach ($results as $result) {
            $this->assertTrue($result['active']);
            $this->assertGreaterThan(2, $result['priority']);

            // Must match one of the OR conditions
            $matchesTechCondition = ($result['category'] === 'technology' && $result['viewCount'] > 500);
            $matchesLifestyleCondition = ($result['category'] === 'lifestyle' && $result['rating'] > 4.5);

            $this->assertTrue(
                $matchesTechCondition || $matchesLifestyleCondition,
                'Result must match either technology or lifestyle condition'
            );
        }
    }

    /**
     * Test array containment in nested filters
     *
     * @group integration
     */
    public function testArrayContainmentInNestedFilters(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Filter: (tags contains 'technology' OR tags contains 'lifestyle') AND status = 'published'
        $arrayFilter = Filter::allOf([
            Filter::anyOf([
                Filter::byProperty('tags')->containsAny(['technology']),
                Filter::byProperty('tags')->containsAny(['lifestyle'])
            ]),
            Filter::byProperty('status')->equal('published')
        ]);

        $results = $collection->query()
            ->where($arrayFilter)
            ->returnProperties(['title', 'tags', 'status'])
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        foreach ($results as $result) {
            $this->assertEquals('published', $result['status']);
            $this->assertTrue(
                in_array('technology', $result['tags']) || in_array('lifestyle', $result['tags']),
                'Result must contain either technology or lifestyle tag'
            );
        }
    }

    /**
     * Test null handling in nested filters
     *
     * @group integration
     */
    public function testNullHandlingInNestedFilters(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Filter: publishedAt IS NOT NULL AND (viewCount > 1000 OR featured = true)
        $nullFilter = Filter::allOf([
            Filter::byProperty('publishedAt')->isNull(false),
            Filter::anyOf([
                Filter::byProperty('viewCount')->greaterThan(1000),
                Filter::byProperty('featured')->equal(true)
            ])
        ]);

        $results = $collection->query()
            ->where($nullFilter)
            ->returnProperties(['title', 'publishedAt', 'viewCount', 'featured'])
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        foreach ($results as $result) {
            $this->assertNotNull($result['publishedAt']);
            $this->assertTrue(
                $result['viewCount'] > 1000 || $result['featured'] === true,
                'Result must have high view count or be featured'
            );
        }
    }

    /**
     * Test performance with complex nested filters
     *
     * @group integration
     */
    public function testPerformanceWithComplexFilters(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Create a very complex filter to test performance
        $performanceFilter = Filter::allOf([
            Filter::anyOf([
                Filter::byProperty('category')->equal('technology'),
                Filter::byProperty('category')->equal('lifestyle'),
                Filter::byProperty('category')->equal('travel')
            ]),
            Filter::allOf([
                Filter::byProperty('status')->equal('published'),
                Filter::byProperty('active')->equal(true)
            ]),
            Filter::anyOf([
                Filter::allOf([
                    Filter::byProperty('viewCount')->greaterThan(1000),
                    Filter::byProperty('rating')->greaterThan(4.0)
                ]),
                Filter::byProperty('featured')->equal(true)
            ])
        ]);

        $startTime = microtime(true);

        $results = $collection->query()
            ->where($performanceFilter)
            ->limit(100)
            ->returnProperties(['category', 'status', 'active'])
            ->fetchObjects();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertIsArray($results);
        $this->assertLessThan(5.0, $executionTime, 'Complex query should complete within 5 seconds');

        // Verify results match the complex criteria
        foreach ($results as $result) {
            $this->assertArrayHasKey('category', $result);
            $this->assertArrayHasKey('status', $result);
            $this->assertArrayHasKey('active', $result);

            $this->assertContains($result['category'], ['technology', 'lifestyle', 'travel']);
            $this->assertEquals('published', $result['status']);
            $this->assertTrue($result['active']);
        }
    }

    /**
     * Test mixed data type filtering in nested structures
     *
     * @group integration
     */
    public function testMixedDataTypeFiltering(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Filter combining string, boolean, integer, and float comparisons
        $mixedFilter = Filter::allOf([
            Filter::byProperty('author')->like('*Dr*'),  // String pattern
            Filter::byProperty('active')->equal(true),   // Boolean
            Filter::byProperty('viewCount')->greaterThan(1000), // Integer
            Filter::byProperty('rating')->greaterThan(4.5)      // Float
        ]);

        $results = $collection->query()
            ->where($mixedFilter)
            ->returnProperties(['title', 'author', 'active', 'viewCount', 'rating'])
            ->fetchObjects();

        $this->assertIsArray($results);

        foreach ($results as $result) {
            $this->assertStringContainsString('Dr', $result['author']);
            $this->assertTrue($result['active']);
            $this->assertGreaterThan(1000, $result['viewCount']);
            $this->assertGreaterThan(4.5, $result['rating']);
        }
    }

    /**
     * Test error handling with invalid nested filters
     *
     * @group integration
     */
    public function testErrorHandlingWithInvalidFilters(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Test with non-existent property
        $invalidFilter = Filter::byProperty('nonExistentProperty')->equal('value');

        $this->expectException(\Exception::class);

        $collection->query()
            ->where($invalidFilter)
            ->fetchObjects();
    }

    /**
     * Clean up test collection
     */
    private function cleanupTestCollection(): void
    {
        try {
            $this->client->schema()->delete($this->testClassName);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
