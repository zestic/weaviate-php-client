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
use Weaviate\Query\AggregateBuilder;
use Weaviate\Query\Exception\QueryException;

/**
 * @covers \Weaviate\Query\AggregateBuilder
 */
class AggregateBuilderTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private AggregateBuilder $aggregateBuilder;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->aggregateBuilder = new AggregateBuilder($this->connection, 'TestClass');
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::metrics
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::buildGraphQLQuery
     * @covers \Weaviate\Query\AggregateBuilder::buildMetricsFields
     * @covers \Weaviate\Query\AggregateBuilder::parseResponse
     */
    public function testCanExecuteSimpleCountAggregation(): void
    {
        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass { meta { count } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['meta' => ['count' => 42]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $this->aggregateBuilder
            ->metrics(['count'])
            ->execute();

        $this->assertEquals([['meta' => ['count' => 42]]], $result);
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::groupBy
     * @covers \Weaviate\Query\AggregateBuilder::metrics
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::buildGraphQLQuery
     */
    public function testCanExecuteGroupedAggregation(): void
    {
        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass(groupedBy: "category") { meta { count } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['groupedBy' => ['value' => 'technology'], 'meta' => ['count' => 15]],
                        ['groupedBy' => ['value' => 'sports'], 'meta' => ['count' => 27]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $this->aggregateBuilder
            ->groupBy('category')
            ->metrics(['count'])
            ->execute();

        $expected = [
            ['groupedBy' => ['value' => 'technology'], 'meta' => ['count' => 15]],
            ['groupedBy' => ['value' => 'sports'], 'meta' => ['count' => 27]]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::buildGraphQLQuery
     */
    public function testCanExecuteAggregationWithTenant(): void
    {
        $aggregateBuilder = new AggregateBuilder($this->connection, 'TestClass', 'tenant-123');

        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass(tenant: "tenant-123") { meta { count } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['meta' => ['count' => 10]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $aggregateBuilder
            ->metrics(['count'])
            ->execute();

        $this->assertEquals([['meta' => ['count' => 10]]], $result);
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::groupBy
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::buildGraphQLQuery
     */
    public function testCanExecuteAggregationWithTenantAndGroupBy(): void
    {
        $aggregateBuilder = new AggregateBuilder($this->connection, 'TestClass', 'tenant-123');

        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass(groupedBy: "status", tenant: "tenant-123") { meta { count } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['groupedBy' => ['value' => 'active'], 'meta' => ['count' => 8]],
                        ['groupedBy' => ['value' => 'inactive'], 'meta' => ['count' => 2]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $aggregateBuilder
            ->groupBy('status')
            ->metrics(['count'])
            ->execute();

        $expected = [
            ['groupedBy' => ['value' => 'active'], 'meta' => ['count' => 8]],
            ['groupedBy' => ['value' => 'inactive'], 'meta' => ['count' => 2]]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::buildMetricsFields
     */
    public function testUsesDefaultCountMetricWhenNoMetricsSpecified(): void
    {
        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass { meta { count } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['meta' => ['count' => 5]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $this->aggregateBuilder->execute();

        $this->assertEquals([['meta' => ['count' => 5]]], $result);
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::parseResponse
     */
    public function testThrowsExceptionOnGraphQLError(): void
    {
        $errorResponse = [
            'errors' => [
                ['message' => 'Field "invalidField" not found']
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->willReturn($errorResponse);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('GraphQL aggregation query failed: Field "invalidField" not found');

        $this->aggregateBuilder->execute();
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::execute
     * @covers \Weaviate\Query\AggregateBuilder::parseResponse
     */
    public function testThrowsExceptionOnInvalidResponseFormat(): void
    {
        $invalidResponse = [
            'data' => [
                'Get' => [] // Wrong structure - should be 'Aggregate'
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->willReturn($invalidResponse);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Invalid aggregation response format');

        $this->aggregateBuilder->execute();
    }

    /**
     * @covers \Weaviate\Query\AggregateBuilder::metrics
     * @covers \Weaviate\Query\AggregateBuilder::buildMetricsFields
     */
    public function testCanHandleMultipleMetrics(): void
    {
        $expectedQuery = [
            'query' => 'query { Aggregate { TestClass { meta { count } meta { sum } meta { avg } } } }'
        ];

        $mockResponse = [
            'data' => [
                'Aggregate' => [
                    'TestClass' => [
                        ['meta' => ['count' => 10, 'sum' => 100, 'avg' => 10]]
                    ]
                ]
            ]
        ];

        $this->connection
            ->expects($this->once())
            ->method('post')
            ->with('/v1/graphql', $expectedQuery)
            ->willReturn($mockResponse);

        $result = $this->aggregateBuilder
            ->metrics(['count', 'sum', 'avg'])
            ->execute();

        $this->assertEquals([['meta' => ['count' => 10, 'sum' => 100, 'avg' => 10]]], $result);
    }
}
