# Error Handling Guide

The Weaviate PHP client provides comprehensive error handling that aligns with the Python client's exception hierarchy while following PHP conventions. This guide covers all aspects of error handling, retry mechanisms, and best practices.

## Table of Contents

- [Exception Hierarchy](#exception-hierarchy)
- [Core Exceptions](#core-exceptions)
- [Retry Mechanism](#retry-mechanism)
- [Error Context](#error-context)
- [Best Practices](#best-practices)
- [Migration from Python](#migration-from-python)

## Exception Hierarchy

All Weaviate exceptions inherit from `WeaviateBaseException`, allowing you to catch all Weaviate-related errors with a single catch block:

```
WeaviateBaseException (base exception)
├── WeaviateConnectionException (connection failures)
├── WeaviateTimeoutException (timeout failures)  
├── WeaviateRetryException (retry exhaustion)
├── WeaviateQueryException (query failures)
├── WeaviateInvalidInputException (validation errors)
└── UnexpectedStatusCodeException (HTTP errors)
    ├── InsufficientPermissionsException (403 errors)
    └── NotFoundException (404 errors)
```

## Core Exceptions

### WeaviateBaseException

The base exception for all Weaviate errors. Provides context management and detailed error messages.

```php
use Weaviate\Exceptions\WeaviateBaseException;

try {
    $client->collections()->get('NonExistent');
} catch (WeaviateBaseException $e) {
    echo "Weaviate error: " . $e->getMessage();
    echo "Context: " . json_encode($e->getContext());
    echo "Detailed: " . $e->getDetailedMessage();
}
```

**Key Features:**
- **Context Management**: Additional error context with `getContext()`
- **Detailed Messages**: Rich error information with `getDetailedMessage()`
- **Exception Chaining**: Preserves original exceptions with `getPrevious()`

### WeaviateConnectionException

Thrown when connection to Weaviate fails.

```php
use Weaviate\Exceptions\WeaviateConnectionException;

try {
    $client = WeaviateClientFactory::connectToCustom('unreachable-server.com');
    $client->collections()->exists('Test');
} catch (WeaviateConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
    
    $context = $e->getContext();
    if (isset($context['network_error'])) {
        echo "Network error: " . $context['network_error'];
    }
}
```

**Common Causes:**
- Server not running
- Network connectivity issues
- DNS resolution failures
- SSL/TLS certificate problems

**Factory Methods:**
- `fromNetworkError()` - Network-related failures
- `fromTimeout()` - Connection timeouts
- `fromSslError()` - SSL/TLS issues

### UnexpectedStatusCodeException

Thrown when Weaviate returns HTTP error status codes.

```php
use Weaviate\Exceptions\UnexpectedStatusCodeException;

try {
    $client->collections()->create('invalid-name!', []);
} catch (UnexpectedStatusCodeException $e) {
    echo "HTTP {$e->getStatusCode()}: {$e->getMessage()}";
    
    $response = $e->getResponse();
    if ($response) {
        echo "Response body: " . json_encode($response['body']);
    }
}
```

**Features:**
- **Status Code Access**: `getStatusCode()` returns HTTP status
- **Response Preservation**: `getResponse()` returns full response data
- **Automatic Explanations**: Common status codes include helpful explanations

### InsufficientPermissionsException

Specialized exception for 403 Forbidden errors.

```php
use Weaviate\Exceptions\InsufficientPermissionsException;

try {
    $client->collections()->delete('ProtectedCollection');
} catch (InsufficientPermissionsException $e) {
    echo "Permission denied: " . $e->getMessage();
    
    $context = $e->getContext();
    $suggestions = $context['suggestions'] ?? [];
    foreach ($suggestions as $suggestion) {
        echo "- " . $suggestion . "\n";
    }
}
```

**Factory Methods:**
- `forAuthenticationFailure()` - Authentication issues
- `forRbacRestriction()` - RBAC policy violations

### NotFoundException

Specialized exception for 404 Not Found errors.

```php
use Weaviate\Exceptions\NotFoundException;

try {
    $object = $collection->data()->get('non-existent-id');
} catch (NotFoundException $e) {
    echo "Resource not found: " . $e->getMessage();
    
    $context = $e->getContext();
    if (isset($context['resource_type'])) {
        echo "Resource type: " . $context['resource_type'];
    }
}
```

**Factory Methods:**
- `forCollection()` - Collection not found
- `forObject()` - Object not found
- `forTenant()` - Tenant not found

### WeaviateTimeoutException

Thrown when requests exceed timeout limits.

```php
use Weaviate\Exceptions\WeaviateTimeoutException;

try {
    $client->collections()->create('LargeCollection', $complexConfig);
} catch (WeaviateTimeoutException $e) {
    echo "Request timed out: " . $e->getMessage();
    
    $timeoutSeconds = $e->getTimeoutSeconds();
    if ($timeoutSeconds) {
        echo "Timeout was: {$timeoutSeconds} seconds";
    }
}
```

**Factory Methods:**
- `forConnection()` - Connection timeouts
- `forQuery()` - Query timeouts
- `forBatch()` - Batch operation timeouts

### WeaviateRetryException

Thrown when retry mechanism exhausts all attempts.

```php
use Weaviate\Exceptions\WeaviateRetryException;

try {
    $client->collections()->exists('TestCollection');
} catch (WeaviateRetryException $e) {
    echo "Failed after {$e->getRetryCount()} retries";
    
    $attempts = $e->getRetryAttempts();
    if ($attempts) {
        foreach ($attempts as $attempt) {
            echo "Attempt {$attempt['number']}: {$attempt['error']}";
        }
    }
}
```

**Features:**
- **Retry Count**: `getRetryCount()` returns number of attempts
- **Attempt Details**: `getRetryAttempts()` returns detailed attempt information

### WeaviateInvalidInputException

Thrown for client-side validation errors.

```php
use Weaviate\Exceptions\WeaviateInvalidInputException;

try {
    $client = WeaviateClientFactory::connectToCustom('localhost', 99999); // Invalid port
} catch (WeaviateInvalidInputException $e) {
    echo "Invalid input: " . $e->getMessage();
    
    $context = $e->getContext();
    if (isset($context['expected'])) {
        echo "Expected: " . $context['expected'];
    }
}
```

**Factory Methods:**
- `forParameter()` - Invalid parameter values
- `forMissingParameter()` - Missing required parameters
- `forDataStructure()` - Invalid data structures
- `forConfiguration()` - Invalid configuration
- `forCollectionName()` - Invalid collection names

## Retry Mechanism

The client includes automatic retry with exponential backoff, matching the Python client's behavior.

### Configuration

```php
use Weaviate\Retry\RetryHandler;

// Default configuration (4 retries, 1s base delay, 60s max delay)
$retryHandler = new RetryHandler();

// Custom configuration
$retryHandler = new RetryHandler(
    maxRetries: 3,
    baseDelaySeconds: 0.5,
    maxDelaySeconds: 30.0
);

// Predefined configurations
$connectionHandler = RetryHandler::forConnection(); // 3 retries, 0.5s base, 10s max
$queryHandler = RetryHandler::forQuery();           // 2 retries, 1s base, 30s max
```

### Retry Strategy

**Exponential Backoff**: Delays follow the pattern `baseDelay * (2 ^ attempt)`
- Attempt 1: 1 second
- Attempt 2: 2 seconds  
- Attempt 3: 4 seconds
- Attempt 4: 8 seconds

**Retry Conditions**: Only specific errors trigger retries:
- `WeaviateConnectionException` - Always retry
- `WeaviateTimeoutException` - Always retry
- HTTP 502, 503, 504 status codes - Retry (server issues)
- Other errors - No retry (client issues)

### Manual Retry Usage

```php
use Weaviate\Retry\RetryHandler;

$retryHandler = RetryHandler::forConnection();

$result = $retryHandler->execute(
    operation: 'get collection schema',
    callable: fn() => $client->collections()->get('MyCollection')
);
```

## Error Context

All exceptions provide rich context information for debugging:

```php
try {
    $client->collections()->get('NonExistent')->data()->get('test-id');
} catch (WeaviateBaseException $e) {
    $context = $e->getContext();
    
    // Common context fields:
    echo "Operation: " . ($context['operation'] ?? 'unknown') . "\n";
    echo "Status Code: " . ($context['status_code'] ?? 'N/A') . "\n";
    echo "URL: " . ($context['url'] ?? 'N/A') . "\n";
    
    // Exception-specific context:
    if (isset($context['suggestions'])) {
        echo "Suggestions:\n";
        foreach ($context['suggestions'] as $suggestion) {
            echo "- {$suggestion}\n";
        }
    }
}
```

## Best Practices

### 1. Use Specific Exception Types

```php
// Good: Catch specific exceptions for targeted handling
try {
    $collection->data()->get($id);
} catch (NotFoundException $e) {
    // Handle missing resource
    return null;
} catch (InsufficientPermissionsException $e) {
    // Handle permission issues
    throw new AccessDeniedException($e->getMessage());
} catch (WeaviateConnectionException $e) {
    // Handle connection issues
    $this->logger->error('Weaviate connection failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

### 2. Use Base Exception for General Handling

```php
// Good: Use base exception to catch all Weaviate errors
try {
    $result = $client->collections()->create($name, $config);
} catch (WeaviateBaseException $e) {
    $this->logger->error('Weaviate operation failed', [
        'error' => $e->getMessage(),
        'context' => $e->getContext()
    ]);
    
    // Re-throw or handle as appropriate
    throw new ServiceException('Database operation failed', 0, $e);
}
```

### 3. Leverage Error Context

```php
try {
    $client->collections()->delete($collectionName);
} catch (WeaviateBaseException $e) {
    $context = $e->getContext();
    
    // Log detailed context for debugging
    $this->logger->error('Collection deletion failed', [
        'collection' => $collectionName,
        'error' => $e->getMessage(),
        'status_code' => $context['status_code'] ?? null,
        'operation' => $context['operation'] ?? null,
        'suggestions' => $context['suggestions'] ?? []
    ]);
}
```

### 4. Handle Retry Exhaustion

```php
try {
    $result = $client->collections()->exists($name);
} catch (WeaviateRetryException $e) {
    // Log retry attempts for analysis
    $attempts = $e->getRetryAttempts();
    $this->logger->warning('Operation failed after retries', [
        'operation' => 'collection_exists',
        'retry_count' => $e->getRetryCount(),
        'attempts' => $attempts
    ]);
    
    // Decide whether to fail fast or try alternative approach
    throw new ServiceUnavailableException('Weaviate service is currently unavailable');
}
```

## Migration from Python

### Exception Mapping

| Python Exception | PHP Exception | Notes |
|------------------|---------------|-------|
| `WeaviateBaseError` | `WeaviateBaseException` | Base exception class |
| `WeaviateConnectionError` | `WeaviateConnectionException` | Connection failures |
| `WeaviateTimeoutError` | `WeaviateTimeoutException` | Timeout errors |
| `WeaviateRetryError` | `WeaviateRetryException` | Retry exhaustion |
| `WeaviateQueryError` | `WeaviateQueryException` | Query failures |
| `UnexpectedStatusCodeError` | `UnexpectedStatusCodeException` | HTTP errors |
| `InsufficientPermissionsError` | `InsufficientPermissionsException` | 403 errors |

### Code Migration Examples

**Python:**
```python
try:
    client.collections.get("NonExistent")
except weaviate.exceptions.WeaviateBaseError as e:
    print(f"Weaviate error: {e.message}")
```

**PHP:**
```php
try {
    $client->collections()->get('NonExistent');
} catch (WeaviateBaseException $e) {
    echo "Weaviate error: " . $e->getMessage();
}
```

The PHP client provides the same level of error handling sophistication as the Python client while following PHP conventions and best practices.
