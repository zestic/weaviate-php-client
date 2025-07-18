
# Weaviate PHP Client Implementation Plan

## Recommendation for PHP Client
Based on this analysis, I recommend creating a new PHP client that borrows from both:

### Hybrid Approach
- Architecture: Follow Python's class-based structure for familiarity
- API Style: Adopt TypeScript's fluent interface patterns
- Features: Implement full feature parity with both clients

## Proposed PHP Client Structure
src
```php
<?php

namespace Weaviate;

class WeaviateClient
{
    public function __construct(
        private ConnectionInterface $connection,
        private AuthInterface $auth = null
    ) {}

    public function collections(): Collections
    {
        return new Collections($this->connection);
    }

    public function schema(): Schema
    {
        return new Schema($this->connection);
    }

    public function batch(): Batch
    {
        return new Batch($this->connection);
    }

    public function graphql(): GraphQL
    {
        return new GraphQL($this->connection);
    }
}
```
Fluent Interface Example
src/Collections
```php
<?php

namespace Weaviate\Collections;

class Collection
{
    public function withTenant(string $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->connection, $this->name, $this->tenant);
    }

    public function data(): DataOperations
    {
        return new DataOperations($this->connection, $this->name, $this->tenant);
    }
}
```

## Phased Implementation Strategy

### Phase 1: Core Client with Multi-Tenancy (MVP)
Focus on your immediate needs for organization and workspace management.

#### Components to Implement:
1. **Connection Layer**
   - HTTP client abstraction
   - Authentication support (API key)
   - Enhanced error handling with retry mechanisms
   - **NEW**: Connection helper functions (Python client parity)

2. **Collections API**
   - Create/get/update/delete collections
   - Multi-tenancy support
   - Basic property configuration

3. **Data Operations**
   - CRUD operations for objects
   - Tenant-specific operations
   - Basic reference handling

4. **Connection Helpers** (Python Client Parity)
   - `connect_to_local()` function
   - `connect_to_weaviate_cloud()` function
   - `connect_to_custom()` function
   - Improved user experience and API consistency

#### Example Usage for Phase 1:
```php
// Initialize client
$client = new Weaviate\WeaviateClient([
    'scheme' => 'http',
    'host' => 'localhost:8080',
    'auth' => new Weaviate\Auth\ApiKey('my-api-key')
]);

// Create organization collection if not exists
if (!$client->collections()->exists('Organization')) {
    $client->collections()->create('Organization', [
        'properties' => [
            ['name' => 'name', 'dataType' => ['text']],
            ['name' => 'createdAt', 'dataType' => ['date']]
        ],
        'multiTenancyConfig' => ['enabled' => true]
    ]);
}

// Create workspace collection if not exists
if (!$client->collections()->exists('Workspace')) {
    $client->collections()->create('Workspace', [
        'properties' => [
            ['name' => 'name', 'dataType' => ['text']],
            ['name' => 'organizationId', 'dataType' => ['uuid']],
            ['name' => 'createdAt', 'dataType' => ['date']]
        ],
        'multiTenancyConfig' => ['enabled' => true]
    ]);
}

// Create organization with tenant
$orgId = Uuid::uuid4()->toString();
$client->collections()->get('Organization')
    ->withTenant('tenant1')
    ->data()
    ->create([
        'id' => $orgId,
        'name' => 'ACME Corp',
        'createdAt' => new DateTime()
    ]);

// Create workspace with tenant
$client->collections()->get('Workspace')
    ->withTenant('tenant1')
    ->data()
    ->create([
        'name' => 'Project X',
        'organizationId' => $orgId,
        'createdAt' => new DateTime()
    ]);
```

### Phase 2: Enhanced Data Operations
Expand capabilities for more complex data operations.

#### Components to Implement:
1. **References API**
   - Create/update/delete references between objects
   - Cross-collection references

2. **Batch Operations**
   - Basic batch object creation
   - Batch reference creation

3. **Improved Error Handling**
   - Detailed error messages
   - Retry mechanisms

#### Example Usage for Phase 2:
```php
// Batch create workspaces
$batch = $client->batch();
$workspaces = [
    ['name' => 'Project A', 'organizationId' => $orgId],
    ['name' => 'Project B', 'organizationId' => $orgId],
    ['name' => 'Project C', 'organizationId' => $orgId]
];

foreach ($workspaces as $workspace) {
    $batch->addObject(
        'Workspace',
        $workspace,
        'tenant1'
    );
}

$batch->create();

// Create references between objects
$client->collections()->get('Organization')
    ->withTenant('tenant1')
    ->data()
    ->referenceAdd(
        $orgId,
        'workspaces',
        ['id' => $workspaceId, 'collection' => 'Workspace']
    );
```

### Phase 3: Query Capabilities
Add basic query capabilities for retrieving data.

#### Components to Implement:
1. **Query Builder**
   - Filter operations
   - Sorting and pagination
   - Property selection

2. **GraphQL Support**
   - Basic GraphQL queries
   - Field selection

