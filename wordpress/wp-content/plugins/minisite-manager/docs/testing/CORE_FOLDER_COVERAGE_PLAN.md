# Core Folder Test Coverage Improvement Plan

## Overview

This document provides a comprehensive testing plan for the Core folder, which contains essential plugin lifecycle management, feature registration, role management, and WordPress integration components. The plan is designed to be detailed enough for an automated agent (Linear app agent) to implement tests independently.

**Target Coverage**: 90%+ for all classes in the Core folder

**Current Status**:
- Basic reflection-based tests exist but lack functional coverage
- Tests verify method existence but not actual behavior
- Missing integration tests for WordPress hook registration
- Missing tests for error handling and edge cases

## Core Folder Structure

The Core folder contains seven main components:

1. **ActivationHandler.php** - Plugin activation lifecycle management
2. **DeactivationHandler.php** - Plugin deactivation cleanup
3. **FeatureRegistry.php** - Feature registration and initialization
4. **PluginBootstrap.php** - Main plugin initialization coordinator
5. **RoleManager.php** - WordPress roles and capabilities management
6. **AdminMenuManager.php** - WordPress admin menu registration
7. **RewriteCoordinator.php** - WordPress rewrite system coordination

---

## File 1: `src/Core/ActivationHandler.php`

### Class Overview

**Purpose**: Handles plugin activation lifecycle including database migrations, role setup, and configuration seeding.

**Class Signature**:
```php
final class ActivationHandler
{
    public static function handle(): void

    private static function runMigrations(): void

    public static function seedDefaultConfigs(): void
}
```

**Key Responsibilities**:
- Set rewrite flush flag
- Run Doctrine database migrations
- Sync WordPress roles and capabilities
- Seed default configuration values
- Handle errors gracefully with logging

### Method Analysis

#### Method: `handle(): void`

**Purpose**: Main entry point for plugin activation.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Sets option `minisite_flush_rewrites` to `1` via `update_option()`
2. Calls `runMigrations()` to execute database migrations
3. Calls `RoleManager::syncRolesAndCapabilities()` to set up roles
4. Registers `seedDefaultConfigs()` on `init` hook with priority 15

**Dependencies**:
- WordPress function: `update_option()`
- WordPress function: `add_action()`
- `RoleManager::syncRolesAndCapabilities()`
- `self::runMigrations()`
- `self::seedDefaultConfigs()`

**Side Effects**:
- Sets WordPress option
- Registers WordPress action hook
- May run database migrations
- May modify WordPress roles

**Test Focus**:
- Verify option is set correctly
- Verify migrations are called
- Verify role sync is called
- Verify init hook is registered
- Test error handling

---

#### Method: `runMigrations(): void`

**Purpose**: Execute Doctrine database migrations during activation.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Checks if `Doctrine\ORM\EntityManager` class exists
2. If not, logs warning and returns early
3. If yes, creates `DoctrineMigrationRunner` instance
4. Calls `migrate()` on runner
5. Catches exceptions and logs error details

**Dependencies**:
- `class_exists('Doctrine\ORM\EntityManager')`
- `DoctrineMigrationRunner` class
- `LoggingServiceProvider::getFeatureLogger()`

**Side Effects**:
- May execute database migrations
- May log warnings or errors

**Test Focus**:
- Test with Doctrine available
- Test with Doctrine not available
- Test migration execution
- Test exception handling
- Test logging calls

---

#### Method: `seedDefaultConfigs(): void`

**Purpose**: Seed default configuration values after ConfigManager is initialized.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Checks if `$GLOBALS['minisite_config_manager']` exists
2. If not, tries to initialize via `PluginBootstrap::initializeConfigSystem()`
3. If still not available, retries on next init hook (max 2 retries)
4. Creates `ConfigSeeder` instance
5. Calls `seedDefaults()` with config manager
6. Catches exceptions and logs error details

**Dependencies**:
- `$GLOBALS['minisite_config_manager']`
- `PluginBootstrap::initializeConfigSystem()`
- `ConfigSeeder` class
- `LoggingServiceProvider::getFeatureLogger()`
- WordPress function: `add_action()`

**Side Effects**:
- May initialize config system
- May seed configuration values
- May register WordPress action hooks
- May log errors

**Test Focus**:
- Test with config manager available
- Test with config manager not available
- Test retry logic
- Test seeding execution
- Test exception handling

---

### Unit Test Plan for `ActivationHandler`

#### Test File Location
`tests/Unit/Core/ActivationHandlerTest.php`

#### Test Class Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Minisite\Core\ActivationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivationHandler::class)]
final class ActivationHandlerTest extends TestCase
{
    // Test implementation
}
```

#### Test Cases

##### Test 1: `test_handle_sets_flush_rewrites_option()`
**Purpose**: Verify handle method sets the rewrite flush option.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `update_option('minisite_flush_rewrites', 1, false)` is called

**Test Steps**:
1. Mock `update_option()` function to track calls
2. Mock `add_action()` function
3. Mock `RoleManager::syncRolesAndCapabilities()` (or use real if testable)
4. Mock `runMigrations()` via reflection or dependency injection
5. Call `ActivationHandler::handle()`
6. Assert `update_option()` was called with correct parameters

**Mocking Strategy**:
- Mock WordPress functions via `eval()` or Brain Monkey
- Track function calls in global arrays
- Use reflection to test private methods if needed

**Assertions**:
- Verify `update_option()` was called
- Verify call includes `'minisite_flush_rewrites'`, `1`, `false`

---

##### Test 2: `test_handle_calls_run_migrations()`
**Purpose**: Verify handle method calls runMigrations.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `runMigrations()` is executed

**Test Steps**:
1. Mock WordPress functions
2. Use reflection to verify `runMigrations()` is called
3. Or verify migration runner is instantiated
4. Call `ActivationHandler::handle()`
5. Assert migrations were attempted

**Assertions**:
- Verify migration execution path is reached
- Verify DoctrineMigrationRunner is created (if Doctrine available)

---

##### Test 3: `test_handle_calls_role_manager_sync()`
**Purpose**: Verify handle method calls RoleManager sync.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `RoleManager::syncRolesAndCapabilities()` is called

**Test Steps**:
1. Mock WordPress functions
2. Mock `RoleManager::syncRolesAndCapabilities()` or verify it's called
3. Call `ActivationHandler::handle()`
4. Assert role sync was called

**Assertions**:
- Verify `RoleManager::syncRolesAndCapabilities()` was called

---

##### Test 4: `test_handle_registers_seed_configs_hook()`
**Purpose**: Verify handle method registers seedDefaultConfigs on init hook.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `add_action('init', [ActivationHandler::class, 'seedDefaultConfigs'], 15)` is called

**Test Steps**:
1. Mock `add_action()` to track calls
2. Call `ActivationHandler::handle()`
3. Assert `add_action()` was called with correct parameters

**Assertions**:
- Verify `add_action()` was called
- Verify hook is `'init'`
- Verify callback is `[ActivationHandler::class, 'seedDefaultConfigs']`
- Verify priority is `15`

---

##### Test 5: `test_run_migrations_with_doctrine_available()`
**Purpose**: Verify runMigrations executes when Doctrine is available.

**Input**: None (calls `runMigrations()` via reflection)

**Expected Behavior**:
- DoctrineMigrationRunner is created
- `migrate()` is called on runner

**Test Steps**:
1. Ensure Doctrine class exists (or mock it)
2. Mock `DoctrineMigrationRunner` class
3. Mock `LoggingServiceProvider::getFeatureLogger()`
4. Use reflection to call `runMigrations()`
5. Assert migration runner was created and migrate() was called

**Mocking Strategy**:
- Mock `DoctrineMigrationRunner` class
- Mock logger
- Use reflection for private method

**Assertions**:
- Verify `DoctrineMigrationRunner` was instantiated
- Verify `migrate()` was called

---

##### Test 6: `test_run_migrations_without_doctrine_logs_warning()`
**Purpose**: Verify runMigrations logs warning when Doctrine is not available.

**Input**: None (calls `runMigrations()` via reflection)

**Expected Behavior**:
- `class_exists('Doctrine\ORM\EntityManager')` returns false
- Warning is logged
- Method returns early without error

**Test Steps**:
1. Ensure Doctrine class does not exist
2. Mock `LoggingServiceProvider::getFeatureLogger()`
3. Mock logger's `warning()` method
4. Use reflection to call `runMigrations()`
5. Assert warning was logged
6. Assert method returned without error

**Assertions**:
- Verify `logger->warning()` was called
- Verify warning message contains expected text
- Verify no exceptions thrown

---

##### Test 7: `test_run_migrations_handles_exception()`
**Purpose**: Verify runMigrations handles exceptions gracefully.

**Input**: None (calls `runMigrations()` via reflection)

**Expected Behavior**:
- Exception is caught
- Error is logged with full details
- Method returns without throwing

**Test Steps**:
1. Mock Doctrine to be available
2. Mock `DoctrineMigrationRunner` to throw exception
3. Mock logger's `error()` method
4. Use reflection to call `runMigrations()`
5. Assert error was logged
6. Assert exception details are in log
7. Assert no exception propagates

**Assertions**:
- Verify `logger->error()` was called
- Verify error log contains exception message, class, file, line, trace
- Verify no exception thrown to caller

---

##### Test 8: `test_seed_default_configs_with_config_manager_available()`
**Purpose**: Verify seedDefaultConfigs executes when config manager exists.

**Input**: None (calls `seedDefaultConfigs()`)

**Expected Behavior**:
- ConfigSeeder is created
- `seedDefaults()` is called with config manager

**Test Steps**:
1. Set `$GLOBALS['minisite_config_manager']` to mock object
2. Mock `ConfigSeeder` class
3. Mock `LoggingServiceProvider::getFeatureLogger()`
4. Call `ActivationHandler::seedDefaultConfigs()`
5. Assert seeder was created and seedDefaults() was called

**Mocking Strategy**:
- Mock config manager in globals
- Mock ConfigSeeder class
- Verify method calls

**Assertions**:
- Verify `ConfigSeeder` was instantiated
- Verify `seedDefaults()` was called with config manager

---

##### Test 9: `test_seed_default_configs_initializes_config_system_when_missing()`
**Purpose**: Verify seedDefaultConfigs initializes config system when missing.

**Input**: None (calls `seedDefaultConfigs()`)

**Expected Behavior**:
- `PluginBootstrap::initializeConfigSystem()` is called
- Config manager is created in globals

**Test Steps**:
1. Ensure `$GLOBALS['minisite_config_manager']` is not set
2. Mock `PluginBootstrap::initializeConfigSystem()` to set global
3. Mock Doctrine to be available
4. Call `ActivationHandler::seedDefaultConfigs()`
5. Assert initializeConfigSystem was called
6. Assert config manager exists in globals

**Assertions**:
- Verify `PluginBootstrap::initializeConfigSystem()` was called
- Verify `$GLOBALS['minisite_config_manager']` is set

---

##### Test 10: `test_seed_default_configs_retries_on_next_init_when_missing()`
**Purpose**: Verify seedDefaultConfigs retries on next init hook when config manager still missing.

**Input**: None (calls `seedDefaultConfigs()`)

**Expected Behavior**:
- If config manager still missing after initialization attempt
- `add_action('init', [self::class, 'seedDefaultConfigs'], 20)` is called
- Retry count is tracked (max 2 retries)

**Test Steps**:
1. Ensure config manager is not set
2. Mock `PluginBootstrap::initializeConfigSystem()` to not set global
3. Mock `add_action()` to track calls
4. Call `ActivationHandler::seedDefaultConfigs()` first time
5. Assert `add_action()` was called for retry
6. Call again (simulating retry)
7. Assert retry logic works

**Assertions**:
- Verify `add_action()` was called for retry
- Verify priority is `20`
- Verify retry count is tracked

---

##### Test 11: `test_seed_default_configs_handles_exception()`
**Purpose**: Verify seedDefaultConfigs handles exceptions gracefully.

**Input**: None (calls `seedDefaultConfigs()`)

**Expected Behavior**:
- Exception is caught
- Error is logged with full details
- Method returns without throwing

**Test Steps**:
1. Set config manager in globals
2. Mock `ConfigSeeder` to throw exception
3. Mock logger's `error()` method
4. Call `ActivationHandler::seedDefaultConfigs()`
5. Assert error was logged
6. Assert no exception propagates

**Assertions**:
- Verify `logger->error()` was called
- Verify error log contains exception details
- Verify no exception thrown

---

### Integration Test Plan for `ActivationHandler`

#### Test File Location
`tests/Integration/Core/ActivationHandlerIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_handle_executes_full_activation_workflow()`
**Purpose**: Verify complete activation workflow in WordPress environment.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- Option is set in WordPress database
- Migrations run (if Doctrine available)
- Roles are synced
- Init hook is registered

**Test Steps**:
1. Set up WordPress test environment
2. Call `ActivationHandler::handle()`
3. Verify option exists in database
4. Verify roles exist in WordPress
5. Verify init hook is registered

**Assertions**:
- `assertTrue(get_option('minisite_flush_rewrites') === '1')`
- Verify roles exist via `get_role()`
- Verify hooks are registered

---

---

## File 2: `src/Core/DeactivationHandler.php`

### Class Overview

**Purpose**: Handles plugin deactivation cleanup including rewrite rules and optional data cleanup.

**Class Signature**:
```php
final class DeactivationHandler
{
    public static function handle(): void

