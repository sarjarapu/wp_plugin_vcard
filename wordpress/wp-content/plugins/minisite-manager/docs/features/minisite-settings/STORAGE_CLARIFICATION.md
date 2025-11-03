# Storage Clarification: `_minisite_` Fields

## Current State vs. Documentation

There's a **discrepancy** between the documentation and actual implementation regarding `_minisite_` prefixed fields.

### Documentation Says (Future/Planned)

The documentation (`docs/project/listing-minisites.md`) describes these fields as **post meta** for a Custom Post Type:

```php
Meta (single):
  - _minisite_owner_user_id (int, required)
  - _minisite_assigned_editors (array<int>)
  - _minisite_online (bool; default false)
  - _minisite_current_version_id (int; FK → versions table)
  - _minisite_business_slug (string)
  - _minisite_location_slug (string)
```

**Expected Storage**: `wp_postmeta` table (if using CPT)

### Actual Implementation (Current)

**Reality**: Minisites are **NOT** stored as WordPress posts. They use a **custom table**:

- **Table**: `wp_minisites` (custom table, NOT `wp_posts`)
- **Repository**: Queries directly from `$wpdb->prefix . 'minisites'`
- **No CPT**: No `register_post_type('minisite')` exists
- **No Post Meta**: Since there are no posts, there's no `wp_postmeta` entries

### Current Database Schema

```sql
CREATE TABLE wp_minisites (
    id VARCHAR(32) NOT NULL,
    -- ... standard fields ...
    created_by BIGINT UNSIGNED NULL,           -- Currently used as owner surrogate
    updated_by BIGINT UNSIGNED NULL,
    _minisite_current_version_id BIGINT UNSIGNED NULL,  -- ✅ EXISTS in table
    PRIMARY KEY (id),
    -- ...
) ENGINE=InnoDB;
```

**Missing Fields** (mentioned in docs but NOT in schema):
- ❌ `_minisite_owner_user_id` - **NOT in table yet**
- ❌ `_minisite_assigned_editors` - **NOT in table yet**
- ❌ `_minisite_online` - **NOT in table yet**
- ❌ `_minisite_business_slug` - **NOT in table** (but `business_slug` column exists)
- ❌ `_minisite_location_slug` - **NOT in table** (but `location_slug` column exists)

### Code Evidence

From `MinisiteRepository.php`:
```php
/**
 * List minisites owned by a user (v1 minimal: uses created_by as owner surrogate)
 * TODO: Switch to explicit owner_user_id column when added.
 */
public function listByOwner(int $userId, int $limit = 50, int $offset = 0): array
{
    $sql = $this->db->prepare(
        "SELECT * FROM {$this->table()} WHERE created_by=%d ...",
        // Uses created_by, not owner_user_id
    );
}
```

---

## Storage Options

Since minisites are **NOT** WordPress posts, the `_minisite_` fields need to be stored in one of these ways:

### Option 1: Add Columns to Custom Table (Recommended)

**Add new columns to `wp_minisites` table**:

```sql
ALTER TABLE wp_minisites
  ADD COLUMN owner_user_id BIGINT UNSIGNED NULL AFTER created_by,
  ADD COLUMN assigned_editors JSON NULL,
  ADD COLUMN online TINYINT(1) NOT NULL DEFAULT 0,
  ADD INDEX idx_owner (owner_user_id);
```

**Pros**:
- ✅ Single table, efficient queries
- ✅ No JOINs needed
- ✅ Atomic updates
- ✅ Better performance

**Cons**:
- ⚠️ Requires migration
- ⚠️ Schema changes

**Storage Table**: `wp_minisites` (custom table)

---

### Option 2: Separate Meta Table (WordPress-like)

**Create a separate meta table** (similar to `wp_postmeta`):

