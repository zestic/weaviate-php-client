# Weaviate PHP Client

A modern PHP client for Weaviate vector database with multi-tenancy support.

## License

This project is licensed under the Apache License 2.0. See the [LICENSE](LICENSE) file for details.

## Requirements

- PHP 8.3 or higher
- Composer
- Docker and Docker Compose (for integration tests)

## Installation

```bash
composer require zestic/weaviate-php-client
```

## Basic Usage

### Quick Start with connectToLocal

```php
<?php

use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;

// Connect to local Weaviate instance (default: localhost:8080)
$client = WeaviateClient::connectToLocal();

// Connect to Docker container on custom port
$client = WeaviateClient::connectToLocal('localhost:18080');

// Connect with authentication
$client = WeaviateClient::connectToLocal('localhost:8080', new ApiKey('your-api-key'));

// Connect to Weaviate Cloud
$client = WeaviateClient::connectToWeaviateCloud(
    'my-cluster.weaviate.network',
    new ApiKey('your-wcd-api-key')
);

// Connect to custom Weaviate instance
$client = WeaviateClient::connectToCustom(
    'my-server.com',        // host
    9200,                   // port
    true,                   // use HTTPS
    new ApiKey('api-key'),  // authentication
    [                       // custom headers
        'X-OpenAI-Api-Key' => 'your-openai-key',
        'X-Custom-Header' => 'custom-value'
    ]
);
```

### Schema Management

The PHP client provides comprehensive schema management capabilities:

```php
<?php

use Weaviate\WeaviateClient;

$client = WeaviateClient::connectToLocal();
$schema = $client->schema();

// Check if collection exists
if (!$schema->exists('Article')) {
    // Create collection with properties
    $schema->create([
        'class' => 'Article',
        'description' => 'A collection for storing articles',
        'properties' => [
            [
                'name' => 'title',
                'dataType' => ['text'],
                'description' => 'Article title'
            ],
            [
                'name' => 'content',
                'dataType' => ['text'],
                'description' => 'Article content'
            ],
            [
                'name' => 'publishedAt',
                'dataType' => ['date'],
                'description' => 'Publication date'
            ],
            [
                'name' => 'viewCount',
                'dataType' => ['int'],
                'description' => 'Number of views'
            ]
        ]
    ]);
}

// Get complete schema
$completeSchema = $schema->get();

// Get specific collection schema
$articleSchema = $schema->get('Article');

// Add property to existing collection
$schema->addProperty('Article', [
    'name' => 'author',
    'dataType' => ['text'],
    'description' => 'Article author'
]);

// Get specific property
$authorProperty = $schema->getProperty('Article', 'author');

// Delete collection
$schema->delete('Article');
```

### Advanced Usage with Manual Connection

```php
<?php

use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;
use Weaviate\Auth\ApiKey;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// Create HTTP client and factories
$httpClient = new Client();
$httpFactory = new HttpFactory();

// Create connection
$connection = new HttpConnection(
    'http://localhost:8080',
    $httpClient,
    $httpFactory,
    $httpFactory
);

// Create client with optional authentication
$client = new WeaviateClient($connection, new ApiKey('your-api-key'));

// Check if collection exists
if (!$client->collections()->exists('Organization')) {
    // Create collection with multi-tenancy
    $client->collections()->create('Organization', [
        'properties' => [
            ['name' => 'name', 'dataType' => ['text']],
            ['name' => 'createdAt', 'dataType' => ['date']]
        ],
        'multiTenancyConfig' => ['enabled' => true]
    ]);
}

// Manage tenants
use Weaviate\Tenants\Tenant;
use Weaviate\Tenants\TenantActivityStatus;

$collection = $client->collections()->get('Organization');
$tenants = $collection->tenants();

// Create tenants (multiple ways)
$tenants->create(['tenant1', 'tenant2']);
$tenants->create(new Tenant('tenant3', TenantActivityStatus::INACTIVE));

// Retrieve tenants
$allTenants = $tenants->get();
$specificTenant = $tenants->getByName('tenant1');

// Manage tenant status
$tenants->activate('tenant3');
$tenants->deactivate('tenant1');
$tenants->offload('tenant2');

// Work with tenant-specific data
$orgId = '123e4567-e89b-12d3-a456-426614174000';
$result = $collection
    ->withTenant('tenant1')
    ->data()
    ->create([
        'id' => $orgId,
        'name' => 'ACME Corp',
        'createdAt' => '2024-01-01T00:00:00Z'
    ]);

// Retrieve object from specific tenant
$org = $collection
    ->withTenant('tenant1')
    ->data()
    ->get($orgId);
```

