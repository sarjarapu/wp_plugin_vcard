# Testing Strategy: DoctrineMigrationRunner

## Overview

`DoctrineMigrationRunner` is an **orchestrator** that:
- Discovers migration files
- Determines which migrations need to run
- Executes them in order
- Tracks execution state in metadata storage

**Key Principle**: We test the **orchestration logic**, NOT the individual migration implementations.

---

## What SHOULD Be Tested

### 1. **Migration Discovery**
- ✅ Can it find migration files in the directory?
- ✅ Does it correctly identify pending vs. executed migrations?
- ✅ What happens if the migration directory is empty?
- ✅ What happens if migrations exist but none are pending?

### 2. **Metadata Storage Management**
- ✅ Does it create `wp_minisite_migrations` table on first run?
- ✅ Does it correctly use WordPress table prefix (`$wpdb->prefix`)?
- ✅ Does it track executed migrations correctly?

### 3. **Execution Flow**
- ✅ Does it execute pending migrations?
- ✅ Does it skip already-executed migrations?
- ✅ Are migrations executed in the correct order (by version)?
- ✅ Does it handle the case where all migrations are already executed?

### 4. **Idempotency**
- ✅ Can `migrate()` be called multiple times safely?
- ✅ Second call with no new migrations doesn't cause errors

### 5. **Error Handling**
- ✅ What happens if Doctrine is not available? (graceful skip)
- ✅ What happens if metadata storage initialization fails? (logs warning, continues)
- ✅ What happens if a migration throws an exception? (propagates error)
- ✅ What happens if no migrations found but `latestVersion` is null? (throws RuntimeException)

### 6. **Dependency Injection**
- ✅ Does it use injected `EntityManager` when provided?
- ✅ Does it fall back to `DoctrineFactory::createEntityManager()` when not provided?

### 7. **Configuration**
- ✅ Does it set up migration paths correctly?
- ✅ Does it configure metadata storage table with correct prefix?
- ✅ Does it use correct namespace for migrations?

---

## What SHOULD NOT Be Tested

### ❌ Individual Migration Logic
- Individual migration `up()`/`down()` methods are already tested in `Version20251103000000Test`
- Schema creation details are covered by migration-specific tests
- Table structure verification is done in migration tests

### ❌ Doctrine Framework Internals
- How Doctrine discovers files (we trust Doctrine works)
- How Doctrine executes SQL (we trust Doctrine works)
- Doctrine's migration version comparison logic (framework responsibility)

---

## Test Approach

### Integration Tests (Recommended)

**Why Integration Tests?**
- Tests the **real** orchestration flow
- Validates interaction with actual database
- Ensures metadata storage works correctly
- Catches integration issues (prefix, connection, etc.)

**Test Structure:**
```php
class DoctrineMigrationRunnerIntegrationTest extends AbstractDoctrineMigrationTest
{
    // Tests focus on:
    // - Discovery (find migrations)
    // - Execution tracking (metadata table)
    // - Idempotency (multiple calls)
    // - Error scenarios
    // - Edge cases
}
```

**Test Scenarios:**

1. **`test_migrate_discovers_and_executes_pending_migrations()`**
   - Verify migrations are discovered
   - Verify they're executed
   - Verify they're tracked in metadata table

2. **`test_migrate_skips_already_executed_migrations()`**
   - Run migration once
   - Verify it's in metadata table
   - Run again - should skip (no new migrations)

3. **`test_migrate_is_idempotent()`**
   - Run multiple times
   - Verify no errors
   - Verify state is consistent

4. **`test_migrate_creates_metadata_storage_table()`**
   - First run creates `wp_minisite_migrations`
   - Verify table structure

5. **`test_migrate_uses_wordpress_table_prefix()`**
   - Verify metadata table uses `wp_` prefix (from `$wpdb->prefix`)

6. **`test_migrate_handles_missing_doctrine_gracefully()`**
   - Mock scenario where Doctrine class doesn't exist
   - Should log warning and return early

