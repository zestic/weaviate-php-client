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

namespace Weaviate\Tests\Unit\Collections;

use PHPUnit\Framework\TestCase;
use Weaviate\Collections\Collections;
use Weaviate\Collections\Collection;
use Weaviate\Connection\ConnectionInterface;

class CollectionsTest extends TestCase
{
    public function testCanCheckIfCollectionExists(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Organization')
            ->willReturn(['class' => 'Organization']);

        $collections = new Collections($connection);

        $exists = $collections->exists('Organization');

        $this->assertTrue($exists);
    }

    public function testReturnsFalseWhenCollectionDoesNotExist(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('get')
            ->with('/v1/schema/Organization')
            ->willThrowException(new \Weaviate\Exceptions\NotFoundException());

        $collections = new Collections($connection);

        $exists = $collections->exists('Organization');

        $this->assertFalse($exists);
    }

    public function testCanCreateCollection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('post')
            ->with('/v1/schema', [
                'class' => 'Organization',
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']]
                ],
                'multiTenancyConfig' => ['enabled' => true]
            ])
            ->willReturn(['class' => 'Organization']);

        $collections = new Collections($connection);

        $result = $collections->create('Organization', [
            'properties' => [
                ['name' => 'name', 'dataType' => ['text']]
            ],
            'multiTenancyConfig' => ['enabled' => true]
        ]);

        $this->assertEquals(['class' => 'Organization'], $result);
    }

    public function testCanGetCollection(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $collections = new Collections($connection);
        $collection = $collections->get('Organization');

        $this->assertInstanceOf(Collection::class, $collection);
    }
}
