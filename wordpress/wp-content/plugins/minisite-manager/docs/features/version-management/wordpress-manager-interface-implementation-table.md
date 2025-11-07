# WordPressManagerInterface Implementation Analysis Table

## All Classes Implementing WordPressManagerInterface

1. `WordPressEditManager` (MinisiteEdit)
2. `WordPressVersionManager` (VersionManagement)
3. `WordPressPublishManager` (PublishMinisite)
4. `WordPressNewMinisiteManager` (NewMinisite)
5. `WordPressUserManager` (Authentication)

---

## Method Implementation Status Table

| Method | WordPress-Related? | Active Usage? | WordPressEditManager | WordPressVersionManager | WordPressPublishManager | WordPressNewMinisiteManager | WordPressUserManager | Recommendation |
|--------|-------------------|---------------|---------------------|------------------------|------------------------|----------------------------|---------------------|----------------|
| `getCurrentUser()` | ✅ YES | ✅ YES (many services) | ✅ Active | ✅ Active | ✅ Active | ✅ Active | ✅ Active | ✅ **KEEP** |
| `findMinisiteById()` | ❌ NO | ⚠️ YES (MinisiteFormProcessor) | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ⚠️ **REFACTOR** |
| `getNextVersionNumber()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ✅ **REMOVE** |
| `saveVersion()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ✅ **REMOVE** |
| `hasBeenPublished()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ✅ Active* | ❌ Stub | ✅ **REMOVE** |
| `updateBusinessInfo()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ✅ **REMOVE** |
| `updateCoordinates()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ✅ **REMOVE** |
| `updateTitle()` | ❌ NO | ❌ NO | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ✅ **REMOVE** |
| `updateMinisiteFields()` | ❌ NO | ⚠️ YES (MinisiteDatabaseCoordinator) | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ⚠️ **REFACTOR** |
| `startTransaction()` | ❌ NO | ⚠️ YES (MinisiteDatabaseCoordinator) | ✅ Active | ✅ Active | ✅ Active | ✅ Active | ❌ Stub | ⚠️ **REFACTOR** |
| `commitTransaction()` | ❌ NO | ⚠️ YES (MinisiteDatabaseCoordinator) | ✅ Active | ✅ Active | ✅ Active | ✅ Active | ❌ Stub | ⚠️ **REFACTOR** |
| `rollbackTransaction()` | ❌ NO | ⚠️ YES (MinisiteDatabaseCoordinator) | ✅ Active | ✅ Active | ✅ Active | ✅ Active | ❌ Stub | ⚠️ **REFACTOR** |
| `getMinisiteRepository()` | ❌ NO | ⚠️ YES (multiple services) | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ❌ Stub | ⚠️ **REFACTOR** |

**Legend:**
- ✅ Active = Real implementation, actually used
- ❌ Stub = Dead code, not used, just for interface compliance
- ⚠️ YES = Still used but shouldn't be (needs refactoring)
- ✅ Active* = Has implementation but not called

---

## Detailed Implementation Status

### WordPressEditManager

| Method | Status | Notes |
|--------|--------|-------|
| `getCurrentUser()` | ✅ Active | `return wp_get_current_user();` |
| `findMinisiteById()` | ❌ Stub | Returns `null`, marked "Not used" |
| `getNextVersionNumber()` | ❌ Stub | Returns `1`, marked "Not used" |
| `saveVersion()` | ❌ Stub | Returns version as-is, marked "Not used" |
| `hasBeenPublished()` | ❌ Stub | Returns `false`, marked "Not used" |
| `updateBusinessInfo()` | ❌ Stub | No-op, marked "Not used" |
| `updateCoordinates()` | ❌ Stub | No-op, marked "Not used" |
| `updateTitle()` | ❌ Stub | No-op, marked "Not used" |
| `updateMinisiteFields()` | ❌ Stub | No-op, marked "Not used" |
| `startTransaction()` | ✅ Active | `db::query('START TRANSACTION');` |
| `commitTransaction()` | ✅ Active | `db::query('COMMIT');` |
| `rollbackTransaction()` | ✅ Active | `db::query('ROLLBACK');` |
| `getMinisiteRepository()` | ❌ Stub | Creates new instance, marked "Not used" |

---

### WordPressVersionManager

| Method | Status | Notes |
|--------|--------|-------|
| `getCurrentUser()` | ✅ Active | `return wp_get_current_user();` |
| `findMinisiteById()` | ❌ Stub | Returns `null`, marked "Not used" |
| `getNextVersionNumber()` | ❌ Stub | Returns `1`, marked "Not used" |
| `saveVersion()` | ❌ Stub | Returns version as-is, marked "Not used" |
| `hasBeenPublished()` | ❌ Stub | Returns `false`, marked "Not used" |
| `updateBusinessInfo()` | ❌ Stub | No-op, marked "Not used" |
| `updateCoordinates()` | ❌ Stub | No-op, marked "Not used" |
| `updateTitle()` | ❌ Stub | No-op, marked "Not used" |
| `updateMinisiteFields()` | ❌ Stub | No-op, marked "Not used" |
| `startTransaction()` | ✅ Active | `$wpdb->query('START TRANSACTION');` |
| `commitTransaction()` | ✅ Active | `$wpdb->query('COMMIT');` |
| `rollbackTransaction()` | ✅ Active | `$wpdb->query('ROLLBACK');` |
| `getMinisiteRepository()` | ❌ Stub | Creates new instance, marked "Not used" |

---

### WordPressPublishManager

| Method | Status | Notes |
|--------|--------|-------|
| `getCurrentUser()` | ✅ Active | `return wp_get_current_user();` |
| `findMinisiteById()` | ❌ Stub | Returns `null`, marked "Not used" |
| `getNextVersionNumber()` | ❌ Stub | Returns `1`, marked "Not used" |
| `saveVersion()` | ❌ Stub | Returns version as-is, marked "Not used" |
| `hasBeenPublished()` | ❌ Stub | Returns `false`, marked "Not used" |
| `updateBusinessInfo()` | ❌ Stub | No-op, marked "Not used" |
| `updateCoordinates()` | ❌ Stub | No-op, marked "Not used" |
| `updateTitle()` | ❌ Stub | No-op, marked "Not used" |
| `updateMinisiteFields()` | ❌ Stub | No-op, marked "Not used" |
| `startTransaction()` | ✅ Active | `db::query('START TRANSACTION');` |
| `commitTransaction()` | ✅ Active | `db::query('COMMIT');` |
| `rollbackTransaction()` | ✅ Active | `db::query('ROLLBACK');` |
| `getMinisiteRepository()` | ❌ Stub | Creates new instance, marked "Not used" |

---

### WordPressNewMinisiteManager

| Method | Status | Notes |
|--------|--------|-------|
| `getCurrentUser()` | ✅ Active | `return wp_get_current_user();` |
| `findMinisiteById()` | ❌ Stub | Returns `null`, marked "Not used" |
| `getNextVersionNumber()` | ❌ Stub | Returns `1`, marked "Not used" |
| `saveVersion()` | ❌ Stub | Returns version as-is, marked "Not used" |
| `hasBeenPublished()` | ✅ Active* | Returns `false` (correct for new minisites), but **not called** |
| `updateBusinessInfo()` | ❌ Stub | No-op, marked "Not used" |
| `updateCoordinates()` | ❌ Stub | No-op, marked "Not used" |
| `updateTitle()` | ❌ Stub | No-op, marked "Not used" |
| `updateMinisiteFields()` | ❌ Stub | No-op, marked "Not used" |
| `startTransaction()` | ✅ Active | `db::query('START TRANSACTION');` |
| `commitTransaction()` | ✅ Active | `db::query('COMMIT');` |
| `rollbackTransaction()` | ✅ Active | `db::query('ROLLBACK');` |
| `getMinisiteRepository()` | ❌ Stub | Creates new instance, marked "Not used" |

---

### WordPressUserManager

| Method | Status | Notes |
|--------|--------|-------|
| `getCurrentUser()` | ✅ Active | `return wp_get_current_user();` |
| `findMinisiteById()` | ❌ Stub | Returns `null`, marked "not applicable" |
| `getNextVersionNumber()` | ❌ Stub | Returns `1`, marked "not applicable" |
| `saveVersion()` | ❌ Stub | Returns version as-is, marked "not applicable" |
| `hasBeenPublished()` | ❌ Stub | Returns `false`, marked "not applicable" |
| `updateBusinessInfo()` | ❌ Stub | No-op, marked "not applicable" |
| `updateCoordinates()` | ❌ Stub | No-op, marked "not applicable" |
| `updateTitle()` | ❌ Stub | No-op, marked "not applicable" |
| `updateMinisiteFields()` | ❌ Stub | No-op, marked "not applicable" |
| `startTransaction()` | ❌ Stub | No-op, marked "not applicable" |
| `commitTransaction()` | ❌ Stub | No-op, marked "not applicable" |
| `rollbackTransaction()` | ❌ Stub | No-op, marked "not applicable" |
| `getMinisiteRepository()` | ❌ Stub | Returns `new \stdClass()`, marked "not applicable" |

---

## Summary Statistics

### Dead Code (Can Remove Immediately):
- **6 methods** with no active usage:
  1. `saveVersion()` - 5 stub implementations
  2. `hasBeenPublished()` - 4 stubs + 1 unused active implementation
  3. `getNextVersionNumber()` - 5 stub implementations
  4. `updateBusinessInfo()` - 5 stub implementations
  5. `updateCoordinates()` - 5 stub implementations
  6. `updateTitle()` - 5 stub implementations

**Total dead code:** 30 stub method implementations across 5 classes

### Needs Refactoring (Still Used):
- **6 methods** still used but shouldn't be in WordPress manager:
  1. `findMinisiteById()` - Used in 1 place
  2. `updateMinisiteFields()` - Used in 1 place
  3. `startTransaction()` - Used in 2 places
  4. `commitTransaction()` - Used in 2 places
  5. `rollbackTransaction()` - Used in 2 places
  6. `getMinisiteRepository()` - Used in 4 places

### Legitimate WordPress Methods (Keep):
- **1 method** that is actually WordPress-related:
  1. `getCurrentUser()` - Used in 10+ places, legitimate WordPress wrapper

---

## Action Items

### Immediate (Remove Dead Code):
1. Remove 6 methods from `WordPressManagerInterface`
2. Remove 30 stub implementations from 5 WordPress manager classes
3. **Result:** Cleaner interface, less dead code

### Next Phase (Refactor Remaining):
1. Refactor 6 methods to use proper dependency injection
2. **Result:** Clean separation of concerns, WordPress managers only handle WordPress functions

