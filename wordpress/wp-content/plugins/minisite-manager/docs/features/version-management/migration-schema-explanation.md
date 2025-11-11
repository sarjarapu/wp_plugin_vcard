# Understanding `$schema` Parameter in Doctrine Migrations

## The Key Difference

### In `up(Schema $schema)`:
- **`$schema` = TARGET schema** (what we want to build/create)
- It's an **empty or modified schema object** representing the desired state
- It does **NOT** reflect what currently exists in the database
- **We need to introspect the database** to check current state

### In `down(Schema $schema)`:
- **`$schema` = CURRENT database schema** (what exists now)
- Doctrine Migrations **automatically introspects** the database and passes it to `down()`
- It **DOES** reflect what currently exists in the database
- **We can use it directly** without introspection

## Visual Example

### Scenario: Creating a table

```php
public function up(Schema $schema): void
{
    // $schema here is EMPTY or represents target state
    // $schema->hasTable('my_table') would return FALSE
    // even if the table EXISTS in the database!

    // So we MUST introspect to check database:
    $currentSchema = $schemaManager->introspectSchema();
    if ($currentSchema->hasTable('my_table')) {
        return; // Table already exists in DB
    }

    // Create table...
}
```

### Scenario: Dropping a table

```php
public function down(Schema $schema): void
{
    // $schema here is the CURRENT database schema
    // Doctrine Migrations already introspected the DB for us
    // $schema->hasTable('my_table') returns TRUE if table exists in DB

    if ($schema->hasTable('my_table')) {
        // Drop table...
    }
}
```

## Why This Design?

**In `up()`:**
- We're building the target state
- We need to check: "Does this table exist in the database RIGHT NOW?"
- The `$schema` parameter doesn't know the current DB state
- **Solution**: Introspect the database directly

**In `down()`:**
- We're reverting to a previous state
- We need to check: "Does this table exist in the database RIGHT NOW?"
- Doctrine Migrations **already introspected** and passed it to us
- **Solution**: Use `$schema->hasTable()` directly

## Summary

| Method   | `$schema` Parameter | What It Represents    | Can Use `$schema->hasTable()`?  |
| -------- | ------------------- | --------------------- | ------------------------------- |
| `up()`   | TARGET schema       | What we want to build | ❌ NO - doesn't reflect DB state |
| `down()` | CURRENT schema      | What exists in DB now | ✅ YES - already introspected    |

## Code Comparison

```php
// ❌ WRONG in up() - $schema doesn't know DB state
public function up(Schema $schema): void {
    if ($schema->hasTable('my_table')) {  // Always false!
        return;
    }
}

// ✅ CORRECT in up() - introspect database
public function up(Schema $schema): void {
    $currentSchema = $schemaManager->introspectSchema();
    if ($currentSchema->hasTable('my_table')) {  // Checks actual DB
        return;
    }
}

// ✅ CORRECT in down() - $schema is already current DB state
public function down(Schema $schema): void {
    if ($schema->hasTable('my_table')) {  // Checks actual DB
        // Drop table
    }
}
```

