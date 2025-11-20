# MinisiteViewer Feature Test Coverage Improvement Plan

## Overview

This document outlines a comprehensive plan to improve test coverage for the MinisiteViewer feature from the current ~55% overall coverage to above 90% as specified in MIN-27.

**Current Coverage Status:**
- Overall: 55.35% lines, 51.87% methods
- MinisiteViewer specific classes vary from 0% to 100%

## Coverage Analysis by Class

### 1. ViewRequestHandler (20% methods, 7.14% lines) ⚠️ CRITICAL

**Current State:**
- Only basic reflection tests exist
- No actual functional tests for request handling
- Missing tests for all public methods

**Missing Coverage:**
- `handleViewRequest()` - success cases with valid slugs
- `handleViewRequest()` - failure cases (missing slugs, null values)
- `getBusinessSlug()` - with valid/invalid query vars
- `getLocationSlug()` - with valid/invalid query vars
- `sanitizeSlug()` - private method (test via public methods)
- Edge cases: empty strings, special characters, null values

**Action Plan:**
1. **Unit Tests (Priority: HIGH)**
   - Add tests for `handleViewRequest()` with mocked WordPressManager
   - Test `getBusinessSlug()` and `getLocationSlug()` methods
   - Test sanitization logic through public methods
   - Test edge cases (null, empty strings, special characters)
   - Mock `WordPressMinisiteManager::getQueryVar()` and `sanitizeTextField()`

2. **Refactoring Needed:**
   - None - class is already well-structured for testing

3. **Mocking Strategy:**
   - Mock `WordPressMinisiteManager` to return various query var values
   - Test sanitization by verifying `sanitizeTextField()` is called

**Expected Coverage After:** 100% methods, 95%+ lines

---

### 2. ViewRenderer (45.45% methods, 56% lines) ⚠️ HIGH PRIORITY

**Current State:**
- Basic rendering tests exist
- Missing version-specific preview tests
- Missing fallback rendering edge cases
- Missing Timber integration edge cases

**Missing Coverage:**
- `renderVersionSpecificPreview()` - all scenarios
- `renderVersionSpecificPreview()` - with/without Timber
- `renderVersionSpecificPreview()` - fallback rendering
- `prepareVersionSpecificPreviewTemplateData()` - private method (partially tested)
- `setupTimberLocations()` - private method
- `fetchReviews()` - private method
- `renderFallbackVersionSpecificPreview()` - private method
- `render()` - generic template rendering method
- `renderFallbackFromContext()` - private method
- Edge cases: null renderer, missing Timber class, template errors

**Action Plan:**
1. **Unit Tests (Priority: HIGH)**
   - Add comprehensive tests for `renderVersionSpecificPreview()`
   - Test fallback rendering paths
   - Test Timber integration (mock Timber class)
   - Test `render()` method with various contexts
   - Test error handling in template rendering

2. **Refactoring Needed:**
   - Consider extracting Timber operations to a separate service for better testability
   - Consider dependency injection for `fetchReviews()` instead of calling WordPressManager directly

3. **Mocking Strategy:**
   - Mock Timber class using Brain Monkey or similar
   - Mock `wordPressManager->getReviewsForMinisite()`
   - Mock `MinisiteViewDataService` for view model preparation
   - Use output buffering to capture rendered output

4. **Integration Tests (Fallback if unit tests insufficient):**
   - Test actual Timber rendering with real templates
   - Test version-specific preview with real data
   - Test template error handling

**Expected Coverage After:** 90%+ methods, 85%+ lines (some Timber-specific code may require integration tests)

---

### 3. MinisiteViewDataService (60% methods, 58.70% lines) ⚠️ MEDIUM PRIORITY

**Current State:**
- Basic `prepareViewModel()` tests exist
- Missing tests for protected methods
- Missing edge cases for bookmark/permission checks

**Missing Coverage:**
- `fetchReviews()` - protected method (test via prepareViewModel)
- `checkIfBookmarked()` - protected method
- `checkIfCanEdit()` - protected method
- Edge cases: user not logged in, database errors, missing repository

**Action Plan:**
1. **Unit Tests (Priority: MEDIUM)**
   - Test `checkIfBookmarked()` through `prepareViewModel()` with various user states
   - Test `checkIfCanEdit()` through `prepareViewModel()` with various permission states
   - Test `fetchReviews()` error handling
   - Test with missing global repository
   - Test with database exceptions

