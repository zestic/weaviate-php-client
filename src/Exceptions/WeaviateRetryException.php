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
 * Is raised when a request to Weaviate fails and is retried multiple times.
 *
 * This exception is thrown when the retry mechanism has exhausted all
 * retry attempts and the operation still fails. It contains information
 * about all the retry attempts and the final failure.
 *
 * @example Handling retry exhaustion
 * ```php
 * try {
 *     $client->collections()->exists('TestCollection');
 * } catch (WeaviateRetryException $e) {
 *     echo "Operation failed after {$e->getRetryCount()} retries";
 *     echo "Final error: " . $e->getMessage();
 *
 *     // Get details about retry attempts
 *     $context = $e->getContext();
 *     if (isset($context['retry_attempts'])) {
 *         foreach ($context['retry_attempts'] as $attempt) {
 *             echo "Attempt {$attempt['number']}: {$attempt['error']}";
 *         }
 *     }
 * }
 * ```
 */
class WeaviateRetryException extends WeaviateBaseException
{
    private int $retryCount;

    /**
     * @param string $message Error message from the final attempt
     * @param int $retryCount Number of retry attempts made
     * @param array<string, mixed> $context Additional context including retry attempts
     * @param Throwable|null $previous Previous exception (usually the final failure)
     */
    public function __construct(
        string $message,
        int $retryCount,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->retryCount = $retryCount;

        $finalMessage = "The request to Weaviate failed after {$retryCount} retries. Final error: {$message}";

        $context['retry_count'] = $retryCount;
        $context['error_type'] = 'retry_exhausted';
        $context['suggestions'] = [
            'Check Weaviate server status and logs',
            'Verify network connectivity is stable',
            'Consider increasing retry limits if appropriate',
            'Check if the operation is valid and properly formatted'
        ];

        parent::__construct($finalMessage, $context, 0, $previous);
    }

    /**
     * Get the number of retry attempts that were made
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Get the retry attempts details
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function getRetryAttempts(): ?array
    {
        return $this->context['retry_attempts'] ?? null;
    }

    /**
     * Create with detailed retry attempt information
     *
     * @param string $operation The operation that was retried
     * @param int $retryCount Number of retry attempts
     * @param array<int, array<string, mixed>> $attempts Details of each retry attempt
     * @param string $finalError The final error message
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function withAttempts(
        string $operation,
        int $retryCount,
        array $attempts,
        string $finalError,
        ?Throwable $previous = null
    ): self {
        $context = [
            'operation' => $operation,
            'retry_attempts' => $attempts,
            'final_error' => $finalError
        ];

        return new self($finalError, $retryCount, $context, $previous);
    }

    /**
     * Create for connection retry failure
     *
     * @param string $url The URL that failed
     * @param int $retryCount Number of attempts
     * @param string $finalError Final error message
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forConnection(
        string $url,
        int $retryCount,
        string $finalError,
        ?Throwable $previous = null
    ): self {
        $context = [
            'url' => $url,
            'retry_type' => 'connection',
            'final_error' => $finalError
        ];

        return new self($finalError, $retryCount, $context, $previous);
    }

    /**
     * Create for query retry failure
     *
     * @param string $query The query that failed
     * @param int $retryCount Number of attempts
     * @param string $finalError Final error message
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forQuery(
        string $query,
        int $retryCount,
        string $finalError,
        ?Throwable $previous = null
    ): self {
        $context = [
            'query' => $query,
            'retry_type' => 'query',
            'final_error' => $finalError
        ];

        return new self($finalError, $retryCount, $context, $previous);
    }
}
