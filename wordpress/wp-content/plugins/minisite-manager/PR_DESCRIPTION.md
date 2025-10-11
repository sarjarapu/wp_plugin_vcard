# Fix: Version Preview and UI Consistency Issues

## 🐛 Issues Fixed

### Critical Data Loss Issue
- **Problem**: JSON import/save functionality was only storing partial siteJson data, causing version-specific previews to show blank content sections
- **Impact**: Critical data integrity issue breaking core versioning functionality
- **Root Cause**: `buildSiteJsonFromForm` method was overwriting existing data instead of preserving it

### Preview Functionality Issues
- **Problem**: Version-specific previews were missing reviews and edit screen preview was broken
- **Impact**: Incomplete preview experience and broken navigation
- **Root Cause**: Missing review fetching and preview URLs in template data

### UI Consistency Issues
- **Problem**: Button order was inconsistent across form sections
- **Impact**: Poor user experience and inconsistent interface
- **Root Cause**: Different sections had different button layouts

## 🔧 Changes Made

### 1. Data Preservation Fix
- **File**: `src/Features/MinisiteEdit/Services/EditService.php`
- **Changes**:
  - Completely rewrote `buildSiteJsonFromForm` method to preserve existing siteJson data
  - Only updates fields that are actually submitted in the form
  - Added comprehensive error logging for debugging
  - Ensures complete data structure preservation (hero, about, services, gallery, social, etc.)

### 2. Preview Functionality Fixes
- **Files**: 
  - `src/Features/MinisiteViewer/Rendering/ViewRenderer.php`
  - `src/Features/MinisiteEdit/Rendering/EditRenderer.php`
- **Changes**:
  - Added review fetching to version-specific previews using `ReviewRepository`
  - Added preview URLs (`preview_url`, `versions_url`, `edit_latest_url`) to edit screen template data
  - Added debug logging for review fetching
  - Fixed broken edit screen preview functionality

### 3. UI Consistency Fixes
- **Files**:
  - `templates/timber/components/forms/gallery-section.twig`
  - `templates/timber/components/forms/products-section.twig`
  - `templates/timber/components/layouts/live-preview.twig`
- **Changes**:
  - Standardized button order across all sections: **Primary Action → Scroll to Top**
  - Converted Preview "Open" button to primary style for consistency
  - Added scroll to top buttons where missing
  - Improved visual hierarchy and user experience

## 📊 Impact

### Before
- ❌ Version previews showed blank sections (missing hero, about, services, gallery, social)
- ❌ Reviews were not displayed in version-specific previews
- ❌ Edit screen preview was broken (missing preview URLs)
- ❌ Inconsistent button order across sections
- ❌ Data loss during save operations

### After
- ✅ Complete siteJson data preserved during save operations
- ✅ Reviews display correctly in all previews
- ✅ Edit screen preview works with proper navigation
- ✅ Consistent button order: Primary Action → Scroll to Top
- ✅ Professional, consistent UI across all sections

## 🧪 Testing

- [x] Save functionality preserves complete siteJson data
- [x] Version-specific previews show reviews
- [x] Edit screen preview works correctly
- [x] Button order is consistent across all sections
- [x] Error logging provides debugging information
- [x] No linting errors introduced

## 📝 Technical Details

### Data Preservation Strategy
- Start with existing siteJson data to preserve all content
- Only update fields that are actually submitted in the form
- Use `array_merge()` to preserve existing data while updating new values
- Maintain all sections: hero, about, whyUs, services, contact, gallery, social, etc.

### Review Fetching Implementation
- Uses same `ReviewRepository::listApprovedForMinisite()` as regular minisite views
- Added to `ViewRenderer::fetchReviews()` method
- Includes debug logging for troubleshooting

### UI Consistency Pattern
```html
<div class="flex items-center justify-between mb-4">
  <h2>Section Title</h2>
  <div class="flex items-center gap-3">
    <button class="primary-button">Primary Action</button>
    <a class="scroll-to-top">↑</a>
  </div>
</div>
```

## 🚀 Deployment Notes

- No database migrations required
- No breaking changes
- Backward compatible
- Improves existing functionality without affecting current data

---

**Fixes**: MIN-18 - Bug: JSON import/save functionality only storing partial siteJson data