2. **Refactoring Needed:**
   - Consider dependency injection for ReviewRepository instead of global access
   - Consider dependency injection for database operations instead of direct wpdb access
   - This would make the class more testable and follow SOLID principles

3. **Mocking Strategy:**
   - Mock WordPress functions: `is_user_logged_in()`, `get_current_user_id()`, `current_user_can()`
   - Mock global `$wpdb` for bookmark checks
   - Mock global `$GLOBALS['minisite_review_repository']`
   - Use Brain Monkey for WordPress function mocking

4. **Integration Tests (Fallback if refactoring not done):**
   - Test with real database for bookmark checks
   - Test with real ReviewRepository

**Expected Coverage After:** 90%+ methods, 85%+ lines (if refactored) or 70%+ methods, 70%+ lines (if not refactored)

---

### 4. ViewHooks (50% methods, 58.33% lines) ⚠️ MEDIUM PRIORITY

**Current State:**
- Basic tests exist
- Missing tests for WordPress hook registration
- Missing tests for route handling

**Missing Coverage:**
- `register()` - WordPress hook registration
- `addRewriteRules()` - query var registration
- `addQueryVars()` - filter hook
- `handleViewRoutes()` - route handling logic
- `getController()` - controller accessor
- Edge cases: missing query vars, route termination

**Action Plan:**
1. **Unit Tests (Priority: MEDIUM)**
   - Test `register()` using Brain Monkey to verify WordPress hooks are added
   - Test `addRewriteRules()` and `addQueryVars()` using Brain Monkey
   - Test `handleViewRoutes()` with various query var states
   - Test `getController()` returns correct instance
   - Mock `MinisitePageController` and verify method calls

2. **Refactoring Needed:**
   - None - class is already well-structured

3. **Mocking Strategy:**
   - Use Brain Monkey for WordPress hooks (`add_action`, `add_filter`)
   - Mock `WordPressMinisiteManager::getQueryVar()`
   - Mock `MinisitePageController::handleView()`
   - Mock `TerminationHandlerInterface::terminate()`

4. **Integration Tests (Fallback):**
   - Test actual WordPress hook registration
   - Test route handling in WordPress environment

**Expected Coverage After:** 90%+ methods, 85%+ lines

---

### 5. ViewHooksFactory (0% methods, 80.65% lines) ⚠️ MEDIUM PRIORITY

**Current State:**
- No method tests exist (static method)
- Lines are covered but methods are not

**Missing Coverage:**
- `create()` - static factory method
- Dependency creation and injection
- Error handling for missing globals

**Action Plan:**
1. **Unit Tests (Priority: MEDIUM)**
   - Test `create()` returns ViewHooks instance
   - Test dependency creation (verify correct types)
   - Test error handling when globals are missing
   - Test with mocked globals for repositories
   - Test Timber renderer creation (with/without Timber class)

2. **Refactoring Needed:**
   - Consider making factory non-static for better testability
   - Consider dependency injection for globals instead of direct access
   - This would allow better mocking and testing

3. **Mocking Strategy:**
   - Mock global repositories: `$GLOBALS['minisite_repository']`, `$GLOBALS['minisite_version_repository']`
   - Mock Timber class existence
   - Use reflection to verify created dependencies

4. **Integration Tests (Fallback):**
   - Test factory with real dependencies
   - Test error scenarios with missing dependencies

**Expected Coverage After:** 100% methods, 95%+ lines

---

### 6. WordPressMinisiteManager (33.33% methods, 16.67% lines) ⚠️ LOW PRIORITY

**Current State:**
- Inherits from BaseWordPressManager (which has some coverage)
- Only MinisiteViewer-specific methods need testing
- Missing tests for new methods

**Missing Coverage:**
- `getLoginRedirectUrl()` - simple method
- `getReviewsForMinisite()` - review fetching
- Edge cases: missing global repository

**Action Plan:**
1. **Unit Tests (Priority: LOW)**
   - Test `getLoginRedirectUrl()` returns correct URL
   - Test `getReviewsForMinisite()` with mocked global repository
   - Test `getReviewsForMinisite()` when repository is missing
   - Mock WordPress function `wp_login_url()`

2. **Refactoring Needed:**
   - Consider dependency injection for ReviewRepository instead of global access
   - This would improve testability

