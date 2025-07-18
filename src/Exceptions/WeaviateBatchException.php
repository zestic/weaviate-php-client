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
 * Is raised if a batch operation to Weaviate fails in any way.
 *
 * This exception is thrown when batch operations (bulk inserts, updates, deletes)
 * fail due to various reasons such as:
 * - Individual object validation failures
 * - Partial batch failures
 * - Batch size limitations
 * - Server-side batch processing errors
 *
 * @example Handling batch errors
 * ```php
 * try {
 *     $client->batch()->insertObjects($largeDataset);
 * } catch (WeaviateBatchException $e) {
 *     echo "Batch operation failed: " . $e->getMessage();
 *
 *     $context = $e->getContext();
 *     if (isset($context['failed_objects'])) {
 *         echo "Failed objects: " . count($context['failed_objects']);
 *
 *         foreach ($context['failed_objects'] as $failure) {
 *             echo "Object {$failure['id']}: {$failure['error']}";
 *         }
 *     }
 *
 *     if (isset($context['successful_count'])) {
 *         echo "Successfully processed: " . $context['successful_count'] . " objects";
 *     }
 * }
 * ```
 */
class WeaviateBatchException extends WeaviateQueryException
{
    /**
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context including batch details
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $context['error_type'] = 'batch_failure';

        parent::__construct($message, 'Batch', $context, $previous);
    }

    /**
     * Create for partial batch failure
     *
     * @param int $totalObjects Total number of objects in batch
     * @param int $successfulCount Number of successfully processed objects
     * @param array<int, array<string, mixed>> $failures Details of failed objects
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forPartialFailure(
        int $totalObjects,
        int $successfulCount,
        array $failures,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $failedCount = count($failures);
        $message = "Batch operation partially failed: {$failedCount} of {$totalObjects} objects failed";

        $context = array_merge($context, [
            'total_objects' => $totalObjects,
            'successful_count' => $successfulCount,
            'failed_count' => $failedCount,
            'failed_objects' => $failures,
            'batch_type' => 'partial_failure'
        ]);

        return new self($message, $context, $previous);
    }

    /**
     * Create for complete batch failure
     *
     * @param int $totalObjects Total number of objects in batch
     * @param string $reason Reason for complete failure
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forCompleteFailure(
        int $totalObjects,
        string $reason,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $message = "Batch operation completely failed: {$reason}";

        $context = array_merge($context, [
            'total_objects' => $totalObjects,
            'successful_count' => 0,
            'failed_count' => $totalObjects,
            'failure_reason' => $reason,
            'batch_type' => 'complete_failure'
        ]);

        return new self($message, $context, $previous);
    }

    /**
     * Create for batch size limit exceeded
     *
     * @param int $requestedSize Requested batch size
     * @param int $maxAllowedSize Maximum allowed batch size
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forSizeLimit(
        int $requestedSize,
        int $maxAllowedSize,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $message = "Batch size {$requestedSize} exceeds maximum allowed size of {$maxAllowedSize}";

        $context = array_merge($context, [
            'requested_size' => $requestedSize,
            'max_allowed_size' => $maxAllowedSize,
            'batch_type' => 'size_limit_exceeded',
            'suggestions' => [
                "Reduce batch size to {$maxAllowedSize} or smaller",
                'Consider splitting the batch into multiple smaller batches',
                'Check server configuration for batch size limits'
            ]
        ]);

        return new self($message, $context, $previous);
    }

    /**
     * Create for batch timeout
     *
     * @param int $batchSize Size of the batch that timed out
     * @param float $timeoutSeconds Timeout value in seconds
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forTimeout(
        int $batchSize,
        float $timeoutSeconds,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $message = "Batch operation with {$batchSize} objects timed out after {$timeoutSeconds} seconds";

        $context = array_merge($context, [
            'batch_size' => $batchSize,
            'timeout_seconds' => $timeoutSeconds,
            'batch_type' => 'timeout',
            'suggestions' => [
                'Increase timeout configuration',
                'Reduce batch size',
                'Check server performance and load'
            ]
        ]);

        return new self($message, $context, $previous);
    }

    /**
     * Get the number of failed objects
     */
    public function getFailedCount(): int
    {
        return $this->context['failed_count'] ?? 0;
    }

    /**
     * Get the number of successful objects
     */
    public function getSuccessfulCount(): int
    {
        return $this->context['successful_count'] ?? 0;
    }

    /**
     * Get details of failed objects
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFailedObjects(): array
    {
        return $this->context['failed_objects'] ?? [];
    }

    /**
     * Get the total number of objects in the batch
     */
    public function getTotalObjects(): int
    {
        return $this->context['total_objects'] ?? 0;
    }

    /**
     * Check if this was a partial failure (some objects succeeded)
     */
    public function isPartialFailure(): bool
    {
        return $this->getSuccessfulCount() > 0 && $this->getFailedCount() > 0;
    }

    /**
     * Check if this was a complete failure (no objects succeeded)
     */
    public function isCompleteFailure(): bool
    {
        return $this->getSuccessfulCount() === 0 && $this->getTotalObjects() > 0;
    }
}
