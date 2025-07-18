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

namespace Weaviate\Retry;

use Throwable;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\WeaviateRetryException;
use Weaviate\Exceptions\WeaviateTimeoutException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;

/**
 * Handles retry logic for Weaviate operations with exponential backoff.
 *
 * Implements the same retry strategy as the Python client:
 * - Exponential backoff: 2^attempt seconds (1s, 2s, 4s, 8s)
 * - Configurable maximum retry attempts (default: 4)
 * - Only retries on specific error conditions
 * - Tracks all retry attempts for debugging
 *
 * @example Basic retry usage
 * ```php
 * $retryHandler = new RetryHandler(maxRetries: 3);
 *
 * $result = $retryHandler->execute(
 *     operation: 'get collection schema',
 *     callable: fn() => $connection->get('/v1/schema/MyCollection')
 * );
 * ```
 *
 * @example Custom retry configuration
 * ```php
 * $retryHandler = new RetryHandler(
 *     maxRetries: 5,
 *     baseDelaySeconds: 0.5,
 *     maxDelaySeconds: 30.0
 * );
 * ```
 */
class RetryHandler
{
    /**
     * @param int $maxRetries Maximum number of retry attempts
     * @param float $baseDelaySeconds Base delay for exponential backoff
     * @param float $maxDelaySeconds Maximum delay between retries
     */
    public function __construct(
        private readonly int $maxRetries = 4,
        private readonly float $baseDelaySeconds = 1.0,
        private readonly float $maxDelaySeconds = 60.0
    ) {
    }

    /**
     * Execute a callable with retry logic
     *
     * @template T
     * @param string $operation Description of the operation for error messages
     * @param callable(): T $callable The operation to execute
     * @return T The result of the successful operation
     * @throws WeaviateRetryException When all retry attempts are exhausted
     */
    public function execute(string $operation, callable $callable): mixed
    {
        $attempts = [];
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // If this is a retry attempt, wait before trying
                if ($attempt > 0) {
                    $delay = $this->calculateDelay($attempt);
                    usleep((int) ($delay * 1_000_000)); // Convert to microseconds
                }

                return $callable();
            } catch (Throwable $e) {
                $lastException = $e;

                // Record this attempt
                $attempts[] = [
                    'number' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e),
                    'timestamp' => time(),
                    'delay_before_next' => $attempt < $this->maxRetries ? $this->calculateDelay($attempt + 1) : null
                ];

                // Check if this error should be retried
                if (!$this->shouldRetry($e)) {
                    // Non-retriable error, throw it immediately
                    throw $e;
                }

                if ($attempt >= $this->maxRetries) {
                    break;
                }
            }
        }

        // All retries exhausted, throw retry exception
        throw WeaviateRetryException::withAttempts(
            $operation,
            count($attempts),
            $attempts,
            $lastException?->getMessage() ?? 'Unknown error',
            $lastException
        );
    }

    /**
     * Calculate delay for exponential backoff
     *
     * @param int $attempt Attempt number (1-based)
     * @return float Delay in seconds
     */
    private function calculateDelay(int $attempt): float
    {
        // Exponential backoff: base * (2 ^ (attempt - 1))
        $delay = $this->baseDelaySeconds * (2 ** ($attempt - 1));

        // Cap at maximum delay
        return min($delay, $this->maxDelaySeconds);
    }

    /**
     * Determine if an exception should trigger a retry
     *
     * Based on Python client logic:
     * - Connection errors: retry
     * - Timeout errors: retry
     * - 503 Service Unavailable: retry
     * - 502 Bad Gateway: retry
     * - 504 Gateway Timeout: retry
     * - Other errors: don't retry
     */
    private function shouldRetry(Throwable $exception): bool
    {
        // Always retry connection errors
        if ($exception instanceof WeaviateConnectionException) {
            return true;
        }

        // Always retry timeout errors
        if ($exception instanceof WeaviateTimeoutException) {
            return true;
        }

        // Retry specific HTTP status codes
        if ($exception instanceof UnexpectedStatusCodeException) {
            $statusCode = $exception->getStatusCode();
            return in_array($statusCode, [502, 503, 504], true);
        }

        // Don't retry other exceptions
        return false;
    }

    /**
     * Get the maximum number of retries
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Get the base delay in seconds
     */
    public function getBaseDelaySeconds(): float
    {
        return $this->baseDelaySeconds;
    }

    /**
     * Get the maximum delay in seconds
     */
    public function getMaxDelaySeconds(): float
    {
        return $this->maxDelaySeconds;
    }

    /**
     * Create a retry handler with custom configuration
     *
     * @param int $maxRetries Maximum retry attempts
     * @param float $baseDelaySeconds Base delay for backoff
     * @param float $maxDelaySeconds Maximum delay cap
     * @return self
     */
    public static function create(
        int $maxRetries = 4,
        float $baseDelaySeconds = 1.0,
        float $maxDelaySeconds = 60.0
    ): self {
        return new self($maxRetries, $baseDelaySeconds, $maxDelaySeconds);
    }

    /**
     * Create a retry handler optimized for connection operations
     *
     * @return self
     */
    public static function forConnection(): self
    {
        return new self(
            maxRetries: 3,
            baseDelaySeconds: 0.5,
            maxDelaySeconds: 10.0
        );
    }

    /**
     * Create a retry handler optimized for query operations
     *
     * @return self
     */
    public static function forQuery(): self
    {
        return new self(
            maxRetries: 2,
            baseDelaySeconds: 1.0,
            maxDelaySeconds: 30.0
        );
    }
}
