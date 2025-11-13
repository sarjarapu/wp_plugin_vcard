# Minisite Manager Refactor Tracking

## Overview
The `minisite-manager.php` file requires major refactoring to complete the migration from old controller-based architecture to the new feature-based architecture. This document tracks all the work needed to clean up and modernize the main plugin file.

## Current Status
- **File**: `minisite-manager.php` (1,415 lines)
- **Architecture**: Hybrid (old controllers + new features)
- **Priority**: Medium (system is functional but needs cleanup)

## ✅ Completed Tasks

### 1. Feature Integration
- [x] AdminMenuManager integration
- [x] New feature initialization (Authentication, MinisiteViewer, MinisiteListing, VersionManagement)
- [x] Route delegation to new features
- [x] MinisiteViewer feature integration for public display

### 2. Commented Out Functions (Ready for Removal)
- [x] Old controller imports commented out
- [x] Version management AJAX handlers commented out
- [x] Minisite creation AJAX handler commented out
- [x] Publishing AJAX handler commented out
- [x] Export/Import AJAX handlers commented out
- [x] WooCommerce order creation commented out

## 🔄 In Progress Tasks

### 1. Remove Old Controller Dependencies
- [ ] **Remove commented imports** (lines 20-26)
  - [ ] `SubscriptionController`
  - [ ] `AuthController`
  - [ ] `MinisitePageController`
  - [ ] `NewMinisiteController`
  - [ ] `SitesController`
  - [ ] `VersionController`

### 2. Migrate Remaining AJAX Handlers
- [ ] **Slug Management** (lines 1027-1065)
  - [ ] `wp_ajax_check_slug_availability` → Move to `MinisiteListingFeature`
  - [ ] `wp_ajax_reserve_slug` → Move to `MinisiteListingFeature`
  - [ ] Remove `NewMinisiteController` references

- [ ] **Subscription Activation** (lines 1173-1193)
  - [ ] `wp_ajax_activate_minisite_subscription` → Move to `MinisiteListingFeature`
  - [ ] Remove `NewMinisiteController` references

- [ ] **WooCommerce Integration** (lines 1195-1250)
  - [ ] `woocommerce_checkout_create_order` → Move to `MinisiteListingFeature` or new `MinisiteCommerceFeature`
  - [ ] `woocommerce_checkout_create_order_line_item` → Move to `MinisiteListingFeature` or new `MinisiteCommerceFeature`
  - [ ] `woocommerce_order_status_completed` → Move to `MinisiteListingFeature` or new `MinisiteCommerceFeature`
  - [ ] Remove `NewMinisiteController` references

- [ ] **Admin Subscription Management** (lines 1252-1270)
  - [ ] `wp_ajax_activate_minisite_subscription_admin` → Move to `MinisiteListingFeature` or new `MinisiteAdminFeature`
  - [ ] Admin menu for subscription management → Move to `MinisiteListingFeature` or new `MinisiteAdminFeature`
  - [ ] Remove `SubscriptionController` references

### 3. Complete Route Migration
- [ ] **Add Missing Routes**
  - [ ] Add `settings` route to `RewriteRegistrar.php`
  - [ ] Add `settings` to `$newFeatureRoutes` array (line 497)

- [ ] **Create Missing Features**
  - [ ] Create `SettingsFeature` for `/account/sites/{id}/settings`
  - [ ] Create `MinisiteEditFeature` for `/account/sites/{id}/edit`
  - [ ] Create `MinisitePreviewFeature` for `/account/sites/{id}/preview`
  - [ ] Create `MinisitePublishFeature` for `/account/sites/{id}/publish`
  - [ ] Create `MinisiteCommerceFeature` for WooCommerce integration
  - [ ] Create `MinisiteAdminFeature` for admin-specific functionality

### 4. WooCommerce Integration (Never Migrated)
- [ ] **Create MinisiteCommerceFeature**
  - [ ] Move WooCommerce checkout handlers
  - [ ] Move WooCommerce order completion handlers
  - [ ] Move WooCommerce cart item metadata handlers
  - [ ] Implement subscription activation via WooCommerce
  - [ ] Handle minisite data transfer from cart to order

