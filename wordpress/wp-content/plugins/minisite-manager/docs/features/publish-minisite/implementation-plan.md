# PublishMinisite Feature Implementation Plan

## Overview

This document outlines the plan to restore and refactor the minisite publish functionality following the current clean architecture pattern used in `MinisiteEdit` and `NewMinisite` features.

## Architecture Decision

### Feature Structure

Following the pattern established by `MinisiteEdit` and `NewMinisite`, the `PublishMinisite` feature will be structured as:

```
src/Features/PublishMinisite/
├── PublishMinisiteFeature.php          # Feature bootstrap
├── Controllers/
│   └── PublishController.php           # HTTP request handling
├── Services/
│   ├── PublishService.php              # Business logic
│   ├── SlugAvailabilityService.php     # Slug checking logic
│   └── ReservationService.php          # Reservation management
├── Hooks/
│   ├── PublishHooks.php                # Route handling
│   └── PublishHooksFactory.php         # Dependency injection
├── Rendering/
│   └── PublishRenderer.php             # UI rendering
├── WordPress/
│   ├── WordPressPublishManager.php     # WordPress operations
│   └── WooCommerceIntegration.php      # WooCommerce hooks
└── Handlers/
    ├── PaymentCompletionHandler.php     # Handle order completion
    └── ReservationCleanupHandler.php   # Handle cleanup cron
```

## Feature Components

### 1. Route Configuration

**Route**: `/account/sites/{minisite_id}/publish`

**Rewrite Rule**: Already exists in `RewriteRegistrar.php` (line 38-43) for generic `/account/sites/publish`, but we need:
- Site-specific route: `/account/sites/{id}/publish` (NEW)
- Generic route: `/account/sites/publish?site_id={id}` (can be used as fallback)

**Recommendation**: Add site-specific route:
```php
add_rewrite_rule(
    '^account/sites/([a-f0-9]{24,32})/publish/?$',
    'index.php?minisite_account=1&minisite_account_action=publish&minisite_site_id=$matches[1]',
    'top'
);
```

### 2. Service Layer Architecture

#### `PublishService`
**Responsibilities**:
- Coordinate the publish workflow
- Validate minisite eligibility for publishing
- Check user permissions
- Orchestrate slug reservation and payment flow
- Handle direct publish for existing subscribers

#### `SlugAvailabilityService`
**Responsibilities**:
- Check slug combination availability
- Validate slug format (lowercase, alphanumeric, hyphens)
- Query database for existing minisites with same slugs
- Check active reservations
- Clean expired reservations before checking

**Key Methods**:
- `checkAvailability(string $businessSlug, string $locationSlug): SlugAvailabilityResult`
- `validateSlugFormat(string $slug): bool`

#### `ReservationService`
**Responsibilities**:
- Create slug reservations (5-minute window)
- Cancel reservations
- Validate reservation ownership
- Handle reservation expiration
- Auto-renew expired reservations if slug still available (for checkout scenario)
- Enforce single active reservation per user

**Key Methods**:
- `reserveSlug(string $businessSlug, string $locationSlug, int $userId): Reservation`
- `cancelReservation(int $reservationId, int $userId): void`
- `isReservationValid(int $reservationId): bool`
- `tryAutoRenewExpiredReservation(int $reservationId, string $businessSlug, string $locationSlug): ?Reservation`
- `hasActiveReservation(int $userId): bool`

### 3. Controller Layer

#### `PublishController`
**Responsibilities**:
- Handle GET request: Display publish page
- Handle AJAX requests:
  - `check_slug_availability` - Check if slugs are available
  - `reserve_slug` - Reserve slugs for 5 minutes
  - `cancel_reservation` - Cancel user's reservation
  - `create_minisite_order` - Create WooCommerce cart item
- Handle POST requests for form submissions

**Route Handling**:
- `/account/sites/{id}/publish` → `handlePublish()`

### 4. Rendering Layer

#### `PublishRenderer`
**Responsibilities**:
- Render publish page UI
- Provide context data (minisite info, current user, etc.)
- Handle error/success messages
- Integrate with Timber template system

**Template**: `templates/timber/views/account-sites-publish.twig`
- Already exists and is well-structured
- Needs minor updates for current architecture

### 5. WordPress Integration Layer

#### `WordPressPublishManager`
**Responsibilities**:
- WordPress-specific operations (get_current_user_id, wp_redirect, etc.)
- Query var extraction
- Nonce verification helpers
- URL generation

#### `WooCommerceIntegration`
**Responsibilities**:
- Hook into WooCommerce order completion
- Transfer cart metadata to order metadata
- Handle payment completion and minisite activation
- Register WooCommerce hooks

