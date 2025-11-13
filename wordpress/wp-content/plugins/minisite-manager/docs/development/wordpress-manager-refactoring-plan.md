# WordPress Manager Refactoring Plan

## Overview
This document outlines the plan to eliminate code duplication across WordPress managers by moving common methods to `BaseWordPressManager`.

## Current State Analysis

### Code Duplication Metrics
- **Total duplicated lines**: ~1,000+ lines across 7 managers
- **Common methods duplicated**: 10+ methods in each manager
- **BaseWordPressManager**: Only 52 lines (only has `redirect()`)
- **Average manager size**: 150-200 lines (should be 50-70 lines)

### Managers Affected
1. `WordPressVersionManager` (207 lines) → Target: ~50-70 lines
2. `WordPressPublishManager` (205 lines) → Target: ~50-70 lines
3. `WordPressEditManager` (152 lines) → Target: ~50-70 lines
4. `WordPressNewMinisiteManager` (151 lines) → Target: ~50-70 lines
5. `WordPressUserManager` (325 lines) → Target: ~100-150 lines (has auth-specific methods)
6. `WordPressMinisiteManager` (123 lines) → Target: ~50-70 lines
7. `WordPressListingManager` (70 lines) → Target: ~50-70 lines
8. **NEW**: `WordPressReviewManager` → Create: ~50-70 lines

## Methods to Move to BaseWordPressManager

### Category 1: Sanitization Methods (Required by Interface)
These are required by `WordPressManagerInterface` and should be in base class:

```php
/**
 * Sanitize text field
 * Standardizes null handling and wp_unslash usage
 */
public function sanitizeTextField(?string $text): string

/**
 * Sanitize textarea field
 */
public function sanitizeTextareaField(?string $text): string

/**
 * Sanitize URL field
 */
public function sanitizeUrl(?string $url): string

/**
 * Sanitize email field
 */
public function sanitizeEmail(?string $email): string
```

**Standardization Decision**: Use `wp_unslash()` in all sanitization methods for consistency.

### Category 2: User Authentication Methods
```php
/**
 * Check if user is logged in
 */
public function isUserLoggedIn(): bool

/**
 * Get current user
 * Standardizes null check for user with ID > 0
 */
public function getCurrentUser(): ?object
```

**Standardization Decision**: Always check `$user && $user->ID > 0` before returning.

### Category 3: Nonce Methods (Required by Interface)
```php
/**
 * Verify nonce
 * Standardizes boolean return (wp_verify_nonce returns int|false)
 */
public function verifyNonce(string $nonce, string $action): bool

/**
 * Create nonce
 */
public function createNonce(string $action): string
```

**Standardization Decision**: Always use `!== false` check for `verifyNonce()`.

### Category 4: URL and Query Methods
```php
/**
 * Get home URL
 */
public function getHomeUrl(string $path = ''): string

/**
 * Get query variable
 */
public function getQueryVar(string $var, mixed $default = ''): mixed
```

### Category 5: Common Utilities
```php
/**
 * Remove slashes from string
 */
public function unslash(string $string): string

/**
 * Escape URL for database storage
 */
public function escUrlRaw(string $url): string
```

## Refactored BaseWordPressManager Structure

```php
<?php

namespace Minisite\Features\BaseFeature\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * Base WordPress Manager Abstract Class
 *
 * SINGLE RESPONSIBILITY: Provide common WordPress operations for all managers
 * - Manages termination handler injection
 * - Provides consistent redirect behavior with termination
 * - Centralizes common WordPress function wrappers
 * - Standardizes sanitization, authentication, and nonce handling
 *
 * All WordPress managers should extend this class to ensure consistent
 * behavior and reduce code duplication.
 */
abstract class BaseWordPressManager implements WordPressManagerInterface
{
    protected TerminationHandlerInterface $terminationHandler;

    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        $this->terminationHandler = $terminationHandler;
    }

    // ===== REDIRECT METHODS =====

    public function redirect(string $url, int $status = 302): void
    {
        wp_redirect($url, $status);
        $this->terminationHandler->terminate();
    }

    // ===== SANITIZATION METHODS (Required by Interface) =====

    public function sanitizeTextField(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return sanitize_text_field(wp_unslash($text));
    }

    public function sanitizeTextareaField(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return sanitize_textarea_field(wp_unslash($text));
    }

    public function sanitizeUrl(?string $url): string
    {
        if ($url === null) {
            return '';
        }
        return esc_url_raw(wp_unslash($url));
    }

    public function sanitizeEmail(?string $email): string
    {
        if ($email === null) {
            return '';
        }
        return sanitize_email(wp_unslash($email));
    }

    // ===== USER AUTHENTICATION METHODS =====

    public function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function getCurrentUser(): ?object
    {
        $user = wp_get_current_user();
        return $user && $user->ID > 0 ? $user : null;
    }

    // ===== NONCE METHODS (Required by Interface) =====

    public function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    public function createNonce(string $action): string
    {
        return wp_create_nonce($action);
    }

    // ===== URL AND QUERY METHODS =====

    public function getHomeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    public function getQueryVar(string $var, mixed $default = ''): mixed
    {
        return get_query_var($var, $default);
    }

    // ===== COMMON UTILITIES =====

    public function unslash(string $string): string
    {
        return wp_unslash($string);
    }

    public function escUrlRaw(string $url): string
    {
        return esc_url_raw($url);
    }
}
```