- [ ] **Create MinisiteSubscriptionFeature**
  - [ ] Move subscription management functionality
  - [ ] Handle subscription activation/deactivation
  - [ ] Manage subscription status and billing
  - [ ] Integrate with WooCommerce subscriptions
  - [ ] Handle subscription lifecycle events

### 5. Settings Feature Implementation
- [ ] **Create SettingsFeature**
  - [ ] Implement minisite settings page
  - [ ] Handle ownership management
  - [ ] Handle editor assignment
  - [ ] Handle online/offline toggle
  - [ ] Handle subscription management
  - [ ] Handle billing information

### 6. Clean Up Route Handling
- [ ] **Simplify template_redirect function**
  - [ ] Remove old controller instantiation code
  - [ ] Remove fallback error messages for old controllers
  - [ ] Clean up switch statement in account handling

- [ ] **Remove Old Controller References**
  - [ ] Remove `SitesController` references in edit/preview cases
  - [ ] Remove `NewMinisiteController` references in new/publish cases
  - [ ] Remove `MinisitePageController` references in minisite display

### 7. Remove Commented Code
- [ ] **Delete Commented AJAX Handlers**
  - [ ] Remove commented version management handlers (lines 803-961)
  - [ ] Remove commented minisite creation handler (lines 1003-1025)
  - [ ] Remove commented publishing handler (lines 1067-1087)
  - [ ] Remove commented export/import handlers (lines 1089-1149)
  - [ ] Remove commented WooCommerce order creation (lines 1151-1171)

## 🔧 Related Refactoring: WordPressManager Classes

### WordPressManager Interface & Implementation Refactoring

**Status**: TODO - Tracked for future refactoring
**Priority**: Medium
**Created**: November 2025

