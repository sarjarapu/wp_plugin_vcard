# Method Call Flow Analysis - With and Without Connection Close

## Overview

This document shows the method call flow for `test_save_and_find_config()`, focusing on key methods with inline comments.

---

## Scenario 1: WITH Connection Close ✅

### setUp() Method Flow

```php
// Line 49: Create database connection
$connection = DriverManager::getConnection([...]);  // Connection state: nestingLevel=0, savepoints=[]

// Line 70: Create EntityManager
$this->em = new EntityManager($connection, $config);  // EntityManager created with connection

// Line 77: Clean any existing transactions
$connection->executeStatement('ROLLBACK');  // Safe even if no transaction active

// Line 85: Reset savepoint counter
$connection->beginTransaction();  // nestingLevel = 1
$connection->commit();  // nestingLevel = 0

// Line 97: Clear EntityManager state
$this->em->clear();  // UnitOfWork cleared, no entities tracked

// Line 119: Run migrations
$migrationRunner->migrate();  // Calls Doctrine Migrations
  // Inside migrate():
  //   - Doctrine starts transaction: beginTransaction() → nestingLevel = 1
  //   - For each migration (isTransactional() => true):
  //     - Creates savepoint: SAVEPOINT DOCTRINE_1 → savepoints = ['DOCTRINE_1']
  //     - Executes migration: CREATE TABLE wp_minisite_config ...
  //     - Releases savepoint: RELEASE SAVEPOINT DOCTRINE_1 → savepoints = []
  //     - Next migration: DOCTRINE_2, DOCTRINE_3, etc.
  //   - Commits transaction: commit() → nestingLevel = 0
  //   - ⚠️ BUT: Doctrine's internal savepoint counter = 3 (not reset!)

// Line 129: Cleanup transactions
while ($connection->isTransactionActive()) {
    $connection->rollBack();  // nestingLevel = 0
}

// Line 142: Clear EntityManager again
$this->em->clear();  // UnitOfWork cleared

// Line 148: ✅ CLOSE CONNECTION (KEY DIFFERENCE)
$connection->close();  // Closes PDO connection
  // Connection state: RESET
  //   - nestingLevel = 0
  //   - savepoints = []
  //   - Doctrine's internal savepoint counter = 0 (RESET!)

// Line 156: Create repository
$this->repository = new ConfigRepository($this->em, $classMetadata);  // Repository ready, EntityManager will auto-reconnect
```

### test_save_and_find_config() Method Flow (WITH Connection Close)

```php
// Line 218: Create Config entity
$config = new Config();  // Entity created (not persisted)

// Line 219-221: Set entity properties
$config->key = 'test_key';
$config->type = 'string';
$config->setTypedValue('test_value');

// Line 223: Save entity
$saved = $this->repository->save($config);  // Calls ConfigRepository::save()
  // Inside ConfigRepository::save() (line 103):
  //   $this->getEntityManager()  // Returns EntityManager
  //     - EntityManager detects connection is closed
  //     - ✅ Auto-reconnects (creates NEW connection)
  //     - NEW Connection state: nestingLevel=0, savepoints=[], counter=0

  // Line 103: Persist entity
  $this->getEntityManager()->persist($config);  // Queues entity for INSERT
    // Inside persist():
    //   - UnitOfWork::scheduleForInsert($config)
    //   - Entity queued: insertions = [$config]
    //   - NOT saved to database yet

  // Line 104: Flush (execute SQL)
  $this->getEntityManager()->flush();  // Executes queued operations
    // Inside flush():
    //   - UnitOfWork::commit()
    //   - Check transaction: isTransactionActive() → false
    //   - Start transaction: beginTransaction() → nestingLevel = 1
    //   - ✅ Create savepoint: SAVEPOINT DOCTRINE_1 → SUCCESS (clean state, counter=0)
    //   - Execute SQL: INSERT INTO wp_minisite_config ...
    //   - Release savepoint: RELEASE SAVEPOINT DOCTRINE_1
    //   - Commit transaction: commit() → nestingLevel = 0
    //   - ✅ Entity saved successfully

// Line 225: Assert ID is set
$this->assertNotNull($saved->id);  // ✅ Passes

// Line 227: Find entity
$found = $this->repository->findByKey('test_key');  // Finds from database

// Line 229-231: Assertions
$this->assertEquals('test_key', $found->key);  // ✅ Passes
$this->assertEquals('test_value', $found->getTypedValue());  // ✅ Passes
```

---

## Scenario 2: WITHOUT Connection Close ❌

### setUp() Method Flow (Same until line 148)

```php
// Lines 49-142: (Same as WITH connection close)

// Line 148: ❌ CONNECTION CLOSE IS COMMENTED OUT
// $connection->close();  // NOT EXECUTED
  // Connection state: STILL OPEN
  //   - nestingLevel = 0 (MySQL level is clean)
  //   - ⚠️ BUT: Doctrine's internal savepoint counter = 3 (from migrations)
  //   - ⚠️ Doctrine thinks: savepoints = ['DOCTRINE_1', 'DOCTRINE_2', 'DOCTRINE_3']
  //   - ⚠️ Reality: savepoints = [] (they were released during migrations)

// Line 156: Create repository
$this->repository = new ConfigRepository($this->em, $classMetadata);  // Repository ready, using SAME connection (corrupted state)
```

