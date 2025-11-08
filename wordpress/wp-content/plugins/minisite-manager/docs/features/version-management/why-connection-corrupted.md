# Why Does the Connection End Up in a Corrupted State?

## The Root Cause

The connection gets corrupted because **Doctrine Migrations library doesn't properly reset transaction nesting level** after migrations complete, even though it commits the transaction.

## Step-by-Step Breakdown

### 1. Migration Configuration

```php
// DoctrineMigrationRunner.php
'all_or_nothing' => true,  // All migrations run in ONE transaction
```

### 2. Individual Migrations Are Transactional

```php
// Version20251105000000.php
public function isTransactional(): bool
{
    return true;  // This migration runs in a transaction
}
```

### 3. What Doctrine Migrations Does

When `$migrator->migrate($plan, $migratorConfig)` is called:

1. **Starts a transaction** (because `all_or_nothing => true`)
2. **For each migration**:
   - If `isTransactional() => true`, Doctrine Migrations creates a **savepoint** (e.g., `SAVEPOINT DOCTRINE_1`)
   - Executes the migration's `up()` method
   - If successful, releases the savepoint
   - If failed, rolls back to the savepoint
3. **After all migrations**:
   - Commits the outer transaction
   - **BUT**: The transaction nesting level counter might not reset properly

### 4. The Problem

**Doctrine tracks transaction nesting level internally** (not just in MySQL):

```php
// Doctrine's internal state (simplified)
$connection->transactionNestingLevel = 2;  // ← This doesn't reset!
```

**What happens:**
- Migrations create savepoints: `SAVEPOINT DOCTRINE_1`, `SAVEPOINT DOCTRINE_2`
- Migrations complete and release savepoints
- MySQL transaction commits ✅
- **BUT**: Doctrine's internal `transactionNestingLevel` counter is still > 0 ❌

### 5. The Corrupted State

After migrations complete:

```
MySQL State:
  - Transaction: COMMITTED ✅
  - Savepoints: RELEASED ✅

Doctrine's Internal State:
  - transactionNestingLevel: 2 ❌ (should be 0!)
  - Expected savepoints: DOCTRINE_1, DOCTRINE_2 (but they're gone!)
```

### 6. Why This Causes Errors

When the next test calls `flush()`:

```php
// Test code
$connection->beginTransaction();  // Nesting level: 1
$em->persist($entity);
$em->flush();  // Doctrine tries to create SAVEPOINT DOCTRINE_3

// But Doctrine's internal state thinks:
// - Nesting level should be 3 (1 from beginTransaction + 2 from migrations)
// - It tries to reference DOCTRINE_2 (which doesn't exist)
// - ERROR: SAVEPOINT DOCTRINE_2 does not exist
```

## Why It's Intermittent

The error is **intermittent** because:

1. **If migrations run cleanly**:
   - Transaction nesting level resets properly
   - No error ✅

2. **If migrations have internal savepoint operations**:
   - Nesting level doesn't reset
   - Next test fails ❌

3. **Connection reuse**:
   - Same connection used across tests
   - Corrupted state persists until connection is closed

## The Evidence

From `reproduce-savepoint-error-test.php`:

```
Step 2: Running migrations...
  - Migrations completed
  - Transaction active after migrations: YES
  - Transaction nesting level: 2  ← CORRUPTED STATE
```

**Even though migrations completed successfully**, the nesting level is 2 (should be 0).

## Why Closing Connection Works

Closing the connection:
1. **Forces Doctrine to reset all internal state**
   - `transactionNestingLevel` resets to 0
   - All savepoint tracking is cleared
2. **EntityManager auto-reconnects** when needed
3. **Fresh connection = clean state**

## The Real Question

**Why doesn't Doctrine Migrations reset the nesting level?**

This is likely a **bug or limitation** in Doctrine Migrations:
- It properly commits the MySQL transaction
- But doesn't reset Doctrine's internal transaction tracking
- The connection object retains corrupted state

## Better Solutions (Future)

### Option 1: Fix in DoctrineMigrationRunner
After migrations complete, explicitly reset state:

```php
// After $migrator->migrate()
$connection->setTransactionNestingLevel(0);  // If this method exists
```

### Option 2: Use a Fresh Connection for Tests
Create a new connection after migrations instead of reusing:

```php
// After migrations
$oldConnection = $em->getConnection();
$oldConnection->close();

// Create new connection
$newConnection = DriverManager::getConnection([...]);
$em = new EntityManager($newConnection, $config);
```

### Option 3: Report to Doctrine Migrations
This might be a bug in Doctrine Migrations library that should be fixed upstream.

## Current Workaround

**Closing the connection** is the most reliable workaround:
- Ensures clean state
- Works consistently
- EntityManager handles reconnection automatically

But you're right: **it's not ideal practice** for production code. It's acceptable for tests where we need guaranteed clean state.

