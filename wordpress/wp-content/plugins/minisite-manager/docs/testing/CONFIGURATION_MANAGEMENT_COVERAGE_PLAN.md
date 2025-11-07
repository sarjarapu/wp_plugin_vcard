# ConfigurationManagement Feature Coverage Improvement Plan

## Current Coverage Status

| Class | Methods | Lines | Status |
|-------|---------|-------|--------|
| **ConfigurationManagementHooks** | 20.00% (1/5) | 3.33% (1/30) | 🔴 **CRITICAL** |
| **ConfigurationManagementService** | 47.06% (8/17) | 67.65% (115/170) | 🟡 **HIGH PRIORITY** |
| **ConfigurationManagementController** | 66.67% (6/9) | 70.54% (91/129) | 🟡 **MEDIUM PRIORITY** |
| **ConfigurationManagementRenderer** | 60.00% (3/5) | 95.83% (46/48) | 🟢 **LOW PRIORITY** |
| **ConfigSeeder** | 60.00% (3/5) | 90.43% (85/94) | 🟢 **LOW PRIORITY** |

## Priority 1: ConfigurationManagementHooks (20% → 90%+)

### Missing Coverage:
- `register()` - Registers WordPress hooks
- `registerAdminMenu()` - Adds admin menu item
- `renderPage()` - Renders admin page
- `handleDeleteAction()` - Handles delete via admin_post

### Action Items:
1. **Add unit tests for `register()` method**
   - Test that it calls `add_action` for `admin_menu`
   - Test that it calls `add_action` for `admin_post_minisite_config_delete`

2. **Add unit tests for `registerAdminMenu()` method**
   - Mock `add_submenu_page` and verify it's called with correct parameters
   - Verify callback is set to `renderPage`

3. **Add unit tests for `renderPage()` method**
   - Mock `current_user_can('manage_options')` - test both true and false
   - Test that `controller->handleRequest()` is called
   - Test that `controller->render()` is called
   - Test `wp_die` when user lacks permissions

4. **Add unit tests for `handleDeleteAction()` method**
   - Test permission check (`current_user_can`)
   - Test nonce verification (valid and invalid)
   - Test key extraction from `$_GET`
   - Test command creation and handler call
   - Test redirect after deletion
   - Test `wp_die` for various error conditions

**Estimated Impact**: +70% method coverage, +27 lines covered

---

## Priority 2: ConfigurationManagementService (47% → 90%+)

### Missing Coverage:
- `keys()` - Get all config keys
- `find()` - Find config by key
- `reload()` - Force cache reload
- `getString()` - Typed getter for strings
- `getInt()` - Typed getter for integers
- `getBool()` - Typed getter for booleans
- `getJson()` - Typed getter for JSON arrays
- `ensureLoaded()` - Private cache loading
- `clearCache()` - Private cache clearing
- `isSensitiveType()` - Private type checking
- `sanitizeForLogging()` - Private logging sanitization

### Action Items:
1. **Add tests for convenience getters** (`getString`, `getInt`, `getBool`, `getJson`)
   - Test type casting behavior
   - Test default values
   - Test with existing and non-existing keys

2. **Add tests for `keys()` method**
   - Test returns array of keys
   - Test with empty cache
   - Test with multiple configs

3. **Add tests for `find()` method**
   - Test returns Config entity when found
   - Test returns null when not found
   - Test uses cache

4. **Add tests for `reload()` method**
   - Test clears cache
   - Test forces reload on next access

5. **Add tests for private methods via integration tests**
   - `ensureLoaded()` - Test lazy loading behavior
   - `clearCache()` - Test cache invalidation
   - `isSensitiveType()` - Test type detection
   - `sanitizeForLogging()` - Test value sanitization

**Estimated Impact**: +9 methods covered, +55 lines covered

---

## Priority 3: ConfigurationManagementController (67% → 90%+)

### Missing Coverage:
- `handleSave()` - Private method for save logic
- `handleDelete()` - Private method for delete logic
- `getPostData()` - Private helper for POST data
- `getPostDataTextarea()` - Private helper for textarea data
- `getPostDataArray()` - Private helper for array data
- `verifyNonce()` - Private nonce verification
- `isMaskedValue()` - Private masked value detection
- `getSettingsMessages()` - Private message retrieval

