# Application Folder Test Coverage Improvement Plan

## Overview

This document provides a comprehensive testing plan for the Application folder, which contains core application-level services for HTTP routing and template rendering. The plan is designed to be detailed enough for an automated agent (Linear app agent) to implement tests independently.

**Target Coverage**: 90%+ for all classes in the Application folder

**Current Status**:
- No existing tests in the active test directory
- Legacy tests exist in `delete_me/tests/` but are not part of the active test suite

## Application Folder Structure

The Application folder contains two main components:

1. **Http/RewriteRegistrar.php** - WordPress rewrite rule registration
2. **Rendering/TimberRenderer.php** - Timber template rendering with fallback

---

## File 1: `src/Application/Http/RewriteRegistrar.php`

### Class Overview

**Purpose**: Registers WordPress rewrite rules and tags for minisite profile routes and account management routes.

**Class Signature**:
```php
final class RewriteRegistrar
{
    public function register(): void
}
```

**Key Responsibilities**:
- Register rewrite tags for query variables
- Register rewrite rules for URL patterns
- Ensure all rules use 'top' priority
- Support minisite profile routes (`/b/{business}/{location}`)
- Support account routes (`/account/*`)
- Support site management routes (`/account/sites/*`)

### Method Analysis

#### Method: `register(): void`

**Purpose**: Registers all rewrite tags and rules for the minisite plugin.

**Parameters**: None

**Return Type**: `void`

**Behavior**:
1. Registers rewrite tags for query variables:
   - `%minisite%` → `([0-1])`
   - `%minisite_biz%` → `([^&]+)`
   - `%minisite_loc%` → `([^&]+)`
   - `%minisite_account%` → `([0-1])`
   - `%minisite_account_action%` → `([^&]+)`
   - `%minisite_id%` → `([a-f0-9]{24,32})`
   - `%minisite_version_id%` → `([0-9]+|current|latest)`

2. Registers rewrite rules:
   - Minisite profile: `^b/([^/]+)/([^/]+)/?$` → `index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]`
   - Account routes: `^account/(login|register|dashboard|logout|forgot|sites)/?$` → `index.php?minisite_account=1&minisite_account_action=$matches[1]`
   - Account sites new: `^account/sites/new/?$` → `index.php?minisite_account=1&minisite_account_action=new`
   - Account sites publish: `^account/sites/publish/?$` → `index.php?minisite_account=1&minisite_account_action=publish`
   - Account sites edit with version: `^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$` → `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]&minisite_version_id=$matches[2]`
   - Account sites edit: `^account/sites/([a-f0-9]{24,32})/edit/?$` → `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]`
   - Account sites preview: `^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$` → `index.php?minisite_account=1&minisite_account_action=preview&minisite_id=$matches[1]&minisite_version_id=$matches[2]`
   - Account sites versions: `^account/sites/([a-f0-9]{24,32})/versions/?$` → `index.php?minisite_account=1&minisite_account_action=versions&minisite_id=$matches[1]`
   - Account sites publish (with ID): `^account/sites/([a-f0-9]{24,32})/publish/?$` → `index.php?minisite_account=1&minisite_account_action=publish&minisite_id=$matches[1]`
   - Account sites default: `^account/sites/([a-f0-9]{24,32})/?$` → `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]`

3. All rules use `'top'` priority

**Dependencies**:
- WordPress function: `add_rewrite_tag()`
- WordPress function: `add_rewrite_rule()`

**Side Effects**:
- Modifies WordPress rewrite system
- Registers global rewrite tags and rules

---

### Unit Test Plan for `RewriteRegistrar`

#### Test File Location
`tests/Unit/Application/Http/RewriteRegistrarTest.php`

#### Test Class Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http;

use Minisite\Application\Http\RewriteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RewriteRegistrar::class)]
final class RewriteRegistrarTest extends TestCase
{
    // Test implementation
}
```

#### Test Cases

##### Test 1: `test_register_registers_all_rewrite_tags()`
**Purpose**: Verify all expected rewrite tags are registered with correct regex patterns.

**Input**: None (calls `register()`)

**Expected Behavior**:
- `add_rewrite_tag()` is called exactly 7 times
- Each tag is registered with the correct regex pattern

**Test Steps**:
1. Mock `add_rewrite_tag()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Assert `add_rewrite_tag()` was called 7 times
5. Assert each expected tag was registered with correct regex:
   - `%minisite%` with `([0-1])`
   - `%minisite_biz%` with `([^&]+)`
   - `%minisite_loc%` with `([^&]+)`
   - `%minisite_account%` with `([0-1])`
   - `%minisite_account_action%` with `([^&]+)`
   - `%minisite_id%` with `([a-f0-9]{24,32})`
   - `%minisite_version_id%` with `([0-9]+|current|latest)`

