# Weaviate PHP Client Feature Request - Python Client Parity

## Summary

This document outlines critical features needed in the Weaviate PHP client to achieve parity with the Python client v4. These features are essential for production applications requiring cross-reference management, advanced querying, and robust multi-tenancy support.

## Environment

- **PHP Version**: 8.4.10
- **Current Weaviate PHP Client Version**: v0.4.0
- **Weaviate Server Version**: Latest (Docker)
- **Target**: Match Python client v4 functionality
- **Use Case**: Production applications with complex cross-reference relationships

## Current Status Assessment

### ✅ What Works Well (v0.4.0)
- **Entity CRUD Operations**: Create, update, delete individual objects
- **Schema Management**: Collection creation and property management
- **Multi-tenancy**: Basic tenant creation and data isolation
- **Basic Query System**: Simple filtering and property-based queries
- **Authentication**: API key and connection management

### ❌ Critical Missing Features (Python Client Parity)

## 1. Cross-Reference Management

### **Priority**: 🔴 **CRITICAL**

**Current Issue**: PHP client lacks cross-reference management methods that exist in Python client v4.

**Python Client v4 Has**:
```python
# Add cross-references
questions.data.reference_add(
    from_uuid=question_obj_id,
    from_property="hasCategory",
    to=category_obj_id
)

# Delete cross-references
questions.data.reference_delete(
    from_uuid=question_obj_id,
    from_property="hasCategory",
    to=category_obj_id
)

# Replace cross-references
questions.data.reference_replace(
    from_uuid=question_obj_id,
    from_property="hasCategory",
    to=category_obj_id
)

# Batch add cross-references
questions.data.reference_add_many([ref1, ref2, ref3])
```

**Required PHP Implementation**:
```php
// Add cross-references
$collection->data()->referenceAdd(
    fromUuid: $questionId,
    fromProperty: 'hasCategory',
    to: $categoryId
);

// Delete cross-references
$collection->data()->referenceDelete(
    fromUuid: $questionId,
    fromProperty: 'hasCategory',
    to: $categoryId
);

// Replace cross-references
$collection->data()->referenceReplace(
    fromUuid: $questionId,
    fromProperty: 'hasCategory',
    to: $categoryId
);

// Batch operations
$collection->data()->referenceAddMany([
    ['fromUuid' => $id1, 'fromProperty' => 'hasCategory', 'to' => $catId1],
    ['fromUuid' => $id2, 'fromProperty' => 'hasCategory', 'to' => $catId2]
]);
```

**Business Impact**:
- Cannot manage relationships between objects
- Third-party libraries cannot implement relationship management
- Severely limits application architecture options

## 2. Cross-Reference Querying

### **Priority**: 🔴 **CRITICAL**

**Current Issue**: PHP client cannot filter by cross-referenced properties like Python client v4.

**Python Client v4 Has**:
```python
# Filter by cross-referenced properties
response = questions.query.fetch_objects(
    filters=Filter.by_ref(link_on="hasCategory").by_property("title").like("*Sport*"),
    return_references=QueryReference(link_on="hasCategory", return_properties=["title"]),
    limit=3
)

# Include cross-references in results
response = questions.query.fetch_objects(
    return_references=QueryReference(
        link_on="hasCategory",
        return_properties=["title"]
    )
)
```

**Required PHP Implementation**:
```php
// Filter by cross-referenced properties
$results = $collection->query()
    ->where(Filter::byRef('hasCategory')->byProperty('title')->like('*Sport*'))
    ->returnReferences(['hasCategory' => ['title']])
    ->fetchObjects();

// Include cross-references in results
$results = $collection->query()
    ->returnReferences(['hasCategory' => ['title']])
    ->fetchObjects();
```

**Business Impact**:
- Cannot query objects based on their relationships
- Cannot retrieve relationship data with objects
- Limits complex application queries

## 3. Tenant Management Fixes

### **Priority**: 🟡 **HIGH**

**Current Issue**: Tenant existence checking methods don't work (already reported in `WEAVIATE_PHP_CLIENT_BUG_REPORT.md`).

**Python Client Pattern**: Python client works with tenant operations through standard collection methods.

**Required Fix**:
```php
// These should work as convenience methods
$exists = $tenants->exists('tenant-name');        // Should return true/false
$tenant = $tenants->getByName('tenant-name');     // Should return Tenant object
```

**Business Impact**:
- Cannot reliably check tenant existence before operations
- Risk of duplicate tenant creation attempts
- Complicates tenant management workflows

## 4. Advanced Query Operations

### **Priority**: 🟡 **HIGH**

**Current Issue**: PHP client lacks advanced query capabilities that exist in Python client v4.

