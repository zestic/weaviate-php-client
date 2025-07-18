# Weaviate PHP Client API Documentation

## Table of Contents

- [Connection Methods](#connection-methods)
- [Core Classes](#core-classes)
- [Collections API](#collections-api)
- [Data Operations](#data-operations)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Examples](#examples)

## Connection Methods

The Weaviate PHP client provides three convenient static methods for connecting to different Weaviate deployments:

### `WeaviateClient::connectToLocal()`

Connect to a local Weaviate instance (development, Docker containers).

```php
public static function connectToLocal(
    string $host = 'localhost:8080',
    ?AuthInterface $auth = null
): WeaviateClient
```

**Parameters:**
- `$host` - Host and port (e.g., 'localhost:8080', 'localhost:18080')
- `$auth` - Optional authentication

**Examples:**
```php
// Default local connection
$client = WeaviateClient::connectToLocal();

// Docker container
$client = WeaviateClient::connectToLocal('localhost:18080');

// With authentication
$client = WeaviateClient::connectToLocal('localhost:8080', new ApiKey('key'));
```

### `WeaviateClient::connectToWeaviateCloud()`

Connect to a Weaviate Cloud (WCD) instance.

```php
public static function connectToWeaviateCloud(
    string $clusterUrl,
    AuthInterface $auth
): WeaviateClient
```

**Parameters:**
- `$clusterUrl` - WCD cluster URL or hostname (e.g., 'my-cluster.weaviate.network')
- `$auth` - Authentication credentials (required for WCD)

**Examples:**
```php
// Basic cloud connection
$client = WeaviateClient::connectToWeaviateCloud(
    'my-cluster.weaviate.network',
    new ApiKey('your-wcd-api-key')
);

// Works with various URL formats
$client = WeaviateClient::connectToWeaviateCloud(
    'https://my-cluster.weaviate.network/some/path',
    new ApiKey('your-wcd-api-key')
);
```

### `WeaviateClient::connectToCustom()`

Connect to a custom Weaviate deployment with full control over connection parameters.

```php
public static function connectToCustom(
    string $host,
    int $port = 8080,
    bool $secure = false,
    ?AuthInterface $auth = null,
    array $headers = []
): WeaviateClient
```

**Parameters:**
- `$host` - Host to connect to (e.g., 'localhost', 'my-server.com')
- `$port` - Port to connect to (1-65535)
- `$secure` - Whether to use HTTPS (true) or HTTP (false)
- `$auth` - Optional authentication credentials
- `$headers` - Additional headers for requests

**Examples:**
```php
// Basic custom connection
$client = WeaviateClient::connectToCustom('my-server.com');

// Full configuration
$client = WeaviateClient::connectToCustom(
    'my-server.com',
    9200,
    true, // HTTPS
    new ApiKey('api-key'),
    [
        'X-OpenAI-Api-Key' => 'your-openai-key',
        'X-Custom-Header' => 'custom-value'
    ]
);
```

## Core Classes

### WeaviateClient

The main entry point for all Weaviate operations.

**Methods:**
- `collections()` - Access collections API
- `schema()` - Access schema management API
- `getAuth()` - Get authentication instance

### Schema

Comprehensive schema management for Weaviate collections.

**Methods:**
- `get(?string $className = null): array` - Get complete schema or specific collection schema
- `exists(string $className): bool` - Check if collection exists
- `create(array $classDefinition): array` - Create new collection
- `update(string $className, array $updates): array` - Update existing collection
- `delete(string $className): bool` - Delete collection
- `addProperty(string $className, array $property): array` - Add property to collection
- `updateProperty(string $className, string $propertyName, array $updates): array` - Update property
- `deleteProperty(string $className, string $propertyName): bool` - Delete property
- `getProperty(string $className, string $propertyName): array` - Get specific property

**Example:**
```php
$client = WeaviateClient::connectToLocal();
$schema = $client->schema();

// Create collection
$schema->create([
    'class' => 'Article',
    'properties' => [
        ['name' => 'title', 'dataType' => ['text']],
        ['name' => 'content', 'dataType' => ['text']]
    ]
]);

// Add property
$schema->addProperty('Article', [
    'name' => 'author',
    'dataType' => ['text']
]);

// Get collection schema
$articleSchema = $schema->get('Article');
```

### Collections

Manages Weaviate collections (schemas).

**Methods:**
- `exists(string $name): bool` - Check if collection exists
- `create(string $name, array $config = []): array` - Create collection
- `get(string $name): Collection` - Get collection instance
- `delete(string $name): bool` - Delete collection

### Collection

Represents a specific collection and provides data operations.

**Methods:**
- `withTenant(string $tenant): self` - Set tenant for multi-tenancy
- `data(): DataOperations` - Access data operations

## Collections API

### Creating Collections

```php
$client = WeaviateClient::connectToLocal();
$collections = $client->collections();

// Basic collection
$result = $collections->create('Article', [
    'properties' => [
        ['name' => 'title', 'dataType' => ['text']],
        ['name' => 'content', 'dataType' => ['text']],
        ['name' => 'publishedAt', 'dataType' => ['date']],
    ]
]);

// Collection with multi-tenancy
$result = $collections->create('Organization', [
    'properties' => [
        ['name' => 'name', 'dataType' => ['text']],
        ['name' => 'industry', 'dataType' => ['text']],
    ],
    'multiTenancyConfig' => ['enabled' => true]
]);
```

### Checking Collection Existence

```php
if ($collections->exists('Article')) {
    echo "Article collection exists";
} else {
    echo "Article collection does not exist";
}
```

### Deleting Collections

```php
$success = $collections->delete('Article');
if ($success) {
    echo "Collection deleted successfully";
}
```

## Data Operations

### Basic CRUD Operations

```php
$collection = $client->collections()->get('Article');
$data = $collection->data();

// Create object
$result = $data->create([
    'title' => 'My Article',
    'content' => 'Article content here...',
    'publishedAt' => '2024-01-01T00:00:00Z'
]);

// Get object
$article = $data->get($result['id']);

// Update object
$data->update($result['id'], [
    'title' => 'Updated Article Title'
]);

// Delete object
$success = $data->delete($result['id']);
```

### Multi-Tenant Operations

```php
$collection = $client->collections()->get('Organization');

// Work with specific tenant
$tenantData = $collection->withTenant('tenant-123')->data();

// Create tenant-specific object
$result = $tenantData->create([
    'name' => 'ACME Corp',
    'industry' => 'Technology'
]);

// Get tenant-specific object
$org = $tenantData->get($result['id']);
```

## Authentication

### API Key Authentication

```php
use Weaviate\Auth\ApiKey;

$auth = new ApiKey('your-api-key-here');

// Use with any connection method
$client = WeaviateClient::connectToLocal('localhost:8080', $auth);
$client = WeaviateClient::connectToWeaviateCloud('cluster.weaviate.network', $auth);
$client = WeaviateClient::connectToCustom('server.com', 443, true, $auth);
```

### Custom Authentication

```php
use Weaviate\Auth\AuthInterface;
use Psr\Http\Message\RequestInterface;

class CustomAuth implements AuthInterface
{
    public function __construct(private string $token) {}
    
    public function apply(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('X-Custom-Auth', $this->token);
    }
}

$auth = new CustomAuth('custom-token');
$client = WeaviateClient::connectToCustom('server.com', 8080, false, $auth);
```

## Error Handling

The client throws specific exceptions for different error conditions:

```php
use Weaviate\Exceptions\NotFoundException;

try {
    $collection = $client->collections()->get('NonExistent');
    $data = $collection->data()->get('non-existent-id');
} catch (NotFoundException $e) {
    echo "Object not found: " . $e->getMessage();
} catch (\Exception $e) {
    echo "General error: " . $e->getMessage();
}
```

## Examples

### Complete Workflow Example

```php
<?php

use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

// Connect to Weaviate
$client = WeaviateClient::connectToLocal('localhost:18080');

// Create collection if it doesn't exist
$collections = $client->collections();
if (!$collections->exists('Blog')) {
    $collections->create('Blog', [
        'properties' => [
            ['name' => 'title', 'dataType' => ['text']],
            ['name' => 'content', 'dataType' => ['text']],
            ['name' => 'author', 'dataType' => ['text']],
            ['name' => 'publishedAt', 'dataType' => ['date']],
        ]
    ]);
}

// Work with data
$blog = $collections->get('Blog');
$data = $blog->data();

// Create a blog post
$post = $data->create([
    'title' => 'Getting Started with Weaviate PHP Client',
    'content' => 'This is a comprehensive guide...',
    'author' => 'John Doe',
    'publishedAt' => '2024-01-15T10:00:00Z'
]);

echo "Created blog post with ID: " . $post['id'] . "\n";

// Retrieve the post
$retrievedPost = $data->get($post['id']);
echo "Retrieved post: " . $retrievedPost['title'] . "\n";

// Update the post
$data->update($post['id'], [
    'title' => 'Complete Guide to Weaviate PHP Client'
]);

echo "Post updated successfully\n";
```

### Multi-Tenant Example

```php
// Create multi-tenant collection
$collections->create('Company', [
    'properties' => [
        ['name' => 'name', 'dataType' => ['text']],
        ['name' => 'employees', 'dataType' => ['int']],
    ],
    'multiTenancyConfig' => ['enabled' => true]
]);

$company = $collections->get('Company');

// Create data for different tenants
$tenant1Data = $company->withTenant('client-1')->data();
$tenant1Data->create([
    'name' => 'Client 1 Company',
    'employees' => 100
]);

$tenant2Data = $company->withTenant('client-2')->data();
$tenant2Data->create([
    'name' => 'Client 2 Company', 
    'employees' => 250
]);
```
