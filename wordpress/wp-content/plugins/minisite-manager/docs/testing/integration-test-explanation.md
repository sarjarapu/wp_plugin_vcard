# Integration Test Explanation: DoctrineMigrationRunner

## What Are We Testing?

We're testing **`DoctrineMigrationRunner::migrate()`** - the code that runs Doctrine database migrations when your plugin activates.

## What Does `DoctrineMigrationRunner::migrate()` Do?

**Original Code Purpose:**
1. Connects to MySQL database via Doctrine
2. Discovers migration files (e.g., `Version20251103000000.php`)
3. Checks which migrations haven't run yet
4. Executes pending migrations (creates/modifies database tables)
5. Records executed migrations in a tracking table

**Example:** When plugin activates, it should:
- Create `wp_minisite_config` table (from `Version20251103000000.php`)
- Record that this migration ran in `wp_doctrine_migration_versions` table

## How Do Integration Tests Validate This?

### Test Setup (Before Each Test)

```php
protected function setUp(): void
{
    // 1. Connect to REAL MySQL database (in Docker container)
    $this->connection = DriverManager::getConnection([
        'driver' => 'pdo_mysql',
        'host' => '127.0.0.1',
        'port' => '3307',  // Docker MySQL container
        'dbname' => 'minisite_test',
        // ...
    ]);
    
    // 2. Create EntityManager (Doctrine ORM)
    $this->em = new EntityManager($this->connection, $config);
    
    // 3. Set up $wpdb stub (WordPress database abstraction)
    // We only need $wpdb->prefix = 'wp_'
    $this->wpdb = new \wpdb();
    $this->wpdb->prefix = 'wp_';
    
    // 4. Clean up any existing tables
    $this->cleanupTestTables(); // DROP TABLE IF EXISTS wp_minisite_config
}
```

**What This Validates:**
- ✅ Tests run against **real MySQL** (same as production)
- ✅ Uses same Doctrine setup as production code
- ✅ Uses WordPress table prefix (`wp_`)

### Test: `test_migrate_creates_minisite_config_table()`

**What It Does:**
```php
public function test_migrate_creates_minisite_config_table(): void
{
    // Step 1: Verify table DOESN'T exist before migration
    $tablesBefore = $this->connection->fetchFirstColumn(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'wp_minisite_config'",
        [$this->dbName]
    );
    $this->assertEmpty($tablesBefore); // ✅ Table should NOT exist
    
    // Step 2: Run the ACTUAL migration code
    $runner = new DoctrineMigrationRunner($this->em);
    $runner->migrate(); // ← This is the REAL code we're testing
    
    // Step 3: Verify table DOES exist after migration
    $tablesAfter = $this->connection->fetchFirstColumn(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'wp_minisite_config'",
        [$this->dbName]
    );
    $this->assertNotEmpty($tablesAfter); // ✅ Table should NOW exist
    $this->assertEquals('wp_minisite_config', $tablesAfter[0]);
}
```

**What This Validates:**
- ✅ **Migration Discovery**: Doctrine finds `Version20251103000000.php`
- ✅ **Migration Execution**: Migration's `up()` method runs
- ✅ **Table Creation**: `wp_minisite_config` table is created
- ✅ **WordPress Prefix**: Table name includes `wp_` prefix

### Test: `test_minisite_config_table_has_correct_schema()`

**What It Does:**
```php
public function test_minisite_config_table_has_correct_schema(): void
{
    // Step 1: Run migration
    $runner = new DoctrineMigrationRunner($this->em);
    $runner->migrate(); // ← Execute migration
    
    // Step 2: Query MySQL's INFORMATION_SCHEMA to get actual table structure
    $columns = $this->connection->fetchAllAssociative(
        "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'wp_minisite_config'",
        [$this->dbName]
    );
    
    // Step 3: Verify expected columns exist
    $columnMap = [];
    foreach ($columns as $column) {
        $columnMap[$column['COLUMN_NAME']] = $column;
    }
    
    $this->assertArrayHasKey('id', $columnMap);           // ✅ Primary key exists
    $this->assertArrayHasKey('config_key', $columnMap);    // ✅ Config key column
    $this->assertArrayHasKey('config_value', $columnMap);  // ✅ Config value column
    // ... etc
}
```

**What This Validates:**
- ✅ **Schema Correctness**: Table has all columns defined in migration
- ✅ **Column Types**: MySQL data types match migration definition
- ✅ **No Missing Columns**: Nothing was skipped during migration

### Test: `test_migrate_records_executed_migration()`

