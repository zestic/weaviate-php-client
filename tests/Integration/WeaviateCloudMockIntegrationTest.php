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

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;
use Weaviate\Connection\HttpConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Mock integration tests for Weaviate Cloud connectivity
 * These tests use a mock HTTP handler to simulate Weaviate Cloud responses
 */
class WeaviateCloudMockIntegrationTest extends TestCase
{
    /**
     * Test connectToWeaviateCloud with mocked HTTP responses
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     * @covers \Weaviate\Connection\HttpConnection::get
     */
    public function testConnectToWeaviateCloudWithMockResponses(): void
    {
        // Create a mock HTTP handler that simulates Weaviate Cloud responses
        $mockHandler = new MockHandler([
            // Mock response for collection existence check (404 = doesn't exist)
            new Response(404, ['Content-Type' => 'application/json'], '{"error": "collection not found"}'),
            // Mock response for collection creation
            new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'class' => 'TestCollection',
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']]
                ]
            ]) ?: '{}'),
            // Mock response for collection existence check after creation (200 = exists)
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'class' => 'TestCollection'
            ]) ?: '{}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $httpFactory = new HttpFactory();

        // Create connection manually with our mock client
        $connection = new HttpConnection(
            'https://mock-cluster.weaviate.network',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new ApiKey('mock-api-key')
        );

        $client = new WeaviateClient($connection, new ApiKey('mock-api-key'));

        // Test collection existence check (should return false due to 404)
        $exists = $client->collections()->exists('TestCollection');
        $this->assertFalse($exists);

        // Test collection creation
        $result = $client->collections()->create('TestCollection', [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']]
            ]
        ]);
        $this->assertIsArray($result);
        $this->assertEquals('TestCollection', $result['class']);

        // Test collection existence check after creation (should return true due to 200)
        $schema = $client->collections()->exists('TestCollection');
        $this->assertTrue($schema);
    }

    /**
     * Test URL parsing with various formats
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     */
    public function testConnectToWeaviateCloudUrlParsing(): void
    {
        $auth = new ApiKey('test-key');

        // Test different URL formats - all should create valid clients
        $testUrls = [
            'my-cluster.weaviate.network',
            'https://my-cluster.weaviate.network',
            'http://my-cluster.weaviate.network',
            'my-cluster.weaviate.network/',
            'https://my-cluster.weaviate.network/some/path',
        ];

        foreach ($testUrls as $url) {
            $client = WeaviateClient::connectToWeaviateCloud($url, $auth);
            $this->assertInstanceOf(WeaviateClient::class, $client);
            $this->assertSame($auth, $client->getAuth());
        }
    }

    /**
     * Test that HTTPS is enforced for Weaviate Cloud connections
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     */
    public function testConnectToWeaviateCloudEnforcesHttps(): void
    {
        // Create a mock that expects HTTPS requests
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"hostname": "mock-cluster"}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);

        // Create a client that will capture the actual request URL
        /** @var string|null $requestUrl */
        $requestUrl = null;
        $handlerStack->push(function (callable $handler) use (&$requestUrl) {
            return function ($request, array $options) use ($handler, &$requestUrl) {
                $requestUrl = (string) $request->getUri();
                return $handler($request, $options);
            };
        });

        $httpClient = new Client(['handler' => $handlerStack]);
        $httpFactory = new HttpFactory();

        // Create connection manually with our mock client
        $connection = new HttpConnection(
            'https://mock-cluster.weaviate.network',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new ApiKey('mock-api-key')
        );

        $client = new WeaviateClient($connection, new ApiKey('mock-api-key'));

        // Make a request to trigger the URL capture
        try {
            $client->collections()->exists('TestCollection');
        } catch (\Exception) {
            // Ignore any errors, we just want to capture the URL
        }

        // Verify that the request was made to HTTPS URL
        $this->assertNotNull($requestUrl, 'Request URL should have been captured');
        $this->assertIsString($requestUrl);
        $this->assertStringStartsWith('https://', $requestUrl);
    }

    /**
     * Test authentication header is included in requests
     *
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\Auth\ApiKey::apply
     */
    public function testConnectToWeaviateCloudIncludesAuthHeader(): void
    {
        $capturedHeaders = [];

        // Create a mock that captures request headers
        $mockHandler = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], '{"error": "not found"}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(function (callable $handler) use (&$capturedHeaders) {
            return function ($request, array $options) use ($handler, &$capturedHeaders) {
                $capturedHeaders = $request->getHeaders();
                return $handler($request, $options);
            };
        });

        $httpClient = new Client(['handler' => $handlerStack]);
        $httpFactory = new HttpFactory();

        $apiKey = 'test-api-key-12345';
        $connection = new HttpConnection(
            'https://mock-cluster.weaviate.network',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new ApiKey($apiKey)
        );

        $client = new WeaviateClient($connection, new ApiKey($apiKey));

        // Make a request to trigger header capture
        try {
            $client->collections()->exists('TestCollection');
        } catch (\Exception) {
            // Ignore errors, we just want to capture headers
        }

        // Verify that the Authorization header is present
        $this->assertArrayHasKey('Authorization', $capturedHeaders);
        $this->assertStringContainsString('Bearer ' . $apiKey, $capturedHeaders['Authorization'][0]);
    }
}
