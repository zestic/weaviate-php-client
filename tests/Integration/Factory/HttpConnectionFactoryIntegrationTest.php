<?php

declare(strict_types=1);

namespace Weaviate\Tests\Integration\Factory;

use Weaviate\Tests\TestCase;
use Weaviate\Factory\HttpConnectionFactory;

class HttpConnectionFactoryIntegrationTest extends TestCase
{
    public function testCreateProvidesWorkingHttpConnection(): void
    {
        $this->skipIfWeaviateNotAvailable();

        $baseUrl = $this->getWeaviateUrl();

        $connection = HttpConnectionFactory::create($baseUrl);

        $this->assertInstanceOf(\Weaviate\Connection\HttpConnection::class, $connection);

        // Use HEAD to check readiness via the connection implementation
        $ready = $connection->head('/v1/.well-known/ready');

        $this->assertTrue($ready, 'Weaviate should report ready via HTTP HEAD');
    }
}
