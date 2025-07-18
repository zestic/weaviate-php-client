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
 * Mock integration tests for connectToCustom functionality
 * These tests use a mock HTTP handler to simulate custom server responses
 */
class WeaviateCustomMockIntegrationTest extends TestCase
{
    /**
     * Test connectToCustom with mocked HTTP responses
     *
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::applyHeaders
     */
    public function testConnectToCustomWithMockResponses(): void
    {
        // Create a mock HTTP handler
        $mockHandler = new MockHandler([
            // Mock response for collection existence check
            new Response(404, ['Content-Type' => 'application/json'], '{"error": "collection not found"}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $httpFactory = new HttpFactory();

        // Create connection manually with our mock client
        $connection = new HttpConnection(
            'https://mock-server.com:9200',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new ApiKey('mock-api-key'),
            ['X-Custom-Header' => 'custom-value']
        );

        $client = new WeaviateClient($connection, new ApiKey('mock-api-key'));

        // Test collection existence check (should return false due to 404)
        $exists = $client->collections()->exists('TestCollection');
        $this->assertFalse($exists);
    }

    /**
     * Test URL construction for different schemes and ports
     *
     * @covers \Weaviate\WeaviateClient::connectToCustom
     */
    public function testConnectToCustomUrlConstruction(): void
    {
        $auth = new ApiKey('test-key');

        // Test HTTP with default port
        $client1 = WeaviateClient::connectToCustom('localhost', 8080, false, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client1);

        // Test HTTPS with custom port
        $client2 = WeaviateClient::connectToCustom('secure-server.com', 9200, true, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client2);

        // Test with custom domain
        $client3 = WeaviateClient::connectToCustom('my-weaviate.example.com', 443, true, $auth);
        $this->assertInstanceOf(WeaviateClient::class, $client3);
    }

    /**
     * Test that custom headers are included in requests
     *
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\Connection\HttpConnection::applyHeaders
     */
    public function testConnectToCustomIncludesCustomHeaders(): void
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

        $customHeaders = [
            'X-Custom-Header' => 'custom-value',
            'X-API-Version' => 'v2',
            'X-Client-ID' => 'php-client-test',
        ];

        $connection = new HttpConnection(
            'https://mock-server.com:9200',
            $httpClient,
            $httpFactory,
            $httpFactory,
            new ApiKey('mock-api-key'),
            $customHeaders
        );

        $client = new WeaviateClient($connection, new ApiKey('mock-api-key'));

        // Make a request to trigger header capture
        try {
            $client->collections()->exists('TestCollection');
        } catch (\Exception) {
            // Ignore errors, we just want to capture headers
        }

        // Verify that custom headers are present
        $this->assertArrayHasKey('X-Custom-Header', $capturedHeaders);
        $this->assertStringContainsString('custom-value', $capturedHeaders['X-Custom-Header'][0]);

        $this->assertArrayHasKey('X-API-Version', $capturedHeaders);
        $this->assertStringContainsString('v2', $capturedHeaders['X-API-Version'][0]);

        $this->assertArrayHasKey('X-Client-ID', $capturedHeaders);
        $this->assertStringContainsString('php-client-test', $capturedHeaders['X-Client-ID'][0]);

        // Verify that authentication header is also present
        $this->assertArrayHasKey('Authorization', $capturedHeaders);
        $this->assertStringContainsString('Bearer mock-api-key', $capturedHeaders['Authorization'][0]);
    }

    /**
     * Test port validation
     *
     * @covers \Weaviate\WeaviateClient::connectToCustom
     */
    public function testConnectToCustomPortValidation(): void
    {
        $auth = new ApiKey('test-key');

        // Test valid ports
        $validPorts = [1, 80, 443, 8080, 9200, 65535];
        foreach ($validPorts as $port) {
            $client = WeaviateClient::connectToCustom('localhost', $port, false, $auth);
            $this->assertInstanceOf(WeaviateClient::class, $client);
        }

        // Test invalid ports
        $invalidPorts = [0, -1, 65536, 100000];
        foreach ($invalidPorts as $port) {
            $this->expectException(\Weaviate\Exceptions\WeaviateInvalidInputException::class);
            $this->expectExceptionMessage('Port must be between 1 and 65535');
            WeaviateClient::connectToCustom('localhost', $port, false, $auth);
        }
    }

    /**
     * Test scheme handling (HTTP vs HTTPS)
     *
     * @covers \Weaviate\WeaviateClient::connectToCustom
     */
    public function testConnectToCustomSchemeHandling(): void
    {
        $capturedUrls = [];

        // Create a mock that captures request URLs
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"hostname": "mock-server"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"hostname": "mock-server"}'),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(function (callable $handler) use (&$capturedUrls) {
            return function ($request, array $options) use ($handler, &$capturedUrls) {
                $capturedUrls[] = (string) $request->getUri();
                return $handler($request, $options);
            };
        });

        $httpClient = new Client(['handler' => $handlerStack]);
        $httpFactory = new HttpFactory();

        // Test HTTP
        $httpConnection = new HttpConnection(
            'http://mock-server.com:8080',
            $httpClient,
            $httpFactory,
            $httpFactory,
            null,
            []
        );
        $httpClient1 = new WeaviateClient($httpConnection);

        // Test HTTPS
        $httpsConnection = new HttpConnection(
            'https://mock-server.com:443',
            $httpClient,
            $httpFactory,
            $httpFactory,
            null,
            []
        );
        $httpsClient = new WeaviateClient($httpsConnection);

        // Make requests to capture URLs
        try {
            $httpClient1->collections()->exists('TestCollection');
            $httpsClient->collections()->exists('TestCollection');
        } catch (\Exception) {
            // Ignore errors, we just want to capture URLs
        }

        // Verify schemes
        $this->assertCount(2, $capturedUrls);
        $this->assertStringStartsWith('http://mock-server.com:8080', $capturedUrls[0]);
        $this->assertStringStartsWith('https://mock-server.com', $capturedUrls[1]);
    }
}
