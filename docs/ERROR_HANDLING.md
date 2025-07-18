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
    $client = WeaviateClient::connectToCustom('unreachable-server.com');
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
