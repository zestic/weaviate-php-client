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

namespace Weaviate\Exceptions;

use Throwable;

/**
 * Is raised if a query to Weaviate fails in any way.
 *
 * This exception is thrown when queries (GraphQL, REST API calls, etc.)
 * fail due to various reasons such as:
 * - Invalid query syntax
 * - Missing collections or properties
 * - Server-side query processing errors
 * - Data validation failures
 *
 * @example Handling query errors
 * ```php
 * try {
 *     $result = $client->collections()->create('Invalid-Name!', []);
 * } catch (WeaviateQueryException $e) {
 *     echo "Query failed: " . $e->getMessage();
 *
 *     $context = $e->getContext();
 *     if (isset($context['query_type'])) {
 *         echo "Query type: " . $context['query_type'];
 *     }
 *
 *     if (isset($context['validation_errors'])) {
 *         echo "Validation errors: " . json_encode($context['validation_errors']);
 *     }
 * }
 * ```
 */
class WeaviateQueryException extends WeaviateBaseException
{
    private string $queryType;

    /**
     * @param string $message Error message
     * @param string $queryType Type of query that failed (REST, GraphQL, etc.)
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $queryType = 'unknown',
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->queryType = $queryType;

        $finalMessage = "Query call with protocol {$queryType} failed with message {$message}";

        $context['query_type'] = $queryType;
        $context['error_type'] = 'query_failure';

        parent::__construct($finalMessage, $context, 0, $previous);
    }

    /**
     * Get the type of query that failed
     */
    public function getQueryType(): string
    {
        return $this->queryType;
    }

    /**
     * Create for REST API query failure
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path API path
     * @param string $error Error message
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forRestQuery(
        string $method,
        string $path,
        string $error,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['method'] = $method;
        $context['path'] = $path;
        $context['query_subtype'] = 'rest_api';

        return new self($error, 'REST', $context, $previous);
    }

    /**
     * Create for GraphQL query failure
     *
     * @param string $query GraphQL query
     * @param string $error Error message
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forGraphQLQuery(
        string $query,
        string $error,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['graphql_query'] = $query;
        $context['query_subtype'] = 'graphql';

        return new self($error, 'GraphQL', $context, $previous);
    }

    /**
     * Create for validation error
     *
     * @param string $operation The operation that failed validation
     * @param array<string, mixed> $validationErrors Validation error details
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forValidation(
        string $operation,
        array $validationErrors,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['operation'] = $operation;
        $context['validation_errors'] = $validationErrors;
        $context['query_subtype'] = 'validation';

        $errorMessage = "Validation failed for operation '{$operation}': " . json_encode($validationErrors);

        return new self($errorMessage, 'Validation', $context, $previous);
    }

    /**
     * Create for schema-related query failure
     *
     * @param string $collection Collection name
     * @param string $operation Schema operation (create, update, delete)
     * @param string $error Error message
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forSchema(
        string $collection,
        string $operation,
        string $error,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['collection'] = $collection;
        $context['schema_operation'] = $operation;
        $context['query_subtype'] = 'schema';

        return new self($error, 'Schema', $context, $previous);
    }
}