    private static function cleanupNonProduction(): void
}
```

**Key Responsibilities**:
- Flush rewrite rules
- Clean up non-production data (if not in production)
- Remove custom roles (in non-production)
- Clear legacy options

### Method Analysis

#### Method: `handle(): void`

**Purpose**: Main entry point for plugin deactivation.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Calls `flush_rewrite_rules()` to clear rewrite rules
2. Checks if `MINISITE_LIVE_PRODUCTION` constant is defined and true
3. If not in production, calls `cleanupNonProduction()`

**Dependencies**:
- WordPress function: `flush_rewrite_rules()`
- WordPress constant: `MINISITE_LIVE_PRODUCTION`
- `self::cleanupNonProduction()`

**Side Effects**:
- Flushes WordPress rewrite rules
- May clean up data in non-production

**Test Focus**:
- Verify rewrite rules are flushed
- Verify production check
- Verify cleanup is called in non-production
- Verify cleanup is not called in production

---

#### Method: `cleanupNonProduction(): void`

**Purpose**: Clean up data in non-production environments.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Deletes option `MINISITE_DB_OPTION` via `delete_option()`
2. Removes custom roles: `minisite_user`, `minisite_member`, `minisite_power`, `minisite_admin`
3. Uses `remove_role()` for each role

**Dependencies**:
- WordPress function: `delete_option()`
- WordPress function: `remove_role()`
- Constant: `MINISITE_DB_OPTION`

**Side Effects**:
- Deletes WordPress option
- Removes WordPress roles

**Test Focus**:
- Verify option is deleted
- Verify all roles are removed
- Test with roles that exist
- Test with roles that don't exist

---

### Unit Test Plan for `DeactivationHandler`

#### Test File Location
`tests/Unit/Core/DeactivationHandlerTest.php`

#### Test Cases

##### Test 1: `test_handle_flushes_rewrite_rules()`
**Purpose**: Verify handle method flushes rewrite rules.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `flush_rewrite_rules()` is called

**Test Steps**:
1. Mock `flush_rewrite_rules()` function
2. Ensure `MINISITE_LIVE_PRODUCTION` is not defined or false
3. Call `DeactivationHandler::handle()`
4. Assert `flush_rewrite_rules()` was called

**Assertions**:
- Verify `flush_rewrite_rules()` was called

---

##### Test 2: `test_handle_calls_cleanup_in_non_production()`
**Purpose**: Verify handle method calls cleanup when not in production.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `MINISITE_LIVE_PRODUCTION` is not defined or false
- `cleanupNonProduction()` is called

**Test Steps**:
1. Ensure `MINISITE_LIVE_PRODUCTION` is not defined
2. Mock `flush_rewrite_rules()`
3. Mock `delete_option()` and `remove_role()`
4. Call `DeactivationHandler::handle()`
5. Assert cleanup methods were called

**Assertions**:
- Verify `delete_option()` was called
- Verify `remove_role()` was called for each role

---

##### Test 3: `test_handle_skips_cleanup_in_production()`
**Purpose**: Verify handle method skips cleanup in production.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- `MINISITE_LIVE_PRODUCTION` is defined and true
- `cleanupNonProduction()` is not called

**Test Steps**:
1. Define `MINISITE_LIVE_PRODUCTION` as true
2. Mock `flush_rewrite_rules()`
3. Mock `delete_option()` and `remove_role()`
4. Call `DeactivationHandler::handle()`
5. Assert cleanup methods were NOT called

**Assertions**:
- Verify `delete_option()` was NOT called
- Verify `remove_role()` was NOT called

---

##### Test 4: `test_cleanup_non_production_deletes_option()`
**Purpose**: Verify cleanupNonProduction deletes the legacy option.

**Input**: None (calls `cleanupNonProduction()` via reflection)

**Expected Behavior**:
- `delete_option(MINISITE_DB_OPTION)` is called

**Test Steps**:
1. Define `MINISITE_DB_OPTION` constant
2. Mock `delete_option()` function
3. Use reflection to call `cleanupNonProduction()`
4. Assert `delete_option()` was called with correct option name

**Assertions**:
- Verify `delete_option()` was called
- Verify call includes `MINISITE_DB_OPTION` constant

---

##### Test 5: `test_cleanup_non_production_removes_all_roles()`
**Purpose**: Verify cleanupNonProduction removes all custom roles.

**Input**: None (calls `cleanupNonProduction()` via reflection)

**Expected Behavior**:
- `remove_role()` is called for each role: `minisite_user`, `minisite_member`, `minisite_power`, `minisite_admin`

**Test Steps**:
1. Mock `remove_role()` function to track calls
2. Use reflection to call `cleanupNonProduction()`
3. Assert `remove_role()` was called 4 times
4. Assert each expected role was passed

**Assertions**:
- Verify `remove_role()` was called 4 times
- Verify calls include: `'minisite_user'`, `'minisite_member'`, `'minisite_power'`, `'minisite_admin'`

---

### Integration Test Plan for `DeactivationHandler`

#### Test File Location
`tests/Integration/Core/DeactivationHandlerIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_handle_removes_roles_in_wordpress()`
**Purpose**: Verify roles are actually removed from WordPress in non-production.

**Input**: None (calls `handle()`)

**Expected Behavior**:
- Roles are removed from WordPress
- `get_role()` returns null for removed roles

**Test Steps**:
1. Set up WordPress test environment
2. Create test roles first
3. Ensure `MINISITE_LIVE_PRODUCTION` is not defined
4. Call `DeactivationHandler::handle()`
5. Verify roles don't exist via `get_role()`

**Assertions**:
- `assertNull(get_role('minisite_user'))`
- (Repeat for all roles)

---

---

## File 3: `src/Core/FeatureRegistry.php`

### Class Overview

**Purpose**: Manages feature registration and initialization for the plugin.

**Class Signature**:
```php
final class FeatureRegistry
{
    private static array $features = [...];

