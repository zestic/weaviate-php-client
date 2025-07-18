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

class WeaviateClientIntegrationTest extends TestCase
{
    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     */
    public function testConnectToLocalCanConnectToWeaviate(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClient::connectToLocal($host);

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that we can actually use the client
        $collections = $client->collections();
        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     * @covers \Weaviate\Collections\Collections::exists
     */
    public function testConnectToLocalCanMakeApiCalls(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClient::connectToLocal($host);

        // Test that we can make actual API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testConnectToLocalWithAuth(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        $host = $url['host'] . ':' . $url['port'];

        $apiKey = $this->getWeaviateApiKey();
        if (empty($apiKey)) {
            $this->markTestSkipped('No API key provided for auth test');
        }

        $auth = new ApiKey($apiKey);
        $client = WeaviateClient::connectToLocal($host, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());

        // Test that we can make API calls with auth
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToLocalWithDefaultHost(): void
    {
        // This test will only work if Weaviate is running on default port 8080
        // We'll skip if it's not available
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents('http://localhost:8080/v1/.well-known/ready', false, $context);

        if ($result === false) {
            $this->markTestSkipped('Weaviate is not available on default port localhost:8080');
        }

        $client = WeaviateClient::connectToLocal();

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that we can make API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     * @covers \Weaviate\Collections\Collections::create
     * @covers \Weaviate\Collections\Collections::exists
     * @covers \Weaviate\Collections\Collections::delete
     */
    public function testConnectToLocalEndToEndWorkflow(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClient::connectToLocal($host);

        $testCollectionName = 'TestConnectToLocal_' . uniqid();

        try {
            // Ensure collection doesn't exist
            $this->assertFalse($client->collections()->exists($testCollectionName));

            // Create collection
            $result = $client->collections()->create($testCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
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
}
