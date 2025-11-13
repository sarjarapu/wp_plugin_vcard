# WordPress Manager Method Migration Table

## Quick Reference: Methods Moving to BaseWordPressManager

| Method | Current Locations | Move to Base? | Standardization Notes |
|--------|------------------|---------------|---------------------|
| `sanitizeTextField()` | All 7 managers | ✅ YES | Use `wp_unslash()` consistently |
| `sanitizeTextareaField()` | All 7 managers | ✅ YES | Use `wp_unslash()` consistently |
| `sanitizeUrl()` | All 7 managers | ✅ YES | Use `wp_unslash()` + `esc_url_raw()` |
| `sanitizeEmail()` | All 7 managers | ✅ YES | Use `wp_unslash()` consistently |
| `isUserLoggedIn()` | All 7 managers | ✅ YES | Standard implementation |
| `getCurrentUser()` | All 7 managers | ✅ YES | Always check `$user->ID > 0` |
| `verifyNonce()` | All 7 managers | ✅ YES | Use `!== false` check |
| `createNonce()` | All 7 managers | ✅ YES | Standard implementation |
| `getHomeUrl()` | All 7 managers | ✅ YES | Standard implementation |
| `getQueryVar()` | All 7 managers | ✅ YES | Standard implementation |
| `redirect()` | All 7 managers | ✅ YES | Already in base, remove overrides |
| `unslash()` | Version, Auth | ✅ YES | Common utility |
| `escUrlRaw()` | Version | ✅ YES | Common utility |

## Methods Staying in Individual Managers

### WordPressVersionManager (Keep These)
- `sendJsonSuccess()` - AJAX-specific
- `sendJsonError()` - AJAX-specific
- `setStatusHeader()` - HTTP-specific
- `setNoCacheHeaders()` - HTTP-specific
- `jsonEncode()` - Utility specific to Version
- `getHomeUrl()` - Override with `$scheme` parameter

### WordPressPublishManager (Keep These)
- `getCurrentUserId()` - Convenience method
- `getAdminUrl()` - Publish-specific
- `sendJsonSuccess()` - AJAX-specific
- `sendJsonError()` - AJAX-specific
- `isWooCommerceActive()` - Publish-specific
- `getPostData()` - Request handling
- `isPostRequest()` - Request handling
- `isAjaxRequest()` - Request handling

### WordPressEditManager (Keep These)
- `getLoginRedirectUrl()` - Edit-specific redirect logic
- `userOwnsMinisite()` - Edit-specific authorization

### WordPressNewMinisiteManager (Keep These)
- `getLoginRedirectUrl()` - NewMinisite-specific redirect logic
- `userCanCreateMinisite()` - NewMinisite-specific authorization

### WordPressUserManager (Keep These)
- `signon()` - Auth-specific
- `createUser()` - Auth-specific
- `getUserBy()` - Auth-specific
- `setCurrentUser()` - Auth-specific
- `setAuthCookie()` - Auth-specific
- `logout()` - Auth-specific
- `isWpError()` - Auth-specific utility
- `isEmail()` - Auth-specific utility
- `sanitizeText()` - Different from `sanitizeTextField` (no unslash?)
- `retrievePassword()` - Auth-specific
- `setStatusHeader()` - HTTP utility
- `getTemplatePart()` - Template utility
- `getWpQuery()` - Query utility

### WordPressMinisiteManager (Keep These)
- None identified (may be empty after refactoring)

### WordPressListingManager (Keep These)
- None identified (may be empty after refactoring)

### WordPressReviewManager (New - Keep These)
- None initially (will add as needed)

## Standardization Decisions

### 1. wp_unslash() Usage
**Decision**: Use `wp_unslash()` in ALL sanitization methods
**Rationale**: WordPress adds slashes to POST data, so we should unslash before sanitizing
**Implementation**:
```php
public function sanitizeTextField(?string $text): string
{
    if ($text === null) {
        return '';
    }
    return sanitize_text_field(wp_unslash($text));
}
```

### 2. getCurrentUser() Null Check
**Decision**: Always check `$user && $user->ID > 0` before returning
**Rationale**: WordPress may return user object with ID 0 for logged-out users
**Implementation**:
```php
public function getCurrentUser(): ?object
{
    $user = wp_get_current_user();
    return $user && $user->ID > 0 ? $user : null;
}
```

### 3. verifyNonce() Boolean Return
**Decision**: Always use `!== false` check
**Rationale**: `wp_verify_nonce()` returns `int|false`, we need boolean
**Implementation**:
```php
public function verifyNonce(string $nonce, string $action): bool
{
    return wp_verify_nonce($nonce, $action) !== false;
}
```

