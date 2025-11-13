# `_1_0_0_CreateBase.php` Status Report

## Current State (After Phase 3 Completion)

### ✅ **Already Migrated to Doctrine Migrations**

The following table creations have been **completely moved** to Doctrine migrations:

1. **minisite_reviews** → `Version20251104000000.php` ✅
2. **minisite_versions** → `Version20251105000000.php` ✅
3. **minisites** → `Version20251106000000.php` ✅
4. **minisite_bookmarks** → `Version20251107000000.php` ✅
5. **minisite_payments** → `Version20251108000000.php` ✅
6. **minisite_payment_history** → `Version20251109000000.php` ✅
7. **minisite_reservations** → `Version20251110000000.php` ✅
8. **MySQL event (purge_reservations)** → `Version20251110000000.php` ✅

All foreign keys have also been moved to Doctrine migrations.

### ⚠️ **Still in `_1_0_0_CreateBase.php`**

#### 1. **Minisites Table Creation** (Line 48-51)
```php
SqlLoader::loadAndExecute(
    'minisites.sql',
    SqlLoader::createStandardVariables($wpdb)
);
```
**Status**: ⚠️ **DUPLICATE** - This table is already created by `Version20251106000000.php`
**Action Needed**: Remove this SQL file loading since Doctrine migration handles it

#### 2. **Test Data Seeding** (`seedTestData()` method, Line 508-904)
**What it does**:
- Inserts 4 test minisites using `insertMinisite()` (direct SQL via `$wpdb`)
- Inserts versions using direct SQL via `$wpdb` (lines 564-675)
- Uses `ReviewSeederService` for reviews (lines 677-719) ✅ **Already using seeder!**

**Status**: ⚠️ **PARTIALLY MIGRATED**
- ✅ Reviews: Already using `ReviewSeederService`
- ❌ Minisites: Still using direct SQL (`insertMinisite()`)
- ❌ Versions: Still using direct SQL (should use `VersionSeederService`)

#### 3. **Helper Methods** (Still needed for seeding, but should be in seeder service)
- `loadMinisiteFromJson()` (Line 281-316) - Loads JSON from `data/json/minisites/`
- `convertLocationFormat()` (Line 321-335) - Converts location format
- `setComputedFields()` (Line 340-361) - Sets computed/audit fields
- `insertMinisite()` (Line 366-423) - Direct SQL insertion via `$wpdb`

**Status**: ⚠️ **Should be moved to MinisiteSeederService**

#### 4. **Unused Methods** (Can be removed)
- `addForeignKeyIfNotExists()` (Line 254-276) - No longer needed (Doctrine migrations handle this)
- `insertReview()` (Line 437-467) - Already commented out, uses ReviewSeederService

## What's Pending

### Phase 4: Create MinisiteSeederService (NOT STARTED)

**Goal**: Move test data seeding from `_1_0_0_CreateBase.php` to dedicated seeder service

**Tasks**:

1. **Create `MinisiteSeederService`**:
   - Location: `src/Features/MinisiteManagement/Services/MinisiteSeederService.php`
   - Pattern: Follow `ReviewSeederService` and `VersionSeederService`
   - Inject: `MinisiteRepository` and `VersionRepository`
   - Methods needed:
     - `loadMinisiteFromJson(string $jsonFile): array` - Load from JSON
     - `createMinisiteFromJsonData(array $minisiteData): Minisite` - Create entity from JSON
     - `seedAllTestMinisites(): array` - Seed all 4 test minisites, returns minisite IDs
     - Helper methods: `convertLocationFormat()`, `setComputedFields()`

2. **Update `seedTestData()` in `_1_0_0_CreateBase.php`**:
   - Replace `insertMinisite()` calls with `MinisiteSeederService`
   - Replace direct version SQL insertion with `VersionSeederService`
   - Keep `ReviewSeederService` call (already working)
   - Simplify to orchestrate seeders only

3. **Remove helper methods from `_1_0_0_CreateBase.php`**:
   - Move `loadMinisiteFromJson()` to `MinisiteSeederService`
   - Move `convertLocationFormat()` to `MinisiteSeederService`
   - Move `setComputedFields()` to `MinisiteSeederService`
   - Remove `insertMinisite()` (replaced by repository)
   - Remove `addForeignKeyIfNotExists()` (no longer needed)

