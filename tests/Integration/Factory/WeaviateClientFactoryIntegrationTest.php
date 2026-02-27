<?php

declare(strict_types=1);

namespace Weaviate\Tests\Integration\Factory;

use Weaviate\Auth\ApiKey;
use Weaviate\Exceptions\WeaviateInvalidInputException;
use Weaviate\Factory\WeaviateClientFactory;
use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;

class WeaviateClientFactoryIntegrationTest extends TestCase
{
    public function testCreateLocalReturnsClient(): void
    {
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $host = 'localhost:18080';
        } else {
            $host = $url['host'] . ':' . $url['port'];
        }

        $client = WeaviateClientFactory::connectToLocal($host);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    public function testCreateLocalWithAuth(): void
    {
        $apiKey = $this->getWeaviateApiKey();
        if (empty($apiKey)) {
            $this->markTestSkipped('No API key provided for auth test');
        }

        $auth = new ApiKey($apiKey);
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClientFactory::connectToLocal($host, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    public function testCreateWeaviateCloudWithAuth(): void
    {
        $apiKey = $this->getWeaviateApiKey();
        if (empty($apiKey)) {
            $this->markTestSkipped('No API key provided for cloud test');
        }

        $auth = new ApiKey($apiKey);
        $client = WeaviateClientFactory::connectToWeaviateCloud(
            'https://example-cluster.weaviate.network/path/',
            $auth,
        );

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    public function testCreateCustomInvalidPortThrows(): void
    {
        $this->expectException(WeaviateInvalidInputException::class);
        WeaviateClientFactory::connectToCustom('example.com', 70000);
    }

    public function testConnectToLocalCanConnectToWeaviate(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClientFactory::connectToLocal($host);

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that we can actually use the client
        $collections = $client->collections();
        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    public function testConnectToLocalCanMakeApiCalls(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $client = WeaviateClientFactory::connectToLocal($host);

        // Test that we can make actual API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

    public function testConnectToLocalWithAuth(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Extract host and port from the Weaviate URL
        $url = parse_url($this->getWeaviateUrl());
        if ($url === false || !isset($url['host'], $url['port'])) {
            $this->fail('Invalid Weaviate URL');
        }
        $host = $url['host'] . ':' . $url['port'];

        $apiKey = $this->getWeaviateApiKey();
        if (empty($apiKey)) {
            $this->markTestSkipped('No API key provided for auth test');
        }

        $auth = new ApiKey($apiKey);
        $client = WeaviateClientFactory::connectToLocal($host, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());

        // Test that we can make API calls with auth
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }

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

        $client = WeaviateClientFactory::connectToLocal();

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that we can make API calls
        $exists = $client->collections()->exists('NonExistentCollection');
        $this->assertFalse($exists);
    }
}