**Mocking Strategy**:
- Use global function mocking via `eval()` or Brain Monkey
- Track calls in a global array or mock object
- Verify call order and parameters

**Assertions**:
- `assertCount(7, $registeredTags)`
- `assertArrayHasKey('%minisite%', $registeredTags)`
- `assertEquals('([0-1])', $registeredTags['%minisite%'])`
- (Repeat for all 7 tags)

---

##### Test 2: `test_register_registers_minisite_profile_route()`
**Purpose**: Verify minisite profile route is registered correctly.

**Input**: None (calls `register()`)

**Expected Behavior**:
- `add_rewrite_rule()` is called with pattern `^b/([^/]+)/([^/]+)/?$`
- Redirect target is `index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]`
- Priority is `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the rule with pattern `^b/([^/]+)/([^/]+)/?$`
5. Assert redirect target matches expected value
6. Assert priority is `'top'`

**Mocking Strategy**:
- Track rewrite rules in an array
- Use pattern matching to find specific rule

**Assertions**:
- `assertNotNull($minisiteRoute)`
- `assertEquals('index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', $minisiteRoute['redirect'])`
- `assertEquals('top', $minisiteRoute['after'])`

---

##### Test 3: `test_register_registers_account_routes()`
**Purpose**: Verify account authentication routes are registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/(login|register|dashboard|logout|forgot|sites)/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=$matches[1]`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the account routes rule
5. Assert redirect target matches expected value
6. Assert priority is `'top'`

**Assertions**:
- `assertNotNull($accountRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=$matches[1]', $accountRoute['redirect'])`
- `assertEquals('top', $accountRoute['after'])`

---

##### Test 4: `test_register_registers_account_sites_new_route()`
**Purpose**: Verify account sites new route is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/new/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=new`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the new site route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($newSiteRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=new', $newSiteRoute['redirect'])`
- `assertEquals('top', $newSiteRoute['after'])`

---

##### Test 5: `test_register_registers_account_sites_publish_route()`
**Purpose**: Verify account sites publish route is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/publish/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=publish`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the publish route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($publishRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=publish', $publishRoute['redirect'])`
- `assertEquals('top', $publishRoute['after'])`

---

##### Test 6: `test_register_registers_account_sites_edit_routes()`
**Purpose**: Verify account sites edit routes (with and without version) are registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route 1 pattern: `^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$`
- Route 1 redirect: `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]&minisite_version_id=$matches[2]`
- Route 2 pattern: `^account/sites/([a-f0-9]{24,32})/edit/?$`
- Route 2 redirect: `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]`
- Both with priority `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find both edit routes
5. Assert redirect targets match expected values

**Assertions**:
- `assertNotNull($editWithVersionRoute)`
- `assertStringContainsString('minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]&minisite_version_id=$matches[2]', $editWithVersionRoute['redirect'])`
- `assertNotNull($editRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]', $editRoute['redirect'])`

---

##### Test 7: `test_register_registers_account_sites_preview_route()`
**Purpose**: Verify account sites preview route is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=preview&minisite_id=$matches[1]&minisite_version_id=$matches[2]`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the preview route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($previewRoute)`
- `assertStringContainsString('minisite_account=1&minisite_account_action=preview&minisite_id=$matches[1]&minisite_version_id=$matches[2]', $previewRoute['redirect'])`
- `assertEquals('top', $previewRoute['after'])`

---

##### Test 8: `test_register_registers_account_sites_versions_route()`
**Purpose**: Verify account sites versions route is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/([a-f0-9]{24,32})/versions/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=versions&minisite_id=$matches[1]`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the versions route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($versionsRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=versions&minisite_id=$matches[1]', $versionsRoute['redirect'])`
- `assertEquals('top', $versionsRoute['after'])`

---

##### Test 9: `test_register_registers_account_sites_publish_with_id_route()`
**Purpose**: Verify account sites publish route with ID is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/([a-f0-9]{24,32})/publish/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=publish&minisite_id=$matches[1]`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the publish with ID route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($publishWithIdRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=publish&minisite_id=$matches[1]', $publishWithIdRoute['redirect'])`
- `assertEquals('top', $publishWithIdRoute['after'])`

---

##### Test 10: `test_register_registers_account_sites_default_route()`
**Purpose**: Verify account sites default route (fallback to edit) is registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Route pattern: `^account/sites/([a-f0-9]{24,32})/?$`
- Redirect: `index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]`
- Priority: `'top'`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Find the default route
5. Assert redirect target matches expected value

**Assertions**:
- `assertNotNull($defaultRoute)`
- `assertEquals('index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]', $defaultRoute['redirect'])`
- `assertEquals('top', $defaultRoute['after'])`

---

##### Test 11: `test_register_all_rules_have_top_priority()`
**Purpose**: Verify all rewrite rules are registered with 'top' priority.

