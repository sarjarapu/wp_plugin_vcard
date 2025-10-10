# Debugging Approaches for WordPress Plugin Issues

**Date**: October 10, 2025  
**Context**: Minisite Preview Blank Page Issue  
**Status**: ✅ Successfully Applied

## The "H1 Tag with Current Date/Time" Approach

### When to Use
- **Blank page issues** where you're not sure if the problem is routing, rendering, or data
- **Template rendering problems** where you need to isolate the issue
- **Authentication/authorization issues** where you want to test without access control
- **Complex debugging scenarios** where multiple systems could be failing

### The Process

#### Step 1: Start with Minimal Output
```php
public function renderPreview(object $previewData): void
{
    $currentDateTime = date('Y-m-d H:i:s');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Debug Test</title>
    </head>
    <body>
        <h1>' . esc_html($currentDateTime) . '</h1>
        <p>If you see this, basic rendering works!</p>
    </body>
    </html>';
    return; // Stop here for now
}
```

#### Step 2: Verify Basic Rendering
- **Test**: `curl -s "http://localhost:8000/account/sites/{id}/preview/{version}" | grep -E "(<h1>|<title>)"`
- **Expected**: HTML structure with current date/time
- **If fails**: Problem is in routing, hooks, or early execution
- **If succeeds**: Problem is in template rendering or data

#### Step 3: Add Complexity Incrementally
```php
// Add authentication check
if (!$this->wordPressManager->isUserLoggedIn()) {
    $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
}

// Add data fetching
$previewData = $this->editService->getMinisiteForPreview($siteId, $versionId);

// Add template rendering
$this->editRenderer->renderPreview($previewData);
```

#### Step 4: Test Each Layer
- **Authentication**: Does user login work?
- **Data Fetching**: Does service return data?
- **Template Rendering**: Does template render with data?

### Why This Works

1. **Isolates the Problem**: Separates routing from rendering from data
2. **Provides Immediate Feedback**: You know within seconds if basic rendering works
3. **Builds Confidence**: Each step confirms the previous layer is working
4. **Avoids Complex Debugging**: No need to trace through multiple systems initially

## Systematic Tracing Approach

### When to Use
- **Complex routing issues** with multiple rewrite rules
- **Hook priority conflicts** between features
- **Template rendering failures** where you need to trace execution
- **Service layer problems** where data isn't being fetched correctly

### The Process

#### Step 1: Use `error_log()` Instead of `echo`
```php
// BAD - causes "headers already sent" errors
echo '<!-- Debug: Starting renderPreview -->';

// GOOD - logs to server without affecting output
error_log('EditRenderer::renderPreview() called with data: ' . print_r($previewData, true));
```

#### Step 2: Trace Execution Flow
```php
public function handleEditRoutes(): void
{
    error_log('EditHooks::handleEditRoutes() called');
    
    $minisiteAccount = (int) $this->wordPressManager->getQueryVar('minisite_account');
    error_log('minisite_account = ' . $minisiteAccount);
    
    if ($minisiteAccount !== 1) {
        error_log('Not a minisite account route, exiting');
        return;
    }
    
    $action = $this->wordPressManager->getQueryVar('minisite_account_action');
    error_log('action = ' . $action);
    
    if ($action === 'preview') {
        error_log('Routing to preview controller');
        $this->editController->handlePreview();
        exit;
    }
}
```

#### Step 3: Check Each System Component
```php
// Check if Timber is available
if (class_exists('Timber\\Timber')) {
    error_log('Timber is available');
} else {
    error_log('Timber is NOT available');
}

// Check if template file exists
$templatePath = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber/v2025/minisite.twig';
if (file_exists($templatePath)) {
    error_log('Template file exists: ' . $templatePath);
} else {
    error_log('Template file NOT found: ' . $templatePath);
}

// Check Timber locations
error_log('Timber locations: ' . print_r(\Timber\Timber::$locations, true));
```

#### Step 4: Compare Working vs Broken Systems
```php
// Working system (MinisiteViewer)
$workingRenderer = new \Minisite\Application\Rendering\TimberRenderer('v2025');
$workingRenderer->render($minisite);

// Broken system (MinisiteEdit)
$brokenRenderer = new \Minisite\Features\MinisiteEdit\Rendering\EditRenderer();
$brokenRenderer->renderPreview($previewData);

// Compare the differences
```

