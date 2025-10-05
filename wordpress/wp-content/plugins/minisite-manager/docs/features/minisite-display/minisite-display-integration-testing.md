# MinisiteDisplay Feature Integration Testing Guide

## Overview

This document provides comprehensive testing instructions for the refactored MinisiteDisplay feature to ensure all WordPress hooks and functionalities are working correctly.

## Integration Changes Made

### 1. **Query Variable Alignment**
- Fixed query var names to match existing system:
  - `minisite_business_slug` → `minisite_biz`
  - `minisite_location_slug` → `minisite_loc`

### 2. **Feature Initialization**
- Added `MinisiteDisplayFeature::initialize()` to main plugin bootstrap
- Feature initializes with priority 5 (before main plugin hooks)

### 3. **Route Handling**
- New feature intercepts `/b/{business}/{location}` routes via `DisplayHooks`
- Falls back to old system if new feature is unavailable
- Maintains backward compatibility

## Pre-Testing Setup

### 1. **Verify Feature Files Exist**
```bash
# Check all MinisiteDisplay feature files are present
ls -la src/Features/MinisiteDisplay/
ls -la src/Features/MinisiteDisplay/{Controllers,Services,Handlers,Commands,Hooks,Http,Rendering,WordPress}/
```

### 2. **Check Database**
```bash
# Ensure you have test minisite data
# Check if minisites table has data
wp db query "SELECT id, name, business_slug, location_slug FROM wp_minisites LIMIT 5;"
```

### 3. **Verify Rewrite Rules**
```bash
# Check if rewrite rules are registered
wp rewrite list | grep "b/"
```

## Manual Testing Instructions

### Test 1: Basic Minisite Display

**URL Pattern**: `/b/{business-slug}/{location-slug}`

**Steps**:
1. Navigate to a known minisite URL (e.g., `/b/test-business/test-location`)
2. **Expected**: Minisite page loads with proper content
3. **Check**: 
   - Page title shows minisite name
   - Content displays correctly
   - No PHP errors in browser console
   - No 404 errors

**Test URLs** (replace with actual slugs from your database):
```
/b/coffee-shop/downtown
/b/restaurant/mall-location
/b/salon/main-street
```

### Test 2: 404 Handling

**Steps**:
1. Navigate to non-existent minisite URL (e.g., `/b/nonexistent-business/nonexistent-location`)
2. **Expected**: 
   - 404 status code
   - "Minisite not found" message
   - Proper 404 page structure

**Test URLs**:
```
/b/fake-business/fake-location
/b/does-not-exist/also-fake
```

### Test 3: Malformed URLs

**Steps**:
1. Test various malformed URL patterns
2. **Expected**: Proper error handling or redirects

**Test URLs**:
```
/b/only-business-slug
/b//empty-location
/b/business-slug/
/b/business-slug/location-slug/extra-path
```

### Test 4: WordPress Hook Integration

**Steps**:
1. Check if WordPress hooks are properly registered
2. **Expected**: No conflicts with other plugins

**Verification**:
```php
// Add this to a test file or wp-cli
add_action('init', function() {
    global $wp_filter;
    echo "Display hooks registered: " . (isset($wp_filter['template_redirect']) ? 'Yes' : 'No') . "\n";
}, 20);
```

### Test 5: Template Rendering

**Steps**:
1. Verify Timber/Twig templates are loading
2. **Expected**: Proper template rendering with data

**Check**:
- Template files exist in `templates/timber/v2025/`
- Minisite data is passed to templates
- Fallback rendering works if Timber is unavailable

### Test 6: Performance Testing

**Steps**:
1. Load multiple minisite pages
2. **Expected**: Fast page loads, no memory leaks

**Tools**:
- Browser DevTools Network tab
- WordPress Query Monitor plugin
- Server response time monitoring

## Debugging Commands

### 1. **Check Feature Initialization**
```bash
# Add debug output to verify feature is loading
wp eval "echo 'MinisiteDisplay Feature: ' . (class_exists('Minisite\Features\MinisiteDisplay\MinisiteDisplayFeature') ? 'Loaded' : 'Not Loaded') . PHP_EOL;"
```

### 2. **Verify Query Variables**
```bash
# Check if query vars are registered
wp eval "global \$wp; echo 'Query vars: ' . implode(', ', \$wp->public_query_vars) . PHP_EOL;"
```

### 3. **Test Database Connection**
```bash
# Verify minisite repository can connect
wp eval "
\$repo = new Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository(\$GLOBALS['wpdb']);
echo 'Repository connection: ' . (is_object(\$repo) ? 'OK' : 'Failed') . PHP_EOL;
"
```

### 4. **Check Rewrite Rules**
```bash
# List all rewrite rules
wp rewrite list
```

## Error Scenarios to Test

### 1. **Database Connection Issues**
- Simulate database connection failure
- **Expected**: Graceful error handling, fallback messages

### 2. **Missing Template Files**
- Remove template files temporarily
- **Expected**: Fallback rendering still works

### 3. **Memory Limits**
- Test with large minisite datasets
- **Expected**: No memory exhaustion errors

### 4. **Concurrent Requests**
- Load multiple minisite pages simultaneously
- **Expected**: No race conditions or conflicts

## Browser Testing

### 1. **Cross-Browser Compatibility**
Test in:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

### 2. **Mobile Responsiveness**
- Test on mobile devices
- Check responsive design
- Verify touch interactions

### 3. **Accessibility**
- Screen reader compatibility
- Keyboard navigation
- Color contrast
- Alt text for images

## Performance Benchmarks

### Expected Performance:
- **Page Load Time**: < 2 seconds
- **Database Queries**: < 5 queries per page
- **Memory Usage**: < 32MB per request
- **Cache Hit Rate**: > 80% (if caching enabled)

## Rollback Plan

If issues are found:

### 1. **Disable New Feature**
```php
// Comment out in minisite-manager.php
// MinisiteDisplayFeature::initialize();
```

### 2. **Revert to Old Controller**
The system automatically falls back to the old `MinisitePageController` if the new feature is unavailable.

### 3. **Database Rollback**
No database changes were made, so no rollback needed.

## Success Criteria

✅ **All tests pass**:
- [ ] Basic minisite display works
- [ ] 404 handling works correctly
- [ ] Malformed URLs handled gracefully
- [ ] WordPress hooks integrate properly
- [ ] Template rendering works
- [ ] Performance meets benchmarks
- [ ] No PHP errors or warnings
- [ ] Cross-browser compatibility
- [ ] Mobile responsiveness
- [ ] Accessibility standards met

## Troubleshooting Common Issues

### Issue: "Class not found" errors
**Solution**: Check autoloader and file paths

### Issue: 404 on all minisite pages
**Solution**: Verify rewrite rules are flushed

### Issue: Template not rendering
**Solution**: Check Timber installation and template paths

### Issue: Database connection errors
**Solution**: Verify database credentials and table structure

## Post-Testing Cleanup

1. **Remove debug code** added during testing
2. **Clear any test data** created
3. **Document any issues** found
4. **Update issue tracking** with results

## Next Steps After Successful Testing

1. **Remove old MinisitePageController** from `src/Application/Controllers/Front/`
2. **Update documentation** with new architecture
3. **Create unit tests** for the new feature
4. **Deploy to staging** for final validation
5. **Deploy to production** with monitoring

---

**Testing completed by**: [Your Name]  
**Date**: [Current Date]  
**Results**: [Pass/Fail with details]
