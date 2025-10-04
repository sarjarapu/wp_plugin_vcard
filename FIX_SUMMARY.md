# Fix Summary: Location Fields Not Displayed on My Sites Listing Page

## Issue Description (MIN-5)
When creating a new minisite draft and importing existing JSON data, the location fields (city, state/region, country, postal code) were populated and saved correctly to the `wp_minisite_versions` table, but the "My Sites" listing page at `/account/sites` continued to show stale/empty location data.

## Root Cause Analysis

The issue was NOT in the import or save functionality - those were working correctly. The problem was in the **listing page query logic**:

1. **Draft data architecture**: When editing drafts, changes are saved only to `wp_minisite_versions` table (by design)
2. **Main table intent**: The `wp_minisites` table should only contain published data for performance
3. **Listing query bug**: The `handleList()` method in `SitesController.php` was fetching ALL minisites from `wp_minisites` table, regardless of status
4. **Result**: For draft minisites, the listing showed stale data from `wp_minisites` instead of the latest draft data from `wp_minisite_versions`

### The Design Pattern
```
┌─────────────────────────────────────────────────────┐
│ wp_minisites (Main Table)                           │
│ - Stores published minisite data for performance    │
│ - Updated only when version is published            │
│ - May contain stale data for draft minisites        │
└─────────────────────────────────────────────────────┘
                    │
                    │ Uses for listing query
                    ▼
┌─────────────────────────────────────────────────────┐
│ LISTING PAGE BUG: Always read from wp_minisites    │
│ ❌ Shows stale data for draft minisites            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│ wp_minisite_versions (Versions Table)               │
│ - Stores ALL draft and published versions           │
│ - Contains latest draft data                        │
│ - Updated every time draft is saved                 │
└─────────────────────────────────────────────────────┘
```

## Solution

Modified the listing query in `SitesController.php::handleList()` to:

1. Check the status of each minisite
2. For **draft** minisites: Fetch the latest draft version from `wp_minisite_versions` and overlay its data
3. For **published** minisites: Continue using data from `wp_minisites` (no change needed)

### Code Changes

```php
// Before: Always used data from wp_minisites
$items = array_map(
    function ($p) {
        // Used $p->city, $p->region, etc. directly
        // This showed stale data for drafts
    },
    $sites
);

// After: Overlay draft version data for draft minisites
$items = array_map(
    function ($p) use ($versionRepo) {
        // For draft minisites, overlay latest draft version data
        if ($p->status === 'draft') {
            $latestDraft = $versionRepo->findLatestDraft($p->id);
            if ($latestDraft) {
                $p->title       = $latestDraft->title ?? $p->title;
                $p->name        = $latestDraft->name ?? $p->name;
                $p->city        = $latestDraft->city ?? $p->city;
                $p->region      = $latestDraft->region ?? $p->region;
                $p->countryCode = $latestDraft->countryCode ?? $p->countryCode;
                $p->postalCode  = $latestDraft->postalCode ?? $p->postalCode;
                $p->updatedAt   = $latestDraft->createdAt ?? $p->updatedAt;
            }
        }
        // Now $p has fresh data from latest draft
    },
    $sites
);
```

## What This Fixes

### ✅ Draft Minisites
- Location fields imported from JSON are now visible on listing page
- Any edits to draft data (title, city, region, etc.) are immediately reflected
- Updated timestamp shows the latest draft save time

### ✅ Published Minisites
- No change - continues to use performant query from `wp_minisites`
- Published data is always in sync between both tables

## Testing Steps

1. **Create a new draft minisite**:
   - Navigate to `/account/sites/new/`
   - Click "Create Free Draft"

2. **Import JSON or manually edit**:
   - Import JSON with location data, OR
   - Manually edit and add city, region, country, postal code
   - Click "Save Draft"

3. **Verify listing page**:
   - Navigate back to `/account/sites`
   - **Expected**: Location column should show the imported/edited location
   - **Before fix**: Location column showed empty or stale data

4. **Verify updated timestamp**:
   - The "Updated At" column should show the draft save time

## Performance Considerations

- **Minimal impact**: The additional query (`findLatestDraft`) is only executed for draft minisites
- **Published minisites**: No extra queries - maintains original performance
- **Optimization opportunity**: If many drafts exist, consider adding caching or a single JOIN query

## Files Modified

1. `wordpress/wp-content/plugins/minisite-manager/src/Application/Controllers/Front/SitesController.php`
   - Modified `handleList()` method to overlay draft version data

## Related Architecture

This fix respects the existing versioning architecture where:
- Draft changes are isolated in `wp_minisite_versions`
- `wp_minisites` is updated only on publish via `publishMinisite()`
- The listing page now correctly shows draft data for drafts and published data for published sites

## Previous Issue Context

Last night's fix prevented draft data from overriding published site data in `wp_minisites`. This fix addresses the opposite side - ensuring draft data IS shown on the listing page where appropriate.

## Related Issue

- Linear Issue: MIN-5 "Bug: Location fields not saved when importing JSON data to new minisite draft"
- The title was slightly misleading - fields WERE being saved, just not displayed on the listing page