### Why This Works

1. **Non-Intrusive**: `error_log()` doesn't affect HTML output or cause header errors
2. **Comprehensive**: Traces the entire execution flow
3. **Comparable**: Easy to compare working vs broken systems
4. **Persistent**: Logs remain in server logs for analysis

## Template Debugging Approach

### When to Use
- **Template not rendering** despite correct data
- **Template rendering but no content** (blank page with HTML structure)
- **Template path issues** where Timber can't find the file
- **Data structure mismatches** between what template expects vs what you provide

### The Process

#### Step 1: Verify Template File Exists
```bash
ls -la /path/to/templates/timber/v2025/minisite.twig
```

#### Step 2: Check Timber Locations
```php
error_log('Current Timber locations: ' . print_r(\Timber\Timber::$locations, true));

// Add missing locations
$timberBase = trailingslashit(MINISITE_PLUGIN_DIR) . 'templates/timber';
\Timber\Timber::$locations = array_merge(
    \Timber\Timber::$locations ?? [],
    [$timberBase]
);
```

#### Step 3: Test Template Rendering with Try/Catch
```php
try {
    \Timber\Timber::render('v2025/minisite.twig', $templateData);
    error_log('Template rendered successfully');
} catch (\Exception $e) {
    error_log('Template rendering error: ' . $e->getMessage());
    $this->renderFallbackPreview($previewData);
}
```

#### Step 4: Validate Data Structure
```php
// Check what the working system provides
$workingData = [
    'minisite' => $minisite,
    'reviews' => $reviews
];

// Check what the broken system provides
$brokenData = [
    'minisite' => $minisite,
    'version' => $version,
    'siteJson' => $siteJson
];

// Align the data structures
$alignedData = [
    'minisite' => $minisite,
    'reviews' => [], // Add missing field
    'version' => $version, // Keep additional fields
    'siteJson' => $siteJson
];
```

### Why This Works

1. **File System Verification**: Confirms template files exist where expected
2. **Path Resolution**: Ensures Timber can find templates in the right locations
3. **Error Handling**: Catches and logs template rendering errors
4. **Data Validation**: Ensures template receives expected data structure

## Key Lessons Learned

### 1. Start Simple, Add Complexity
- Begin with minimal output (H1 tag)
- Add one layer at a time (auth → data → template)
- Test each layer before moving to the next

### 2. Use Non-Intrusive Debugging
- `error_log()` instead of `echo` to avoid header errors
- HTML comments for immediate visual feedback
- Server logs for comprehensive tracing

### 3. Compare Working vs Broken Systems
- Analyze what the working system does differently
- Copy successful patterns from working code
- Identify missing components or incorrect configurations

### 4. Verify File System and Paths
- Check if template files exist
- Verify Timber can find templates in configured locations
- Ensure data structures match template expectations

### 5. Test Incrementally
- Don't try to fix everything at once
- Isolate problems by testing one component at a time
- Build confidence with each successful step

## Common Pitfalls to Avoid

### 1. Using `echo` for Debugging
```php
// BAD - causes "headers already sent" errors
echo '<!-- Debug info -->';

// GOOD - logs without affecting output
error_log('Debug info');
```

### 2. Assuming Template Paths
```php
// BAD - assumes template is in views directory
\Timber\Timber::render('minisite-preview.twig', $data);

// GOOD - verify template exists and Timber can find it
$templatePath = 'v2025/minisite.twig'; // Use same as working system
\Timber\Timber::render($templatePath, $data);
```

### 3. Ignoring Data Structure Differences
```php
// BAD - custom data structure
$data = ['minisite' => $minisite, 'version' => $version];

// GOOD - match working system's data structure
$data = ['minisite' => $minisite, 'reviews' => []];
```

### 4. Not Testing Each Layer
```php
// BAD - try to fix everything at once
public function complexMethod() {
    // 50 lines of complex logic
    // Hard to debug when it fails
}

// GOOD - test each layer separately
public function simpleMethod() {
    $this->testAuthentication();
    $this->testDataFetching();
    $this->testTemplateRendering();
}
```

This debugging approach was successfully used to resolve the minisite preview blank page issue by systematically isolating and fixing each component of the system.