**Input**: None (calls `register()`)

**Expected Behavior**:
- All registered rules have `'top'` priority

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Iterate through all registered rules
5. Assert each rule has `'top'` priority

**Assertions**:
- `assertEquals('top', $rule['after'])` for all rules

---

##### Test 12: `test_register_registers_correct_number_of_rules()`
**Purpose**: Verify exactly 9 rewrite rules are registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Exactly 9 rewrite rules are registered

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Count registered rules
5. Assert count is 9

**Assertions**:
- `assertCount(9, $registeredRules)`

---

##### Test 13: `test_register_registers_correct_number_of_tags()`
**Purpose**: Verify exactly 7 rewrite tags are registered.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Exactly 7 rewrite tags are registered

**Test Steps**:
1. Mock `add_rewrite_tag()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Count registered tags
5. Assert count is 7

**Assertions**:
- `assertCount(7, $registeredTags)`

---

##### Test 14: `test_register_does_not_interfere_with_existing_rules()`
**Purpose**: Verify registration doesn't remove or modify existing rewrite rules.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Existing rules remain intact
- New rules are added without removing old ones

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Pre-register some existing rules
3. Create `RewriteRegistrar` instance
4. Call `register()`
5. Assert existing rules are still present
6. Assert new rules are added

**Assertions**:
- `assertContains($existingRule, $allRules)`
- `assertCount($expectedTotal, $allRules)`

---

##### Test 15: `test_register_creates_valid_regex_patterns()`
**Purpose**: Verify all registered regex patterns are valid.

**Input**: None (calls `register()`)

**Expected Behavior**:
- All regex patterns can be compiled by PHP's `preg_match()`

**Test Steps**:
1. Mock `add_rewrite_rule()` to track calls
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Extract all regex patterns from registered rules
5. Test each pattern with `preg_match()`
6. Assert no patterns cause regex errors

**Assertions**:
- `assertNotFalse(@preg_match('/' . $escapedPattern . '/', 'test'))` for each pattern

---

### Integration Test Plan for `RewriteRegistrar`

#### Test File Location
`tests/Integration/Application/Http/RewriteRegistrarIntegrationTest.php`

#### Test Class Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Http;

use Minisite\Application\Http\RewriteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(RewriteRegistrar::class)]
#[Group('integration')]
final class RewriteRegistrarIntegrationTest extends TestCase
{
    // Test implementation
}
```

#### Test Cases

##### Integration Test 1: `test_register_adds_rewrite_rules_to_wordpress_system()`
**Purpose**: Verify rewrite rules are actually added to WordPress rewrite system.

**Input**: None (calls `register()`)

**Expected Behavior**:
- Rules are accessible via `$GLOBALS['wp_rewrite']->rules` or global tracking arrays
- All expected patterns are present

**Test Steps**:
1. Set up WordPress rewrite system mock
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Query `$GLOBALS['wp_rewrite']->rules` or global tracking arrays
5. Assert all expected patterns are present

**Assertions**:
- `assertContains('^b/([^/]+)/([^/]+)/?$', $allRulePatterns)`
- (Repeat for all 9 rules)

---

##### Integration Test 2: `test_register_maintains_top_priority_for_all_rules()`
**Purpose**: Verify all rules maintain top priority in WordPress system.

**Input**: None (calls `register()`)

**Expected Behavior**:
- All rules in WordPress system have top priority

**Test Steps**:
1. Set up WordPress rewrite system
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Query WordPress rewrite system
5. Assert all rules have top priority

**Assertions**:
- `assertEquals('top', $rule['after'])` for all rules

---

##### Integration Test 3: `test_register_creates_valid_redirect_targets()`
**Purpose**: Verify all redirect targets are valid WordPress query strings.

**Input**: None (calls `register()`)

**Expected Behavior**:
- All redirect targets start with `index.php?`
- All redirect targets contain minisite-related parameters

**Test Steps**:
1. Set up WordPress rewrite system
2. Create `RewriteRegistrar` instance
3. Call `register()`
4. Extract all redirect targets
5. Assert each target starts with `index.php?`
6. Assert each target contains `minisite` parameter

**Assertions**:
- `assertStringStartsWith('index.php?', $redirect)`
- `assertStringContainsString('minisite', $redirect)`

---

---

## File 2: `src/Application/Rendering/TimberRenderer.php`

### Class Overview

**Purpose**: Handles template rendering using Timber with fallback rendering when Timber is not available.

**Class Signature**:
```php
class TimberRenderer
{
    public function __construct(private string $variant = 'v2025')

    public function render(MinisiteViewModel $viewModel): void

    protected function renderFallback(MinisiteViewModel $viewModel): void

    protected function registerTimberLocations(): void
}
```

