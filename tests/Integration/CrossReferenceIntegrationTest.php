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

namespace Weaviate\Tests\Integration;

use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Query\Filter;

/**
 * Integration tests for cross-reference functionality
 * 
 * These tests verify that the new cross-reference management and querying
 * features work correctly with a real Weaviate instance.
 */
class CrossReferenceIntegrationTest extends TestCase
{
    private WeaviateClient $client;
    private string $questionCollectionName = 'Question';
    private string $categoryCollectionName = 'Category';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = WeaviateClient::connectToLocal();
        
        // Clean up any existing collections
        $collections = $this->client->collections();
        if ($collections->exists($this->questionCollectionName)) {
            $collections->delete($this->questionCollectionName);
        }
        if ($collections->exists($this->categoryCollectionName)) {
            $collections->delete($this->categoryCollectionName);
        }
        
        // Create test collections with cross-reference
        $this->createTestCollections();
    }

    protected function tearDown(): void
    {
        // Clean up test collections
        $collections = $this->client->collections();
        if ($collections->exists($this->questionCollectionName)) {
            $collections->delete($this->questionCollectionName);
        }
        if ($collections->exists($this->categoryCollectionName)) {
            $collections->delete($this->categoryCollectionName);
        }
        
        parent::tearDown();
    }

    /**
     * @covers \Weaviate\Data\DataOperations::referenceAdd
     * @covers \Weaviate\Data\DataOperations::referenceDelete
     * @covers \Weaviate\Data\DataOperations::referenceReplace
     */
    public function testCrossReferenceManagement(): void
    {
        // Create test objects
        $categoryCollection = $this->client->collections()->get($this->categoryCollectionName);
        $questionCollection = $this->client->collections()->get($this->questionCollectionName);
        
        // Create category
        $categoryResult = $categoryCollection->data()->create([
            'title' => 'Technology',
            'description' => 'Technology related questions'
        ]);
        $categoryId = $categoryResult['id'];
        
        // Create question
        $questionResult = $questionCollection->data()->create([
            'question' => 'What is PHP?',
            'answer' => 'PHP is a programming language'
        ]);
        $questionId = $questionResult['id'];
        
        // Test adding cross-reference
        $addResult = $questionCollection->data()->referenceAdd(
            $questionId,
            'hasCategory',
            $categoryId
        );
        $this->assertTrue($addResult);
        
        // Test replacing cross-reference
        $replaceResult = $questionCollection->data()->referenceReplace(
            $questionId,
            'hasCategory',
            $categoryId
        );
        $this->assertTrue($replaceResult);
        
        // Test deleting cross-reference
        $deleteResult = $questionCollection->data()->referenceDelete(
            $questionId,
            'hasCategory',
            $categoryId
        );
        $this->assertTrue($deleteResult);
    }

    /**
     * @covers \Weaviate\Data\DataOperations::referenceAddMany
     */
    public function testBatchCrossReferenceManagement(): void
    {
        // Create test objects
        $categoryCollection = $this->client->collections()->get($this->categoryCollectionName);
        $questionCollection = $this->client->collections()->get($this->questionCollectionName);
        
        // Create categories
        $techCategory = $categoryCollection->data()->create([
            'title' => 'Technology',
            'description' => 'Tech questions'
        ]);
        
        $scienceCategory = $categoryCollection->data()->create([
            'title' => 'Science',
            'description' => 'Science questions'
        ]);
        
        // Create questions
        $question1 = $questionCollection->data()->create([
            'question' => 'What is PHP?',
            'answer' => 'A programming language'
        ]);
        
        $question2 = $questionCollection->data()->create([
            'question' => 'What is gravity?',
            'answer' => 'A fundamental force'
        ]);
        
        // Test batch adding cross-references
        $references = [
            [
                'fromUuid' => $question1['id'],
                'fromProperty' => 'hasCategory',
                'to' => $techCategory['id']
            ],
            [
                'fromUuid' => $question2['id'],
                'fromProperty' => 'hasCategory',
                'to' => $scienceCategory['id']
            ]
        ];
        
        $results = $questionCollection->data()->referenceAddMany($references);
        
        $this->assertCount(2, $results);
        $this->assertTrue($results["{$question1['id']}.hasCategory"]);
        $this->assertTrue($results["{$question2['id']}.hasCategory"]);
    }

    /**
     * @covers \Weaviate\Query\Filter::byRef
     * @covers \Weaviate\Query\ReferenceFilter
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     */
    public function testCrossReferenceQuerying(): void
    {
        // Create test data with cross-references
        $this->createTestDataWithReferences();
        
        $questionCollection = $this->client->collections()->get($this->questionCollectionName);
        
        // Test filtering by cross-referenced property
        $results = $questionCollection->query()
            ->where(Filter::byRef('hasCategory')->byProperty('title')->like('*Tech*'))
            ->returnReferences(['hasCategory' => ['title', 'description']])
            ->fetchObjects();
        
        $this->assertNotEmpty($results);
        
        // Verify that results include cross-reference data
        foreach ($results as $result) {
            $this->assertArrayHasKey('hasCategory', $result);
            $this->assertArrayHasKey('title', $result['hasCategory']);
        }
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::aggregate
     * @covers \Weaviate\Query\AggregateBuilder
     */
    public function testAggregationQueries(): void
    {
        // Create test data
        $this->createTestDataWithReferences();
        
        $questionCollection = $this->client->collections()->get($this->questionCollectionName);
        
        // Test simple count aggregation
        $countResult = $questionCollection->query()
            ->aggregate()
            ->metrics(['count'])
            ->execute();
        
        $this->assertNotEmpty($countResult);
        $this->assertIsArray($countResult);

        $firstResult = reset($countResult);
        $this->assertIsArray($firstResult);
        $this->assertArrayHasKey('meta', $firstResult);
        $this->assertArrayHasKey('count', $firstResult['meta']);
        $this->assertGreaterThan(0, $firstResult['meta']['count']);
    }

    private function createTestCollections(): void
    {
        $collections = $this->client->collections();
        
        // Create Category collection
        $collections->create($this->categoryCollectionName, [
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text']
                ],
                [
                    'name' => 'description',
                    'dataType' => ['text']
                ]
            ]
        ]);
        
        // Create Question collection with cross-reference to Category
        $collections->create($this->questionCollectionName, [
            'properties' => [
                [
                    'name' => 'question',
                    'dataType' => ['text']
                ],
                [
                    'name' => 'answer',
                    'dataType' => ['text']
                ],
                [
                    'name' => 'hasCategory',
                    'dataType' => [$this->categoryCollectionName]
                ]
            ]
        ]);
    }

    private function createTestDataWithReferences(): void
    {
        $categoryCollection = $this->client->collections()->get($this->categoryCollectionName);
        $questionCollection = $this->client->collections()->get($this->questionCollectionName);
        
        // Create categories
        $techCategory = $categoryCollection->data()->create([
            'title' => 'Technology',
            'description' => 'Technology related questions'
        ]);
        
        $scienceCategory = $categoryCollection->data()->create([
            'title' => 'Science',
            'description' => 'Science related questions'
        ]);
        
        // Create questions
        $phpQuestion = $questionCollection->data()->create([
            'question' => 'What is PHP?',
            'answer' => 'PHP is a programming language'
        ]);
        
        $gravityQuestion = $questionCollection->data()->create([
            'question' => 'What is gravity?',
            'answer' => 'Gravity is a fundamental force'
        ]);
        
        // Add cross-references
        $questionCollection->data()->referenceAdd(
            $phpQuestion['id'],
            'hasCategory',
            $techCategory['id']
        );
        
        $questionCollection->data()->referenceAdd(
            $gravityQuestion['id'],
            'hasCategory',
            $scienceCategory['id']
        );
    }
}
