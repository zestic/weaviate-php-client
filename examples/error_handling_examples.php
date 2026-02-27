<?php

declare(strict_types=1);

/*
 * Comprehensive Error Handling Examples for Weaviate PHP Client
 * 
 * This file demonstrates all aspects of error handling in the Weaviate PHP client,
 * including exception types, retry mechanisms, and best practices.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;
use Weaviate\Exceptions\WeaviateBaseException;
use Weaviate\Exceptions\WeaviateConnectionException;
use Weaviate\Exceptions\WeaviateTimeoutException;
use Weaviate\Exceptions\WeaviateRetryException;
use Weaviate\Exceptions\UnexpectedStatusCodeException;
use Weaviate\Exceptions\InsufficientPermissionsException;
use Weaviate\Exceptions\NotFoundException;
use Weaviate\Exceptions\WeaviateInvalidInputException;
use Weaviate\Retry\RetryHandler;

echo "=== Weaviate PHP Client Error Handling Examples ===\n\n";

// Example 1: Connection Error Handling
echo "1. Connection Error Handling\n";
echo "----------------------------\n";

try {
    // Try to connect to a non-existent server
    $client = WeaviateClientFactory::connectToCustom('non-existent-server.invalid', 8080);
    $client->collections()->exists('TestCollection');
    echo "✓ Connection successful (unexpected)\n";
} catch (WeaviateConnectionException $e) {
    echo "✓ Caught connection error: " . $e->getMessage() . "\n";
    
    $context = $e->getContext();
    if (isset($context['network_error'])) {
        echo "  Network error details: " . $context['network_error'] . "\n";
    }
} catch (WeaviateBaseException $e) {
    echo "✓ Caught general Weaviate error: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: Input Validation Error Handling
echo "2. Input Validation Error Handling\n";
echo "-----------------------------------\n";

try {
    // Try to create client with invalid port
    WeaviateClientFactory::connectToCustom('localhost', 99999);
    echo "✗ Should have failed with invalid port\n";
} catch (WeaviateInvalidInputException $e) {
    echo "✓ Caught input validation error: " . $e->getMessage() . "\n";
    
    $context = $e->getContext();
    if (isset($context['expected'])) {
        echo "  Expected: " . $context['expected'] . "\n";
    }
}

echo "\n";

// Example 3: Working with Real Weaviate Instance
echo "3. Real Weaviate Instance Error Handling\n";
echo "----------------------------------------\n";

try {
    // Connect to local Weaviate (adjust URL as needed)
    $client = WeaviateClientFactory::connectToLocal('localhost:8080');
    
    // Example 3a: Not Found Error Handling
    echo "3a. Testing Not Found Errors:\n";
    try {
        // First create a collection, then try to access non-existent object
        if (!$client->collections()->exists('TestErrorCollection')) {
            $client->collections()->create('TestErrorCollection', [
                'properties' => [['name' => 'title', 'dataType' => ['text']]]
            ]);
        }

        $client->collections()->get('TestErrorCollection')->data()->get('non-existent-id');
        echo "✗ Should have failed with not found error\n";
    } catch (NotFoundException $e) {
        echo "✓ Caught not found error: " . $e->getMessage() . "\n";
        echo "  Status code: " . $e->getStatusCode() . "\n";

        $context = $e->getContext();
        if (isset($context['resource_type'])) {
            echo "  Resource type: " . $context['resource_type'] . "\n";
        }
    } catch (UnexpectedStatusCodeException $e) {
        echo "✓ Caught HTTP error (collection may not exist): " . $e->getMessage() . "\n";
        echo "  Status code: " . $e->getStatusCode() . "\n";
    }
    
    echo "\n";
    
    // Example 3b: Schema Validation Error Handling
    echo "3b. Testing Schema Validation Errors:\n";
    try {
        // Try to create collection with invalid name (lowercase)
        $client->collections()->create('invalidname', [
            'properties' => [
                ['name' => 'title', 'dataType' => ['text']]
            ]
        ]);
        echo "✗ Should have failed with validation error\n";
    } catch (UnexpectedStatusCodeException $e) {
        echo "✓ Caught validation error: " . $e->getMessage() . "\n";
        echo "  Status code: " . $e->getStatusCode() . "\n";
        
        $response = $e->getResponse();
        if ($response && isset($response['body'])) {
            echo "  Response body: " . json_encode($response['body']) . "\n";
        }
    }
    
    echo "\n";
    
} catch (WeaviateConnectionException $e) {
    echo "⚠ Could not connect to Weaviate instance: " . $e->getMessage() . "\n";
    echo "  Make sure Weaviate is running on localhost:8080\n";
    echo "  You can start it with: docker run -p 8080:8080 semitechnologies/weaviate:latest\n\n";
}

// Example 4: Exception Hierarchy Demonstration
echo "4. Exception Hierarchy Demonstration\n";
echo "------------------------------------\n";

try {
    // This will be caught by the base exception
    throw new NotFoundException('Test not found error');
} catch (UnexpectedStatusCodeException $e) {
    echo "✓ Caught as UnexpectedStatusCodeException: " . $e->getMessage() . "\n";
} catch (WeaviateBaseException $e) {
    echo "✗ Should have been caught by more specific exception\n";
}

try {
    // This demonstrates the hierarchy
    throw new NotFoundException('Another test error');
} catch (WeaviateBaseException $e) {
    echo "✓ Caught as WeaviateBaseException: " . $e->getMessage() . "\n";
    echo "  Actual type: " . get_class($e) . "\n";
    echo "  Is NotFoundException: " . ($e instanceof NotFoundException ? 'Yes' : 'No') . "\n";
    echo "  Is UnexpectedStatusCodeException: " . ($e instanceof UnexpectedStatusCodeException ? 'Yes' : 'No') . "\n";
}

echo "\n";

// Example 5: Error Context Usage
echo "5. Error Context Usage\n";
echo "----------------------\n";

try {
    $exception = new WeaviateConnectionException('Connection failed');
    $exception->addContext('url', 'http://localhost:8080')
              ->addContext('timeout', 30)
              ->addContext('retry_count', 3);
    
    throw $exception;
} catch (WeaviateBaseException $e) {
    echo "✓ Exception with context:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    echo "  Detailed message:\n" . $e->getDetailedMessage() . "\n";
}

echo "\n";

// Example 6: Retry Handler Usage
echo "6. Retry Handler Usage\n";
echo "----------------------\n";

// Create a retry handler
$retryHandler = new RetryHandler(3, 0.1, 5.0); // 3 retries, 0.1s base delay, 5s max delay

$attemptCount = 0;
try {
    $result = $retryHandler->execute('test operation', function () use (&$attemptCount) {
        $attemptCount++;
        echo "  Attempt {$attemptCount}\n";
        
        if ($attemptCount < 3) {
            throw new WeaviateConnectionException('Simulated connection failure');
        }
        
        return ['success' => true, 'attempts' => $attemptCount];
    });
    
    echo "✓ Operation succeeded after {$attemptCount} attempts\n";
    echo "  Result: " . json_encode($result) . "\n";
    
} catch (WeaviateRetryException $e) {
    echo "✗ Operation failed after retries: " . $e->getMessage() . "\n";
    echo "  Retry count: " . $e->getRetryCount() . "\n";
    
    $attempts = $e->getRetryAttempts();
    if ($attempts) {
        echo "  Attempt details:\n";
        foreach ($attempts as $attempt) {
            echo "    Attempt {$attempt['number']}: {$attempt['error']}\n";
        }
    }
}

echo "\n";

// Example 7: Non-Retriable Error Handling
echo "7. Non-Retriable Error Handling\n";
echo "-------------------------------\n";

$retryHandler = new RetryHandler(3, 0.1, 5.0);

try {
    $retryHandler->execute('validation operation', function () {
        // This error should not be retried
        throw new \InvalidArgumentException('Invalid input - this should not be retried');
    });
} catch (\InvalidArgumentException $e) {
    echo "✓ Non-retriable error was not retried: " . $e->getMessage() . "\n";
} catch (WeaviateRetryException $e) {
    echo "✗ Non-retriable error was incorrectly retried\n";
}

echo "\n";

// Example 8: Best Practices Demonstration
echo "8. Best Practices Demonstration\n";
echo "-------------------------------\n";

function performWeaviateOperation(WeaviateClient $client, string $collectionName): ?array
{
    try {
        // Attempt the operation
        return $client->collections()->get($collectionName)->data()->get('some-id');
        
    } catch (NotFoundException $e) {
        // Handle missing resources gracefully
        echo "  Resource not found, returning null\n";
        return null;
        
    } catch (InsufficientPermissionsException $e) {
        // Handle permission issues
        echo "  Permission denied: " . $e->getMessage() . "\n";
        
        $context = $e->getContext();
        if (isset($context['suggestions'])) {
            echo "  Suggestions:\n";
            foreach ($context['suggestions'] as $suggestion) {
                echo "    - {$suggestion}\n";
            }
        }
        
        throw $e; // Re-throw for higher-level handling
        
    } catch (WeaviateConnectionException $e) {
        // Handle connection issues with logging
        error_log("Weaviate connection failed: " . $e->getMessage());
        
        $context = $e->getContext();
        if (isset($context['network_error'])) {
            error_log("Network error details: " . $context['network_error']);
        }
        
        throw new \RuntimeException('Database service unavailable', 0, $e);
        
    } catch (WeaviateRetryException $e) {
        // Handle retry exhaustion
        echo "  Operation failed after {$e->getRetryCount()} retries\n";
        
        $attempts = $e->getRetryAttempts();
        if ($attempts) {
            echo "  Final attempt error: " . end($attempts)['error'] . "\n";
        }
        
        throw new \RuntimeException('Service temporarily unavailable', 0, $e);
        
    } catch (WeaviateBaseException $e) {
        // Catch-all for other Weaviate errors
        echo "  Weaviate error: " . $e->getMessage() . "\n";
        echo "  Context: " . json_encode($e->getContext()) . "\n";
        
        throw new \RuntimeException('Database operation failed', 0, $e);
    }
}

// Demonstrate the best practices function
try {
    $client = WeaviateClientFactory::connectToLocal('localhost:8080');
    $result = performWeaviateOperation($client, 'NonExistentCollection');
    echo "✓ Operation completed, result: " . ($result ? 'found' : 'not found') . "\n";
} catch (\RuntimeException $e) {
    echo "✓ Caught application-level exception: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "  Original error: " . $e->getPrevious()->getMessage() . "\n";
    }
} catch (WeaviateConnectionException $e) {
    echo "⚠ Could not connect to demonstrate best practices\n";
}

// Cleanup
echo "\n9. Cleanup\n";
echo "----------\n";

try {
    $client = WeaviateClientFactory::connectToLocal('localhost:8080');
    if ($client->collections()->exists('TestErrorCollection')) {
        $client->collections()->delete('TestErrorCollection');
        echo "✓ Cleaned up test collection\n";
    }
} catch (WeaviateBaseException $e) {
    echo "⚠ Could not clean up: " . $e->getMessage() . "\n";
}

echo "\n=== Error Handling Examples Complete ===\n";
