# Integration Testing Requirements

## Overview

This document lists all classes and methods that require **integration testing** due to their dependencies on external systems, WordPress functions, or complex integrations that cannot be properly mocked in unit tests.

## Current Testing Strategy

Our current unit tests use "coverage testing" - they verify method existence and basic functionality but do not test complex business logic or WordPress integration. This document identifies what needs **true integration testing** to ensure real functionality works correctly.

## Classes Requiring Integration Testing

### 1. WordPress Manager Classes
These classes wrap WordPress functions and need integration testing to verify they work correctly with actual WordPress.

#### Authentication/WordPress/WordPressUserManager.php
- **Methods requiring integration testing:**
  - `signon()` - WordPress authentication
  - `createUser()` - User creation with WordPress
  - `getUserBy()` - User lookup
  - `getCurrentUser()` - Current user retrieval
  - `setCurrentUser()` - User session management
  - `setAuthCookie()` - Cookie management
  - `logout()` - WordPress logout
  - `isUserLoggedIn()` - Session state checking
  - `isWpError()` - WordPress error handling
  - `isEmail()` - Email validation
  - `sanitizeText()` - Text sanitization
  - `sanitizeEmail()` - Email sanitization
  - `sanitizeUrl()` - URL sanitization
  - `unslash()` - Data unslashing
  - `verifyNonce()` - Nonce verification
  - `redirect()` - WordPress redirects
  - `getHomeUrl()` - URL generation
  - `getQueryVar()` - Query variable retrieval
  - `setStatusHeader()` - HTTP status headers
  - `getTemplatePart()` - Template loading
  - `getWpQuery()` - Query object access
  - `retrievePassword()` - Password reset

#### MinisiteListing/WordPress/WordPressListingManager.php
- **Methods requiring integration testing:**
  - `isUserLoggedIn()` - Session checking
  - `getCurrentUser()` - User retrieval
  - `currentUserCan()` - Capability checking
  - `listMinisitesByOwner()` - Database queries with $wpdb

#### MinisiteViewer/WordPress/WordPressMinisiteManager.php
- **Methods requiring integration testing:**
  - `getMinisiteBySlug()` - Database queries
  - `getPublishedVersion()` - Version retrieval
  - `getCurrentUser()` - User context
  - `currentUserCan()` - Permission checking

#### VersionManagement/WordPress/WordPressVersionManager.php
- **Methods requiring integration testing:**
  - `getCurrentUser()` - User context
  - `currentUserCan()` - Permission checking
  - `getMinisiteById()` - Database queries
  - `getVersionsByMinisiteId()` - Version queries
  - `publishVersion()` - Database transactions
  - `createVersion()` - Version creation
  - `rollbackToVersion()` - Version rollback

### 2. Renderer Classes
These classes use Timber/Twig templating and need integration testing to verify template rendering works correctly.

#### Authentication/Rendering/AuthRenderer.php
- **Methods requiring integration testing:**
  - `render()` - Timber template rendering with authentication templates

#### MinisiteListing/Rendering/ListingRenderer.php
- **Methods requiring integration testing:**
  - `renderListPage()` - Template rendering with minisite listing data
  - `registerTimberLocations()` - Timber location registration
  - `renderFallback()` - Fallback rendering when Timber fails

#### MinisiteViewer/Rendering/DisplayRenderer.php
- **Methods requiring integration testing:**
  - `renderMinisite()` - Public minisite rendering with Timber
  - `render404()` - 404 error page rendering
  - Fallback rendering when Timber renderer fails

#### VersionManagement/Rendering/VersionRenderer.php
- **Methods requiring integration testing:**
  - `renderVersionHistory()` - Version management UI rendering
  - Timber integration and fallback rendering

### 3. Factory Classes
These classes create complex object graphs and need integration testing to verify dependency injection works correctly.

#### Authentication/Hooks/AuthHooksFactory.php
- **Methods requiring integration testing:**
  - `create()` - Complete authentication system setup

#### MinisiteListing/Hooks/ListingHooksFactory.php
- **Methods requiring integration testing:**
  - `create()` - Listing system setup with $wpdb

#### MinisiteViewer/Hooks/DisplayHooksFactory.php
- **Methods requiring integration testing:**
  - `create()` - Display system setup

#### VersionManagement/Hooks/VersionHooksFactory.php
- **Methods requiring integration testing:**
  - `create()` - Version management system setup

### 4. Service Classes (Business Logic)
These classes contain complex business logic that should be tested with real data flows.

#### Authentication/Services/AuthService.php
- **Methods requiring integration testing:**
  - `login()` - Complete login flow
  - `register()` - Complete registration flow
  - `forgotPassword()` - Password reset flow
  - `isLoggedIn()` - Session state management
  - `getCurrentUser()` - User context retrieval
  - `logout()` - Complete logout flow

