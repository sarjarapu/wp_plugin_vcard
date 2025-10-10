# Fix: Resolve Minisite Preview Blank Page Issue

## 🐛 Problem
The minisite preview functionality at `/account/sites/{id}/preview/{version}` was returning a blank page despite:
- 200 HTTP response (successful)
- Route being properly matched and handled
- All debugging showing the system was working

## 🔍 Root Cause
**Missing Timber base directory in `setupTimberLocations()`**

The `EditRenderer::setupTimberLocations()` method was only adding:
- `templates/timber/views/`
- `templates/timber/components/`

But **NOT** the base directory `templates/timber/` where the `v2025/` subdirectory exists.

When trying to render `v2025/minisite.twig`, Timber couldn't find it because:
- Template path: `templates/timber/v2025/minisite.twig`
- Timber locations: `templates/timber/views/`, `templates/timber/components/`
- Result: Template not found, no content rendered (577 bytes vs 83,409 bytes)

## ✅ Solution
1. **Fixed Timber Locations**: Added `templates/timber/` to `\Timber\Timber::$locations`
2. **Used Correct Template**: Changed from `minisite-preview.twig` to `v2025/minisite.twig` (same as MinisiteViewer)
3. **Aligned Data Structure**: Added `reviews: []` to match MinisiteViewer expectations
4. **Added Error Handling**: Wrapped template rendering in try/catch
5. **Restored Security**: Re-enabled authentication and access control

## 📁 Files Changed
- `src/Features/MinisiteEdit/Rendering/EditRenderer.php` - **Main fix**
- `src/Features/MinisiteEdit/Controllers/EditController.php` - Auth re-enabled
- `src/Features/MinisiteEdit/Services/EditService.php` - Access control re-enabled
- `docs/issues/preview-blank-page-issue.md` - Updated with solution
- `docs/debugging-approaches.md` - New debugging methodology

## 🧪 Testing
- ✅ Preview route works: `http://localhost:8000/account/sites/{id}/preview/{version}`
- ✅ Template renders correctly with database data (83,409 bytes)
- ✅ Both `current` and specific version IDs work
- ✅ Authentication and access control enforced
- ✅ No debug code left in production

## 📚 Documentation
- Updated issue documentation with root cause analysis
- Created comprehensive debugging approaches guide
- Documented the "H1 tag with current date/time" debugging methodology

## 🔧 Code Quality
- Removed all temporary debug statements
- Added proper error handling
- Maintained security with authentication checks
- Clean, production-ready code