**Key Responsibilities**:
- Render templates using Timber when available
- Provide fallback HTML rendering when Timber is unavailable
- Register Timber template locations
- Convert view model to array for template context

**Dependencies**:
- `MinisiteViewModel` (from `Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel`)
- Timber class (`Timber\Timber`)
- WordPress constants (`MINISITE_PLUGIN_DIR`)
- WordPress functions (`trailingslashit()`, `esc_html()`)

### Method Analysis

#### Method: `__construct(private string $variant = 'v2025')`

**Purpose**: Initialize renderer with template variant.

**Parameters**:
- `$variant` (string, default: `'v2025'`): Template variant to use

**Return Type**: Constructor (void)

**Behavior**:
- Stores variant in private property
- No side effects

**Test Focus**:
- Verify variant is stored correctly
- Verify default variant is `'v2025'`

---

#### Method: `render(MinisiteViewModel $viewModel): void`

**Purpose**: Render minisite using view model, with Timber if available, otherwise fallback.

**Parameters**:
- `$viewModel` (MinisiteViewModel): View model containing minisite data, reviews, and user flags

**Return Type**: `void`

**Behavior**:
1. Check if `Timber\Timber` class exists
2. If not, call `renderFallback()` and return
3. If yes:
   - Call `registerTimberLocations()`
   - Convert view model to array via `$viewModel->toArray()`
   - Call `Timber\Timber::render()` with template path and context
   - Template path: `{variant}/minisite.twig` (e.g., `v2025/minisite.twig`)

**Dependencies**:
- `class_exists('Timber\Timber')`
- `MinisiteViewModel::toArray()`
- `Timber\Timber::render()`
- `registerTimberLocations()` (protected method)

**Side Effects**:
- Outputs HTML to stdout
- Registers Timber locations
- May set HTTP headers

**Test Focus**:
- Test with Timber available
- Test with Timber not available (fallback path)
- Test template path construction
- Test view model conversion
- Test Timber location registration

---

#### Method: `renderFallback(MinisiteViewModel $viewModel): void`

**Purpose**: Render basic HTML fallback when Timber is not available.

**Parameters**:
- `$viewModel` (MinisiteViewModel): View model containing minisite data

**Return Type**: `void`

**Behavior**:
1. Get minisite from view model via `$viewModel->getMinisite()`
2. Set HTTP header: `Content-Type: text/html; charset=utf-8`
3. Output HTML:
   - `<!doctype html>`
   - `<meta charset="utf-8">`
   - `<title>{escaped minisite title}</title>`
   - `<h1>{escaped minisite name}</h1>`

**Dependencies**:
- `MinisiteViewModel::getMinisite()`
- `header()` function
- `esc_html()` function
- `echo` (output)

**Side Effects**:
- Sets HTTP header
- Outputs HTML to stdout

**Test Focus**:
- Test HTML output structure
- Test escaping of minisite title and name
- Test HTTP header setting
- Test with various minisite data

---

#### Method: `registerTimberLocations(): void`

**Purpose**: Register plugin template directory with Timber.

**Return Type**: `void`

**Behavior**:
1. Get base path: `trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber'`
2. Merge with existing `Timber\Timber::$locations` array
3. Remove duplicates using `array_unique()`
4. Re-index array using `array_values()`
5. Assign to `Timber\Timber::$locations`

**Dependencies**:
- `MINISITE_PLUGIN_DIR` constant
- `trailingslashit()` function
- `Timber\Timber::$locations` static property
- `array_merge()`, `array_unique()`, `array_values()`

**Side Effects**:
- Modifies `Timber\Timber::$locations` static property

**Test Focus**:
- Test location is added correctly
- Test existing locations are preserved
- Test duplicates are removed
- Test array is re-indexed

---

### Unit Test Plan for `TimberRenderer`

#### Test File Location
`tests/Unit/Application/Rendering/TimberRendererTest.php`

#### Test Class Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rendering;