## Multi-Tenancy

Weaviate supports multi-tenancy, allowing you to isolate data for different tenants within the same collection. This PHP client provides comprehensive tenant management capabilities.

### Creating Multi-Tenant Collections

```php
use Weaviate\WeaviateClient;
use Weaviate\Connection\HttpConnection;

$client = WeaviateClient::connectToLocal();

// Create a collection with multi-tenancy enabled
$client->collections()->create('Articles', [
    'properties' => [
        ['name' => 'title', 'dataType' => ['text']],
        ['name' => 'content', 'dataType' => ['text']],
        ['name' => 'author', 'dataType' => ['text']]
    ],
    'multiTenancyConfig' => ['enabled' => true]
]);
```

### Managing Tenants

```php
use Weaviate\Tenants\Tenant;
use Weaviate\Tenants\TenantActivityStatus;

$collection = $client->collections()->get('Articles');
$tenants = $collection->tenants();

// Create tenants (multiple input types supported)
$tenants->create('customer-123');                                    // String
$tenants->create(['customer-456', 'customer-789']);                 // Array of strings
$tenants->create(new Tenant('customer-abc', TenantActivityStatus::INACTIVE)); // Tenant object
$tenants->create([
    new Tenant('customer-def'),
    new Tenant('customer-ghi', TenantActivityStatus::INACTIVE)
]); // Array of Tenant objects

// Retrieve tenants
$allTenants = $tenants->get();                    // Returns array indexed by tenant name
$specificTenant = $tenants->getByName('customer-123'); // Returns Tenant object or null
$multipleTenants = $tenants->getByNames(['customer-123', 'customer-456']);

// Check tenant existence
if ($tenants->exists('customer-123')) {
    echo "Tenant exists!";
}

// Remove tenants
$tenants->remove('customer-123');                // Single tenant
$tenants->remove(['customer-456', 'customer-789']); // Multiple tenants
```

### Tenant Activity Status Management

Tenants can have different activity statuses that control how their data is stored and accessed:

- **ACTIVE**: Tenant is fully active, data immediately accessible
- **INACTIVE**: Tenant is inactive, data stored locally but not accessible
- **OFFLOADED**: Tenant is inactive, data stored in cloud storage
- **OFFLOADING**: (Read-only) Tenant is being moved to cloud storage
- **ONLOADING**: (Read-only) Tenant is being activated from cloud storage

```php
// Manage tenant status
$tenants->activate('customer-123');      // Set to ACTIVE
$tenants->deactivate('customer-456');    // Set to INACTIVE
$tenants->offload('customer-789');       // Set to OFFLOADED

// Update multiple tenants at once
$tenants->activate(['customer-123', 'customer-456']);

// Manual status updates
$tenants->update(new Tenant('customer-123', TenantActivityStatus::OFFLOADED));
```

### Working with Tenant Data

Once tenants are created, you can work with tenant-specific data:

```php
// Get tenant-specific collection instance
$customerCollection = $collection->withTenant('customer-123');

// Create data for specific tenant
$articleId = $customerCollection->data()->create([
    'title' => 'Customer 123 Article',
    'content' => 'This article belongs to customer 123',
    'author' => 'John Doe'
]);

// Retrieve data from specific tenant
$article = $customerCollection->data()->get($articleId);

// Update data within tenant
$customerCollection->data()->update($articleId, [
    'title' => 'Updated Article Title'
]);

// Data isolation - tenant A cannot access tenant B's data
$customerACollection = $collection->withTenant('customer-a');
$customerBCollection = $collection->withTenant('customer-b');

$articleA = $customerACollection->data()->create(['title' => 'Article A']);
$articleB = $customerBCollection->data()->create(['title' => 'Article B']);

// This will return null - tenant A cannot see tenant B's data
$result = $customerACollection->data()->get($articleB); // null
```

