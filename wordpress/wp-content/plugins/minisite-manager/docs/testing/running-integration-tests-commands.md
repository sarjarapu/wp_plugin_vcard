# Running Integration Tests - Commands Reference

## Quick Commands

### Run All Integration Tests
```bash
vendor/bin/phpunit --testsuite=Integration
```

### Run Only Migration Integration Tests
```bash
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/
```

### Run Specific Migration Test
```bash
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/Version20251103000000Test.php
```

### Run All Tests (Unit + Integration)
```bash
vendor/bin/phpunit
```

## Filter Options

### Run Tests by Pattern
```bash
# Run tests matching a pattern
vendor/bin/phpunit --filter Version20251103000000

# Run tests in specific directory
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter test_migrate_creates_minisite_config_table
```

## Prerequisites

### 1. Start MySQL Test Container
```bash
docker-compose up -d mysql_integration
```

### 2. Verify MySQL is Running
```bash
docker-compose ps mysql_integration
```

### 3. Check Connection
```bash
mysql -h 127.0.0.1 -P 3307 -u minisite -pminisite minisite_test -e "SELECT 1"
```

## Environment Variables

Integration tests use these environment variables (defined in `phpunit.xml.dist`):

- `MYSQL_HOST` (default: `127.0.0.1`)
- `MYSQL_PORT` (default: `3307`)
- `MYSQL_DATABASE` (default: `minisite_test`)
- `MYSQL_USER` (default: `minisite`)
- `MYSQL_PASSWORD` (default: `minisite`)

To override, set them before running:
```bash
MYSQL_HOST=localhost MYSQL_PORT=3306 vendor/bin/phpunit --testsuite=Integration
```

## Troubleshooting

### Fatal Error: Class Not Found
If you see fatal errors about missing classes, it's likely related to:
1. **Old tests** that need updating (not related to new migration tests)
2. **Missing dependencies** - run `composer install`
3. **Autoload issues** - run `composer dump-autoload`

### Database Connection Errors
- Ensure MySQL container is running: `docker-compose ps mysql_integration`
- Check port is correct: `3307` (not `3306`)
- Verify credentials match `phpunit.xml.dist`

### Test Isolation
Each test cleans up its own tables. If tests fail unexpectedly:
- Check for leftover tables: `mysql -h 127.0.0.1 -P 3307 -u minisite -pminisite minisite_test -e "SHOW TABLES"`
- Manually clean if needed: `docker-compose exec mysql_integration mysql -u minisite -pminisite minisite_test -e "DROP TABLE IF EXISTS wp_minisite_config, wp_doctrine_migration_versions"`

