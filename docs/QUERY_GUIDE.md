# Weaviate PHP Client - Query Guide

This guide provides comprehensive documentation for the query functionality in the Weaviate PHP client, which offers Python client v4 API compatibility.

## Table of Contents

- [Overview](#overview)
- [Basic Concepts](#basic-concepts)
- [Filter System](#filter-system)
- [Query Builder](#query-builder)
- [Advanced Usage](#advanced-usage)
- [Performance Tips](#performance-tips)
- [Error Handling](#error-handling)
- [Migration from Other Clients](#migration-from-other-clients)

## Overview

The Weaviate PHP client provides a powerful query system that allows you to:

- Filter objects by properties, IDs, and complex conditions
- Build GraphQL queries programmatically
- Execute queries with tenant isolation
- Handle errors gracefully with detailed context

The API is designed to match the Python client v4 patterns for consistency across language implementations.

## Basic Concepts

### Filter Classes

The query system is built around three main filter classes:

- **`Filter`** - Base class with static factory methods
- **`PropertyFilter`** - Filters based on object properties
- **`IdFilter`** - Filters based on object IDs

### Query Builder

The **`QueryBuilder`** class handles:

- GraphQL query generation
- Property selection
- Result limiting
- Tenant-aware queries
- Error handling

## Filter System

### Property Filters

Property filters allow you to filter objects based on their property values:

```php
use Weaviate\Query\Filter;

// Equality
$filter = Filter::byProperty('status')->equal('published');
$filter = Filter::byProperty('age')->equal(25);
$filter = Filter::byProperty('active')->equal(true);

// Inequality
$filter = Filter::byProperty('status')->notEqual('draft');

// Comparisons
$filter = Filter::byProperty('viewCount')->greaterThan(1000);
$filter = Filter::byProperty('price')->lessThan(99.99);

// Pattern matching
$filter = Filter::byProperty('title')->like('*AI*');
$filter = Filter::byProperty('email')->like('*@example.com');

// Null checks
$filter = Filter::byProperty('deletedAt')->isNull(true);  // Find deleted items
$filter = Filter::byProperty('deletedAt')->isNull(false); // Find active items

// Array containment
$filter = Filter::byProperty('tags')->containsAny(['php', 'javascript', 'python']);
```

### ID Filters

ID filters allow you to filter objects by their unique identifiers:

```php
// Single ID
$filter = Filter::byId()->equal('123e4567-e89b-12d3-a456-426614174000');

// Multiple IDs
$filter = Filter::byId()->containsAny([
    '123e4567-e89b-12d3-a456-426614174000',
    '987fcdeb-51a2-43d1-9f12-345678901234',
    '456789ab-cdef-1234-5678-90abcdef1234'
]);

// Exclude specific ID
$filter = Filter::byId()->notEqual('123e4567-e89b-12d3-a456-426614174000');
```

### Complex Filters

Combine multiple filters using logical operators:

```php
// AND logic - all conditions must be true
$filter = Filter::allOf([
    Filter::byProperty('status')->equal('published'),
    Filter::byProperty('viewCount')->greaterThan(100),
    Filter::byProperty('publishedAt')->greaterThan(new DateTime('-30 days'))
]);

// OR logic - any condition can be true
$filter = Filter::anyOf([
    Filter::byProperty('status')->equal('published'),
    Filter::byProperty('status')->equal('featured'),
    Filter::byProperty('priority')->equal('high')
]);

// Nested logic
$complexFilter = Filter::allOf([
    Filter::byProperty('category')->equal('technology'),
    Filter::anyOf([
        Filter::byProperty('status')->equal('published'),
        Filter::byProperty('featured')->equal(true)
    ]),
    Filter::byProperty('viewCount')->greaterThan(500)
]);
```

## Query Builder

### Basic Usage

```php
$collection = $client->collections()->get('Article');

// Simple query
$results = $collection->query()->fetchObjects();

// With filters
$results = $collection->query()
    ->where(Filter::byProperty('status')->equal('published'))
    ->fetchObjects();

// With limit
$results = $collection->query()
    ->where(Filter::byProperty('category')->equal('tech'))
    ->limit(10)
    ->fetchObjects();

// With custom properties
$results = $collection->query()
    ->returnProperties(['title', 'content', 'publishedAt'])
    ->where(Filter::byProperty('status')->equal('published'))
    ->limit(20)
    ->fetchObjects();
```

### Method Chaining

The QueryBuilder supports fluent method chaining:

```php
$results = $collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('status')->equal('published'),
        Filter::byProperty('category')->equal('technology')
    ]))
    ->returnProperties(['title', 'summary', 'publishedAt', 'author'])
    ->limit(15)
    ->fetchObjects();
```

### Default Fields Configuration

Configure default fields per collection for optimized queries:

```php
// Set default fields
$collection = $client->collections()->get('Article')
    ->setDefaultQueryFields('title content publishedAt viewCount status author');

// Queries without returnProperties() will use these defaults
$results = $collection->query()
    ->where(Filter::byProperty('status')->equal('published'))
    ->fetchObjects(); // Returns configured default fields + _additional.id
```

## Advanced Usage

### Multi-Tenant Queries

```php
// Query within specific tenant
$tenantCollection = $collection->withTenant('customer-123');

$results = $tenantCollection->query()
    ->where(Filter::byProperty('status')->equal('active'))
    ->fetchObjects();

// Default fields are preserved across tenant cloning
$optimizedCollection = $collection
    ->setDefaultQueryFields('title status createdAt')
    ->withTenant('customer-456');

$results = $optimizedCollection->query()
    ->where(Filter::byProperty('active')->equal(true))
    ->fetchObjects(); // Uses configured default fields
```

### Convenience Methods

The DataOperations class provides convenient methods for common queries:

```php
// Find by simple criteria
$activeUsers = $collection->data()->findBy(['status' => 'active']);

$recentArticles = $collection->data()->findBy([
    'status' => 'published',
    'featured' => true
], 10); // limit to 10 results

// Find single object
$user = $collection->data()->findOneBy(['email' => 'user@example.com']);

// Returns null if not found
$article = $collection->data()->findOneBy(['slug' => 'non-existent']);

// With tenant
$tenantData = $collection->withTenant('customer-123')->data();
$customerArticles = $tenantData->findBy(['published' => true]);
```

### Working with Different Data Types

```php
// String values
Filter::byProperty('title')->equal('My Article')
Filter::byProperty('title')->like('*search term*')

// Numeric values
Filter::byProperty('viewCount')->greaterThan(1000)
Filter::byProperty('price')->lessThan(99.99)
Filter::byProperty('rating')->equal(5)

// Boolean values
Filter::byProperty('featured')->equal(true)
Filter::byProperty('published')->equal(false)

// Date values
Filter::byProperty('publishedAt')->greaterThan(new DateTime('2024-01-01'))
Filter::byProperty('createdAt')->lessThan(new DateTime('-7 days'))

// Array values
Filter::byProperty('tags')->containsAny(['php', 'web', 'api'])
```

## Performance Tips

1. **Use Specific Properties**: Only request the properties you need
   ```php
   ->returnProperties(['id', 'title', 'status']) // Good
   // vs fetching all properties (slower)
   ```

2. **Set Default Fields**: Configure optimized default fields per collection
   ```php
   $collection->setDefaultQueryFields('title status createdAt updatedAt');
   ```

3. **Use Limits**: Always use limits for large datasets
   ```php
   ->limit(50) // Prevent accidentally fetching thousands of objects
   ```

4. **Optimize Filters**: Put most selective filters first in allOf() conditions
   ```php
   Filter::allOf([
       Filter::byProperty('status')->equal('published'), // Most selective first
       Filter::byProperty('category')->equal('tech'),
       Filter::byProperty('viewCount')->greaterThan(100)
   ])
   ```

## Error Handling

### QueryException

All query-related errors throw `QueryException` with detailed context:

```php
use Weaviate\Query\Exception\QueryException;

try {
    $results = $collection->query()
        ->where(Filter::byProperty('invalidField')->equal('value'))
        ->fetchObjects();
} catch (QueryException $e) {
    // Basic error message
    echo "Query failed: " . $e->getMessage();
    
    // Access original GraphQL errors
    $graphqlErrors = $e->getGraphqlErrors();
    foreach ($graphqlErrors as $error) {
        echo "GraphQL Error: " . ($error['message'] ?? 'Unknown error');
        if (isset($error['path'])) {
            echo " at path: " . json_encode($error['path']);
        }
    }
    
    // Get formatted error details
    echo "Detailed errors:\n" . $e->getDetailedErrorMessage();
}
```

### Common Error Scenarios

1. **Invalid Property Names**
   ```php
   // This will throw QueryException if 'nonExistentField' doesn't exist
   Filter::byProperty('nonExistentField')->equal('value')
   ```

2. **Type Mismatches**
   ```php
   // This might fail if 'age' expects integer but gets string
   Filter::byProperty('age')->equal('not-a-number')
   ```

3. **Invalid GraphQL Syntax**
   ```php
   // Complex nested filters might generate invalid GraphQL
   // The QueryException will contain the GraphQL validation errors
   ```

## Migration from Other Clients

### From Python Client v4

The PHP client API closely matches the Python client v4:

```python
# Python client v4
collection.query.fetch_objects(
    filters=Filter.by_property("status").equal("published") & 
            Filter.by_property("viewCount").greater_than(1000),
    limit=10
)
```

```php
// PHP client (equivalent)
$collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('status')->equal('published'),
        Filter::byProperty('viewCount')->greaterThan(1000)
    ]))
    ->limit(10)
    ->fetchObjects();
```

### Key Differences

1. **Method Names**: PHP uses camelCase (`greaterThan` vs `greater_than`)
2. **Logical Operators**: PHP uses `Filter::allOf()` instead of `&` operator
3. **Array Syntax**: PHP uses explicit array syntax for multiple conditions

## Best Practices

1. **Always Handle Exceptions**: Wrap queries in try-catch blocks
2. **Use Type Hints**: Leverage PHP's type system for better IDE support
3. **Validate Input**: Check user input before building filters
4. **Test Queries**: Write unit tests for complex filter logic
5. **Monitor Performance**: Use limits and selective properties for large datasets
6. **Document Filters**: Comment complex filter logic for maintainability

## Examples Repository

For more examples and use cases, see the `tests/Integration/Query/` directory in the repository, which contains comprehensive integration tests demonstrating real-world usage patterns.
