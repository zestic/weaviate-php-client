<?php

declare(strict_types=1);

/*
 * Copyright 2024 Zestic
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

namespace Weaviate\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\WeaviateInvalidInputException;
use Weaviate\Exceptions\WeaviateBaseException;

class WeaviateInvalidInputExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::__construct
     */
    public function testCanCreateInvalidInputException(): void
    {
        $exception = new WeaviateInvalidInputException('Invalid parameter value');

        $this->assertInstanceOf(WeaviateBaseException::class, $exception);
        $this->assertSame('Invalid parameter value', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('invalid_input', $context['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['parameter' => 'port', 'value' => 99999];
        $exception = new WeaviateInvalidInputException('Invalid port', $context);

        $resultContext = $exception->getContext();
        $this->assertSame('port', $resultContext['parameter']);
        $this->assertSame(99999, $resultContext['value']);
        $this->assertSame('invalid_input', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forParameter
     */
    public function testForParameter(): void
    {
        $parameter = 'timeout';
        $value = -5;
        $expected = 'Positive number';

        $exception = WeaviateInvalidInputException::forParameter($parameter, $value, $expected);

        $this->assertStringContainsString("Invalid value for parameter 'timeout'", $exception->getMessage());
        $this->assertStringContainsString('-5', $exception->getMessage());
        $this->assertStringContainsString('Expected: Positive number', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame($value, $resultContext['provided_value']);
        $this->assertSame($expected, $resultContext['expected']);
        $this->assertSame('parameter', $resultContext['input_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forMissingParameter
     */
    public function testForMissingParameter(): void
    {
        $parameter = 'api_key';
        $operation = 'authentication';

        $exception = WeaviateInvalidInputException::forMissingParameter($parameter, $operation);

        $this->assertStringContainsString("Missing required parameter 'api_key'", $exception->getMessage());
        $this->assertStringContainsString("for operation 'authentication'", $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame($operation, $resultContext['operation']);
        $this->assertSame('missing_parameter', $resultContext['input_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forDataStructure
     */
    public function testForDataStructure(): void
    {
        $structure = 'properties';
        $issue = 'must be an array';
        $data = 'invalid_string';

        $exception = WeaviateInvalidInputException::forDataStructure($structure, $issue, $data);

        $this->assertStringContainsString("Invalid properties: must be an array", $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($structure, $resultContext['structure']);
        $this->assertSame($issue, $resultContext['issue']);
        $this->assertSame($data, $resultContext['provided_data']);
        $this->assertSame('data_structure', $resultContext['input_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forConfiguration
     */
    public function testForConfiguration(): void
    {
        $configKey = 'timeout';
        $configValue = -1;
        $reason = 'Timeout must be positive';

        $exception = WeaviateInvalidInputException::forConfiguration($configKey, $configValue, $reason);

        $this->assertStringContainsString("Invalid configuration for 'timeout'", $exception->getMessage());
        $this->assertStringContainsString('-1', $exception->getMessage());
        $this->assertStringContainsString('Timeout must be positive', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($configKey, $resultContext['config_key']);
        $this->assertSame($configValue, $resultContext['config_value']);
        $this->assertSame($reason, $resultContext['reason']);
        $this->assertSame('configuration', $resultContext['input_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \InvalidArgumentException('Original error');
        $exception = new WeaviateInvalidInputException('Invalid input', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forParameter
     */
    public function testForParameterWithPreviousException(): void
    {
        $previous = new \Exception('Validation failed');
        $exception = WeaviateInvalidInputException::forParameter('test', 'value', 'expected', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forParameter
     */
    public function testForParameterWithNullValue(): void
    {
        $exception = WeaviateInvalidInputException::forParameter('param', null, 'non-null value');

        $this->assertStringContainsString('null', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertNull($context['provided_value']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forParameter
     */
    public function testForParameterWithArrayValue(): void
    {
        $value = ['key' => 'value'];
        $exception = WeaviateInvalidInputException::forParameter('param', $value, 'string');

        $this->assertStringContainsString('Array', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($value, $context['provided_value']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forMissingParameter
     */
    public function testForMissingParameterWithEmptyOperation(): void
    {
        $exception = WeaviateInvalidInputException::forMissingParameter('required_param', '');

        $context = $exception->getContext();
        $this->assertSame('required_param', $context['parameter']);
        $this->assertSame('', $context['operation']);
        $this->assertSame('missing_parameter', $context['input_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forCollectionName
     */
    public function testForCollectionName(): void
    {
        $collectionName = 'invalid-name!';
        $reason = 'Collection names cannot contain special characters';

        $exception = WeaviateInvalidInputException::forCollectionName($collectionName, $reason);

        $this->assertStringContainsString("Invalid collection name 'invalid-name!'", $exception->getMessage());
        $this->assertStringContainsString($reason, $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($collectionName, $context['collection_name']);
        $this->assertSame($reason, $context['reason']);
        $this->assertSame('collection_name', $context['input_type']);
        $this->assertIsArray($context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::__construct
     */
    public function testSuggestionsAreIncluded(): void
    {
        $exception = new WeaviateInvalidInputException('Invalid input');

        $context = $exception->getContext();
        $this->assertIsArray($context['suggestions']);
        $this->assertContains('Check the parameter documentation for valid values', $context['suggestions']);
        $this->assertContains('Ensure all required parameters are provided', $context['suggestions']);
        $this->assertContains('Validate parameter types match the expected format', $context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forParameter
     */
    public function testForParameterWithBooleanValue(): void
    {
        $exception = WeaviateInvalidInputException::forParameter('enabled', false, 'true');

        $this->assertStringContainsString('false', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertFalse($context['provided_value']);
    }
}
