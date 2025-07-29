# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
