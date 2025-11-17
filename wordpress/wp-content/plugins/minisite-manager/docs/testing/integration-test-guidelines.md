# Integration Test Guidelines

## Overview

This document provides guidelines for writing integration tests in the Minisite Manager plugin. Integration tests verify that multiple components work together correctly, using a real MySQL database connection.

## Base Class: `BaseIntegrationTest`

All integration tests should extend `Tests\Integration\BaseIntegrationTest`, which provides:

- Database connection setup
- EntityManager initialization
- WordPress constants and globals
- Table cleanup
- Migration running
- `wp_users` table stub for foreign keys
- Test user creation (ID = 1)

## Required Implementation

When extending `BaseIntegrationTest`, you must implement three abstract methods:

### 1. `getEntityPaths(): array`

Return an array of paths to entity directories that your test needs. These paths are used to configure Doctrine ORM.

**Example:**
```php
protected function getEntityPaths(): array
{
    return array(
        __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
        __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
    );
}
```

### 2. `setupTestSpecificServices(): void`

Initialize test-specific repositories, services, handlers, etc. This is called after migrations have run and tables are ready.

**Example:**
```php
protected function setupTestSpecificServices(): void
{
    // Get repository
    $classMetadata = $this->em->getClassMetadata(Config::class);
    $this->repository = new ConfigRepository($this->em, $classMetadata);

    // Create service
    $this->service = new ConfigurationManagementService($this->repository);

    // Create handlers
    $this->saveHandler = new SaveConfigHandler($this->service);
    $this->deleteHandler = new DeleteConfigHandler($this->service);
}
```

### 3. `cleanupTestData(): void`

Clean up test-specific data (but keep table structure). This is called in both `setUp()` (after migrations) and `tearDown()`.

**Example:**
```php
protected function cleanupTestData(): void
{
    try {
        $this->em->getConnection()->executeStatement(
            "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%' OR config_key IN ('workflow_key', 'workflow_delete')"
        );
    } catch (\Exception $e) {
        // Ignore errors - table might not exist or connection might be closed
    }
}
```

## Complete Example

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Tests\Integration\BaseIntegrationTest;

final class ConfigurationManagementWorkflowIntegrationTest extends BaseIntegrationTest
{
    private ConfigRepository $repository;
    private ConfigurationManagementService $service;

    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
            __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    protected function setupTestSpecificServices(): void
    {
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);
        $this->service = new ConfigurationManagementService($this->repository);
    }

    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%'"
            );
        } catch (\Exception $e) {
            // Ignore errors
        }
    }

    public function test_save_and_retrieve(): void
    {
        // Your test code here
        // Access $this->em, $this->connection, $this->repository, etc.
    }
}
```

## Available Properties

The base class provides these protected properties:

- `EntityManager $em` - Doctrine EntityManager
- `Connection $connection` - Doctrine DBAL Connection
- `string $dbName` - Database name

## Setup Flow

The base class `setUp()` method executes in this order:

1. `initializeLogging()` - Initialize LoggingServiceProvider
2. `defineConstants()` - Define WordPress DB constants and encryption key
3. `setupWordPressGlobals()` - Setup `$GLOBALS['wpdb']`
4. `createDatabaseConnection()` - Create Doctrine connection
5. `createEntityManager()` - Create EntityManager with your entity paths
6. `resetConnectionState()` - Ensure clean transaction state
7. `registerTablePrefixListener()` - Register WordPress table prefix support
8. `cleanupTables()` - Drop all migration tables
9. `createWordPressUsersTableStub()` - Create `wp_users` table
10. `createTestUser()` - Insert test user (ID = 1)
11. `runMigrations()` - Run all Doctrine migrations
12. `setupTestSpecificServices()` - **Your implementation**
13. `cleanupTestData()` - **Your implementation**

## TearDown Flow

The base class `tearDown()` method:

1. Calls `cleanupTestData()` - **Your implementation**
2. Clears EntityManager
3. Rolls back any active transactions
4. Closes EntityManager

You can override `tearDown()` to add test-specific cleanup, but **always call `parent::tearDown()`** at the end.

**Example:**
```php
protected function tearDown(): void
{
    // Reset static cache
    $reflection = new \ReflectionClass(MyService::class);
    $cacheProperty = $reflection->getProperty('cache');
    $cacheProperty->setAccessible(true);
    $cacheProperty->setValue(null, null);

    parent::tearDown(); // Always call parent
}
```

## Database Configuration

The base class reads database configuration from environment variables:

- `MYSQL_HOST` (default: `127.0.0.1`)
- `MYSQL_PORT` (default: `3307`)
- `MYSQL_DATABASE` (default: `minisite_test`)
- `MYSQL_USER` (default: `minisite`)
- `MYSQL_PASSWORD` (default: `minisite`)

These are also defined as WordPress constants (`DB_HOST`, `DB_PORT`, etc.) for use by `DoctrineFactory`.

## Table Cleanup

The base class automatically:

- Finds all tables with `wp_minisite_%` prefix
- Includes `wp_minisites` table
- Includes `wp_minisite_migrations` tracking table
- Includes `wp_users` table
- Drops all tables (with foreign key checks disabled)

**Note:** Tables are dropped in `setUp()` before migrations run, ensuring a clean slate for each test.

## Foreign Key Support

The base class automatically:

- Creates a minimal `wp_users` table with just `ID` column
- Inserts a test user with `ID = 1`
- Drops `wp_users` in cleanup

This allows migrations that create foreign keys to `wp_users` to work correctly.

## Best Practices

### 1. Use Descriptive Test Names

```php
// Good
public function test_save_workflow_end_to_end(): void
public function test_update_workflow_with_different_types(): void

