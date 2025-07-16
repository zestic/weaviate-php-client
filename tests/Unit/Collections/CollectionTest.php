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
use Weaviate\Collections\Collection;
use Weaviate\Data\DataOperations;
use Weaviate\Connection\ConnectionInterface;

class CollectionTest extends TestCase
{
    /**
     * @covers \Weaviate\Collections\Collection::withTenant
     * @covers \Weaviate\Collections\Collection::getTenant
     */
    public function testCanSetTenant(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');

        $result = $collection->withTenant('tenant1');

        $this->assertSame($collection, $result);
        $this->assertEquals('tenant1', $collection->getTenant());
    }

    /**
     * @covers \Weaviate\Collections\Collection::data
     */
    public function testDataReturnsDataOperations(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');

        $data = $collection->data();

        $this->assertInstanceOf(DataOperations::class, $data);
    }

    /**
     * @covers \Weaviate\Collections\Collection::data
     * @covers \Weaviate\Collections\Collection::withTenant
     */
    public function testDataOperationsReceiveTenant(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $collection = new Collection($connection, 'Organization');
        $collection->withTenant('tenant1');

        $data = $collection->data();

        $this->assertEquals('tenant1', $data->getTenant());
    }
}
