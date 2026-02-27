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

use Weaviate\Query\Filter;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Integration tests for query isolation between multiple clients
 *
 * These tests ensure that queries from different client instances
 * don't interfere with each other and maintain proper isolation.
 *
 * @group integration
 */
class MultipleClientsQueryTest extends TestCase
{
    private WeaviateClient $client;
    private string $testClassName = 'MultiClientTestClass';
    private WeaviateClient $client2;
    private WeaviateClient $client3;

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection for main client (no auth for test instance)
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create main client
        $this->client = new WeaviateClient($connection);

        // Create additional client instances
        $connection2 = new HttpConnection(
            $this->getWeaviateUrl(),
            new Client(),
            $httpFactory,
            $httpFactory
        );
        $this->client2 = new WeaviateClient($connection2);

        $connection3 = new HttpConnection(
            $this->getWeaviateUrl(),
            new Client(),
            $httpFactory,
            $httpFactory
        );
        $this->client3 = new WeaviateClient($connection3);

        $this->createTestCollection();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCollection();
    }

    /**
     * Create test collection with multi-tenant support
     */
    private function createTestCollection(): void
    {
        $schema = [
            'class' => $this->testClassName,
            'multiTenancyConfig' => ['enabled' => true],
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'category', 'dataType' => ['text']],
                ['name' => 'status', 'dataType' => ['text']],
                ['name' => 'clientId', 'dataType' => ['text']],
                ['name' => 'priority', 'dataType' => ['int']],
                ['name' => 'active', 'dataType' => ['boolean']]
            ]
        ];

        $this->client->schema()->create($schema);

        // Create tenants for isolation testing
        $collection = $this->client->collections()->get($this->testClassName);
        $collection->tenants()->create(['tenant1', 'tenant2', 'tenant3']);

        // Wait for tenant creation
        sleep(2);
    }

    /**
     * Insert test data for different tenants and clients
     */
    private function insertTestData(): void
    {
        $testData = [
            'tenant1' => [
                [
                    'title' => 'Client1 Article 1', 'category' => 'tech', 'status' => 'published',
                    'clientId' => 'client1', 'priority' => 1, 'active' => true
                ],
                [
                    'title' => 'Client1 Article 2', 'category' => 'science', 'status' => 'draft',
                    'clientId' => 'client1', 'priority' => 2, 'active' => false
                ],
                [
                    'title' => 'Client1 Article 3', 'category' => 'tech', 'status' => 'published',
                    'clientId' => 'client1', 'priority' => 3, 'active' => true
                ]
            ],
            'tenant2' => [
                [
                    'title' => 'Client2 Article 1', 'category' => 'business', 'status' => 'published',
                    'clientId' => 'client2', 'priority' => 1, 'active' => true
                ],
                [
                    'title' => 'Client2 Article 2', 'category' => 'tech', 'status' => 'published',
                    'clientId' => 'client2', 'priority' => 2, 'active' => true
                ],
                [
                    'title' => 'Client2 Article 3', 'category' => 'business', 'status' => 'archived',
                    'clientId' => 'client2', 'priority' => 3, 'active' => false
                ]
            ],
            'tenant3' => [
                [
                    'title' => 'Client3 Article 1', 'category' => 'lifestyle', 'status' => 'published',
                    'clientId' => 'client3', 'priority' => 1, 'active' => true
                ],
                [
                    'title' => 'Client3 Article 2', 'category' => 'travel', 'status' => 'published',
                    'clientId' => 'client3', 'priority' => 2, 'active' => true
                ],
                [
                    'title' => 'Client3 Article 3', 'category' => 'lifestyle', 'status' => 'draft',
                    'clientId' => 'client3', 'priority' => 3, 'active' => false
                ]
            ]
        ];

        foreach ($testData as $tenant => $objects) {
            $collection = $this->client->collections()->get($this->testClassName)->withTenant($tenant);
            foreach ($objects as $object) {
                $collection->data()->create($object);
            }
        }

        // Wait for indexing
        sleep(2);
    }

    /**
     * Test that queries from different clients are properly isolated by tenant
     *
     * @group integration
     */
    public function testTenantIsolationBetweenClients(): void
    {
        // Client 1 queries tenant1
        $collection1 = $this->client->collections()->get($this->testClassName)->withTenant('tenant1');
        $results1 = $collection1->query()
            ->where(Filter::byProperty('status')->equal('published'))
            ->returnProperties(['title', 'clientId', 'category'])
            ->fetchObjects();

        // Client 2 queries tenant2
        $collection2 = $this->client2->collections()->get($this->testClassName)->withTenant('tenant2');
        $results2 = $collection2->query()
            ->where(Filter::byProperty('status')->equal('published'))
            ->returnProperties(['title', 'clientId', 'category'])
            ->fetchObjects();

        // Client 3 queries tenant3
        $collection3 = $this->client3->collections()->get($this->testClassName)->withTenant('tenant3');
        $results3 = $collection3->query()
            ->where(Filter::byProperty('status')->equal('published'))
            ->returnProperties(['title', 'clientId', 'category'])
            ->fetchObjects();

        // Verify each client only sees their tenant's data
        $this->assertGreaterThan(0, count($results1));
        $this->assertGreaterThan(0, count($results2));
        $this->assertGreaterThan(0, count($results3));

        // Verify tenant isolation
        foreach ($results1 as $result) {
            $this->assertEquals('client1', $result['clientId']);
        }

        foreach ($results2 as $result) {
            $this->assertEquals('client2', $result['clientId']);
        }

        foreach ($results3 as $result) {
            $this->assertEquals('client3', $result['clientId']);
        }
    }

    /**
     * Test concurrent queries from multiple clients
     *
     * @group integration
     */
    public function testConcurrentQueriesFromMultipleClients(): void
    {
        $startTime = microtime(true);

        // Execute queries concurrently (in practice, this is sequential but tests the pattern)
        $promises = [];

        // Client 1 - Complex filter
        $collection1 = $this->client->collections()->get($this->testClassName)->withTenant('tenant1');
        $filter1 = Filter::allOf([
            Filter::byProperty('category')->equal('tech'),
            Filter::byProperty('active')->equal(true)
        ]);

        // Client 2 - Simple filter
        $collection2 = $this->client2->collections()->get($this->testClassName)->withTenant('tenant2');
        $filter2 = Filter::byProperty('status')->equal('published');

        // Client 3 - Range filter
        $collection3 = $this->client3->collections()->get($this->testClassName)->withTenant('tenant3');
        $filter3 = Filter::byProperty('priority')->greaterThan(1);

        // Execute queries
        $results1 = $collection1->query()->where($filter1)->returnProperties(['title', 'clientId'])->fetchObjects();
        $results2 = $collection2->query()->where($filter2)->returnProperties(['title', 'clientId'])->fetchObjects();
        $results3 = $collection3->query()->where($filter3)->returnProperties(['title', 'clientId'])->fetchObjects();

        $totalTime = microtime(true) - $startTime;

        // Verify all queries completed successfully
        $this->assertIsArray($results1);
        $this->assertIsArray($results2);
        $this->assertIsArray($results3);

        // Verify reasonable performance for concurrent queries
        $this->assertLessThan(10.0, $totalTime, 'Concurrent queries should complete within 10 seconds');

        // Verify data isolation
        foreach ($results1 as $result) {
            $this->assertArrayHasKey('title', $result);
            $this->assertNotNull($result['title']);
            $this->assertStringContainsString('Client1', $result['title']);
        }

        foreach ($results2 as $result) {
            $this->assertArrayHasKey('title', $result);
            $this->assertNotNull($result['title']);
            $this->assertStringContainsString('Client2', $result['title']);
        }

        foreach ($results3 as $result) {
            $this->assertArrayHasKey('title', $result);
            $this->assertNotNull($result['title']);
            $this->assertStringContainsString('Client3', $result['title']);
        }
    }

    /**
     * Test that client-specific configurations don't interfere
     *
     * @group integration
     */
    public function testClientConfigurationIsolation(): void
    {
        // Configure different default fields for each client's collection
        $collection1 = $this->client->collections()->get($this->testClassName)
            ->withTenant('tenant1')
            ->setDefaultQueryFields('title category');

        $collection2 = $this->client2->collections()->get($this->testClassName)
            ->withTenant('tenant2')
            ->setDefaultQueryFields('title status priority');

        $collection3 = $this->client3->collections()->get($this->testClassName)
            ->withTenant('tenant3')
            ->setDefaultQueryFields('title clientId active');

        // Execute queries using default fields
        $results1 = $collection1->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->fetchObjects();

        $results2 = $collection2->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->fetchObjects();

        $results3 = $collection3->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->fetchObjects();

        // Verify each client gets results with their configured fields
        $this->assertGreaterThan(0, count($results1));
        $this->assertGreaterThan(0, count($results2));
        $this->assertGreaterThan(0, count($results3));

        // Verify field configurations are isolated
        if (!empty($results1)) {
            $this->assertArrayHasKey('title', $results1[0]);
            $this->assertArrayHasKey('category', $results1[0]);
        }

        if (!empty($results2)) {
            $this->assertArrayHasKey('title', $results2[0]);
            $this->assertArrayHasKey('status', $results2[0]);
            $this->assertArrayHasKey('priority', $results2[0]);
        }

        if (!empty($results3)) {
            $this->assertArrayHasKey('title', $results3[0]);
            $this->assertArrayHasKey('clientId', $results3[0]);
            $this->assertArrayHasKey('active', $results3[0]);
        }
    }

    /**
     * Test error isolation between clients
     *
     * @group integration
     */
    public function testErrorIsolationBetweenClients(): void
    {
        $collection1 = $this->client->collections()->get($this->testClassName)->withTenant('tenant1');
        $collection2 = $this->client2->collections()->get($this->testClassName)->withTenant('tenant2');

        // Client 1 executes a valid query
        $validResults = $collection1->query()
            ->where(Filter::byProperty('status')->equal('published'))
            ->fetchObjects();

        // Client 2 executes an invalid query
        $invalidQueryExecuted = false;
        try {
            $collection2->query()
                ->where(Filter::byProperty('nonExistentField')->equal('value'))
                ->fetchObjects();
        } catch (\Exception $e) {
            $invalidQueryExecuted = true;
        }

        // Verify that client 1's valid query succeeded
        $this->assertIsArray($validResults);
        $this->assertGreaterThan(0, count($validResults));

        // Verify that client 2's invalid query failed as expected
        $this->assertTrue($invalidQueryExecuted, 'Invalid query should have thrown an exception');

        // Verify that client 1 can still execute queries after client 2's error
        $subsequentResults = $collection1->query()
            ->where(Filter::byProperty('active')->equal(true))
            ->fetchObjects();

        $this->assertIsArray($subsequentResults);
    }

    /**
     * Test query state isolation between clients
     *
     * @group integration
     */
    public function testQueryStateIsolation(): void
    {
        $collection1 = $this->client->collections()->get($this->testClassName)->withTenant('tenant1');
        $collection2 = $this->client2->collections()->get($this->testClassName)->withTenant('tenant2');

        // Client 1 builds a query with specific filters and limits
        $query1 = $collection1->query()
            ->where(Filter::byProperty('category')->equal('tech'))
            ->limit(5)
            ->returnProperties(['title', 'category']);

        // Client 2 builds a different query
        $query2 = $collection2->query()
            ->where(Filter::byProperty('status')->equal('published'))
            ->limit(10)
            ->returnProperties(['title', 'status', 'priority']);

        // Execute both queries
        $results1 = $query1->fetchObjects();
        $results2 = $query2->fetchObjects();

        // Verify queries maintained their independent state
        $this->assertIsArray($results1);
        $this->assertIsArray($results2);

        // Verify result limits were respected independently
        $this->assertLessThanOrEqual(5, count($results1));
        $this->assertLessThanOrEqual(10, count($results2));

        // Verify field selections were maintained independently
        if (!empty($results1)) {
            $this->assertArrayHasKey('title', $results1[0]);
            $this->assertArrayHasKey('category', $results1[0]);
            $this->assertArrayNotHasKey('status', $results1[0]);
        }

        if (!empty($results2)) {
            $this->assertArrayHasKey('title', $results2[0]);
            $this->assertArrayHasKey('status', $results2[0]);
            $this->assertArrayHasKey('priority', $results2[0]);
            $this->assertArrayNotHasKey('category', $results2[0]);
        }
    }

    /**
     * Test memory isolation between multiple client queries
     *
     * @group integration
     */
    public function testMemoryIsolationBetweenClients(): void
    {
        $memoryBefore = memory_get_usage(true);

        // Execute queries from multiple clients
        $collection1 = $this->client->collections()->get($this->testClassName)->withTenant('tenant1');
        $collection2 = $this->client2->collections()->get($this->testClassName)->withTenant('tenant2');
        $collection3 = $this->client3->collections()->get($this->testClassName)->withTenant('tenant3');

        $results1 = $collection1->query()->fetchObjects();
        $results2 = $collection2->query()->fetchObjects();
        $results3 = $collection3->query()->fetchObjects();

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Verify all queries succeeded
        $this->assertIsArray($results1);
        $this->assertIsArray($results2);
        $this->assertIsArray($results3);

        // Verify reasonable memory usage
        $totalResults = count($results1) + count($results2) + count($results3);
        $this->assertLessThan(20 * 1024 * 1024, $memoryUsed, 'Multiple client queries should use less than 20MB');

        // Clean up results to test memory cleanup
        unset($results1, $results2, $results3);

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
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
