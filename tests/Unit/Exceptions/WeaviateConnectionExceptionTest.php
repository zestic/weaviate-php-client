<?php

declare(strict_types=1);

namespace Weaviate\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Weaviate\Exceptions\WeaviateConnectionException;

class WeaviateConnectionExceptionTest extends TestCase
{
    public function testCanCreateConnectionException(): void
    {
        $exception = new WeaviateConnectionException('Connection failed');

        $this->assertSame('Connection failed', $exception->getMessage());
        $this->assertSame([], $exception->getContext());
    }

    public function testCanCreateFromNetworkError(): void
    {
        $url = 'http://localhost:8080';
        $error = 'Connection refused';

        $exception = WeaviateConnectionException::fromNetworkError($url, $error);

        $this->assertStringContainsString($url, $exception->getMessage());
        $this->assertStringContainsString($error, $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($url, $context['url']);
        $this->assertSame($error, $context['network_error']);
        $this->assertSame('network_error', $context['type']);
    }

    public function testCanCreateFromTimeout(): void
    {
        $url = 'http://localhost:8080';
        $timeout = 30.0;

        $exception = WeaviateConnectionException::fromTimeout($url, $timeout);

        $this->assertStringContainsString($url, $exception->getMessage());
        $this->assertStringContainsString('30', $exception->getMessage());
        $this->assertStringContainsString('timed out', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($url, $context['url']);
        $this->assertSame($timeout, $context['timeout']);
        $this->assertSame('timeout', $context['type']);
    }

    public function testCanCreateFromSslError(): void
    {
        $url = 'https://localhost:8080';
        $sslError = 'SSL certificate verification failed';

        $exception = WeaviateConnectionException::fromSslError($url, $sslError);

        $this->assertStringContainsString($url, $exception->getMessage());
        $this->assertStringContainsString($sslError, $exception->getMessage());
        $this->assertStringContainsString('SSL/TLS error', $exception->getMessage());

        $context = $exception->getContext();
        $this->assertSame($url, $context['url']);
        $this->assertSame($sslError, $context['ssl_error']);
        $this->assertSame('ssl_error', $context['type']);
    }

    public function testExceptionChaining(): void
    {
        $previous = new \RuntimeException('Network error');
        $exception = WeaviateConnectionException::fromNetworkError(
            'http://localhost:8080',
            'Connection failed',
            $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
    }
}