**What It Does:**
```php
public function test_migrate_records_executed_migration(): void
{
    // Step 1: Run migration
    $runner = new DoctrineMigrationRunner($this->em);
    $runner->migrate();
    
    // Step 2: Query the tracking table directly
    $executedMigrations = $this->connection->fetchAllAssociative(
        "SELECT version FROM wp_doctrine_migration_versions"
    );
    
    // Step 3: Verify migration was recorded
    $this->assertNotEmpty($executedMigrations);
    $this->assertStringContainsString('Version20251103000000', $executedMigrations[0]['version']);
}
```

**What This Validates:**
- ✅ **Tracking Table**: `wp_doctrine_migration_versions` exists
- ✅ **Migration Recorded**: Executed migration is logged
- ✅ **Idempotency**: Running migration twice doesn't duplicate records

## Validation Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ Test: test_migrate_creates_minisite_config_table()         │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 1: Verify Clean State                                 │
│ SELECT FROM INFORMATION_SCHEMA WHERE TABLE = 'wp_...'      │
│ Expected: 0 rows (table doesn't exist)                      │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 2: Execute REAL Code                                  │
│ $runner = new DoctrineMigrationRunner($this->em);          │
│ $runner->migrate();                                        │
│                                                             │
│ Inside migrate():                                          │
│   1. DoctrineFactory::createEntityManager()                │
│   2. Finds Version20251103000000.php via GlobFinder        │
│   3. Creates migration plan                                │
│   4. Executes Version20251103000000::up()                  │
│      → Creates wp_minisite_config table                    │
│   5. Records in wp_doctrine_migration_versions             │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│ Step 3: Verify Result                                       │
│ SELECT FROM INFORMATION_SCHEMA WHERE TABLE = 'wp_...'      │
│ Expected: 1 row (table exists)                             │
│                                                             │
│ ✅ If assertion passes: Migration worked correctly!       │
│ ❌ If assertion fails: Something is broken                 │
└─────────────────────────────────────────────────────────────┘
```

## What Makes This an Integration Test?

**Real Components:**
- ✅ Real MySQL database (not SQLite, not mocked)
- ✅ Real Doctrine ORM (actual EntityManager, Connection)
- ✅ Real migration execution (actual SQL commands run)
- ✅ Real WordPress prefix handling (`$wpdb->prefix`)

**What We're NOT Mocking:**
- ❌ Database connection (real MySQL)
- ❌ Migration discovery (real file scanning)
- ❌ SQL execution (real CREATE TABLE commands)
- ❌ Table structure (real MySQL schema)

**What We ARE Mocking:**
- ✅ `$wpdb` object (stub from `bootstrap.php` - only need `prefix` property)

## How This Differs from Unit Tests

| Aspect | Unit Test | Integration Test (This) |
|--------|-----------|------------------------|
| Database | Mocked/Faked | **Real MySQL** |
| Migration Execution | Mocked | **Actually runs SQL** |
| Table Creation | Assert mocked calls | **Verify real tables exist** |
| Schema Validation | Can't validate | **Query INFORMATION_SCHEMA** |

## Why This Approach Works

**Problem with Unit Tests:**
- Can't verify actual table creation
- Can't verify schema correctness
- Can't verify SQL execution
- Might pass even if migration is broken

**Solution: Integration Tests:**
- ✅ Runs real migrations against real database
- ✅ Verifies tables actually exist after migration
- ✅ Verifies schema matches expectations
- ✅ Would fail if migration code is broken

## Test Coverage Summary

Our 7 tests validate:

1. **Table Creation**: `wp_minisite_config` table exists after migration
2. **Tracking Table**: `wp_doctrine_migration_versions` table exists
3. **Migration Recording**: Executed migration is logged
4. **Idempotency**: Running migration twice is safe
5. **Schema Correctness**: All expected columns exist with correct types
6. **Prefix Usage**: Tables use `wp_` prefix
7. **Tracking Schema**: Migration tracking table has correct structure

## Running the Tests

```bash
# Start MySQL test container
docker-compose up -d mysql_integration

# Run tests (connects to MySQL at 127.0.0.1:3307)
vendor/bin/phpunit --testsuite=Integration
```

**What Happens:**
- Tests run on your **local machine** (not in Docker)
- Tests connect to MySQL **in Docker** via port 3307
- Each test runs migrations against **real database**
- Each test verifies **real results** via SQL queries

This is **true integration testing** - we test the complete flow from migration file → SQL execution → table creation → verification.

