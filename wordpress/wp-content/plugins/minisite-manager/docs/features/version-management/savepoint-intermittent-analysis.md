# Savepoint Error - Intermittent Analysis

## User's Valid Concerns

1. **Error is intermittent** - suggests it's not always happening
2. **Nothing should have changed** from testing transaction point of view except Doctrine
3. **Are we always closing connection or only when things fail?**

## Current State

- **11 tests** call `migrate()`
- **9 tests** close connections after migrations
- **2 tests** do NOT close connections

## Why It's Intermittent

The error occurs when:
1. Migrations leave connection with **transaction nesting level > 0**
2. But savepoints are gone
3. Next operation tries to use savepoints → ERROR

**Why intermittent?**
- If migrations complete cleanly → nesting level = 0 → no error
- If migrations leave nesting level > 0 → error occurs
- Depends on which migrations run, in what order, and their internal state

## The Real Question

**Should we fix this at the source (Doctrine Migrations) or work around it (close connections)?**

### Option 1: Fix at Source
Ensure Doctrine Migrations properly resets transaction state after completion.

### Option 2: Work Around
Close connections in ALL tests that call `migrate()`.

## Next Steps

1. Identify which 2 tests don't close connections
2. Check if they're experiencing the error
3. Determine if we should:
   - Fix Doctrine Migrations to properly reset state
   - OR consistently close connections in all tests


