# NewMinisite Feature Test Coverage Improvement Plan

## Overview

This document outlines the plan to improve test coverage for the NewMinisite feature, following the testing patterns established in ReviewManagement and VersionManagement features.

## Current Status

- **Existing Tests**: None
- **Target Coverage**: 90%+ for all classes
- **Priority**: High (feature is critical for minisite creation workflow)

## Feature Structure

The NewMinisite feature consists of 7 main classes:

1. **NewMinisiteFeature** - Bootstrap class
2. **NewMinisiteService** - Business logic for minisite creation
3. **NewMinisiteController** - HTTP request handling
4. **NewMinisiteHooks** - WordPress hook registration
5. **NewMinisiteHooksFactory** - Dependency injection factory
6. **NewMinisiteRenderer** - Template rendering
7. **WordPressNewMinisiteManager** - WordPress-specific operations

## Testing Strategy

Following the patterns from ReviewManagement and VersionManagement:

### Unit Tests (Primary Strategy)
- Mock all dependencies (repositories, WordPress functions, renderers)
- Test business logic in isolation
- Use PHPUnit mocks and Brain Monkey where appropriate
- Test error handling and edge cases

### Integration Tests (Secondary Strategy)
- Use when unit tests are not feasible (e.g., database interactions)
- Test end-to-end workflows
- Use real repositories with test database

## Per-Class Testing Plan

### 1. NewMinisiteFeature (Bootstrap)
**Priority**: Medium
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_initialize_registers_hooks()` - Verify hooks are registered
- `test_initialize_creates_hooks_via_factory()` - Verify factory is called
- `test_initialize_is_static_method()` - Verify method signature

**Dependencies to Mock**:
- `NewMinisiteHooksFactory::create()`
- WordPress `add_action()`

---

### 2. NewMinisiteService
**Priority**: High
**Complexity**: High
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_createNewMinisite_success()` - Successful minisite creation
- `test_createNewMinisite_validation_failure()` - Form validation errors
- `test_createNewMinisite_nonce_verification_failure()` - Security check failure
- `test_createNewMinisite_repository_exception()` - Database error handling
- `test_createNewMinisite_transaction_rollback()` - Transaction failure
- `test_getEmptyFormData_returns_empty_structure()` - Empty form data
- `test_canCreateNewMinisite_user_has_permission()` - Permission check (true)
- `test_canCreateNewMinisite_user_no_permission()` - Permission check (false)
- `test_canCreateNewMinisite_user_not_logged_in()` - No user
- `test_getUserMinisiteCount_returns_count()` - User minisite count
- `test_getUserMinisiteCount_user_not_logged_in()` - Returns 0 when no user

**Dependencies to Mock**:
- `WordPressNewMinisiteManager`
- `MinisiteRepositoryInterface`
- `VersionRepositoryInterface`
- `MinisiteFormProcessor`
- `MinisiteDatabaseCoordinator`
- `WordPressTransactionManager`

**Challenges**:
- Complex dependencies (MinisiteFormProcessor, MinisiteDatabaseCoordinator)
- Transaction management
- Form validation logic

---

### 3. NewMinisiteController
**Priority**: High
**Complexity**: Medium
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_handleNewMinisite_user_not_logged_in_redirects()` - Authentication check
- `test_handleNewMinisite_user_no_permission_renders_error()` - Authorization check
- `test_handleNewMinisite_get_request_displays_form()` - GET request handling
- `test_handleNewMinisite_post_request_submits_form()` - POST request handling
- `test_handleFormSubmission_invalid_nonce_renders_error()` - Nonce verification
- `test_handleFormSubmission_success_redirects()` - Successful submission
- `test_handleFormSubmission_validation_errors_displays_form()` - Validation errors
- `test_handleFormSubmission_service_exception_handles_gracefully()` - Exception handling
- `test_displayNewMinisiteForm_calls_renderer()` - Form display
- `test_displayNewMinisiteForm_exception_renders_error()` - Error handling

**Dependencies to Mock**:
- `NewMinisiteService`
- `NewMinisiteRenderer`
- `WordPressNewMinisiteManager`
- `FormSecurityHelper`
- WordPress functions (`$_POST`, `$_SERVER`)

**Challenges**:
- HTTP request/response mocking
- WordPress global state (`$_POST`, `$_SERVER`)

---

### 4. NewMinisiteHooks
**Priority**: Medium
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_register_method_exists()` - Method exists
- `test_handleNewMinisiteRoutes_not_account_route_returns_early()` - Route filtering
- `test_handleNewMinisiteRoutes_new_action_calls_controller()` - Route handling
- `test_handleNewMinisiteRoutes_other_action_ignored()` - Other actions ignored
- `test_handleNewMinisiteRoutes_terminates_after_handling()` - Termination

