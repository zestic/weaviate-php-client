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

namespace Weaviate\Tests\Performance\Query;

use Weaviate\Query\Filter;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Performance tests for query operations
 *
 * These tests measure query execution times and ensure performance
 * meets acceptable thresholds for various query scenarios.
 *
 * @group performance
 * @covers \Weaviate\Query\QueryBuilder
 * @covers \Weaviate\Query\Filter
 */
class QueryPerformanceTest extends TestCase
{
    private WeaviateClient $client;
    private string $testClassName = 'PerformanceTestClass';
    private int $testDataSize = 1000;

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
        $this->insertLargeTestDataset();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCollection();
    }

    /**
     * Create test collection optimized for performance testing
     */
    private function createTestCollection(): void
    {
        $schema = [
            'class' => $this->testClassName,
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'category', 'dataType' => ['text']],
                ['name' => 'status', 'dataType' => ['text']],
                ['name' => 'priority', 'dataType' => ['int']],
                ['name' => 'score', 'dataType' => ['number']],
                ['name' => 'active', 'dataType' => ['boolean']],
                ['name' => 'tags', 'dataType' => ['text[]']],
                ['name' => 'createdAt', 'dataType' => ['date']],
                ['name' => 'metadata', 'dataType' => ['text']]
            ]
        ];

        $this->client->schema()->create($schema);
    }

    /**
     * Insert large dataset for performance testing
     */
    private function insertLargeTestDataset(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);
        
        $categories = ['technology', 'science', 'business', 'lifestyle', 'travel'];
        $statuses = ['active', 'inactive', 'pending', 'archived'];
        $tags = ['php', 'javascript', 'python', 'java', 'go', 'rust', 'ai', 'ml', 'web', 'mobile'];

        for ($i = 0; $i < $this->testDataSize; $i++) {
            $object = [
                'title' => "Test Object {$i}",
                'category' => $categories[$i % count($categories)],
                'status' => $statuses[$i % count($statuses)],
                'priority' => ($i % 10) + 1,
                'score' => round(($i % 100) / 10, 2),
                'active' => ($i % 2) === 0,
                'tags' => array_slice($tags, $i % 5, 3),
                'createdAt' => date('c', time() - ($i * 3600)), // $i hours ago
                'metadata' => json_encode(['index' => $i, 'batch' => floor($i / 100)])
            ];

            $collection->data()->create($object);

            // Add small delay every 100 objects to prevent overwhelming
            if ($i % 100 === 0) {
                usleep(100000); // 100ms
            }
        }

        // Wait for indexing
        sleep(5);
    }

    /**
     * Test simple query performance
     *
     * @group performance
     */
    public function testSimpleQueryPerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $startTime = microtime(true);

        $results = $collection->query()
            ->where(Filter::byProperty('status')->equal('active'))
            ->limit(100)
            ->fetchObjects();

        $duration = microtime(true) - $startTime;

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(100, count($results));
        $this->assertLessThan(2.0, $duration, 'Simple query should complete within 2 seconds');

        // Log performance metrics
        echo "\nSimple Query Performance: {$duration}s for " . count($results) . " results\n";
    }

    /**
     * Test complex query performance
     *
     * @group performance
     */
    public function testComplexQueryPerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $complexFilter = Filter::allOf([
            Filter::anyOf([
                Filter::byProperty('category')->equal('technology'),
                Filter::byProperty('category')->equal('science')
            ]),
            Filter::allOf([
                Filter::byProperty('status')->equal('active'),
                Filter::byProperty('active')->equal(true)
            ]),
            Filter::anyOf([
                Filter::byProperty('priority')->greaterThan(5),
                Filter::byProperty('score')->greaterThan(7.0)
            ])
        ]);

        $startTime = microtime(true);

        $results = $collection->query()
            ->where($complexFilter)
            ->limit(50)
            ->fetchObjects();

        $duration = microtime(true) - $startTime;

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(50, count($results));
        $this->assertLessThan(3.0, $duration, 'Complex query should complete within 3 seconds');

        echo "\nComplex Query Performance: {$duration}s for " . count($results) . " results\n";
    }

    /**
     * Test large result set performance
     *
     * @group performance
     */
    public function testLargeResultSetPerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $startTime = microtime(true);

        $results = $collection->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->limit(500) // Large result set
            ->fetchObjects();

        $duration = microtime(true) - $startTime;

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(500, count($results));
        $this->assertLessThan(5.0, $duration, 'Large result set query should complete within 5 seconds');

        echo "\nLarge Result Set Performance: {$duration}s for " . count($results) . " results\n";
    }

    /**
     * Test concurrent query performance
     *
     * @group performance
     */
    public function testConcurrentQueryPerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $queries = [
            Filter::byProperty('category')->equal('technology'),
            Filter::byProperty('status')->equal('active'),
            Filter::byProperty('priority')->greaterThan(5),
            Filter::byProperty('score')->lessThan(5.0),
            Filter::byProperty('active')->equal(false)
        ];

        $startTime = microtime(true);
        $results = [];

        // Execute multiple queries in sequence (simulating concurrent load)
        foreach ($queries as $i => $filter) {
            $queryStart = microtime(true);
            
            $queryResults = $collection->query()
                ->where($filter)
                ->limit(20)
                ->fetchObjects();
                
            $queryDuration = microtime(true) - $queryStart;
            
            $results[] = [
                'query' => $i,
                'duration' => $queryDuration,
                'count' => count($queryResults)
            ];

            $this->assertLessThan(2.0, $queryDuration, "Query {$i} should complete within 2 seconds");
        }

        $totalDuration = microtime(true) - $startTime;
        $avgDuration = $totalDuration / count($queries);

        $this->assertLessThan(8.0, $totalDuration, 'All concurrent queries should complete within 8 seconds');
        $this->assertLessThan(2.0, $avgDuration, 'Average query time should be under 2 seconds');

        echo "\nConcurrent Query Performance:\n";
        echo "Total: {$totalDuration}s, Average: {$avgDuration}s\n";
        foreach ($results as $result) {
            echo "Query {$result['query']}: {$result['duration']}s ({$result['count']} results)\n";
        }
    }

    /**
     * Test query performance with different field selections
     *
     * @group performance
     */
    public function testFieldSelectionPerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        // Test minimal fields
        $startTime = microtime(true);
        $minimalResults = $collection->query()
            ->returnProperties(['title'])
            ->where(Filter::byProperty('status')->equal('active'))
            ->limit(100)
            ->fetchObjects();
        $minimalDuration = microtime(true) - $startTime;

        // Test all fields
        $startTime = microtime(true);
        $fullResults = $collection->query()
            ->returnProperties(['title', 'category', 'status', 'priority', 'score', 'active', 'tags', 'createdAt', 'metadata'])
            ->where(Filter::byProperty('status')->equal('active'))
            ->limit(100)
            ->fetchObjects();
        $fullDuration = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $minimalDuration, 'Minimal field query should be fast');
        $this->assertLessThan(3.0, $fullDuration, 'Full field query should complete within 3 seconds');

        // Minimal fields should generally be faster
        $this->assertLessThanOrEqual($fullDuration * 1.5, $minimalDuration, 'Minimal fields should not be significantly slower');

        echo "\nField Selection Performance:\n";
        echo "Minimal fields: {$minimalDuration}s (" . count($minimalResults) . " results)\n";
        echo "All fields: {$fullDuration}s (" . count($fullResults) . " results)\n";
    }

    /**
     * Test query performance with different limit sizes
     *
     * @group performance
     */
    public function testLimitSizePerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);
        $filter = Filter::byProperty('active')->equal(true);

        $limits = [10, 50, 100, 200, 500];
        $results = [];

        foreach ($limits as $limit) {
            $startTime = microtime(true);
            
            $queryResults = $collection->query()
                ->where($filter)
                ->limit($limit)
                ->fetchObjects();
                
            $duration = microtime(true) - $startTime;
            
            $results[] = [
                'limit' => $limit,
                'duration' => $duration,
                'count' => count($queryResults)
            ];

            // Performance should scale reasonably with limit size
            $expectedMaxTime = 1.0 + ($limit / 200); // Base 1s + scaling factor
            $this->assertLessThan($expectedMaxTime, $duration, "Query with limit {$limit} took too long");
        }

        echo "\nLimit Size Performance:\n";
        foreach ($results as $result) {
            echo "Limit {$result['limit']}: {$result['duration']}s ({$result['count']} results)\n";
        }
    }

    /**
     * Test memory usage during large queries
     *
     * @group performance
     */
    public function testMemoryUsagePerformance(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $memoryBefore = memory_get_usage(true);

        $results = $collection->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->limit(500)
            ->fetchObjects();

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $this->assertIsArray($results);
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Query should use less than 50MB of memory'); // 50MB limit

        echo "\nMemory Usage Performance:\n";
        echo "Memory used: " . round($memoryUsed / 1024 / 1024, 2) . "MB for " . count($results) . " results\n";
        echo "Memory per result: " . round($memoryUsed / count($results), 2) . " bytes\n";
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
