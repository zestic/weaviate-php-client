<?php

declare(strict_types=1);

namespace Weaviate\Tests\Unit\Retry;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\WeaviateRetryException;
use Weaviate\Exceptions\WeaviateTimeoutException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Retry\RetryHandler;

class RetryHandlerTest extends TestCase
{
    public function testCanCreateRetryHandler(): void
    {
        $handler = new RetryHandler(3, 0.5, 10.0);

        $this->assertSame(3, $handler->getMaxRetries());
        $this->assertSame(0.5, $handler->getBaseDelaySeconds());
        $this->assertSame(10.0, $handler->getMaxDelaySeconds());
    }

    public function testExecuteSuccessfulOperation(): void
    {
        $handler = new RetryHandler(3);
        $expectedResult = ['success' => true];

        $result = $handler->execute('test operation', fn() => $expectedResult);

        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteWithRetriableError(): void
    {
        $handler = new RetryHandler(2, 0.001); // Very short delay for testing
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new WeaviateConnectionException('Connection failed');
            }
            return ['success' => true, 'attempts' => $callCount];
        };

        $result = $handler->execute('test operation', $operation);

        $this->assertSame(['success' => true, 'attempts' => 3], $result);
        $this->assertSame(3, $callCount);
    }

    public function testExecuteWithNonRetriableError(): void
    {
        $handler = new RetryHandler(3);
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            throw new \InvalidArgumentException('Invalid input');
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $handler->execute('test operation', $operation);

        // Should not retry non-retriable errors
        // @phpstan-ignore-next-line - This line is unreachable due to expectException, but it's here for clarity
        $this->assertSame(1, $callCount);
    }

    public function testExecuteExhaustsRetries(): void
    {
        $handler = new RetryHandler(2, 0.001); // Very short delay for testing
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            throw new WeaviateConnectionException('Always fails');
        };

        $this->expectException(WeaviateRetryException::class);
        $this->expectExceptionMessage('The request to Weaviate failed after 3 retries');

        $handler->execute('test operation', $operation);

        // Should try initial + 2 retries = 3 total attempts
        // @phpstan-ignore-next-line - This line is unreachable due to expectException, but it's here for clarity
        $this->assertSame(3, $callCount);
    }

    public function testShouldRetryConnectionErrors(): void
    {
        $handler = new RetryHandler(1, 0.001);
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new WeaviateConnectionException('Connection failed');
            }
            return 'success';
        };

        $result = $handler->execute('test', $operation);

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }

    public function testShouldRetryTimeoutErrors(): void
    {
        $handler = new RetryHandler(1, 0.001);
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new WeaviateTimeoutException('Request timed out');
            }
            return 'success';
        };

        $result = $handler->execute('test', $operation);

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }

    public function testShouldRetrySpecificHttpStatusCodes(): void
    {
        $handler = new RetryHandler(1, 0.001);
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new UnexpectedStatusCodeException('Service unavailable', 503);
            }
            return 'success';
        };

        $result = $handler->execute('test', $operation);

        $this->assertSame('success', $result);
        $this->assertSame(2, $callCount);
    }

    public function testShouldNotRetryClientErrors(): void
    {
        $handler = new RetryHandler(3);
        $callCount = 0;

        $operation = function () use (&$callCount) {
            $callCount++;
            throw new UnexpectedStatusCodeException('Bad request', 400);
        };

        $this->expectException(UnexpectedStatusCodeException::class);

        $handler->execute('test', $operation);

        // Should not retry 400 errors
        // @phpstan-ignore-next-line - This line is unreachable due to expectException, but it's here for clarity
        $this->assertSame(1, $callCount);
    }

    public function testCreateFactoryMethods(): void
    {
        $connectionHandler = RetryHandler::forConnection();
        $this->assertSame(3, $connectionHandler->getMaxRetries());
        $this->assertSame(0.5, $connectionHandler->getBaseDelaySeconds());
        $this->assertSame(10.0, $connectionHandler->getMaxDelaySeconds());

        $queryHandler = RetryHandler::forQuery();
        $this->assertSame(2, $queryHandler->getMaxRetries());
        $this->assertSame(1.0, $queryHandler->getBaseDelaySeconds());
        $this->assertSame(30.0, $queryHandler->getMaxDelaySeconds());
    }

    public function testRetryExceptionContainsAttemptDetails(): void
    {
        $handler = new RetryHandler(1, 0.001);

        $operation = function () {
            throw new WeaviateConnectionException('Always fails');
        };

        try {
            $handler->execute('test operation', $operation);
            // This line is reachable if no exception is thrown, which would be a test failure
            // @phpstan-ignore-next-line
            $this->fail('Expected WeaviateRetryException');
        } catch (WeaviateRetryException $e) {
            $this->assertSame(2, $e->getRetryCount());

            $attempts = $e->getRetryAttempts();
            $this->assertNotNull($attempts);
            $this->assertCount(2, $attempts);

            $this->assertSame(1, $attempts[0]['number']);
            $this->assertStringContainsString('Always fails', $attempts[0]['error']);

            $this->assertSame(2, $attempts[1]['number']);
            $this->assertStringContainsString('Always fails', $attempts[1]['error']);
        }
    }
}
