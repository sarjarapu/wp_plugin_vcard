# ReviewManagement Test Coverage Gaps Analysis

## Current Coverage Status

### ReviewSeederService: 66.67% Methods, 77.39% Lines

**Missing Methods (2/6):**
1. `loadReviewsFromJson(string $jsonFile): array` - Protected method (0% coverage)
2. `seedAllTestReviews(array $minisiteIds): void` - Public method (0% coverage)

**Missing Code Paths:**
1. `createReviewFromJsonData()`:
   - Line 191-192: When `publishedAt` is provided in JSON data
   - Line 193-195: When status is `approved` but no `publishedAt` provided
   - Line 173-177: When `createdAt` is NOT provided in JSON (uses current time)
   - Line 179-183: When `updatedAt` is NOT provided in JSON (uses current time)
   - Line 185-188: When `createdBy` is NOT provided in JSON (uses current user)

2. `seedAllTestReviews()`:
   - Line 275: When `minisiteIds[$key]` is empty (skips that minisite)
   - Line 277-278: Successful JSON file loading and seeding
   - Line 279-282: Exception handling when JSON file loading fails

3. `loadReviewsFromJson()` (entire method):
   - Line 229-230: File not found exception
   - Line 233-240: Invalid JSON exception
   - Line 242-246: Missing 'reviews' array exception
   - Line 248: Successful return

### ReviewRepository: 77.78% Methods, 93.22% Lines

**Missing Methods (2/9):**
- Likely error handling paths in existing methods

## Recommendations

### High Priority (To reach 90%+ coverage)

1. **ReviewSeederService - loadReviewsFromJson()**
   - Test file not found scenario
   - Test invalid JSON scenario
   - Test missing 'reviews' array scenario
   - Test successful file loading

2. **ReviewSeederService - seedAllTestReviews()**
   - Test with valid minisite IDs and JSON files
   - Test with empty minisite IDs (should skip)
   - Test error handling when JSON file fails (should continue with other files)

3. **ReviewSeederService - createReviewFromJsonData() edge cases**
   - Test with publishedAt provided in JSON
   - Test with approved status but no publishedAt (should call markAsPublished)
   - Test with missing createdAt/updatedAt (should use current time)
   - Test with missing createdBy (should use current user)

### Medium Priority

4. **ReviewRepository - Error handling**
   - Test save() exception handling
   - Test delete() exception handling
   - Test find() exception handling