    public static function initializeAll(): void

    public static function registerFeature(string $featureClass): void

    public static function getFeatures(): array
}
```

**Key Responsibilities**:
- Maintain list of available features
- Initialize all registered features
- Allow dynamic feature registration
- Provide feature discovery

### Method Analysis

#### Method: `initializeAll(): void`

**Purpose**: Initialize all registered features.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Iterates through `self::$features` array
2. For each feature class:
   - Checks if class exists via `class_exists()`
   - Checks if class has `initialize` method via `method_exists()`
   - If both true, calls `$featureClass::initialize()`

**Dependencies**:
- `class_exists()` function
- `method_exists()` function
- Feature classes must have static `initialize()` method

**Side Effects**:
- May initialize multiple features
- Features may register WordPress hooks

**Test Focus**:
- Test with all features available
- Test with some features missing
- Test with features missing initialize method
- Verify each feature's initialize is called

---

#### Method: `registerFeature(string $featureClass): void`

**Purpose**: Dynamically register a new feature class.

**Parameters**:
- `$featureClass` (string): Fully qualified class name

**Return Type**: `void`

**Behavior**:
1. Checks if `$featureClass` is already in `self::$features` array
2. If not present, adds to array
3. If present, does nothing (no duplicates)

**Dependencies**:
- `in_array()` function

**Side Effects**:
- Modifies static `$features` array

**Test Focus**:
- Test adding new feature
- Test adding duplicate feature (should not add)
- Verify feature is in array after registration

---

#### Method: `getFeatures(): array`

**Purpose**: Get list of all registered features.

**Parameters**: None

**Return Type**: `array`

**Behavior**:
- Returns `self::$features` array

**Dependencies**: None

**Side Effects**: None

**Test Focus**:
- Verify returns array
- Verify contains expected features
- Verify array is not empty

---

### Unit Test Plan for `FeatureRegistry`

#### Test File Location
`tests/Unit/Core/FeatureRegistryTest.php`

#### Test Cases

##### Test 1: `test_initialize_all_calls_initialize_on_all_features()`
**Purpose**: Verify initializeAll calls initialize on all available features.

**Input**: None (calls `initializeAll()`)

**Expected Behavior**:
- Each feature class that exists and has initialize method has initialize() called

**Test Steps**:
1. Mock feature classes or use real ones
2. Track initialize() calls
3. Call `FeatureRegistry::initializeAll()`
4. Assert initialize() was called for each valid feature

**Mocking Strategy**:
- Create stub feature classes with static initialize() methods
- Track calls via static properties or mocks

**Assertions**:
- Verify initialize() was called for each valid feature
- Verify count matches expected features

---

##### Test 2: `test_initialize_all_skips_missing_classes()`
**Purpose**: Verify initializeAll skips features where class doesn't exist.

**Input**: None (calls `initializeAll()`)

**Expected Behavior**:
- Features with non-existent classes are skipped
- No errors thrown

**Test Steps**:
1. Add non-existent class to features array (via reflection)
2. Call `FeatureRegistry::initializeAll()`
3. Assert no errors thrown
4. Assert existing features still initialized

**Assertions**:
- No exceptions thrown
- Existing features still initialized

---

##### Test 3: `test_initialize_all_skips_classes_without_initialize_method()`
**Purpose**: Verify initializeAll skips features without initialize method.

**Input**: None (calls `initializeAll()`)

**Expected Behavior**:
- Features without initialize() method are skipped
- No errors thrown

**Test Steps**:
1. Create stub class without initialize() method
2. Add to features array (via reflection)
3. Call `FeatureRegistry::initializeAll()`
4. Assert no errors thrown

**Assertions**:
- No exceptions thrown
- Class without initialize() is skipped

---

##### Test 4: `test_register_feature_adds_new_feature()`
**Purpose**: Verify registerFeature adds new feature to array.

**Input**:
- `$featureClass = 'Test\Feature\Class'`

**Expected Behavior**:
- Feature is added to `$features` array
- `getFeatures()` returns the new feature

**Test Steps**:
1. Get initial feature count
2. Call `FeatureRegistry::registerFeature('Test\Feature\Class')`
3. Get updated features
4. Assert count increased by 1
5. Assert new feature is in array

**Assertions**:
- `assertCount($initialCount + 1, $updatedFeatures)`
- `assertContains('Test\Feature\Class', $updatedFeatures)`

---

##### Test 5: `test_register_feature_does_not_add_duplicate()`
**Purpose**: Verify registerFeature does not add duplicate features.

**Input**:
- `$featureClass = \Minisite\Features\Authentication\AuthenticationFeature::class` (existing)

**Expected Behavior**:
- Feature is not added again
- Array count remains same

**Test Steps**:
1. Get initial feature count
2. Call `FeatureRegistry::registerFeature()` with existing feature
3. Get updated features
4. Assert count is same
5. Assert feature appears only once

**Assertions**:
- `assertCount($initialCount, $updatedFeatures)`
- `assertCount(1, array_filter($features, fn($f) => $f === $featureClass))`

---

##### Test 6: `test_get_features_returns_array()`
**Purpose**: Verify getFeatures returns an array.

**Input**: None (calls `getFeatures()`)

**Expected Behavior**:
- Returns array type

**Test Steps**:
1. Call `FeatureRegistry::getFeatures()`
2. Assert return type is array

**Assertions**:
- `assertIsArray($features)`

---

##### Test 7: `test_get_features_returns_expected_features()`
**Purpose**: Verify getFeatures returns all expected registered features.

**Input**: None (calls `getFeatures()`)

**Expected Behavior**:
- Returns array containing all registered feature classes

**Test Steps**:
1. Call `FeatureRegistry::getFeatures()`
2. Assert contains expected feature classes
3. Assert array is not empty

**Assertions**:
- `assertContains(AuthenticationFeature::class, $features)`
- `assertContains(MinisiteViewerFeature::class, $features)`
- (Repeat for all expected features)
- `assertNotEmpty($features)`

---

##### Test 8: `test_get_features_includes_dynamically_registered_features()`
**Purpose**: Verify getFeatures includes features registered via registerFeature.

**Input**: None (calls `registerFeature()` then `getFeatures()`)

**Expected Behavior**:
- Dynamically registered feature appears in getFeatures() result

**Test Steps**:
1. Call `FeatureRegistry::registerFeature('Test\New\Feature')`
2. Call `FeatureRegistry::getFeatures()`
3. Assert new feature is in result

**Assertions**:
- `assertContains('Test\New\Feature', $features)`

---

### Integration Test Plan for `FeatureRegistry`

#### Test File Location
`tests/Integration/Core/FeatureRegistryIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_initialize_all_initializes_all_features_in_wordpress()`
**Purpose**: Verify all features are initialized in WordPress environment.

**Input**: None (calls `initializeAll()`)

**Expected Behavior**:
- All features register their WordPress hooks
- Hooks are accessible in WordPress

**Test Steps**:
1. Set up WordPress test environment
2. Call `FeatureRegistry::initializeAll()`
3. Verify hooks are registered via WordPress hook system
4. Verify features are functional

**Assertions**:
- Verify hooks exist in WordPress
- Verify features work as expected

---

---

## File 4: `src/Core/PluginBootstrap.php`

### Class Overview

**Purpose**: Main plugin initialization coordinator that sets up core systems and features.

**Class Signature**:
```php
final class PluginBootstrap
{
    public static function initialize(): void

    public static function onActivation(): void

    public static function onDeactivation(): void

    public static function initializeCore(): void

    public static function initializeConfigSystem(): void

    public static function initializeFeatures(): void
}
```

**Key Responsibilities**:
- Register activation/deactivation hooks
- Initialize core systems (logging, error handling, roles, rewrite, admin menu, config)
- Initialize all features
- Coordinate plugin lifecycle

### Method Analysis

#### Method: `initialize(): void`

**Purpose**: Main plugin initialization entry point.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Registers activation hook: `register_activation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onActivation'])`
2. Registers deactivation hook: `register_deactivation_hook(MINISITE_PLUGIN_FILE, [self::class, 'onDeactivation'])`
3. Registers init hook for core: `add_action('init', [self::class, 'initializeCore'], 5)`
4. Registers init hook for features: `add_action('init', [self::class, 'initializeFeatures'], 10)`

**Dependencies**:
- WordPress constant: `MINISITE_PLUGIN_FILE`
- WordPress function: `register_activation_hook()`
- WordPress function: `register_deactivation_hook()`
- WordPress function: `add_action()`

**Side Effects**:
- Registers WordPress hooks
- Sets up plugin lifecycle

**Test Focus**:
- Verify activation hook is registered
- Verify deactivation hook is registered
- Verify init hooks are registered with correct priorities

---

#### Method: `onActivation(): void`

**Purpose**: Callback for plugin activation.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
- Calls `ActivationHandler::handle()`

**Dependencies**:
- `ActivationHandler::handle()`

**Test Focus**:
- Verify ActivationHandler::handle() is called

---

#### Method: `onDeactivation(): void`

**Purpose**: Callback for plugin deactivation.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
- Calls `DeactivationHandler::handle()`

**Dependencies**:
- `DeactivationHandler::handle()`

**Test Focus**:
- Verify DeactivationHandler::handle() is called

---

#### Method: `initializeCore(): void`

