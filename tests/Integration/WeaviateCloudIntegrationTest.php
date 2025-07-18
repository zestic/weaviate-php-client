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
use Weaviate\Auth\ApiKey;

class WeaviateCloudIntegrationTest extends TestCase
{
    /**
     * Test connecting to a real Weaviate Cloud instance
     * This test only runs when WEAVIATE_CLOUD_URL and WEAVIATE_CLOUD_API_KEY are set
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     * @covers \Weaviate\WeaviateClient::collections
     */
    public function testConnectToWeaviateCloudReal(): void
    {
        $clusterUrl = $_ENV['WEAVIATE_CLOUD_URL'] ?? '';
        $apiKey = $_ENV['WEAVIATE_CLOUD_API_KEY'] ?? '';

        if (empty($clusterUrl) || empty($apiKey)) {
            $this->markTestSkipped(
                'Weaviate Cloud credentials not provided. ' .
                'Set WEAVIATE_CLOUD_URL and WEAVIATE_CLOUD_API_KEY environment variables to run this test.'
            );
        }

        $client = WeaviateClient::connectToWeaviateCloud($clusterUrl, new ApiKey($apiKey));

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertInstanceOf(ApiKey::class, $client->getAuth());

        // Test that we can make API calls
        $collections = $client->collections();
        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);

        // Test a simple API call
        $exists = $collections->exists('NonExistentTestCollection');
        $this->assertFalse($exists);
    }

    /**
     * Test connecting to Weaviate Cloud with different URL formats
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     */
    public function testConnectToWeaviateCloudWithDifferentUrlFormats(): void
    {
        $clusterUrl = $_ENV['WEAVIATE_CLOUD_URL'] ?? '';
        $apiKey = $_ENV['WEAVIATE_CLOUD_API_KEY'] ?? '';

        if (empty($clusterUrl) || empty($apiKey)) {
            $this->markTestSkipped(
                'Weaviate Cloud credentials not provided. ' .
                'Set WEAVIATE_CLOUD_URL and WEAVIATE_CLOUD_API_KEY environment variables to run this test.'
            );
        }

        $auth = new ApiKey($apiKey);

        // Test with hostname only
        $client1 = WeaviateClient::connectToWeaviateCloud($clusterUrl, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client1);

        // Test with https:// prefix
        $client2 = WeaviateClient::connectToWeaviateCloud('https://' . $clusterUrl, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client2);

        // Test with trailing slash
        $client3 = WeaviateClient::connectToWeaviateCloud($clusterUrl . '/', $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client3);

        // All should be able to make API calls
        $this->assertFalse($client1->collections()->exists('NonExistentTestCollection'));
        $this->assertFalse($client2->collections()->exists('NonExistentTestCollection'));
        $this->assertFalse($client3->collections()->exists('NonExistentTestCollection'));
    }

    /**
     * Test end-to-end workflow with Weaviate Cloud
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\Collections\Collections::create
     * @covers \Weaviate\Collections\Collections::exists
     * @covers \Weaviate\Collections\Collections::delete
     */
    public function testConnectToWeaviateCloudEndToEndWorkflow(): void
    {
        $clusterUrl = $_ENV['WEAVIATE_CLOUD_URL'] ?? '';
        $apiKey = $_ENV['WEAVIATE_CLOUD_API_KEY'] ?? '';

        if (empty($clusterUrl) || empty($apiKey)) {
            $this->markTestSkipped(
                'Weaviate Cloud credentials not provided. ' .
                'Set WEAVIATE_CLOUD_URL and WEAVIATE_CLOUD_API_KEY environment variables to run this test.'
            );
        }

        $client = WeaviateClient::connectToWeaviateCloud($clusterUrl, new ApiKey($apiKey));
        $testCollectionName = 'TestWeaviateCloud_' . uniqid();

        try {
            // Ensure collection doesn't exist
            $this->assertFalse($client->collections()->exists($testCollectionName));

            // Create collection
            $result = $client->collections()->create($testCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                    ['name' => 'description', 'dataType' => ['text']],
                ],
            ]);

            $this->assertIsArray($result);
            $this->assertEquals($testCollectionName, $result['class']);

            // Verify collection exists
            $this->assertTrue($client->collections()->exists($testCollectionName));
        } finally {
            // Clean up - delete the test collection
            if ($client->collections()->exists($testCollectionName)) {
                $client->collections()->delete($testCollectionName);
            }
        }
    }

    /**
     * Test that connectToWeaviateCloud requires authentication
     * This is a mock test since we can't test auth failure without valid credentials
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     */
    public function testConnectToWeaviateCloudRequiresAuth(): void
    {
        // This test verifies that auth is required (not null)
        // We can't easily test auth failure without a real endpoint
        $auth = new ApiKey('required-api-key');
        $client = WeaviateClient::connectToWeaviateCloud('test-cluster.weaviate.network', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNotNull($client->getAuth());
        $this->assertInstanceOf(ApiKey::class, $client->getAuth());
    }
}