use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimberRenderer::class)]
final class TimberRendererTest extends TestCase
{
    // Test implementation
}
```

#### Test Cases

##### Test 1: `test_constructor_sets_variant()`
**Purpose**: Verify constructor stores variant correctly.

**Input**:
- `$variant = 'v2024'`

**Expected Behavior**:
- Variant property is set to `'v2024'`

**Test Steps**:
1. Create `TimberRenderer` with variant `'v2024'`
2. Use reflection to access private `$variant` property
3. Assert value is `'v2024'`

**Mocking Strategy**:
- No mocks needed

**Assertions**:
- `assertSame('v2024', $variantProperty->getValue($renderer))`

---

##### Test 2: `test_constructor_uses_default_variant()`
**Purpose**: Verify constructor uses default variant when none provided.

**Input**: None (uses default)

**Expected Behavior**:
- Variant property is set to `'v2025'` (default)

**Test Steps**:
1. Create `TimberRenderer` without variant parameter
2. Use reflection to access private `$variant` property
3. Assert value is `'v2025'`

**Assertions**:
- `assertSame('v2025', $variantProperty->getValue($renderer))`

---

##### Test 3: `test_render_with_timber_available_calls_timber_render()`
**Purpose**: Verify render method calls Timber when Timber class exists.

**Input**:
- `$viewModel` (MinisiteViewModel with test data)

**Expected Behavior**:
- `registerTimberLocations()` is called
- `$viewModel->toArray()` is called
- `Timber\Timber::render()` is called with correct template path and context

**Test Steps**:
1. Mock `Timber\Timber` class (create stub class)
2. Set up `Timber\Timber::$locations` static property
3. Mock `$viewModel->toArray()` to return test data
4. Create `TimberRenderer` instance
5. Capture output with `ob_start()`
6. Call `render($viewModel)`
7. Clean up output buffer
8. Assert `Timber\Timber::render()` was called (or verify method executed)

**Mocking Strategy**:
- Create stub `Timber\Timber` class with static `$locations` and `render()` method
- Mock `MinisiteViewModel` or create real instance with test data
- Use output buffering to capture any output

**Assertions**:
- Verify method executes without fatal errors
- Verify `registerTimberLocations()` was called (check `Timber\Timber::$locations`)
- Verify template path includes variant (e.g., `v2025/minisite.twig`)

**Note**: Actual Timber rendering may fail in unit tests due to missing WordPress dependencies, but the method should execute and reach the `Timber::render()` call.

---

##### Test 4: `test_render_without_timber_calls_fallback()`
**Purpose**: Verify render method calls fallback when Timber class doesn't exist.

**Input**:
- `$viewModel` (MinisiteViewModel with test data)

**Expected Behavior**:
- `class_exists('Timber\Timber')` returns false
- `renderFallback()` is called
- Fallback HTML is output

**Test Steps**:
1. Ensure `Timber\Timber` class doesn't exist (or unset it)
2. Create `MinisiteViewModel` with test minisite data
3. Create `TimberRenderer` instance
4. Mock `header()` function (or use output buffering)
5. Capture output with `ob_start()`
6. Call `render($viewModel)`
7. Get output with `ob_get_clean()`
8. Assert fallback HTML is present

**Mocking Strategy**:
- Ensure `Timber\Timber` class is not available
- Mock `header()` function if needed
- Mock `esc_html()` function
- Use output buffering

**Assertions**:
- `assertStringContainsString('<!doctype html>', $output)`
- `assertStringContainsString($minisite->title, $output)` (escaped)
- `assertStringContainsString($minisite->name, $output)` (escaped)

---

##### Test 5: `test_render_registers_timber_locations()`
**Purpose**: Verify render method registers Timber locations when Timber is available.

**Input**:
- `$viewModel` (MinisiteViewModel)

**Expected Behavior**:
- `registerTimberLocations()` is called
- Plugin template directory is added to `Timber\Timber::$locations`

**Test Steps**:
1. Create stub `Timber\Timber` class with static `$locations` property
2. Initialize `Timber\Timber::$locations = []`
3. Define `MINISITE_PLUGIN_DIR` constant
4. Mock `trailingslashit()` function
5. Create `TimberRenderer` instance
6. Call `render($viewModel)` (may fail at Timber::render, but locations should be registered)
7. Check `Timber\Timber::$locations` array
8. Assert plugin template directory is present

**Mocking Strategy**:
- Create stub `Timber\Timber` class
- Mock WordPress constants and functions
- Use try-catch to handle Timber rendering errors

**Assertions**:
- `assertContains($expectedPath, Timber\Timber::$locations)`
- `assertIsArray(Timber\Timber::$locations)`

---

##### Test 6: `test_render_converts_view_model_to_array()`
**Purpose**: Verify render method converts view model to array for template context.

**Input**:
- `$viewModel` (MinisiteViewModel with test data)

**Expected Behavior**:
- `$viewModel->toArray()` is called
- Result is passed to `Timber\Timber::render()`

**Test Steps**:
1. Create stub `Timber\Timber` class
2. Create `MinisiteViewModel` with test data
3. Mock `$viewModel->toArray()` to return known data structure
4. Create `TimberRenderer` instance
5. Call `render($viewModel)`
6. Verify `toArray()` result structure (or verify method executed)

**Mocking Strategy**:
- Mock `MinisiteViewModel` or use real instance
- Verify `toArray()` is called (or check context passed to Timber)

**Assertions**:
- Verify method executes
- Verify context structure matches `toArray()` result

---

##### Test 7: `test_render_uses_correct_template_path()`
**Purpose**: Verify render method uses correct template path based on variant.

**Input**:
- `$viewModel` (MinisiteViewModel)
- `$variant = 'v2024'` (constructor parameter)

**Expected Behavior**:
- Template path includes variant: `v2024/minisite.twig`

**Test Steps**:
1. Create stub `Timber\Timber` class with `render()` method that captures arguments
2. Create `TimberRenderer` with variant `'v2024'`
3. Call `render($viewModel)`
4. Check captured template path argument
5. Assert path contains `v2024/minisite.twig`

**Mocking Strategy**:
- Create stub `Timber\Timber` class that stores render arguments
- Verify template path in stored arguments

**Assertions**:
- `assertContains('v2024/minisite.twig', $capturedTemplatePath)`

---

##### Test 8: `test_render_fallback_outputs_html_structure()`
**Purpose**: Verify fallback method outputs correct HTML structure.

**Input**:
- `$viewModel` (MinisiteViewModel with minisite having title "Test Title" and name "Test Name")

**Expected Behavior**:
- Output contains `<!doctype html>`
- Output contains `<meta charset="utf-8">`
- Output contains `<title>Test Title</title>` (escaped)
- Output contains `<h1>Test Name</h1>` (escaped)

**Test Steps**:
1. Create `MinisiteViewModel` with test minisite
2. Create `TimberRenderer` instance
3. Mock `header()` function
4. Mock `esc_html()` function to return escaped string
5. Capture output with `ob_start()`
6. Use reflection to call `renderFallback($viewModel)`
7. Get output with `ob_get_clean()`
8. Assert HTML structure

**Mocking Strategy**:
- Mock `header()` function
- Mock `esc_html()` function
- Use output buffering

**Assertions**:
- `assertStringContainsString('<!doctype html>', $output)`
- `assertStringContainsString('<meta charset="utf-8">', $output)`
- `assertStringContainsString('<title>', $output)`
- `assertStringContainsString('<h1>', $output)`

---

##### Test 9: `test_render_fallback_escapes_minisite_data()`
**Purpose**: Verify fallback method escapes minisite title and name.

**Input**:
- `$viewModel` (MinisiteViewModel with minisite having title containing HTML: `"Test <script>alert('xss')</script> Title"`)

**Expected Behavior**:
- `esc_html()` is called for title and name
- Output contains escaped HTML entities

**Test Steps**:
1. Create `MinisiteViewModel` with minisite containing HTML in title/name
2. Create `TimberRenderer` instance
3. Mock `esc_html()` to track calls and return escaped strings
4. Capture output with `ob_start()`
5. Use reflection to call `renderFallback($viewModel)`
6. Get output with `ob_get_clean()`
7. Assert `esc_html()` was called
8. Assert output contains escaped strings

**Mocking Strategy**:
- Mock `esc_html()` function to track calls
- Verify escaped output

**Assertions**:
- Verify `esc_html()` was called (via mock)
- `assertStringNotContainsString('<script>', $output)`
- `assertStringContainsString('&lt;script&gt;', $output)` (or equivalent)

---

##### Test 10: `test_render_fallback_sets_content_type_header()`
**Purpose**: Verify fallback method sets correct HTTP header.

**Input**:
- `$viewModel` (MinisiteViewModel)

**Expected Behavior**:
- `header('Content-Type: text/html; charset=utf-8')` is called

**Test Steps**:
1. Create `MinisiteViewModel` with test data
2. Create `TimberRenderer` instance
3. Mock `header()` function to track calls
4. Use reflection to call `renderFallback($viewModel)`
5. Assert `header()` was called with correct arguments

**Mocking Strategy**:
- Mock `header()` function
- Track function calls

**Assertions**:
- Verify `header()` was called
- Verify call includes `'Content-Type: text/html; charset=utf-8'`

---

##### Test 11: `test_register_timber_locations_adds_plugin_directory()`
**Purpose**: Verify registerTimberLocations adds plugin template directory.

**Input**: None (calls protected method via reflection)

**Expected Behavior**:
- Plugin template directory is added to `Timber\Timber::$locations`
- Path is: `trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber'`