**Purpose**: Initialize core plugin systems.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Calls `LoggingServiceProvider::register()`
2. Calls `ErrorHandlingServiceProvider::register()`
3. Calls `RoleManager::initialize()`
4. Calls `RewriteCoordinator::initialize()`
5. Calls `AdminMenuManager::initialize()`
6. Calls `self::initializeConfigSystem()`

**Dependencies**:
- `LoggingServiceProvider::register()`
- `ErrorHandlingServiceProvider::register()`
- `RoleManager::initialize()`
- `RewriteCoordinator::initialize()`
- `AdminMenuManager::initialize()`
- `self::initializeConfigSystem()`

**Test Focus**:
- Verify each service provider is registered
- Verify each manager is initialized
- Verify config system is initialized
- Test order of initialization

---

#### Method: `initializeConfigSystem(): void`

**Purpose**: Initialize Doctrine and configuration management system.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Checks if `Doctrine\ORM\EntityManager` class exists
2. If not, logs warning and returns
3. If yes:
   - Checks if EntityManager exists in globals and is open
   - If closed or missing, creates new EntityManager via `DoctrineFactory::createEntityManager()`
   - Stores in `$GLOBALS['minisite_entity_manager']`
   - Creates ReviewRepository and stores in `$GLOBALS['minisite_review_repository']`
   - Creates VersionRepository and stores in `$GLOBALS['minisite_version_repository']`
   - Creates MinisiteRepository and stores in `$GLOBALS['minisite_repository']`
4. Catches exceptions and logs errors

**Dependencies**:
- `class_exists('Doctrine\ORM\EntityManager')`
- `DoctrineFactory::createEntityManager()`
- Repository classes
- `LoggingServiceProvider::getFeatureLogger()`

**Test Focus**:
- Test with Doctrine available
- Test with Doctrine not available
- Test EntityManager creation
- Test repository creation
- Test exception handling
- Test EntityManager reuse when open

---

#### Method: `initializeFeatures(): void`

**Purpose**: Initialize all registered features.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
- Calls `FeatureRegistry::initializeAll()`

**Dependencies**:
- `FeatureRegistry::initializeAll()`

**Test Focus**:
- Verify FeatureRegistry::initializeAll() is called

---

### Unit Test Plan for `PluginBootstrap`

#### Test File Location
`tests/Unit/Core/PluginBootstrapTest.php`

#### Test Cases

##### Test 1: `test_initialize_registers_activation_hook()`
**Purpose**: Verify initialize registers activation hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `register_activation_hook()` is called with correct parameters

**Test Steps**:
1. Mock `register_activation_hook()` to track calls
2. Define `MINISITE_PLUGIN_FILE` constant
3. Call `PluginBootstrap::initialize()`
4. Assert activation hook was registered

**Assertions**:
- Verify `register_activation_hook()` was called
- Verify file and callback are correct

---

##### Test 2: `test_initialize_registers_deactivation_hook()`
**Purpose**: Verify initialize registers deactivation hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `register_deactivation_hook()` is called with correct parameters

**Test Steps**:
1. Mock `register_deactivation_hook()` to track calls
2. Define `MINISITE_PLUGIN_FILE` constant
3. Call `PluginBootstrap::initialize()`
4. Assert deactivation hook was registered

**Assertions**:
- Verify `register_deactivation_hook()` was called
- Verify file and callback are correct

---

##### Test 3: `test_initialize_registers_core_init_hook()`
**Purpose**: Verify initialize registers core init hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `add_action('init', [self::class, 'initializeCore'], 5)` is called

**Test Steps**:
1. Mock `add_action()` to track calls
2. Call `PluginBootstrap::initialize()`
3. Assert init hook for core was registered with priority 5

**Assertions**:
- Verify `add_action()` was called
- Verify hook is `'init'`
- Verify callback is `[PluginBootstrap::class, 'initializeCore']`
- Verify priority is `5`

---

##### Test 4: `test_initialize_registers_features_init_hook()`
**Purpose**: Verify initialize registers features init hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `add_action('init', [self::class, 'initializeFeatures'], 10)` is called

**Test Steps**:
1. Mock `add_action()` to track calls
2. Call `PluginBootstrap::initialize()`
3. Assert init hook for features was registered with priority 10

**Assertions**:
- Verify `add_action()` was called
- Verify hook is `'init'`
- Verify callback is `[PluginBootstrap::class, 'initializeFeatures']`
- Verify priority is `10`

---

##### Test 5: `test_on_activation_calls_activation_handler()`
**Purpose**: Verify onActivation calls ActivationHandler.

**Input**: None (calls `onActivation()`)

**Expected Behavior**:
- `ActivationHandler::handle()` is called

**Test Steps**:
1. Mock `ActivationHandler::handle()` or verify it's called
2. Call `PluginBootstrap::onActivation()`
3. Assert ActivationHandler::handle() was called

**Assertions**:
- Verify `ActivationHandler::handle()` was called

---

##### Test 6: `test_on_deactivation_calls_deactivation_handler()`
**Purpose**: Verify onDeactivation calls DeactivationHandler.

**Input**: None (calls `onDeactivation()`)

**Expected Behavior**:
- `DeactivationHandler::handle()` is called

**Test Steps**:
1. Mock `DeactivationHandler::handle()` or verify it's called
2. Call `PluginBootstrap::onDeactivation()`
3. Assert DeactivationHandler::handle() was called

**Assertions**:
- Verify `DeactivationHandler::handle()` was called

---

##### Test 7: `test_initialize_core_registers_all_services()`
**Purpose**: Verify initializeCore registers all service providers and managers.

**Input**: None (calls `initializeCore()`)

**Expected Behavior**:
- All service providers and managers are initialized in correct order

**Test Steps**:
1. Mock all service providers and managers
2. Track initialization order
3. Call `PluginBootstrap::initializeCore()`
4. Assert all were called
5. Assert order is correct

**Assertions**:
- Verify `LoggingServiceProvider::register()` was called first
- Verify `ErrorHandlingServiceProvider::register()` was called second
- Verify `RoleManager::initialize()` was called
- Verify `RewriteCoordinator::initialize()` was called
- Verify `AdminMenuManager::initialize()` was called
- Verify `initializeConfigSystem()` was called last

---

##### Test 8: `test_initialize_config_system_with_doctrine_available()`
**Purpose**: Verify initializeConfigSystem creates EntityManager and repositories when Doctrine is available.

**Input**: None (calls `initializeConfigSystem()`)

**Expected Behavior**:
- EntityManager is created and stored in globals
- All repositories are created and stored in globals

**Test Steps**:
1. Mock Doctrine to be available
2. Mock `DoctrineFactory::createEntityManager()`
3. Mock repository classes
4. Call `PluginBootstrap::initializeConfigSystem()`
5. Assert EntityManager is in globals
6. Assert repositories are in globals

**Assertions**:
- Verify `$GLOBALS['minisite_entity_manager']` is set
- Verify `$GLOBALS['minisite_review_repository']` is set
- Verify `$GLOBALS['minisite_version_repository']` is set
- Verify `$GLOBALS['minisite_repository']` is set

---

##### Test 9: `test_initialize_config_system_without_doctrine_logs_warning()`
**Purpose**: Verify initializeConfigSystem logs warning when Doctrine is not available.

**Input**: None (calls `initializeConfigSystem()`)

**Expected Behavior**:
- Warning is logged
- Method returns early
- No repositories created

**Test Steps**:
1. Ensure Doctrine class does not exist
2. Mock logger's `warning()` method
3. Call `PluginBootstrap::initializeConfigSystem()`
4. Assert warning was logged
5. Assert no repositories in globals

**Assertions**:
- Verify `logger->warning()` was called
- Verify globals are not set

---

##### Test 10: `test_initialize_config_system_reuses_open_entity_manager()`
**Purpose**: Verify initializeConfigSystem reuses existing open EntityManager.

**Input**: None (calls `initializeConfigSystem()`)

**Expected Behavior**:
- If EntityManager exists in globals and is open, it's reused
- No new EntityManager is created

**Test Steps**:
1. Set up open EntityManager in globals
2. Mock `DoctrineFactory::createEntityManager()` to track calls
3. Call `PluginBootstrap::initializeConfigSystem()`
4. Assert createEntityManager was NOT called
5. Assert existing EntityManager is still in globals

**Assertions**:
- Verify `createEntityManager()` was NOT called
- Verify existing EntityManager is reused

---

##### Test 11: `test_initialize_config_system_creates_new_when_closed()`
**Purpose**: Verify initializeConfigSystem creates new EntityManager when existing is closed.

**Input**: None (calls `initializeConfigSystem()`)

**Expected Behavior**:
- If EntityManager exists but is closed, new one is created
- Old one is removed from globals

**Test Steps**:
1. Set up closed EntityManager in globals (mock to throw EntityManagerClosed)
2. Mock `DoctrineFactory::createEntityManager()` to return new EntityManager
3. Call `PluginBootstrap::initializeConfigSystem()`
4. Assert createEntityManager was called
5. Assert new EntityManager is in globals

**Assertions**:
- Verify `createEntityManager()` was called
- Verify new EntityManager is in globals

---

##### Test 12: `test_initialize_config_system_handles_exception()`
**Purpose**: Verify initializeConfigSystem handles exceptions gracefully.

**Input**: None (calls `initializeConfigSystem()`)

**Expected Behavior**:
- Exception is caught
- Error is logged
- Method returns without throwing

**Test Steps**:
1. Mock Doctrine to be available
2. Mock `DoctrineFactory::createEntityManager()` to throw exception
3. Mock logger's `error()` method
4. Call `PluginBootstrap::initializeConfigSystem()`
5. Assert error was logged
6. Assert no exception propagates

