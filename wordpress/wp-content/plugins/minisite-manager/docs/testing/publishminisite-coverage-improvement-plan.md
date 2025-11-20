# PublishMinisite Feature Test Coverage Improvement Plan

## Overview

This document outlines the plan to improve test coverage for the PublishMinisite feature, following the testing patterns established in ReviewManagement, VersionManagement, and NewMinisite features.

## Current Status

- **Existing Tests**: None
- **Target Coverage**: 90%+ for all classes
- **Priority**: High (feature is critical for minisite publishing workflow)

## Feature Structure

The PublishMinisite feature consists of 12 main classes:

1. **PublishMinisiteFeature** - Bootstrap class
2. **PublishService** - Main business logic for publishing
3. **SlugAvailabilityService** - Slug availability checking
4. **ReservationService** - Slug reservation management
5. **SubscriptionActivationService** - WooCommerce order activation
6. **WooCommerceIntegration** - WooCommerce hooks integration
7. **PublishController** - HTTP request handling
8. **PublishHooks** - WordPress hook registration
9. **PublishHooksFactory** - Dependency injection factory
10. **PublishRenderer** - Template rendering
11. **WordPressPublishManager** - WordPress-specific operations
12. **PaymentConstants** - Constants (no tests needed)

## Testing Strategy

Following the patterns from ReviewManagement, VersionManagement, and NewMinisite:

### Unit Tests (Primary Strategy)
- Mock all dependencies (repositories, WordPress functions, WooCommerce, renderers)
- Test business logic in isolation
- Use PHPUnit mocks and Brain Monkey where appropriate
- Test error handling and edge cases
- Mock database operations using $wpdb mocks

### Integration Tests (Secondary Strategy)
- Use when unit tests are not feasible (e.g., database interactions, WooCommerce)
- Test end-to-end workflows
- Use real repositories with test database

## Per-Class Testing Plan

### 1. PublishMinisiteFeature (Bootstrap)
**Priority**: Medium
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_initialize_registers_hooks()` - Verify hooks are registered
- `test_initialize_creates_hooks_via_factory()` - Verify factory is called
- `test_initialize_is_static_method()` - Verify method signature
- `test_class_is_final()` - Verify class is final
- `test_class_has_no_constructor()` - Verify no constructor

**Dependencies to Mock**:
- `PublishHooksFactory::create()`
- WordPress `add_action()`

---

### 2. PublishService
**Priority**: High
**Complexity**: Medium
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_getSlugAvailabilityService_returns_service()` - Getter method
- `test_getMinisiteForPublishing_success()` - Successful retrieval
- `test_getMinisiteForPublishing_minisite_not_found()` - Minisite not found exception
- `test_getMinisiteForPublishing_access_denied()` - Ownership check failure
- `test_getMinisiteForPublishing_returns_correct_structure()` - Return structure

**Dependencies to Mock**:
- `WordPressPublishManager`
- `MinisiteRepositoryInterface`
- `SlugAvailabilityService`
- `ReservationService`

---

### 3. SlugAvailabilityService
**Priority**: High
**Complexity**: High
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_validateSlugFormat_valid_slug()` - Valid slug format
- `test_validateSlugFormat_invalid_slug()` - Invalid slug format
- `test_checkAvailability_slug_available()` - Slug combination available
- `test_checkAvailability_slug_taken_by_minisite()` - Slug taken by existing minisite
- `test_checkAvailability_slug_reserved()` - Slug currently reserved
- `test_checkAvailability_invalid_business_slug()` - Invalid business slug format
- `test_checkAvailability_invalid_location_slug()` - Invalid location slug format
- `test_checkAvailability_empty_location_slug()` - Empty location slug handling
- `test_checkAvailability_database_exception()` - Exception handling

**Dependencies to Mock**:
- `WordPressPublishManager`
- `MinisiteRepositoryInterface`
- Global `$wpdb`
- `ReservationCleanup::cleanupExpired()`

**Challenges**:
- Database queries for reservations
- NULL handling for location slugs
- Reservation expiration logic

---

### 4. ReservationService
**Priority**: High
**Complexity**: High
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_hasActiveReservation_returns_false()` - Active reservation check (TODO implementation)
- `test_reserveSlug_creates_new_reservation()` - Create new reservation
- `test_reserveSlug_extends_existing_reservation()` - Extend existing reservation
- `test_reserveSlug_slug_taken_by_active_subscription()` - Slug protected by active subscription
- `test_reserveSlug_slug_reserved_by_another_user()` - Reservation conflict
- `test_reserveSlug_transaction_rollback_on_error()` - Transaction handling
- `test_cancelReservation_not_implemented()` - Cancellation (TODO)
- `test_isReservationValid_not_implemented()` - Validation (TODO)
- `test_tryAutoRenewExpiredReservation_not_implemented()` - Auto-renewal (TODO)