**Python Client v4 Has**:
```python
# Aggregation queries
response = collection.query.aggregate.over_all(
    group_by="category",
    return_metrics=[Metrics("count")]
)

# Complex filtering with multiple conditions
response = collection.query.fetch_objects(
    filters=Filter.all_of([
        Filter.by_property("status").equal("active"),
        Filter.by_property("points").greater_than(300)
    ])
)
```

**Required PHP Implementation**:
```php
// Aggregation queries
$results = $collection->query()
    ->aggregate()
    ->groupBy('category')
    ->metrics(['count'])
    ->execute();

// Complex filtering (already partially supported, needs enhancement)
$results = $collection->query()
    ->where(Filter::allOf([
        Filter::byProperty('status')->equal('active'),
        Filter::byProperty('points')->greaterThan(300)
    ]))
    ->fetchObjects();
```

## 5. Enhanced Multi-Tenancy Features

### **Priority**: 🟢 **MEDIUM**

**Current Issue**: PHP client lacks some multi-tenancy conveniences that would improve usability.

**Python Client Pattern**: Python client handles multi-tenancy through collection-level tenant specification.

**Required Functionality**:

#### A. **Bulk Tenant Operations**
```php
// Efficient tenant management (convenience methods)
$tenants->createBatch(['tenant-1', 'tenant-2', 'tenant-3']);
$tenants->activateBatch(['tenant-1', 'tenant-2']);
$tenants->existsBatch(['tenant-1', 'tenant-2', 'tenant-3']); // Returns array
```

#### B. **Tenant-Aware Cross-Reference Operations**
```php
// Ensure cross-references respect tenant boundaries
$tenantCollection = $collection->withTenant('tenant-123');
$tenantCollection->data()->referenceAdd(
    fromUuid: $profileId,
    fromProperty: 'owns',
    to: $workspaceId
);
```

## Implementation Priority

### **Phase 1 (Critical - Python Client Parity)**
1. ✅ **Cross-Reference Management** - Essential for relationship management
2. ✅ **Cross-Reference Querying** - Essential for complex queries
3. ✅ **Tenant Management Bug Fix** - Fixes existing broken functionality

### **Phase 2 (High Priority - Enhanced Functionality)**
4. ✅ **Advanced Query Operations** - Enables complex application features
5. ✅ **Enhanced Multi-Tenancy** - Improves tenant management

## Expected API Design

Based on Python client v4 patterns and current PHP client conventions:

```php
// Cross-reference management (matching Python client patterns)
$collection->data()->referenceAdd(
    fromUuid: $questionId,
    fromProperty: 'hasCategory',
    to: $categoryId
);

// Cross-reference querying (matching Python client patterns)
$results = $collection->query()
    ->where(Filter::byRef('hasCategory')->byProperty('title')->like('*Sport*'))
    ->returnReferences(['hasCategory' => ['title']])
    ->fetchObjects();

// Tenant operations (fixing existing API)
$exists = $tenants->exists('tenant-name');
$tenant = $tenants->getByName('tenant-name');
$batch = $tenants->existsBatch(['tenant-1', 'tenant-2']);

// Advanced queries (extending current query builder)
$results = $collection->query()
    ->aggregate()
    ->groupBy('category')
    ->metrics(['count'])
    ->execute();
```

## Business Justification

These features are **critical for achieving Python client parity** and enabling production deployment of applications that require:

- **Cross-Reference Management**: Essential for managing relationships between objects
- **Advanced Querying**: Complex filtering and aggregation capabilities
- **Multi-Tenancy**: Reliable tenant management and isolation
- **Third-Party Integration**: Enabling libraries to build on top of the PHP client
- **Feature Parity**: Consistent experience across Weaviate client libraries

## Testing Requirements

All new features should include:
- ✅ **Unit Tests**: Comprehensive test coverage matching Python client patterns
- ✅ **Integration Tests**: Real Weaviate instance testing
- ✅ **Cross-Reference Tests**: Comprehensive relationship management testing
- ✅ **Multi-Tenant Tests**: Tenant isolation validation
- ✅ **Parity Tests**: Ensure functionality matches Python client v4

## Compatibility

- **Backward Compatibility**: All new features should be additive
- **API Consistency**: Follow existing PHP client patterns and Python client v4 conventions
- **PHP Version**: Support PHP 8.3+ (current requirement)
- **Weaviate Version**: Support latest Weaviate server versions
- **Python Client Alignment**: Maintain functional parity with Python client v4

## Success Criteria

The PHP client should achieve functional parity with Python client v4, enabling:
- Third-party libraries to implement relationship management
- Complex cross-reference queries and filtering
- Reliable multi-tenant operations
- Advanced querying and aggregation capabilities

---

**Reference**: This request is based on Python client v4 functionality and aims to achieve feature parity between the PHP and Python Weaviate clients.
