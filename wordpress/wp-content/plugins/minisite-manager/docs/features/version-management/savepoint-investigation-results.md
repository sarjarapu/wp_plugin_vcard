# Savepoint Investigation Results

## Test File
`tests/Integration/Features/VersionManagement/savepoint-investigation-test.php`

## Key Findings

### 1. Migrations Do NOT Leave Transactions Active
- **Observation**: After migrations complete, `isTransactionActive()` returns `false`
- **Transaction nesting level**: 0 (clean state)
- **Conclusion**: Migrations properly commit/rollback their transactions

### 2. Doctrine Creates Savepoints Automatically
- **When**: `flush()` is called within an active transaction
- **Observation**: Multiple `flush()` calls within the same transaction work fine
- **Rollback**: Works correctly even with multiple savepoints

### 3. The Problem Scenario
The savepoint error (`SAVEPOINT DOCTRINE_X does not exist`) occurs when:
- Migrations run and create savepoints internally
- Migrations complete, but savepoint state persists on the connection
- Next test starts a transaction and calls `flush()`
- Doctrine tries to create a new savepoint, but the connection's savepoint state is corrupted

### 4. Why Closing Connection Works
- **Savepoints are connection-scoped**: They exist only on the specific database connection
- **Closing connection**: Clears ALL savepoint state
- **EntityManager auto-reconnect**: When `flush()` is called, EntityManager automatically reconnects
- **Result**: Fresh connection = clean savepoint state

## Test Results

### Without Connection Close
- ✅ Multiple `flush()` calls work fine
- ✅ Rollback works correctly
- ⚠️ **BUT**: In real integration tests, savepoint errors occur

### With Connection Close
- ✅ Connection closes successfully
- ✅ EntityManager auto-reconnects when needed
- ✅ `flush()` works after reconnect
- ✅ No savepoint errors

## Hypothesis

The savepoint error likely occurs due to:
1. **Connection reuse**: The same connection is reused across multiple tests
2. **Savepoint state persistence**: Even after transactions commit, savepoint metadata might persist
3. **Doctrine's internal savepoint tracking**: Doctrine tracks savepoint names (DOCTRINE_1, DOCTRINE_2, etc.) and if the connection state is corrupted, it might try to reference a non-existent savepoint

## Proposed Fix

### Option 1: Close Connection (Current Approach)
```php
// After migrations
$connection->close();
// EntityManager will auto-reconnect when needed
```

**Pros**:
- ✅ Guaranteed clean state
- ✅ Clears ALL savepoint state
- ✅ Works reliably

**Cons**:
- ⚠️ Connection overhead (but minimal - EntityManager reconnects automatically)
- ⚠️ Not ideal practice (but necessary for this specific issue)

### Option 2: Explicit Savepoint Cleanup (To Test)
```php
// After migrations
// Try to release all savepoints explicitly
// (MySQL doesn't have a direct way to list/release all savepoints)
```

**Pros**:
- ✅ Keeps connection open

**Cons**:
- ❌ MySQL doesn't expose savepoint list
- ❌ Not reliable

### Option 3: Ensure Clean Transaction State (Current Partial Fix)
```php
// After migrations
while ($connection->isTransactionActive()) {
    $connection->rollBack();
}
$connection->setAutoCommit(true);
```

**Pros**:
- ✅ Keeps connection open
- ✅ Ensures clean transaction state

**Cons**:
- ❌ Doesn't clear savepoint state (savepoints persist even after transaction commit)
- ❌ Test showed this doesn't work (savepoint errors still occur)

## Recommendation

**Use Option 1 (Close Connection)** because:
1. It's the ONLY reliable way to clear savepoint state
2. EntityManager auto-reconnects, so overhead is minimal
3. It's a known workaround for Doctrine savepoint issues in test scenarios
4. The alternative (Option 3) doesn't work - savepoint state persists

## Next Steps

1. ✅ Verify the fix works in the test file
2. ⏳ Get user approval
3. ⏳ Roll out to all integration tests
4. ⏳ Document the rationale in code comments