**WooCommerce Hooks**:
1. `woocommerce_checkout_create_order_line_item` - Transfer cart metadata
2. `woocommerce_order_status_completed` - Activate subscription after payment
3. `woocommerce_add_to_cart` - Validate minisite data (optional)

### 6. Payment Completion Handler

#### `PaymentCompletionHandler`
**Responsibilities**:
- Process completed WooCommerce orders
- Extract minisite data from order metadata
- Validate or auto-renew expired reservations
- Activate minisite subscription
- Update minisite status to 'published'
- Update slugs from reservation
- Create payment record
- Clean up reservation

**Key Methods**:
- `handleOrderCompletion(int $orderId): void`
- `activateSubscription(int $orderId, string $minisiteId, string $businessSlug, string $locationSlug): void`
- `validateOrRenewReservation(int $reservationId, string $businessSlug, string $locationSlug): bool`

## Implementation Flow

### User Journey

```
1. User clicks "Publish" button on minisite
   ↓
2. Navigate to /account/sites/{id}/publish
   ↓
3. PublishController::handlePublish() displays publish page
   ↓
4. User enters business slug and optional location slug
   ↓
5. User clicks "Check Availability"
   ↓
6. AJAX: SlugAvailabilityService::checkAvailability()
   ↓
7. If available, user clicks "Publish Minisite"
   ↓
8. AJAX: ReservationService::reserveSlug() (5-minute window)
   ↓
9. AJAX: WooCommerceIntegration::addToCart() with reservation_id
   ↓
10. Redirect to WooCommerce cart/checkout
   ↓
11. User completes payment
   ↓
12. WooCommerce order status → "completed"
   ↓
13. PaymentCompletionHandler::handleOrderCompletion()
   ↓
14. Check if reservation expired:
    - If expired BUT slug still available → Auto-renew reservation
    - If expired AND slug taken → Reject with error
    - If valid → Proceed normally
   ↓
15. Activate subscription, publish minisite, clean reservation
```

### Timeout & Cleanup Strategy

#### 1. Frontend Timer (Client-Side)
- **Location**: Template JavaScript (`account-sites-publish.twig`)
- **Function**: Display countdown timer to user
- **Behavior**: Updates every second, shows remaining time
- **Action on Expiry**: Disable publish button, show expiration message
- **Re-check**: Allow user to check availability again after expiry

#### 2. Reservation Cleanup (Server-Side)
- **Method**: `ReservationCleanup::cleanupExpired()`
- **When**: 
  - Before every availability check
  - Before every reservation creation
  - Cron job (recommended)
- **Action**: DELETE expired reservations from database
- **Frequency**: 
  - On-demand (reactive): Before every check/reservation
  - Cron (proactive): Every 15 minutes (configurable via `RESERVATION_CLEANUP_CRON_INTERVAL` constant)
- **Configuration**: Set in `src/Infrastructure/Utils/ReservationCleanup.php` or via plugin constants

#### 3. Reservation Validation (Server-Side)
- **Check on Purchase**: Validate reservation is still valid before processing payment
- **Check on Order Completion**: 
  - If reservation expired but slug still available → Auto-renew reservation and proceed
  - If reservation expired and slug taken → Reject with clear error message
- **Auto-Renewal Logic**: Only during checkout completion, not for general extension
- **Single Reservation Rule**: Users can only have one active reservation at a time

### Database Operations

#### Reservation Table
- **Table**: `wp_minisite_reservations`
- **Operations**:
  - INSERT on reservation
  - UPDATE on extension
  - DELETE on expiration/cancellation/completion
- **Transaction Safety**: All reservation operations use database transactions

#### Payment Table
- **Table**: `wp_minisite_payments`
- **Operations**:
  - INSERT on payment completion
  - UPDATE on renewal
- **Expiration**: Track `expires_at` and `grace_period_ends_at`

## Key Design Decisions

### 1. Route Structure
**Decision**: Use site-specific route `/account/sites/{id}/publish`
**Rationale**: 
- Consistent with edit route pattern
- Clear minisite context
- Better URL structure

### 2. Reservation Timeout
**Decision**: 5 minutes, no grace period, no manual extension
**Rationale**:
- Prevents slug hoarding
- Sufficient for checkout process
- Fair to all users
- Keeps system simple and predictable

### 2a. Reservation Auto-Renewal (Special Case)
**Decision**: Auto-renew expired reservation during checkout completion if slug still available
**Rationale**:
- Better UX - avoids frustrating "start over" scenario
- Fair - only happens if slug is still actually available
- Only applies during order completion, not for general extension

### 3. Cleanup Strategy
**Decision**: Reactive + Proactive
**Rationale**:
- Reactive cleanup ensures accuracy at critical moments
- Proactive cron prevents table bloat
- Balance between performance and data hygiene