### Action Items:
1. **Add tests for `handleSave()` method**
   - Test processing multiple config fields
   - Test handling masked values (skip update)
   - Test new config addition
   - Test key validation (invalid format)
   - Test error handling and messages
   - Test success messages

2. **Add tests for `handleDelete()` method**
   - Test deletion of non-required configs
   - Test blocking deletion of required configs (default list)
   - Test blocking deletion of configs with `isRequired` flag
   - Test error handling
   - Test success messages

3. **Add tests for helper methods** (via integration or reflection)
   - `getPostData()` - Test sanitization and defaults
   - `getPostDataTextarea()` - Test textarea sanitization
   - `getPostDataArray()` - Test array handling
   - `verifyNonce()` - Test nonce verification
   - `isMaskedValue()` - Test masked value detection
   - `getSettingsMessages()` - Test message retrieval

**Estimated Impact**: +3 methods covered, +38 lines covered

---

## Priority 4: ConfigurationManagementRenderer (60% → 90%+)

### Missing Coverage:
- `registerTimberLocations()` - Private method (already partially covered via integration)

### Action Items:
1. **Add unit test for `registerTimberLocations()` using reflection**
   - Test that it adds template path to Timber locations
   - Test that it doesn't duplicate paths

**Estimated Impact**: +1 method covered, +2 lines covered

---

## Priority 5: ConfigSeeder (60% → 90%+)

### Missing Coverage:
- Remaining private methods (if any)

### Action Items:
1. **Review existing tests and add any missing edge cases**
   - Test error scenarios
   - Test edge cases in JSON loading

**Estimated Impact**: +2 methods covered, +9 lines covered

---

## Implementation Strategy

### Phase 1: Critical Coverage (ConfigurationManagementHooks)
**Time Estimate**: 2-3 hours
**Target**: 20% → 90%+ coverage
**Files to Create/Update**:
- `tests/Unit/Features/ConfigurationManagement/Hooks/ConfigurationManagementHooksTest.php` (expand existing)

### Phase 2: High Priority (ConfigurationManagementService)
**Time Estimate**: 2-3 hours
**Target**: 47% → 90%+ coverage
**Files to Create/Update**:
- `tests/Unit/Features/ConfigurationManagement/Services/ConfigurationManagementServiceTest.php` (expand existing)
- `tests/Integration/Features/ConfigurationManagement/Services/ConfigurationManagementServiceIntegrationTest.php` (expand existing)

### Phase 3: Medium Priority (ConfigurationManagementController)
**Time Estimate**: 2-3 hours
**Target**: 67% → 90%+ coverage
**Files to Create/Update**:
- `tests/Unit/Features/ConfigurationManagement/Controllers/ConfigurationManagementControllerTest.php` (expand existing)

### Phase 4: Low Priority (Renderer & Seeder)
**Time Estimate**: 1 hour
**Target**: 60% → 90%+ coverage
**Files to Create/Update**:
- `tests/Unit/Features/ConfigurationManagement/Rendering/ConfigurationManagementRendererTest.php` (expand existing)
- `tests/Unit/Features/ConfigurationManagement/Services/ConfigSeederTest.php` (expand existing)

---

## Testing Best Practices

1. **Use Brain Monkey for WordPress functions** - Already established pattern
2. **Use reflection for private methods** - When necessary for coverage
3. **Integration tests for database operations** - Use real DB when possible
4. **Mock dependencies** - Use PHPUnit mocks for handlers, services, etc.
5. **Test error paths** - Don't just test happy paths
6. **Test edge cases** - Empty values, null, invalid formats, etc.

---

## Success Criteria

- ✅ All classes above 90% method coverage
- ✅ All classes above 90% line coverage
- ✅ All tests passing
- ✅ No skipped tests (except for conditional skips like DB unavailable)

---

## Estimated Total Impact

- **Methods**: +15 methods covered
- **Lines**: +131 lines covered
- **Overall Coverage**: Should push ConfigurationManagement feature above 90%

---

## Next Steps

1. Start with Priority 1 (ConfigurationManagementHooks) - highest impact
2. Move to Priority 2 (ConfigurationManagementService) - second highest impact
3. Complete Priority 3 (ConfigurationManagementController)
4. Finish with Priority 4 & 5 (Renderer & Seeder)

