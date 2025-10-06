# VersionManagement Feature

This document provides an overview of the VersionManagement feature and instructions for testing its functionality.

## Overview

The VersionManagement feature handles version control for minisites, allowing users to:
- View version history
- Create draft versions
- Publish versions
- Rollback to previous versions

## Architecture

The feature follows the established patterns from Authentication and MinisiteDisplay features:

- **Commands**: Data transfer objects for version operations
- **Handlers**: Execute commands and delegate to services
- **Services**: Contain business logic for version management
- **Controllers**: Orchestrate the display flow
- **Hooks**: WordPress integration and routing
- **HTTP Components**: Request/response handling
- **Rendering**: Template rendering with Timber/Twig
- **WordPress Utilities**: WordPress-specific function wrappers

## Routes

The feature integrates with the existing WordPress rewrite system:

### Version History Page
- **URL**: `/account/sites/{site_id}/versions`
- **Method**: GET
- **Description**: Displays version history for a specific minisite
- **Authentication**: Required (user must own the minisite)

### AJAX Endpoints

#### Create Draft Version
- **Action**: `wp_ajax_minisite_create_draft`
- **Method**: POST
- **Description**: Creates a new draft version from form data
- **Authentication**: Required

#### Publish Version
- **Action**: `wp_ajax_minisite_publish_version`
- **Method**: POST
- **Description**: Publishes a draft version (atomic operation)
- **Authentication**: Required

#### Rollback Version
- **Action**: `wp_ajax_minisite_rollback_version`
- **Method**: POST
- **Description**: Creates a rollback draft from a previous version
- **Authentication**: Required

## Testing Instructions

### Prerequisites

1. **WordPress Environment**: Ensure WordPress is running with the plugin activated
2. **User Account**: Create a test user account
3. **Minisite**: Create at least one minisite owned by the test user
4. **Database**: Ensure the minisite_versions table exists

### Manual Testing Steps

#### 1. Test Version History Page

1. **Login** to WordPress with a user account that owns a minisite
2. **Navigate** to `/account/sites/{site_id}/versions` (replace `{site_id}` with actual minisite ID)
3. **Verify** the page loads and displays version history
4. **Check** that only the minisite owner can access the page

**Expected Results:**
- Page loads successfully
- Version history is displayed
- User authentication is enforced
- Access control works (non-owners are redirected)

#### 2. Test Create Draft Version (AJAX)

1. **Prepare** a form with version data:
   ```javascript
   const formData = new FormData();
   formData.append('action', 'minisite_create_draft');
   formData.append('nonce', 'your-nonce-here');
   formData.append('site_id', 'your-site-id');
   formData.append('label', 'Test Version');
   formData.append('version_comment', 'Test comment');
   formData.append('seo_title', 'SEO Title');
   formData.append('brand_name', 'Brand Name');
   // ... other form fields
   ```

2. **Send** AJAX request:
   ```javascript
   fetch(ajaxurl, {
       method: 'POST',
       body: formData
   })
   .then(response => response.json())
   .then(data => console.log(data));
   ```

**Expected Results:**
- Returns JSON success response with version details
- New draft version is created in database
- Version number is incremented correctly

#### 3. Test Publish Version (AJAX)

1. **Ensure** you have a draft version to publish
2. **Prepare** AJAX request:
   ```javascript
   const formData = new FormData();
   formData.append('action', 'minisite_publish_version');
   formData.append('nonce', 'your-nonce-here');
   formData.append('site_id', 'your-site-id');
   formData.append('version_id', 'version-id-to-publish');
   ```

3. **Send** AJAX request

**Expected Results:**
- Returns JSON success response
- Version status changes to 'published'
- Previous published version becomes 'draft'
- Minisite data is updated with published version data
- Database transaction completes successfully

#### 4. Test Rollback Version (AJAX)

1. **Ensure** you have a published version to rollback from
2. **Prepare** AJAX request:
   ```javascript
   const formData = new FormData();
   formData.append('action', 'minisite_rollback_version');
   formData.append('nonce', 'your-nonce-here');
   formData.append('site_id', 'your-site-id');
   formData.append('source_version_id', 'version-id-to-rollback-from');
   ```

3. **Send** AJAX request

**Expected Results:**
- Returns JSON success response with new rollback version details
- New draft version is created with data from source version
- Version number is incremented correctly
- All version data is copied correctly

### Error Testing

#### 1. Test Authentication Errors

1. **Logout** from WordPress
2. **Try** to access version history page
3. **Try** to send AJAX requests

**Expected Results:**
- Redirected to login page for page access
- JSON error responses for AJAX requests

#### 2. Test Access Control Errors

1. **Login** with a user who doesn't own the minisite
2. **Try** to access version history page
3. **Try** to send AJAX requests

**Expected Results:**
- Redirected to sites page
- JSON error responses for AJAX requests

#### 3. Test Invalid Data Errors

1. **Send** AJAX requests with missing required fields
2. **Send** AJAX requests with invalid nonces
3. **Send** AJAX requests with non-existent site/version IDs

**Expected Results:**
- JSON error responses with appropriate error messages
- No database changes made

### Database Testing

#### 1. Verify Version Creation

```sql
SELECT * FROM wp_minisite_versions 
WHERE minisite_id = 'your-site-id' 
ORDER BY version_number DESC;
```

#### 2. Verify Publishing Transaction

```sql
-- Check that only one version is published
SELECT COUNT(*) FROM wp_minisite_versions 
WHERE minisite_id = 'your-site-id' AND status = 'published';

-- Check that minisite data is updated
SELECT _minisite_current_version_id FROM wp_minisites 
WHERE id = 'your-site-id';
```

### Integration Testing

#### 1. Test with Existing Features

1. **Create** a minisite using the existing minisite creation flow
2. **Edit** the minisite and create versions
3. **Verify** that version management works with existing minisite data

#### 2. Test Template Integration

1. **Ensure** Timber/Twig is available
2. **Verify** that version history page renders correctly
3. **Test** fallback rendering when Timber is not available

### Performance Testing

#### 1. Test with Large Version History

1. **Create** many versions for a minisite
2. **Test** version history page loading
3. **Verify** performance is acceptable

#### 2. Test Concurrent Operations

1. **Simulate** multiple users creating versions simultaneously
2. **Verify** version numbering remains consistent
3. **Test** database transaction integrity

## Troubleshooting

### Common Issues

1. **404 Errors**: Ensure rewrite rules are flushed (`wp-admin > Settings > Permalinks > Save Changes`)
2. **AJAX Errors**: Check nonce generation and WordPress AJAX configuration
3. **Database Errors**: Verify table structure and permissions
4. **Template Errors**: Check Timber/Twig availability and template files

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Files

Check WordPress debug log for error messages:
- `/wp-content/debug.log`

## API Reference

### Commands

- `ListVersionsCommand`: Request to list versions for a minisite
- `CreateDraftCommand`: Request to create a new draft version
- `PublishVersionCommand`: Request to publish a version
- `RollbackVersionCommand`: Request to create a rollback version

### Services

- `VersionService`: Main business logic for version operations

### Controllers

- `VersionController`: Orchestrates version management operations

### WordPress Integration

- `VersionHooks`: Registers WordPress hooks and handles routing
- `VersionHooksFactory`: Creates hooks with all dependencies
- `WordPressVersionManager`: Wraps WordPress functions for testability
