# VersionManagement Coverage Improvement Strategy

## Problem Analysis

**Current Situation**: Despite fixing 5 workflow integration tests, coverage only increased by **0.2%**.

**Root Cause**: Workflow tests exercise code paths already covered by unit tests. We need to target **actual uncovered code**.

## Strategic Approach

### 1. Identify Coverage Gaps First (Don't Guess)

**Action**: Run coverage analysis to find actual uncovered lines/methods:
```bash
composer run test:coverage -- --filter VersionManagement
```

**Focus on**:
- Methods with 0% coverage
- Error/exception paths (usually untested)
- Edge cases (null checks, boundary conditions)
- Private methods (if testable via public API)

### 2. Prioritize by Impact (ROI)

**High Impact** (Do First):
- **VersionService** (276 lines) - Core business logic
  - `performPublishVersion()` (private, 67 lines) - Complex transaction logic
  - `performCreateRollbackVersion()` (private, 40 lines) - Rollback logic
  - `hasUserAccess()` (private, 9 lines) - Access control
  - Error paths in public methods (already partially tested)

- **VersionController** (178 lines) - HTTP layer
  - Error handling paths (lines 71-73, 103-105, 133-135, 165-167)
  - Unauthenticated user paths (lines 38-46, 81-85, 113-117, 143-147)
  - Invalid request paths (lines 49-53, 88-92, 120-124, 150-154)

**Medium Impact**:
- **VersionHooks** (82 lines) - WordPress integration
  - `register()` method (lines 24-32)
  - Hook handler methods (lines 37-80)

- **VersionRequestHandler** (73 lines) - Request parsing
  - Error validation paths
  - Edge cases in parsing

**Low Impact** (Do Last):
- Commands (already well tested)
- Handlers (already well tested)
- Simple getters/setters

### 3. Write Targeted Tests (Not Broad Integration Tests)

**Anti-Pattern** (What We Did):
- ❌ Write 5 workflow integration tests (200+ lines)
- ❌ Test happy paths already covered by unit tests
- ❌ Result: 0.2% coverage increase

**Best Practice** (What We Should Do):
- ✅ Target specific uncovered methods
- ✅ Test error paths and edge cases
- ✅ Use unit tests with mocks (faster, more focused)
- ✅ Result: 5-10% coverage increase per test file

## Quick Wins Strategy

### Priority 1: VersionService Private Methods (2-3 hours, ~15% coverage)

**Target**: `performPublishVersion()`, `performCreateRollbackVersion()`, `hasUserAccess()`

**Missing Coverage** (~40 lines):

#### `performPublishVersion()` - Lines 132-198 (67 lines)
**Current**: Only happy path tested via `publishVersion()`
**Missing**:
- ✅ Line 185-189: `if ($version->geo && ...)` - Geo data update path
- ✅ Line 193-196: `catch (\Exception $e)` - Transaction rollback path
- ✅ Line 143-148: Moving current published version to draft (edge case)

**Quick Win Tests**:
```php
// VersionServiceTest.php
public function test_publishVersion_updates_location_point_when_geo_exists(): void
// Covers: performPublishVersion() lines 185-189

public function test_publishVersion_rolls_back_transaction_on_exception(): void
// Covers: performPublishVersion() lines 193-196

public function test_publishVersion_moves_existing_published_to_draft(): void
// Covers: performPublishVersion() lines 143-148
```

#### `performCreateRollbackVersion()` - Lines 203-242 (40 lines)
**Current**: Happy path tested
**Missing**:
- ✅ Edge cases with different source version types
- ✅ All field copying logic (lines 224-238)

**Quick Win Tests**:
```php
public function test_createRollbackVersion_copies_all_fields_from_source(): void
// Covers: performCreateRollbackVersion() lines 224-238
```

#### `hasUserAccess()` - Lines 265-274 (10 lines)
**Current**: Not directly tested
**Missing**:
- ✅ Line 267-268: Unauthenticated user path
- ✅ Line 271-273: User ID comparison

**Quick Win Tests**:
```php
public function test_getMinisiteForRendering_returns_null_when_user_not_logged_in(): void
// Covers: hasUserAccess() lines 267-268

public function test_getMinisiteForRendering_returns_null_when_user_mismatch(): void
// Covers: hasUserAccess() lines 271-273
```

**Estimated Impact**: +15% coverage, ~40 lines

---

### Priority 2: VersionController Error Paths (2-3 hours, ~10% coverage)

**Target**: All error handling and edge case paths

**Missing Coverage** (~30 lines):

#### `handleListVersions()` - Error paths
**Current**: Unauthenticated and invalid request tested
**Missing**:
- ✅ Line 59-64: `getMinisiteForRendering()` returns null
- ✅ Line 71-73: Exception handling

**Quick Win Tests**:
```php
// VersionControllerTest.php
public function test_handleListVersions_redirects_when_minisite_not_found(): void
// Covers: handleListVersions() lines 59-64

public function test_handleListVersions_redirects_on_exception(): void
// Covers: handleListVersions() lines 71-73
```

#### `handleCreateDraft()` - Error paths
**Current**: Unauthenticated tested
**Missing**:
- ✅ Line 88-92: Invalid request path
- ✅ Line 103-105: Exception handling

**Quick Win Tests**:
```php
public function test_handleCreateDraft_returns_error_on_invalid_request(): void
// Covers: handleCreateDraft() lines 88-92

public function test_handleCreateDraft_returns_error_on_exception(): void
// Covers: handleCreateDraft() lines 103-105
```

