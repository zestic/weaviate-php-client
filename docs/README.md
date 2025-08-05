# Weaviate PHP Client Documentation

Welcome to the Weaviate PHP Client documentation. This directory contains comprehensive guides and references for using the client effectively.

## Documentation Structure

### Getting Started
- [Main README](../README.md) - Installation, basic usage, and examples
- [Quick Start Guide](#quick-start) - Get up and running in minutes

### Core Features
- [Query Guide](QUERY_GUIDE.md) - Comprehensive guide to filtering and querying data
- [Schema Management](#schema-management) - Creating and managing collections
- [Data Operations](#data-operations) - CRUD operations and data management
- [Multi-Tenancy](#multi-tenancy) - Tenant isolation and management

### Advanced Topics
- [Authentication](#authentication) - API keys and security
- [Error Handling](#error-handling) - Exception handling and debugging
- [Performance Optimization](#performance-optimization) - Best practices for production

### API Reference
- [Class Reference](#class-reference) - Detailed API documentation
- [Exception Reference](#exception-reference) - All exception types and handling

## Quick Start

```php
<?php

use Weaviate\WeaviateClient;
use Weaviate\Query\Filter;

// 1. Connect to Weaviate
$client = WeaviateClient::connectToLocal();

// 2. Get a collection
$collection = $client->collections()->get('Article');

// 3. Query data with filters
$results = $collection->query()
    ->where(Filter::byProperty('status')->equal('published'))
    ->limit(10)
    ->fetchObjects();

// 4. Work with results
foreach ($results as $article) {
    echo $article['title'] . "\n";
}
```

## Key Features

### ✅ Python Client v4 Compatible API
The PHP client provides API compatibility with the Python client v4, making it easy to migrate or work across different language implementations.

### ✅ Comprehensive Query System
- Property-based filtering with all operators
- ID-based filtering
- Complex nested filters with AND/OR logic
- GraphQL query generation
- Tenant-aware queries

### ✅ Multi-Tenancy Support
- Complete tenant isolation
- Tenant lifecycle management
- Tenant-aware data operations
- Batch tenant operations

### ✅ Production Ready
- Comprehensive error handling
- Type safety with PHP 8.3+
- Extensive test coverage
- Performance optimizations

## Examples by Use Case

### E-commerce Platform
```php
// Find featured products in a specific category
$products = $collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('category')->equal('electronics'),
        Filter::byProperty('featured')->equal(true),
        Filter::byProperty('inStock')->equal(true),
        Filter::byProperty('price')->lessThan(1000)
    ]))
    ->returnProperties(['name', 'price', 'description', 'imageUrl'])
    ->limit(20)
    ->fetchObjects();
```

### Content Management System
```php
// Find recent published articles by author
$articles = $collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('status')->equal('published'),
        Filter::byProperty('author')->equal('john-doe'),
        Filter::byProperty('publishedAt')->greaterThan(new DateTime('-30 days'))
    ]))
    ->returnProperties(['title', 'summary', 'publishedAt', 'slug'])
    ->limit(10)
    ->fetchObjects();
```

### User Management
```php
// Find active users with specific roles
$users = $collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('status')->equal('active'),
        Filter::byProperty('roles')->containsAny(['admin', 'editor']),
        Filter::byProperty('lastLoginAt')->greaterThan(new DateTime('-7 days'))
    ]))
    ->returnProperties(['username', 'email', 'roles', 'lastLoginAt'])
    ->fetchObjects();
```

### Multi-Tenant SaaS
```php
// Query data within specific tenant
$tenantCollection = $collection->withTenant('customer-123');

$customerData = $tenantCollection->query()
    ->where(Filter::byProperty('active')->equal(true))
    ->fetchObjects();

// Batch operations across tenants
$tenants = $collection->tenants();
$allTenants = $tenants->get();

foreach ($allTenants as $tenant) {
    if ($tenant->getActivityStatus() === TenantActivityStatus::ACTIVE) {
        $tenantData = $collection->withTenant($tenant->getName())
            ->data()
            ->findBy(['needsProcessing' => true]);
        
        // Process tenant-specific data
        processData($tenantData);
    }
}
```

## Contributing to Documentation

We welcome contributions to improve the documentation:

1. **Found an Error?** Open an issue or submit a PR
2. **Missing Examples?** Add real-world use cases
3. **Unclear Explanations?** Help us make it clearer
4. **New Features?** Document new functionality

### Documentation Standards

- Use clear, concise language
- Provide working code examples
- Include error handling examples
- Test all code examples
- Follow PHP coding standards
- Use proper markdown formatting

## Getting Help

- **GitHub Issues**: Report bugs or request features
- **Discussions**: Ask questions and share ideas
- **Community**: Join the Weaviate community forums

## Version Compatibility

This documentation covers:
- **PHP Client**: v1.0.0+
- **PHP Version**: 8.3+
- **Weaviate**: v1.20.0+

For older versions, please refer to the appropriate version tags in the repository.
