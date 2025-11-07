# Integration Test Analysis - Version Management

## Error Patterns

### Primary Issue: Missing `wp_minisite_versions` Table

**Error**: `Table 'minisite_test.wp_minisite_versions' doesn't exist`

**Root Cause**: Integration tests create their own `EntityManager` with limited entity paths. The `Version20251105000000.php` migration exists, but:

1. **Integration tests don't include Version entity path** in their `ORMSetup::createAttributeMetadataConfiguration()` calls
2. **Migration runner discovers migrations** based on entity paths, so if Version entity path isn't included, the migration isn't discovered
3. **Example**: `ConfigurationManagementWorkflowIntegrationTest` only includes:
   ```php
   paths: [
       __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
   ],
   ```
   Missing: `__DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities'`

### Secondary Issue: Missing Sample Data

**Pattern**: Even if the table exists, integration tests may fail because:
- No sample versions exist in the database
- Tests expect certain minisites to have versions
- Similar to how `ReviewSeederService` populates reviews from JSON

## Comparison with Config and Review Patterns

### ConfigSeeder Pattern
- **Location**: `src/Features/ConfigurationManagement/Services/ConfigSeeder.php`
- **JSON Source**: `data/json/config/default-config.json`
- **Method**: `seedDefaults(ConfigurationManagementService $configManager)`
- **Behavior**: Loads from JSON, creates configs if they don't exist (preserves existing)

### ReviewSeederService Pattern
- **Location**: `src/Features/ReviewManagement/Services/ReviewSeederService.php`
- **JSON Source**: `data/json/reviews/*.json` (e.g., `acme-dental-reviews.json`)
- **Methods**:
  - `insertReview()` - Create single review
  - `createReviewFromJsonData()` - Create from JSON array
  - `seedReviewsForMinisite()` - Seed multiple reviews for a minisite
  - `seedAllTestReviews()` - Seed all test reviews for standard minisites
- **Behavior**: Loads from JSON files, creates Review entities, saves via repository

### VersionSeederService - MISSING

**Status**: No seeder exists for Version entities

**What's Needed**:
1. Create `src/Features/VersionManagement/Services/VersionSeederService.php`
2. Create JSON files in `data/json/versions/` (or similar)
3. Methods to:
   - Create version from JSON data
   - Seed versions for a minisite
   - Seed all test versions for standard minisites

## Integration Test Setup Patterns

### Current Pattern (Incomplete)

```php
// ConfigurationManagementWorkflowIntegrationTest.php
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [
        __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
        // ❌ Missing: VersionManagement entity path
    ],
    isDevMode: true
);
```

### Correct Pattern (Should Include All Required Entities)

```php
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [
        __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
        __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities', // ✅ Add this
        __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',   // ✅ If needed
        __DIR__ . '/../../../../src/Domain/Entities',                             // ✅ If needed
    ],
    isDevMode: true
);
```

### DoctrineFactory Pattern (Production - Already Correct)

```php
// src/Infrastructure/Persistence/Doctrine/DoctrineFactory.php
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: array(
        __DIR__ . '/../../../Domain/Entities',
        __DIR__ . '/../../../Features/ReviewManagement/Domain/Entities',
        __DIR__ . '/../../../Features/VersionManagement/Domain/Entities', // ✅ Already included
    ),
    isDevMode: defined('WP_DEBUG') && WP_DEBUG
);
```

## Recommended Fixes

### Fix 1: Update Integration Test Entity Paths

**Files to Update**:
- `tests/Integration/Features/ConfigurationManagement/ConfigurationManagementWorkflowIntegrationTest.php`
- Any other integration tests that create their own EntityManager

**Change**: Add Version entity path to `ORMSetup::createAttributeMetadataConfiguration()` paths array

**Why**: Ensures `Version20251105000000` migration is discovered and executed

### Fix 2: Create VersionSeederService

**New File**: `src/Features/VersionManagement/Services/VersionSeederService.php`

**Pattern**: Follow `ReviewSeederService` pattern:
- Constructor: Inject `VersionRepositoryInterface`
- Methods:
  - `createVersionFromJsonData(string $minisiteId, array $versionData): Version`
  - `seedVersionsForMinisite(string $minisiteId, array $versions): void`
  - `seedAllTestVersions(array $minisiteIds): void` (optional)
  - `loadVersionsFromJson(string $jsonFile): array` (protected helper)

**JSON Structure**: Similar to review JSON files:
```json
{
  "versions": [
    {
      "versionNumber": 1,
      "status": "published",
      "siteJson": { ... },
      "businessName": "...",
      "businessCity": "...",
      ...
    }
  ]
}
```

### Fix 3: Create Version JSON Files

**Location**: `data/json/versions/` (new directory)

**Files** (matching minisite JSON files):
- `acme-dental-versions.json`
- `lotus-textiles-versions.json`
- `green-bites-versions.json`
- `swift-transit-versions.json`

**Content**: Sample versions for each minisite (published and draft versions)

### Fix 4: Update Integration Test Setup

**Pattern**: Similar to how `ReviewSeederServiceIntegrationTest` works:
1. Run migrations (creates tables)
2. Seed minisites (if needed)
3. Seed versions using `VersionSeederService`
4. Run tests

## Priority

1. **HIGH**: Fix integration test entity paths (Fix 1) - This will resolve 96 errors immediately
2. **MEDIUM**: Create VersionSeederService (Fix 2) - Needed for comprehensive test coverage
3. **LOW**: Create version JSON files (Fix 3) - Can be done incrementally

## Next Steps

1. ✅ Document the issue (this file)
2. ⏳ Fix integration test entity paths
3. ⏳ Create VersionSeederService
4. ⏳ Create sample version JSON files
5. ⏳ Update integration tests to use seeder

