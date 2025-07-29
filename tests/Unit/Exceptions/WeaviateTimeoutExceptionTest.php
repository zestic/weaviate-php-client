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
use Weaviate\Exceptions\WeaviateTimeoutException;
use Weaviate\Exceptions\WeaviateBaseException;

class WeaviateTimeoutExceptionTest extends TestCase
{
    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testCanCreateTimeoutException(): void
    {
        $exception = new WeaviateTimeoutException('Operation timed out', 30.0);

        $this->assertInstanceOf(WeaviateBaseException::class, $exception);
        $this->assertStringContainsString('Operation timed out', $exception->getMessage());
        $this->assertStringContainsString('30 seconds', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertSame(30.0, $context['timeout_seconds']);
        $this->assertSame('timeout', $context['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testCanCreateWithContext(): void
    {
        $context = ['operation' => 'query', 'retry_count' => 3];
        $exception = new WeaviateTimeoutException('Query timed out', 15.5, $context);

        $resultContext = $exception->getContext();
        $this->assertSame('query', $resultContext['operation']);
        $this->assertSame(3, $resultContext['retry_count']);
        $this->assertSame(15.5, $resultContext['timeout_seconds']);
        $this->assertSame('timeout', $resultContext['error_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forConnection
     */
    public function testForConnection(): void
    {
        $url = 'http://localhost:8080';
        $timeoutSeconds = 10.0;
        $context = ['retry_attempt' => 2];

        $exception = WeaviateTimeoutException::forConnection($url, $timeoutSeconds, $context);

        $this->assertStringContainsString('Connection to', $exception->getMessage());
        $this->assertStringContainsString($url, $exception->getMessage());
        $this->assertStringContainsString('timed out', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($url, $resultContext['url']);
        $this->assertSame($timeoutSeconds, $resultContext['timeout_seconds']);
        $this->assertSame('connection', $resultContext['timeout_type']);
        $this->assertSame(2, $resultContext['retry_attempt']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forQuery
     */
    public function testForQuery(): void
    {
        $query = 'SELECT * FROM Article';
        $timeoutSeconds = 45.0;
        $context = ['collection' => 'Article'];

        $exception = WeaviateTimeoutException::forQuery($query, $timeoutSeconds, $context);

        $this->assertStringContainsString('Query execution timed out', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($query, $resultContext['query']);
        $this->assertSame($timeoutSeconds, $resultContext['timeout_seconds']);
        $this->assertSame('query', $resultContext['timeout_type']);
        $this->assertSame('Article', $resultContext['collection']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forBatch
     */
    public function testForBatch(): void
    {
        $batchSize = 100;
        $timeoutSeconds = 60.0;
        $context = ['operation_type' => 'insert'];

        $exception = WeaviateTimeoutException::forBatch($batchSize, $timeoutSeconds, $context);

        $this->assertStringContainsString('Batch operation with 100 items timed out', $exception->getMessage());

        $resultContext = $exception->getContext();
        $this->assertSame($batchSize, $resultContext['batch_size']);
        $this->assertSame($timeoutSeconds, $resultContext['timeout_seconds']);
        $this->assertSame('batch', $resultContext['timeout_type']);
        $this->assertSame('insert', $resultContext['operation_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::getTimeoutSeconds
     */
    public function testGetTimeoutSeconds(): void
    {
        $exception = new WeaviateTimeoutException('Timeout', 25.5);

        $this->assertSame(25.5, $exception->getTimeoutSeconds());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::getTimeoutSeconds
     */
    public function testGetTimeoutSecondsReturnsNullWhenNotSet(): void
    {
        $exception = new WeaviateTimeoutException('Timeout', 0.0, ['timeout_seconds' => null]);

        $this->assertNull($exception->getTimeoutSeconds());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = new WeaviateTimeoutException('Timeout occurred', 30.0, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forConnection
     */
    public function testForConnectionWithPreviousException(): void
    {
        $previous = new \Exception('Connection refused');
        $exception = WeaviateTimeoutException::forConnection('http://localhost:8080', 10.0, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testWithZeroTimeout(): void
    {
        $exception = new WeaviateTimeoutException('Immediate timeout', 0.0);

        $this->assertStringContainsString('0 seconds', $exception->getMessage());
        $this->assertSame(0.0, $exception->getTimeoutSeconds());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testWithNegativeTimeout(): void
    {
        $exception = new WeaviateTimeoutException('Invalid timeout', -5.0);

        $this->assertStringContainsString('-5 seconds', $exception->getMessage());
        $this->assertSame(-5.0, $exception->getTimeoutSeconds());
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forQuery
     */
    public function testForQueryWithEmptyQuery(): void
    {
        $exception = WeaviateTimeoutException::forQuery('', 30.0);

        $context = $exception->getContext();
        $this->assertSame('', $context['query']);
        $this->assertSame('query', $context['timeout_type']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::forBatch
     */
    public function testForBatchWithZeroSize(): void
    {
        $exception = WeaviateTimeoutException::forBatch(0, 30.0);

        $this->assertStringContainsString('0 items', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertSame(0, $context['batch_size']);
    }

    /**
     * @covers \Weaviate\Exceptions\WeaviateTimeoutException::__construct
     */
    public function testTimeoutSuggestionsAreIncluded(): void
    {
        $exception = new WeaviateTimeoutException('Timeout', 30.0);

        $context = $exception->getContext();
        $this->assertIsArray($context['suggestions']);
        $this->assertContains('Increase timeout configuration', $context['suggestions']);
        $this->assertContains('Check server performance and load', $context['suggestions']);
        $this->assertContains('Verify network connectivity is stable', $context['suggestions']);
    }
}