### Tenant Management Best Practices

1. **Tenant Naming**: Use consistent, meaningful tenant names (e.g., customer IDs, organization slugs)
2. **Status Management**: Use INACTIVE for temporary suspension, OFFLOADED for long-term storage
3. **Batch Operations**: Create/update multiple tenants at once for better performance
4. **Error Handling**: Always check tenant existence before performing operations
5. **Data Isolation**: Remember that tenant data is completely isolated

```php
// Example: Comprehensive tenant management
try {
    $tenants = $collection->tenants();

    // Check if tenant exists before creating
    if (!$tenants->exists('new-customer')) {
        $tenants->create('new-customer');
    }

    // Batch create multiple tenants
    $newTenants = ['customer-001', 'customer-002', 'customer-003'];
    $tenants->create($newTenants);

    // Get all active tenants
    $allTenants = $tenants->get();
    $activeTenants = array_filter($allTenants, function($tenant) {
        return $tenant->getActivityStatus() === TenantActivityStatus::ACTIVE;
    });

    echo "Found " . count($activeTenants) . " active tenants";

} catch (Exception $e) {
    echo "Error managing tenants: " . $e->getMessage();
}
```

## Development

### Running Tests

#### Unit Tests (No External Dependencies)
```bash
# Install dependencies
composer install

# Run unit tests only
composer test-unit
```

#### Integration Tests (Requires Weaviate)

**Option 1: Using Docker (Recommended)**
```bash
# Start Weaviate and run all tests
composer test-docker

# Run only integration tests with Docker
composer test-docker-integration

# Start Weaviate instance for manual testing
composer docker-start

# Stop Weaviate instance
composer docker-stop

# Reset Weaviate data
composer docker-reset
```

**Option 2: Using External Weaviate Instance**
```bash
# Set environment variables
export WEAVIATE_URL=http://localhost:8080
export WEAVIATE_API_KEY=your-api-key  # Optional

# Run integration tests
composer test-integration

# Run all tests
composer test
```

#### Test Coverage
```bash
# Run tests with coverage report
composer test-coverage
```

### Code Quality

```bash
# Run PHPStan static analysis
composer phpstan

# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix
```

## Features

### Phase 1 (Current)
- ✅ Core client with multi-tenancy support
- ✅ Connection layer with authentication
- ✅ Collections API for basic CRUD operations
- ✅ Data operations with tenant support

### Planned Features
- Batch operations
- Query builder with filters
- GraphQL support
- Vector operations
- Advanced schema management

## Architecture

The client follows a modular architecture:

- **WeaviateClient**: Main entry point
- **Connection**: HTTP communication layer
- **Auth**: Authentication mechanisms
- **Collections**: Collection management
- **Data**: Object CRUD operations
- **Schema**: Schema management

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## Testing Philosophy

This project follows Test-Driven Development (TDD):
- Tests drive the implementation
- High test coverage is maintained
- Both unit and integration tests are included

## Documentation

### API Reference
- [Complete API Documentation](docs/API.md) - Detailed API reference with examples
- [Error Handling Guide](docs/ERROR_HANDLING.md) - Comprehensive error handling and retry strategies
- [Python Client Migration Guide](docs/PYTHON_MIGRATION.md) - For developers familiar with the Python client

### Key Features
- **Three Connection Methods**: `connectToLocal()`, `connectToWeaviateCloud()`, `connectToCustom()`
- **Full Python Client Parity**: Same functionality and patterns as the Python client
- **Multi-Tenancy Support**: Built-in support for tenant-specific operations
- **Type Safety**: Full PHPStan level 8 compliance with comprehensive type annotations
- **Comprehensive Testing**: Unit, integration, and mock tests with 200+ assertions
- **Advanced Error Handling**: Complete exception hierarchy with retry mechanisms
- **Automatic Retries**: Exponential backoff for transient failures

## Support

For issues and questions, please use the GitHub issue tracker.
