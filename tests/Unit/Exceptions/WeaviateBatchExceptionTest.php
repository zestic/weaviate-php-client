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
use Weaviate\Exceptions\WeaviateBatchException;
use Weaviate\Exceptions\WeaviateQueryException;

class WeaviateBatchExceptionTest extends TestCase
{
    public function testCanCreateBatchException(): void
    {
        $exception = new WeaviateBatchException('Batch operation failed');

        $this->assertInstanceOf(WeaviateQueryException::class, $exception);
        $this->assertStringContainsString('Batch operation failed', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame('query_failure', $context['error_type']);
        $this->assertSame('Batch', $context['query_type']);
    }

    public function testCanCreateWithContext(): void
    {
        $context = ['batch_id' => 'test-123', 'operation' => 'insert'];
        $exception = new WeaviateBatchException('Batch failed', $context);

        $resultContext = $exception->getContext();
        $this->assertSame('test-123', $resultContext['batch_id']);
        $this->assertSame('insert', $resultContext['operation']);
        $this->assertSame('query_failure', $resultContext['error_type']);
    }

    public function testForCompleteFailure(): void
    {
        $totalObjects = 100;
        $reason = 'Server overloaded';
        $context = ['server_load' => 95];

        $exception = WeaviateBatchException::forCompleteFailure($totalObjects, $reason, $context);

        $this->assertStringContainsString('Batch operation completely failed', $exception->getMessage());
        $this->assertStringContainsString($reason, $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($totalObjects, $resultContext['total_objects']);
        $this->assertSame(0, $resultContext['successful_count']);
        $this->assertSame($totalObjects, $resultContext['failed_count']);
        $this->assertSame($reason, $resultContext['failure_reason']);
        $this->assertSame('complete_failure', $resultContext['batch_type']);
        $this->assertSame(95, $resultContext['server_load']);
    }

    public function testForPartialFailure(): void
    {
        $totalObjects = 100;
        $successfulCount = 75;
        $failedObjects = [
            ['id' => 'obj1', 'error' => 'Validation failed'],
            ['id' => 'obj2', 'error' => 'Duplicate key']
        ];

        $exception = WeaviateBatchException::forPartialFailure($totalObjects, $successfulCount, $failedObjects);

        $this->assertStringContainsString('Batch operation partially failed', $exception->getMessage());
        $this->assertStringContainsString('2 of 100 objects failed', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($totalObjects, $context['total_objects']);
        $this->assertSame($successfulCount, $context['successful_count']);
        $this->assertSame(2, $context['failed_count']);
        $this->assertSame($failedObjects, $context['failed_objects']);
        $this->assertSame('partial_failure', $context['batch_type']);
    }

    public function testForTimeout(): void
    {
        $batchSize = 50;
        $timeoutSeconds = 30.5;
        $context = ['retry_count' => 3];

        $exception = WeaviateBatchException::forTimeout($batchSize, $timeoutSeconds, $context);

        $this->assertStringContainsString('Batch operation with 50 objects timed out', $exception->getMessage());
        $this->assertStringContainsString('30.5 seconds', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($batchSize, $resultContext['batch_size']);
        $this->assertSame($timeoutSeconds, $resultContext['timeout_seconds']);
        $this->assertSame('timeout', $resultContext['batch_type']);
        $this->assertSame(3, $resultContext['retry_count']);
        $this->assertIsArray($resultContext['suggestions']);
        $this->assertContains('Increase timeout configuration', $resultContext['suggestions']);
    }

    public function testForTimeoutWithLargeTimeout(): void
    {
        $batchSize = 1000;
        $timeoutSeconds = 300.0;
        $context = ['retry_count' => 5, 'server_load' => 'high'];

        $exception = WeaviateBatchException::forTimeout($batchSize, $timeoutSeconds, $context);

        $this->assertStringContainsString('Batch operation with 1000 objects timed out', $exception->getMessage());
        $this->assertStringContainsString('300 seconds', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($batchSize, $resultContext['batch_size']);
        $this->assertSame($timeoutSeconds, $resultContext['timeout_seconds']);
        $this->assertSame('timeout', $resultContext['batch_type']);
        $this->assertSame(5, $resultContext['retry_count']);
        $this->assertSame('high', $resultContext['server_load']);
    }

    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new WeaviateBatchException('Batch failed', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testForCompleteFailureWithPreviousException(): void
    {
        $previous = new \Exception('Connection lost');
        $exception = WeaviateBatchException::forCompleteFailure(50, 'Network error', [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testForPartialFailureWithEmptyFailedObjects(): void
    {
        $exception = WeaviateBatchException::forPartialFailure(100, 100, []);

        $context = $exception->getContext();
        $this->assertSame(100, $context['total_objects']);
        $this->assertSame(100, $context['successful_count']);
        $this->assertSame(0, $context['failed_count']);
        $this->assertSame([], $context['failed_objects']);
    }

    public function testForTimeoutWithZeroTimeout(): void
    {
        $exception = WeaviateBatchException::forTimeout(10, 0.0);

        $this->assertStringContainsString('0 seconds', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame(0.0, $context['timeout_seconds']);
    }

    public function testForPartialFailureWithLargeNumbers(): void
    {
        $successfulCount = 9500;
        $failedCount = 500;
        $failedObjects = [
            ['id' => 'obj1', 'error' => 'Network timeout'],
            ['id' => 'obj2', 'error' => 'Validation failed']
        ];

        $totalObjects = $successfulCount + $failedCount;
        $exception = WeaviateBatchException::forPartialFailure($totalObjects, $successfulCount, $failedObjects);

        $this->assertStringContainsString('2 of 10000 objects failed', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($totalObjects, $context['total_objects']);
        $this->assertSame($successfulCount, $context['successful_count']);
        $this->assertSame(2, $context['failed_count']);
        $this->assertSame($failedObjects, $context['failed_objects']);
    }
}