// Bad
public function test1(): void
public function test_save(): void
```

### 2. Clean Up Test Data

Always clean up data created by your tests in `cleanupTestData()`. Use patterns like:

- `LIKE 'test_%'` for test data
- Specific keys for workflow tests
- Delete in reverse dependency order if needed

### 3. Handle Exceptions Gracefully

In `cleanupTestData()`, wrap cleanup in try-catch blocks:

```php
protected function cleanupTestData(): void
{
    try {
        $this->em->getConnection()->executeStatement("DELETE FROM ...");
    } catch (\Exception $e) {
        // Ignore errors - table might not exist or connection might be closed
    }
}
```

### 4. Test Complete Workflows

Integration tests should verify end-to-end workflows:

```php
public function test_save_workflow_end_to_end(): void
{
    // 1. Create command
    $command = new SaveConfigCommand('key', 'value', 'string');

    // 2. Execute via handler
    $this->handler->handle($command);

    // 3. Verify via service
    $result = $this->service->get('key');
    $this->assertEquals('value', $result);

    // 4. Verify via repository
    $config = $this->repository->findByKey('key');
    $this->assertNotNull($config);
}
```

### 5. Use EntityManager and Connection

Access the database via:

- `$this->em` - For Doctrine ORM operations
- `$this->connection` - For raw SQL queries

### 6. Don't Mock Database

Integration tests should use the **real database**. Don't mock repositories or database connections.

## Common Patterns

### Testing Repository Methods

```php
public function test_repository_find_by_id(): void
{
    // Create entity
    $entity = new MyEntity(/* ... */);
    $this->em->persist($entity);
    $this->em->flush();

    // Test repository
    $found = $this->repository->findById($entity->id);
    $this->assertNotNull($found);
    $this->assertEquals($entity->id, $found->id);
}
```

### Testing Service Methods

```php
public function test_service_workflow(): void
{
    // Use service
    $this->service->doSomething('test_key', 'test_value');

    // Verify via repository
    $result = $this->repository->findByKey('test_key');
    $this->assertNotNull($result);
}
```

### Testing with Transactions

The base class resets connection state, but you can use transactions in tests:

```php
public function test_with_transaction(): void
{
    $this->connection->beginTransaction();
    try {
        // Your test code
        $this->connection->commit();
    } catch (\Exception $e) {
        $this->connection->rollBack();
        throw $e;
    }
}
```

## Migration Notes

- Migrations are **automatically run** in `setUp()`
- All migration tables are **dropped** before migrations run
- Sample seed data is **automatically seeded** by migrations
- The `wp_users` table is created **before** migrations run

## Troubleshooting

### EntityManager is Closed

If you see "EntityManager is closed" errors, ensure you're not closing it in your test. The base class handles EntityManager lifecycle.

### Foreign Key Violations

If you see foreign key violations, ensure:
- The `wp_users` table exists (base class creates it)
- Test user (ID = 1) exists (base class creates it)
- Referenced entities exist before creating dependent entities

### Duplicate Entry Errors

If you see duplicate entry errors:
- Ensure `cleanupTestData()` properly deletes test data
- Check that test data uses unique identifiers
- Verify cleanup runs in both `setUp()` and `tearDown()`

## Refactoring Existing Tests

To refactor an existing integration test to use `BaseIntegrationTest`:

1. Change `extends TestCase` to `extends BaseIntegrationTest`
2. Remove duplicate setup code (logging, constants, connection, etc.)
3. Implement `getEntityPaths()` with your entity paths
4. Move repository/service initialization to `setupTestSpecificServices()`
5. Move data cleanup to `cleanupTestData()`
6. Remove duplicate `cleanupTables()` and `createWordPressUsersTableStub()` methods
7. Update `tearDown()` to call `parent::tearDown()`

## See Also

- `tests/Integration/BaseIntegrationTest.php` - Base class implementation
- `tests/Integration/Features/ConfigurationManagement/ConfigurationManagementWorkflowIntegrationTest.php` - Complete example