**Dependencies to Mock**:
- `WordPressPublishManager`
- `MinisiteRepositoryInterface`
- Global `$wpdb`
- Database transactions

**Challenges**:
- Transaction management
- Reservation expiration logic
- Multiple TODO methods

---

### 5. SubscriptionActivationService
**Priority**: High
**Complexity**: High
**Strategy**: Unit test with mocks (WooCommerce integration)

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_activateFromOrder_order_not_found()` - Order not found exception
- `test_activateFromOrder_minisite_id_from_order_meta()` - Get minisite ID from order meta
- `test_activateFromOrder_minisite_id_from_order_items()` - Get minisite ID from order items
- `test_activateFromOrder_minisite_id_from_session()` - Get minisite ID from session
- `test_activateFromOrder_no_minisite_id_found()` - No minisite ID exception
- `test_activateFromOrder_invalid_slug_format()` - Invalid slug format exception
- `test_activateFromOrder_success()` - Successful activation
- `test_activateFromOrder_transaction_rollback()` - Transaction rollback on error

**Dependencies to Mock**:
- `WordPressPublishManager`
- `MinisiteRepositoryInterface`
- WooCommerce `wc_get_order()`
- WooCommerce order object
- Global `$wpdb`
- `PaymentConstants`

**Challenges**:
- WooCommerce integration
- Order meta data handling
- Session handling
- Payment record creation

---

### 6. WooCommerceIntegration
**Priority**: Medium
**Complexity**: High
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_transferCartDataToOrder_transfers_data()` - Cart data transfer
- `test_transferCartItemToOrderItem_transfers_item_data()` - Item data transfer
- `test_activateSubscriptionOnOrderCompletion_calls_service()` - Order completion hook

**Dependencies to Mock**:
- `WordPressPublishManager`
- `SubscriptionActivationService`
- WooCommerce order object
- WooCommerce cart/session

**Challenges**:
- WooCommerce hook system
- Cart data structure
- Order item meta

---

### 7. PublishController
**Priority**: High
**Complexity**: High
**Strategy**: Unit test with mocks

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_handlePublish_user_not_logged_in_redirects()` - Authentication check
- `test_handlePublish_no_site_id_redirects()` - Missing site ID
- `test_handlePublish_success_renders_page()` - Successful page display
- `test_handlePublish_access_denied_redirects()` - Access denied handling
- `test_handleCheckSlugAvailability_not_authenticated()` - AJAX authentication
- `test_handleCheckSlugAvailability_invalid_nonce()` - AJAX nonce verification
- `test_handleCheckSlugAvailability_success()` - Successful slug check
- `test_handleReserveSlug_not_authenticated()` - Reservation authentication
- `test_handleReserveSlug_invalid_nonce()` - Reservation nonce
- `test_handleReserveSlug_invalid_slug_format()` - Slug format validation
- `test_handleReserveSlug_success()` - Successful reservation
- `test_handleCancelReservation_not_implemented()` - Cancellation (TODO)
- `test_handleCreateWooCommerceOrder_not_authenticated()` - Order creation auth
- `test_handleCreateWooCommerceOrder_woocommerce_not_active()` - WooCommerce check
- `test_handleCreateWooCommerceOrder_success()` - Successful order creation

**Dependencies to Mock**:
- `PublishService`
- `PublishRenderer`
- `WordPressPublishManager`
- `FormSecurityHelper`
- `SubscriptionActivationService`
- `ReservationService`
- WooCommerce functions
- Global `$wpdb`

**Challenges**:
- Multiple AJAX handlers
- WooCommerce integration
- Complex form data handling

---

### 8. PublishHooks
**Priority**: Medium
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_register_method_exists()` - Method exists
- `test_register_registers_ajax_handlers()` - AJAX handler registration
- `test_register_registers_woocommerce_hooks()` - WooCommerce hook registration
- `test_handlePublishRoutes_not_account_route_returns_early()` - Route filtering
- `test_handlePublishRoutes_publish_action_calls_controller()` - Route handling
- `test_handlePublishRoutes_other_action_ignored()` - Other actions ignored
- `test_handlePublishRoutes_terminates_after_handling()` - Termination
- `test_getController_returns_controller()` - Getter method

**Dependencies to Mock**:
- `PublishController`
- `WordPressPublishManager`
- `WooCommerceIntegration`
- `TerminationHandlerInterface`
- WordPress `add_action()`

---

