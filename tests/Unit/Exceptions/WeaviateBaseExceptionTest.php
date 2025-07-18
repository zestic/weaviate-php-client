<?php

declare(strict_types=1);

namespace Weaviate\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\WeaviateBaseException;

class WeaviateBaseExceptionTest extends TestCase
{
    public function testCanCreateBaseException(): void
    {
        $exception = new WeaviateBaseException('Test message');

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame([], $exception->getContext());
    }

    public function testCanCreateWithContext(): void
    {
        $context = ['key' => 'value', 'number' => 42];
        $exception = new WeaviateBaseException('Test message', $context);

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame($context, $exception->getContext());
    }

    public function testCanSetContext(): void
    {
        $exception = new WeaviateBaseException('Test message');
        $context = ['new' => 'context'];

        $result = $exception->setContext($context);

        $this->assertSame($exception, $result);
        $this->assertSame($context, $exception->getContext());
    }

    public function testCanAddContext(): void
    {
        $exception = new WeaviateBaseException('Test message', ['existing' => 'value']);

        $result = $exception->addContext('new_key', 'new_value');

        $this->assertSame($exception, $result);
        $this->assertSame([
            'existing' => 'value',
            'new_key' => 'new_value'
        ], $exception->getContext());
    }

    public function testGetDetailedMessage(): void
    {
        $context = ['operation' => 'test', 'status' => 404];
        $exception = new WeaviateBaseException('Test error', $context);

        $detailedMessage = $exception->getDetailedMessage();

        $this->assertStringContainsString('Test error', $detailedMessage);
        $this->assertStringContainsString('Context:', $detailedMessage);
        $this->assertStringContainsString('"operation": "test"', $detailedMessage);
        $this->assertStringContainsString('"status": 404', $detailedMessage);
    }

    public function testGetDetailedMessageWithoutContext(): void
    {
        $exception = new WeaviateBaseException('Test error');

        $detailedMessage = $exception->getDetailedMessage();

        $this->assertSame('Test error', $detailedMessage);
    }

    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new WeaviateBaseException('Current error', [], 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(500, $exception->getCode());
    }
}