### 4. Payment Integration
**Decision**: Cart-based flow (not direct order creation)
**Rationale**:
- Uses standard WooCommerce checkout flow
- User sees full cart and can review
- Better UX and compliance

### 5. Existing Subscriber Handling
**Decision**: Direct publish without payment
**Rationale**:
- User already paid for subscription
- No need to charge again
- Seamless experience

### 6. Error Handling
**Decision**: Transaction rollback on failures
**Rationale**:
- Maintain data consistency
- Prevent partial states
- Better error recovery

### 7. Single Reservation per User
**Decision**: Users can only have one active reservation at a time
**Rationale**:
- Prevents abuse and complexity
- Focuses user on completing one checkout
- Simpler to manage and debug

### 8. Failed Payment Handling
**Decision**: Reservation expires naturally, WooCommerce handles payment flow
**Rationale**:
- WooCommerce manages payment retries internally
- Reservation timeout prevents indefinite holds
- User can retry with new reservation if needed
- No special handling needed - natural expiration works

### 9. Cron Configuration
**Decision**: 15-minute cleanup interval, easily configurable
**Rationale**:
- Less frequent than reservation duration (5 min)
- Reactive cleanup on checks/reservations ensures accuracy
- Tunable via constant for easy adjustment
- Documented for quick reference

## Implementation Checklist

### Phase 1: Core Feature Structure
- [ ] Create `PublishMinisiteFeature` bootstrap class
- [ ] Create `PublishHooks` and `PublishHooksFactory`
- [ ] Create `PublishController` with route handling
- [ ] Create `PublishService` for business logic
- [ ] Create `WordPressPublishManager` for WordPress operations
- [ ] Create `PublishRenderer` for UI rendering
- [ ] Update `RewriteRegistrar` with site-specific publish route
- [ ] Register feature in `FeatureRegistry`

### Phase 2: Slug Management Services
- [ ] Create `SlugAvailabilityService`
- [ ] Create `ReservationService`
- [ ] Implement slug format validation
- [ ] Implement availability checking logic
- [ ] Implement reservation creation/cancellation
- [ ] Implement single reservation per user enforcement
- [ ] Implement auto-renewal logic for expired reservations (checkout scenario)
- [ ] Integrate `ReservationCleanup` utility
- [ ] Add configurable cron interval (15 minutes default)

### Phase 3: WooCommerce Integration
- [ ] Create `WooCommerceIntegration` class
- [ ] Implement cart item creation with metadata
- [ ] Register `woocommerce_checkout_create_order_line_item` hook
- [ ] Create `PaymentCompletionHandler`
- [ ] Implement reservation validation/renewal on order completion
- [ ] Register `woocommerce_order_status_completed` hook
- [ ] Implement subscription activation logic
- [ ] Handle existing subscriber direct publish
- [ ] Handle expired reservation auto-renewal during checkout completion

### Phase 4: UI & Frontend
- [ ] Review and update `account-sites-publish.twig` template
- [ ] Implement AJAX handlers in template
- [ ] Add countdown timer functionality
- [ ] Add error/success message handling
- [ ] Add reservation cancellation UI
- [ ] Test responsive design

### Phase 5: Testing
- [ ] Unit tests for `SlugAvailabilityService`
- [ ] Unit tests for `ReservationService`
- [ ] Unit tests for `PublishService`
- [ ] Integration tests for reservation flow
- [ ] Integration tests for WooCommerce flow
- [ ] End-to-end test for complete publish journey

### Phase 6: Cleanup & Optimization
- [ ] Add cron job for reservation cleanup (15-minute interval, configurable)
- [ ] Document cron configuration for quick lookup
- [ ] Add logging for critical operations
- [ ] Add error handling improvements
- [ ] Performance optimization if needed
- [ ] Documentation updates

## Design Decisions - RESOLVED

### 1. Reservation Extension
**Decision**: NO manual extension allowed
- Users get one 5-minute window to complete payment
- No ability to extend reservations
- Keeps system simple and fair

### 2. Cron Job Frequency
**Decision**: 15 minutes (configurable)
- Less frequent than reservation duration
- Reactive cleanup on checks/reservations ensures accuracy
- Must be easily tunable via constant
- Documented in code and config file

### 3. Multiple Reservations
**Decision**: Only one active reservation per user
- Prevents complexity and abuse
- Focuses user on completing one checkout
- Simpler system to manage

### 4. Reservation Expires Mid-Checkout
**Decision**: Auto-renew if slug still available, reject if taken

