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

namespace Weaviate\Tests\Integration\Factory;

use Weaviate\Auth\ApiKey;
use Weaviate\Factory\WeaviateClientFactory;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;

class WeaviateCustomIntegrationTest extends TestCase
{
    /**
     * Test connecting to local Weaviate using connectToCustom
     *
     */
    public function testConnectToCustomWithLocalWeaviate(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $secure = $url['scheme'] === 'https';

        $client = WeaviateClientFactory::connectToCustom($host, $port, $secure);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());

        // Test that we can make API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * Test connecting with authentication
     *
     */
    public function testConnectToCustomWithAuth(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $secure = $url['scheme'] === 'https';

        $apiKey = $this->getWeaviateApiKey();
        if (empty($apiKey)) {
            $this->markTestSkipped('No API key provided for auth test');
        }

        $auth = new ApiKey($apiKey);
        $client = WeaviateClientFactory::connectToCustom($host, $port, $secure, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());

        // Test that we can make API calls with auth
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * Test connecting with custom headers
     *
     */
    public function testConnectToCustomWithHeaders(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $secure = $url['scheme'] === 'https';

        $headers = [
            'X-Custom-Header' => 'test-value',
            'X-Another-Header' => 'another-value',
        ];

        $client = WeaviateClientFactory::connectToCustom($host, $port, $secure, null, $headers);

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that we can make API calls (headers are applied internally)
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    /**
     * Test end-to-end workflow with connectToCustom
     *
     */
    public function testConnectToCustomEndToEndWorkflow(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $secure = $url['scheme'] === 'https';

        $client = WeaviateClientFactory::connectToCustom($host, $port, $secure);
        $testCollectionName = 'TestCustomConnect_' . uniqid();

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
     * Test different parameter combinations
     *
     */
    public function testConnectToCustomParameterCombinations(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $secure = $url['scheme'] === 'https';

        // Test with minimal parameters
        $client1 = WeaviateClientFactory::connectToCustom($host, $port, $secure);
        $this->assertInstanceOf(WeaviateClient::class, $client1);

        // Test with auth
        $auth = new ApiKey('test-key');
        $client2 = WeaviateClientFactory::connectToCustom($host, $port, $secure, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client2);
        $this->assertSame($auth, $client2->getAuth());

        // Test with headers
        $headers = ['X-Test' => 'value'];
        $client3 = WeaviateClientFactory::connectToCustom($host, $port, $secure, null, $headers);
        $this->assertInstanceOf(WeaviateClient::class, $client3);

        // Test with both auth and headers
        $client4 = WeaviateClientFactory::connectToCustom($host, $port, $secure, $auth, $headers);
        $this->assertInstanceOf(WeaviateClient::class, $client4);
        $this->assertSame($auth, $client4->getAuth());

        // All should be able to make basic API calls
        $this->assertFalse($client1->collections()->exists('NonExistentCollection'));
        // Skip auth tests for client2 since we don't have real auth
        $this->assertFalse($client3->collections()->exists('NonExistentCollection'));
        // Skip auth tests for client4 since we don't have real auth
    }

    /**
     * Test HTTPS vs HTTP scheme handling
     *
     */
    public function testConnectToCustomSchemeHandling(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'], $url['scheme'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'];
        $port = $url['port'];
        $isHttps = $url['scheme'] === 'https';

        // Test with correct scheme
        $client = WeaviateClientFactory::connectToCustom($host, $port, $isHttps);
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Should be able to make API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }
}