### 4. Null Handling in Sanitization
**Decision**: Return empty string for null input
**Rationale**: Consistent behavior, prevents type errors
**Implementation**: All sanitization methods check `if ($text === null) return '';`

## Migration Checklist Per Manager

### WordPressVersionManager
- [ ] Remove `isUserLoggedIn()` → Use parent
- [ ] Remove `getCurrentUser()` → Use parent
- [ ] Remove `getQueryVar()` → Use parent
- [ ] Remove `sanitizeTextField()` → Use parent
- [ ] Remove `sanitizeTextareaField()` → Use parent
- [ ] Remove `sanitizeUrl()` → Use parent
- [ ] Remove `sanitizeEmail()` → Use parent
- [ ] Remove `verifyNonce()` → Use parent
- [ ] Remove `createNonce()` → Use parent
- [ ] Remove `redirect()` → Use parent
- [ ] Remove `unslash()` → Use parent
- [ ] Remove `escUrlRaw()` → Use parent
- [ ] Keep `sendJsonSuccess()`
- [ ] Keep `sendJsonError()`
- [ ] Keep `setStatusHeader()`
- [ ] Keep `setNoCacheHeaders()`
- [ ] Keep `jsonEncode()`
- [ ] Override `getHomeUrl()` with scheme parameter
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressPublishManager
- [ ] Remove all common methods (same as Version)
- [ ] Keep `getCurrentUserId()`
- [ ] Keep `getAdminUrl()`
- [ ] Keep `sendJsonSuccess()`
- [ ] Keep `sendJsonError()`
- [ ] Keep `isWooCommerceActive()`
- [ ] Keep `getPostData()`
- [ ] Keep `isPostRequest()`
- [ ] Keep `isAjaxRequest()`
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressEditManager
- [ ] Remove all common methods
- [ ] Keep `getLoginRedirectUrl()`
- [ ] Keep `userOwnsMinisite()`
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressNewMinisiteManager
- [ ] Remove all common methods
- [ ] Keep `getLoginRedirectUrl()`
- [ ] Keep `userCanCreateMinisite()`
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressUserManager
- [ ] Remove common methods (sanitization, nonce, URL, etc.)
- [ ] Keep all auth-specific methods
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressMinisiteManager
- [ ] Remove all common methods
- [ ] Add `WordPressManagerInterface` implementation if needed
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressListingManager
- [ ] Remove all common methods
- [ ] Add `WordPressManagerInterface` implementation if needed
- [ ] Update tests
- [ ] Verify all tests pass

### WordPressReviewManager (New)
- [ ] Create new class
- [ ] Extend `BaseWordPressManager`
- [ ] Implement `WordPressManagerInterface`
- [ ] Add Review-specific methods as needed
- [ ] Create tests
- [ ] Verify all tests pass

## Code Size Reduction Estimates

| Manager | Current Lines | Target Lines | Reduction | % Reduction |
|---------|--------------|-------------|-----------|-------------|
| BaseWordPressManager | 52 | 120-150 | +68-98 | N/A (growing) |
| WordPressVersionManager | 207 | 60-70 | -137-147 | 66-71% |
| WordPressPublishManager | 205 | 60-70 | -135-145 | 66-71% |
| WordPressEditManager | 152 | 30-40 | -112-122 | 74-80% |
| WordPressNewMinisiteManager | 151 | 30-40 | -111-121 | 74-80% |
| WordPressUserManager | 325 | 150-200 | -125-175 | 38-54% |
| WordPressMinisiteManager | 123 | 20-30 | -93-103 | 76-84% |
| WordPressListingManager | 70 | 20-30 | -40-50 | 57-71% |
| WordPressReviewManager | 0 | 20-30 | +20-30 | N/A (new) |
| **TOTAL** | **1,285** | **500-600** | **-685-785** | **53-61%** |

## Testing Requirements

### BaseWordPressManager Tests (New)
- [ ] Test all sanitization methods with null, empty, and valid inputs
- [ ] Test `isUserLoggedIn()` returns correct boolean
- [ ] Test `getCurrentUser()` returns null for logged-out users
- [ ] Test `getCurrentUser()` returns user for logged-in users
- [ ] Test `verifyNonce()` with valid and invalid nonces
- [ ] Test `createNonce()` generates valid nonces
- [ ] Test `getHomeUrl()` with and without path
- [ ] Test `getQueryVar()` with existing and missing vars
- [ ] Test `redirect()` calls termination handler
- [ ] Test `unslash()` removes slashes
- [ ] Test `escUrlRaw()` escapes URLs

### Manager-Specific Tests
Each manager should test:
- [ ] Only its specific methods
- [ ] That parent methods are accessible
- [ ] That interface compliance is maintained
- [ ] Integration with feature services