**Dependencies to Mock**:
- `NewMinisiteController`
- `WordPressNewMinisiteManager`
- `TerminationHandlerInterface`
- WordPress `get_query_var()`

---

### 5. NewMinisiteHooksFactory
**Priority**: Medium
**Complexity**: Medium
**Strategy**: Unit test

**Test Cases**:
- `test_create_returns_newminisite_hooks()` - Factory creates hooks
- `test_create_is_static_method()` - Method signature
- `test_create_throws_exception_when_minisite_repository_missing()` - Error handling
- `test_create_throws_exception_when_version_repository_missing()` - Error handling
- `test_create_creates_wordpress_manager()` - WordPress manager creation
- `test_create_creates_service_with_repositories()` - Service creation
- `test_create_creates_renderer()` - Renderer creation
- `test_create_creates_controller()` - Controller creation
- `test_create_creates_hooks_with_dependencies()` - Hooks creation

**Dependencies to Mock**:
- Global repositories (`$GLOBALS['minisite_repository']`, `$GLOBALS['minisite_version_repository']`)
- Timber class existence

**Challenges**:
- Global state management
- Conditional Timber rendering

---

### 6. NewMinisiteRenderer
**Priority**: Medium
**Complexity**: Medium
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_renderNewMinisiteForm_with_timber_renders_template()` - Timber rendering
- `test_renderNewMinisiteForm_without_timber_renders_fallback()` - Fallback rendering
- `test_renderNewMinisiteForm_prepares_template_data()` - Template data preparation
- `test_renderError_with_timber_renders_error_template()` - Error rendering (Timber)
- `test_renderError_without_timber_renders_fallback()` - Error rendering (fallback)
- `test_prepareTemplateData_creates_correct_structure()` - Template data structure
- `test_setupTimberLocations_sets_locations()` - Timber location setup

**Dependencies to Mock**:
- `TimberRenderer`
- Timber class existence
- WordPress constants (`MINISITE_PLUGIN_DIR`)

**Challenges**:
- Timber integration testing
- Template rendering in unit tests

---

### 7. WordPressNewMinisiteManager
**Priority**: Low
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_getLoginRedirectUrl_returns_login_url()` - Login redirect URL
- `test_userCanCreateMinisite_returns_true_for_read_capability()` - Permission check
- `test_userCanCreateMinisite_returns_false_for_no_capability()` - No permission

**Dependencies to Mock**:
- `TerminationHandlerInterface`
- WordPress `wp_login_url()`
- WordPress `user_can()`

---

## Implementation Checklist

### Phase 1: Foundation Tests (High Priority)
- [ ] NewMinisiteFeatureTest
- [ ] NewMinisiteServiceTest (core methods)
- [ ] NewMinisiteControllerTest (authentication, authorization, basic flow)

### Phase 2: Service Layer Tests (High Priority)
- [ ] NewMinisiteServiceTest (complete - all methods, error cases)
- [ ] WordPressNewMinisiteManagerTest

### Phase 3: Integration Layer Tests (Medium Priority)
- [ ] NewMinisiteHooksTest
- [ ] NewMinisiteHooksFactoryTest
- [ ] NewMinisiteRendererTest

### Phase 4: Edge Cases and Error Handling (Medium Priority)
- [ ] Additional error scenarios
- [ ] Transaction rollback tests
- [ ] Permission edge cases

### Phase 5: Integration Tests (Low Priority - if needed)
- [ ] End-to-end minisite creation workflow
- [ ] Database interaction tests

## Testing Patterns to Follow

### From ReviewManagement:
- Use `#[CoversClass]` attribute
- Mock repositories with `createMock()`
- Use global variables for WordPress functions when needed
- Test constructor dependency injection
- Test error handling and exceptions

### From VersionManagement:
- Comprehensive controller testing
- Handler pattern testing
- Request/Response handler testing
- Command pattern testing
- Factory pattern testing

## Success Criteria

1. **Coverage**: 90%+ for all NewMinisite classes
2. **Test Count**: Minimum 30+ test cases
3. **All Tests Passing**: No failures or errors
4. **Test Isolation**: Tests can run independently
5. **Documentation**: Tests are well-documented with clear names

## Notes

- Follow existing test file naming conventions: `*Test.php`
- Place tests in `tests/Unit/Features/NewMinisite/` directory structure
- Use same directory structure as source code
- Mock WordPress functions using global variables or Brain Monkey
- Ensure proper cleanup in `tearDown()` methods

