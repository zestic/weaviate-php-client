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

namespace Weaviate\Query;

use Weaviate\Connection\ConnectionInterface;
use Weaviate\Query\Exception\QueryException;

/**
 * GraphQL query builder for Weaviate
 *
 * This class provides a fluent interface for building and executing GraphQL queries
 * against Weaviate. It supports filtering, property selection, limits, and tenant-aware
 * queries, matching the Python client v4 API patterns.
 *
 * @example Basic query usage
 * ```php
 * $collection = $client->collections()->get('Article');
 *
 * // Simple query with filter
 * $results = $collection->query()
 *     ->where(Filter::byProperty('status')->equal('published'))
 *     ->limit(10)
 *     ->fetchObjects();
 *
 * // Complex query with multiple conditions
 * $results = $collection->query()
 *     ->where(Filter::allOf([
 *         Filter::byProperty('status')->equal('published'),
 *         Filter::byProperty('publishedAt')->greaterThan($date)
 *     ]))
 *     ->returnProperties(['title', 'content', 'publishedAt'])
 *     ->limit(20)
 *     ->fetchObjects();
 * ```
 */
class QueryBuilder
{
    private ?Filter $filter = null;
    private ?int $limit = null;
    
    /** @var array<string> */
    private array $returnProperties = [];
    
    private ?string $defaultFields = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $className,
        private readonly ?string $tenant = null
    ) {
    }

    /**
     * Add a filter condition to the query
     *
     * @param Filter $filter The filter to apply
     * @return $this
     */
    public function where(Filter $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Set the maximum number of results to return
     *
     * @param int $limit Maximum number of results
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Specify which properties to return in the results
     *
     * @param array<string> $properties Array of property names to return
     * @return $this
     */
    public function returnProperties(array $properties): self
    {
        $this->returnProperties = $properties;
        return $this;
    }

    /**
     * Set default fields for queries when no specific properties are requested
     *
     * @param string $fields Space-separated list of default fields
     * @return $this
     */
    public function setDefaultFields(string $fields): self
    {
        $this->defaultFields = $fields;
        return $this;
    }

    /**
     * Execute the query and return the results
     *
     * @return array<int, array<string, mixed>> Array of matching objects
     * @throws QueryException When the GraphQL query fails
     */
    public function fetchObjects(): array
    {
        $query = $this->buildGraphQLQuery();
        $response = $this->connection->post('/v1/graphql', $query);
        
        return $this->parseResponse($response);
    }

    /**
     * Build the GraphQL query array
     *
     * @return array<string, mixed> The GraphQL query payload
     */
    private function buildGraphQLQuery(): array
    {
        $fields = empty($this->returnProperties) 
            ? $this->getDefaultFields() 
            : implode(' ', $this->returnProperties);
            
        $whereClause = $this->filter ? $this->buildWhereClause() : '';
        $limitClause = $this->limit ? "limit: {$this->limit}" : '';
        
        $arguments = array_filter([$whereClause, $limitClause]);
        $argumentsStr = empty($arguments) ? '' : '(' . implode(', ', $arguments) . ')';
        
        $query = sprintf(
            'query { Get { %s%s { %s _additional { id } } } }',
            $this->className,
            $argumentsStr,
            $fields
        );
        
        $payload = ['query' => $query];
        
        // Add tenant to variables if specified
        if ($this->tenant) {
            $payload['variables'] = ['tenant' => $this->tenant];
        }
        
        return $payload;
    }

    /**
     * Build the WHERE clause for GraphQL
     *
     * @return string The formatted WHERE clause
     */
    private function buildWhereClause(): string
    {
        if (!$this->filter) {
            return '';
        }
        
        $conditions = $this->filter->toArray();
        return 'where: ' . $this->arrayToGraphQL($conditions);
    }

    /**
     * Convert PHP array to GraphQL format
     *
     * @param array<string, mixed> $array The array to convert
     * @return string The GraphQL formatted string
     */
    private function arrayToGraphQL(array $array): string
    {
        $parts = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($key === 'path') {
                    $parts[] = $key . ': [' . implode(', ', array_map(fn($v) => '"' . addslashes($v) . '"', $value)) . ']';
                } elseif ($key === 'operands') {
                    // Handle nested operands for complex filters
                    $operandParts = array_map(fn($operand) => $this->arrayToGraphQL($operand), $value);
                    $parts[] = $key . ': [' . implode(', ', $operandParts) . ']';
                } else {
                    $parts[] = $key . ': ' . $this->arrayToGraphQL($value);
                }
            } elseif (is_string($value)) {
                $parts[] = $key . ': "' . addslashes($value) . '"';
            } elseif (is_bool($value)) {
                $parts[] = $key . ': ' . ($value ? 'true' : 'false');
            } elseif (is_null($value)) {
                $parts[] = $key . ': null';
            } else {
                $parts[] = $key . ': ' . json_encode($value);
            }
        }
        return '{' . implode(', ', $parts) . '}';
    }

    /**
     * Get the default fields to return when no specific properties are requested
     *
     * @return string The default fields string
     */
    private function getDefaultFields(): string
    {
        // Use configurable default fields per collection
        return $this->defaultFields ?? '_additional { id }';
    }

    /**
     * Parse the GraphQL response and extract the results
     *
     * @param array<string, mixed> $response The raw GraphQL response
     * @return array<int, array<string, mixed>> The parsed results
     * @throws QueryException When the response contains errors
     */
    private function parseResponse(array $response): array
    {
        if (isset($response['errors'])) {
            $errorMessages = array_map(fn($error) => $error['message'] ?? 'Unknown error', $response['errors']);
            throw new QueryException('GraphQL query failed: ' . implode(', ', $errorMessages), $response['errors']);
        }

        if (!isset($response['data']['Get'][$this->className])) {
            return [];
        }

        return $response['data']['Get'][$this->className];
    }
}
