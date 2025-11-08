# Savepoint Analysis - Reality Check

## What I Documented

1. **Root Cause**: Doctrine Migrations leaves connection in corrupted state (transaction nesting level > 0)
2. **Solution**: Close connection after migrations to reset state
3. **Impact**: All tests that call `migrate()` need connection close

## What Actually Happened

### ✅ The Error IS Real and Reproducible

The reproduction test (`reproduce-savepoint-error-test.php`) **still reproduces the error**:
```
SAVEPOINT DOCTRINE_5 does not exist
```

### ✅ The Fix Works

Tests that close connections after migrations are **passing**.

### ⚠️ But Not All Tests Need It

**Finding**: 53 tests call `migrate()`, but only 10 close connections.

**Tests that DON'T close but still pass**:
- `DoctrineMigrationRunnerIntegrationTest.php` - Only tests table existence
- `Version20251103000000Test.php` - Only tests migration execution
- `Version20251104000000Test.php` - Only tests migration execution

**Tests that DO close connections**:
- Tests that use `flush()` after migrations
- Tests that use repositories/services after migrations
- Tests that perform actual database operations

## Revised Understanding

### When the Error Occurs

The savepoint error occurs when:
1. Migrations run (create savepoints internally)
2. Connection is NOT closed
3. **AND** the test calls `flush()` or performs operations that trigger savepoint creation

### When It Doesn't Occur

The error does NOT occur when:
1. Migrations run
2. Connection is NOT closed
3. **BUT** the test only checks table existence or migration tracking (no `flush()`)

## Conclusion

### My Analysis Was:
- ✅ **Correct** about the root cause (Doctrine Migrations leaves corrupted state)
- ✅ **Correct** about the fix (closing connection works)
- ⚠️ **Overgeneralized** about the scope (not ALL tests need it)

### The Real Pattern

**Close connection when**:
- Test uses `flush()` after migrations
- Test uses repositories/services that might call `flush()`
- Test performs database operations after migrations

**Don't need to close when**:
- Test only verifies migration execution
- Test only checks table existence
- Test doesn't use Doctrine ORM operations after migrations

## Why Integration Tests Are Passing

1. **Tests that need it**: Close connections → ✅ Pass
2. **Tests that don't need it**: Don't close → ✅ Pass (because they don't trigger the error)

The error is **conditional** - it only manifests when specific operations (`flush()`) are performed after migrations without closing the connection.

## Recommendation

**Keep the connection close pattern** for:
- Tests that use repositories/services
- Tests that call `flush()`
- Tests that perform database operations after migrations

**Don't require it for**:
- Pure migration execution tests
- Table existence verification tests

The fix is **defensive** - it ensures clean state even if the test pattern changes later.

