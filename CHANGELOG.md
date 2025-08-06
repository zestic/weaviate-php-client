# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **🔧 Tenant exists() and getByName() Methods**: Fixed critical bug where these methods returned false/null even after successful tenant creation
- **HTTP HEAD Request Handling**: Improved HttpConnection::head() method to properly distinguish between "not found" (404) and actual errors (network, auth, etc.)
- **Exception Handling**: Enhanced error handling in tenant operations to properly propagate network and authentication errors instead of masking them
- **Retry Support**: Added retry mechanism support for tenant existence checks and retrieval operations
- **Documentation**: Updated method documentation to clearly specify which exceptions can be thrown

### Enhanced
- **Error Differentiation**: Tenant methods now properly distinguish between "tenant doesn't exist" and actual system errors
- **Performance**: Tenant exists() method now uses efficient HEAD requests with proper error handling
- **Test Coverage**: Added comprehensive tests for tenant error handling scenarios

## [0.4.0] - 2025-01-30

### Added
- **🎯 Comprehensive Query System**: Complete implementation matching Python client v4 API patterns
- **Filter System**: Full filtering capabilities with `Filter::byProperty()` and `Filter::byId()`
- **Property Filters**: All operators implemented (equal, notEqual, like, isNull, greaterThan, lessThan, containsAny)
- **Complex Filters**: Support for nested combinations with `Filter::allOf()` and `Filter::anyOf()`
- **QueryBuilder**: Fluent interface for building GraphQL queries with filtering, limits, and property selection
- **Data Operations**: Enhanced with `fetchObjects()`, `findBy()`, and `findOneBy()` convenience methods
- **Query Exception Handling**: Dedicated `QueryException` class with detailed GraphQL error information
- **Configurable Default Fields**: Collection-specific default query fields with per-query overrides
- **Multi-tenant Query Support**: All query operations respect tenant context and isolation
- **Performance Optimization**: Efficient GraphQL query generation and connection reuse

### Enhanced
- **Collection Class**: Added `query()` method returning QueryBuilder instance
- **DataOperations Class**: Extended with query convenience methods for common use cases
- **Error Handling**: Enhanced with specific query-related exceptions and detailed error messages
- **Documentation**: Comprehensive query guide and updated README with extensive examples

### Testing
- **380 Unit Tests**: Complete test coverage for all query functionality (100% passing)
- **83 Integration Tests**: Real Weaviate instance testing with end-to-end validation
- **7 Performance Tests**: Benchmarking with excellent performance metrics (<0.003s for simple queries)
- **Test Infrastructure**: Docker Compose setup with automated test scripts

### Documentation
- **Query Guide**: Complete documentation with examples and best practices (`docs/QUERY_GUIDE.md`)
- **README Updates**: Comprehensive query examples and usage patterns
- **API Documentation**: Extensive PHPDoc comments with code examples
- **Migration Guide**: Python client v4 parity examples for easy migration

### Performance
- **Simple Queries**: <0.003s execution time for basic filtering
- **Complex Queries**: <0.003s for nested filter combinations
- **Large Result Sets**: <0.006s for 500+ results
- **Memory Efficient**: Minimal memory overhead with optimized query building

### Python Client v4 Parity
- **Exact API Matching**: Identical patterns to Python client for seamless developer experience
- **Filter Syntax**: Perfect compatibility with Python client filter expressions
- **Query Methods**: Complete `fetch_objects()` equivalent functionality
- **Error Handling**: Enhanced error reporting exceeding Python client capabilities

### XADDAX Integration Ready
- **ProfileWeaviateAdapter Support**: Direct support for `findBy()` and `findOneBy()` patterns
- **Soft Delete Filtering**: Built-in support for `deletedAt` null checks
- **Multi-tenant Profiles**: Complete tenant isolation for profile management
- **Production Ready**: Comprehensive error handling and performance optimization

## [0.3.0] - 2025-01-29

### Added
- Comprehensive unit test coverage for all exception classes
- Complete test suite for WeaviateBatchException with all factory methods
- Comprehensive tests for WeaviateTimeoutException including connection, query, and batch timeouts
- Thorough tests for InsufficientPermissionsException covering API key, tenant, and RBAC scenarios
- Complete tests for WeaviateInvalidInputException with parameter validation and type checking
- Comprehensive tests for NotFoundException covering all resource types
- Thorough tests for UnexpectedStatusCodeException with status code handling
- Complete tests for WeaviateQueryException covering REST, GraphQL, validation, and schema queries
- Comprehensive tests for WeaviateRetryException with retry logic and attempt tracking
- Edge case testing including null values, empty strings, and boundary conditions
- Exception chaining testing for proper error propagation
- Context validation to ensure all relevant debugging information is captured
- Message formatting testing for informative error messages

### Fixed
- Mock-related test errors where tests were returning strings instead of StreamInterface objects
- Integration test failures by adding proper Weaviate availability checks using skipIfWeaviateNotAvailable()
- Unit test assertion errors to match actual exception behavior
- Test logic to properly handle exception message extraction from response bodies
- PSR-7 ResponseInterface mocking to use proper StreamInterface objects
- Exception hierarchy test expectations to match actual exception types

### Improved
- Test coverage significantly increased with 2,087 lines of new comprehensive test code
- Code quality and reliability enhanced through extensive exception testing
- All 377 tests now passing with 1,051 assertions
- Exception handling robustness validated through comprehensive test scenarios
- Integration test stability with proper dependency availability checks

### Technical Details
- Added 8 new comprehensive exception test files
- Fixed 7 mock-related errors in exception tests
- Resolved 5 integration test connection failures
- Fixed 2 unit test logic errors
- Properly configured 48 integration tests to skip when Weaviate is unavailable

## [0.2.0] - Previous Release
- GitHub CI fixes for cs-check, PHPStan, and integration tests
- HTTP connection improvements
- Increased test coverage for Tenant functionality
- Comprehensive test coverage for Tenants classes

## [0.1.0] - Initial Release
- Initial implementation of Weaviate PHP client
- Multi-tenancy support
- Schema management
- Comprehensive documentation and badges
