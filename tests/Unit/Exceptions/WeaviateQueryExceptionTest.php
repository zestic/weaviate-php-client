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

namespace Weaviate\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\WeaviateQueryException;
use Weaviate\Exceptions\WeaviateBaseException;

class WeaviateQueryExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::__construct
     */
    public function testCanCreateQueryException(): void
    {
        $exception = new WeaviateQueryException('Query failed');

        $this->assertInstanceOf(WeaviateBaseException::class, $exception);
        $this->assertStringContainsString('Query call with protocol unknown failed with message Query failed', $exception->getMessage());
        $this->assertSame('unknown', $exception->getQueryType());
        
        $context = $exception->getContext();
        $this->assertSame('unknown', $context['query_type']);
        $this->assertSame('query_failure', $context['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::__construct
     */
    public function testCanCreateWithQueryType(): void
    {
        $exception = new WeaviateQueryException('GraphQL error', 'GraphQL');

        $this->assertStringContainsString('Query call with protocol GraphQL failed', $exception->getMessage());
        $this->assertSame('GraphQL', $exception->getQueryType());
        
        $context = $exception->getContext();
        $this->assertSame('GraphQL', $context['query_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['collection' => 'Article', 'operation' => 'create'];
        $exception = new WeaviateQueryException('Query failed', 'REST', $context);

        $resultContext = $exception->getContext();
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame('create', $resultContext['operation']);
        $this->assertSame('REST', $resultContext['query_type']);
        $this->assertSame('query_failure', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::getQueryType
     */
    public function testGetQueryType(): void
    {
        $exception = new WeaviateQueryException('Error', 'Custom');

        $this->assertSame('Custom', $exception->getQueryType());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forRestQuery
     */
    public function testForRestQuery(): void
    {
        $method = 'POST';
        $path = '/v1/objects';
        $error = 'Validation failed';
        $context = ['collection' => 'Article'];

        $exception = WeaviateQueryException::forRestQuery($method, $path, $error, $context);

        $this->assertStringContainsString('Query call with protocol REST failed with message Validation failed', $exception->getMessage());
        $this->assertSame('REST', $exception->getQueryType());

        $resultContext = $exception->getContext();
        $this->assertSame($method, $resultContext['method']);
        $this->assertSame($path, $resultContext['path']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame('rest_api', $resultContext['query_subtype']);
        $this->assertSame('REST', $resultContext['query_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forGraphQLQuery
     */
    public function testForGraphQLQuery(): void
    {
        $query = '{ Get { Article { title } } }';
        $error = 'Field not found';
        $context = ['variables' => ['limit' => 10]];

        $exception = WeaviateQueryException::forGraphQLQuery($query, $error, $context);

        $this->assertStringContainsString('Query call with protocol GraphQL failed with message Field not found', $exception->getMessage());
        $this->assertSame('GraphQL', $exception->getQueryType());

        $resultContext = $exception->getContext();
        $this->assertSame($query, $resultContext['graphql_query']);
        $this->assertSame(['limit' => 10], $resultContext['variables']);
        $this->assertSame('graphql', $resultContext['query_subtype']);
        $this->assertSame('GraphQL', $resultContext['query_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forValidation
     */
    public function testForValidation(): void
    {
        $operation = 'create_object';
        $validationErrors = [
            'title' => 'Required field missing',
            'content' => 'Must be string'
        ];
        $context = ['collection' => 'Article'];

        $exception = WeaviateQueryException::forValidation($operation, $validationErrors, $context);

        $this->assertStringContainsString('Query call with protocol Validation failed', $exception->getMessage());
        $this->assertStringContainsString("Validation failed for operation 'create_object'", $exception->getMessage());
        $this->assertSame('Validation', $exception->getQueryType());

        $resultContext = $exception->getContext();
        $this->assertSame($operation, $resultContext['operation']);
        $this->assertSame($validationErrors, $resultContext['validation_errors']);
        $this->assertSame('Article', $resultContext['collection']);
        $this->assertSame('validation', $resultContext['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forSchema
     */
    public function testForSchema(): void
    {
        $collection = 'Article';
        $operation = 'create';
        $error = 'Collection already exists';
        $context = ['properties_count' => 5];

        $exception = WeaviateQueryException::forSchema($collection, $operation, $error, $context);

        $this->assertStringContainsString('Query call with protocol Schema failed with message Collection already exists', $exception->getMessage());
        $this->assertSame('Schema', $exception->getQueryType());

        $resultContext = $exception->getContext();
        $this->assertSame($collection, $resultContext['collection']);
        $this->assertSame($operation, $resultContext['schema_operation']);
        $this->assertSame(5, $resultContext['properties_count']);
        $this->assertSame('schema', $resultContext['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new WeaviateQueryException('Query failed', 'REST', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forRestQuery
     */
    public function testForRestQueryWithPreviousException(): void
    {
        $previous = new \Exception('Connection lost');
        $exception = WeaviateQueryException::forRestQuery('GET', '/test', 'Error', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forRestQuery
     */
    public function testForRestQueryWithEmptyContext(): void
    {
        $exception = WeaviateQueryException::forRestQuery('GET', '/test', 'Error');

        $context = $exception->getContext();
        $this->assertSame('GET', $context['method']);
        $this->assertSame('/test', $context['path']);
        $this->assertSame('rest_api', $context['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forGraphQLQuery
     */
    public function testForGraphQLQueryWithEmptyQuery(): void
    {
        $exception = WeaviateQueryException::forGraphQLQuery('', 'Empty query');

        $context = $exception->getContext();
        $this->assertSame('', $context['graphql_query']);
        $this->assertSame('graphql', $context['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forValidation
     */
    public function testForValidationWithEmptyErrors(): void
    {
        $exception = WeaviateQueryException::forValidation('test_operation', []);

        $context = $exception->getContext();
        $this->assertSame('test_operation', $context['operation']);
        $this->assertSame([], $context['validation_errors']);
        $this->assertSame('validation', $context['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forSchema
     */
    public function testForSchemaWithEmptyContext(): void
    {
        $exception = WeaviateQueryException::forSchema('TestCollection', 'update', 'Schema error');

        $context = $exception->getContext();
        $this->assertSame('TestCollection', $context['collection']);
        $this->assertSame('update', $context['schema_operation']);
        $this->assertSame('schema', $context['query_subtype']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::__construct
     */
    public function testWithEmptyQueryType(): void
    {
        $exception = new WeaviateQueryException('Error', '');

        $this->assertSame('', $exception->getQueryType());
        $this->assertStringContainsString('Query call with protocol  failed', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forValidation
     */
    public function testForValidationMessageIncludesJsonEncodedErrors(): void
    {
        $validationErrors = ['field1' => 'error1', 'field2' => 'error2'];
        $exception = WeaviateQueryException::forValidation('test', $validationErrors);

        $this->assertStringContainsString(json_encode($validationErrors), $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateQueryException::forRestQuery
     */
    public function testForRestQueryWithSpecialCharacters(): void
    {
        $method = 'POST';
        $path = '/v1/objects/special%20chars';
        $error = 'Error with "quotes" and special chars';

        $exception = WeaviateQueryException::forRestQuery($method, $path, $error);

        $context = $exception->getContext();
        $this->assertSame($path, $context['path']);
        $this->assertStringContainsString($error, $exception->getMessage());
    }
}