**Test Steps**:
1. Define `MINISITE_PLUGIN_DIR` constant (e.g., `'/path/to/plugin'`)
2. Mock `trailingslashit()` function
3. Create stub `Timber\Timber` class with static `$locations` property
4. Initialize `Timber\Timber::$locations = []`
5. Create `TimberRenderer` instance
6. Use reflection to call `registerTimberLocations()`
7. Check `Timber\Timber::$locations` array
8. Assert plugin directory is present

**Mocking Strategy**:
- Create stub `Timber\Timber` class
- Mock WordPress constants and functions

**Assertions**:
- `assertContains($expectedPath, Timber\Timber::$locations)`
- `assertIsArray(Timber\Timber::$locations)`

---

##### Test 12: `test_register_timber_locations_preserves_existing_locations()`
**Purpose**: Verify registerTimberLocations preserves existing Timber locations.

**Input**: None (calls protected method via reflection)

**Expected Behavior**:
- Existing locations in `Timber\Timber::$locations` are preserved
- New location is added to existing array

**Test Steps**:
1. Create stub `Timber\Timber` class
2. Initialize `Timber\Timber::$locations = ['/existing/path']`
3. Define `MINISITE_PLUGIN_DIR` constant
4. Mock `trailingslashit()` function
5. Create `TimberRenderer` instance
6. Use reflection to call `registerTimberLocations()`
7. Check `Timber\Timber::$locations` array
8. Assert existing location is still present
9. Assert new location is added

