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

namespace Weaviate\Query\Exception;

use Exception;
use Throwable;

/**
 * Exception thrown when GraphQL query operations fail
 *
 * This exception provides detailed information about GraphQL errors,
 * including error messages, paths, and locations to help with debugging.
 *
 * @example Basic usage
 * ```php
 * try {
 *     $results = $collection->query()
 *         ->where(Filter::byProperty('invalidField')->equal('value'))
 *         ->fetchObjects();
 * } catch (QueryException $e) {
 *     echo "Query failed: " . $e->getMessage();
 *     echo "Details: " . $e->getDetailedErrorMessage();
 * }
 * ```
 */
class QueryException extends Exception
{
    /**
     * @param array<int, array<string, mixed>> $graphqlErrors
     */
    public function __construct(
        string $message,
        private readonly array $graphqlErrors = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the original GraphQL errors
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGraphqlErrors(): array
    {
        return $this->graphqlErrors;
    }

    /**
     * Get a detailed error message with all GraphQL error information
     *
     * This method formats all GraphQL errors into a human-readable string
     * that includes error messages, paths, and locations for debugging.
     */
    public function getDetailedErrorMessage(): string
    {
        if (empty($this->graphqlErrors)) {
            return '';
        }

        $details = [];
        foreach ($this->graphqlErrors as $error) {
            $details[] = sprintf(
                "Error: %s (Path: %s, Locations: %s)",
                $error['message'] ?? 'Unknown',
                json_encode($error['path'] ?? []),
                json_encode($error['locations'] ?? [])
            );
        }

        return implode("\n", $details);
    }
}