#### Current Issues
- [ ] **Code Duplication**: Significant duplication across WordPress manager implementations
  - `WordPressEditManager`, `WordPressNewMinisiteManager`, `WordPressPublishManager` all implement `WordPressManagerInterface`
  - Methods like `updateBusinessInfo()`, `updateCoordinates()`, `updateTitle()` are duplicated
  - Many methods are not relevant to all features (e.g., `PublishMinisite` doesn't need version-related methods)

- [ ] **Interface Bloat**: `WordPressManagerInterface` requires all managers to implement methods they don't need
  - Methods like `getNextVersionNumber()`, `saveVersion()` are irrelevant to publish feature
  - Methods like `updateBusinessInfo()` might not be needed in all features
  - Forces implementation of stub methods that do nothing or return default values

- [ ] **Maintenance Burden**:
  - Adding new methods to interface requires updating all implementations
  - Risk of forgetting to update one of the managers
  - Difficult to understand which methods are actually used per feature

#### Proposed Solution
- [ ] **Create Base WordPress Manager Class**:
  - Common WordPress operations (sanitization, nonces, URLs, etc.)
  - Shared repository access patterns
  - Transaction management

- [ ] **Split Interface into Smaller, Focused Interfaces**:
  - `WordPressManagerInterface` - Core WordPress operations (sanitize, nonce, URLs)
  - `WordPressRepositoryInterface` - Repository access patterns
  - `WordPressVersionManagerInterface` - Version-specific operations (optional)
  - `WordPressBusinessInfoInterface` - Business info updates (optional)

- [ ] **Use Composition Instead of Inheritance**:
  - Managers can compose only the interfaces they need
  - No forced implementation of irrelevant methods
  - Better separation of concerns

- [ ] **Example Structure**:
  ```php
  // Base class with common operations
  abstract class BaseWordPressManager implements WordPressManagerInterface

  // Edit manager uses version operations
  class WordPressEditManager extends BaseWordPressManager
    implements WordPressVersionManagerInterface

  // Publish manager doesn't need version operations
  class WordPressPublishManager extends BaseWordPressManager
    // Only implements what it needs
  ```

#### Files Affected
- `src/Domain/Interfaces/WordPressManagerInterface.php`
- `src/Features/MinisiteEdit/WordPress/WordPressEditManager.php`
- `src/Features/NewMinisite/WordPress/WordPressNewMinisiteManager.php`
- `src/Features/PublishMinisite/WordPress/WordPressPublishManager.php`
- Potentially all other feature WordPress managers

#### Benefits
- ✅ Reduced code duplication
- ✅ Clearer intent - each manager only implements what it needs
- ✅ Easier maintenance - changes to one manager don't affect others unnecessarily
- ✅ Better type safety - interfaces describe actual capabilities
- ✅ Easier to understand which operations are available per feature

#### Risks
- ⚠️ Breaking changes to existing code
- ⚠️ Need to update all feature implementations
- ⚠️ Migration effort across all features
- ⚠️ Need thorough testing to ensure nothing breaks

#### Estimated Effort
- **Planning & Design**: 1-2 days
- **Implementation**: 3-5 days
- **Testing & Migration**: 2-3 days
- **Total**: ~1-2 weeks

**Note**: This is tracked for future refactoring. Not urgent but should be addressed before adding more WordPress managers.

---

## 🔧 Related Refactoring: CreateBase.php

### CreateBase.php Refactoring Needs
- [ ] **File Location**: `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php`
- [ ] **Current Issues**:
  - [ ] Large monolithic migration class
  - [ ] Mixed responsibilities (schema creation, data migration, cleanup)
  - [ ] Hard to maintain and extend
  - [ ] Difficult to test individual components
- [ ] **Why We Still Need CreateBase.php (Blockers to Removal)**:
  - [ ] **Legacy table creation**: still provisions `wp_minisites`, `minisite_bookmarks`, `minisite_payments`, `minisite_payment_history`, and `minisite_reservations` via SQL loaders. These tables have not been ported to Doctrine migrations yet.
  - [ ] **Foreign key + event setup**: owns `addForeignKeyIfNotExists()` calls and the MySQL event `event_purge_reservations.sql`; no replacement exists in Doctrine migrations or separate installers.
  - [ ] **Dev/test seed data**: `seedTestData()` inserts the four sample minisites and bootstraps initial versions. There is no dedicated `MinisiteSeederService` yet, so deleting the migration would drop seeded content.
  - [ ] **Plugin activation/deactivation**: `ActivationHandler` / `DeactivationHandler` still instantiate `_1_0_0_CreateBase` for the legacy migration path. Those flows must be updated to call Doctrine migrations / new seeders first.

- [ ] **Refactoring Tasks**:
  - [ ] Split into separate migration classes by concern
  - [ ] Create dedicated schema migration classes
  - [ ] Create dedicated data migration classes
  - [ ] Create dedicated cleanup classes
  - [ ] Implement proper migration versioning
  - [ ] Add comprehensive error handling
  - [ ] Add rollback capabilities
  - [ ] Add migration validation

- [ ] **New Structure**:
  - [ ] `SchemaMigrations/` - Database schema changes
  - [ ] `DataMigrations/` - Data transformation and migration
  - [ ] `CleanupMigrations/` - Cleanup and maintenance tasks
  - [ ] `MigrationRunner.php` - Orchestrates migration execution
  - [ ] `MigrationValidator.php` - Validates migration integrity

## 🎯 Future Tasks

### 1. Code Organization
- [ ] **Split Large Functions**
  - [ ] Break down `template_redirect` function (currently 200+ lines)
  - [ ] Extract AJAX handler registration to separate functions
  - [ ] Extract WooCommerce integration to separate functions

- [ ] **Improve Documentation**
  - [ ] Add comprehensive PHPDoc comments
  - [ ] Document feature initialization order
  - [ ] Document route handling flow

### 2. Performance Optimization
- [ ] **Lazy Loading**
  - [ ] Implement lazy loading for features
  - [ ] Only initialize features when needed
  - [ ] Optimize hook registration

- [ ] **Caching**
  - [ ] Add caching for route resolution
  - [ ] Cache feature initialization status
  - [ ] Optimize database queries

### 3. Testing & Validation
- [ ] **Unit Tests**
  - [ ] Test feature initialization
  - [ ] Test route handling
  - [ ] Test AJAX handlers

- [ ] **Integration Tests**
  - [ ] Test complete request flow
  - [ ] Test feature interactions
  - [ ] Test error handling

## 📊 Metrics

### Current State
- **Total Lines**: 1,415
- **Commented Lines**: ~200
- **Active Old Controllers**: 6
- **New Features**: 4
- **Missing Features**: 8 (including WooCommerce & Subscription)
- **WooCommerce Integration**: Not migrated to features
- **Subscription Management**: Not migrated to features

### Target State
- **Total Lines**: ~800-1000
- **Commented Lines**: 0
- **Active Old Controllers**: 0
- **New Features**: 12 (including WooCommerce & Subscription)
- **Missing Features**: 0
- **WooCommerce Integration**: Fully migrated to MinisiteCommerceFeature
- **Subscription Management**: Fully migrated to MinisiteSubscriptionFeature

## 🚨 Risks & Considerations

### 1. Breaking Changes
- [ ] **Route Changes**: Ensure all routes continue to work
- [ ] **AJAX Endpoints**: Maintain backward compatibility
- [ ] **WooCommerce Integration**: Don't break e-commerce functionality

### 2. Dependencies
- [ ] **Feature Dependencies**: Ensure proper initialization order
- [ ] **Hook Priorities**: Maintain correct hook execution order
- [ ] **Database Schema**: Ensure compatibility with existing data

### 3. Testing Strategy
- [ ] **Staging Environment**: Test all changes in staging first
- [ ] **User Acceptance**: Test with real user workflows
- [ ] **Performance Testing**: Ensure no performance regression

## 📝 Notes

### Architecture Decisions
- **Feature-Based**: Each feature handles its own routes, AJAX handlers, and functionality
- **Separation of Concerns**: Clear separation between features
- **Backward Compatibility**: Maintain existing functionality during transition

### Implementation Strategy
1. **Phase 1**: Remove commented code and old controller imports
2. **Phase 2**: Migrate remaining AJAX handlers to features
3. **Phase 3**: Create missing features for complete route coverage
4. **Phase 4**: Clean up and optimize the main plugin file
5. **Phase 5**: Add comprehensive testing and documentation

## 🎯 Prioritization Recommendations

### **Priority 1: minisite-manager.php (HIGH PRIORITY)**
**Why First:**
- ✅ **Immediate Impact**: Reduces technical debt and improves maintainability
- ✅ **User-Facing**: Affects all user interactions and functionality
- ✅ **Foundation**: Other features depend on clean architecture
- ✅ **Risk Management**: Easier to test and validate changes
- ✅ **Incremental**: Can be done in phases without breaking functionality

**Estimated Effort**: 2-3 weeks
**Risk Level**: Medium (can be done incrementally)

### **Priority 2: CreateBase.php (MEDIUM PRIORITY)**
**Why Second:**
- ⚠️ **Database Critical**: Affects data integrity and migrations
- ⚠️ **Lower Visibility**: Less user-facing impact
- ⚠️ **Complex Dependencies**: Requires careful testing of migration logic
- ⚠️ **One-Time Impact**: Migration changes are harder to rollback

**Estimated Effort**: 1-2 weeks
**Risk Level**: High (database migrations are critical)

### **Recommended Approach:**
1. **Start with minisite-manager.php** - Complete the feature migration
2. **Test thoroughly** - Ensure all functionality works
3. **Then tackle CreateBase.php** - Refactor migration system
4. **Final integration testing** - Ensure both systems work together

### **Why This Order:**
- **minisite-manager.php** provides immediate architectural benefits
- **CreateBase.php** is more specialized and can wait
- **Feature migration** is more visible and provides faster feedback
- **Migration refactoring** requires more careful planning and testing

---

**Last Updated**: $(date)
**Status**: In Progress
**Assigned**: Development Team
**Priority**: High (minisite-manager.php), Medium (CreateBase.php)
