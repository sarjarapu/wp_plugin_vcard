# Test Failures Analysis After Legacy Repository Retirement

**Date**: 2025-11-06
**Context**: After removing legacy `$wpdb`-based `VersionRepository`

## Summary
- **Total Tests**: 1219
- **Errors**: 190
- **Failures**: 2
- **Warnings**: 3
- **Skipped**: 14

## Error Categories

### 🟢 EASY FIXES (Quick wins)

#### 1. **VersionRepositoryTest - Class Not Found** (Multiple errors)
- **Issue**: Test file `tests/Unit/Infrastructure/Persistence/Repositories/VersionRepositoryTest.php` references the deleted legacy `VersionRepository` class
- **Fix**: Delete this test file (it was testing the old `$wpdb` implementation) OR update it to test the new Doctrine-based repository
- **Impact**: ~15-20 errors
- **Effort**: 5 minutes

#### 2. **Service Constructor Signature Mismatches** (Multiple errors)
- **Issue**: Tests are passing old number of arguments to service constructors
- **Examples**:
  - `EditService` now requires 3 args (WordPressManager, MinisiteRepository, VersionRepository) but tests pass 1
  - `MinisiteListingService` now requires 2 args (MinisiteRepository, WordPressManager) but tests pass 1
- **Fix**: Update test files to pass correct dependencies (mock them)
- **Files**:
  - `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php`
  - `tests/Unit/Features/MinisiteListing/Services/MinisiteListingServiceTest.php`
- **Impact**: ~20-30 errors
- **Effort**: 30-45 minutes

#### 3. **Factory Tests - Missing $wpdb Global** (Multiple errors)
- **Issue**: Factory tests fail because `global $wpdb` is null in test environment
- **Examples**:
  - `EditHooksFactoryTest` - all 10 tests failing
  - Similar issues in other factory tests
- **Fix**: Mock `$wpdb` in test setup or use `Tests\Support\FakeWpdb`
- **Files**:
  - `tests/Unit/Features/MinisiteEdit/Hooks/EditHooksFactoryTest.php`
  - `tests/Unit/Features/NewMinisite/Hooks/NewMinisiteHooksFactoryTest.php` (likely)
  - `tests/Unit/Features/MinisiteViewer/Hooks/ViewHooksFactoryTest.php` (likely)
- **Impact**: ~30-40 errors
- **Effort**: 1-2 hours

### 🟡 MEDIUM FIXES (Require understanding)

#### 4. **VersionRepository Mock References in Tests**
- **Issue**: Tests that mock `VersionRepository` are using the old namespace
- **Example**: `tests/Unit/Features/MinisiteViewer/Services/MinisiteViewServiceTest.php` creates mocks of old `VersionRepository`
- **Fix**: Update mocks to use `VersionRepositoryInterface` instead
- **Impact**: ~10-15 errors
- **Effort**: 1 hour

#### 5. **MinisiteRepositoryTest - VersionRepository Dependency**
- **Issue**: `tests/Unit/Infrastructure/Persistence/Repositories/MinisiteRepositoryTest.php` imports old `VersionRepository`
- **Fix**: Update to use `VersionRepositoryInterface` or remove dependency if not needed
- **Impact**: ~5-10 errors
- **Effort**: 30 minutes

### 🔴 HARD FIXES (Require architectural decisions)

#### 6. **Integration Tests for VersionRepository**
- **Issue**: Integration tests likely test the old `$wpdb` implementation
- **Fix**: Either:
  - Delete them (if we have new Doctrine-based integration tests)
  - Update them to test the new Doctrine repository
  - Mark as skipped with TODO
- **Files**:
  - `tests/Integration/Infrastructure/Persistence/Repositories/VersionRepositoryIntegrationTest.php` (if exists)
- **Impact**: Unknown
- **Effort**: 2-4 hours (if updating)

## Recommended Fix Order

1. **Delete obsolete VersionRepositoryTest** (5 min) → Removes ~15-20 errors
2. **Fix service constructor tests** (30-45 min) → Removes ~20-30 errors
3. **Fix factory tests with $wpdb mocks** (1-2 hours) → Removes ~30-40 errors
4. **Update mock references** (1 hour) → Removes ~10-15 errors
5. **Handle integration tests** (2-4 hours) → Decision needed

**Total Estimated Effort**: 4-8 hours to get to green

## Notes

- The new Doctrine-based `VersionRepository` is at `Minisite\Features\VersionManagement\Repositories\VersionRepository`
- It implements `Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface`
- Tests should mock the interface, not the concrete class
- Factory tests need proper `$wpdb` mocking setup

