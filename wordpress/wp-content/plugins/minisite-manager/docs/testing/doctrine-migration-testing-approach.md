# Doctrine Migration Testing: Proper Approach

## Problem with Skipping Tests

Tests that just skip everything are **useless** - they provide no value and waste time. They give false confidence without actually verifying anything works.

## Why Integration Tests Make Sense

For `DoctrineMigrationRunner`, **integration tests are the right approach** because:

1. **Migrations are inherently database operations** - they create/modify tables
2. **Unit tests would require extensive mocking** - mocking EntityManager, Connection, DependencyFactory, Migrator, etc.
3. **Integration tests verify actual behavior** - we can see if migrations actually create the correct tables
4. **SQLite in-memory is fast** - we get real database behavior without the overhead of MySQL

## Testing Strategy

### ✅ Integration Tests (Recommended)

Test actual migration execution against in-memory SQLite:

```php
class DoctrineMigrationRunnerIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Create SQLite in-memory connection
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        
        // Create EntityManager
        $em = new EntityManager($connection, $config);
        
        // Set up WordPress globals
        global $wpdb;
        $wpdb = new FakeWpdb($pdo);
        $wpdb->prefix = 'wp_';
    }
    
    public function test_migrate_creates_minisite_config_table(): void
    {
        $runner = new DoctrineMigrationRunner();
        $runner->migrate();
        
        // Verify table exists
        $tables = $connection->fetchFirstColumn(
            "SELECT name FROM sqlite_master WHERE type='table'"
        );
        $this->assertContains('wp_minisite_config', $tables);
    }
}
```

**Benefits:**
- ✅ Tests actual migration execution
- ✅ Verifies tables are created correctly
- ✅ Verifies schema matches expectations
- ✅ Fast (in-memory SQLite)
- ✅ Isolated (each test gets fresh DB)

### ❌ Unit Tests with Mocks (Not Recommended)

While possible, unit tests would require mocking:
- `DoctrineFactory::createEntityManager()` (static method)
- `EntityManager` and its methods
- `Connection`
- `DependencyFactory`
- `Migrator`
- `StatusCalculator`

This would be **more complex** than the actual implementation and prone to errors.

## Current Challenge: DoctrineFactory

`DoctrineFactory::createEntityManager()` uses WordPress constants (`DB_HOST`, `DB_USER`, etc.) to connect to MySQL. For tests, we need SQLite.

### Option 1: Refactor DoctrineFactory (Better Testability)

Make `DoctrineFactory` accept an optional connection:

```php
class DoctrineFactory
{
    public static function createEntityManager(
        ?\wpdb $wpdb = null,
        ?\Doctrine\DBAL\Connection $connection = null  // For testing
    ): EntityManager {
        if ($connection !== null) {
            // Use provided connection (for testing)
            $em = new EntityManager($connection, $config);
            // ... setup prefix listener
            return $em;
        }
        
        // Normal path: create from WordPress constants
        // ...
    }
}
```

Then `DoctrineMigrationRunner` can optionally accept an EntityManager:

```php
class DoctrineMigrationRunner
{
    private ?EntityManager $em = null;
    
    public function __construct(?EntityManager $em = null)
    {
        $this->em = $em;
    }
    
    public function migrate(): void
    {
        if ($this->em === null) {
            $this->em = DoctrineFactory::createEntityManager();
        }
        // ... rest of migration logic
    }
}
```

### Option 2: Keep Current Approach (Simpler, Less Testable)

Keep `DoctrineFactory` as-is, but create EntityManager directly in tests:

```php
// In test
$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$em = new EntityManager($connection, $config);
// ... but DoctrineMigrationRunner still calls DoctrineFactory...
```

**Problem:** We'd need to mock/stub `DoctrineFactory` or refactor `DoctrineMigrationRunner`.

## Recommendation

**Use Integration Tests** - they provide real value by testing actual migration behavior. The current implementation in `DoctrineMigrationRunnerIntegrationTest.php` is the right approach.

**Future Enhancement:** If we want better testability, refactor `DoctrineFactory` and `DoctrineMigrationRunner` to accept optional dependencies (dependency injection pattern).

## Test Coverage

Integration tests should verify:

1. ✅ **Table Creation**: Migration creates `wp_minisite_config` table
2. ✅ **Tracking Table**: Migration creates `wp_doctrine_migration_versions` table
3. ✅ **Migration Recording**: Executed migration is recorded in tracking table
4. ✅ **Idempotency**: Running migration twice doesn't cause errors
5. ✅ **Schema Correctness**: Table has all expected columns with correct types
6. ✅ **Prefix Usage**: Tables use WordPress prefix (`wp_`)

These are **real, valuable tests** that verify the migration system works correctly.