```sql
CREATE TABLE wp_minisite_meta (
    meta_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    minisite_id VARCHAR(32) NOT NULL,
    meta_key VARCHAR(255) NOT NULL,
    meta_value LONGTEXT NULL,
    INDEX idx_minisite_id (minisite_id),
    INDEX idx_meta_key (meta_key),
    UNIQUE KEY uniq_minisite_meta (minisite_id, meta_key),
    FOREIGN KEY (minisite_id) REFERENCES wp_minisites(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Pros**:
- ✅ Flexible schema (can add fields without ALTER)
- ✅ Similar to WordPress patterns
- ✅ Easy to extend

**Cons**:
- ⚠️ Requires JOINs for queries
- ⚠️ Slightly slower than single table
- ⚠️ More complex queries

**Storage Table**: `wp_minisite_meta` (custom meta table)

---

### Option 3: WordPress Post Meta (If Converting to CPT)

If minisites were converted to a Custom Post Type:

```php
register_post_type('minisite', [...]);
```

Then fields would be stored in:
- **Storage Table**: `wp_postmeta`
- **Access**: `get_post_meta()`, `update_post_meta()`

**Pros**:
- ✅ Uses WordPress built-in functions
- ✅ Compatible with WordPress plugins
- ✅ Easy to query

**Cons**:
- ⚠️ Requires major refactoring (convert custom table to CPT)
- ⚠️ Data migration needed
- ⚠️ Not currently implemented

---

## Recommendation for Settings Feature

**Use Option 1: Add columns to `wp_minisites` table**

### Migration SQL

```sql
-- Add owner_user_id column (defaults to created_by for existing minisites)
ALTER TABLE wp_minisites
  ADD COLUMN owner_user_id BIGINT UNSIGNED NULL AFTER created_by;

-- Set existing minisites to use created_by as owner
UPDATE wp_minisites SET owner_user_id = created_by WHERE owner_user_id IS NULL;

-- Make owner_user_id required (after backfill)
ALTER TABLE wp_minisites
  MODIFY COLUMN owner_user_id BIGINT UNSIGNED NOT NULL;

-- Add online status column
ALTER TABLE wp_minisites
  ADD COLUMN online TINYINT(1) NOT NULL DEFAULT 0 AFTER owner_user_id;

-- Add assigned_editors JSON column
ALTER TABLE wp_minisites
  ADD COLUMN assigned_editors JSON NULL AFTER online;

-- Add indexes
ALTER TABLE wp_minisites
  ADD INDEX idx_owner_user_id (owner_user_id),
  ADD INDEX idx_online (online);
```

### Code Implementation

**Repository**:
```php
public function updateOnlineStatus(string $minisiteId, bool $online): void
{
    $this->db->update(
        $this->table(),
        ['online' => $online ? 1 : 0],
        ['id' => $minisiteId],
        ['%d'],
        ['%s']
    );
}

public function updateOwner(string $minisiteId, int $newOwnerId): void
{
    $this->db->update(
        $this->table(),
        ['owner_user_id' => $newOwnerId],
        ['id' => $minisiteId],
        ['%d'],
        ['%s']
    );
}

public function updateAssignedEditors(string $minisiteId, array $editorIds): void
{
    $this->db->update(
        $this->table(),
        ['assigned_editors' => json_encode($editorIds)],
        ['id' => $minisiteId],
        ['%s'],
        ['%s']
    );
}
```

---

## Field Naming Convention

**Current Pattern**:
- Fields in `wp_minisites` table use **direct names**: `created_by`, `business_slug`, `location_slug`
- One exception: `_minisite_current_version_id` (has underscore prefix)

**Recommendation**:
For consistency with existing schema, use **direct column names** (no `_minisite_` prefix):

- ✅ `owner_user_id` (not `_minisite_owner_user_id`)
- ✅ `online` (not `_minisite_online`)
- ✅ `assigned_editors` (not `_minisite_assigned_editors`)

**Exception**: Keep `_minisite_current_version_id` as-is for backward compatibility.

---

## Summary

| Field | Documentation | Current Reality | Recommended Storage |
|-------|--------------|----------------|-------------------|
| `_minisite_owner_user_id` | Post meta | ❌ Not exists | Column: `owner_user_id` in `wp_minisites` |
| `_minisite_assigned_editors` | Post meta | ❌ Not exists | Column: `assigned_editors` (JSON) in `wp_minisites` |
| `_minisite_online` | Post meta | ❌ Not exists | Column: `online` (TINYINT) in `wp_minisites` |
| `_minisite_current_version_id` | Post meta | ✅ Exists | Already in `wp_minisites` table |
| `_minisite_business_slug` | Post meta | ✅ Exists as `business_slug` | Already in `wp_minisites` table |
| `_minisite_location_slug` | Post meta | ✅ Exists as `location_slug` | Already in `wp_minisites` table |

**Action Required**: 
1. Create migration to add missing columns to `wp_minisites` table
2. Update documentation to reflect actual storage (columns, not post meta)
3. Update repository methods to use new columns

---

**Last Updated**: 2025-01-XX
**Status**: Clarification Document