**Assertions**:
- Verify `logger->error()` was called
- Verify no exception thrown

---

##### Test 13: `test_initialize_features_calls_feature_registry()`
**Purpose**: Verify initializeFeatures calls FeatureRegistry.

**Input**: None (calls `initializeFeatures()`)

**Expected Behavior**:
- `FeatureRegistry::initializeAll()` is called

**Test Steps**:
1. Mock `FeatureRegistry::initializeAll()` or verify it's called
2. Call `PluginBootstrap::initializeFeatures()`
3. Assert FeatureRegistry::initializeAll() was called

**Assertions**:
- Verify `FeatureRegistry::initializeAll()` was called

---

### Integration Test Plan for `PluginBootstrap`

#### Test File Location
`tests/Integration/Core/PluginBootstrapIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_initialize_sets_up_complete_plugin_system()`
**Purpose**: Verify initialize sets up complete plugin system in WordPress.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- All hooks are registered
- Core systems are initialized
- Features are initialized

**Test Steps**:
1. Set up WordPress test environment
2. Call `PluginBootstrap::initialize()`
3. Trigger init hook
4. Verify all systems are initialized
5. Verify features are initialized

**Assertions**:
- Verify hooks exist
- Verify core systems work
- Verify features work

---

---

## File 5: `src/Core/RoleManager.php`

### Class Overview

**Purpose**: Manages WordPress roles and capabilities for the minisite plugin.

**Class Signature**:
```php
final class RoleManager
{
    public static function initialize(): void

    public static function syncRolesAndCapabilities(): void

    private static function getCapabilities(): array

    private static function getRoles(): array

    private static function addOrUpdateRole(string $slug, string $name, array $caps): void

    private static function grantAdminCapabilities(array $capabilities): void
}
```

**Key Responsibilities**:
- Define minisite-specific capabilities
- Define minisite-specific roles
- Register roles in WordPress
- Grant capabilities to WordPress Administrator
- Sync roles and capabilities on init

### Method Analysis

#### Method: `initialize(): void`

**Purpose**: Register sync method on init hook.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
- Registers `add_action('init', [self::class, 'syncRolesAndCapabilities'], 20)`

**Dependencies**:
- WordPress function: `add_action()`

**Test Focus**:
- Verify init hook is registered
- Verify priority is 20

---

#### Method: `syncRolesAndCapabilities(): void`

**Purpose**: Sync all roles and capabilities to WordPress.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Gets capabilities via `getCapabilities()`
2. Gets roles via `getRoles()`
3. For each role, calls `addOrUpdateRole()`
4. Calls `grantAdminCapabilities()` with all capabilities

**Dependencies**:
- `self::getCapabilities()`
- `self::getRoles()`
- `self::addOrUpdateRole()`
- `self::grantAdminCapabilities()`

**Test Focus**:
- Verify all roles are registered
- Verify admin capabilities are granted
- Test with existing roles
- Test with new roles

---

#### Method: `getCapabilities(): array`

**Purpose**: Get list of all minisite capabilities.

**Parameters**: None

**Return Type**: `array`

**Behavior**:
- Returns array of 19 capability strings

**Test Focus**:
- Verify returns array
- Verify contains all expected capabilities
- Verify count is 19

---

#### Method: `getRoles(): array`

**Purpose**: Get role definitions with capabilities.

**Parameters**: None

**Return Type**: `array`

**Behavior**:
- Returns array with 4 roles: `minisite_user`, `minisite_member`, `minisite_power`, `minisite_admin`
- Each role has `name` and `capabilities` array

**Test Focus**:
- Verify returns array
- Verify contains all 4 roles
- Verify each role has name and capabilities

---

#### Method: `addOrUpdateRole(string $slug, string $name, array $caps): void`

**Purpose**: Add new role or update existing role with capabilities.

**Parameters**:
- `$slug` (string): Role slug
- `$name` (string): Role display name
- `$caps` (array): Capabilities array

**Return Type**: `void`

**Behavior**:
1. Gets existing role via `get_role($slug)`
2. If role doesn't exist, creates it via `add_role($slug, $name, $caps)`
3. If role exists, adds each capability via `$role->add_cap($cap)`

**Dependencies**:
- WordPress function: `get_role()`
- WordPress function: `add_role()`
- WordPress role object: `add_cap()`

**Test Focus**:
- Test adding new role
- Test updating existing role
- Test capability assignment

---

#### Method: `grantAdminCapabilities(array $capabilities): void`

**Purpose**: Grant all minisite capabilities to WordPress Administrator.

**Parameters**:
- `$capabilities` (array): Array of capability strings

**Return Type**: `void`

**Behavior**:
1. Gets `administrator` role via `get_role('administrator')`
2. If role exists, adds each capability via `$role->add_cap($cap)`

**Dependencies**:
- WordPress function: `get_role()`
- WordPress role object: `add_cap()`

**Test Focus**:
- Test with administrator role existing
- Test with administrator role missing
- Verify all capabilities are granted

---

### Unit Test Plan for `RoleManager`

#### Test File Location
`tests/Unit/Core/RoleManagerTest.php`

#### Test Cases

##### Test 1: `test_initialize_registers_sync_hook()`
**Purpose**: Verify initialize registers sync on init hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `add_action('init', [self::class, 'syncRolesAndCapabilities'], 20)` is called

**Test Steps**:
1. Mock `add_action()` to track calls
2. Call `RoleManager::initialize()`
3. Assert init hook was registered with priority 20

**Assertions**:
- Verify `add_action()` was called
- Verify hook is `'init'`
- Verify callback is `[RoleManager::class, 'syncRolesAndCapabilities']`
- Verify priority is `20`

---

##### Test 2: `test_sync_roles_and_capabilities_registers_all_roles()`
**Purpose**: Verify syncRolesAndCapabilities registers all 4 roles.

**Input**: None (calls `syncRolesAndCapabilities()`)

**Expected Behavior**:
- All 4 roles are registered via `addOrUpdateRole()`

**Test Steps**:
1. Mock `get_role()`, `add_role()`, and role `add_cap()` methods
2. Call `RoleManager::syncRolesAndCapabilities()`
3. Assert all 4 roles were processed

**Assertions**:
- Verify `add_role()` or `get_role()` was called for each role
- Verify 4 roles total

---

##### Test 3: `test_sync_roles_and_capabilities_grants_admin_capabilities()`
**Purpose**: Verify syncRolesAndCapabilities grants capabilities to administrator.

**Input**: None (calls `syncRolesAndCapabilities()`)

**Expected Behavior**:
- `grantAdminCapabilities()` is called with all capabilities

**Test Steps**:
1. Mock `get_role('administrator')` to return role object
2. Mock role's `add_cap()` method
3. Call `RoleManager::syncRolesAndCapabilities()`
4. Assert add_cap was called for each capability

**Assertions**:
- Verify `add_cap()` was called for each capability
- Verify 19 capabilities total

---

##### Test 4: `test_get_capabilities_returns_array()`
**Purpose**: Verify getCapabilities returns array.

**Input**: None (calls `getCapabilities()` via reflection)

**Expected Behavior**:
- Returns array type

**Test Steps**:
1. Use reflection to call `getCapabilities()`
2. Assert return type is array

**Assertions**:
- `assertIsArray($capabilities)`

---

##### Test 5: `test_get_capabilities_returns_all_expected_capabilities()`
**Purpose**: Verify getCapabilities returns all 19 expected capabilities.

**Input**: None (calls `getCapabilities()` via reflection)

**Expected Behavior**:
- Returns array with 19 capabilities
- Contains all expected capability strings

**Test Steps**:
1. Use reflection to call `getCapabilities()`
2. Assert count is 19
3. Assert contains expected capabilities

**Assertions**:
- `assertCount(19, $capabilities)`
- `assertContains('minisite_read', $capabilities)`
- `assertContains('minisite_create', $capabilities)`
- (Repeat for all 19 capabilities)

---

##### Test 6: `test_get_roles_returns_array()`
**Purpose**: Verify getRoles returns array.

**Input**: None (calls `getRoles()` via reflection)

**Expected Behavior**:
- Returns array type

**Test Steps**:
1. Use reflection to call `getRoles()`
2. Assert return type is array

**Assertions**:
- `assertIsArray($roles)`

---

##### Test 7: `test_get_roles_returns_all_four_roles()`
**Purpose**: Verify getRoles returns all 4 role definitions.

**Input**: None (calls `getRoles()` via reflection)

**Expected Behavior**:
- Returns array with 4 roles
- Each role has `name` and `capabilities` keys

**Test Steps**:
1. Use reflection to call `getRoles()`
2. Assert count is 4
3. Assert contains all role slugs
4. Assert each role has required structure

**Assertions**:
- `assertCount(4, $roles)`
- `assertArrayHasKey('minisite_user', $roles)`
- `assertArrayHasKey('minisite_member', $roles)`
- `assertArrayHasKey('minisite_power', $roles)`
- `assertArrayHasKey('minisite_admin', $roles)`
- `assertArrayHasKey('name', $roles['minisite_user'])`
- `assertArrayHasKey('capabilities', $roles['minisite_user'])`

---

##### Test 8: `test_add_or_update_role_creates_new_role()`
**Purpose**: Verify addOrUpdateRole creates new role when it doesn't exist.

**Input**:
- `$slug = 'test_role'`
- `$name = 'Test Role'`
- `$caps = ['read' => true, 'test_cap' => true]`

