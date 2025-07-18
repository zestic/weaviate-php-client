<?php

declare(strict_types=1);

/*
 * Copyright 2024 Zestic
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

namespace Weaviate\Tests\Integration\Connection;

use Weaviate\Tests\TestCase;
use Weaviate\Connection\HttpConnection;
use Weaviate\Exceptions\NotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class HttpConnectionIntegrationTest extends TestCase
{
    private HttpConnection $connection;

    protected function setUp(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection
        $this->connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory,
            null,
            []
        );
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testCanMakeGetRequestToMeta(): void
    {
        $result = $this->connection->get('/v1/meta');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hostname', $result);
        $this->assertArrayHasKey('version', $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testCanMakeGetRequestToSchema(): void
    {
        $result = $this->connection->get('/v1/schema');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('classes', $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestWithQueryParameters(): void
    {
        // Test with query parameters - using nodes endpoint which accepts parameters
        $result = $this->connection->get('/v1/nodes', ['output' => 'minimal']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testGetRequestThrowsNotFoundExceptionFor404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->connection->get('/v1/non-existent-endpoint');
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::post
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::delete
     */
    public function testCanCreateAndDeleteCollection(): void
    {
        $collectionName = 'TestHttpConnection';

        // Clean up any existing collection first
        try {
            $this->connection->delete("/v1/schema/{$collectionName}");
        } catch (NotFoundException) {
            // Collection doesn't exist, which is fine
        }

        // Create collection using POST
        $createData = [
            'class' => $collectionName,
            'properties' => [
                [
                    'name' => 'testProperty',
                    'dataType' => ['text']
                ]
            ]
        ];

        $result = $this->connection->post('/v1/schema', $createData);

        $this->assertIsArray($result);
        $this->assertEquals($collectionName, $result['class']);
        $this->assertArrayHasKey('properties', $result);

        // Verify collection exists using GET
        $schema = $this->connection->get("/v1/schema/{$collectionName}");
        $this->assertEquals($collectionName, $schema['class']);

        // Clean up - delete the collection
        $deleteResult = $this->connection->delete("/v1/schema/{$collectionName}");
        $this->assertTrue($deleteResult);

        // Verify collection is deleted
        $this->expectException(NotFoundException::class);
        $this->connection->get("/v1/schema/{$collectionName}");
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::post
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::patch
     * @covers \Weaviate\Connection\HttpConnection::delete
     */
    public function testCanCreateUpdateAndDeleteObject(): void
    {
        $collectionName = 'TestHttpConnectionObjects';
        $objectId = '123e4567-e89b-12d3-a456-426614174000';

        // Clean up any existing collection first
        try {
            $this->connection->delete("/v1/schema/{$collectionName}");
        } catch (NotFoundException) {
            // Collection doesn't exist, which is fine
        }

        // Create collection first
        $createCollectionData = [
            'class' => $collectionName,
            'properties' => [
                [
                    'name' => 'name',
                    'dataType' => ['text']
                ],
                [
                    'name' => 'value',
                    'dataType' => ['int']
                ]
            ]
        ];

        $this->connection->post('/v1/schema', $createCollectionData);

        // Create object using POST
        $createObjectData = [
            'class' => $collectionName,
            'id' => $objectId,
            'properties' => [
                'name' => 'Test Object',
                'value' => 42
            ]
        ];

        $result = $this->connection->post('/v1/objects', $createObjectData);
        $this->assertEquals($objectId, $result['id']);
        $this->assertEquals($collectionName, $result['class']);

        // Get object using GET
        $retrievedObject = $this->connection->get("/v1/objects/{$collectionName}/{$objectId}");
        $this->assertEquals($objectId, $retrievedObject['id']);
        $this->assertEquals('Test Object', $retrievedObject['properties']['name']);
        $this->assertEquals(42, $retrievedObject['properties']['value']);

        // Update object using PATCH
        $updateData = [
            'properties' => [
                'name' => 'Updated Object',
                'value' => 84
            ]
        ];

        $this->connection->patch("/v1/objects/{$collectionName}/{$objectId}", $updateData);

        // PATCH may return empty response on success, so let's verify by fetching the object
        $updatedObject = $this->connection->get("/v1/objects/{$collectionName}/{$objectId}");
        $this->assertEquals('Updated Object', $updatedObject['properties']['name']);
        $this->assertEquals(84, $updatedObject['properties']['value']);

        // Delete object using DELETE
        $deleteResult = $this->connection->delete("/v1/objects/{$collectionName}/{$objectId}");
        $this->assertTrue($deleteResult);

        // Verify object is deleted
        $this->expectException(NotFoundException::class);
        $this->connection->get("/v1/objects/{$collectionName}/{$objectId}");
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::put
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::delete
     */
    public function testCanUpdateObjectWithPut(): void
    {
        $collectionName = 'TestHttpConnectionPut';
        $objectId = '456e7890-e89b-12d3-a456-426614174001';

        // Clean up any existing collection first
        try {
            $this->connection->delete("/v1/schema/{$collectionName}");
        } catch (NotFoundException) {
            // Collection doesn't exist, which is fine
        }

        // Create collection first
        $createCollectionData = [
            'class' => $collectionName,
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
        ];

        $this->connection->post('/v1/schema', $createCollectionData);

        // Create object using POST
        $createObjectData = [
            'class' => $collectionName,
            'id' => $objectId,
            'properties' => [
                'title' => 'Original Title',
                'description' => 'Original Description'
            ]
        ];

        $this->connection->post('/v1/objects', $createObjectData);

        // Update object using PUT (full replacement)
        // Note: PUT requests require id and class in the request body
        $updateData = [
            'id' => $objectId,
            'class' => $collectionName,
            'properties' => [
                'title' => 'Updated Title via PUT',
                'description' => 'Updated Description via PUT'
            ]
        ];

        $result = $this->connection->put("/v1/objects/{$collectionName}/{$objectId}", $updateData);

        // PUT should return the updated object
        $this->assertIsArray($result);
        $this->assertEquals($objectId, $result['id']);

        // Verify the update by fetching the object
        $updatedObject = $this->connection->get("/v1/objects/{$collectionName}/{$objectId}");
        $this->assertEquals('Updated Title via PUT', $updatedObject['properties']['title']);
        $this->assertEquals('Updated Description via PUT', $updatedObject['properties']['description']);

        // Clean up
        $this->connection->delete("/v1/schema/{$collectionName}");
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::head
     */
    public function testCanMakeHeadRequest(): void
    {
        // Test HEAD request to ready endpoint (should exist and support HEAD)
        $result = $this->connection->head('/v1/.well-known/ready');
        $this->assertTrue($result);

        // Test HEAD request to non-existent endpoint
        $result = $this->connection->head('/v1/non-existent-endpoint');
        $this->assertFalse($result);
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::deleteWithData
     */
    public function testCanDeleteWithData(): void
    {
        $collectionName = 'TestHttpConnectionDeleteWithData';

        // Clean up any existing collection first
        try {
            $this->connection->delete("/v1/schema/{$collectionName}");
        } catch (NotFoundException) {
            // Collection doesn't exist, which is fine
        }

        // Create collection first
        $createCollectionData = [
            'class' => $collectionName,
            'properties' => [
                [
                    'name' => 'category',
                    'dataType' => ['text']
                ],
                [
                    'name' => 'value',
                    'dataType' => ['int']
                ]
            ]
        ];

        $this->connection->post('/v1/schema', $createCollectionData);

        // Create multiple objects
        $objects = [
            [
                'class' => $collectionName,
                'properties' => ['category' => 'test', 'value' => 1]
            ],
            [
                'class' => $collectionName,
                'properties' => ['category' => 'test', 'value' => 2]
            ],
            [
                'class' => $collectionName,
                'properties' => ['category' => 'keep', 'value' => 3]
            ]
        ];

        foreach ($objects as $object) {
            $this->connection->post('/v1/objects', $object);
        }

        // Delete objects with specific criteria using deleteWithData
        $deleteData = [
            'match' => [
                'class' => $collectionName,
                'where' => [
                    'path' => ['category'],
                    'operator' => 'Equal',
                    'valueText' => 'test'
                ]
            ]
        ];

        $result = $this->connection->deleteWithData('/v1/batch/objects', $deleteData);
        $this->assertTrue($result);

        // Clean up
        $this->connection->delete("/v1/schema/{$collectionName}");
    }

    protected function tearDown(): void
    {
        // Clean up any test collections that might have been left behind
        $testCollections = ['TestHttpConnection', 'TestHttpConnectionObjects'];

        foreach ($testCollections as $collection) {
            try {
                $this->connection->delete("/v1/schema/{$collection}");
            } catch (NotFoundException) {
                // Collection doesn't exist, which is fine
            }
        }
    }
}