#### Example Usage for Phase 3:
```php
// Get all workspaces for an organization
$workspaces = $client->collections()->get('Workspace')
    ->withTenant('tenant1')
    ->query()
    ->withFilter([
        'path' => ['organizationId'],
        'operator' => 'Equal',
        'valueText' => $orgId
    ])
    ->withLimit(10)
    ->withSort(['name'])
    ->do();

// GraphQL query
$result = $client->graphql()->get()
    ->withClassName('Organization')
    ->withTenant('tenant1')
    ->withFields('name createdAt')
    ->withWhere([
        'operator' => 'Equal',
        'path' => ['id'],
        'valueString' => $orgId
    ])
    ->do();
```

### Phase 4: Advanced Features
Add more advanced features as needed.

#### Components to Implement:
1. **Vector Operations**
   - Basic vector search
   - Near text/vector queries

2. **Schema Management**
   - Advanced schema configuration
   - Property validation

3. **Authentication Options**
   - OIDC support
   - Client credentials

## Implementation Guidelines

### Code Quality Standards
- PSR-12 coding standards
- PHPStan level 8 for static analysis
- 100% unit test coverage for core components
- Integration tests against Weaviate instance

### Dependencies
- PHP 8.3 or higher
- PSR-18 HTTP client (Guzzle recommended)
- PSR-7 HTTP message implementation
- PSR-17 HTTP factory
- Ramsey/UUID for UUID handling

### Documentation
- PHPDoc for all classes and methods
- README with installation and basic usage
- Examples directory with common use cases
- Upgrade guide for users of older clients

## Development Roadmap

### Week 1-2: Foundation
- Set up project structure
- Implement connection layer
- Basic collections API
- Authentication
- **NEW**: Connection helper functions

### Week 3-4: Multi-Tenancy & Enhanced Features
- Tenant operations
- Data CRUD with tenants
- Enhanced error handling with retry mechanisms
- **NEW**: Improved exception alignment with Python client

### Week 5-6: Testing & Documentation
- Unit tests
- Integration tests
- Documentation with Python client comparison
- Release MVP (v0.1.0)

### Subsequent Releases
- Phase 2 features (v0.2.0)
- Phase 3 features (v0.3.0)
- Phase 4 features (v0.4.0)
- Stable release (v1.0.0)

## Python Client Alignment Analysis

### ✅ Current Alignment Status
Our PHP client structure is well-aligned with the Python client:

- **Core Structure**: `WeaviateClient` mirrors Python's `WeaviateClient`
- **Collections API**: Our `Collections`/`Collection` classes follow Python's pattern
- **Authentication**: `AuthInterface` aligns with Python's auth system
- **Connection Layer**: `ConnectionInterface` provides similar abstraction
- **Exception Handling**: Basic exception structure in place

### 🔄 Enhancement Areas (Python Client Parity)

#### High Priority Enhancements
1. **Connection Helpers** (Phase 1 addition)
   - Add factory functions similar to Python client
   - `connect_to_local()`, `connect_to_weaviate_cloud()`, `connect_to_custom()`
   - Improves user experience and API consistency

2. **Enhanced Error Handling** (Phase 1 addition)
   - Align exception types with Python client
   - Add retry mechanisms
   - Better error messages and context

#### Medium Priority Enhancements (Future Phases)
3. **Backup Namespace** (Phase 4)
   - `backup()` method on main client
   - Backup creation, restoration, status checking
   - Aligns with Python's `_Backup` functionality

4. **Cluster Namespace** (Phase 4)
   - `cluster()` method on main client
   - Cluster status, node information
   - Aligns with Python's `_Cluster` functionality

5. **Debug Namespace** (Phase 4)
   - `debug()` method on main client
   - Debug utilities and cluster inspection
   - Aligns with Python's `_Debug` functionality

6. **RBAC Namespaces** (Phase 4)
   - `roles()` and `users()` methods on main client
   - Role-based access control functionality
   - Aligns with Python's `_Roles` and `_Users` functionality

#### Example Enhanced Client Structure
```php
class WeaviateClient
{
    // Current methods
    public function collections(): Collections { }
    public function schema(): Schema { }

    // Phase 1 additions
    public function batch(): Batch { }

    // Phase 4 additions (Python parity)
    public function backup(): Backup { }
    public function cluster(): Cluster { }
    public function debug(): Debug { }
    public function roles(): Roles { }
    public function users(): Users { }
}
```

#### Connection Helpers Implementation
```php
// Add to main namespace (Phase 1)
namespace Weaviate;

function connect_to_local(
    string $host = 'localhost:8080',
    ?AuthInterface $auth = null,
    array $headers = []
): WeaviateClient;

function connect_to_weaviate_cloud(
    string $cluster_url,
    AuthInterface $auth,
    array $headers = []
): WeaviateClient;

function connect_to_custom(
    string $url,
    ?AuthInterface $auth = null,
    array $headers = []
): WeaviateClient;
```

## Compatibility Considerations

### Version Support
- Target Weaviate 1.31+ (with multi-tenancy)
- Version detection for feature availability
- Graceful degradation for unsupported features

### API Stability
- Semantic versioning
- Deprecation notices before breaking changes
- Migration guides for major versions

### Python Client Compatibility
- Follow Python client API patterns where applicable
- Maintain similar method signatures and behavior
- Provide migration examples from Python to PHP

