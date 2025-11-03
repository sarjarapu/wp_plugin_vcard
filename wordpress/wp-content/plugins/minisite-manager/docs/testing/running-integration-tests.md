# Running Integration Tests

## Overview

Integration tests connect to a **real MySQL database** running in Docker. The tests run on your **local machine** (not inside Docker), but connect to MySQL in Docker via the exposed port.

## Setup

### 1. Start MySQL Test Container

The `docker-compose.yml` already defines a test MySQL container:

```bash
# Start the test database container
docker-compose up -d mysql_integration

# Verify it's running
docker ps | grep mysql_integration
```

The container exposes MySQL on **port 3307** (different from dev database).

### 2. Verify Database Connection

```bash
# Test connection from your local machine
mysql -h 127.0.0.1 -P 3307 -u minisite -pminisite minisite_test -e "SELECT 1"
```

If this works, your tests can connect!

## Running Tests

### From Local Machine (Recommended)

Integration tests run on your **local machine**, not inside Docker:

```bash
# Run all integration tests
vendor/bin/phpunit --testsuite=Integration

# Run specific test file
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerIntegrationTest.php

# Run with verbose output
vendor/bin/phpunit --testsuite=Integration --verbose
```

### Environment Variables

Tests read database config from `phpunit.xml.dist`:

```xml
<php>
  <env name="MYSQL_HOST" value="127.0.0.1"/>
  <env name="MYSQL_PORT" value="3307"/>
  <env name="MYSQL_DATABASE" value="minisite_test"/>
  <env name="MYSQL_USER" value="minisite"/>
  <env name="MYSQL_PASSWORD" value="minisite"/>
</php>
```

You can override these via command line:

```bash
MYSQL_HOST=127.0.0.1 MYSQL_PORT=3307 vendor/bin/phpunit --testsuite=Integration
```

## How It Works

### Test Architecture

```
┌─────────────────────────────────────────┐
│ Your Local Machine                      │
│ ─────────────────────────────────────── │
│                                          │
│  PHPUnit Tests                          │
│  ├─ DoctrineMigrationRunnerTest         │
│  ├─ Connects via Doctrine → MySQL       │
│  └─ Uses $wpdb stub (just for prefix)  │
│                                          │
└──────────────┬──────────────────────────┘
               │
               │ TCP Connection
               │ (127.0.0.1:3307)
               ▼
┌─────────────────────────────────────────┐
│ Docker Container                        │
│ ─────────────────────────────────────── │
│                                          │
│  MySQL 8.0                              │
│  ├─ Port: 3307 (mapped from 3306)       │
│  ├─ Database: minisite_test            │
│  └─ User: minisite/minisite            │
│                                          │
└─────────────────────────────────────────┘
```

### Key Points

1. **Tests run locally** - PHPUnit executes on your machine
2. **Database in Docker** - MySQL runs in container, port exposed to host
3. **No WordPress needed** - Tests use `$wpdb` stub from `bootstrap.php`
4. **Doctrine connects directly** - Uses MySQL connection, not WordPress

### Why No Full WordPress Bootstrap?

Integration tests **don't** load full WordPress because:

- ✅ Migrations only need `$wpdb->prefix` - provided by stub
- ✅ Doctrine handles all database operations
- ✅ No WordPress functions needed for migrations
- ✅ Faster test execution (no WordPress core loading)

The `wpdb` stub in `tests/bootstrap.php` provides:
- `$wpdb->prefix = 'wp_'` - This is all migrations need!

## Troubleshooting

### Connection Refused

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Check:**
```bash
# Is container running?
docker ps | grep mysql_integration

# Is port exposed?
docker port mysql_integration

# Can you connect manually?
mysql -h 127.0.0.1 -P 3307 -u minisite -pminisite -e "SELECT 1"
```

**Fix:**
```bash
# Start container
docker-compose up -d mysql_integration

# Wait for healthcheck
docker-compose ps
```

### Database Doesn't Exist

**Error:** `Unknown database 'minisite_test'`

**Fix:**
```bash
# Connect to MySQL container
docker exec -it mysql_integration mysql -uroot -proot

# Create database
CREATE DATABASE minisite_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON minisite_test.* TO 'minisite'@'%';
FLUSH PRIVILEGES;
```

### Access Denied

**Error:** `Access denied for user 'minisite'@'%'`

**Fix:**
```bash
# Check user exists
docker exec -it mysql_integration mysql -uroot -proot -e "SELECT User, Host FROM mysql.user WHERE User='minisite';"

# Create user if missing
docker exec -it mysql_integration mysql -uroot -proot -e "CREATE USER IF NOT EXISTS 'minisite'@'%' IDENTIFIED BY 'minisite'; GRANT ALL ON minisite_test.* TO 'minisite'@'%'; FLUSH PRIVILEGES;"
```

### Port Already in Use

**Error:** `Port 3307 is already allocated`

**Fix:**
```bash
# Check what's using the port
lsof -i :3307

# Use a different port - update docker-compose.yml and phpunit.xml.dist
# Or stop the conflicting service
```

## Running in CI/CD

For CI environments, you might want to:

1. **Start MySQL container** before tests
2. **Wait for healthcheck** before running tests
3. **Clean up** after tests complete

Example GitHub Actions:

```yaml
- name: Start MySQL
  run: docker-compose up -d mysql_integration

- name: Wait for MySQL
  run: |
    until docker exec mysql_integration mysqladmin ping -h127.0.0.1 --silent; do
      sleep 1
    done

- name: Run Integration Tests
  run: vendor/bin/phpunit --testsuite=Integration
```

## Summary

- ✅ Tests run **locally** (your machine)
- ✅ MySQL runs **in Docker** (port 3307)
- ✅ No WordPress bootstrap needed
- ✅ `$wpdb` stub provides prefix only
- ✅ Doctrine handles all database operations

This gives you **real integration testing** without needing full WordPress installation!

