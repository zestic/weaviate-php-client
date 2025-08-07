# Migration Guide: Python to PHP Client

This guide helps developers familiar with the Weaviate Python client transition to the PHP client. The PHP client follows the same patterns and provides equivalent functionality.

## Table of Contents

- [Connection Methods](#connection-methods)
- [Collections API](#collections-api)
- [Data Operations](#data-operations)
- [Authentication](#authentication)
- [Key Differences](#key-differences)
- [Side-by-Side Examples](#side-by-side-examples)

## Connection Methods

### Local Connection

**Python:**
```python
import weaviate

# Default local connection
client = weaviate.connect_to_local()

# Custom host/port
client = weaviate.connect_to_local(host="localhost", port=18080)

# With authentication
client = weaviate.connect_to_local(
    host="localhost",
    port=8080,
    auth_credentials=weaviate.AuthApiKey("your-api-key")
)
```

**PHP:**
```php
use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

// Default local connection
$client = WeaviateClient::connectToLocal();

// Custom host/port
$client = WeaviateClient::connectToLocal('localhost:18080');

// With authentication
$client = WeaviateClient::connectToLocal(
    'localhost:8080',
    new ApiKey('your-api-key')
);
```

### Weaviate Cloud Connection

**Python:**
```python
client = weaviate.connect_to_weaviate_cloud(
    cluster_url="my-cluster.weaviate.network",
    auth_credentials=weaviate.AuthApiKey("your-wcd-api-key")
)
```

**PHP:**
```php
$client = WeaviateClient::connectToWeaviateCloud(
    'my-cluster.weaviate.network',
    new ApiKey('your-wcd-api-key')
);
```

### Custom Connection

**Python:**
```python
client = weaviate.connect_to_custom(
    http_host="my-server.com",
    http_port=9200,
    http_secure=True,
    auth_credentials=weaviate.AuthApiKey("api-key"),
    headers={"X-OpenAI-Api-Key": "your-openai-key"}
)
```

**PHP:**
```php
$client = WeaviateClient::connectToCustom(
    'my-server.com',
    9200,
    true, // HTTPS
    new ApiKey('api-key'),
    ['X-OpenAI-Api-Key' => 'your-openai-key']
);
```

## Collections API

### Creating Collections

**Python:**
```python
from weaviate.classes.config import Property, DataType

client.collections.create(
    name="Article",
    properties=[
        Property(name="title", data_type=DataType.TEXT),
        Property(name="content", data_type=DataType.TEXT),
    ]
)
```

**PHP:**
```php
$client->collections()->create('Article', [
    'properties' => [
        ['name' => 'title', 'dataType' => ['text']],
        ['name' => 'content', 'dataType' => ['text']],
    ]
]);
```

### Checking Collection Existence

**Python:**
```python
exists = client.collections.exists("Article")
```

**PHP:**
```php
$exists = $client->collections()->exists('Article');
```

### Getting Collection Instance

**Python:**
```python
collection = client.collections.get("Article")
```

**PHP:**
```php
$collection = $client->collections()->get('Article');
```

## Data Operations

### Creating Objects

**Python:**
```python
collection = client.collections.get("Article")

result = collection.data.insert({
    "title": "My Article",
    "content": "Article content..."
})
```

**PHP:**
```php
$collection = $client->collections()->get('Article');

$result = $collection->data()->create([
    'title' => 'My Article',
    'content' => 'Article content...'
]);
```

### Getting Objects

**Python:**
```python
article = collection.data.get_by_id(uuid="object-id")
```

**PHP:**
```php
$article = $collection->data()->get('object-id');
```

### Updating Objects

**Python:**
```python
collection.data.update(
    uuid="object-id",
    properties={"title": "Updated Title"}
)
```

**PHP:**
```php
$collection->data()->update('object-id', [
    'title' => 'Updated Title'
]);
```

### Deleting Objects

**Python:**
```python
collection.data.delete_by_id(uuid="object-id")
```

**PHP:**
```php
$collection->data()->delete('object-id');
```

## Authentication

### API Key Authentication

**Python:**
```python
import weaviate

auth = weaviate.AuthApiKey("your-api-key")
client = weaviate.connect_to_local(auth_credentials=auth)
```

**PHP:**
```php
use Weaviate\Auth\ApiKey;

$auth = new ApiKey('your-api-key');
$client = WeaviateClient::connectToLocal('localhost:8080', $auth);
```

## Multi-Tenancy

### Working with Tenants

**Python:**
```python
# Create multi-tenant collection
from weaviate.classes.config import MultiTenancyConfig

client.collections.create(
    name="Organization",
    multi_tenancy_config=MultiTenancyConfig(enabled=True),
    properties=[
        Property(name="name", data_type=DataType.TEXT)
    ]
)

# Work with specific tenant
collection = client.collections.get("Organization")
tenant_collection = collection.with_tenant("tenant-123")

result = tenant_collection.data.insert({
    "name": "ACME Corp"
})
```

**PHP:**
```php
// Create multi-tenant collection
$client->collections()->create('Organization', [
    'multiTenancyConfig' => ['enabled' => true],
    'properties' => [
        ['name' => 'name', 'dataType' => ['text']]
    ]
]);

// Work with specific tenant
$collection = $client->collections()->get('Organization');
$tenantCollection = $collection->withTenant('tenant-123');

$result = $tenantCollection->data()->create([
    'name' => 'ACME Corp'
]);
```

## Key Differences

### 1. Method Naming Convention

| Python | PHP | Notes |
|--------|-----|-------|
| `snake_case` | `camelCase` | PHP follows PSR standards |
| `connect_to_local()` | `connectToLocal()` | Static methods in PHP |
| `data.insert()` | `data()->create()` | More explicit CRUD naming |
| `get_by_id()` | `get()` | Simplified method names |

### 2. Array vs Object Syntax

**Python uses objects:**
```python
Property(name="title", data_type=DataType.TEXT)
```

**PHP uses associative arrays:**
```php
['name' => 'title', 'dataType' => ['text']]
```

### 3. Context Managers vs Manual Connection

**Python supports context managers:**
```python
with weaviate.connect_to_local() as client:
    # Work with client
    pass  # Connection automatically closed
```

**PHP uses explicit connection management:**
```php
$client = WeaviateClient::connectToLocal();
// Work with client
// Connection is managed internally
```

### 4. Exception Handling

**Python:**
```python
from weaviate.exceptions import WeaviateConnectionError

try:
    client.collections.get("NonExistent")
except WeaviateConnectionError as e:
    print(f"Connection error: {e}")
```

**PHP:**
```php
use Weaviate\Exceptions\NotFoundException;

try {
    $client->collections()->get('NonExistent');
} catch (NotFoundException $e) {
    echo "Not found: " . $e->getMessage();
}
```

## Side-by-Side Examples

### Complete Workflow Comparison

**Python:**
```python
import weaviate
from weaviate.classes.config import Property, DataType

# Connect
client = weaviate.connect_to_local(port=18080)

# Create collection
if not client.collections.exists("Blog"):
    client.collections.create(
        name="Blog",
        properties=[
            Property(name="title", data_type=DataType.TEXT),
            Property(name="content", data_type=DataType.TEXT),
        ]
    )

# Work with data
collection = client.collections.get("Blog")
result = collection.data.insert({
    "title": "My Blog Post",
    "content": "Post content..."
})

# Retrieve
post = collection.data.get_by_id(result.uuid)
print(f"Title: {post.properties['title']}")
```

**PHP:**
```php
use Weaviate\WeaviateClient;

// Connect
$client = WeaviateClient::connectToLocal('localhost:18080');

// Create collection
if (!$client->collections()->exists('Blog')) {
    $client->collections()->create('Blog', [
        'properties' => [
            ['name' => 'title', 'dataType' => ['text']],
            ['name' => 'content', 'dataType' => ['text']],
        ]
    ]);
}

// Work with data
$collection = $client->collections()->get('Blog');
$result = $collection->data()->create([
    'title' => 'My Blog Post',
    'content' => 'Post content...'
]);

// Retrieve
$post = $collection->data()->get($result['id']);
echo "Title: " . $post['title'] . "\n";
```

## Migration Checklist

- [ ] Replace `weaviate.connect_to_*()` with `WeaviateClient::connectTo*()`
- [ ] Convert Python objects to PHP associative arrays
- [ ] Update method names from `snake_case` to `camelCase`
- [ ] Replace `data.insert()` with `data()->create()`
- [ ] Replace `get_by_id()` with `get()`
- [ ] Update exception handling to use PHP exception classes
- [ ] Convert property access from `object.property` to `array['key']`
- [ ] Update authentication from `AuthApiKey()` to `new ApiKey()`

## Getting Help

If you encounter issues during migration:

1. Check the [API Documentation](API.md) for detailed method signatures
2. Review the [examples](../examples/) for common patterns
3. Run the test suite to see working examples
4. Open an issue on GitHub for specific migration questions

The PHP client is designed to provide the same developer experience as the Python client while following PHP conventions and best practices.
