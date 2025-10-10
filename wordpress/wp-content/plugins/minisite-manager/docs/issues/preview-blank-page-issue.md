# Preview Blank Page Issue

**Date**: October 10, 2025  
**Status**: Active  
**Priority**: High  
**URL**: `http://localhost:8000/account/sites/{id}/preview/{version}`

## Issue Description

The minisite preview functionality is returning a blank page despite:
- 200 HTTP response (successful)
- 3,257 bytes of content being returned
- Route being properly matched and handled
- All debugging showing the system is working

## Current Behavior

1. **Version History Page** → **Preview Button** → **Blank Page**
2. **URL Pattern**: `/account/sites/beaed79daf4a6da0df2b80c8/preview/5`
3. **Expected**: Full preview page with version information
4. **Actual**: Completely blank page

## Technical Analysis

### What's Working ✅
- Plugin activation/deactivation successful
- Rewrite rules properly flushed
- Route matching: `minisite_account=1`, `minisite_account_action=preview`
- `EditHooks::handleEditRoutes()` method being called
- `MinisiteEditFeature` properly initialized
- Template file `minisite-preview.twig` exists

### What's Failing ❌
- Page renders completely blank
- Headers already sent errors in logs
- No visible content despite 200 response

### Docker Logs Evidence
```
GET /account/sites/d6bc4c1b75bb99b2e3fd21b9/preview/3 HTTP/1.1" 200 3257
PHP Warning: Cannot modify header information - headers already sent by (output started at /var/www/html/wp-content/plugins/minisite-manager/src/Features/MinisiteEdit/Hooks/EditHooks.php:39)
```

## Route Flow Analysis

### Working Route: `/b/swift-transit/sydney`
- **Feature**: `MinisiteViewerFeature`
- **Query Vars**: `minisite=1`, `minisite_biz=swift-transit`, `minisite_loc=sydney`
- **Template**: `v2025/minisite.twig`
- **Status**: ✅ Working perfectly

### Broken Route: `/account/sites/{id}/preview/{version}`
- **Feature**: `MinisiteEditFeature`
- **Query Vars**: `minisite_account=1`, `minisite_account_action=preview`
- **Template**: `minisite-preview.twig`
- **Status**: ❌ Blank page

## What Did Not Work So Far

### 1. Hook Priority Fix ❌
- **Attempt**: Changed `MinisiteEditFeature` priority from 5 to 3
- **Reason**: Avoid conflict with `VersionManagementFeature` (priority 5)
- **Result**: Still blank page

### 2. Missing Template Creation ❌
- **Attempt**: Created `templates/timber/views/minisite-preview.twig`
- **Reason**: Template was missing, causing silent failure
- **Result**: Template exists but page still blank

### 3. Debugging Output ❌
- **Attempt**: Added HTML comments to trace execution
- **Reason**: Verify hooks are being called
- **Result**: Confirmed hooks are called but caused header errors

### 4. Feature Registry Verification ❌
- **Attempt**: Confirmed `MinisiteEditFeature` is in `FeatureRegistry`
- **Reason**: Ensure feature is being initialized
- **Result**: Feature is registered but still blank page

### 5. Plugin File Verification ❌
- **Attempt**: Confirmed new plugin file (1,146 bytes) vs old (48,669 bytes)
- **Reason**: Ensure new system is active
- **Result**: New system is active but still blank page

### 6. Rewrite Rules Flush ❌
- **Attempt**: Deactivated/reactivated plugin, flushed permalinks
- **Reason**: Ensure rewrite rules are properly registered
- **Result**: Rules are working (200 response) but still blank page

## Root Cause Hypothesis

The issue appears to be that:
1. **The route is being matched correctly** (200 response)
2. **The hooks are being called** (debug output confirmed)
3. **But something is preventing the template from rendering properly**

Possible causes:
- **Old plugin system still interfering** despite being in `delete_me/`
- **WordPress caching** of old plugin code
- **Template rendering issue** in `EditRenderer::renderPreview()`
- **Service layer failure** in `EditService::getMinisiteForPreview()`

## Next Steps

1. **Remove debugging output** to eliminate header errors
2. **Check if old plugin file is still being loaded** somehow
3. **Verify template rendering** in `EditRenderer`
4. **Test service layer** in `EditService`
5. **Compare working vs broken route handling** in detail

## Files Involved

- `src/Features/MinisiteEdit/MinisiteEditFeature.php`
- `src/Features/MinisiteEdit/Hooks/EditHooks.php`
- `src/Features/MinisiteEdit/Controllers/EditController.php`
- `src/Features/MinisiteEdit/Services/EditService.php`
- `src/Features/MinisiteEdit/Rendering/EditRenderer.php`
- `templates/timber/views/minisite-preview.twig`
