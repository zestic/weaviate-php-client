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
 * Integration tests for Query functionality
 *
 * These tests run against a real Weaviate instance to ensure
 * the query functionality works correctly in practice.
 */
class QueryIntegrationTest extends TestCase
{
    private WeaviateClient $client;
    private string $testClassName = 'QueryTestClass';

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create client (no auth needed for test instance)
        $this->client = new WeaviateClient($connection);

        parent::setUp();
        $this->createTestCollection();
        $this->insertTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCollection();
        parent::tearDown();
    }

    /**
     * @group integration
     */
    public function testBasicQueryWithoutFilter(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $results = $collection->query()->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * @group integration
     */
    public function testQueryWithSimpleFilter(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $results = $collection->query()
            ->where(Filter::byProperty('status')->equal('active'))
            ->returnProperties(['name', 'status', 'age', 'email'])
            ->fetchObjects();

        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertEquals('active', $result['status']);
        }
    }

    /**
     * @group integration
     */
    public function testQueryWithLimit(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $results = $collection->query()
            ->limit(2)
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(2, count($results));
    }

    /**
     * @group integration
     */
    public function testQueryWithCustomProperties(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $results = $collection->query()
            ->returnProperties(['name', 'status'])
            ->limit(1)
            ->fetchObjects();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));

        $result = $results[0];
        // Weaviate returns properties directly, not wrapped in a 'properties' key
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('status', $result);
    }

    /**
     * @group integration
     */
    public function testQueryWithComplexFilter(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $filter = Filter::allOf([
            Filter::byProperty('status')->equal('active'),
            Filter::byProperty('age')->greaterThan(18)
        ]);

        $results = $collection->query()
            ->where($filter)
            ->returnProperties(['name', 'status', 'age', 'email'])
            ->fetchObjects();

        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertEquals('active', $result['status']);
            $this->assertGreaterThan(18, $result['age']);
        }
    }

    /**
     * @group integration
     */
    public function testFindByMethod(): void
    {
        $collection = $this->client->collections()->get($this->testClassName)
            ->setDefaultQueryFields('name status age email');

        $results = $collection->data()->findBy(['status' => 'active']);

        $this->assertIsArray($results);
        foreach ($results as $result) {
            $this->assertEquals('active', $result['status']);
        }
    }

    /**
     * @group integration
     */
    public function testFindOneByMethod(): void
    {
        $collection = $this->client->collections()->get($this->testClassName)
            ->setDefaultQueryFields('name status age email');

        $result = $collection->data()->findOneBy(['name' => 'John Doe']);

        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['name']);
    }

    /**
     * @group integration
     */
    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $result = $collection->data()->findOneBy(['name' => 'Non Existent']);

        $this->assertNull($result);
    }

    private function createTestCollection(): void
    {
        if ($this->client->collections()->exists($this->testClassName)) {
            $this->client->collections()->delete($this->testClassName);
        }

        $this->client->collections()->create($this->testClassName, [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']],
                ['name' => 'status', 'dataType' => ['text']],
                ['name' => 'age', 'dataType' => ['int']],
                ['name' => 'email', 'dataType' => ['text']],
            ]
        ]);
    }

    private function insertTestData(): void
    {
        $collection = $this->client->collections()->get($this->testClassName);

        $testData = [
            ['name' => 'John Doe', 'status' => 'active', 'age' => 30, 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'status' => 'active', 'age' => 25, 'email' => 'jane@example.com'],
            ['name' => 'Bob Johnson', 'status' => 'inactive', 'age' => 35, 'email' => 'bob@example.com'],
            ['name' => 'Alice Brown', 'status' => 'active', 'age' => 28, 'email' => 'alice@example.com'],
            ['name' => 'Charlie Wilson', 'status' => 'pending', 'age' => 22, 'email' => 'charlie@example.com'],
        ];

        foreach ($testData as $data) {
            $collection->data()->create($data);
        }

        // Give Weaviate a moment to index the data
        usleep(100000); // 100ms
    }

    private function cleanupTestCollection(): void
    {
        if ($this->client->collections()->exists($this->testClassName)) {
            $this->client->collections()->delete($this->testClassName);
        }
    }
}