**Expected Behavior**:
- `get_role()` returns null
- `add_role()` is called with correct parameters

**Test Steps**:
1. Mock `get_role()` to return null
2. Mock `add_role()` to track calls
3. Use reflection to call `addOrUpdateRole()`
4. Assert `add_role()` was called with correct parameters

**Assertions**:
- Verify `add_role()` was called
- Verify parameters match input

---

##### Test 9: `test_add_or_update_role_updates_existing_role()`
**Purpose**: Verify addOrUpdateRole updates existing role with capabilities.

**Input**:
- `$slug = 'existing_role'`
- `$name = 'Existing Role'`
- `$caps = ['new_cap' => true]`

**Expected Behavior**:
- `get_role()` returns role object
- `add_role()` is NOT called
- `$role->add_cap()` is called for each capability

**Test Steps**:
1. Mock `get_role()` to return role object
2. Mock role's `add_cap()` method
3. Use reflection to call `addOrUpdateRole()`
4. Assert `add_role()` was NOT called
5. Assert `add_cap()` was called

**Assertions**:
- Verify `add_role()` was NOT called
- Verify `add_cap()` was called for each capability

---

##### Test 10: `test_grant_admin_capabilities_with_administrator_role()`
**Purpose**: Verify grantAdminCapabilities grants capabilities when administrator role exists.

**Input**:
- `$capabilities = ['minisite_read', 'minisite_create']`

**Expected Behavior**:
- `get_role('administrator')` returns role object
- `$role->add_cap()` is called for each capability

**Test Steps**:
1. Mock `get_role('administrator')` to return role object
2. Mock role's `add_cap()` method
3. Use reflection to call `grantAdminCapabilities()`
4. Assert `add_cap()` was called for each capability

**Assertions**:
- Verify `add_cap()` was called
- Verify called for each capability in input

---

##### Test 11: `test_grant_admin_capabilities_without_administrator_role()`
**Purpose**: Verify grantAdminCapabilities handles missing administrator role gracefully.

**Input**:
- `$capabilities = ['minisite_read']`

**Expected Behavior**:
- `get_role('administrator')` returns null
- No errors thrown
- No capabilities granted

**Test Steps**:
1. Mock `get_role('administrator')` to return null
2. Use reflection to call `grantAdminCapabilities()`
3. Assert no errors thrown
4. Assert no capabilities were added

**Assertions**:
- No exceptions thrown
- Method completes successfully

---

### Integration Test Plan for `RoleManager`

#### Test File Location
`tests/Integration/Core/RoleManagerIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_sync_roles_and_capabilities_creates_roles_in_wordpress()`
**Purpose**: Verify roles are actually created in WordPress.

**Input**: None (calls `syncRolesAndCapabilities()`)

**Expected Behavior**:
- All 4 roles exist in WordPress
- Roles have correct names and capabilities

**Test Steps**:
1. Set up WordPress test environment
2. Call `RoleManager::syncRolesAndCapabilities()`
3. Verify roles exist via `get_role()`
4. Verify capabilities are assigned

**Assertions**:
- `assertNotNull(get_role('minisite_user'))`
- `assertTrue(get_role('minisite_user')->has_cap('minisite_read'))`
- (Repeat for all roles and key capabilities)

---

##### Integration Test 2: `test_sync_roles_and_capabilities_grants_admin_capabilities_in_wordpress()`
**Purpose**: Verify administrator role has all minisite capabilities in WordPress.

**Input**: None (calls `syncRolesAndCapabilities()`)

**Expected Behavior**:
- Administrator role has all 19 minisite capabilities

**Test Steps**:
1. Set up WordPress test environment
2. Get administrator role
3. Call `RoleManager::syncRolesAndCapabilities()`
4. Verify administrator has all capabilities

**Assertions**:
- `assertTrue($adminRole->has_cap('minisite_read'))`
- (Repeat for all 19 capabilities)

---

---

## File 6: `src/Core/AdminMenuManager.php`

### Class Overview

**Purpose**: Manages WordPress admin menu registration for the plugin.

**Class Signature**:
```php
final class AdminMenuManager
{
    private const MENU_SLUG = 'minisite-manager';
    private const MENU_TITLE = 'Minisite Manager';
    private const MENU_ICON = 'dashicons-admin-site-alt3';
    private const MENU_POSITION = 30;

    public static function initialize(): void

    public function register(): void

    public function addMainMenu(): void

    public function renderDashboardPage(): void

    public function renderMySitesPage(): void

    private function getMainMenuCapability(): string

    private function getSitesMenuCapability(): string
}
```

**Key Responsibilities**:
- Register main admin menu
- Register submenus (Dashboard, My Sites)
- Handle menu page rendering (redirects)
- Manage menu capabilities
- Add logging test menu in development

### Method Analysis

#### Method: `initialize(): void`

**Purpose**: Register admin menu on admin_menu hook.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
- Registers `add_action('admin_menu', function() { $adminMenu = new AdminMenuManager(); $adminMenu->register(); })`

**Dependencies**:
- WordPress function: `add_action()`

**Test Focus**:
- Verify admin_menu hook is registered
- Verify register() is called

---

#### Method: `register(): void`

**Purpose**: Main registration method that adds main menu.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Gets logger via `LoggingServiceProvider::getFeatureLogger('admin-menu')`
2. Logs debug message
3. Calls `addMainMenu()`

**Dependencies**:
- `LoggingServiceProvider::getFeatureLogger()`
- `self::addMainMenu()`

**Test Focus**:
- Verify logger is called
- Verify addMainMenu is called

---

#### Method: `addMainMenu(): void`

**Purpose**: Add main menu and submenus to WordPress admin.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Gets logger
2. Logs debug message with capability
3. Calls `add_menu_page()` for main menu
4. Calls `add_submenu_page()` for Dashboard submenu
5. Calls `add_submenu_page()` for My Sites submenu
6. If `WP_DEBUG` is defined and true, calls `LoggingTestController::addAdminMenu()`

**Dependencies**:
- WordPress function: `add_menu_page()`
- WordPress function: `add_submenu_page()`
- WordPress constant: `WP_DEBUG`
- `LoggingTestController::addAdminMenu()`
- `self::getMainMenuCapability()`
- `self::getSitesMenuCapability()`

**Test Focus**:
- Verify main menu is added
- Verify submenus are added
- Verify capabilities are used
- Test with WP_DEBUG true and false

---

#### Method: `renderDashboardPage(): void`

**Purpose**: Render dashboard page (redirects to front-end).

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Gets dashboard URL via `home_url('/account/dashboard')`
2. Redirects via `wp_redirect()`
3. Exits via `exit`

**Dependencies**:
- WordPress function: `home_url()`
- WordPress function: `wp_redirect()`
- PHP function: `exit`

**Test Focus**:
- Verify URL is constructed correctly
- Verify redirect is called
- Verify exit is called

---

#### Method: `renderMySitesPage(): void`

**Purpose**: Render My Sites page (redirects to front-end).

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Gets sites URL via `home_url('/account/sites')`
2. Redirects via `wp_redirect()`
3. Exits via `exit`

**Dependencies**:
- WordPress function: `home_url()`
- WordPress function: `wp_redirect()`
- PHP function: `exit`

**Test Focus**:
- Verify URL is constructed correctly
- Verify redirect is called
- Verify exit is called

---

#### Method: `getMainMenuCapability(): string`

**Purpose**: Get capability required for main menu.

**Parameters**: None

**Return Type**: `string`

**Behavior**:
- Returns `'read'` (temporary, TODO to switch to MINISITE_CAP_READ)

**Test Focus**:
- Verify returns 'read'

---

#### Method: `getSitesMenuCapability(): string`

**Purpose**: Get capability required for sites menu.

**Parameters**: None

**Return Type**: `string`

**Behavior**:
- Returns `'read'` (temporary, TODO to switch to MINISITE_CAP_READ)

**Test Focus**:
- Verify returns 'read'

---

### Unit Test Plan for `AdminMenuManager`

#### Test File Location
`tests/Unit/Core/AdminMenuManagerTest.php`

#### Test Cases

##### Test 1: `test_initialize_registers_admin_menu_hook()`
**Purpose**: Verify initialize registers admin_menu hook.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `add_action('admin_menu', ...)` is called
- Callback creates AdminMenuManager and calls register()

**Test Steps**:
1. Mock `add_action()` to track calls and execute callback
2. Call `AdminMenuManager::initialize()`
3. Assert admin_menu hook was registered
4. Assert register() was called

**Assertions**:
- Verify `add_action()` was called
- Verify hook is `'admin_menu'`
- Verify register() was called

---

##### Test 2: `test_register_calls_add_main_menu()`
**Purpose**: Verify register calls addMainMenu.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Logger is called
- `addMainMenu()` is called

**Test Steps**:
1. Mock logger
2. Create AdminMenuManager instance
3. Mock `addMainMenu()` or verify it's called
4. Call `register()`
5. Assert addMainMenu was called

**Assertions**:
- Verify logger was called
- Verify `addMainMenu()` was called

---

##### Test 3: `test_add_main_menu_adds_main_menu_page()`
**Purpose**: Verify addMainMenu adds main menu page.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- `add_menu_page()` is called with correct parameters

**Test Steps**:
1. Mock `add_menu_page()` to track calls
2. Mock logger
3. Create AdminMenuManager instance
4. Call `addMainMenu()`
5. Assert add_menu_page was called with correct parameters

**Assertions**:
- Verify `add_menu_page()` was called
- Verify parameters: title, menu_title, capability, slug, callback, icon, position

---

