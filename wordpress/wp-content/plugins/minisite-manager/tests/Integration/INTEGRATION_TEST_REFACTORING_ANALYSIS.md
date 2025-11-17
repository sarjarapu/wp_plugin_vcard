# Integration Test Refactoring Analysis

## Summary

This document lists all integration test files that are **NOT** extending `BaseIntegrationTest` and analyzes whether they can be refactored to use it.

---

## ✅ Already Extending `BaseIntegrationTest`

These tests have already been refactored:

1. ✅ `tests/Integration/Features/ReviewManagement/ReviewWorkflowIntegrationTest.php`
2. ✅ `tests/Integration/Features/ConfigurationManagement/ConfigurationManagementFeatureIntegrationTest.php`
3. ✅ `tests/Integration/Features/ConfigurationManagement/Hooks/ConfigurationManagementHooksFactoryIntegrationTest.php`
4. ✅ `tests/Integration/Features/ConfigurationManagement/Hooks/ConfigurationManagementHooksIntegrationTest.php`
5. ✅ `tests/Integration/Features/MinisiteManagement/Repositories/MinisiteRepositoryIntegrationTest.php`
6. ✅ `tests/Integration/Features/VersionManagement/Repositories/VersionRepositoryIntegrationTest.php`
7. ✅ `tests/Integration/Features/ReviewManagement/Repositories/ReviewRepositoryIntegrationTest.php`
8. ✅ `tests/Integration/Features/ConfigurationManagement/ConfigurationManagementWorkflowIntegrationTest.php`

---

## 🔄 Special Case: Migration Tests

These tests extend `AbstractDoctrineMigrationTest` (which extends `TestCase`):

- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251103000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251104000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251105000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251106000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251107000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251108000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251109000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251110000000Test.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunnerIntegrationTest.php`

**Status:** These are migration-specific tests with their own base class. They should **NOT** be refactored to extend `BaseIntegrationTest` as they have specialized migration testing logic.

---

## ❌ Tests Extending `TestCase` That Can Be Refactored

### 1. `ReviewSeederServiceIntegrationTest`
**File:** `tests/Integration/Features/ReviewManagement/Services/ReviewSeederServiceIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- DB connection setup (lines 45-60)
- EntityManager creation (lines 63-71)
- Connection state reset (lines 74-89)
- `$wpdb` setup (lines 94-97)
- `TablePrefixListener` registration (lines 100-103)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `ReviewManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition (fixes potential connection issues)
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

---

### 2. `VersionManagementWorkflowIntegrationTest`
**File:** `tests/Integration/Features/VersionManagement/VersionManagementWorkflowIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- DB connection setup (lines 57-71)
- EntityManager creation (lines 73-82)
- Connection state reset (lines 85-100)
- `$wpdb` setup (lines 103-106)
- `TablePrefixListener` registration (lines 108-111)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `Domain/Entities`, `ReviewManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

**Note:** Uses `FakeWpdb` - may need to check if this conflicts with `BaseIntegrationTest`'s `$wpdb` setup.

---

### 3. `ConfigurationManagementServiceIntegrationTest`
**File:** `tests/Integration/Features/ConfigurationManagement/Services/ConfigurationManagementServiceIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- `MINISITE_ENCRYPTION_KEY` constant definition (lines 44-46)
- DB connection setup (lines 49-64)
- EntityManager creation (lines 67-75)
- Connection state reset (lines 78-93)
- `$wpdb` setup (lines 98-101)
- `TablePrefixListener` registration (lines 103-106)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `ConfigurationManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

**Note:** Uses `MINISITE_ENCRYPTION_KEY` - `BaseIntegrationTest` already defines this in `defineConstants()`.

---

### 4. `ConfigSeederIntegrationTest`
**File:** `tests/Integration/Features/ConfigurationManagement/Services/ConfigSeederIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- `MINISITE_ENCRYPTION_KEY` constant definition (lines 46-48)
- `MINISITE_PLUGIN_DIR` constant definition (lines 51-53)
- DB connection setup (lines 56-71)
- EntityManager creation (lines 74-82)
- Connection state reset (lines 85-100)
- `$wpdb` setup (lines 103-106)
- `TablePrefixListener` registration (lines 108-111)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `ConfigurationManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

**Note:** Uses `MINISITE_PLUGIN_DIR` - may need to add this to `BaseIntegrationTest::defineConstants()` if not already present.

---

### 5. `ConfigRepositoryIntegrationTest`
**File:** `tests/Integration/Features/ConfigurationManagement/Repositories/ConfigRepositoryIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- DB connection setup (lines 42-57)
- EntityManager creation (lines 61-70)
- Connection state reset (lines 73-94)
- `$wpdb` setup (lines 99-102)
- `TablePrefixListener` registration (lines 104-107)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `ConfigurationManagement/Domain/Entities`, `ReviewManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

---

### 6. `ConfigurationManagementRendererIntegrationTest`
**File:** `tests/Integration/Features/ConfigurationManagement/Rendering/ConfigurationManagementRendererIntegrationTest.php`

**Can be refactored?** ✅ **YES**

**Duplicate code to remove:**
- Logging initialization (`LoggingServiceProvider::register()`)
- `MINISITE_ENCRYPTION_KEY` constant definition (lines 47-49)
- DB connection setup (lines 52-67)
- EntityManager creation (lines 70-78)
- Connection state reset (lines 81-96)
- `$wpdb` setup (lines 100-103)
- `TablePrefixListener` registration (lines 105-108)
- Table cleanup (`cleanupTables()` method)
- Migration runner setup
- Entity paths: `ConfigurationManagement/Domain/Entities`, `VersionManagement/Domain/Entities`

**Benefits:**
- Removes ~100 lines of duplicate setup code
- Gets proper DB constants definition
- Gets `wp_users` table stub and test user creation
- Gets proper table cleanup

---

### 7. `ReviewHooksFactoryIntegrationTest`
**File:** `tests/Integration/Features/ReviewManagement/Hooks/ReviewHooksFactoryIntegrationTest.php`

**Can be refactored?** ⚠️ **PARTIALLY** (Lightweight test)

**Current setup:**
- Only defines DB constants (lines 35-52)
- Sets up `$wpdb` (lines 55-58)
- Does NOT create EntityManager
- Does NOT run migrations
- Tests call `ReviewHooksFactory::create()` which likely initializes Doctrine internally

**Duplicate code to remove:**
- DB constants definition (lines 35-52) - already in `BaseIntegrationTest::defineConstants()`
- `$wpdb` setup (lines 55-58) - already in `BaseIntegrationTest::setupWordPressGlobals()`

**Considerations:**
- This test doesn't need migrations or EntityManager setup
- However, it would benefit from the constant definitions
- Could extend `BaseIntegrationTest` but override `runMigrations()` to do nothing, or make migrations optional in base class
- Alternatively, could create a lightweight base class for tests that only need constants

**Recommendation:**
- **Option A:** Refactor to extend `BaseIntegrationTest` and override `runMigrations()` to skip migrations (since this test doesn't need them)
- **Option B:** Keep as-is since it's very lightweight and doesn't have much duplicate code

---

## Summary Statistics

- **Total integration tests:** 26 (excluding migration tests and `delete_me` folder)
- **Already refactored:** 8
- **Can be refactored:** 7
- **Special cases (migration tests):** 9 (should NOT be refactored)
- **Lightweight test:** 1 (`ReviewHooksFactoryIntegrationTest` - optional refactoring)

---

## Estimated Code Reduction

If all 7 tests are refactored:
- **~700 lines of duplicate code removed** (average ~100 lines per test)
- **Consistent setup across all integration tests**
- **Better maintainability** (fixes in one place benefit all tests)
- **Proper DB constants** (fixes connection issues)
- **Automatic `wp_users` table setup** (fixes foreign key issues)

---

## Recommended Refactoring Order

1. ✅ `ConfigRepositoryIntegrationTest` (similar to already refactored `ConfigRepositoryIntegrationTest`)
2. ✅ `ConfigurationManagementServiceIntegrationTest` (similar to already refactored `ConfigurationManagementWorkflowIntegrationTest`)
3. ✅ `ConfigurationManagementRendererIntegrationTest` (similar to above)
4. ✅ `ConfigSeederIntegrationTest` (similar to above)
5. ✅ `ReviewSeederServiceIntegrationTest` (similar to already refactored `ReviewRepositoryIntegrationTest`)
6. ✅ `VersionManagementWorkflowIntegrationTest` (similar to already refactored `VersionRepositoryIntegrationTest`)
7. ⚠️ `ReviewHooksFactoryIntegrationTest` (optional - lightweight test)