4. **Remove duplicate minisites table creation**:
   - Remove `SqlLoader::loadAndExecute('minisites.sql', ...)` call
   - Table is already created by `Version20251106000000.php`

## Current File Breakdown

### What `_1_0_0_CreateBase.php` Currently Does:

1. **Table Creation** (Line 48-51):
   - ❌ Creates `minisites` table via SQL file (DUPLICATE - already in Doctrine migration)

2. **Test Data Seeding** (Line 508-904):
   - ✅ Reviews: Uses `ReviewSeederService` (already migrated)
   - ❌ Minisites: Uses direct SQL `insertMinisite()` (needs MinisiteSeederService)
   - ❌ Versions: Uses direct SQL `db::insert()` (should use VersionSeederService)

3. **Helper Methods** (Lines 251-423):
   - `addForeignKeyIfNotExists()` - No longer needed
   - `loadMinisiteFromJson()` - Should move to MinisiteSeederService
   - `convertLocationFormat()` - Should move to MinisiteSeederService
   - `setComputedFields()` - Should move to MinisiteSeederService
   - `insertMinisite()` - Should be replaced with MinisiteRepository

## Migration Status Summary

| Component                      | Status        | Location                                                |
| ------------------------------ | ------------- | ------------------------------------------------------- |
| minisites table                | ✅ Migrated    | `Version20251106000000.php`                             |
| minisite_reviews table         | ✅ Migrated    | `Version20251104000000.php`                             |
| minisite_versions table        | ✅ Migrated    | `Version20251105000000.php`                             |
| minisite_bookmarks table       | ✅ Migrated    | `Version20251107000000.php`                             |
| minisite_payments table        | ✅ Migrated    | `Version20251108000000.php`                             |
| minisite_payment_history table | ✅ Migrated    | `Version20251109000000.php`                             |
| minisite_reservations table    | ✅ Migrated    | `Version20251110000000.php`                             |
| purge_reservations event       | ✅ Migrated    | `Version20251110000000.php`                             |
| All foreign keys               | ✅ Migrated    | In respective Doctrine migrations                       |
| Review seeding                 | ✅ Migrated    | `ReviewSeederService`                                   |
| Minisite seeding               | ❌ **PENDING** | Still in `_1_0_0_CreateBase.php`                        |
| Version seeding                | ⚠️ **PARTIAL** | `VersionSeederService` exists but not used in migration |

## Next Steps (Phase 4)

1. **Create `MinisiteSeederService`**:
   - Follow pattern from `ReviewSeederService`
   - Use `MinisiteRepository` for persistence
   - Handle location_point via repository (raw SQL)

2. **Update `seedTestData()`**:
   - Use `MinisiteSeederService::seedAllTestMinisites()` → returns minisite IDs
   - Use `VersionSeederService::seedAllTestVersions($minisiteIds)`
   - Keep `ReviewSeederService::seedAllTestReviews($minisiteIds)` (already working)

3. **Remove duplicate table creation**:
   - Remove `SqlLoader::loadAndExecute('minisites.sql', ...)`
   - Add comment noting table is created by Doctrine migration

4. **Clean up helper methods**:
   - Move JSON loading/formatting to seeder service
   - Remove direct SQL insertion methods

## Estimated Remaining Work

- **MinisiteSeederService creation**: 2-3 hours
- **Update seedTestData()**: 1 hour
- **Remove duplicate code**: 30 minutes
- **Testing**: 1 hour
- **Total**: ~4-5 hours

## Blockers to Removing `_1_0_0_CreateBase.php`

Once Phase 4 is complete, `_1_0_0_CreateBase.php` can potentially be:
- **Simplified** to only call seeder services (if still needed for activation)
- **Or removed entirely** if activation handlers call seeders directly

**Current blockers**:
1. ❌ No `MinisiteSeederService` exists yet
2. ⚠️ `seedTestData()` still uses direct SQL for minisites and versions
3. ⚠️ Activation/deactivation handlers may still reference this migration

