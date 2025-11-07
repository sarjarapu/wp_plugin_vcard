# Error Pattern Analysis - Test Fixes

**Date**: 2025-11-06
**Context**: Analyzing test failures after retiring legacy VersionRepository

## Error Patterns Identified

### 🟢 Pattern 1: Tests Testing Dropped WordPressManager Methods (DELETE THESE)

**Issue**: Tests are checking for methods that were removed from `WordPressManagerInterface`

**Files to DELETE**:
1. `tests/Unit/Features/MinisiteViewer/WordPress/WordPressMinisiteManagerTest.php`
   - Tests `findMinisiteBySlugs()` - **REMOVED** (moved to repository)
   - Tests `minisiteExists()` - **REMOVED** (moved to repository)
   - **Action**: Delete entire test file (methods don't exist anymore)

**Impact**: ~10-15 errors removed
**Effort**: 2 minutes (just delete the file)

---

### 🟡 Pattern 2: EditServiceTest Using Old WordPressManager Methods (UPDATE)

**Issue**: `EditServiceTest` expects methods on `WordPressManager` that are now on repositories

**Methods to Update**:
- `findMinisiteById()` → Use `$mockMinisiteRepository->findById()`
- `getLatestDraftForEditing()` → Use `$mockVersionRepository->getLatestDraftForEditing()`
- `findLatestDraft()` → Use `$mockVersionRepository->findLatestDraft()`
- `getNextVersionNumber()` → Use `$mockVersionRepository->getNextVersionNumber()`
- `saveVersion()` → Use `$mockVersionRepository->save()`
- `hasBeenPublished()` → Check `$mockVersionRepository->findPublishedVersion() !== null`
- `startTransaction()` → Use `$mockTransactionManager->startTransaction()` (if needed)
- `commitTransaction()` → Use `$mockTransactionManager->commitTransaction()` (if needed)
- `rollbackTransaction()` → Use `$mockTransactionManager->rollbackTransaction()` (if needed)

**File**: `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php`

**Impact**: ~10-15 errors
**Effort**: 1-2 hours (systematic update of all test methods)

---

### 🟡 Pattern 3: Integration Tests - Missing Version Table (SKIP OR FIX)

**Issue**: Integration tests may be failing because `wp_minisite_versions` table doesn't exist in test database

**Files to Check**:
- `tests/Integration/Infrastructure/Persistence/Repositories/VersionRepositoryIntegrationTest.php` (if exists)
- Any integration test that uses version table

**Options**:
1. **Skip tests** if table setup is complex
2. **Fix tests** to create table via Doctrine migrations
3. **Mark as TODO** for later

**Impact**: Unknown (need to check which integration tests exist)
**Effort**: 30 minutes to identify, 1-2 hours to fix

---

### 🟡 Pattern 4: VersionServiceTest - May Need Updates

**Issue**: `VersionServiceTest` may be using old repository patterns

**File**: `tests/Unit/Features/VersionManagement/Services/VersionServiceTest.php`

**Impact**: ~5-10 errors
**Effort**: 30-60 minutes

---

### 🟡 Pattern 5: MinisiteRepositoryTest - VersionRepository Reference

**Issue**: `MinisiteRepositoryTest` may import/use old `VersionRepository` class

**File**: `tests/Unit/Infrastructure/Persistence/Repositories/MinisiteRepositoryTest.php`

**Impact**: ~5-10 errors
**Effort**: 15-30 minutes (just update imports/references)

---

## Recommended Fix Order (Lowest Hanging Fruit First)

### 1. DELETE WordPressMinisiteManagerTest (2 minutes) ⭐ EASIEST
- **File**: `tests/Unit/Features/MinisiteViewer/WordPress/WordPressMinisiteManagerTest.php`
- **Reason**: Tests methods that no longer exist
- **Impact**: ~10-15 errors removed instantly
- **Risk**: None (dead code)

### 2. Fix MinisiteRepositoryTest References (15-30 minutes) ⭐ EASY
- **File**: `tests/Unit/Infrastructure/Persistence/Repositories/MinisiteRepositoryTest.php`
- **Action**: Update imports/references from old `VersionRepository` to `VersionRepositoryInterface`
- **Impact**: ~5-10 errors
- **Risk**: Low

### 3. Fix VersionServiceTest (30-60 minutes) ⭐ MEDIUM
- **File**: `tests/Unit/Features/VersionManagement/Services/VersionServiceTest.php`
- **Action**: Update to use repository mocks instead of WordPressManager
- **Impact**: ~5-10 errors
- **Risk**: Medium

### 4. Fix EditServiceTest Repository Mocks (1-2 hours) ⭐ MEDIUM-HARD
- **File**: `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php`
- **Action**: Systematic update of all test methods to use repository mocks
- **Impact**: ~10-15 errors
- **Risk**: Medium (more complex, but straightforward pattern)

### 5. Fix Integration Tests (1-2 hours) ⭐ HARD
- **Files**: Integration test files
- **Action**: Either skip, fix table setup, or mark as TODO
- **Impact**: Unknown
- **Risk**: Medium-High (depends on test setup complexity)

---

## Quick Win Summary

**Total Quick Wins** (Steps 1-2): ~15-25 errors in ~20-30 minutes
**Medium Effort** (Steps 3-4): ~15-25 errors in ~2-3 hours
**Hard** (Step 5): Unknown errors in ~1-2 hours

**Estimated Total**: 3-5 hours to fix all remaining errors

---

## Action Plan

1. ✅ **Delete** `WordPressMinisiteManagerTest.php` (immediate)
2. ✅ **Update** `MinisiteRepositoryTest.php` imports (quick)
3. ⏳ **Update** `VersionServiceTest.php` (medium)
4. ⏳ **Update** `EditServiceTest.php` (medium-hard)
5. ⏳ **Review** integration tests (hard)

