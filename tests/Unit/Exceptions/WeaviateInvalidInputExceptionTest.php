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
        $context = ['min_value' => 0];

        $exception = WeaviateInvalidInputException::forParameter($parameter, $value, $expected, $context);

        $this->assertStringContainsString("Invalid value for parameter 'timeout'", $exception->getMessage());
        $this->assertStringContainsString('-5', $exception->getMessage());
        $this->assertStringContainsString('Expected: Positive number', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame($value, $resultContext['value']);
        $this->assertSame($expected, $resultContext['expected']);
        $this->assertSame(0, $resultContext['min_value']);
        $this->assertSame('parameter_validation', $resultContext['validation_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forMissingParameter
     */
    public function testForMissingParameter(): void
    {
        $parameter = 'api_key';
        $context = ['operation' => 'authentication'];

        $exception = WeaviateInvalidInputException::forMissingParameter($parameter, $context);

        $this->assertStringContainsString("Required parameter 'api_key' is missing", $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame('authentication', $resultContext['operation']);
        $this->assertSame('missing_parameter', $resultContext['validation_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forInvalidType
     */
    public function testForInvalidType(): void
    {
        $parameter = 'properties';
        $expectedType = 'array';
        $actualType = 'string';
        $context = ['function' => 'createCollection'];

        $exception = WeaviateInvalidInputException::forInvalidType($parameter, $expectedType, $actualType, $context);

        $this->assertStringContainsString("Parameter 'properties' must be of type array", $exception->getMessage());
        $this->assertStringContainsString('string given', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame($expectedType, $resultContext['expected_type']);
        $this->assertSame($actualType, $resultContext['actual_type']);
        $this->assertSame('createCollection', $resultContext['function']);
        $this->assertSame('type_validation', $resultContext['validation_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forInvalidFormat
     */
    public function testForInvalidFormat(): void
    {
        $parameter = 'uuid';
        $value = 'invalid-uuid';
        $expectedFormat = 'UUID v4 format';
        $context = ['example' => '550e8400-e29b-41d4-a716-446655440000'];

        $exception = WeaviateInvalidInputException::forInvalidFormat($parameter, $value, $expectedFormat, $context);

        $this->assertStringContainsString("Parameter 'uuid' has invalid format", $exception->getMessage());
        $this->assertStringContainsString('invalid-uuid', $exception->getMessage());
        $this->assertStringContainsString('Expected: UUID v4 format', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($parameter, $resultContext['parameter']);
        $this->assertSame($value, $resultContext['value']);
        $this->assertSame($expectedFormat, $resultContext['expected_format']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $resultContext['example']);
        $this->assertSame('format_validation', $resultContext['validation_type']);
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
        $exception = WeaviateInvalidInputException::forParameter('test', 'value', 'expected', [], $previous);

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
        $this->assertNull($context['value']);
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
        $this->assertSame($value, $context['value']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forMissingParameter
     */
    public function testForMissingParameterWithEmptyContext(): void
    {
        $exception = WeaviateInvalidInputException::forMissingParameter('required_param');

        $context = $exception->getContext();
        $this->assertSame('required_param', $context['parameter']);
        $this->assertSame('missing_parameter', $context['validation_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forInvalidType
     */
    public function testForInvalidTypeWithSameTypes(): void
    {
        $exception = WeaviateInvalidInputException::forInvalidType('param', 'string', 'string');

        $this->assertStringContainsString("Parameter 'param' must be of type string", $exception->getMessage());
        $this->assertStringContainsString('string given', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateInvalidInputException::forInvalidFormat
     */
    public function testForInvalidFormatWithEmptyValue(): void
    {
        $exception = WeaviateInvalidInputException::forInvalidFormat('param', '', 'non-empty string');

        $this->assertStringContainsString("Parameter 'param' has invalid format", $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertSame('', $context['value']);
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
        $this->assertFalse($context['value']);
    }
}
