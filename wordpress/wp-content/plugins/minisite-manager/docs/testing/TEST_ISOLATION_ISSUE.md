# Test Isolation Issue: Final Classes Not Mockable When Running All Features Tests Together

## Problem

When running `./vendor/bin/phpunit --testsuite=Features`, 160 tests fail with:
```
ClassIsFinalException: Class "Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager" is declared "final" and cannot be doubled
```

However, when running individual feature test directories, all tests pass.

## Root Cause

The `DG\BypassFinals` extension is configured correctly in `phpunit.xml.dist`, but when running all Features tests together, final classes are being loaded into memory **before** BypassFinals can intercept them. Once a final class is loaded, it cannot be "un-finaled" later.

### Why Individual Tests Pass

When running individual test directories:
- Classes are loaded in isolation
- BypassFinals extension intercepts class loading before final classes are loaded
- Tests can successfully mock final classes

### Why All Features Tests Together Fail

When running all Features tests together:
- Multiple test classes load dependencies
- Some final classes (like `WordPressMinisiteManager`, `Version`, `Minisite`) get loaded early
- Once loaded, they remain "final" in memory
- Subsequent tests fail when trying to mock these classes

## Current Configuration

### phpunit.xml.dist
```xml
<extensions>
  <bootstrap class="DG\BypassFinals\PHPUnitExtension">
    <parameter name="bypassFinal" value="true"/>
    <parameter name="bypassReadOnly" value="false"/>
  </bootstrap>
</extensions>
```

### tests/bootstrap.php
```php
// Enable BypassFinals FIRST before any other code
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (class_exists('DG\BypassFinals')) {
    DG\BypassFinals::enable();
}
```

## Solution Implemented ✅

**Removed `final` keywords from classes that need to be mocked in tests.**

This is the fastest and most practical solution. By removing `final` from application/infrastructure layer classes (services, handlers, managers, controllers, entities), tests can mock them without needing BypassFinals or process isolation.

### Classes Modified:
- **WordPress Managers**: `WordPressMinisiteManager`, `WordPressListingManager`
- **Services**: `MinisiteViewService`, `MinisiteListingService`
- **Handlers**: `ListMinisitesHandler`, `ViewHandler`
- **Controllers**: `ListingController`
- **Request Handlers**: `ListingRequestHandler`, `ViewRequestHandler`
- **Response Handlers**: `ListingResponseHandler`, `AuthResponseHandler`, `ViewResponseHandler`
- **Renderers**: `ListingRenderer`, `ViewRenderer`
- **Entities**: `Minisite`, `Version`

### Result:
✅ All 606 Features tests now pass when run together  
✅ No process isolation needed (fast execution)  
✅ No BypassFinals dependency issues  
✅ Tests run in normal PHPUnit execution

## Alternative Solutions (Not Implemented)

### Option 1: Process Isolation (Slower)

Run each test in a separate process. This ensures complete isolation but is slower:

```bash
./vendor/bin/phpunit --testsuite=Features --process-isolation
```

**Pros:**
- Complete test isolation
- No class loading conflicts
- Guaranteed to work

**Cons:**
- Significantly slower (each test spawns new PHP process)
- Higher memory usage

### Option 2: Ensure BypassFinals Loads Before Plugin Autoloader

Modify `tests/bootstrap.php` to ensure BypassFinals is enabled before the plugin's autoloader loads classes:

```php
// Load composer autoloader FIRST
require_once __DIR__ . '/../vendor/autoload.php';

// Enable BypassFinals BEFORE any plugin classes are loaded
if (class_exists('DG\BypassFinals')) {
    DG\BypassFinals::enable();
}

// NOW load plugin (which may autoload final classes)
// But ensure this happens AFTER BypassFinals is enabled
require_once __DIR__ . '/../minisite-manager.php';
```

### Option 3: Remove Final Keywords (Not Recommended)

Remove `final` keywords from classes that need to be mocked. This breaks encapsulation but solves the mocking issue.

**Pros:**
- Tests work without special configuration
- No performance penalty

**Cons:**
- Breaks encapsulation
- Allows inheritance that shouldn't be allowed
- Not a good practice

### Option 4: Use Dependency Injection Instead of Mocks

Instead of mocking final classes, inject interfaces or use factory patterns:

```php
// Instead of:
$manager = $this->createMock(WordPressMinisiteManager::class);

// Use:
interface WordPressManagerInterface { ... }
$manager = $this->createMock(WordPressManagerInterface::class);
```

**Pros:**
- Better design (dependency inversion)
- No mocking issues
- More testable code

**Cons:**
- Requires refactoring
- May break existing code

### Option 5: Run Features Tests in Separate Suites

Create separate test suites for each feature and run them individually:

```xml
<testsuite name="Features">
  <!-- Don't use - runs all together -->
</testsuite>

<testsuite name="Features-Auth">
  <directory>tests/Unit/Features/Authentication</directory>
</testsuite>

<testsuite name="Features-Viewer">
  <directory>tests/Unit/Features/MinisiteViewer</directory>
</testsuite>
<!-- etc -->
```

Then run:
```bash
./vendor/bin/phpunit --testsuite=Features-Auth
./vendor/bin/phpunit --testsuite=Features-Viewer
# etc
```

## Recommended Approach

**For Development:**
- Use Option 5 (separate test suites) - run each feature's tests individually
- This is fast and provides good isolation

**For CI/CD:**
- Use Option 1 (process isolation) - ensures all tests pass together
- Accept slower test execution for guaranteed correctness

**Long-term:**
- Consider Option 4 (dependency injection) - refactor to use interfaces
- This is the best architectural solution

## Testing the Fix

```bash
# Test individual feature (should pass)
./vendor/bin/phpunit tests/Unit/Features/MinisiteViewer

# Test all features together (currently fails)
./vendor/bin/phpunit --testsuite=Features

# Test with process isolation (should pass but slower)
./vendor/bin/phpunit --testsuite=Features --process-isolation
```

## Affected Classes

The following final classes are causing issues:
- `WordPressMinisiteManager` (final)
- `WordPressVersionManager` (not final, but extends BaseWordPressManager)
- `Version` (final entity)
- `Minisite` (final entity)

## Next Steps

1. **Immediate:** Document that Features tests should be run individually or with `--process-isolation`
2. **Short-term:** Consider Option 5 (separate test suites) for better organization
3. **Long-term:** Refactor to use interfaces (Option 4) for better testability