### 9. PublishHooksFactory
**Priority**: Medium
**Complexity**: Medium
**Strategy**: Unit test

**Test Cases**:
- `test_create_returns_publish_hooks()` - Factory creates hooks
- `test_create_is_static_method()` - Method signature
- `test_create_throws_exception_when_minisite_repository_missing()` - Error handling
- `test_create_creates_wordpress_manager()` - WordPress manager creation
- `test_create_creates_services()` - Service creation
- `test_create_creates_renderer()` - Renderer creation
- `test_create_creates_controller()` - Controller creation
- `test_create_creates_hooks_with_dependencies()` - Hooks creation

**Dependencies to Mock**:
- Global repositories (`$GLOBALS['minisite_repository']`)
- Timber class existence
- WooCommerce class existence

---

### 10. PublishRenderer
**Priority**: Medium
**Complexity**: Medium
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_renderPublishPage_with_timber_renders_template()` - Timber rendering
- `test_renderPublishPage_without_timber_renders_fallback()` - Fallback rendering
- `test_renderPublishPage_prepares_template_data()` - Template data preparation
- `test_setupTimberLocations_sets_locations()` - Timber location setup

**Dependencies to Mock**:
- `TimberRenderer`
- Timber class existence
- WordPress constants (`MINISITE_PLUGIN_DIR`)
- WordPress functions (`wp_get_current_user()`)

---

### 11. WordPressPublishManager
**Priority**: Low
**Complexity**: Low
**Strategy**: Unit test

**Test Cases**:
- `test_constructor_dependency_injection()` - Verify dependencies
- `test_getCurrentUserId_returns_user_id()` - Get user ID
- `test_getAdminUrl_returns_admin_url()` - Admin URL
- `test_sendJsonSuccess_sends_json()` - JSON success response
- `test_sendJsonError_sends_json()` - JSON error response
- `test_isWooCommerceActive_returns_boolean()` - WooCommerce check
- `test_getPostData_returns_post_data()` - POST data retrieval

**Dependencies to Mock**:
- `TerminationHandlerInterface`
- WordPress functions (`get_current_user_id()`, `admin_url()`, `wp_send_json_success()`, etc.)
- WooCommerce class existence

---

## Implementation Checklist

### Phase 1: Foundation Tests (High Priority)
- [ ] PublishMinisiteFeatureTest
- [ ] WordPressPublishManagerTest
- [ ] PublishServiceTest (core methods)
- [ ] PublishHooksTest
- [ ] PublishHooksFactoryTest

### Phase 2: Service Layer Tests (High Priority)
- [ ] SlugAvailabilityServiceTest (complete)
- [ ] ReservationServiceTest (complete - including TODO methods)
- [ ] SubscriptionActivationServiceTest
- [ ] WooCommerceIntegrationTest

### Phase 3: Controller Tests (High Priority)
- [ ] PublishControllerTest (all AJAX handlers, authentication, authorization)

### Phase 4: Rendering Tests (Medium Priority)
- [ ] PublishRendererTest

### Phase 5: Edge Cases and Error Handling (Medium Priority)
- [ ] Additional error scenarios
- [ ] Transaction rollback tests
- [ ] WooCommerce integration edge cases

### Phase 6: Integration Tests (Low Priority - if needed)
- [ ] End-to-end publishing workflow
- [ ] Database interaction tests
- [ ] WooCommerce order flow tests

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

### From NewMinisite:
- Bootstrap class testing
- Service layer testing with complex dependencies
- Controller testing with AJAX handlers
- Renderer testing with Timber fallback

## Success Criteria

1. **Coverage**: 90%+ for all PublishMinisite classes
2. **Test Count**: Minimum 50+ test cases
3. **All Tests Passing**: No failures or errors
4. **Test Isolation**: Tests can run independently
5. **Documentation**: Tests are well-documented with clear names

## Notes

- Follow existing test file naming conventions: `*Test.php`
- Place tests in `tests/Unit/Features/PublishMinisite/` directory structure
- Use same directory structure as source code
- Mock WordPress functions using global variables or Brain Monkey
- Mock WooCommerce functions and classes
- Ensure proper cleanup in `tearDown()` methods
- Handle TODO methods by testing current behavior (return false/null/not implemented)
- Mock database operations using $wpdb anonymous classes
- Test transaction rollback scenarios

## Special Considerations

### WooCommerce Integration
- Mock WooCommerce classes and functions
- Test with and without WooCommerce active
- Handle WooCommerce-specific data structures

### Database Transactions
- Mock transaction start/commit/rollback
- Test rollback scenarios
- Verify transaction isolation

### Reservation System
- Test expiration logic
- Test cleanup operations
- Handle NULL location slugs properly

