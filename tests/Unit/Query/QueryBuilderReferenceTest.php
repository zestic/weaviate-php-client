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
use Weaviate\Query\QueryBuilder;
use Weaviate\Query\Filter;

/**
 * @covers \Weaviate\Query\QueryBuilder
 */
class QueryBuilderReferenceTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->queryBuilder = new QueryBuilder($this->connection, 'TestClass');
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::buildReferenceFields
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testCanIncludeCrossReferencesInQuery(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { title content _additional { id } ' .
                      'hasCategory { ... on TestClass { title description } } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        [
                            'title' => 'Test Article',
                            'content' => 'Article content',
                            '_additional' => ['id' => '123'],
                            'hasCategory' => [
                                'title' => 'Technology',
                                'description' => 'Tech category'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $this->queryBuilder
            ->returnProperties(['title', 'content'])
            ->returnReferences(['hasCategory' => ['title', 'description']])
            ->fetchObjects();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Article', $result[0]['title']);
        $this->assertArrayHasKey('hasCategory', $result[0]);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::buildReferenceFields
     */
    public function testCanIncludeMultipleCrossReferences(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { title _additional { id } ' .
                      'hasCategory { ... on TestClass { title } } ' .
                      'hasAuthor { ... on TestClass { name email } } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $this->queryBuilder
            ->returnProperties(['title'])
            ->returnReferences([
                'hasCategory' => ['title'],
                'hasAuthor' => ['name', 'email']
            ])
            ->fetchObjects();
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::where
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::fetchObjects
     */
    public function testCanCombineCrossReferenceFilterWithReturnReferences(): void
    {
        $filter = Filter::byRef('hasCategory')->byProperty('title')->like('*Tech*');

        $expectedQuery = [
            'query' => 'query { Get { TestClass(where: {path: ["hasCategory"], operator: Equal, ' .
                      'valueObject: {path: ["title"], operator: Like, valueText: "*Tech*"}}) { title ' .
                      '_additional { id } hasCategory { ... on TestClass { title description } } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $this->queryBuilder
            ->where($filter)
            ->returnProperties(['title'])
            ->returnReferences(['hasCategory' => ['title', 'description']])
            ->fetchObjects();
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::buildReferenceFields
     */
    public function testCanIncludeCrossReferencesWithTenant(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant-123');

        $expectedQuery = [
            'query' => 'query { Get { TestClass(tenant: "tenant-123") { title _additional { id } ' .
                      'hasCategory { ... on TestClass { title } } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $queryBuilder
            ->returnProperties(['title'])
            ->returnReferences(['hasCategory' => ['title']])
            ->fetchObjects();
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::buildReferenceFields
     */
    public function testCanIncludeCrossReferencesWithDefaultFields(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { title content _additional { id } ' .
                      'hasCategory { ... on TestClass { title } } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $this->queryBuilder
            ->setDefaultFields('title content _additional { id }')
            ->returnReferences(['hasCategory' => ['title']])
            ->fetchObjects();
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::aggregate
     */
    public function testCanCreateAggregateBuilder(): void
    {
        $aggregateBuilder = $this->queryBuilder->aggregate();

        $this->assertInstanceOf(\Weaviate\Query\AggregateBuilder::class, $aggregateBuilder);
    }

    /**
     * @covers \Weaviate\Query\QueryBuilder::returnReferences
     * @covers \Weaviate\Query\QueryBuilder::buildReferenceFields
     */
    public function testEmptyReferencesDoesNotAddReferenceFields(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass { title _additional { id } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $this->queryBuilder
            ->returnProperties(['title'])
            ->returnReferences([])
            ->fetchObjects();
    }
}