#### `handlePublishVersion()` - Error paths
**Current**: Unauthenticated tested
**Missing**:
- ✅ Line 120-124: Invalid request path
- ✅ Line 133-135: Exception handling

**Quick Win Tests**:
```php
public function test_handlePublishVersion_returns_error_on_invalid_request(): void
// Covers: handlePublishVersion() lines 120-124

public function test_handlePublishVersion_returns_error_on_exception(): void
// Covers: handlePublishVersion() lines 133-135
```

#### `handleRollbackVersion()` - Error paths
**Current**: Unauthenticated tested
**Missing**:
- ✅ Line 150-154: Invalid request path
- ✅ Line 165-167: Exception handling

**Quick Win Tests**:
```php
public function test_handleRollbackVersion_returns_error_on_invalid_request(): void
// Covers: handleRollbackVersion() lines 150-154

public function test_handleRollbackVersion_returns_error_on_exception(): void
// Covers: handleRollbackVersion() lines 165-167
```

**Estimated Impact**: +10% coverage, ~30 lines

---

### Priority 3: VersionHooks (1-2 hours, ~5% coverage)

**Target**: `register()` and hook handler methods

**Missing Coverage** (~25 lines):

#### `register()` - Lines 24-32
**Current**: Not tested
**Missing**:
- ✅ All `add_action` calls

**Quick Win Tests**:
```php
// VersionHooksTest.php
public function test_register_adds_all_ajax_actions(): void
// Covers: register() lines 24-32
```

#### Hook handlers - Lines 37-80
**Current**: Method existence tested
**Missing**:
- ✅ Actual controller method calls

**Quick Win Tests**:
```php
public function test_handleVersionHistoryPage_calls_controller_when_route_matches(): void
// Covers: handleVersionHistoryPage() lines 40-47

public function test_handleCreateDraft_calls_controller_method(): void
// Covers: handleCreateDraft() lines 61-64
// Similar for other handlers
```

**Estimated Impact**: +5% coverage, ~25 lines

---

### Priority 4: VersionRequestHandler Edge Cases (1-2 hours, ~5% coverage)

**Target**: Error validation and edge cases

**Missing Coverage** (~15 lines):

**Quick Win Tests**:
- Test invalid input validation
- Test edge cases in parsing
- Test missing required fields

**Estimated Impact**: +5% coverage, ~15 lines

---

## Implementation Strategy

### Step 1: Run Coverage Analysis (5 min)
```bash
composer run test:coverage -- --filter VersionManagement
```
**Goal**: Get actual coverage numbers and identify exact uncovered lines

### Step 2: Write Priority 1 Tests (2-3 hours)
**Target**: VersionService private methods
**Expected**: +15% coverage

### Step 3: Write Priority 2 Tests (2-3 hours)
**Target**: VersionController error paths
**Expected**: +10% coverage

### Step 4: Write Priority 3 & 4 Tests (2-3 hours)
**Target**: VersionHooks and VersionRequestHandler
**Expected**: +10% coverage

### Total Expected Impact
- **Time**: 6-9 hours
- **Coverage Increase**: +35-40%
- **vs. Current**: 0.2% in many hours

## Example: High-Impact Test

Instead of this (low impact):
```php
// Workflow test - tests happy path already covered
public function test_version_lifecycle_from_creation_to_rollback(): void
{
    // 50+ lines testing happy path
    // Result: 0.2% coverage increase
}
```

Do this (high impact):
```php
// Unit test - targets specific uncovered method
public function test_publishVersion_with_geo_data_updates_location_point(): void
{
    // Test that performPublishVersion() updates location_point when geo exists
    // Result: Covers 5 uncovered lines (185-189)
}

public function test_publishVersion_rolls_back_on_exception(): void
{
    // Test transaction rollback in performPublishVersion()
    // Result: Covers 3 uncovered lines (193-196)
}
```

## Key Principles

1. ✅ **Measure first** - Run coverage to find gaps
2. ✅ **Target gaps** - Write tests for uncovered code
3. ✅ **Prioritize impact** - Focus on large/complex methods
4. ✅ **Use unit tests** - Faster, more focused than integration tests
5. ✅ **Test error paths** - Usually the most uncovered code
6. ✅ **Measure after each priority** - Verify actual impact
7. ✅ **Stop when target reached** - Don't over-test

## Anti-Patterns to Avoid

❌ **Don't**: Write broad integration tests for happy paths
❌ **Don't**: Test code already covered by unit tests
❌ **Don't**: Guess what needs testing - measure first
❌ **Don't**: Write tests without measuring impact

## Success Metrics

**Target**: 90% coverage for VersionManagement
**Current**: ~53% (based on migration-summary.md)
**Gap**: ~37%

**With this strategy**:
- Priority 1: +15% → 68%
- Priority 2: +10% → 78%
- Priority 3-4: +10% → 88%
- **Result**: Close to 90% target

## Summary

**Key Principles**:
1. ✅ **Measure first** - Run coverage to find gaps
2. ✅ **Target gaps** - Write tests for uncovered code
3. ✅ **Prioritize impact** - Focus on large/complex methods
4. ✅ **Use unit tests** - Faster, more focused than integration tests
5. ✅ **Test error paths** - Usually the most uncovered code

**Expected Results**:
- Priority 1: +15% coverage (2-3 hours)
- Priority 2: +10% coverage (2-3 hours)
- Priority 3: +5% coverage (1-2 hours)
- Priority 4: +5% coverage (1-2 hours)
- **Total**: +35% coverage in 6-10 hours

**vs. Current Approach**:
- 5 workflow tests: +0.2% coverage (many hours)

