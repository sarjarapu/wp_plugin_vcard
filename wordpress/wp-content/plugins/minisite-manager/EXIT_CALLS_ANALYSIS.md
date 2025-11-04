# Exit Calls Analysis

All `exit` calls found in `src/` directory:

## 1. Hooks (Template Redirect Handlers) - 6 locations
These exit after routing to controllers to prevent WordPress from loading default templates.

### `src/Features/MinisiteEdit/Hooks/EditHooks.php`
- **Line 66**: After `handleVersionSpecificPreview()` (preview route)
  - Note: Edit route uses TerminationHandlerInterface, but preview still uses exit

### `src/Features/Authentication/Hooks/AuthHooks.php`
- **Line 82**: After routing to auth controller methods
- **Line 94**: After rendering 404 page

### `src/Features/MinisiteListing/Hooks/ListingHooks.php`
- **Line 64**: After `handleList()` (sites route)

### `src/Features/VersionManagement/Hooks/VersionHooks.php`
- **Line 41**: After `handleListVersions()` (versions route)

### `src/Features/MinisiteViewer/Hooks/ViewHooks.php`
- **Line 73**: After `handleView()` (view route)

### `src/Features/PublishMinisite/Hooks/PublishHooks.php`
- **Line 85**: After `handlePublish()` (publish route)

### `src/Features/NewMinisite/Hooks/NewMinisiteHooks.php`
- **Line 55**: After `handleNewMinisite()` (new route)

### `src/Features/ConfigurationManagement/Hooks/ConfigurationManagementHooks.php`
- **Line 95**: After redirecting in admin page handler

## 2. WordPress Manager Redirect Methods - 5 locations
These exit after calling `wp_redirect()` (WordPress best practice).

### `src/Features/MinisiteEdit/WordPress/WordPressEditManager.php`
- **Line 135**: In `redirect()` method

### `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php`
- **Line 124**: In `redirect()` method

### `src/Features/PublishMinisite/WordPress/WordPressPublishManager.php`
- **Line 70**: In `redirect()` method

### `src/Features/NewMinisite/WordPress/WordPressNewMinisiteManager.php`
- **Line 135**: In `redirect()` method

### `src/Core/AdminMenuManager.php`
- **Line 96**: After redirecting to dashboard
- **Line 107**: After redirecting to sites page

## 3. Response Handlers - 6 locations
These exit after redirecting (duplicate exit calls - they call redirect() which already exits).

### `src/Features/Authentication/Http/AuthResponseHandler.php`
- **Line 28**: In `redirect()` method

### `src/Features/MinisiteListing/Http/ListingResponseHandler.php`
- **Line 33**: In `redirectToLogin()` method
- **Line 42**: In `redirectToSites()` method
- **Line 51**: In `redirect()` method

### `src/Features/VersionManagement/Http/VersionResponseHandler.php`
- **Line 43**: In `redirectToLogin()` method
- **Line 52**: In `redirectToSites()` method

## 4. Termination Handler (Infrastructure) - 1 location
This is the abstraction we created - it's intentionally here.

### `src/Infrastructure/Http/WordPressTerminationHandler.php`
- **Line 18**: In `terminate()` method - This is the production implementation

---

## Summary by Category:

1. **Hooks (template_redirect handlers)**: 8 locations
   - These exit after routing/rendering to prevent WordPress template loading

2. **WordPress Manager redirect methods**: 5 locations  
   - These exit after `wp_redirect()` (WordPress standard)

3. **Response Handler redirect methods**: 6 locations
   - **Problem**: These call `redirect()` which already exits, so these are redundant!

4. **Termination Handler**: 1 location
   - This is the abstraction - intentionally uses exit

## Total: 20 actual exit calls (excluding the TerminationHandler abstraction)

---

## Recommendations:

1. **Response Handlers** (6 locations) - Remove redundant exits since they call `redirect()` which already exits
2. **Hooks** (8 locations) - Consider using TerminationHandlerInterface pattern like EditController
3. **WordPress Managers** (5 locations) - Keep these (WordPress standard after wp_redirect)
4. **TerminationHandler** (1 location) - Keep this (it's the abstraction)

