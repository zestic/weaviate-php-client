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
use Weaviate\Query\Exception\QueryException;

/**
 * @covers \Weaviate\Query\Exception\QueryException
 */
class QueryExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Query\Exception\QueryException::__construct
     */
    public function testCanBeConstructedWithMessage(): void
    {
        $exception = new QueryException('Test message');

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals([], $exception->getGraphqlErrors());
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::__construct
     * @covers \Weaviate\Query\Exception\QueryException::getGraphqlErrors
     */
    public function testCanBeConstructedWithGraphQLErrors(): void
    {
        $graphqlErrors = [
            ['message' => 'Field not found', 'path' => ['field1']],
            ['message' => 'Invalid syntax', 'locations' => [['line' => 1, 'column' => 5]]]
        ];

        $exception = new QueryException('GraphQL error', $graphqlErrors);

        $this->assertEquals('GraphQL error', $exception->getMessage());
        $this->assertEquals($graphqlErrors, $exception->getGraphqlErrors());
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::__construct
     */
    public function testCanBeConstructedWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new QueryException('Test message', [], 500, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::getDetailedErrorMessage
     */
    public function testGetDetailedErrorMessageWithNoErrors(): void
    {
        $exception = new QueryException('Test message');

        $this->assertEquals('', $exception->getDetailedErrorMessage());
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::getDetailedErrorMessage
     */
    public function testGetDetailedErrorMessageWithSingleError(): void
    {
        $graphqlErrors = [
            [
                'message' => 'Field not found',
                'path' => ['field1'],
                'locations' => [['line' => 1, 'column' => 5]]
            ]
        ];

        $exception = new QueryException('GraphQL error', $graphqlErrors);
        $detailedMessage = $exception->getDetailedErrorMessage();

        $this->assertStringContainsString('Error: Field not found', $detailedMessage);
        $this->assertStringContainsString('Path: ["field1"]', $detailedMessage);
        $this->assertStringContainsString('Locations: [{"line":1,"column":5}]', $detailedMessage);
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::getDetailedErrorMessage
     */
    public function testGetDetailedErrorMessageWithMultipleErrors(): void
    {
        $graphqlErrors = [
            [
                'message' => 'Field not found',
                'path' => ['field1']
            ],
            [
                'message' => 'Invalid syntax',
                'locations' => [['line' => 2, 'column' => 10]]
            ]
        ];

        $exception = new QueryException('GraphQL errors', $graphqlErrors);
        $detailedMessage = $exception->getDetailedErrorMessage();

        $this->assertStringContainsString('Error: Field not found', $detailedMessage);
        $this->assertStringContainsString('Error: Invalid syntax', $detailedMessage);
        $this->assertStringContainsString('Path: ["field1"]', $detailedMessage);
        $this->assertStringContainsString('Locations: [{"line":2,"column":10}]', $detailedMessage);
    }

    /**
     * @covers \Weaviate\Query\Exception\QueryException::getDetailedErrorMessage
     */
    public function testGetDetailedErrorMessageWithMissingFields(): void
    {
        $graphqlErrors = [
            [
                'message' => 'Field not found'
                // Missing path and locations
            ],
            [
                // Missing message
                'path' => ['field1']
            ]
        ];

        $exception = new QueryException('GraphQL errors', $graphqlErrors);
        $detailedMessage = $exception->getDetailedErrorMessage();

        $this->assertStringContainsString('Error: Field not found', $detailedMessage);
        $this->assertStringContainsString('Path: []', $detailedMessage);
        $this->assertStringContainsString('Locations: []', $detailedMessage);
        $this->assertStringContainsString('Error: Unknown', $detailedMessage);
        $this->assertStringContainsString('Path: ["field1"]', $detailedMessage);
    }
}
