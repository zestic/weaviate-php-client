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

class WeaviateClientTest extends TestCase
{
    public function testCanCreateClientWithConnection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    public function testCanCreateClientWithConnectionAndAuth(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $auth = $this->createMock(AuthInterface::class);
        $client = new WeaviateClient($connection, $auth);

        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    public function testCollectionsReturnsCollectionsInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $collections = $client->collections();

        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    public function testSchemaReturnsSchemaInstance(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $client = new WeaviateClient($connection);

        $schema = $client->schema();

        $this->assertInstanceOf(\Weaviate\Schema\Schema::class, $schema);
    }
}
