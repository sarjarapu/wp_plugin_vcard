# Savepoint Investigation - Why Close Connection?

## The Question
**User's concern**: "I have no idea why close connection is required in first place to avoid savepoint error. Opening closing database connection per operation is very bad practice. Am I even using the savepoint feature in first place?"

## The Answer

### 1. Are We Using Savepoints Explicitly?
**NO** - We are NOT explicitly using savepoints anywhere in our codebase.

### 2. Why Are Savepoints Being Created?
Doctrine ORM **automatically creates savepoints** when:
- `flush()` is called
- AND there's an active transaction on the connection

This is Doctrine's internal mechanism for handling nested operations safely.

### 3. The Root Cause
The problem occurs when:
1. **Migrations run** (they're transactional: `isTransactional() => true`)
2. **Migrations complete** - Doctrine Migrations should commit/rollback automatically
3. **BUT** - The connection might still have lingering savepoint state
4. **Tests call `flush()`** - Doctrine tries to create a new savepoint
5. **Error**: "SAVEPOINT DOCTRINE_X does not exist" - because the savepoint state is corrupted

### 4. Why Close Connection?
Closing the connection is a **nuclear option** that:
- Forces a fresh connection state
- Clears ALL savepoints (they're connection-scoped)
- Ensures clean state for tests

**BUT** - The user is right: this is not ideal practice.

## Better Solutions (To Investigate)

### Option 1: Ensure Migrations Properly Commit
Doctrine Migrations with `isTransactional() => true` should automatically commit after successful migration. But maybe they're not? We should verify:
- Are migrations actually committing?
- Is there a transaction still active after migrations?

### Option 2: Explicitly Commit After Migrations
Instead of closing connection, we could:
```php
// After migrations
if ($connection->isTransactionActive()) {
    $connection->commit();
}
```

### Option 3: Disable Savepoints in Doctrine
Doctrine has configuration to control savepoint behavior. We could:
- Configure Doctrine to not use savepoints
- Or ensure transactions are properly managed

### Option 4: Ensure Clean Transaction State
Instead of closing connection:
```php
// After migrations
while ($connection->isTransactionActive()) {
    $connection->rollBack();
}
// Then ensure we're in autocommit mode
$connection->setAutoCommit(true);
```

## Next Steps
1. **Investigate**: Are migrations leaving transactions active?
2. **Test**: Can we ensure clean state without closing connection?
3. **Verify**: Does `isTransactionActive()` return false after migrations?
4. **Implement**: Better solution that doesn't require closing connection

## Current Status
- **All tests passing** with connection close approach
- **But** - User is right that this is not ideal
- **Action**: Investigate and implement better solution