**Assertions**:
- `assertContains('/existing/path', Timber\Timber::$locations)`
- `assertContains($pluginPath, Timber\Timber::$locations)`
- `assertCount(2, Timber\Timber::$locations)` (or appropriate count)

---

##### Test 13: `test_register_timber_locations_removes_duplicates()`
**Purpose**: Verify registerTimberLocations removes duplicate locations.

**Input**: None (calls protected method via reflection)

**Expected Behavior**:
- If plugin directory already exists in `Timber\Timber::$locations`, it's not added twice
- `array_unique()` removes duplicates

**Test Steps**:
1. Create stub `Timber\Timber` class
2. Initialize `Timber\Timber::$locations = [$pluginPath]` (same as plugin path)
3. Define `MINISITE_PLUGIN_DIR` constant
4. Mock `trailingslashit()` function
5. Create `TimberRenderer` instance
6. Use reflection to call `registerTimberLocations()`
7. Check `Timber\Timber::$locations` array
8. Assert plugin path appears only once

**Assertions**:
- `assertCount(1, array_filter(Timber\Timber::$locations, fn($loc) => $loc === $pluginPath))`

---

##### Test 14: `test_register_timber_locations_reindexes_array()`
**Purpose**: Verify registerTimberLocations re-indexes array after removing duplicates.

**Input**: None (calls protected method via reflection)

**Expected Behavior**:
- After `array_unique()`, array is re-indexed with `array_values()`
- Array keys are sequential (0, 1, 2, ...)

**Test Steps**:
1. Create stub `Timber\Timber` class
2. Initialize `Timber\Timber::$locations` with duplicates
3. Define `MINISITE_PLUGIN_DIR` constant
4. Mock `trailingslashit()` function
5. Create `TimberRenderer` instance
6. Use reflection to call `registerTimberLocations()`
7. Check array keys
8. Assert keys are sequential starting from 0

**Assertions**:
- `assertEquals([0, 1, 2, ...], array_keys(Timber\Timber::$locations))`

---

##### Test 15: `test_render_fallback_handles_empty_minisite_data()`
**Purpose**: Verify fallback method handles minisite with empty/null title and name.

**Input**:
- `$viewModel` (MinisiteViewModel with minisite having empty title and name)

**Expected Behavior**:
- Method executes without errors
- Output contains empty title and name (or default values)

**Test Steps**:
1. Create `MinisiteViewModel` with minisite having empty title and name
2. Create `TimberRenderer` instance
3. Mock `header()` and `esc_html()` functions
4. Capture output with `ob_start()`
5. Use reflection to call `renderFallback($viewModel)`
6. Get output with `ob_get_clean()`
7. Assert method executed without errors
8. Assert output structure is valid

**Assertions**:
- No exceptions thrown
- Output contains valid HTML structure

---

### Integration Test Plan for `TimberRenderer`

#### Test File Location
`tests/Integration/Application/Rendering/TimberRendererIntegrationTest.php`

#### Test Class Structure
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Rendering;

