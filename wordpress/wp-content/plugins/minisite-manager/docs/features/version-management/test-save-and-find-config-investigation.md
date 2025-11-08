# test_save_and_find_config() - Savepoint Error Investigation

## ✅ Perfect Test for Investigation

**Test Method**: `test_save_and_find_config()`
**File**: `tests/Integration/Features/ConfigurationManagement/Repositories/ConfigRepositoryIntegrationTest.php:216`
**Status**: ✅ **Consistently fails** when connection close is commented out

## How to Reproduce

### Step 1: Comment Out Connection Close

In `ConfigRepositoryIntegrationTest.php`, comment out lines 147-152:

```php
// Close connection to clear ALL savepoints (connection-scoped)
// EntityManager will automatically reconnect when needed
// This is necessary because Doctrine's savepoint state persists even after transactions
// COMMENTED OUT FOR TESTING: Testing if savepoint errors occur without connection close
// try {
//     $connection->close();
// } catch (\Exception $e) {
//     // Ignore - connection might already be closed
// }
```

### Step 2: Run the Test

```bash
vendor/bin/phpunit --filter test_save_and_find_config \
  tests/Integration/Features/ConfigurationManagement/Repositories/ConfigRepositoryIntegrationTest.php
```

### Step 3: Observe Error

**Result**: 100% failure rate (3/3 runs failed)

**Error Message**:
```
PDOException: SQLSTATE[42000]: Syntax error or access violation:
1305 SAVEPOINT DOCTRINE_4 does not exist
```

**Error Location**:
- File: `src/Features/ConfigurationManagement/Repositories/ConfigRepository.php:104`
- Line: `$this->getEntityManager()->flush();`

## Test Flow (When Connection Close is Commented)

```
1. setUp() runs
   ├── Creates EntityManager
   ├── Runs migrations (migrate())
   │   └── Migrations create savepoints internally (DOCTRINE_1, DOCTRINE_2, DOCTRINE_3)
   ├── Tries to rollback transactions
   ├── Clears EntityManager
   └── ❌ Connection close is COMMENTED OUT
       └── Savepoint state persists (transaction nesting level > 0)

2. test_save_and_find_config() runs
   ├── Creates Config entity
   ├── Calls repository->save($config)
   │   └── Calls $em->persist($config)
   │   └── Calls $em->flush()  ← ERROR HERE
   │       └── Doctrine tries to create SAVEPOINT DOCTRINE_4
   │       └── But savepoint state is corrupted → ERROR
   └── Test fails
```

## Error Details

### Error Stack Trace

```
ConfigRepository.php:104
  └── $this->getEntityManager()->flush()
      └── EntityManager.php:268
          └── UnitOfWork.php:432
              └── Connection.php:1084
                  └── Connection.php:1191
                      └── Connection.php:909
                          └── PDO Connection.php:33
                              └── ERROR: SAVEPOINT DOCTRINE_4 does not exist
```

### Why It Fails

1. **Migrations create savepoints**: `DOCTRINE_1`, `DOCTRINE_2`, `DOCTRINE_3`
2. **Connection close is commented out**: Savepoint state persists
3. **Repository->save() calls flush()**: Doctrine tries to create `SAVEPOINT DOCTRINE_4`
4. **Savepoint state is corrupted**: Doctrine thinks savepoints exist, but they don't
5. **ERROR**: `SAVEPOINT DOCTRINE_4 does not exist`

## Test Code

### The Test Method

```php
public function test_save_and_find_config(): void
{
    $config = new Config();
    $config->key = 'test_key';
    $config->type = 'string';
    $config->setTypedValue('test_value');

    $saved = $this->repository->save($config);  // ← Line 223, calls flush() at line 104

    $this->assertNotNull($saved->id);

    $found = $this->repository->findByKey('test_key');

    $this->assertNotNull($found);
    $this->assertEquals('test_key', $found->key);
    $this->assertEquals('test_value', $found->getTypedValue());
}
```

### The Repository Save Method

```php
// ConfigRepository.php:95-104
public function save(Config $config): Config
{
    try {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();  // ← ERROR OCCURS HERE (line 104)

        return $config;
    } catch (\Exception $e) {
        // Error handling
    }
}
```

## Why This Test is Perfect for Investigation

1. ✅ **Simple**: Only 3 operations (create, save, find)
2. ✅ **Direct**: Directly calls `repository->save()` which triggers `flush()`
3. ✅ **Consistent**: Fails 100% of the time (3/3 runs)
4. ✅ **Clear error location**: Error at `ConfigRepository.php:104`
5. ✅ **No dependencies**: Doesn't depend on other tests
6. ✅ **Easy to debug**: Can add logging/breakpoints easily

## Investigation Strategy

### 1. Add Debug Logging

Add logging in `setUp()` to see connection state:

```php
// After migrations, before connection close
$connection = $this->em->getConnection();
echo "Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
```

### 2. Check Savepoint State

Try to query MySQL for active savepoints (if possible):

```php
// Check if we can see savepoint state
$result = $connection->executeQuery("SHOW SAVEPOINTS");
```

### 3. Try Alternative Fixes

Instead of closing connection, try:

**Option A**: Reset transaction nesting level manually
```php
// If there's a way to reset nesting level
$connection->setTransactionNestingLevel(0);
```

**Option B**: Explicitly release all savepoints
```php
// Try to release all savepoints
while ($connection->getTransactionNestingLevel() > 0) {
    $connection->rollBack();
}
```

**Option C**: Fix at Doctrine Migrations level
- Investigate why migrations leave corrupted state
- Fix in `DoctrineMigrationRunner` or migration classes

### 4. Check Doctrine Connection State

Use reflection to inspect Doctrine's internal state:

```php
$reflection = new \ReflectionClass($connection);
$property = $reflection->getProperty('transactionNestingLevel');
$property->setAccessible(true);
$nestingLevel = $property->getValue($connection);
echo "Internal nesting level: $nestingLevel\n";
```

## Test Results

### With Connection Close (Original)
- ✅ **Run 1**: PASS
- ✅ **Run 2**: PASS
- ✅ **Run 3**: PASS

### Without Connection Close (Commented Out)
- ❌ **Run 1**: FAIL - `SAVEPOINT DOCTRINE_4 does not exist`
- ❌ **Run 2**: FAIL - `SAVEPOINT DOCTRINE_3 does not exist` (variation)
- ❌ **Run 3**: FAIL - `SAVEPOINT DOCTRINE_3 does not exist` (variation)

**Note**: The savepoint number varies (DOCTRINE_3 or DOCTRINE_4) depending on how many savepoints were created during migrations, but the error is **100% consistent**.

## Next Steps

1. ✅ **Reproduce error** - DONE (100% consistent)
2. ⏳ **Add debug logging** - See connection state after migrations
3. ⏳ **Investigate Doctrine internal state** - Check transaction nesting level
4. ⏳ **Try alternative fixes** - Without closing connection
5. ⏳ **Document findings** - Create fix proposal

