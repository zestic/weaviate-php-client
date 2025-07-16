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

namespace Weaviate\Tests\Integration\Auth;

use Weaviate\Tests\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\ApiKey;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class AuthenticationIntegrationTest extends TestCase
{
    /**
     * @covers \Weaviate\Auth\ApiKey::__construct
     * @covers \Weaviate\Auth\ApiKey::apply
     * @covers \Weaviate\WeaviateClient::__construct
     * @covers \Weaviate\WeaviateClient::getAuth
     */
    public function testCanCreateClientWithApiKeyAuth(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create auth
        $auth = new ApiKey($this->getWeaviateApiKey());

        // Create client with auth
        $client = new WeaviateClient($connection, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertInstanceOf(ApiKey::class, $client->getAuth());
    }

    /**
     * @covers \Weaviate\WeaviateClient::__construct
     */
    public function testCanCreateClientWithoutAuth(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create connection
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory
        );

        // Create client without auth
        $client = new WeaviateClient($connection);

        $this->assertInstanceOf(WeaviateClient::class, $client);
        $this->assertNull($client->getAuth());
    }

    /**
     * @covers \Weaviate\Connection\HttpConnection::__construct
     * @covers \Weaviate\Connection\HttpConnection::get
     * @covers \Weaviate\Connection\HttpConnection::applyAuth
     * @covers \Weaviate\Auth\ApiKey::apply
     */
    public function testHttpConnectionAppliesAuthenticationToRequests(): void
    {
        $this->skipIfWeaviateNotAvailable();

        // Create HTTP client and factories
        $httpClient = new Client();
        $httpFactory = new HttpFactory();

        // Create auth
        $auth = new ApiKey($this->getWeaviateApiKey());

        // Create connection with auth
        $connection = new HttpConnection(
            $this->getWeaviateUrl(),
            $httpClient,
            $httpFactory,
            $httpFactory,
            $auth
        );

        // Make a request - auth should be automatically applied
        $result = $connection->get('/v1/meta');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hostname', $result);
        $this->assertArrayHasKey('version', $result);
    }
}
