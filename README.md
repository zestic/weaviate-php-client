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

// Work with tenant-specific data
$orgId = '123e4567-e89b-12d3-a456-426614174000';
$result = $client->collections()->get('Organization')
    ->withTenant('tenant1')
    ->data()
    ->create([
        'id' => $orgId,
        'name' => 'ACME Corp',
        'createdAt' => '2024-01-01T00:00:00Z'
    ]);

// Retrieve object
$org = $client->collections()->get('Organization')
    ->withTenant('tenant1')
    ->data()
    ->get($orgId);
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

## Support

For issues and questions, please use the GitHub issue tracker.
