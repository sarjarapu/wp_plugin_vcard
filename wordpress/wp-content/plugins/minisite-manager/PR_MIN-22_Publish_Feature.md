# MIN-22: Restore Minisite Publish Functionality with Slug Reservation and WooCommerce Checkout

## Overview

Restores and refactors the minisite publish functionality following the feature-based architecture pattern. Allows users to purchase and publish minisites by selecting custom business/location slugs with WooCommerce checkout integration.

## What's Fixed

- ✅ **Slug Reservation System**: 5-minute reservation window when users initiate purchase
- ✅ **Slug Availability Checking**: Real-time AJAX validation against existing minisites and active reservations
- ✅ **WooCommerce Integration**: Cart creation, checkout flow, and automatic subscription activation
- ✅ **Grace Period Protection**: Slugs protected during 1-week grace period after expiration
- ✅ **Subscription Management**: Automatic activation on order completion, direct publish for existing subscribers

## Key Changes

### Slug Reservation Logic
- Validates against active subscriptions (`expires_at > NOW()`)
- Respects grace period protection (`grace_period_ends_at > NOW()`)
- Blocks concurrent reservations by other users (5-minute lock)
- Extends user's own existing reservations
- Allows reservation if subscription fully expired (beyond grace period)

### WooCommerce Flow
1. User reserves slug (5-minute window)
2. Product (SKU: NMS001) added to cart with minisite metadata
3. User completes checkout through WooCommerce
4. Subscription activates automatically on order completion
5. Minisite publishes with reserved slugs

### UI Improvements
- Button text: "Purchase Minisite" (was "Publish Minisite")
- Benefit-focused messaging in "What you get" section
- Extended error/success message display (30 seconds)
- Real-time URL preview
- Reservation countdown timer

### Configuration
Payment constants centralized in `PaymentConstants.php`:
- `SUBSCRIPTION_DURATION_MONTHS = 12` (1 year)
- `GRACE_PERIOD_DAYS = 7` (1 week)

Easily modifiable for different subscription durations.

## Architecture

Follows feature-based pattern like `MinisiteEdit` and `NewMinisite`:
- Controllers for HTTP handling
- Services for business logic (Reservation, SlugAvailability, SubscriptionActivation)
- WooCommerceIntegration for hook handlers
- WordPress manager for WP-specific operations

## Routes

- **Publish Page**: `/account/sites/{minisite_id}/publish`
- **AJAX Endpoints**: `check_slug_availability`, `reserve_slug`, `cancel_reservation`, `create_minisite_order`

## Testing Notes

- Requires WooCommerce active and product with SKU `NMS001`
- Test flow: Create draft → Check availability → Reserve → Checkout → Verify activation
- Edge cases: NULL location slugs, grace period protection, expired subscriptions

## Related

- MIN-22: Restore Minisite Publish Functionality
