# Integration Test Refactoring: Migration Tests

## Why Refactor?

The original `DoctrineMigrationRunnerIntegrationTest.php` had all migration tests in one file:
- ❌ Not scalable - each new migration needs tests
- ❌ Duplicate code - same setup/teardown/helpers in every test
- ❌ Hard to maintain - changes to common functionality required updating all tests

## Solution: Base Class + Per-Migration Tests

### Architecture

```
AbstractDoctrineMigrationTest (base class)
├── Common setup/teardown
├── Helper methods (tableExists, getTableColumns, etc.)
└── Abstract method: getMigrationTables()

Version20251103000000Test (extends base)
├── getMigrationTables() → ['wp_minisite_config']
└── Tests specific to minisite_config migration

Version20251104000000Test (future migration)
├── getMigrationTables() → ['wp_some_other_table']
└── Tests specific to that migration
```

## Base Class Features

### `AbstractDoctrineMigrationTest`

**Common Setup:**
- MySQL connection setup
- EntityManager creation
- WordPress `$wpdb` stub setup
- Table cleanup

**Helper Methods:**
- `tableExists($tableName)` - Check if table exists
- `getTableColumns($tableName)` - Get all columns with types
- `assertTableExists($tableName)` - Assert table exists
- `assertTableNotExists($tableName)` - Assert table doesn't exist
- `assertTableHasColumn($tableName, $columnName)` - Assert column exists
- `assertColumnType($tableName, $columnName, $expectedType)` - Assert column type
- `getExecutedMigrations()` - Get executed migrations from tracking table
- `assertMigrationExecuted($version)` - Assert migration was executed

**Abstract Method:**
- `getMigrationTables()` - Child classes must implement to specify which tables to clean up

## Example: Version20251103000000Test

```php
final class Version20251103000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_minisite_config'];
    }
    
    public function test_migrate_creates_minisite_config_table(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        
        // Use base class helpers
        $this->assertTableNotExists('wp_minisite_config');
        $runner->migrate();
        $this->assertTableExists('wp_minisite_config');
    }
    
    public function test_minisite_config_table_has_correct_schema(): void
    {
        $runner = new DoctrineMigrationRunner($this->em);
        $runner->migrate();
        
        // Use base class helpers
        $this->assertTableHasColumn('wp_minisite_config', 'id');
        $this->assertTableHasColumn('wp_minisite_config', 'config_key');
        $this->assertColumnType('wp_minisite_config', 'id', 'bigint');
    }
}
```

## Adding New Migration Tests

When creating a new migration (e.g., `Version20251104000000`):

1. **Create test file:** `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251104000000Test.php`

2. **Extend base class:**
```php
final class Version20251104000000Test extends AbstractDoctrineMigrationTest
{
    protected function getMigrationTables(): array
    {
        return ['wp_new_table']; // List tables this migration creates
    }
    
    // Write tests specific to this migration
}
```

3. **Use base class helpers** - All common functionality is already available!

## Benefits

✅ **Scalable**: One test file per migration
✅ **DRY**: Common functionality in base class
✅ **Maintainable**: Change base class, all tests benefit
✅ **Consistent**: All migration tests follow same pattern
✅ **Readable**: Each test file focuses on one migration

## About Symfony Cache in Tests

**Original Question:** Why `Symfony ArrayAdapter` for caching?

**Answer:** We **removed it**! 

Doctrine ORM in dev mode (`isDevMode: true`) automatically uses an in-memory cache if no cache is explicitly provided. We don't need to manually configure Symfony cache for tests.

**Before:**
```php
// Manual cache setup (unnecessary)
if (class_exists(\Symfony\Component\Cache\Adapter\ArrayAdapter::class)) {
    $cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
    $config->setMetadataCache($cache);
    // ...
}
```

**After:**
```php
// Doctrine handles it automatically in dev mode
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [...],
    isDevMode: true  // ← This is enough!
);
```

**Why it works:**
- In dev mode, Doctrine uses `ArrayCache` (in-memory) automatically
- No external dependencies needed
- Simpler code
- Works perfectly for tests

