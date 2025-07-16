
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
   - Error handling

2. **Collections API**
   - Create/get/update/delete collections
   - Multi-tenancy support
   - Basic property configuration

3. **Data Operations**
   - CRUD operations for objects
   - Tenant-specific operations
   - Basic reference handling

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

### Week 3-4: Multi-Tenancy
- Tenant operations
- Data CRUD with tenants
- Error handling

### Week 5-6: Testing & Documentation
- Unit tests
- Integration tests
- Documentation
- Release MVP (v0.1.0)

### Subsequent Releases
- Phase 2 features (v0.2.0)
- Phase 3 features (v0.3.0)
- Phase 4 features (v0.4.0)
- Stable release (v1.0.0)

## Compatibility Considerations

### Version Support
- Target Weaviate 1.31+ (with multi-tenancy)
- Version detection for feature availability
- Graceful degradation for unsupported features

### API Stability
- Semantic versioning
- Deprecation notices before breaking changes
- Migration guides for major versions

