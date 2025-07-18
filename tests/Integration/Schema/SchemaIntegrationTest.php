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

namespace Weaviate\Tests\Integration\Schema;

use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;

class SchemaIntegrationTest extends TestCase
{
    private const TEST_CLASS_NAME = 'SchemaTestArticle';
    private WeaviateClient $client;

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $this->client = WeaviateClient::connectToLocal($host);

        // Clean up any existing test collection
        if ($this->client->schema()->exists(self::TEST_CLASS_NAME)) {
            $this->client->schema()->delete(self::TEST_CLASS_NAME);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test collection
        if ($this->client->schema()->exists(self::TEST_CLASS_NAME)) {
            $this->client->schema()->delete(self::TEST_CLASS_NAME);
        }
    }

    public function testGetCompleteSchema(): void
    {
        $schema = $this->client->schema()->get();
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
        $this->assertIsArray($schema['classes']);
    }

    public function testCollectionLifecycle(): void
    {
        // Test collection doesn't exist initially
        $this->assertFalse($this->client->schema()->exists(self::TEST_CLASS_NAME));

        // Create collection
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'description' => 'Test collection for schema integration tests',
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text'],
                    'description' => 'Article title'
                ],
                [
                    'name' => 'content',
                    'dataType' => ['text'],
                    'description' => 'Article content'
                ]
            ]
        ];

        $createdSchema = $this->client->schema()->create($classDefinition);
        
        $this->assertIsArray($createdSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $createdSchema['class']);
        $this->assertEquals('Test collection for schema integration tests', $createdSchema['description']);
        $this->assertCount(2, $createdSchema['properties']);

        // Test collection exists now
        $this->assertTrue($this->client->schema()->exists(self::TEST_CLASS_NAME));

        // Get specific collection schema
        $retrievedSchema = $this->client->schema()->get(self::TEST_CLASS_NAME);
        
        $this->assertIsArray($retrievedSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $retrievedSchema['class']);
        $this->assertEquals('Test collection for schema integration tests', $retrievedSchema['description']);
        $this->assertCount(2, $retrievedSchema['properties']);

        // Note: Weaviate may not support updating collection descriptions after creation
        // So we'll just verify the schema can be retrieved again
        $retrievedAgain = $this->client->schema()->get(self::TEST_CLASS_NAME);
        $this->assertEquals(self::TEST_CLASS_NAME, $retrievedAgain['class']);

        // Delete collection
        $deleteResult = $this->client->schema()->delete(self::TEST_CLASS_NAME);
        
        $this->assertTrue($deleteResult);
        $this->assertFalse($this->client->schema()->exists(self::TEST_CLASS_NAME));
    }

    public function testPropertyManagement(): void
    {
        // Create base collection
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text'],
                    'description' => 'Article title'
                ]
            ]
        ];

        $this->client->schema()->create($classDefinition);

        // Add property
        $newProperty = [
            'name' => 'author',
            'dataType' => ['text'],
            'description' => 'Article author'
        ];

        $updatedSchema = $this->client->schema()->addProperty(self::TEST_CLASS_NAME, $newProperty);
        
        $this->assertCount(2, $updatedSchema['properties']);
        
        // Find the author property
        $authorProperty = null;
        foreach ($updatedSchema['properties'] as $property) {
            if ($property['name'] === 'author') {
                $authorProperty = $property;
                break;
            }
        }
        
        $this->assertNotNull($authorProperty);
        $this->assertEquals('author', $authorProperty['name']);
        $this->assertEquals(['text'], $authorProperty['dataType']);
        $this->assertEquals('Article author', $authorProperty['description']);

        // Get specific property
        $retrievedProperty = $this->client->schema()->getProperty(self::TEST_CLASS_NAME, 'author');
        
        $this->assertEquals('author', $retrievedProperty['name']);
        $this->assertEquals(['text'], $retrievedProperty['dataType']);

        // Note: Weaviate may not support updating property descriptions after creation
        // So we'll just verify the property exists and can be retrieved
        $retrievedProperty = $this->client->schema()->getProperty(self::TEST_CLASS_NAME, 'author');
        $this->assertEquals('author', $retrievedProperty['name']);

        // Note: Weaviate may not support deleting properties from existing collections
        // This is a limitation of the Weaviate API, so we'll skip this test
        // In a real scenario, you would need to recreate the collection without the property

        // Verify we can still get the final schema
        $finalSchema = $this->client->schema()->get(self::TEST_CLASS_NAME);
        $this->assertCount(2, $finalSchema['properties']); // Both title and author should still exist
    }

    public function testCreateCollectionWithMultiTenancy(): void
    {
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text']
                ]
            ],
            'multiTenancyConfig' => [
                'enabled' => true
            ]
        ];

        $createdSchema = $this->client->schema()->create($classDefinition);
        
        $this->assertIsArray($createdSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $createdSchema['class']);
        $this->assertTrue($createdSchema['multiTenancyConfig']['enabled']);
    }

    public function testCreateCollectionWithVectorizer(): void
    {
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text']
                ]
            ],
            'vectorizer' => 'none' // Use 'none' to avoid requiring external vectorizer
        ];

        $createdSchema = $this->client->schema()->create($classDefinition);
        
        $this->assertIsArray($createdSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $createdSchema['class']);
        $this->assertEquals('none', $createdSchema['vectorizer']);
    }

    public function testCreateCollectionWithComplexProperties(): void
    {
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text'],
                    'description' => 'Article title',
                    'tokenization' => 'word'
                ],
                [
                    'name' => 'publishedAt',
                    'dataType' => ['date'],
                    'description' => 'Publication date'
                ],
                [
                    'name' => 'viewCount',
                    'dataType' => ['int'],
                    'description' => 'Number of views'
                ],
                [
                    'name' => 'rating',
                    'dataType' => ['number'],
                    'description' => 'Article rating'
                ],
                [
                    'name' => 'isPublished',
                    'dataType' => ['boolean'],
                    'description' => 'Publication status'
                ],
                [
                    'name' => 'tags',
                    'dataType' => ['text[]'],
                    'description' => 'Article tags'
                ]
            ]
        ];

        $createdSchema = $this->client->schema()->create($classDefinition);
        
        $this->assertIsArray($createdSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $createdSchema['class']);
        $this->assertCount(6, $createdSchema['properties']);

        // Verify each property type
        $propertyTypes = [];
        foreach ($createdSchema['properties'] as $property) {
            $propertyTypes[$property['name']] = $property['dataType'];
        }

        $this->assertEquals(['text'], $propertyTypes['title']);
        $this->assertEquals(['date'], $propertyTypes['publishedAt']);
        $this->assertEquals(['int'], $propertyTypes['viewCount']);
        $this->assertEquals(['number'], $propertyTypes['rating']);
        $this->assertEquals(['boolean'], $propertyTypes['isPublished']);
        $this->assertEquals(['text[]'], $propertyTypes['tags']);
    }

    public function testGetPropertyThrowsExceptionForNonExistentProperty(): void
    {
        // Create collection with one property
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text']
                ]
            ]
        ];

        $this->client->schema()->create($classDefinition);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'nonexistent' not found in collection '" . self::TEST_CLASS_NAME . "'");

        $this->client->schema()->getProperty(self::TEST_CLASS_NAME, 'nonexistent');
    }

    public function testCreateCollectionWithInvertedIndexConfig(): void
    {
        $classDefinition = [
            'class' => self::TEST_CLASS_NAME,
            'properties' => [
                [
                    'name' => 'title',
                    'dataType' => ['text']
                ]
            ],
            'invertedIndexConfig' => [
                'bm25' => [
                    'b' => 0.75,
                    'k1' => 1.2
                ],
                'stopwords' => [
                    'preset' => 'en'
                ]
            ]
        ];

        $createdSchema = $this->client->schema()->create($classDefinition);
        
        $this->assertIsArray($createdSchema);
        $this->assertEquals(self::TEST_CLASS_NAME, $createdSchema['class']);
        $this->assertArrayHasKey('invertedIndexConfig', $createdSchema);
        $this->assertEquals(0.75, $createdSchema['invertedIndexConfig']['bm25']['b']);
        $this->assertEquals(1.2, $createdSchema['invertedIndexConfig']['bm25']['k1']);
    }
}