**Analysis**: 
This is a smart compromise between user experience and fairness:
- **Pro UX**: User has already invested time in checkout process - making them restart is frustrating
- **Pro Fairness**: Only auto-renews if slug is genuinely still available (not reserved by others)
- **Pro Technical**: Happens during order completion transaction, so it's atomic and safe
- **Con Edge Case**: If user took exactly 5 minutes and slug was just taken, they get rejected (but this is expected behavior)
- **Mitigation**: Frontend timer warns users, giving them ample time to complete checkout

**Implementation Strategy**:
- Check reservation validity during `PaymentCompletionHandler::handleOrderCompletion()`
- If expired, immediately check slug availability (with cleanup)
- If available: create new reservation, update order metadata, proceed
- If taken: throw exception with clear message, log for admin review
- All in transaction to ensure atomicity

### 5. Failed Payment Handling
**Decision**: Natural expiration, WooCommerce handles payment flow
- WooCommerce manages payment retries internally
- Reservation expires after 5 minutes regardless
- User can create new reservation if payment fails
- No special handling needed

## Files to Reference from delete_me

1. **Controllers**:
   - `delete_me/src/Application/Controllers/Front/NewMinisiteController.php`
     - `handleCheckSlugAvailability()` (lines 469-551)
     - `handleReserveSlug()` (lines 553-689)
     - `handleCreateWooCommerceOrder()` (lines 831-942)
     - `activateMinisiteSubscription()` (lines 1021-1152)

2. **Templates**:
   - `templates/timber/views/account-sites-publish.twig` (already in templates)

3. **Utilities**:
   - `src/Infrastructure/Utils/ReservationCleanup.php` (already exists)

4. **Database Tables**:
   - `data/db/tables/minisite_reservations.sql` (already exists)
   - `data/db/tables/minisite_payments.sql` (already exists)

## Technical Design Details

### Auto-Renewal Logic for Expired Reservations

When a reservation expires during checkout, we implement smart auto-renewal:

**Scenario**: User reserved slug, reservation expired while they were completing payment, order is now completing.

**Flow**:
1. `PaymentCompletionHandler::handleOrderCompletion()` is called
2. Extract reservation_id from order metadata
3. Check if reservation exists and is valid:
   - If valid → Proceed normally
   - If expired → Continue to step 4
4. If expired, call `ReservationService::tryAutoRenewExpiredReservation()`
5. Auto-renewal logic:
   ```php
   // Pseudo-code
   if (reservation_expired) {
       // Clean expired reservations first
       ReservationCleanup::cleanupExpired();
       
       // Check if slug combination is still available
       if (slug_available) {
           // Create new reservation (same user, same slugs)
           new_reservation = createReservation(business_slug, location_slug, user_id);
           // Update order metadata with new reservation_id
           // Proceed with activation
       } else {
           // Slug taken by someone else
           throw ReservationExpiredException("Reservation expired and slug is no longer available");
       }
   }
   ```

**Implementation Location**: 
- `ReservationService::tryAutoRenewExpiredReservation()`
- `PaymentCompletionHandler::validateOrRenewReservation()`

**Important Notes**:
- Only happens during order completion, not for general operations
- Requires slug to actually be available (not just expired)
- Transaction-safe - all operations in single transaction
- Logs auto-renewal for audit trail

### Single Reservation Enforcement

**Implementation**: 
- Before creating reservation, check: `ReservationService::hasActiveReservation(userId)`
- If active reservation exists:
  - Option A: Cancel existing reservation and create new one
  - Option B: Reject new reservation with message
  
**Decision**: Option B (Reject with clear message)
- More predictable behavior
- User must explicitly cancel before reserving new slug
- Prevents accidental overwriting

### Cron Configuration

**Location**: `src/Infrastructure/Utils/ReservationCleanup.php`

**Constant Definition**:
```php
// Default: 15 minutes (in seconds)
if (!defined('MINISITE_RESERVATION_CLEANUP_INTERVAL')) {
    define('MINISITE_RESERVATION_CLEANUP_INTERVAL', 15 * 60);
}
```

**Cron Hook Registration**:
```php
// Register cron event
if (!wp_next_scheduled('minisite_cleanup_expired_reservations')) {
    wp_schedule_event(time(), 'minisite_reservation_cleanup', 'minisite_cleanup_expired_reservations');
}

// Register custom interval
add_filter('cron_schedules', function($schedules) {
    $schedules['minisite_reservation_cleanup'] = array(
        'interval' => MINISITE_RESERVATION_CLEANUP_INTERVAL,
        'display' => 'Minisite Reservation Cleanup'
    );
    return $schedules;
});
```

**Documentation Location**: 
- Inline comments in `ReservationCleanup.php`
- Plugin README or configuration docs
- Quick reference in feature documentation

## Next Steps

1. Review this plan and discuss any concerns or modifications
2. Once aligned, begin Phase 1 implementation
3. Iterate through phases with testing at each step
4. Final review and integration

