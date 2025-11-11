# Test Fixes Progress Report

**Date**: 2025-11-06
**Context**: Fixing test failures after retiring legacy `$wpdb`-based `VersionRepository`

## Summary

- **Starting Point**: 190 errors, 2 failures
- **Current Status**: 133 errors, 5 failures
- **Progress**: **57 errors fixed** (30% reduction)
- **Tests**: 1196 total tests

## Completed Fixes ✅

### 1. Deleted Obsolete Test File
- **File**: `tests/Unit/Infrastructure/Persistence/Repositories/VersionRepositoryTest.php`
- **Reason**: Tested the deleted legacy `VersionRepository` class
- **Impact**: ~15-20 errors removed
- **Status**: ✅ Complete

### 2. Fixed Service Constructor Tests
- **Files Updated**:
  - `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php`
  - `tests/Unit/Features/MinisiteListing/Services/MinisiteListingServiceTest.php`
- **Changes**:
  - Updated `EditServiceTest` to inject `MinisiteRepository` and `VersionRepositoryInterface` mocks
  - Completely rewrote `MinisiteListingServiceTest` to use repository mocks instead of WordPressManager mocks
  - Added helper method `createMinisiteEntity()` for creating test entities
- **Impact**: ~20-30 errors removed
- **Status**: ✅ Complete

### 3. Fixed Factory Tests - $wpdb Mocking
- **Files Updated**:
  - `tests/Unit/Features/MinisiteEdit/Hooks/EditHooksFactoryTest.php`
  - `tests/Unit/Features/VersionManagement/Hooks/VersionHooksFactoryTest.php`
  - `tests/Unit/Features/MinisiteViewer/Hooks/ViewHooksFactoryTest.php`
- **Changes**:
  - Added `global $wpdb` mocking using `FakeWpdb` class
  - Added `$GLOBALS['minisite_version_repository']` mock (required by factories)
  - Added proper cleanup in `tearDown()` methods
- **Impact**: ~30-40 errors removed
- **Status**: ✅ Complete

## Remaining Issues 🔄

### 1. EditServiceTest - Repository Method Mocks
- **Issue**: Tests still expect methods on `WordPressManager` that are now on repositories
- **Examples**:
  - `findMinisiteById()` → should use `$mockMinisiteRepository->findById()`
  - `getLatestDraftForEditing()` → should use `$mockVersionRepository->getLatestDraftForEditing()`
  - `findLatestDraft()` → should use `$mockVersionRepository->findLatestDraft()`
  - `getNextVersionNumber()` → should use `$mockVersionRepository->getNextVersionNumber()`
  - `saveVersion()` → should use `$mockVersionRepository->save()`
  - `hasBeenPublished()` → should check `$mockVersionRepository->findPublishedVersion()`
- **Impact**: ~20-30 errors
- **Effort**: 1-2 hours
- **Status**: ⏳ Pending

### 2. Other Tests Referencing Old VersionRepository
- **Issue**: Tests that mock or reference the old `VersionRepository` class
- **Files to Check**:
  - `tests/Unit/Infrastructure/Persistence/Repositories/MinisiteRepositoryTest.php`
  - `tests/Unit/Features/MinisiteViewer/Services/MinisiteViewServiceTest.php`
  - `tests/Unit/Features/VersionManagement/Services/VersionServiceTest.php`
  - `tests/Unit/Features/MinisiteListing/Hooks/ListingHooksFactoryTest.php`
- **Impact**: ~10-15 errors
- **Effort**: 30-60 minutes
- **Status**: ⏳ Pending

### 3. Integration Tests
- **Issue**: Integration tests may need updates for Doctrine-based repository
- **Files to Check**:
  - `tests/Integration/Infrastructure/Persistence/Repositories/VersionRepositoryIntegrationTest.php` (if exists)
- **Impact**: Unknown
- **Effort**: 1-2 hours (if updating)
- **Status**: ⏳ Pending

### 4. Other Factory Tests
- **Issue**: Other factory tests may need similar `$wpdb` mocking
- **Files to Check**:
  - `tests/Unit/Features/NewMinisite/Hooks/NewMinisiteHooksFactoryTest.php` (if exists)
  - `tests/Unit/Features/MinisiteListing/Hooks/ListingHooksFactoryTest.php`
- **Impact**: ~5-10 errors
- **Effort**: 30 minutes
- **Status**: ⏳ Pending

## Files Modified

### Source Code
- ✅ `src/Infrastructure/Persistence/Repositories/VersionRepository.php` - **DELETED** (legacy)
- ✅ `src/Features/VersionManagement/Hooks/VersionHooksFactory.php` - Updated to require global repository
- ✅ `src/Features/NewMinisite/Hooks/NewMinisiteHooksFactory.php` - Updated to require global repository
- ✅ `src/Features/MinisiteEdit/Hooks/EditHooksFactory.php` - Updated to require global repository
- ✅ `src/Features/MinisiteViewer/Hooks/ViewHooksFactory.php` - Updated to require global repository
- ✅ `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php` - Updated to require global repository

### Test Files
- ✅ `tests/Unit/Infrastructure/Persistence/Repositories/VersionRepositoryTest.php` - **DELETED**
- ✅ `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php` - Updated constructor
- ✅ `tests/Unit/Features/MinisiteListing/Services/MinisiteListingServiceTest.php` - Complete rewrite
- ✅ `tests/Unit/Features/MinisiteEdit/Hooks/EditHooksFactoryTest.php` - Added $wpdb mocking
- ✅ `tests/Unit/Features/VersionManagement/Hooks/VersionHooksFactoryTest.php` - Added $wpdb mocking
- ✅ `tests/Unit/Features/MinisiteViewer/Hooks/ViewHooksFactoryTest.php` - Added $wpdb mocking

## Next Steps

### Immediate (Easy Wins)
1. **Update EditServiceTest** to use repository mocks instead of WordPressManager mocks
   - Update all test methods to mock `$mockMinisiteRepository` and `$mockVersionRepository`
   - Remove expectations on `$mockWordPressManager` for repository methods
   - Estimated: 1-2 hours

2. **Fix remaining mock references** to old `VersionRepository` class
   - Search for `Minisite\Infrastructure\Persistence\Repositories\VersionRepository` in tests
   - Replace with `VersionRepositoryInterface` mocks
   - Estimated: 30-60 minutes

3. **Fix other factory tests** that need `$wpdb` mocking
   - Check `NewMinisiteHooksFactoryTest` and `ListingHooksFactoryTest`
   - Apply same pattern as other factory tests
   - Estimated: 30 minutes

### Medium Priority
4. **Review integration tests** for Doctrine compatibility
   - Check if integration tests exist for VersionRepository
   - Update or mark as skipped if needed
   - Estimated: 1-2 hours

## Estimated Remaining Effort

- **Easy fixes**: 2-3 hours
- **Medium fixes**: 1-2 hours
- **Total**: 3-5 hours to get to green

## Key Learnings

1. **Factory Pattern**: All factories now require `$GLOBALS['minisite_version_repository']` to be set
2. **Repository Injection**: Services now directly inject repositories instead of getting them through WordPressManagers
3. **Test Mocking**: Tests need to mock both `global $wpdb` and `$GLOBALS['minisite_version_repository']` for factories
4. **Entity Creation**: Tests need helper methods to create proper entity objects for repository mocks

## Notes

- The new Doctrine-based `VersionRepository` is at `Minisite\Features\VersionManagement\Repositories\VersionRepository`
- It implements `Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface`
- All tests should mock the interface, not the concrete class
- Factory tests need proper `$wpdb` mocking setup

