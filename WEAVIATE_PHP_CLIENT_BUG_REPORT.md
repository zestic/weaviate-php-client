# Bug Report: Tenant exists() and getByName() methods not working correctly

## Summary
The `exists()` and `getByName()` methods in the Tenants class consistently return `false`/`null` even after successful tenant creation, while the `get()` method correctly shows the tenant exists.

## Environment
- **PHP Version**: 8.4.10
- **Weaviate PHP Client Version**: Latest (from composer)
- **Weaviate Server Version**: Latest (Docker)
- **Operating System**: Linux (Docker container)

## Expected Behavior
After creating a tenant with `$tenants->create('tenant-name')`:
1. `$tenants->exists('tenant-name')` should return `true`
2. `$tenants->getByName('tenant-name')` should return a Tenant object
3. This matches the behavior shown in the client's own integration tests

## Actual Behavior
After creating a tenant with `$tenants->create('tenant-name')`:
1. `$tenants->exists('tenant-name')` returns `false` ❌
2. `$tenants->getByName('tenant-name')` returns `null` ❌
3. `$tenants->get()` correctly shows the tenant in the list ✅

**Note**: This affects **all tenant activity statuses** (ACTIVE, INACTIVE, etc.). Both `exists()` and `getByName()` fail regardless of whether the tenant is created with default ACTIVE status or explicitly set to INACTIVE status.

## Reproduction Steps

### 1. Create a multi-tenant collection
```php
$client = WeaviateClient::connectToLocal('localhost:8080');
$collectionName = 'TestCollection_' . uniqid();

$client->collections()->create($collectionName, [
    'properties' => [
        ['name' => 'title', 'dataType' => ['text']],
        ['name' => 'content', 'dataType' => ['text']],
    ],
    'multiTenancyConfig' => ['enabled' => true]
]);
```

### 2. Create tenants (both ACTIVE and INACTIVE)
```php
$collection = $client->collections()->get($collectionName);
$tenants = $collection->tenants();

// Create ACTIVE tenant (default)
$activeTenantName = 'test-tenant-active';
$activeResult = $tenants->create($activeTenantName);
// Returns: null (expected behavior)

// Create INACTIVE tenant (explicit status)
$inactiveTenantName = 'test-tenant-inactive';
$inactiveTenant = new \Weaviate\Tenants\Tenant($inactiveTenantName, \Weaviate\Tenants\TenantActivityStatus::INACTIVE);
$inactiveResult = $tenants->create($inactiveTenant);
// Returns: null (expected behavior)
```

### 3. Check tenant existence (BUG HERE - affects all statuses)
```php
// BUG: exists() returns false for ACTIVE tenant
$activeExists = $tenants->exists($activeTenantName);
var_dump($activeExists); // false ❌

// BUG: exists() returns false for INACTIVE tenant
$inactiveExists = $tenants->exists($inactiveTenantName);
var_dump($inactiveExists); // false ❌

// BUG: getByName() returns null for ACTIVE tenant
$activeTenant = $tenants->getByName($activeTenantName);
var_dump($activeTenant); // null ❌

// BUG: getByName() returns null for INACTIVE tenant
$inactiveTenant = $tenants->getByName($inactiveTenantName);
var_dump($inactiveTenant); // null ❌

// WORKS: get() correctly shows both tenants exist with proper statuses
$allTenants = $tenants->get();
var_dump(array_keys($allTenants)); // ['test-tenant-active', 'test-tenant-inactive'] ✅
foreach ($allTenants as $name => $tenant) {
    echo "Tenant: {$name}, Status: " . $tenant->getActivityStatus()->value . "\n";
}
// Output:
// Tenant: test-tenant-active, Status: ACTIVE
// Tenant: test-tenant-inactive, Status: INACTIVE
```

## Complete Test Script
```php
<?php
require_once 'vendor/autoload.php';
use Weaviate\WeaviateClient;

$client = WeaviateClient::connectToLocal('localhost:8080');
$collectionName = 'BugTest_' . uniqid();

try {
    // Create multi-tenant collection
    $client->collections()->create($collectionName, [
        'properties' => [['name' => 'title', 'dataType' => ['text']]],
        'multiTenancyConfig' => ['enabled' => true]
    ]);
    
    $collection = $client->collections()->get($collectionName);
    $tenants = $collection->tenants();
    $tenantName = 'bug-test-tenant';
    
    // Create tenant
    $tenants->create($tenantName);
    
    // Test methods
    echo "exists(): " . ($tenants->exists($tenantName) ? 'true' : 'false') . "\n";
    echo "getByName(): " . ($tenants->getByName($tenantName) ? 'object' : 'null') . "\n";
    echo "get() keys: " . json_encode(array_keys($tenants->get())) . "\n";
    
} finally {
    $client->collections()->delete($collectionName);
}
```

**Expected Output:**
```
exists(): true
getByName(): object
get() keys: ["bug-test-tenant"]
```

**Actual Output:**
```
exists(): false
getByName(): null
get() keys: ["bug-test-tenant"]
```

## Impact
This bug prevents proper tenant management and breaks integration tests that rely on checking tenant existence after creation. It affects any application using multi-tenancy features.

## Workaround
Use `get()` method and check if tenant exists in the returned array:
```php
function tenantExists($tenants, $tenantName): bool {
    $allTenants = $tenants->get();
    return array_key_exists($tenantName, $allTenants);
}
```

## Investigation Notes
- The tenant **is** successfully created (appears in `get()` results)
- Multi-tenancy is properly enabled on the collection
- The issue appears to be in the HTTP client methods used by `exists()` and `getByName()`
- `exists()` uses HEAD request to `/v1/schema/{collection}/tenants/{tenant}`
- `getByName()` uses GET request to `/v1/schema/{collection}/tenants/{tenant}`
- Both may have issues with the HTTP response handling

## Suggested Fix Areas
1. Check HTTP status code handling in `HttpConnection::head()` method
2. Verify response parsing in `Tenants::getByName()` method
3. Ensure proper error handling for 404 vs other HTTP errors
4. Add retry mechanism for eventual consistency issues

## Additional Context
This issue was discovered while implementing integration tests for a production application. The client's own integration tests may be passing due to different test environment setup or timing.