3. **Mocking Strategy:**
   - Mock WordPress function `wp_login_url()`
   - Mock global `$GLOBALS['minisite_review_repository']`
   - Use Brain Monkey for WordPress functions

**Expected Coverage After:** 100% methods, 90%+ lines

---

### 7. MinisiteViewModel (83.33% methods, 41.67% lines) ⚠️ LOW PRIORITY

**Current State:**
- Most getter methods are tested
- Missing test for `toArray()` method

**Missing Coverage:**
- `toArray()` - conversion method
- Edge cases: null values, empty arrays

**Action Plan:**
1. **Unit Tests (Priority: LOW)**
   - Test `toArray()` returns correct structure
   - Test that `isBookmarked` and `canEdit` are set on minisite entity
   - Test with various review arrays
   - Test with empty reviews array

2. **Refactoring Needed:**
   - None - simple DTO class

3. **Mocking Strategy:**
   - Create mock Minisite entity
   - Verify properties are set correctly

**Expected Coverage After:** 100% methods, 95%+ lines

---

## Testing Strategy Summary

### Priority Order

1. **HIGH PRIORITY** (Critical for functionality):
   - ViewRequestHandler
   - ViewRenderer (version-specific preview)

2. **MEDIUM PRIORITY** (Important for completeness):
   - MinisiteViewDataService
   - ViewHooks
   - ViewHooksFactory

3. **LOW PRIORITY** (Nice to have):
   - WordPressMinisiteManager
   - MinisiteViewModel

### Testing Approach

1. **Unit Tests First** (Preferred):
   - Mock all dependencies
   - Test in isolation
   - Fast execution
   - High coverage potential

2. **Refactoring When Needed**:
   - Extract hard-to-test code
   - Improve dependency injection
   - Separate concerns for better testability

3. **Integration Tests as Fallback**:
   - When unit tests are insufficient
   - For WordPress-specific functionality
   - For Timber template rendering
   - For database operations

### Mocking Tools

- **PHPUnit Mocks**: For standard dependency mocking
- **Brain Monkey**: For WordPress function mocking
- **Reflection**: For testing private methods when necessary
- **Output Buffering**: For capturing rendered output

## Implementation Checklist

### Phase 1: Critical Coverage (Week 1)
- [ ] ViewRequestHandler - Add all functional tests
- [ ] ViewRenderer - Add version-specific preview tests
- [ ] Run coverage report and verify improvements

### Phase 2: Important Coverage (Week 2)
- [ ] MinisiteViewDataService - Add missing method tests
- [ ] ViewHooks - Add hook registration tests
- [ ] ViewHooksFactory - Add factory method tests
- [ ] Run coverage report and verify improvements

### Phase 3: Complete Coverage (Week 3)
- [ ] WordPressMinisiteManager - Add method tests
- [ ] MinisiteViewModel - Add toArray() test
- [ ] Review all tests for edge cases
- [ ] Final coverage report - target 90%+

## Refactoring Recommendations

### High Impact Refactoring

1. **MinisiteViewDataService**:
   - Inject ReviewRepository instead of using global
   - Inject database helper instead of direct wpdb access
   - **Benefit**: Much easier to test, better architecture

2. **ViewRenderer**:
   - Extract Timber operations to separate service
   - Inject review fetching service
   - **Benefit**: Better separation of concerns, easier testing

3. **ViewHooksFactory**:
   - Make non-static or inject dependencies
   - **Benefit**: Better testability, follows dependency injection pattern

### Low Impact Refactoring

1. **WordPressMinisiteManager**:
   - Inject ReviewRepository instead of global
   - **Benefit**: Consistent with other classes

## Success Criteria

- **Overall Coverage**: 90%+ lines, 90%+ methods
- **Per-Class Coverage**: 85%+ for all MinisiteViewer classes
- **Critical Paths**: 100% coverage for error handling and edge cases
- **Integration Tests**: All user flows covered

## Notes

- Some Timber-specific code may require integration tests
- WordPress hook registration may require integration tests
- Database operations can be unit tested with proper mocking
- Focus on testing business logic, not WordPress internals

## References

- Current coverage report: `build/coverage/unit/coverage.txt`
- Test files location: `tests/Unit/Features/MinisiteViewer/`
- Source files location: `src/Features/MinisiteViewer/`
- Related issue: MIN-27 (parent), MIN-36 (this task)