##### Test 4: `test_add_main_menu_adds_dashboard_submenu()`
**Purpose**: Verify addMainMenu adds Dashboard submenu.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- `add_submenu_page()` is called for Dashboard

**Test Steps**:
1. Mock `add_submenu_page()` to track calls
2. Create AdminMenuManager instance
3. Call `addMainMenu()`
4. Assert Dashboard submenu was added

**Assertions**:
- Verify `add_submenu_page()` was called
- Verify title is 'Dashboard'

---

##### Test 5: `test_add_main_menu_adds_my_sites_submenu()`
**Purpose**: Verify addMainMenu adds My Sites submenu.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- `add_submenu_page()` is called for My Sites

**Test Steps**:
1. Mock `add_submenu_page()` to track calls
2. Create AdminMenuManager instance
3. Call `addMainMenu()`
4. Assert My Sites submenu was added

**Assertions**:
- Verify `add_submenu_page()` was called
- Verify title is 'My Sites'

---

##### Test 6: `test_add_main_menu_adds_logging_test_menu_in_debug()`
**Purpose**: Verify addMainMenu adds logging test menu when WP_DEBUG is true.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- If `WP_DEBUG` is defined and true, `LoggingTestController::addAdminMenu()` is called

**Test Steps**:
1. Define `WP_DEBUG` as true
2. Mock `LoggingTestController::addAdminMenu()`
3. Create AdminMenuManager instance
4. Call `addMainMenu()`
5. Assert LoggingTestController::addAdminMenu() was called

**Assertions**:
- Verify `LoggingTestController::addAdminMenu()` was called

---

##### Test 7: `test_add_main_menu_skips_logging_test_menu_without_debug()`
**Purpose**: Verify addMainMenu skips logging test menu when WP_DEBUG is false or not defined.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- If `WP_DEBUG` is false or not defined, logging test menu is not added

**Test Steps**:
1. Ensure `WP_DEBUG` is not defined or false
2. Mock `LoggingTestController::addAdminMenu()`
3. Create AdminMenuManager instance
4. Call `addMainMenu()`
5. Assert LoggingTestController::addAdminMenu() was NOT called

**Assertions**:
- Verify `LoggingTestController::addAdminMenu()` was NOT called

---

##### Test 8: `test_render_dashboard_page_redirects_to_dashboard()`
**Purpose**: Verify renderDashboardPage redirects to front-end dashboard.

**Input**: None (calls `renderDashboardPage()`)

**Expected Behavior**:
- `home_url('/account/dashboard')` is called
- `wp_redirect()` is called with dashboard URL
- `exit` is called (or handled via TestTerminationHandler)

**Test Steps**:
1. Mock `home_url()` to return test URL
2. Mock `wp_redirect()` to track calls
3. Use TestTerminationHandler or mock exit
4. Create AdminMenuManager instance
5. Call `renderDashboardPage()`
6. Assert redirect was called with correct URL

**Assertions**:
- Verify `home_url('/account/dashboard')` was called
- Verify `wp_redirect()` was called with dashboard URL

---

##### Test 9: `test_render_my_sites_page_redirects_to_sites()`
**Purpose**: Verify renderMySitesPage redirects to front-end sites page.

**Input**: None (calls `renderMySitesPage()`)

**Expected Behavior**:
- `home_url('/account/sites')` is called
- `wp_redirect()` is called with sites URL
- `exit` is called (or handled via TestTerminationHandler)

**Test Steps**:
1. Mock `home_url()` to return test URL
2. Mock `wp_redirect()` to track calls
3. Use TestTerminationHandler or mock exit
4. Create AdminMenuManager instance
5. Call `renderMySitesPage()`
6. Assert redirect was called with correct URL

**Assertions**:
- Verify `home_url('/account/sites')` was called
- Verify `wp_redirect()` was called with sites URL

---

##### Test 10: `test_get_main_menu_capability_returns_read()`
**Purpose**: Verify getMainMenuCapability returns 'read'.

**Input**: None (calls `getMainMenuCapability()` via reflection)

**Expected Behavior**:
- Returns `'read'` string

**Test Steps**:
1. Create AdminMenuManager instance
2. Use reflection to call `getMainMenuCapability()`
3. Assert return value is 'read'

**Assertions**:
- `assertEquals('read', $capability)`

---

##### Test 11: `test_get_sites_menu_capability_returns_read()`
**Purpose**: Verify getSitesMenuCapability returns 'read'.

**Input**: None (calls `getSitesMenuCapability()` via reflection)

**Expected Behavior**:
- Returns `'read'` string

**Test Steps**:
1. Create AdminMenuManager instance
2. Use reflection to call `getSitesMenuCapability()`
3. Assert return value is 'read'

**Assertions**:
- `assertEquals('read', $capability)`

---

### Integration Test Plan for `AdminMenuManager`

#### Test File Location
`tests/Integration/Core/AdminMenuManagerIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_add_main_menu_creates_menu_in_wordpress()`
**Purpose**: Verify menu is actually created in WordPress admin.

**Input**: None (calls `addMainMenu()`)

**Expected Behavior**:
- Menu exists in WordPress admin menu system
- Menu has correct title and slug

**Test Steps**:
1. Set up WordPress test environment
2. Create AdminMenuManager instance
3. Call `addMainMenu()`
4. Verify menu exists in WordPress

**Assertions**:
- Verify menu is accessible
- Verify menu structure is correct

---

---

## File 7: `src/Core/RewriteCoordinator.php`

### Class Overview

**Purpose**: Coordinates WordPress rewrite system initialization and query variable registration.

**Class Signature**:
```php
final class RewriteCoordinator
{
    public static function initialize(): void

    public static function registerRewriteRules(): void

    public static function addQueryVars(array $vars): array
}
```

**Key Responsibilities**:
- Initialize rewrite system
- Register rewrite rules via RewriteRegistrar
- Add query variables to WordPress
- Handle rewrite rule flushing after activation

### Method Analysis

#### Method: `initialize(): void`

**Purpose**: Initialize rewrite system and handle flushing.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Calls `registerRewriteRules()` immediately
2. Registers `add_filter('query_vars', [self::class, 'addQueryVars'])`
3. Checks if `minisite_flush_rewrites` option exists
4. If option exists, calls `flush_rewrite_rules()` and deletes option

**Dependencies**:
- WordPress function: `add_filter()`
- WordPress function: `get_option()`
- WordPress function: `flush_rewrite_rules()`
- WordPress function: `delete_option()`
- `self::registerRewriteRules()`
- `self::addQueryVars()`

**Test Focus**:
- Verify rewrite rules are registered
- Verify query_vars filter is registered
- Verify flush happens when option exists
- Verify option is deleted after flush

---

#### Method: `registerRewriteRules(): void`

**Purpose**: Register rewrite rules via RewriteRegistrar.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Checks if `RewriteRegistrar` class exists
2. If exists, creates instance and calls `register()`

**Dependencies**:
- `class_exists('Minisite\Application\Http\RewriteRegistrar')`
- `RewriteRegistrar` class

**Test Focus**:
- Test with RewriteRegistrar available
- Test with RewriteRegistrar not available
- Verify register() is called

---

#### Method: `addQueryVars(array $vars): array`

**Purpose**: Add minisite query variables to WordPress query vars.

**Parameters**:
- `$vars` (array): Existing query variables array

**Return Type**: `array`

**Behavior**:
1. Adds 7 query variables to array:
   - `minisite`
   - `minisite_biz`
   - `minisite_loc`
   - `minisite_account`
   - `minisite_account_action`
   - `minisite_id`
   - `minisite_version_id`
2. Returns modified array

**Dependencies**: None

**Test Focus**:
- Verify all 7 variables are added
- Verify existing vars are preserved
- Verify return value is array

---

### Unit Test Plan for `RewriteCoordinator`

#### Test File Location
`tests/Unit/Core/RewriteCoordinatorTest.php`

#### Test Cases

##### Test 1: `test_initialize_registers_rewrite_rules()`
**Purpose**: Verify initialize calls registerRewriteRules.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `registerRewriteRules()` is called

**Test Steps**:
1. Mock `RewriteRegistrar` class
2. Mock `add_filter()` and other WordPress functions
3. Call `RewriteCoordinator::initialize()`
4. Assert RewriteRegistrar::register() was called

**Assertions**:
- Verify `registerRewriteRules()` execution path
- Verify RewriteRegistrar was used

---

##### Test 2: `test_initialize_registers_query_vars_filter()`
**Purpose**: Verify initialize registers query_vars filter.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- `add_filter('query_vars', [self::class, 'addQueryVars'])` is called

**Test Steps**:
1. Mock `add_filter()` to track calls
2. Call `RewriteCoordinator::initialize()`
3. Assert query_vars filter was registered

**Assertions**:
- Verify `add_filter()` was called
- Verify hook is `'query_vars'`
- Verify callback is `[RewriteCoordinator::class, 'addQueryVars']`

---

##### Test 3: `test_initialize_flushes_rewrite_rules_when_option_exists()`
**Purpose**: Verify initialize flushes rewrite rules when option is set.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- If `get_option('minisite_flush_rewrites')` returns truthy value
- `flush_rewrite_rules()` is called
- `delete_option('minisite_flush_rewrites')` is called

**Test Steps**:
1. Mock `get_option()` to return '1'
2. Mock `flush_rewrite_rules()` and `delete_option()` to track calls
3. Call `RewriteCoordinator::initialize()`
4. Assert flush and delete were called

**Assertions**:
- Verify `flush_rewrite_rules()` was called
- Verify `delete_option('minisite_flush_rewrites')` was called

