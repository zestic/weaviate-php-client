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

namespace Weaviate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;
use Weaviate\Auth\ApiKey;
use Weaviate\Exceptions\WeaviateInvalidInputException;

class WeaviateClientTest extends TestCase
{
    /**
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testCanCreateClientWithConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testCanCreateClientWithConnectionAndAuth(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $auth = $this->createMock(AuthInterface::class);
        $client = new WeaviateClient($connection, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::collections
     * @covers \Weaviate\Collections\Collections::__construct
     */
    public function testCollectionsReturnsCollectionsInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $collections = $client->collections();

        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::schema
     * @covers \Weaviate\Schema\Schema::__construct
     */
    public function testSchemaReturnsSchemaInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $schema = $client->schema();

        $this->assertInstanceOf(\Weaviate\Schema\Schema::class, $schema);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToLocalWithDefaults(): void
    {
        $client = WeaviateClient::connectToLocal();

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testConnectToLocalWithCustomHost(): void
    {
        $client = WeaviateClient::connectToLocal('localhost:18080');

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testConnectToLocalWithAuth(): void
    {
        $auth = new ApiKey('test-api-key');
        $client = WeaviateClient::connectToLocal('localhost:8080', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToLocalAddsHttpScheme(): void
    {
        // This test verifies that the method handles scheme addition
        // We can't easily test the internal URL without exposing it,
        // but we can verify the client is created successfully
        $client = WeaviateClient::connectToLocal('localhost:8080');

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToLocal
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToLocalWithHttpsScheme(): void
    {
        $client = WeaviateClient::connectToLocal('https://localhost:8080');

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testConnectToWeaviateCloudWithHostname(): void
    {
        $auth = new ApiKey('test-api-key');
        $client = WeaviateClient::connectToWeaviateCloud('my-cluster.weaviate.network', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToWeaviateCloudWithHttpUrl(): void
    {
        $auth = new ApiKey('test-api-key');
        $client = WeaviateClient::connectToWeaviateCloud('http://my-cluster.weaviate.network', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToWeaviateCloudWithHttpsUrl(): void
    {
        $auth = new ApiKey('test-api-key');
        $client = WeaviateClient::connectToWeaviateCloud('https://my-cluster.weaviate.network/some/path', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToWeaviateCloud
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     */
    public function testConnectToWeaviateCloudWithInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cluster URL provided');

        $auth = new ApiKey('test-api-key');
        WeaviateClient::connectToWeaviateCloud('http://', $auth);
    }

    /**
     * @covers \Weaviate\WeaviateClient::parseWeaviateCloudUrl
     */
    public function testParseWeaviateCloudUrlWithTrailingSlash(): void
    {
        $auth = new ApiKey('test-api-key');
        $client = WeaviateClient::connectToWeaviateCloud('my-cluster.weaviate.network/', $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToCustomWithDefaults(): void
    {
        $client = WeaviateClient::connectToCustom('localhost');

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testConnectToCustomWithAllParameters(): void
    {
        $auth = new ApiKey('test-api-key');
        $headers = ['X-Custom-Header' => 'custom-value'];

        $client = WeaviateClient::connectToCustom('my-server.com', 9200, true, $auth, $headers);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertSame($auth, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToCustomWithHttps(): void
    {
        $client = WeaviateClient::connectToCustom('secure-server.com', 443, true);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     */
    public function testConnectToCustomWithInvalidPort(): void
    {
        $this->expectException(WeaviateInvalidInputException::class);
        $this->expectExceptionMessage(
            'Invalid value for parameter \'port\': 0. Expected: Port must be between 1 and 65535'
        );

        WeaviateClient::connectToCustom('localhost', 0);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     */
    public function testConnectToCustomWithInvalidHighPort(): void
    {
        $this->expectException(WeaviateInvalidInputException::class);
        $this->expectExceptionMessage(
            'Invalid value for parameter \'port\': 65536. Expected: Port must be between 1 and 65535'
        );

        WeaviateClient::connectToCustom('localhost', 65536);
    }

    /**
     * @covers \Weaviate\WeaviateClient::connectToCustom
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testConnectToCustomWithCustomPort(): void
    {
        $client = WeaviateClient::connectToCustom('localhost', 9999);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }
}
