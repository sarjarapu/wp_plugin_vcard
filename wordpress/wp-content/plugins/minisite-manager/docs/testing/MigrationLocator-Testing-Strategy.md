# MigrationLocator Testing Strategy

## Overview

This document outlines the comprehensive testing strategy for the `MigrationLocator` class, which is responsible for discovering, loading, and ordering database migration classes in the WordPress minisite manager plugin.

## Class Purpose

The `MigrationLocator` class serves as a **discovery and loading mechanism** for database migration classes with the following key responsibilities:

1. **File Discovery**: Scans a directory for PHP files containing migration classes
2. **Class Loading**: Uses `require_once` to load migration files and leverages Composer's autoloading
3. **Interface Filtering**: Only includes classes that implement the `Migration` interface
4. **Safety Validation**: Ensures classes physically exist in the specified directory (prevents loading classes from other locations)
5. **Version Sorting**: Orders migrations by semantic version using `version_compare()`
6. **Instance Creation**: Creates instances of each migration class for execution

## Testing Approach

### 1. Unit Tests (`MigrationLocatorTest.php`)

**Purpose**: Test the class in isolation using mocked file systems and controlled environments.

**Key Testing Areas**:

#### File System Operations
- ✅ Directory existence validation
- ✅ PHP file discovery and filtering
- ✅ Non-PHP file exclusion
- ✅ Malformed PHP file handling

#### Class Discovery and Loading
- ✅ Migration interface validation
- ✅ Class instantiation
- ✅ Namespace handling
- ✅ Safety validation (path checking)

#### Version Sorting
- ✅ Semantic version ordering
- ✅ Pre-release version handling (alpha, beta, rc)
- ✅ Complex version number scenarios
- ✅ Edge cases (10.0.0 vs 2.0.0)

#### Error Handling
- ✅ Non-existent directories
- ✅ Empty directories
- ✅ Invalid PHP files
- ✅ Classes without Migration interface

**Testing Tools**:
- **vfsStream**: Virtual file system for isolated testing
- **PHPUnit**: Test framework with comprehensive assertions
- **Mock Objects**: For controlled test environments

### 2. Integration Tests (`MigrationLocatorIntegrationTest.php`)

**Purpose**: Test the class with real file system operations and temporary migration files.

**Key Testing Areas**:

#### Real File Operations
- ✅ Temporary directory creation and cleanup
- ✅ Real PHP file creation and loading
- ✅ Multiple migration file scenarios
- ✅ Complex migration scenarios

#### Database Integration
- ✅ Migration execution (up/down methods)
- ✅ Database table creation and deletion
- ✅ SQL operation validation
- ✅ Rollback functionality

#### Edge Cases
- ✅ File naming convention variations
- ✅ Namespace conflicts
- ✅ Class name conflicts
- ✅ Invalid migration files mixed with valid ones

**Testing Tools**:
- **Real File System**: Temporary directories for realistic testing
- **DatabaseTestHelper**: MySQL integration testing
- **FakeWpdb**: WordPress database abstraction for testing

### 3. Real File Integration Tests (`MigrationLocatorWithRealFilesTest.php`)

**Purpose**: Test with actual migration files in the test suite.

**Key Testing Areas**:

#### Production-like Scenarios
- ✅ Real migration file loading
- ✅ Actual Migration interface implementations
- ✅ Semantic versioning validation
- ✅ Interface contract fulfillment

#### Migration Execution
- ✅ Up method execution
- ✅ Down method execution
- ✅ Database operation validation
- ✅ Error handling during execution

**Testing Tools**:
- **Test Migration Files**: Real migration implementations
- **DatabaseTestHelper**: Full database integration
- **WordPress Environment**: Complete WordPress context

## Test Migration Files

### Purpose
Test migration files provide realistic scenarios for integration testing without affecting production data.

### Structure
```
tests/Support/TestMigrations/
├── _1_0_0_TestInitial.php      # Initial migration
├── _1_1_0_TestFeatures.php     # Feature addition
└── _2_0_0_TestBreaking.php     # Breaking changes
```

### Features
- **Semantic Versioning**: Follows proper version numbering
- **Database Operations**: Real SQL operations for testing
- **Rollback Support**: Proper down() method implementations
- **Realistic Scenarios**: Simulates actual migration patterns

## Test Coverage Areas

### ✅ Core Functionality
- [x] File discovery and loading
- [x] Class instantiation
- [x] Interface validation
- [x] Version sorting
- [x] Safety validation

### ✅ Error Handling
- [x] Non-existent directories
- [x] Empty directories
- [x] Invalid files
- [x] Malformed PHP
- [x] Missing interfaces

### ✅ Edge Cases
- [x] Complex version numbers
- [x] Pre-release versions
- [x] Namespace conflicts
- [x] Class name conflicts
- [x] File naming variations

### ✅ Integration Scenarios
- [x] Real file system operations
- [x] Database operations
- [x] Migration execution
- [x] Rollback functionality
- [x] WordPress environment

## Running the Tests

### Unit Tests
```bash
./vendor/bin/phpunit tests/Unit/Infrastructure/Versioning/MigrationLocatorTest.php
```

### Integration Tests
```bash
./vendor/bin/phpunit tests/Integration/Infrastructure/Versioning/MigrationLocatorIntegrationTest.php
```

### Real File Tests
```bash
./vendor/bin/phpunit tests/Integration/Infrastructure/Versioning/MigrationLocatorWithRealFilesTest.php
```

### All MigrationLocator Tests
```bash
./vendor/bin/phpunit --filter MigrationLocator
```

## Test Dependencies

### Required Packages
- **PHPUnit**: Test framework
- **vfsStream**: Virtual file system for unit tests
- **MySQL**: Database for integration tests
- **WordPress**: WordPress environment for full integration

### Environment Setup
- MySQL test database configured
- WordPress test environment
- Composer autoloading
- Proper file permissions for temporary directories

## Best Practices Implemented

### 1. Test Isolation
- Each test is independent
- Proper setup and teardown
- No shared state between tests

### 2. Comprehensive Coverage
- Unit tests for isolated functionality
- Integration tests for real-world scenarios
- Edge case testing for robustness

### 3. Realistic Testing
- Real file system operations
- Actual database interactions
- Production-like migration files

### 4. Error Handling
- Graceful handling of invalid inputs
- Proper error reporting
- Defensive programming validation

### 5. Maintainability
- Clear test structure
- Descriptive test names
- Comprehensive documentation
- Easy to extend and modify

## Future Enhancements

### Potential Improvements
1. **Performance Testing**: Large number of migration files
2. **Concurrency Testing**: Multiple processes accessing migrations
3. **Memory Testing**: Large migration file handling
4. **Security Testing**: Malicious file handling
5. **Compatibility Testing**: Different PHP versions

### Additional Test Scenarios
1. **Migration Dependencies**: Testing migration ordering with dependencies
2. **Partial Failures**: Testing rollback scenarios
3. **Long-running Migrations**: Testing timeout scenarios
4. **Resource Constraints**: Testing under memory/disk limitations

## Conclusion

This comprehensive testing strategy ensures that the `MigrationLocator` class is robust, reliable, and maintainable. The combination of unit tests, integration tests, and real file tests provides confidence in the class's functionality across various scenarios and edge cases.

The testing approach follows industry best practices and provides a solid foundation for future development and maintenance of the migration system.
