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

require_once __DIR__ . '/../vendor/autoload.php';

use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

/**
 * Schema Management Example
 * 
 * This example demonstrates comprehensive schema management capabilities
 * including collection creation, property management, and schema validation.
 */

echo "=== Weaviate PHP Client - Schema Management Example ===\n\n";

try {
    // Connect to local Weaviate instance
    $client = WeaviateClient::connectToLocal('localhost:18080');
    $schema = $client->schema();
    
    echo "Connected to Weaviate successfully!\n\n";

    // Collection name for this example
    $collectionName = 'BlogPost';

    // Clean up any existing collection
    if ($schema->exists($collectionName)) {
        echo "Cleaning up existing '{$collectionName}' collection...\n";
        $schema->delete($collectionName);
    }

    // 1. Create a comprehensive collection
    echo "1. Creating '{$collectionName}' collection with comprehensive schema...\n";
    
    $collectionDefinition = [
        'class' => $collectionName,
        'description' => 'A collection for storing blog posts with rich metadata',
        'properties' => [
            [
                'name' => 'title',
                'dataType' => ['text'],
                'description' => 'Blog post title',
                'tokenization' => 'word'
            ],
            [
                'name' => 'content',
                'dataType' => ['text'],
                'description' => 'Blog post content',
                'tokenization' => 'word'
            ],
            [
                'name' => 'summary',
                'dataType' => ['text'],
                'description' => 'Brief summary of the post'
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
                'description' => 'Average rating (0.0 to 5.0)'
            ],
            [
                'name' => 'isPublished',
                'dataType' => ['boolean'],
                'description' => 'Publication status'
            ],
            [
                'name' => 'tags',
                'dataType' => ['text[]'],
                'description' => 'Post tags'
            ],
            [
                'name' => 'postId',
                'dataType' => ['uuid'],
                'description' => 'Unique post identifier'
            ]
        ],
        'vectorizer' => 'none', // Use 'none' to avoid requiring external vectorizer
        'multiTenancyConfig' => [
            'enabled' => false
        ]
    ];

    $createdCollection = $schema->create($collectionDefinition);
    echo "✅ Collection created successfully!\n";
    echo "   Class: {$createdCollection['class']}\n";
    echo "   Properties: " . count($createdCollection['properties']) . "\n\n";

    // 2. Verify collection exists
    echo "2. Verifying collection exists...\n";
    $exists = $schema->exists($collectionName);
    echo $exists ? "✅ Collection exists!\n\n" : "❌ Collection not found!\n\n";

    // 3. Get complete schema
    echo "3. Retrieving complete schema...\n";
    $completeSchema = $schema->get();
    $classCount = count($completeSchema['classes'] ?? []);
    echo "✅ Retrieved complete schema with {$classCount} collections\n\n";

    // 4. Get specific collection schema
    echo "4. Retrieving specific collection schema...\n";
    $collectionSchema = $schema->get($collectionName);
    echo "✅ Retrieved '{$collectionName}' schema\n";
    echo "   Description: {$collectionSchema['description']}\n";
    echo "   Properties: " . count($collectionSchema['properties']) . "\n";
    
    // List all properties
    echo "   Property details:\n";
    foreach ($collectionSchema['properties'] as $property) {
        $dataTypes = implode(', ', $property['dataType']);
        echo "     - {$property['name']} ({$dataTypes}): {$property['description']}\n";
    }
    echo "\n";

    // 5. Add new properties
    echo "5. Adding new properties to the collection...\n";
    
    // Add author property
    $authorProperty = [
        'name' => 'author',
        'dataType' => ['text'],
        'description' => 'Blog post author'
    ];
    
    $updatedSchema = $schema->addProperty($collectionName, $authorProperty);
    echo "✅ Added 'author' property\n";
    
    // Add category property
    $categoryProperty = [
        'name' => 'category',
        'dataType' => ['text'],
        'description' => 'Post category'
    ];
    
    $schema->addProperty($collectionName, $categoryProperty);
    echo "✅ Added 'category' property\n";
    
    // Add featured flag
    $featuredProperty = [
        'name' => 'isFeatured',
        'dataType' => ['boolean'],
        'description' => 'Whether the post is featured'
    ];
    
    $schema->addProperty($collectionName, $featuredProperty);
    echo "✅ Added 'isFeatured' property\n\n";

    // 6. Get specific property
    echo "6. Retrieving specific property details...\n";
    $authorPropertyDetails = $schema->getProperty($collectionName, 'author');
    echo "✅ Retrieved 'author' property:\n";
    echo "   Name: {$authorPropertyDetails['name']}\n";
    echo "   Data Type: " . implode(', ', $authorPropertyDetails['dataType']) . "\n";
    echo "   Description: {$authorPropertyDetails['description']}\n\n";

    // 7. Verify final schema
    echo "7. Verifying final collection schema...\n";
    $finalSchema = $schema->get($collectionName);
    echo "✅ Final schema verification:\n";
    echo "   Total properties: " . count($finalSchema['properties']) . "\n";
    echo "   Properties:\n";
    foreach ($finalSchema['properties'] as $property) {
        echo "     - {$property['name']}\n";
    }
    echo "\n";

    // 8. Demonstrate data type validation
    echo "8. Demonstrating data type validation...\n";
    
    try {
        // This should fail due to invalid data type
        $invalidProperty = [
            'name' => 'invalidProp',
            'dataType' => ['invalid_type'],
            'description' => 'This should fail'
        ];
        
        $schema->addProperty($collectionName, $invalidProperty);
        echo "❌ Validation failed - invalid property was accepted!\n";
    } catch (InvalidArgumentException $e) {
        echo "✅ Validation working correctly: {$e->getMessage()}\n";
    }
    
    try {
        // This should fail due to missing name
        $invalidProperty = [
            'dataType' => ['text'],
            'description' => 'Missing name field'
        ];
        
        $schema->addProperty($collectionName, $invalidProperty);
        echo "❌ Validation failed - property without name was accepted!\n";
    } catch (InvalidArgumentException $e) {
        echo "✅ Validation working correctly: {$e->getMessage()}\n";
    }
    echo "\n";

    // 9. Create collection with advanced features
    echo "9. Creating collection with advanced features...\n";
    
    $advancedCollectionName = 'AdvancedBlog';
    
    // Clean up if exists
    if ($schema->exists($advancedCollectionName)) {
        $schema->delete($advancedCollectionName);
    }
    
    $advancedDefinition = [
        'class' => $advancedCollectionName,
        'description' => 'Advanced blog collection with multi-tenancy and custom index settings',
        'properties' => [
            [
                'name' => 'title',
                'dataType' => ['text'],
                'description' => 'Post title',
                'tokenization' => 'lowercase'
            ],
            [
                'name' => 'content',
                'dataType' => ['text'],
                'description' => 'Post content'
            ]
        ],
        'vectorizer' => 'none',
        'multiTenancyConfig' => [
            'enabled' => true
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
    
    $advancedCollection = $schema->create($advancedDefinition);
    echo "✅ Created advanced collection with:\n";
    echo "   - Multi-tenancy enabled: " . ($advancedCollection['multiTenancyConfig']['enabled'] ? 'Yes' : 'No') . "\n";
    echo "   - Custom BM25 settings configured\n";
    echo "   - English stopwords preset\n\n";

    // 10. Clean up
    echo "10. Cleaning up test collections...\n";
    $schema->delete($collectionName);
    echo "✅ Deleted '{$collectionName}' collection\n";
    
    $schema->delete($advancedCollectionName);
    echo "✅ Deleted '{$advancedCollectionName}' collection\n\n";

    echo "=== Schema Management Example Completed Successfully! ===\n";
    echo "\nKey features demonstrated:\n";
    echo "✅ Collection creation with comprehensive properties\n";
    echo "✅ Schema existence checking\n";
    echo "✅ Complete and specific schema retrieval\n";
    echo "✅ Dynamic property addition\n";
    echo "✅ Property-specific retrieval\n";
    echo "✅ Data type validation\n";
    echo "✅ Advanced collection features (multi-tenancy, inverted index)\n";
    echo "✅ Proper cleanup and error handling\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Make sure Weaviate is running at http://localhost:18080\n";
    echo "You can start it with: docker run -p 18080:8080 -p 50051:50051 cr.weaviate.io/semitechnologies/weaviate:1.31.0\n";
}
