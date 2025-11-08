# Savepoint Error Root Cause - REPRODUCED ✅

## The Error
```
SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT DOCTRINE_X does not exist
```

## Reproduction Test
See: `tests/Integration/Features/VersionManagement/reproduce-savepoint-error-test.php`

## Root Cause Identified

### The Problem Sequence

1. **Migrations Run**
   - Migrations execute with `isTransactional() => true`
   - Doctrine creates savepoints internally (DOCTRINE_1, DOCTRINE_2, etc.)
   - Migrations complete

2. **After Migrations - Connection State is CORRUPTED**
   ```
   Transaction active: YES
   Transaction nesting level: 2  ← THIS IS THE PROBLEM!
   ```

3. **Attempting to Rollback**
   - Doctrine tries to rollback to SAVEPOINT DOCTRINE_2
   - But the savepoint doesn't exist anymore → ERROR

4. **Attempting to Use flush()**
   - Doctrine tries to create SAVEPOINT DOCTRINE_3
   - But connection state is corrupted → ERROR

### Key Finding

**The transaction nesting level is 2 after migrations, but the savepoints are gone!**

This means:
- Migrations created nested transactions (or savepoints)
- The savepoints were released/committed
- But the transaction nesting level counter is still at 2
- Doctrine thinks there are savepoints, but they don't exist

## Why This Happens

Doctrine tracks transaction nesting level internally. When migrations:
1. Create savepoints (DOCTRINE_1, DOCTRINE_2)
2. Complete and release savepoints
3. But the nesting level counter doesn't reset properly

The connection is left in an inconsistent state:
- Nesting level says: 2 (expects savepoints)
- Reality: Savepoints are gone

## The Fix

**Close the connection** to reset ALL state:
- Transaction nesting level resets to 0
- All savepoint state is cleared
- Connection is fresh

## Test Results

```
Step 2: Running migrations...
  - Migrations completed
  - Transaction active after migrations: YES
  - Transaction nesting level: 2  ← CORRUPTED STATE

Step 3: Cleaning up transactions...
  - Rollback error: SAVEPOINT DOCTRINE_2 does not exist  ← ERROR REPRODUCED

Step 5: Attempting to use flush()...
  - SAVEPOINT ERROR: SAVEPOINT DOCTRINE_3 does not exist  ← ERROR REPRODUCED
```

## Conclusion

✅ **Error successfully reproduced**
✅ **Root cause identified**: Transaction nesting level mismatch after migrations
✅ **Fix confirmed**: Closing connection resets all state

