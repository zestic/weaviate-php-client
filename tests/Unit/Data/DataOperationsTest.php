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

namespace Weaviate\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Weaviate\Data\DataOperations;
use Weaviate\Connection\ConnectionInterface;

class DataOperationsTest extends TestCase
{
    /**
     * @covers \Weaviate\Data\DataOperations::__construct
     * @covers \Weaviate\Data\DataOperations::create
     */
    public function testCanCreateObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('post')
            ->with('/v1/objects', [
                'class' => 'Organization',
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'properties' => [
                    'name' => 'ACME Corp',
                    'createdAt' => '2024-01-01T00:00:00Z'
                ],
                'tenant' => 'tenant1'
            ])
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'class' => 'Organization'
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->create([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'ACME Corp',
            'createdAt' => '2024-01-01T00:00:00Z'
        ]);

        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $result['id']);
    }

    /**
     * @covers \Weaviate\Data\DataOperations::get
     */
    public function testCanGetObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1')
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'class' => 'Organization',
                'properties' => ['name' => 'ACME Corp']
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->get('123e4567-e89b-12d3-a456-426614174000');

        $this->assertEquals('ACME Corp', $result['properties']['name']);
    }

    /**
     * @covers \Weaviate\Data\DataOperations::__construct
     * @covers \Weaviate\Data\DataOperations::update
     * @covers \Weaviate\Data\DataOperations::get
     */
    public function testCanUpdateObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        // Expect patch call first
        $connection->expects($this->once())
            ->method('patch')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1', [
                'properties' => ['name' => 'Updated Corp'],
                'tenant' => 'tenant1'
            ])
            ->willReturn([]);

        // Then expect get call to fetch updated object
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1')
            ->willReturn([
                'id' => '123e4567-e89b-12d3-a456-426614174000',
                'class' => 'Organization',
                'properties' => ['name' => 'Updated Corp']
            ]);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->update('123e4567-e89b-12d3-a456-426614174000', [
            'name' => 'Updated Corp'
        ]);

        $this->assertEquals('Updated Corp', $result['properties']['name']);
    }

    /**
     * @covers \Weaviate\Data\DataOperations::delete
     */
    public function testCanDeleteObject(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('delete')
            ->with('/v1/objects/Organization/123e4567-e89b-12d3-a456-426614174000?tenant=tenant1')
            ->willReturn(true);

        $data = new DataOperations($connection, 'Organization', 'tenant1');

        $result = $data->delete('123e4567-e89b-12d3-a456-426614174000');

        $this->assertTrue($result);
    }
}
