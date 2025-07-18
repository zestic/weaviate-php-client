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
 * Is raised when a request to Weaviate times out.
 *
 * This exception is thrown when a request takes longer than the configured
 * timeout period. This can happen due to:
 * - Server overload
 * - Complex queries taking too long
 * - Network latency issues
 * - Large data operations
 *
 * @example Handling timeout errors
 * ```php
 * try {
 *     $client->collections()->create('LargeCollection', $complexConfig);
 * } catch (WeaviateTimeoutException $e) {
 *     echo "Request timed out: " . $e->getMessage();
 *
 *     $context = $e->getContext();
 *     if (isset($context['timeout_seconds'])) {
 *         echo "Timeout was set to: " . $context['timeout_seconds'] . " seconds";
 *         echo "Consider increasing the timeout or simplifying the operation";
 *     }
 * }
 * ```
 */
class WeaviateTimeoutException extends WeaviateBaseException
{
    /**
     * @param string $message Error message
     * @param float|null $timeoutSeconds The timeout value that was exceeded
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'The request to Weaviate timed out while awaiting a response',
        ?float $timeoutSeconds = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        if ($timeoutSeconds !== null) {
            $message .= " (timeout: {$timeoutSeconds}s)";
            $context['timeout_seconds'] = $timeoutSeconds;
        }

        $context['error_type'] = 'timeout';
        $context['suggestions'] = [
            'Increase the timeout configuration for your client',
            'Simplify the operation if possible',
            'Check server performance and load',
            'Consider breaking large operations into smaller chunks'
        ];

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Create for connection timeout
     *
     * @param string $url The URL that timed out
     * @param float $timeoutSeconds Timeout value in seconds
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forConnection(
        string $url,
        float $timeoutSeconds,
        ?Throwable $previous = null
    ): self {
        return new self(
            "Connection to Weaviate at {$url} timed out",
            $timeoutSeconds,
            [
                'url' => $url,
                'timeout_type' => 'connection'
            ],
            $previous
        );
    }

    /**
     * Create for query timeout
     *
     * @param string $operation The operation that timed out
     * @param float $timeoutSeconds Timeout value in seconds
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forQuery(
        string $operation,
        float $timeoutSeconds,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['operation'] = $operation;
        $context['timeout_type'] = 'query';

        return new self(
            "Query operation '{$operation}' timed out",
            $timeoutSeconds,
            $context,
            $previous
        );
    }

    /**
     * Create for batch operation timeout
     *
     * @param int $batchSize Size of the batch that timed out
     * @param float $timeoutSeconds Timeout value in seconds
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forBatch(
        int $batchSize,
        float $timeoutSeconds,
        array $context = [],
        ?Throwable $previous = null
    ): self {
        $context['batch_size'] = $batchSize;
        $context['timeout_type'] = 'batch';

        return new self(
            "Batch operation with {$batchSize} items timed out",
            $timeoutSeconds,
            $context,
            $previous
        );
    }

    /**
     * Get the timeout value that was exceeded
     */
    public function getTimeoutSeconds(): ?float
    {
        return $this->context['timeout_seconds'] ?? null;
    }
}