---

##### Test 4: `test_initialize_skips_flush_when_option_missing()`
**Purpose**: Verify initialize skips flush when option doesn't exist.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- If `get_option('minisite_flush_rewrites')` returns false
- `flush_rewrite_rules()` is NOT called

**Test Steps**:
1. Mock `get_option()` to return false
2. Mock `flush_rewrite_rules()` to track calls
3. Call `RewriteCoordinator::initialize()`
4. Assert flush was NOT called

**Assertions**:
- Verify `flush_rewrite_rules()` was NOT called

---

##### Test 5: `test_register_rewrite_rules_with_rewrite_registrar_available()`
**Purpose**: Verify registerRewriteRules uses RewriteRegistrar when available.

**Input**: None (calls `registerRewriteRules()`)

**Expected Behavior**:
- RewriteRegistrar class exists
- RewriteRegistrar instance is created
- `register()` is called

**Test Steps**:
1. Ensure RewriteRegistrar class exists
2. Mock RewriteRegistrar's `register()` method
3. Call `RewriteCoordinator::registerRewriteRules()`
4. Assert register() was called

**Assertions**:
- Verify RewriteRegistrar was instantiated
- Verify `register()` was called

---

##### Test 6: `test_register_rewrite_rules_without_rewrite_registrar()`
**Purpose**: Verify registerRewriteRules handles missing RewriteRegistrar gracefully.

**Input**: None (calls `registerRewriteRules()`)

**Expected Behavior**:
- RewriteRegistrar class doesn't exist
- No errors thrown
- Method completes successfully

**Test Steps**:
1. Ensure RewriteRegistrar class doesn't exist (or mock class_exists)
2. Call `RewriteCoordinator::registerRewriteRules()`
3. Assert no errors thrown

**Assertions**:
- No exceptions thrown
- Method completes successfully

---

##### Test 7: `test_add_query_vars_adds_all_variables()`
**Purpose**: Verify addQueryVars adds all 7 expected query variables.

**Input**:
- `$vars = ['existing_var']`

**Expected Behavior**:
- All 7 minisite query variables are added
- Existing variables are preserved
- Returns array with 8 variables total

**Test Steps**:
1. Call `RewriteCoordinator::addQueryVars(['existing_var'])`
2. Assert return is array
3. Assert contains all 7 minisite variables
4. Assert contains existing variable

**Assertions**:
- `assertIsArray($result)`
- `assertCount(8, $result)` (1 existing + 7 new)
- `assertContains('minisite', $result)`
- `assertContains('minisite_biz', $result)`
- `assertContains('minisite_loc', $result)`
- `assertContains('minisite_account', $result)`
- `assertContains('minisite_account_action', $result)`
- `assertContains('minisite_id', $result)`
- `assertContains('minisite_version_id', $result)`
- `assertContains('existing_var', $result)`

---

##### Test 8: `test_add_query_vars_preserves_existing_variables()`
**Purpose**: Verify addQueryVars preserves existing query variables.

**Input**:
- `$vars = ['var1', 'var2', 'var3']`

**Expected Behavior**:
- All existing variables are in result
- New variables are added
- No duplicates

**Test Steps**:
1. Call `RewriteCoordinator::addQueryVars(['var1', 'var2', 'var3'])`
2. Assert all original vars are present
3. Assert new vars are added
4. Assert no duplicates

**Assertions**:
- `assertContains('var1', $result)`
- `assertContains('var2', $result)`
- `assertContains('var3', $result)`
- `assertCount(10, $result)` (3 existing + 7 new)

---

##### Test 9: `test_add_query_vars_handles_empty_array()`
**Purpose**: Verify addQueryVars handles empty input array.

**Input**:
- `$vars = []`

**Expected Behavior**:
- Returns array with 7 variables
- No errors thrown

**Test Steps**:
1. Call `RewriteCoordinator::addQueryVars([])`
2. Assert return is array
3. Assert contains 7 variables
4. Assert no errors

**Assertions**:
- `assertIsArray($result)`
- `assertCount(7, $result)`

---

### Integration Test Plan for `RewriteCoordinator`

#### Test File Location
`tests/Integration/Core/RewriteCoordinatorIntegrationTest.php`

#### Test Cases

##### Integration Test 1: `test_initialize_registers_rewrite_rules_in_wordpress()`
**Purpose**: Verify rewrite rules are registered in WordPress.

**Input**: None (calls `initialize()`)

**Expected Behavior**:
- Rewrite rules exist in WordPress rewrite system
- Query variables are registered

**Test Steps**:
1. Set up WordPress test environment
2. Call `RewriteCoordinator::initialize()`
3. Verify rewrite rules exist
4. Verify query variables are registered

**Assertions**:
- Verify rules are accessible
- Verify query vars are registered

---

---

## Implementation Guidelines

### Test File Naming Convention
- Unit tests: `{ClassName}Test.php`
- Integration tests: `{ClassName}IntegrationTest.php`

### Test Directory Structure
```
tests/
├── Unit/
│   └── Core/
│       ├── ActivationHandlerTest.php
│       ├── DeactivationHandlerTest.php
│       ├── FeatureRegistryTest.php
│       ├── PluginBootstrapTest.php
│       ├── RoleManagerTest.php
│       ├── AdminMenuManagerTest.php
│       └── RewriteCoordinatorTest.php
└── Integration/
    └── Core/
        ├── ActivationHandlerIntegrationTest.php
        ├── DeactivationHandlerIntegrationTest.php
        ├── FeatureRegistryIntegrationTest.php
        ├── PluginBootstrapIntegrationTest.php
        ├── RoleManagerIntegrationTest.php
        ├── AdminMenuManagerIntegrationTest.php
        └── RewriteCoordinatorIntegrationTest.php
```

### Required PHPUnit Attributes
- `#[CoversClass(ClassName::class)]` - Required for all test classes
- `#[Group('integration')]` - Required for integration tests

### Mocking Strategy

#### WordPress Functions
- Use `eval()` to create function stubs when functions don't exist
- Use Brain Monkey for more complex WordPress function mocking
- Store function calls in global arrays or mock objects for verification

#### Static Methods
- Use reflection to test private static methods
- Mock dependencies called by static methods
- Use real classes when possible for integration tests

#### WordPress Hooks
- Track hook registration via `add_action()` and `add_filter()` mocks
- Verify hooks are registered with correct priorities
- Test hook execution in integration tests

### Test Data Setup

#### WordPress Constants
- Define constants like `MINISITE_PLUGIN_FILE`, `MINISITE_DB_OPTION`, `WP_DEBUG`
- Use `define()` or `eval()` to set constants in tests

#### Global Variables
- Set `$GLOBALS` variables for testing (e.g., `$GLOBALS['minisite_config_manager']`)
- Clean up globals in `tearDown()`

### Common Test Patterns

#### Reflection for Private Methods
```php
$reflection = new \ReflectionClass($object);
$method = $reflection->getMethod('privateMethod');
$method->setAccessible(true);
$result = $method->invoke($object, $arg1, $arg2);
```

#### Static Method Testing
```php
// For static methods, call directly
ClassName::staticMethod();

// Or use reflection for private static methods
$reflection = new \ReflectionClass(ClassName::class);
$method = $reflection->getMethod('privateStaticMethod');
$method->setAccessible(true);
$result = $method->invoke(null, $arg1);
```

#### WordPress Function Mocking
```php
if (!function_exists('function_name')) {
    eval('
        function function_name($arg) {
            global $trackingArray;
            $trackingArray[] = $arg;
            return $result;
        }
    ');
}
```

### Cleanup in tearDown()

Always restore:
- Global variables (`$GLOBALS`)
- WordPress constants (if modified)
- Function definitions (if modified)
- Static properties (if modified)

### Expected Coverage After Implementation

| Class | Current Coverage | Target Coverage | Test Count |
|-------|-----------------|-----------------|------------|
| `ActivationHandler` | ~20% (reflection only) | 90%+ | 11 unit + 1 integration |
| `DeactivationHandler` | ~20% (reflection only) | 90%+ | 5 unit + 1 integration |
| `FeatureRegistry` | ~40% (basic tests) | 90%+ | 8 unit + 1 integration |
| `PluginBootstrap` | ~20% (reflection only) | 90%+ | 13 unit + 1 integration |
| `RoleManager` | ~30% (basic tests) | 90%+ | 11 unit + 2 integration |
| `AdminMenuManager` | ~20% (reflection only) | 90%+ | 11 unit + 1 integration |
| `RewriteCoordinator` | ~30% (basic tests) | 90%+ | 9 unit + 1 integration |

**Total Test Cases**: ~68 unit + ~8 integration = ~76 tests

### Success Criteria

1. **Coverage**: 90%+ for all Core classes
2. **Test Count**: Minimum 70+ test cases
3. **All Tests Passing**: No failures or errors
4. **Test Isolation**: Tests can run independently
5. **Documentation**: Tests are well-documented with clear names

### Notes

- Many Core classes use static methods - use reflection for private methods
- WordPress hook registration requires careful mocking
- Integration tests are essential for verifying WordPress integration
- Focus on testing business logic, not WordPress internals
- Use TestTerminationHandler for methods that call `exit()`
- Mock Doctrine classes when testing activation/bootstrap logic

### References

- Existing test patterns: `tests/Unit/Core/`
- Integration test base: `tests/Integration/BaseIntegrationTest.php`
- Testing guidelines: `docs/testing/integration-test-guidelines.md`
- Application folder plan: `docs/testing/APPLICATION_FOLDER_COVERAGE_PLAN.md`

