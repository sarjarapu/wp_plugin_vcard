# Integration Test Database Setup

## Overview

Integration tests use a **real MySQL database** in a separate Docker container, not in-memory SQLite or fake objects. This ensures tests run against the same database system as production.

## Docker Setup

### Option 1: Separate Test Database Container

Create a separate MySQL container for testing that runs on a different port:

```yaml
# docker-compose.test.yml
version: '3.8'
services:
  mysql_test:
    image: mysql:8.0
    container_name: minisite_mysql_test
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: minisite_test
      MYSQL_USER: minisite
      MYSQL_PASSWORD: minisite
    ports:
      - "3307:3306"  # Different port than dev (3306)
    volumes:
      - mysql_test_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password

volumes:
  mysql_test_data:
```

Start the test database:
```bash
docker-compose -f docker-compose.test.yml up -d
```

### Option 2: Use Existing Dev Container with Different Database

If you already have a MySQL container for development, connect to it but use a different database:

```bash
# Connect to dev MySQL container
docker exec -it <mysql_container_name> mysql -uroot -p

# Create test database
CREATE DATABASE minisite_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'minisite'@'%' IDENTIFIED BY 'minisite';
GRANT ALL PRIVILEGES ON minisite_test.* TO 'minisite'@'%';
FLUSH PRIVILEGES;
```

## Environment Configuration

Tests read database configuration from environment variables (set in `phpunit.xml.dist`):

```xml
<php>
  <env name="MYSQL_HOST" value="127.0.0.1"/>
  <env name="MYSQL_PORT" value="3307"/>
  <env name="MYSQL_DATABASE" value="minisite_test"/>
  <env name="MYSQL_USER" value="minisite"/>
  <env name="MYSQL_PASSWORD" value="minisite"/>
</php>
```

## What WordPress Tables Are Needed?

**Short answer: None!**

For Doctrine migrations, we only need:
1. The table prefix (`wp_`) - provided via `$wpdb->prefix`
2. A real MySQL database to run migrations against

We don't need WordPress core tables (`wp_posts`, `wp_users`, etc.) because:
- Migrations only create our plugin tables (`wp_minisite_config`, etc.)
- Migrations use `$wpdb->prefix` to get the prefix, not WordPress functions

## Test Lifecycle

1. **setUp()**: 
   - Connect to MySQL test database
   - Create EntityManager with MySQL connection
   - Set up real `$wpdb` object (just for the prefix property)
   - Clean up existing test tables

2. **Test Execution**:
   - Run migration via `DoctrineMigrationRunner`
   - Verify tables are created correctly
   - Verify schema matches expectations

3. **tearDown()**:
   - Drop test tables
   - Close connections

## Database Cleanup

Tests automatically clean up tables before and after each test:

```php
private function cleanupTestTables(): void
{
    $tables = [
        'wp_minisite_config',
        'wp_doctrine_migration_versions',
    ];
    
    foreach ($tables as $table) {
        $this->connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
    }
}
```

This ensures:
- ✅ Each test starts with a clean slate
- ✅ No leftover data affects test results
- ✅ Tests can run in any order

## Running Tests

```bash
# Run all integration tests
composer test:integration

# Run specific test class
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerIntegrationTest.php
```

## Troubleshooting

### Database Connection Failed

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Solution:**
- Verify MySQL container is running: `docker ps`
- Check port mapping: `docker port <container_name>`
- Verify environment variables in `phpunit.xml.dist`

### Permission Denied

**Error:** `Access denied for user 'minisite'@'localhost'`

**Solution:**
- Verify user exists in MySQL
- Check user has privileges: `SHOW GRANTS FOR 'minisite'@'%';`
- Grant privileges if needed: `GRANT ALL ON minisite_test.* TO 'minisite'@'%';`

### Table Already Exists

**Error:** `Table 'wp_minisite_config' already exists`

**Solution:**
- Cleanup should handle this, but if it persists:
  - Manually drop tables: `DROP TABLE IF EXISTS wp_minisite_config, wp_doctrine_migration_versions;`
  - Or drop and recreate database: `DROP DATABASE minisite_test; CREATE DATABASE minisite_test;`

## Benefits of Real MySQL Tests

✅ **Production Parity**: Tests run against the same database system as production  
✅ **Real Behavior**: Tests verify actual SQL execution, not mocked behavior  
✅ **Schema Validation**: Tests verify MySQL-specific features (data types, indexes, etc.)  
✅ **Migration Confidence**: If migrations pass here, they'll work in production  

## No Fake Objects Needed

We **don't** use:
- ❌ `FakeWpdb` - Use real `$wpdb` object (just for prefix)
- ❌ SQLite - Use real MySQL
- ❌ Mocks - Use real database connections

This is **real integration testing** - we test against actual infrastructure.

