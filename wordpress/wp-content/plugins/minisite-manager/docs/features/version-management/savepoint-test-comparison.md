# Savepoint Error - Test Comparison

## Test 1: CLOSES Connection ✅

**File**: `tests/Integration/Features/ConfigurationManagement/Repositories/ConfigRepositoryIntegrationTest.php`

### Key Code Pattern:

```php
protected function setUp(): void
{
    // ... setup EntityManager and connection ...

    // Run migrations
    $migrationRunner = new DoctrineMigrationRunner($this->em);
    $migrationRunner->migrate();

    // Reset connection state after migrations
    try {
        // Rollback any active transactions
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e) {
        // If rollback fails, try to execute a direct ROLLBACK
        try {
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e2) {
            // Ignore - connection might already be clean
        }
    }

    // Clear EntityManager state after migrations
    $this->em->clear();

    // ✅ CLOSE CONNECTION to clear ALL savepoints (connection-scoped)
    // EntityManager will automatically reconnect when needed
    // This is necessary because Doctrine's savepoint state persists even after transactions
    try {
        $connection->close();
    } catch (\Exception $e) {
        // Ignore - connection might already be closed
    }

    // Get repository and continue with test
    $this->repository = new ConfigRepository($this->em, $classMetadata);
}
```

### What This Test Does:
1. Runs migrations
2. Tries to clean up transactions
3. **Closes connection** (resets all state)
4. EntityManager auto-reconnects when needed
5. Test continues with fresh connection

### Why This Works:
- Closing connection resets transaction nesting level to 0
- Clears all savepoint state
- Fresh connection = no corrupted state

---

## Test 2: Does NOT Close Connection ❌

**File**: `tests/Integration/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerIntegrationTest.php`

### Key Code Pattern:

```php
public function test_migrate_discovers_and_executes_pending_migrations(): void
{
    $runner = new DoctrineMigrationRunner($this->em);

    // Verify no migrations executed yet
    $this->assertEmpty($this->getExecutedMigrations());

    // Verify table doesn't exist before migration
    $this->assertTableNotExists('wp_minisite_config');

    // Run migrations - this will execute ALL pending migrations
    $runner->migrate();  // ← Migrations run

    // ❌ NO connection cleanup here!
    // ❌ NO connection->close()!

    // Verify at least one migration was executed
    $executedMigrations = $this->getExecutedMigrations();
    $this->assertGreaterThanOrEqual(1, count($executedMigrations));

    // Verify table was created
    $this->assertTableExists('wp_minisite_config');
}
```

### What This Test Does:
1. Runs migrations
2. **Does NOT close connection**
3. **Does NOT clean up transaction state**
4. Test continues with potentially corrupted connection

### Why This Might Fail:
- If migrations leave transaction nesting level > 0
- Savepoint state might be corrupted
- Next operation (if any) might trigger savepoint error

---

## Key Differences

| Aspect                      | Test 1 (Closes)          | Test 2 (Doesn't Close)      |
| --------------------------- | ------------------------ | --------------------------- |
| **Runs migrations**         | ✅ Yes                    | ✅ Yes                       |
| **Cleans up transactions**  | ✅ Yes (tries to)         | ❌ No                        |
| **Closes connection**       | ✅ Yes                    | ❌ No                        |
| **Resets savepoint state**  | ✅ Yes (via close)        | ❌ No                        |
| **Risk of savepoint error** | ✅ Low (connection reset) | ⚠️ **High** (state persists) |

---

## Why Test 2 Might Not Always Fail

The error is **intermittent** because:

1. **If migrations complete cleanly**:
   - Transaction nesting level = 0
   - No savepoints left
   - Test passes ✅

2. **If migrations leave corrupted state**:
   - Transaction nesting level > 0
   - Savepoints gone but nesting level persists
   - Next operation fails ❌

3. **Test 2 doesn't use flush()**:
   - This test only verifies table existence
   - Doesn't call `flush()` which triggers savepoint creation
   - So it might not trigger the error even with corrupted state

---

## The Real Problem

**Test 2 doesn't fail because it doesn't use `flush()`**, but if another test runs after it and uses the same connection, that test might fail.

The issue is:
- Connection state persists across tests
- If one test leaves corrupted state, next test might fail
- Closing connection ensures clean state for each test

---

## Recommendation

**Option 1: Close connection in ALL tests that call `migrate()`**
- Consistent approach
- Prevents intermittent failures
- But: not ideal practice

**Option 2: Fix at source - ensure migrations reset state properly**
- Better long-term solution
- But: requires understanding Doctrine Migrations internals

**Option 3: Close connection only in tests that use `flush()`**
- More targeted
- But: might miss edge cases


