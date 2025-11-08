# Understanding `flush()` and Transactions

## Your Questions Answered

### Q1: If I have multiple `persist()` calls, does `flush()` convert them all into SQL statements at once?

**YES!** This is called **batching**.

```php
// All these persist() calls just QUEUE the entities
$em->persist($entity1);  // Queued, NOT saved
$em->persist($entity2);  // Queued, NOT saved
$em->persist($entity3);  // Queued, NOT saved

// ONE flush() converts ALL 3 into SQL and executes them
$em->flush();  // ← Executes: INSERT entity1, INSERT entity2, INSERT entity3
```

**What happens:**
1. All `persist()` calls queue entities in Doctrine's UnitOfWork
2. `flush()` converts ALL queued operations into SQL statements
3. All SQL statements run in **ONE database transaction** (atomic)
4. If any operation fails, the entire transaction rolls back

### Q2: If I already have a transaction at outer scope, does `flush()` create a nested transaction?

**NO!** `flush()` does **NOT** create nested transactions. It uses **SAVEPOINTS** instead.

## Key Concepts

### 1. Savepoints vs Nested Transactions

**Savepoints** are **markers** within the same transaction, NOT separate transactions:

```php
$connection->beginTransaction();  // Transaction nesting level: 1

$em->persist($entity);
$em->flush();  // Creates SAVEPOINT DOCTRINE_1 (marker, not new transaction)
// Transaction nesting level: STILL 1

$em->persist($entity2);
$em->flush();  // Creates SAVEPOINT DOCTRINE_2 (another marker)
// Transaction nesting level: STILL 1

$connection->rollBack();  // Rolls back ENTIRE transaction (all savepoints)
```

**Real nested transactions** (if you call `beginTransaction()` twice):

```php
$connection->beginTransaction();  // Level 1
$connection->beginTransaction();  // Level 2 (Doctrine creates SAVEPOINT)
// MySQL doesn't support true nested transactions, so Doctrine uses savepoints
```

### 2. Why Savepoints?

Doctrine uses savepoints for **internal state tracking**:
- If `flush()` fails, Doctrine can rollback just that flush operation
- Allows Doctrine to maintain consistency within a transaction
- **BUT**: Savepoints are connection-scoped and can get corrupted

### 3. What Happens in Practice

```php
// Scenario: You have an outer transaction
$connection->beginTransaction();  // Outer transaction

// Multiple entities, different types
$em->persist($version);      // Queued
$em->persist($config);       // Queued
$em->persist($review);       // Queued

// ONE flush() executes ALL 3 in the SAME transaction
$em->flush();
// SQL executed:
//   INSERT INTO wp_minisite_versions ...
//   INSERT INTO wp_minisite_config ...
//   INSERT INTO wp_minisite_reviews ...
// All in ONE atomic transaction

// Transaction nesting level: STILL 1 (same transaction)
// But Doctrine created SAVEPOINT DOCTRINE_1 internally
```

## Visual Diagram

```
Outer Transaction (beginTransaction())
├── persist(entity1) → Queued
├── persist(entity2) → Queued
├── persist(entity3) → Queued
├── flush() → Executes all 3 SQL statements
│   └── Creates SAVEPOINT DOCTRINE_1 (marker, not transaction)
│
├── persist(entity4) → Queued
├── flush() → Executes SQL
│   └── Creates SAVEPOINT DOCTRINE_2 (marker, not transaction)
│
└── rollBack() → Rolls back ENTIRE transaction (all savepoints cleared)
```

## Important Points

1. **Batching**: Multiple `persist()` + single `flush()` = all operations in one transaction
2. **No Nested Transactions**: `flush()` doesn't create nested transactions, it uses savepoints
3. **Savepoints are Markers**: They're connection-scoped markers, not separate transactions
4. **Atomic Operations**: All `flush()` operations within a transaction are atomic (all or nothing)
5. **The Problem**: Savepoint state can get corrupted on the connection, causing errors

## The Savepoint Error

The error `SAVEPOINT DOCTRINE_X does not exist` occurs when:
- Migrations create savepoints internally
- Savepoint state persists on the connection (even after transactions commit)
- Next `flush()` tries to create a new savepoint
- Connection's savepoint state is corrupted → error

**Solution**: Close connection to clear all savepoint state (they're connection-scoped).

## Test File

See `tests/Integration/Features/VersionManagement/flush-transaction-explanation-test.php` for a working demonstration of all these concepts.

