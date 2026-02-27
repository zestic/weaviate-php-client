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
 * Is raised when invalid input is provided to the Weaviate client.
 *
 * This exception is thrown when the client detects invalid input before
 * making a request to the server. This includes:
 * - Invalid parameter types or values
 * - Missing required parameters
 * - Malformed data structures
 * - Invalid configuration options
 *
 * @example Handling invalid input
 * ```php
 * try {
 *     $client = WeaviateClientFactory::connectToCustom('localhost', 99999); // Invalid port
 * } catch (WeaviateInvalidInputException $e) {
 *     echo "Invalid input: " . $e->getMessage();
 *
 *     $context = $e->getContext();
 *     if (isset($context['parameter'])) {
 *         echo "Invalid parameter: " . $context['parameter'];
 *     }
 *
 *     if (isset($context['expected'])) {
 *         echo "Expected: " . $context['expected'];
 *     }
 * }
 * ```
 */
class WeaviateInvalidInputException extends WeaviateBaseException
{
    /**
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $context['error_type'] = 'invalid_input';

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Create for invalid parameter value
     *
     * @param string $parameter Parameter name
     * @param mixed $value Invalid value provided
     * @param string $expected Description of expected value
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forParameter(
        string $parameter,
        mixed $value,
        string $expected,
        ?Throwable $previous = null
    ): self {
        $valueStr = is_scalar($value) ? (string) $value : gettype($value);

        $message = "Invalid value for parameter '{$parameter}': {$valueStr}. Expected: {$expected}";

        $context = [
            'parameter' => $parameter,
            'provided_value' => $value,
            'expected' => $expected,
            'input_type' => 'parameter'
        ];

        return new self($message, $context, $previous);
    }

    /**
     * Create for missing required parameter
     *
     * @param string $parameter Parameter name
     * @param string $operation Operation that requires the parameter
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forMissingParameter(
        string $parameter,
        string $operation,
        ?Throwable $previous = null
    ): self {
        $message = "Missing required parameter '{$parameter}' for operation '{$operation}'";

        $context = [
            'parameter' => $parameter,
            'operation' => $operation,
            'input_type' => 'missing_parameter'
        ];

        return new self($message, $context, $previous);
    }

    /**
     * Create for invalid data structure
     *
     * @param string $structure Name of the data structure
     * @param string $issue Description of the issue
     * @param mixed $data The invalid data
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forDataStructure(
        string $structure,
        string $issue,
        mixed $data,
        ?Throwable $previous = null
    ): self {
        $message = "Invalid {$structure}: {$issue}";

        $context = [
            'structure' => $structure,
            'issue' => $issue,
            'provided_data' => $data,
            'input_type' => 'data_structure'
        ];

        return new self($message, $context, $previous);
    }

    /**
     * Create for invalid configuration
     *
     * @param string $configKey Configuration key
     * @param mixed $configValue Invalid configuration value
     * @param string $reason Reason why it's invalid
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forConfiguration(
        string $configKey,
        mixed $configValue,
        string $reason,
        ?Throwable $previous = null
    ): self {
        $valueStr = is_scalar($configValue) ? (string) $configValue : gettype($configValue);

        $message = "Invalid configuration for '{$configKey}': {$valueStr}. {$reason}";

        $context = [
            'config_key' => $configKey,
            'config_value' => $configValue,
            'reason' => $reason,
            'input_type' => 'configuration'
        ];

        return new self($message, $context, $previous);
    }

    /**
     * Create for invalid collection name
     *
     * @param string $collectionName Invalid collection name
     * @param string $reason Reason why it's invalid
     * @param Throwable|null $previous Previous exception
     * @return self
     */
    public static function forCollectionName(
        string $collectionName,
        string $reason,
        ?Throwable $previous = null
    ): self {
        $message = "Invalid collection name '{$collectionName}': {$reason}";

        $context = [
            'collection_name' => $collectionName,
            'reason' => $reason,
            'input_type' => 'collection_name',
            'suggestions' => [
                'Collection names must start with a capital letter',
                'Collection names can only contain letters, numbers, and underscores',
                'Collection names cannot contain spaces or special characters'
            ]
        ];

        return new self($message, $context, $previous);
    }
}
