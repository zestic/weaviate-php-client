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
use Weaviate\Exceptions\WeaviateRetryException;
use Weaviate\Exceptions\WeaviateBaseException;

class WeaviateRetryExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testCanCreateRetryException(): void
    {
        $exception = new WeaviateRetryException('Connection failed', 3);

        $this->assertInstanceOf(WeaviateBaseException::class, $exception);
        $this->assertStringContainsString('The request to Weaviate failed after 3 retries', $exception->getMessage());
        $this->assertStringContainsString('Final error: Connection failed', $exception->getMessage());
        $this->assertSame(3, $exception->getRetryCount());

        $context = $exception->getContext();
        $this->assertSame(3, $context['retry_count']);
        $this->assertSame('retry_exhausted', $context['error_type']);
        $this->assertIsArray($context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['operation' => 'get_object', 'url' => 'http://localhost:8080'];
        $exception = new WeaviateRetryException('Timeout', 5, $context);

        $resultContext = $exception->getContext();
        $this->assertSame('get_object', $resultContext['operation']);
        $this->assertSame('http://localhost:8080', $resultContext['url']);
        $this->assertSame(5, $resultContext['retry_count']);
        $this->assertSame('retry_exhausted', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::getRetryCount
     */
    public function testGetRetryCount(): void
    {
        $exception = new WeaviateRetryException('Error', 7);

        $this->assertSame(7, $exception->getRetryCount());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::getRetryAttempts
     */
    public function testGetRetryAttempts(): void
    {
        $attempts = [
            ['number' => 1, 'error' => 'Connection timeout'],
            ['number' => 2, 'error' => 'Server unavailable']
        ];
        $context = ['retry_attempts' => $attempts];
        $exception = new WeaviateRetryException('Failed', 2, $context);

        $this->assertSame($attempts, $exception->getRetryAttempts());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::getRetryAttempts
     */
    public function testGetRetryAttemptsReturnsNullWhenNotSet(): void
    {
        $exception = new WeaviateRetryException('Failed', 1);

        $this->assertNull($exception->getRetryAttempts());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::withAttempts
     */
    public function testWithAttempts(): void
    {
        $operation = 'create_object';
        $retryCount = 3;
        $attempts = [
            ['number' => 1, 'error' => 'Timeout', 'duration' => 1.5],
            ['number' => 2, 'error' => 'Connection refused', 'duration' => 2.0],
            ['number' => 3, 'error' => 'Server error', 'duration' => 3.0]
        ];
        $finalError = 'All retries exhausted';

        $exception = WeaviateRetryException::withAttempts($operation, $retryCount, $attempts, $finalError);

        $this->assertStringContainsString('The request to Weaviate failed after 3 retries', $exception->getMessage());
        $this->assertStringContainsString('Final error: All retries exhausted', $exception->getMessage());
        $this->assertSame($retryCount, $exception->getRetryCount());

        $context = $exception->getContext();
        $this->assertSame($operation, $context['operation']);
        $this->assertSame($attempts, $context['retry_attempts']);
        $this->assertSame($finalError, $context['final_error']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forConnection
     */
    public function testForConnection(): void
    {
        $url = 'http://localhost:8080';
        $retryCount = 4;
        $finalError = 'Connection refused';

        $exception = WeaviateRetryException::forConnection($url, $retryCount, $finalError);

        $this->assertStringContainsString('The request to Weaviate failed after 4 retries', $exception->getMessage());
        $this->assertStringContainsString('Final error: Connection refused', $exception->getMessage());
        $this->assertSame($retryCount, $exception->getRetryCount());

        $context = $exception->getContext();
        $this->assertSame($url, $context['url']);
        $this->assertSame('connection', $context['retry_type']);
        $this->assertSame($finalError, $context['final_error']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forQuery
     */
    public function testForQuery(): void
    {
        $query = 'GET /v1/objects';
        $retryCount = 2;
        $finalError = 'Query timeout';

        $exception = WeaviateRetryException::forQuery($query, $retryCount, $finalError);

        $this->assertStringContainsString('The request to Weaviate failed after 2 retries', $exception->getMessage());
        $this->assertStringContainsString('Final error: Query timeout', $exception->getMessage());
        $this->assertSame($retryCount, $exception->getRetryCount());

        $context = $exception->getContext();
        $this->assertSame($query, $context['query']);
        $this->assertSame('query', $context['retry_type']);
        $this->assertSame($finalError, $context['final_error']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new WeaviateRetryException('Retry failed', 3, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::withAttempts
     */
    public function testWithAttemptsWithPreviousException(): void
    {
        $previous = new \Exception('Final failure');
        $exception = WeaviateRetryException::withAttempts('test', 1, [], 'Error', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forConnection
     */
    public function testForConnectionWithPreviousException(): void
    {
        $previous = new \Exception('Connection error');
        $exception = WeaviateRetryException::forConnection('http://test', 2, 'Failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forQuery
     */
    public function testForQueryWithPreviousException(): void
    {
        $previous = new \Exception('Query error');
        $exception = WeaviateRetryException::forQuery('SELECT *', 1, 'Failed', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testWithZeroRetries(): void
    {
        $exception = new WeaviateRetryException('Immediate failure', 0);

        $this->assertStringContainsString('failed after 0 retries', $exception->getMessage());
        $this->assertSame(0, $exception->getRetryCount());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::withAttempts
     */
    public function testWithAttemptsWithEmptyAttempts(): void
    {
        $exception = WeaviateRetryException::withAttempts('operation', 0, [], 'No attempts made');

        $context = $exception->getContext();
        $this->assertSame([], $context['retry_attempts']);
        $this->assertSame(0, $exception->getRetryCount());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forConnection
     */
    public function testForConnectionWithEmptyUrl(): void
    {
        $exception = WeaviateRetryException::forConnection('', 1, 'Connection failed');

        $context = $exception->getContext();
        $this->assertSame('', $context['url']);
        $this->assertSame('connection', $context['retry_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::forQuery
     */
    public function testForQueryWithEmptyQuery(): void
    {
        $exception = WeaviateRetryException::forQuery('', 1, 'Query failed');

        $context = $exception->getContext();
        $this->assertSame('', $context['query']);
        $this->assertSame('query', $context['retry_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testSuggestionsAreIncluded(): void
    {
        $exception = new WeaviateRetryException('Failed', 3);

        $context = $exception->getContext();
        $this->assertIsArray($context['suggestions']);
        $this->assertContains('Check Weaviate server status and logs', $context['suggestions']);
        $this->assertContains('Verify network connectivity is stable', $context['suggestions']);
        $this->assertContains('Consider increasing retry limits if appropriate', $context['suggestions']);
        $this->assertContains('Check if the operation is valid and properly formatted', $context['suggestions']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::__construct
     */
    public function testWithEmptyMessage(): void
    {
        $exception = new WeaviateRetryException('', 2);

        $this->assertStringContainsString('The request to Weaviate failed after 2 retries', $exception->getMessage());
        $this->assertStringContainsString('Final error:', $exception->getMessage());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateRetryException::withAttempts
     */
    public function testWithAttemptsWithEmptyOperation(): void
    {
        $exception = WeaviateRetryException::withAttempts('', 1, [], 'Error');

        $context = $exception->getContext();
        $this->assertSame('', $context['operation']);
    }
}
