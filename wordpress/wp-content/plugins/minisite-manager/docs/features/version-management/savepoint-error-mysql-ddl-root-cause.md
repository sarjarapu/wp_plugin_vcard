# Savepoint Error Root Cause - MySQL DDL Implicit Commits

## The Real Issue

This is **NOT a Doctrine bug** - it's a **known limitation** of MySQL with Doctrine Migrations.

## Official Doctrine Documentation

According to [Doctrine Migrations documentation](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html):

> "Some platforms like MySQL or Oracle do not support DDL statements (`CREATE TABLE`, `ALTER TABLE`, etc.) in transactions. The issue existed before PHP 8 but is now made visible by e.g. PDO, which now produces the above error message when this library attempts to commit a transaction that has already been committed before."

## What Happens

1. **Migration has `isTransactional() => true`**
2. **Doctrine Migrations wraps migration in a transaction**
3. **Migration executes `CREATE TABLE` (DDL statement)**
4. **MySQL performs implicit commit** (DDL statements auto-commit in MySQL)
5. **Doctrine's transaction nesting level doesn't reset** (still thinks transaction is active)
6. **Connection state is corrupted** - Doctrine expects savepoints that don't exist

## The Problem Sequence

```
1. Doctrine starts transaction (nesting level = 1)
2. Migration isTransactional() => true, so Doctrine creates savepoint (nesting level = 2)
3. Migration executes CREATE TABLE
4. MySQL implicitly commits (transaction is gone!)
5. Doctrine releases savepoint (but savepoint doesn't exist - already committed)
6. Doctrine tries to commit outer transaction (but it's already committed)
7. Doctrine's nesting level is still 2, but MySQL transaction is gone
8. Next operation tries to create savepoint → ERROR: SAVEPOINT DOCTRINE_X does not exist
```

## Solutions (Per Doctrine Documentation)

### Solution 1: Set `isTransactional() => false` for DDL migrations (RECOMMENDED)

For migrations that use DDL (CREATE TABLE, ALTER TABLE), set:

```php
public function isTransactional(): bool
{
    return false;  // MySQL doesn't support transactional DDL
}
```

### Solution 2: Set `all_or_nothing => false` (Less Ideal)

This prevents wrapping multiple migrations in a transaction, but each migration can still be transactional if `isTransactional() => true`.

## Why This Happens

- **MySQL doesn't support transactional DDL** - This is a MySQL limitation, not Doctrine
- **Doctrine Migrations assumes transactions work** - It doesn't know MySQL auto-commits DDL
- **Transaction state gets out of sync** - MySQL commits, but Doctrine's internal counter doesn't reset

## References

- [Doctrine Migrations: Implicit Commits](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html)
- [Doctrine Migrations Configuration](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/configuration.html)