#### MinisiteListing/Services/MinisiteListingService.php
- **Methods requiring integration testing:**
  - `listMinisitesByOwner()` - Complete listing flow with permissions

#### MinisiteViewer/Services/MinisiteDisplayService.php
- **Methods requiring integration testing:**
  - `displayMinisite()` - Complete display flow with data loading

#### VersionManagement/Services/VersionService.php
- **Methods requiring integration testing:**
  - `listVersions()` - Version listing with permissions
  - `createVersion()` - Version creation workflow
  - `publishVersion()` - Publishing workflow with transactions
  - `rollbackToVersion()` - Rollback workflow
  - `copyVersion()` - Version copying workflow

### 5. Controller Classes (Integration Points)
These classes orchestrate multiple services and need integration testing to verify end-to-end flows.

#### Authentication/Controllers/AuthController.php
- **Methods requiring integration testing:**
  - `handleLogin()` - Complete login page flow
  - `handleRegister()` - Complete registration page flow
  - `handleForgotPassword()` - Complete password reset flow
  - `handleDashboard()` - Dashboard rendering with user context
  - `handleLogout()` - Complete logout flow

#### MinisiteListing/Controllers/ListingController.php
- **Methods requiring integration testing:**
  - `handleList()` - Complete listing page flow

#### MinisiteViewer/Controllers/MinisitePageController.php
- **Methods requiring integration testing:**
  - `handleDisplay()` - Complete minisite display flow

#### VersionManagement/Controllers/VersionController.php
- **Methods requiring integration testing:**
  - `handleVersions()` - Complete version management flow
  - `handleCreateVersion()` - Version creation flow
  - `handlePublishVersion()` - Version publishing flow
  - `handleRollbackVersion()` - Version rollback flow

### 6. Hook Classes (WordPress Integration)
These classes register WordPress hooks and need integration testing to verify they work correctly.

#### Authentication/Hooks/AuthHooks.php
- **Methods requiring integration testing:**
  - `register()` - WordPress hook registration
  - `handleAuthRoutes()` - Route handling with WordPress
  - `handleNotFound()` - 404 handling

#### MinisiteListing/Hooks/ListingHooks.php
- **Methods requiring integration testing:**
  - `register()` - Hook registration
  - `handleListingRoutes()` - Route handling

#### MinisiteViewer/Hooks/DisplayHooks.php
- **Methods requiring integration testing:**
  - `register()` - Hook registration
  - `handleDisplayRoutes()` - Public route handling

#### VersionManagement/Hooks/VersionHooks.php
- **Methods requiring integration testing:**
  - `register()` - Hook registration
  - `handleVersionRoutes()` - Version route handling

## Integration Testing Priority

### High Priority (Critical Functionality)
1. **Authentication Service** - Core user management
2. **WordPress Manager Classes** - WordPress integration
3. **Renderer Classes** - Template rendering
4. **Controller Classes** - End-to-end flows

### Medium Priority (Important Features)
1. **Service Classes** - Business logic
2. **Factory Classes** - Dependency injection

### Low Priority (Infrastructure)
1. **Hook Classes** - WordPress integration (can be tested manually)

## Testing Environment Requirements

### WordPress Environment
- Full WordPress installation
- Database with test data
- Proper user roles and capabilities
- Timber plugin installed and configured

### Test Data Requirements
- Test users with various roles
- Test minisites with different states
- Test versions with different statuses
- Test templates and assets

### Integration Test Structure
```
tests/Integration/
├── Authentication/
│   ├── AuthServiceIntegrationTest.php
│   ├── WordPressUserManagerIntegrationTest.php
│   └── AuthControllerIntegrationTest.php
├── MinisiteListing/
│   ├── ListingServiceIntegrationTest.php
│   └── ListingControllerIntegrationTest.php
├── MinisiteViewer/
│   ├── DisplayServiceIntegrationTest.php
│   └── DisplayControllerIntegrationTest.php
└── VersionManagement/
    ├── VersionServiceIntegrationTest.php
    └── VersionControllerIntegrationTest.php
```

## Notes

- **Current unit tests are "coverage tests"** - they verify method existence and basic functionality
- **Integration tests should test real functionality** - actual WordPress integration, database operations, template rendering
- **Focus on business logic flows** - complete user journeys, not individual method calls
- **Test with real data** - actual WordPress users, minisites, versions, not mocks
- **Verify end-to-end functionality** - from HTTP request to rendered output

## Implementation Timeline

1. **Phase 1**: WordPress Manager classes (critical for all features)
2. **Phase 2**: Service classes (business logic)
3. **Phase 3**: Controller classes (end-to-end flows)
4. **Phase 4**: Renderer classes (template integration)
5. **Phase 5**: Factory and Hook classes (infrastructure)

This document should be updated as new features are added or existing features are modified.