**Estimated size**: ~120-150 lines (from 52 lines)

## Refactored Manager Examples

### WordPressVersionManager (After Refactoring)

```php
<?php

namespace Minisite\Features\VersionManagement\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress-specific utilities for version management
 *
 * SINGLE RESPONSIBILITY: Version-specific WordPress operations
 * - AJAX response handling
 * - HTTP header management
 * - JSON encoding utilities
 */
class WordPressVersionManager extends BaseWordPressManager implements WordPressManagerInterface
{
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

    // ===== VERSION-SPECIFIC METHODS ONLY =====

    /**
     * Send JSON success response
     */
    public function sendJsonSuccess(array $data = array(), int $statusCode = 200): void
    {
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send JSON error response
     */
    public function sendJsonError(string $message, int $statusCode = 400): void
    {
        wp_send_json_error($message, $statusCode);
    }

    /**
     * Set HTTP status header
     */
    public function setStatusHeader(int $code): void
    {
        status_header($code);
    }

    /**
     * Set no-cache headers
     */
    public function setNoCacheHeaders(): void
    {
        nocache_headers();
    }

    /**
     * Encode data as JSON
     */
    public function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return wp_json_encode($data, $options, $depth);
    }

    /**
     * Get home URL with optional scheme
     * Override to support scheme parameter
     */
    public function getHomeUrl(string $path = '', ?string $scheme = null): string
    {
        return home_url($path, $scheme);
    }
}
```

**Estimated size**: ~60-70 lines (from 207 lines) - **66% reduction**

### WordPressReviewManager (New - To Be Created)

```php
<?php

namespace Minisite\Features\ReviewManagement\WordPress;

use Minisite\Domain\Interfaces\WordPressManagerInterface;
use Minisite\Features\BaseFeature\WordPress\BaseWordPressManager;
use Minisite\Infrastructure\Http\TerminationHandlerInterface;

/**
 * WordPress-specific utilities for review management
 *
 * SINGLE RESPONSIBILITY: Review-specific WordPress operations
 */
class WordPressReviewManager extends BaseWordPressManager implements WordPressManagerInterface
{
    public function __construct(TerminationHandlerInterface $terminationHandler)
    {
        parent::__construct($terminationHandler);
    }

    // ===== REVIEW-SPECIFIC METHODS ONLY =====
    // Add any review-specific WordPress operations here
    // All common methods inherited from BaseWordPressManager
}
```

**Estimated size**: ~20-30 lines (new file)

## Migration Steps

### Phase 1: Prepare BaseWordPressManager
1. ✅ Update `BaseWordPressManager` to implement `WordPressManagerInterface`
2. ✅ Add all common sanitization methods
3. ✅ Add user authentication methods
4. ✅ Add nonce methods
5. ✅ Add URL/query methods
6. ✅ Add common utilities
7. ✅ Standardize `wp_unslash()` usage
8. ✅ Standardize null checks and return values
9. ✅ Add comprehensive PHPDoc comments
10. ✅ Run tests to ensure base class works

### Phase 2: Refactor Individual Managers (One at a time)

#### 2.1 WordPressVersionManager
1. Remove duplicated methods:
   - `isUserLoggedIn()` → Use parent
   - `getCurrentUser()` → Use parent
   - `getQueryVar()` → Use parent
   - `sanitizeTextField()` → Use parent
   - `sanitizeTextareaField()` → Use parent
   - `sanitizeUrl()` → Use parent
   - `sanitizeEmail()` → Use parent
   - `verifyNonce()` → Use parent
   - `createNonce()` → Use parent
   - `getHomeUrl()` → Override only if needed for scheme parameter
   - `redirect()` → Use parent
   - `unslash()` → Use parent
   - `escUrlRaw()` → Use parent

2. Keep only Version-specific methods:
   - `sendJsonSuccess()`
   - `sendJsonError()`
   - `setStatusHeader()`
   - `setNoCacheHeaders()`
   - `jsonEncode()`
   - `getHomeUrl()` (override with scheme parameter)

3. Update tests to verify parent methods are used
4. Run all VersionManagement tests

#### 2.2 WordPressPublishManager
1. Remove duplicated methods (same as Version)
2. Keep only Publish-specific methods:
   - `getCurrentUserId()`
   - `getAdminUrl()`
   - `sendJsonSuccess()`
   - `sendJsonError()`
   - `isWooCommerceActive()`
   - `getPostData()`
   - `isPostRequest()`
   - `isAjaxRequest()`

3. Update tests
4. Run all PublishMinisite tests

#### 2.3 WordPressEditManager
1. Remove duplicated methods
2. Keep only Edit-specific methods:
   - `getLoginRedirectUrl()`
   - `userOwnsMinisite()`