7. **`test_migrate_with_injected_entity_manager()`**
   - Pass EntityManager in constructor
   - Verify it uses injected one instead of creating new

8. **`test_migrate_propagates_migration_exceptions()`**
   - If a migration fails, runner should propagate exception
   - (This might require a test migration that throws)

---

## Test Implementation Strategy

### Option 1: Use Real Migrations (Recommended)
- Use actual `Version20251103000000` migration
- Test that runner discovers and executes it
- Verify execution tracking
- **Pros**: Real integration, catches actual issues
- **Cons**: Depends on actual migration files existing

### Option 2: Use Test Migrations
- Create test-only migrations in a separate directory
- Point runner at test directory
- Test orchestration logic
- **Pros**: Isolated, can test edge cases
- **Cons**: More setup, less realistic

### Recommendation: **Option 1**
Since we already have `Version20251103000000`, use it:
- Tests are realistic
- Less code to maintain
- Catches real integration issues
- We're testing orchestration, not migration content

---

## Test Coverage Goals

**Target Coverage Areas:**
1. ✅ `migrate()` method - all code paths
2. ✅ `getTablePrefix()` - verify prefix usage
3. ✅ Error handling branches
4. ✅ Edge cases (empty migrations, all executed, etc.)

**Not Covered:**
- ❌ Individual migration implementation (separate tests)
- ❌ Doctrine framework internals (trust the framework)

---

## Example Test Structure

```php
final class DoctrineMigrationRunnerIntegrationTest extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        // Return tables that migrations create
        return ['wp_minisite_config'];
    }
    
    public function test_migrate_discovers_and_executes_pending_migrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Verify no migrations executed yet
        $this->assertEmpty($this->getExecutedMigrations());
        
        // Run migrations
        $runner->migrate();
        
        // Verify migration was executed
        $this->assertMigrationExecuted('Version20251103000000');
        
        // Verify table was created (orchestration worked)
        $this->assertTableExists('wp_minisite_config');
    }
    
    public function test_migrate_skips_already_executed_migrations(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // First run
        $runner->migrate();
        $firstRunCount = count($this->getExecutedMigrations());
        
        // Second run - should skip (already executed)
        $runner->migrate();
        $secondRunCount = count($this->getExecutedMigrations());
        
        // Should be same count (no new migrations executed)
        $this->assertEquals($firstRunCount, $secondRunCount);
    }
    
    public function test_migrate_uses_wordpress_table_prefix(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Verify metadata table uses wp_ prefix
        $this->assertTableExists('wp_minisite_migrations');
    }
    
    // ... more tests
}
```

---

## Key Differences from Migration Tests

| Aspect | Migration Tests (`Version20251103000000Test`) | Runner Tests (`DoctrineMigrationRunnerIntegrationTest`) |
|--------|-----------------------------------------------|------------------------------------------------------|
| **Focus** | Individual migration `up()`/`down()` logic | Orchestration: discovery, execution, tracking |
| **What's Tested** | Schema creation, table structure | Migration discovery, state tracking, execution flow |
| **Dependencies** | Direct migration instantiation | Uses `DoctrineMigrationRunner` class |
| **Scope** | One migration file | All migrations in directory |
| **State** | Tests migration behavior | Tests execution tracking |

---

## Summary

**DoctrineMigrationRunner tests should focus on:**
- ✅ **Orchestration**: Can it find, order, and execute migrations?
- ✅ **State Management**: Does it track what's executed?
- ✅ **Error Handling**: Does it handle failures gracefully?
- ✅ **Integration**: Does it work with WordPress prefix and database?

**DoctrineMigrationRunner tests should NOT focus on:**
- ❌ Individual migration logic (separate tests)
- ❌ Schema details (migration tests cover this)
- ❌ Doctrine framework internals (trust the framework)

The runner is a **coordinator**, not an **implementer**. Test its coordination capabilities.

