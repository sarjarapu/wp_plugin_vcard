# Minisite Settings - AJAX Implementation Notes

## Current Implementation Pattern

The Minisite Manager plugin **does NOT use REST API endpoints**. Instead, it uses **WordPress AJAX handlers** following the WordPress standard pattern.

### Existing Example: Slug Availability Check

The slug availability feature demonstrates the pattern:

**Frontend** (`account-sites-publish.twig`):
```javascript
fetch('{{ function("admin_url", "admin-ajax.php") }}', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    action: 'check_slug_availability',
    nonce: '{{ function("wp_create_nonce", "check_slug_availability") }}',
    business_slug: businessSlug,
    location_slug: locationSlug
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    // Handle success
  } else {
    // Handle error
  }
});
```

**Backend** (`PublishHooks.php`):
```php
public function register(): void
{
    add_action('wp_ajax_check_slug_availability', [$this->publishController, 'handleCheckSlugAvailability']);
}
```

**Controller** (`PublishController.php`):
```php
public function handleCheckSlugAvailability(): void
{
    // Check authentication
    if (!$this->wordPressManager->isUserLoggedIn()) {
        $this->wordPressManager->sendJsonError('Not authenticated', 401);
        return;
    }

    // Verify nonce
    if (!$this->formSecurityHelper->verifyNonce('check_slug_availability', 'nonce')) {
        $this->wordPressManager->sendJsonError('Security check failed', 403);
        return;
    }

    // Get and sanitize input
    $businessSlug = $this->wordPressManager->sanitizeTextField(
        $this->wordPressManager->getPostData('business_slug')
    );

    // Process request
    $result = $this->publishService->getSlugAvailabilityService()->checkAvailability(...);

    // Send response
    $this->wordPressManager->sendJsonSuccess([
        'available' => $result->available,
        'message' => $result->message,
    ]);
}
```

---

## Settings Feature Implementation Pattern

### 1. Register AJAX Handlers

In `SettingsHooks.php`:
```php
public function register(): void
{
    // Online/Offline toggle
    add_action('wp_ajax_toggle_minisite_online_offline', [$this->settingsController, 'handleToggleOnlineOffline']);
    
    // Delete minisite
    add_action('wp_ajax_delete_minisite', [$this->settingsController, 'handleDeleteMinisite']);
    
    // Transfer ownership (admin only)
    add_action('wp_ajax_transfer_minisite_ownership', [$this->settingsController, 'handleTransferOwnership']);
    
    // Assign/remove editors (admin only)
    add_action('wp_ajax_assign_minisite_editor', [$this->settingsController, 'handleAssignEditor']);
    
    // Get subscription status
    add_action('wp_ajax_get_minisite_subscription', [$this->settingsController, 'handleGetSubscription']);
}
```

### 2. Controller Methods

Each handler method in `SettingsController.php` should follow this pattern:

```php
public function handleToggleOnlineOffline(): void
{
    // 1. Check authentication
    if (!$this->wordPressManager->isUserLoggedIn()) {
        $this->wordPressManager->sendJsonError('Not authenticated', 401);
        return;
    }

    // 2. Verify nonce
    if (!$this->formSecurityHelper->verifyNonce('toggle_minisite_online_offline', 'nonce')) {
        $this->wordPressManager->sendJsonError('Security check failed', 403);
        return;
    }

    // 3. Get and sanitize input
    $siteId = $this->wordPressManager->sanitizeTextField(
        $this->wordPressManager->getPostData('site_id')
    );
    $online = $this->wordPressManager->getPostData('online') === 'true' || 
              $this->wordPressManager->getPostData('online') === true;

    // 4. Check permissions
    if (!$this->settingsService->canToggleOnlineOffline($siteId)) {
        $this->wordPressManager->sendJsonError('Permission denied', 403);
        return;
    }

    // 5. Process request
    try {
        $result = $this->settingsService->toggleOnlineOffline($siteId, $online);
        
        // 6. Send success response
        $this->wordPressManager->sendJsonSuccess([
            'online' => $result->online,
            'message' => $result->message,
        ]);
    } catch (\Exception $e) {
        // 7. Handle errors
        $this->logger->error('Failed to toggle online/offline', [
            'site_id' => $siteId,
            'error' => $e->getMessage(),
        ]);
        $this->wordPressManager->sendJsonError(
            'Failed to toggle online/offline: ' . $e->getMessage(),
            500
        );
    }
}
```

### 3. Frontend Implementation

In `account-sites-settings.twig`:

```javascript
// Toggle online/offline
function toggleOnlineOffline(siteId, online) {
  fetch('{{ function("admin_url", "admin-ajax.php") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      action: 'toggle_minisite_online_offline',
      nonce: '{{ function("wp_create_nonce", "toggle_minisite_online_offline") }}',
      site_id: siteId,
      online: online
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Update UI
      showNotification(data.data.message, 'success');
      updateOnlineOfflineUI(data.data.online);
    } else {
      showNotification(data.data || 'Failed to update status', 'error');
    }
  })
  .catch(error => {
    showNotification('Network error: ' + error.message, 'error');
  });
}
```

---

## About "mml/v1" in Documentation

The documentation (`docs/project/listing-minisites.md`) mentions REST API namespace `mml/v1` (Minisite Manager API v1), but this is **NOT currently implemented** in the codebase. It appears to be a **future/planned** API design.

**Current Status**:
- ❌ No REST API endpoints registered (`register_rest_route` calls)
- ❌ No `rest_api_init` hooks
- ✅ WordPress AJAX handlers are used instead
- ✅ Pattern is consistent across all features

**Recommendation**: Continue using WordPress AJAX handlers for Settings feature to maintain consistency. If REST API is desired in the future, it can be added as an additional layer without breaking existing functionality.

---

## Security Checklist

Every AJAX handler must:

1. ✅ **Check authentication**: `isUserLoggedIn()`
2. ✅ **Verify nonce**: `verifyNonce('action_name', 'nonce')`
3. ✅ **Sanitize input**: `sanitizeTextField()`, `sanitize_text_field()`
4. ✅ **Check permissions**: Custom capability checks
5. ✅ **Validate data**: Type checking, format validation
6. ✅ **Log errors**: Use logger for debugging
7. ✅ **Return JSON**: `sendJsonSuccess()` or `sendJsonError()`

---

## Benefits of AJAX Pattern

1. **Consistency**: Matches existing plugin architecture
2. **Simplicity**: No REST API routing setup needed
3. **WordPress Native**: Uses built-in WordPress functions
4. **Security**: Nonce verification built-in
5. **Flexibility**: Easy to add new handlers

---

## Migration Path (If REST API Needed Later)

If REST API is desired in the future, it can be added alongside AJAX handlers:

1. Create REST API controller class
2. Register routes in `rest_api_init` hook
3. Reuse existing service classes
4. Frontend can choose AJAX or REST API
5. Gradually migrate features over time

This approach allows incremental adoption without breaking existing functionality.

---

**Last Updated**: 2025-01-XX
**Pattern**: WordPress AJAX Handlers (not REST API)

