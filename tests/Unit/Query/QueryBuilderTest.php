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

namespace Weaviate\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Query\Filter;
use Weaviate\Query\QueryBuilder;
use Weaviate\Query\Exception\QueryException;

/**
 * @covers \Weaviate\Query\QueryBuilder
 */
class QueryBuilderTest extends TestCase
{
    /** @var ConnectionInterface&MockObject */
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::__construct
     */
    public function testCanBeConstructed(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::__construct
     */
    public function testCanBeConstructedWithTenant(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant1');

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::where
     */
    public function testWhereReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $filter = Filter::byProperty('name')->equal('John');

        $result = $queryBuilder->where($filter);

        $this->assertSame($queryBuilder, $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::limit
     */
    public function testLimitReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->limit(10);

        $this->assertSame($queryBuilder, $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnProperties
     */
    public function testReturnPropertiesReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->returnProperties(['name', 'age']);

        $this->assertSame($queryBuilder, $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::setDefaultFields
     */
    public function testSetDefaultFieldsReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->setDefaultFields('name age email');

        $this->assertSame($queryBuilder, $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsWithoutFilters(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { _additional { id } } } }'
        ];

        $expectedResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['_additional' => ['id' => '123']],
                        ['_additional' => ['id' => '456']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($expectedResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsWithFilter(): void
    {
        $filter = Filter::byProperty('name')->equal('John');

        $expectedQuery = [
            'query' => 'query { Get { TestClass(where: {path: ["name"], operator: Equal, ' .
                      'valueText: "John"}) { _additional { id } } } }'
        ];

        $expectedResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['_additional' => ['id' => '123']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($expectedResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->where($filter)->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsWithLimit(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass(limit: 5) { _additional { id } } } }'
        ];

        $expectedResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['_additional' => ['id' => '123']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($expectedResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->limit(5)->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsWithCustomProperties(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { name age _additional { id } } } }'
        ];

        $expectedResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['name' => 'John', 'age' => 30, '_additional' => ['id' => '123']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($expectedResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->returnProperties(['name', 'age'])->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsWithTenant(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { _additional { id } } } }'
        ];

        $expectedResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['_additional' => ['id' => '123']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->with('/v1/graphql?tenant=tenant1', $expectedQuery)
            ->willReturn($expectedResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant1');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsThrowsExceptionOnGraphQLError(): void
    {
        $errorResponse = [
            'errors' => [
                ['message' => 'Field "invalidField" not found'],
                ['message' => 'Another error']
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($errorResponse);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('GraphQL query failed: Field "invalidField" not found, Another error');

        $queryBuilder->fetchObjects();
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testFetchObjectsReturnsEmptyArrayWhenNoData(): void
    {
        $response = [
            'data' => [
                'Get' => []
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals([], $result);
    }
}
