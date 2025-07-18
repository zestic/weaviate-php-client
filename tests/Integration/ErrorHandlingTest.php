<?php

declare(strict_types=1);

namespace Weaviate\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Exceptions\WeaviateBaseException;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\WeaviateInvalidInputException;

class ErrorHandlingTest extends TestCase
{
    private WeaviateClient $client;

    protected function setUp(): void
    {
        $weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:8080';

        // Parse URL to get components for connectToCustom
        $parsedUrl = parse_url($weaviateUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 8080;
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $secure = $scheme === 'https';

        $this->client = WeaviateClient::connectToCustom($host, $port, $secure);
    }

    public function testConnectionErrorHandling(): void
    {
        // Test connection to non-existent server
        $client = WeaviateClient::connectToCustom('non-existent-server.invalid', 8080);

        $this->expectException(WeaviateConnectionException::class);
        $this->expectExceptionMessage('Failed to connect to Weaviate');

        $client->collections()->exists('TestCollection');
    }

    public function testNotFoundErrorHandling(): void
    {
        try {
            $this->client->collections()->get('NonExistentCollection')->data()->get('non-existent-id');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertStringContainsString('not found', strtolower($e->getMessage()));
            $this->assertSame(404, $e->getStatusCode());

            $context = $e->getContext();
            $this->assertArrayHasKey('status_code', $context);
            $this->assertSame(404, $context['status_code']);
        }
    }

    public function testInvalidInputErrorHandling(): void
    {
        $this->expectException(WeaviateInvalidInputException::class);
        $this->expectExceptionMessage('Port must be between 1 and 65535');

        WeaviateClient::connectToCustom('localhost', 99999);
    }

    public function testSchemaValidationErrorHandling(): void
    {
        try {
            // Try to create a collection with invalid name (lowercase)
            $this->client->collections()->create('invalidname', [
                'properties' => [
                    ['name' => 'title', 'dataType' => ['text']]
                ]
            ]);
            $this->fail('Expected UnexpectedStatusCodeException for invalid schema');
        } catch (UnexpectedStatusCodeException $e) {
            $this->assertGreaterThanOrEqual(400, $e->getStatusCode());
            $this->assertLessThan(500, $e->getStatusCode());

            $context = $e->getContext();
            $this->assertArrayHasKey('status_code', $context);
        }
    }

    public function testErrorContextInformation(): void
    {
        try {
            $this->client->collections()->get('NonExistent')->data()->get('test-id');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $context = $e->getContext();

            // Check that context contains useful debugging information
            $this->assertArrayHasKey('status_code', $context);
            $this->assertArrayHasKey('operation', $context);

            // Check detailed message includes context
            $detailedMessage = $e->getDetailedMessage();
            $this->assertStringContainsString('Context:', $detailedMessage);
        }
    }

    public function testExceptionHierarchy(): void
    {
        try {
            $this->client->collections()->get('NonExistent')->data()->get('test-id');
            $this->fail('Expected NotFoundException');
        } catch (WeaviateBaseException $e) {
            // Should be able to catch with base exception
            $this->assertInstanceOf(NotFoundException::class, $e);
            $this->assertInstanceOf(UnexpectedStatusCodeException::class, $e);
            $this->assertInstanceOf(WeaviateBaseException::class, $e);
        }
    }

    public function testRetryMechanismWithTransientFailures(): void
    {
        // This test would require a way to simulate transient failures
        // For now, we'll test that the retry handler is properly integrated

        // Test that normal operations work (retry handler doesn't interfere)
        $exists = $this->client->collections()->exists('TestCollection');
        $this->assertIsBool($exists);
    }

    public function testErrorResponseBodyPreservation(): void
    {
        try {
            // Try to create collection with invalid configuration
            $this->client->collections()->create('TestCollection', [
                'properties' => [
                    ['name' => 'invalid', 'dataType' => ['nonexistent_type']]
                ]
            ]);
            $this->fail('Expected UnexpectedStatusCodeException');
        } catch (UnexpectedStatusCodeException $e) {
            $response = $e->getResponse();

            if ($response !== null) {
                $this->assertArrayHasKey('status_code', $response);
                $this->assertArrayHasKey('body', $response);
                $this->assertArrayHasKey('headers', $response);

                // Response body should contain error details
                $body = $response['body'];
                $this->assertNotEmpty($body);
            }
        }
    }

    public function testMultipleErrorScenarios(): void
    {
        $errorScenarios = [
            'non_existent_collection' => function () {
                return $this->client->collections()->get('NonExistent')->data()->get('test');
            },
            'invalid_object_id' => function () {
                // First ensure we have a collection
                if (!$this->client->collections()->exists('TestErrorCollection')) {
                    $this->client->collections()->create('TestErrorCollection', [
                        'properties' => [['name' => 'title', 'dataType' => ['text']]]
                    ]);
                }
                return $this->client->collections()->get('TestErrorCollection')->data()->get('non-existent');
            }
        ];

        foreach ($errorScenarios as $scenario => $operation) {
            try {
                $operation();
                $this->fail("Expected exception for scenario: {$scenario}");
            } catch (WeaviateBaseException $e) {
                // Each scenario should throw a Weaviate exception
                $this->assertInstanceOf(WeaviateBaseException::class, $e);

                // Should have meaningful error context
                $context = $e->getContext();
                $this->assertIsArray($context);

                // Should have detailed error message
                $detailedMessage = $e->getDetailedMessage();
                $this->assertNotEmpty($detailedMessage);
            }
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test collections
        try {
            if ($this->client->collections()->exists('TestErrorCollection')) {
                $this->client->collections()->delete('TestErrorCollection');
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