### test_save_and_find_config() Method Flow (WITHOUT Connection Close)

```php
// Lines 218-221: (Same as WITH connection close)

// Line 223: Save entity
$saved = $this->repository->save($config);  // Calls ConfigRepository::save()
  // Inside ConfigRepository::save() (line 103):
  //   $this->getEntityManager()  // Returns EntityManager
  //     - Connection is STILL OPEN (not closed)
  //     - ⚠️ Connection state: nestingLevel=0, but savepoint counter=3 (corrupted!)

  // Line 103: Persist entity
  $this->getEntityManager()->persist($config);  // Queues entity for INSERT
    // (Same as WITH connection close)

  // Line 104: Flush (execute SQL)
  $this->getEntityManager()->flush();  // Executes queued operations
    // Inside flush():
    //   - UnitOfWork::commit()
    //   - Check transaction: isTransactionActive() → false
    //   - Start transaction: beginTransaction() → nestingLevel = 1
    //   - ❌ Try to create savepoint: SAVEPOINT DOCTRINE_4
    //     - Doctrine checks internal counter: counter = 3 (from migrations)
    //     - Doctrine tries: SAVEPOINT DOCTRINE_4
    //     - ⚠️ BUT: Doctrine's internal state is corrupted
    //     - ❌ ERROR: SQLSTATE[42000]: SAVEPOINT DOCTRINE_4 does not exist
    //     - OR: SQLSTATE[42000]: SAVEPOINT DOCTRINE_3 does not exist
    //   - Exception thrown, entity NOT saved

// Line 225: Never reached (exception thrown above)
// $this->assertNotNull($saved->id);  // ❌ Test fails with savepoint error
```

---

## Key Differences Summary

### Connection State After Migrations

| Aspect                          | WITH Connection Close               | WITHOUT Connection Close                           |
| ------------------------------- | ----------------------------------- | -------------------------------------------------- |
| **Connection**                  | Closed (new connection on next use) | Open (same connection)                             |
| **MySQL nestingLevel**          | 0 (reset)                           | 0 (clean)                                          |
| **Doctrine savepoint counter**  | 0 (reset)                           | 3 (corrupted)                                      |
| **Doctrine savepoint tracking** | [] (empty)                          | ['DOCTRINE_1', 'DOCTRINE_2', 'DOCTRINE_3'] (stale) |
| **Next flush()**                | Creates DOCTRINE_1 (fresh)          | Tries DOCTRINE_4 (fails)                           |

### flush() Behavior Comparison

| Step                  | WITH Connection Close                   | WITHOUT Connection Close                |
| --------------------- | --------------------------------------- | --------------------------------------- |
| **Check transaction** | No active transaction                   | No active transaction                   |
| **Start transaction** | `beginTransaction()` → nestingLevel = 1 | `beginTransaction()` → nestingLevel = 1 |
| **Create savepoint**  | `SAVEPOINT DOCTRINE_1` ✅                | `SAVEPOINT DOCTRINE_4` ❌                |
| **Execute SQL**       | INSERT succeeds ✅                       | Never reached ❌                         |
| **Result**            | Entity saved ✅                          | Exception thrown ❌                      |

---

## The Root Cause

### Doctrine's Internal Savepoint Counter

Doctrine maintains an internal counter for savepoint names:

```php
// Doctrine's internal state (simplified)
class Connection {
    private $savepointCounter = 0;  // Internal counter

    public function createSavepoint() {
        $this->savepointCounter++;  // Increments: 1, 2, 3, 4...
        $name = 'DOCTRINE_' . $this->savepointCounter;
        $this->executeStatement("SAVEPOINT $name");
        return $name;
    }
}
```

### The Problem Sequence

1. **During migrations**: Counter increments to 3 (DOCTRINE_1, DOCTRINE_2, DOCTRINE_3)
2. **Migrations complete**: Savepoints are released, but counter stays at 3
3. **Next flush()**: Doctrine tries DOCTRINE_4, but state is corrupted
4. **Error**: Savepoint doesn't exist or state mismatch

### The Solution

**Closing connection** resets everything:
- New connection = new counter = 0
- Fresh state = no corruption
- Next flush() = DOCTRINE_1 (clean start)

---

## Visual Flow Comparison

### WITH Connection Close ✅

```
setUp()
  migrate() → creates DOCTRINE_1, DOCTRINE_2, DOCTRINE_3
  connection->close() → RESETS EVERYTHING (counter=0, savepoints=[])

test_save_and_find_config()
  flush() → creates DOCTRINE_1 (fresh start) ✅
```

### WITHOUT Connection Close ❌

```
setUp()
  migrate() → creates DOCTRINE_1, DOCTRINE_2, DOCTRINE_3
  connection stays open → counter=3, but savepoints released

test_save_and_find_config()
  flush() → tries DOCTRINE_4 → ERROR ❌
```

---

## Summary

**The root cause**: Doctrine's internal savepoint counter doesn't reset after migrations, even though the actual savepoints are released.

**The fix**: Closing the connection forces a complete reset of all state, including Doctrine's internal counters.

**Why it works**: New connection = fresh state = no corruption.
