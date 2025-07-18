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

use Exception;
use Throwable;

/**
 * Weaviate base exception that all Weaviate exceptions should inherit from.
 *
 * This error can be used to catch any Weaviate exceptions, similar to the
 * Python client's WeaviateBaseError.
 *
 * @example Catching all Weaviate errors
 * ```php
 * try {
 *     $client->collections()->get('NonExistent');
 * } catch (WeaviateBaseException $e) {
 *     echo "Weaviate error: " . $e->getMessage();
 *     echo "Error context: " . json_encode($e->getContext());
 * }
 * ```
 */
class WeaviateBaseException extends Exception
{
    /**
     * Additional context about the error
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param string $message Error message specific to the context
     * @param array<string, mixed> $context Additional error context
     * @param int $code Error code (usually HTTP status code)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get additional error context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional error context
     *
     * @param array<string, mixed> $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context item
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get a formatted error message with context
     */
    public function getDetailedMessage(): string
    {
        $message = $this->getMessage();

        if (!empty($this->context)) {
            $contextStr = json_encode($this->context, JSON_PRETTY_PRINT);
            $message .= "\nContext: " . $contextStr;
        }

        return $message;
    }
}