use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimberRenderer::class)]
#[Group('integration')]
final class TimberRendererIntegrationTest extends TestCase
{
    // Test implementation
}
```

#### Test Cases

##### Integration Test 1: `test_render_with_timber_renders_template()`
**Purpose**: Verify render method actually renders Timber template when Timber is available.

**Input**:
- `$viewModel` (MinisiteViewModel with real minisite data)

**Expected Behavior**:
- Timber template is rendered
- Output contains expected HTML from template

**Test Steps**:
1. Ensure Timber is available in test environment
2. Ensure template file exists: `templates/timber/v2025/minisite.twig`
3. Create `MinisiteViewModel` with real minisite data
4. Create `TimberRenderer` instance
5. Capture output with `ob_start()`
6. Call `render($viewModel)`
7. Get output with `ob_get_clean()`
8. Assert output contains expected content

**Assertions**:
- `assertNotEmpty($output)`
- `assertStringContainsString($expectedContent, $output)`

**Note**: This test requires Timber to be installed and template files to exist.

---

##### Integration Test 2: `test_render_registers_timber_locations_in_wordpress()`
**Purpose**: Verify Timber locations are registered in actual WordPress/Timber environment.

**Input**:
- `$viewModel` (MinisiteViewModel)

**Expected Behavior**:
- Plugin template directory is added to Timber locations
- Location is accessible in WordPress environment

**Test Steps**:
1. Set up WordPress environment with Timber
2. Create `TimberRenderer` instance
3. Call `render($viewModel)` (may fail at rendering, but locations should be registered)
4. Check `Timber\Timber::$locations` in WordPress context
5. Assert plugin directory is present

**Assertions**:
- `assertContains($pluginPath, Timber\Timber::$locations)`

---

##### Integration Test 3: `test_render_fallback_outputs_valid_html()`
**Purpose**: Verify fallback HTML is valid and properly formatted.

**Input**:
- `$viewModel` (MinisiteViewModel with test data)

**Expected Behavior**:
- Output is valid HTML
- HTML structure is correct

**Test Steps**:
1. Create `MinisiteViewModel` with test data
2. Create `TimberRenderer` instance
3. Ensure Timber is not available
4. Capture output with `ob_start()`
5. Call `render($viewModel)`
6. Get output with `ob_get_clean()`
7. Validate HTML structure (optional: use HTML validator)

**Assertions**:
- `assertStringStartsWith('<!doctype html>', trim($output))`
- `assertStringContainsString('<title>', $output)`
- `assertStringContainsString('<h1>', $output)`

---

## Implementation Guidelines

### Test File Naming Convention
- Unit tests: `{ClassName}Test.php`
- Integration tests: `{ClassName}IntegrationTest.php`

### Test Directory Structure
```
tests/
├── Unit/
│   └── Application/
│       ├── Http/
│       │   └── RewriteRegistrarTest.php
│       └── Rendering/
│           └── TimberRendererTest.php
└── Integration/
    └── Application/
        ├── Http/
        │   └── RewriteRegistrarIntegrationTest.php
        └── Rendering/
            └── TimberRendererIntegrationTest.php
```

### Required PHPUnit Attributes
- `#[CoversClass(ClassName::class)]` - Required for all test classes
- `#[Group('integration')]` - Required for integration tests

### Mocking Strategy

#### WordPress Functions
- Use `eval()` to create function stubs when functions don't exist
- Use Brain Monkey for more complex WordPress function mocking
- Store function calls in global arrays or mock objects for verification

#### Timber Class
- Create stub class using `eval()` or anonymous class
- Use static properties for `$locations`
- Implement `render()` method that captures arguments

#### View Models
- Create real `MinisiteViewModel` instances with test data
- Or use mocks if specific method behavior needs to be verified

### Test Data Setup

#### Minisite Entity
Create test minisite with:
- Valid ID (24-32 character hex string)
- Title and name (for fallback rendering)
- Other required properties

#### MinisiteViewModel
Create test view model with:
- Minisite entity
- Reviews array (can be empty)
- `isBookmarked` flag
- `canEdit` flag

### Common Test Patterns

#### Output Buffering
```php
ob_start();
// Call method that outputs
$output = ob_get_clean();
// Assert on $output
```

#### Reflection for Protected Methods
```php
$reflection = new \ReflectionClass($object);
$method = $reflection->getMethod('protectedMethod');
$method->setAccessible(true);
$result = $method->invoke($object, $arg1, $arg2);
```

#### Global Function Mocking
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
- Global variables (`$GLOBALS['wp_rewrite']`, etc.)
- Static properties (`Timber\Timber::$locations`)
- Function definitions (if modified)
- Output buffers (if not cleaned)

### Expected Coverage After Implementation

| Class | Current Coverage | Target Coverage | Test Count |
|-------|-----------------|-----------------|------------|
| `RewriteRegistrar` | 0% | 90%+ | 15 unit + 3 integration |
| `TimberRenderer` | 0% | 90%+ | 15 unit + 3 integration |

**Total Test Cases**: ~36 tests

### Success Criteria

1. **Coverage**: 90%+ for both classes
2. **Test Count**: Minimum 30+ test cases
3. **All Tests Passing**: No failures or errors
4. **Test Isolation**: Tests can run independently
5. **Documentation**: Tests are well-documented with clear names

### Notes

- Some Timber-specific code may require integration tests when unit tests are insufficient
- WordPress rewrite system testing may require integration tests for full validation
- Focus on testing business logic, not WordPress internals
- Use output buffering carefully to avoid conflicts between tests
- Mock WordPress functions consistently across tests

### References

- Existing test patterns: `tests/Unit/Features/*/`
- Integration test base: `tests/Integration/BaseIntegrationTest.php`
- Testing guidelines: `docs/testing/integration-test-guidelines.md`
- Legacy tests (reference only): `delete_me/tests/Unit/Application/`