3. Update tests
4. Run all MinisiteEdit tests

#### 2.4 WordPressNewMinisiteManager
1. Remove duplicated methods
2. Keep only NewMinisite-specific methods:
   - `getLoginRedirectUrl()`
   - `userCanCreateMinisite()`

3. Update tests
4. Run all NewMinisite tests

#### 2.5 WordPressUserManager
1. Remove duplicated common methods
2. Keep Authentication-specific methods:
   - `signon()`
   - `createUser()`
   - `getUserBy()`
   - `setCurrentUser()`
   - `setAuthCookie()`
   - `logout()`
   - `isWpError()`
   - `isEmail()`
   - `sanitizeText()` (if different from `sanitizeTextField`)
   - `retrievePassword()`
   - `setStatusHeader()`
   - `getTemplatePart()`
   - `getWpQuery()`

3. Update tests
4. Run all Authentication tests

#### 2.6 WordPressMinisiteManager
1. Remove duplicated methods
2. Keep only MinisiteViewer-specific methods (if any)
3. Update to implement `WordPressManagerInterface` if needed
4. Update tests
5. Run all MinisiteViewer tests

#### 2.7 WordPressListingManager
1. Remove duplicated methods
2. Keep only Listing-specific methods (if any)
3. Update to implement `WordPressManagerInterface` if needed
4. Update tests
5. Run all MinisiteListing tests

### Phase 3: Create WordPressReviewManager
1. Create new `WordPressReviewManager` class
2. Extend `BaseWordPressManager`
3. Implement `WordPressManagerInterface`
4. Add Review-specific methods (if any)
5. Update `ReviewHooksFactory` to use new manager
6. Create tests
7. Run all ReviewManagement tests

### Phase 4: Verification and Cleanup
1. Run full test suite
2. Verify all managers implement interface correctly
3. Check for any remaining duplication
4. Update documentation
5. Code review

## Testing Strategy

### Unit Tests
- Test `BaseWordPressManager` methods in isolation
- Verify each manager's specific methods work
- Ensure parent method calls work correctly

### Integration Tests
- Verify managers work in real feature contexts
- Test that interface compliance is maintained
- Verify no breaking changes

### Test Files to Update
1. `tests/Unit/Features/BaseFeature/WordPress/BaseWordPressManagerTest.php` (new)
2. `tests/Unit/Features/VersionManagement/WordPress/WordPressVersionManagerTest.php`
3. `tests/Unit/Features/PublishMinisite/WordPress/WordPressPublishManagerTest.php`
4. `tests/Unit/Features/MinisiteEdit/WordPress/WordPressEditManagerTest.php`
5. `tests/Unit/Features/NewMinisite/WordPress/WordPressNewMinisiteManagerTest.php`
6. `tests/Unit/Features/Authentication/WordPress/WordPressUserManagerTest.php`
7. `tests/Unit/Features/MinisiteViewer/WordPress/WordPressMinisiteManagerTest.php`
8. `tests/Unit/Features/MinisiteListing/WordPress/WordPressListingManagerTest.php`
9. `tests/Unit/Features/ReviewManagement/WordPress/WordPressReviewManagerTest.php` (new)

## Breaking Changes

### None Expected
- All methods maintain same signatures
- Interface compliance maintained
- Only internal implementation changes

### Potential Issues
1. **Method visibility**: Ensure all moved methods remain `public`
2. **Return type consistency**: Verify all managers return same types
3. **Null handling**: Standardize null checks across all methods
4. **wp_unslash usage**: Ensure consistent application

## Success Metrics

### Code Reduction
- **Target**: Reduce total manager code by ~70%
- **Current**: ~1,200 lines across 7 managers
- **Target**: ~350-400 lines across 8 managers
- **Savings**: ~800-850 lines eliminated

### Maintainability
- Single source of truth for common operations
- Easier to update WordPress function wrappers
- Consistent behavior across all features
- Reduced test surface area

### Consistency
- Standardized null handling
- Consistent `wp_unslash()` usage
- Uniform return value handling
- Clear separation of concerns

## Timeline Estimate

- **Phase 1**: 2-3 hours (BaseWordPressManager refactoring + tests)
- **Phase 2**: 4-6 hours (7 managers × ~30-45 min each)
- **Phase 3**: 1-2 hours (Create WordPressReviewManager)
- **Phase 4**: 1-2 hours (Verification and cleanup)
- **Total**: 8-13 hours

## Risk Assessment

### Low Risk
- Methods are simple wrappers around WordPress functions
- No complex business logic involved
- Easy to test and verify
- Can be done incrementally

### Mitigation
- Refactor one manager at a time
- Run tests after each manager
- Keep old code commented until verified
- Create comprehensive test coverage first

## Next Steps

1. ✅ Create this refactoring plan document
2. ⏳ Review and approve plan
3. ⏳ Start with Phase 1 (BaseWordPressManager)
4. ⏳ Create comprehensive tests for base class
5. ⏳ Proceed with Phase 2 (one manager at a time)
6. ⏳ Create WordPressReviewManager
7. ⏳ Final verification and cleanup

