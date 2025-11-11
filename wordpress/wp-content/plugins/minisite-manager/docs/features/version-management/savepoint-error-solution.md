# Savepoint Error: Issue, Root Cause, and Solution

## The Error

```
SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT DOCTRINE_X does not exist
```

This error occurred intermittently in integration tests after running Doctrine Migrations, typically when calling `flush()` on the EntityManager.

## Why It Happens

### The Root Cause: MySQL DDL Implicit Commits

This is **NOT a Doctrine bug** - it's a **known limitation** of MySQL with Doctrine Migrations.

According to [Doctrine Migrations documentation](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html):

> "Some platforms like MySQL or Oracle do not support DDL statements (`CREATE TABLE`, `ALTER TABLE`, etc.) in transactions. The issue existed before PHP 8 but is now made visible by e.g. PDO, which now produces the above error message when this library attempts to commit a transaction that has already been committed before."

### The Problem Sequence

When a migration has `isTransactional() => true` and executes DDL statements (like `CREATE TABLE`):

1. **Doctrine Migrations starts a transaction** (nesting level = 1)
2. **Doctrine creates a savepoint** for the migration (nesting level = 2)
3. **Migration executes `CREATE TABLE`** (DDL statement)
4. **MySQL automatically commits** the transaction (MySQL limitation - DDL statements cause implicit commits)
5. **Doctrine tries to release the savepoint** (but it doesn't exist anymore - transaction was already committed)
6. **Doctrine's internal transaction nesting level counter doesn't reset** (still thinks it's at level 2)
7. **Connection state is corrupted** - Doctrine expects savepoints that don't exist
8. **Next operation (e.g., `flush()`) tries to create a savepoint** → **ERROR: SAVEPOINT DOCTRINE_X does not exist**

### Visual Breakdown

```
Step 1: Doctrine starts transaction
  MySQL: Transaction started
  Doctrine: Nesting level = 1

Step 2: Migration isTransactional() => true, Doctrine creates savepoint
  MySQL: SAVEPOINT DOCTRINE_1 created
  Doctrine: Nesting level = 2

Step 3: Migration executes CREATE TABLE
  MySQL: ⚠️ IMPLICIT COMMIT (DDL auto-commits in MySQL)
  MySQL: Transaction committed, savepoint gone
  Doctrine: Still thinks nesting level = 2 ❌

Step 4: Doctrine tries to release savepoint
  MySQL: SAVEPOINT DOCTRINE_1 doesn't exist → ERROR

Step 5: Next test calls flush()
  Doctrine: Tries to create SAVEPOINT DOCTRINE_2
  MySQL: No active transaction → ERROR: SAVEPOINT DOCTRINE_2 does not exist
```

### Why It's Intermittent

The error was intermittent because:
- Sometimes migrations completed cleanly and the nesting level reset properly
- Sometimes MySQL's implicit commit left Doctrine's internal state out of sync
- Connection reuse across tests meant corrupted state could persist

## The Solution

### Fix: Set `isTransactional() => false` for DDL Migrations

For migrations that use DDL statements (`CREATE TABLE`, `ALTER TABLE`, etc.), set `isTransactional() => false`:

```php
/**
 * Indicate if this migration is transactional
 *
 * MySQL doesn't support transactional DDL (CREATE TABLE causes implicit commit).
 * Setting this to false prevents Doctrine from wrapping the migration in a transaction,
 * which avoids savepoint errors when MySQL auto-commits the DDL statement.
 *
 * @see https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html
 */
public function isTransactional(): bool
{
    return false; // MySQL doesn't support transactional DDL
}
```

### Why This Works

By setting `isTransactional() => false`:
- Doctrine Migrations **doesn't wrap the migration in a transaction**
- No savepoints are created
- MySQL's implicit commit doesn't conflict with Doctrine's transaction tracking
- Connection state remains clean after migrations

### Implementation

All three migration files were updated:

1. **`Version20251103000000.php`** - Creates `wp_minisite_config` table
2. **`Version20251104000000.php`** - Creates `wp_minisite_reviews` table
3. **`Version20251105000000.php`** - Creates `wp_minisite_versions` table

All now have `isTransactional() => false`.

## What Was Removed

After implementing the fix, all workarounds were removed:

- ❌ **Removed**: `connection->close()` calls after migrations
- ❌ **Removed**: `cleanupConnectionState()` methods
- ❌ **Removed**: Transaction rollback loops
- ❌ **Removed**: Savepoint-related comments and documentation

The fix addresses the root cause, so workarounds are no longer needed.

## Test Results

**Before Fix:**
- Intermittent savepoint errors in integration tests
- Required connection.close() workarounds
- 24+ errors across multiple test files

**After Fix:**
- ✅ All tests pass consistently
- ✅ No connection.close() workarounds needed
- ✅ Clean, maintainable code
- ✅ Unit tests: 1070 tests, 3683 assertions
- ✅ Integration tests: 111 tests, 488 assertions

## Key Takeaways

1. **MySQL doesn't support transactional DDL** - This is a MySQL limitation, not a Doctrine bug
2. **Doctrine Migrations assumes transactions work** - It doesn't know MySQL auto-commits DDL
3. **The fix is simple**: Set `isTransactional() => false` for migrations that use DDL
4. **No workarounds needed** - The fix addresses the root cause

## References

- [Doctrine Migrations: Implicit Commits](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html)
- [Doctrine Migrations Configuration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/configuration.html)

