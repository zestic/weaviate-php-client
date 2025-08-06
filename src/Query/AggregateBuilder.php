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
 * Aggregate query builder for Weaviate
 *
 * This class provides a fluent interface for building and executing aggregation queries
 * against Weaviate. It supports grouping, metrics, and tenant-aware aggregations,
 * matching the Python client v4 API patterns.
 *
 * @example Basic aggregation usage
 * ```php
 * $collection = $client->collections()->get('Article');
 *
 * // Simple count aggregation
 * $results = $collection->query()
 *     ->aggregate()
 *     ->metrics(['count'])
 *     ->execute();
 *
 * // Group by category with count
 * $results = $collection->query()
 *     ->aggregate()
 *     ->groupBy('category')
 *     ->metrics(['count'])
 *     ->execute();
 * ```
 */
class AggregateBuilder
{
    private ?string $groupBy = null;

    /** @var array<string> */
    private array $metrics = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $className,
        private readonly ?string $tenant = null
    ) {
    }

    /**
     * Group aggregation results by a property
     *
     * @param string $property The property to group by
     * @return $this
     */
    public function groupBy(string $property): self
    {
        $this->groupBy = $property;
        return $this;
    }

    /**
     * Set the metrics to calculate
     *
     * @param array<string> $metrics Array of metric names (e.g., ['count', 'sum', 'avg'])
     * @return $this
     */
    public function metrics(array $metrics): self
    {
        $this->metrics = $metrics;
        return $this;
    }

    /**
     * Execute the aggregation query and return the results
     *
     * @return array<string, mixed> Aggregation results
     * @throws QueryException When the GraphQL query fails
     */
    public function execute(): array
    {
        $query = $this->buildGraphQLQuery();
        $response = $this->connection->post('/v1/graphql', $query);

        return $this->parseResponse($response);
    }

    /**
     * Build the GraphQL aggregation query
     *
     * @return array<string, mixed> The GraphQL query payload
     */
    private function buildGraphQLQuery(): array
    {
        $metricsFields = $this->buildMetricsFields();
        $groupByClause = $this->groupBy ? "groupedBy: \"{$this->groupBy}\"" : '';
        $tenantClause = $this->tenant ? "tenant: \"{$this->tenant}\"" : '';

        $arguments = array_filter([$groupByClause, $tenantClause]);
        $argumentsStr = empty($arguments) ? '' : '(' . implode(', ', $arguments) . ')';

        $query = sprintf(
            'query { Aggregate { %s%s { %s } } }',
            $this->className,
            $argumentsStr,
            $metricsFields
        );

        return ['query' => $query];
    }

    /**
     * Build the metrics fields for GraphQL
     *
     * @return string The formatted metrics fields
     */
    private function buildMetricsFields(): string
    {
        if (empty($this->metrics)) {
            return 'meta { count }';
        }

        $fields = [];
        foreach ($this->metrics as $metric) {
            switch ($metric) {
                case 'count':
                    $fields[] = 'meta { count }';
                    break;
                default:
                    // For other metrics, we'd need to specify the property
                    // This is a simplified implementation
                    $fields[] = "meta { {$metric} }";
                    break;
            }
        }

        return implode(' ', $fields);
    }

    /**
     * Parse the GraphQL response
     *
     * @param array<string, mixed> $response The raw GraphQL response
     * @return array<string, mixed> Parsed aggregation results
     * @throws QueryException If the response format is invalid
     */
    private function parseResponse(array $response): array
    {
        if (isset($response['errors'])) {
            $errorMessage = 'GraphQL aggregation query failed';
            if (isset($response['errors'][0]['message'])) {
                $errorMessage .= ': ' . $response['errors'][0]['message'];
            }
            throw new QueryException($errorMessage);
        }

        if (!isset($response['data']['Aggregate'][$this->className])) {
            throw new QueryException('Invalid aggregation response format');
        }

        return $response['data']['Aggregate'][$this->className];
    }
}
