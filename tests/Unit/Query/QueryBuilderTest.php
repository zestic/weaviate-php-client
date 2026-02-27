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

class QueryBuilderTest extends TestCase
{
    /** @var ConnectionInterface&MockObject */
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
    }

    public function testCanBeConstructed(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testCanBeConstructedWithTenant(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant1');

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testWhereReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $filter = Filter::byProperty('name')->equal('John');

        $result = $queryBuilder->where($filter);

        $this->assertSame($queryBuilder, $result);
    }

    public function testLimitReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->limit(10);

        $this->assertSame($queryBuilder, $result);
    }

    public function testReturnPropertiesReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->returnProperties(['name', 'age']);

        $this->assertSame($queryBuilder, $result);
    }

    public function testSetDefaultFieldsReturnsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        $result = $queryBuilder->setDefaultFields('name age email');

        $this->assertSame($queryBuilder, $result);
    }

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

    public function testFetchObjectsWithTenant(): void
    {
        $expectedQuery = [
            'query' => 'query { Get { TestClass(tenant: "tenant1") { _additional { id } } } }'
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

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant1');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals($expectedResponse['data']['Get']['TestClass'], $result);
    }

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

    public function testSetDefaultFields(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->setDefaultFields('title content author');

        $this->assertSame($queryBuilder, $result);
    }

    public function testFetchObjectsWithDefaultFields(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        ['title' => 'Test Article', '_additional' => ['id' => '123']]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $queryBuilder->setDefaultFields('title content author');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals([['title' => 'Test Article', '_additional' => ['id' => '123']]], $result);
    }

    public function testAggregate(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $aggregateBuilder = $queryBuilder->aggregate();

        $this->assertInstanceOf(\Weaviate\Query\AggregateBuilder::class, $aggregateBuilder);
    }

    public function testFetchObjectsWithComplexFilter(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        // Test complex filter that exercises arrayToGraphQL method
        $complexFilter = Filter::allOf([
            Filter::byProperty('status')->equal('published'),
            Filter::byProperty('featured')->equal(true)
        ]);

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->where($complexFilter)->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testFetchObjectsWithDifferentFilterTypes(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->exactly(4))
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');

        // Test with null value
        $queryBuilder->where(Filter::byProperty('deletedAt')->equal(null))->fetchObjects();

        // Test with boolean value
        $queryBuilder->where(Filter::byProperty('featured')->equal(true))->fetchObjects();

        // Test with numeric value
        $queryBuilder->where(Filter::byProperty('count')->greaterThan(10))->fetchObjects();

        // Test with string value
        $queryBuilder->where(Filter::byProperty('status')->equal('published'))->fetchObjects();
    }

    public function testFetchObjectsWithTenantReturnsEmptyArray(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass', 'tenant-123');
        $result = $queryBuilder->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testReturnReferences(): void
    {
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->returnReferences([
            'hasCategory' => ['title', 'description'],
            'hasAuthor' => ['name', 'email']
        ]);

        $this->assertSame($queryBuilder, $result);
    }

    public function testFetchObjectsWithReferences(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => [
                        [
                            'title' => 'Test Article',
                            '_additional' => ['id' => '123'],
                            'hasCategory' => [
                                'title' => 'Technology',
                                'description' => 'Tech articles'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder
            ->returnReferences(['hasCategory' => ['title', 'description']])
            ->fetchObjects();

        $expected = [
            [
                'title' => 'Test Article',
                '_additional' => ['id' => '123'],
                'hasCategory' => [
                    'title' => 'Technology',
                    'description' => 'Tech articles'
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFetchObjectsWithMultipleReferences(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder
            ->returnReferences([
                'hasCategory' => ['title', 'description'],
                'hasAuthor' => ['name', 'email'],
                'hasTags' => ['name']
            ])
            ->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testFetchObjectsWithFilterAndReferences(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $filter = Filter::byProperty('status')->equal('published');
        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder
            ->where($filter)
            ->returnReferences(['hasCategory' => ['title']])
            ->returnProperties(['title', 'content'])
            ->limit(10)
            ->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testFetchObjectsWithComplexNestedFilter(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        // Create a complex nested filter to exercise arrayToGraphQL method thoroughly
        $complexFilter = Filter::allOf([
            Filter::anyOf([
                Filter::byProperty('status')->equal('published'),
                Filter::byProperty('status')->equal('featured')
            ]),
            Filter::byProperty('viewCount')->greaterThan(100),
            Filter::byRef('hasCategory')->byProperty('active')->equal(true)
        ]);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->where($complexFilter)->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testFetchObjectsWithArrayContainsAnyFilter(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        // Test containsAny filter to exercise array value handling in arrayToGraphQL
        $filter = Filter::byProperty('tags')->containsAny(['php', 'javascript', 'python']);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->where($filter)->fetchObjects();

        $this->assertEquals([], $result);
    }

    public function testFetchObjectsWithStringArrayFilter(): void
    {
        $response = [
            'data' => [
                'Get' => [
                    'TestClass' => []
                ]
            ]
        ];

        $this->connection->expects($this->once())
            ->method('post')
            ->willReturn($response);

        // Test string array with special characters to exercise escaping
        $filter = Filter::byProperty('categories')->containsAny(['tech & science', 'art "quotes"']);

        $queryBuilder = new QueryBuilder($this->connection, 'TestClass');
        $result = $queryBuilder->where($filter)->fetchObjects();

        $this->assertEquals([], $result);
    }
}
