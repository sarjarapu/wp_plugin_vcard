# VersionManagement Doctrine Migration Plan

## Overview
This document outlines the plan to migrate VersionManagement from the custom database versioning system to Doctrine Migration model, following the patterns established by ConfigManagement and ReviewManagement features.

## Current State

### Custom Database Versioning System
- **Table Creation**: SQL file-based (`data/db/tables/minisite_versions.sql`)
- **Migration**: Custom `VersioningController` with `_1_0_0_CreateBase.php` migration
- **Entity**: `src/Domain/Entities/Version.php` - Plain PHP class (no Doctrine attributes)
- **Repository**: `src/Infrastructure/Persistence/Repositories/VersionRepository.php` - Uses `$wpdb` directly
- **Table**: `wp_minisite_versions` with 27 columns including:
  - Core versioning: `id`, `minisite_id`, `version_number`, `status`, `label`, `comment`
  - Timestamps: `created_at`, `published_at`, `created_by`
  - Rollback tracking: `source_version_id`
  - Minisite fields: `business_slug`, `location_slug`, `title`, `name`, `city`, `region`, `country_code`, `postal_code`, `location_point` (POINT), `site_template`, `palette`, `industry`, `default_locale`, `schema_version`, `site_version`, `site_json`, `search_terms`

### Reference Implementations

#### ConfigManagement Pattern
- **Entity**: `src/Features/ConfigurationManagement/Domain/Entities/Config.php` - Doctrine ORM entity
- **Repository**: `src/Features/ConfigurationManagement/Repositories/ConfigRepository.php` - Extends `EntityRepository`
- **Migration**: `src/Infrastructure/Migrations/Doctrine/Version20251103000000.php`
- **Registration**: Entity path in `DoctrineFactory::createEntityManager()`
- **Initialization**: Repository created in `PluginBootstrap::initializeConfigSystem()`

#### ReviewManagement Pattern
- **Entity**: `src/Features/ReviewManagement/Domain/Entities/Review.php` - Doctrine ORM entity with `#[ORM\Entity]` attributes
- **Repository**: `src/Features/ReviewManagement/Repositories/ReviewRepository.php` - Extends `EntityRepository`, implements `ReviewRepositoryInterface`
- **Migration**: `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`
- **Registration**: Entity path in `DoctrineFactory::createEntityManager()` (line 59)
- **Initialization**: Repository created in `PluginBootstrap::initializeConfigSystem()` (lines 88-96)

## ⚠️ CRITICAL WARNING: location_point Handling

**DO NOT MODIFY location_point LOGIC** - See `docs/issues/location-point-lessons-learned.md`

The `location_point` column uses MySQL POINT geometry with a specific order: `POINT(longitude, latitude)`. There was a previous bug where longitude/latitude values kept swapping, taking several days to debug.

**Rules**:
- **Save**: Use `POINT(lng, lat)` - longitude FIRST, latitude SECOND
- **Load**: Use `ST_X()` for longitude, `ST_Y()` for latitude
- **DO NOT CHANGE** the current working implementation in `VersionRepository::save()` and `mapRow()`
- Copy the exact SQL pattern when converting to Doctrine

**Reference Document**: `docs/issues/location-point-lessons-learned.md`

## Migration Plan

### Phase 1: Entity Conversion

#### Step 1.1: Convert Version Entity to Doctrine ORM
**File**: `src/Domain/Entities/Version.php` → Move to `src/Features/VersionManagement/Domain/Entities/Version.php`

**Changes**:
1. Add Doctrine ORM attributes:
   - `#[ORM\Entity(repositoryClass: VersionRepository::class)]`
   - `#[ORM\Table(name: 'minisite_versions')]`
   - `#[ORM\Index]` for `idx_minisite_status`, `idx_minisite_created`
   - `#[ORM\UniqueConstraint]` for `uniq_minisite_version`
2. Convert all properties to public with `#[ORM\Column]` attributes
3. Handle special types:
   - ⚠️ **`location_point` (POINT)**: **DO NOT MODIFY** - Keep as-is, use raw SQL (see warning above)
   - `status` (ENUM) - Map to string
   - `site_json` (LONGTEXT) - Use `text` type
   - `created_at`, `published_at` - Use `datetime_immutable`
4. Keep existing methods: `isPublished()`, `isDraft()`, `isRollback()`

**Reference**: See `Review.php` for attribute patterns

### Phase 2: Repository Conversion

#### Step 2.1: Create New Doctrine-Based VersionRepository
**File**: `src/Features/VersionManagement/Repositories/VersionRepository.php`

**Changes**:
1. Extend `Doctrine\ORM\EntityRepository`
2. Implement `VersionRepositoryInterface` (keep existing interface)
3. Replace all `$wpdb` queries with Doctrine Query Builder
4. ⚠️ **CRITICAL: Handle POINT geometry for `location_point`**:
   - **DO NOT MODIFY** the current working pattern
   - **Copy EXACTLY** from `src/Infrastructure/Persistence/Repositories/VersionRepository.php` lines 83-131 (save) and 302-328 (load)
   - On save: Use raw SQL `POINT(lng, lat)` - **longitude FIRST, latitude SECOND**
   - On load: Use `ST_X()` for longitude, `ST_Y()` for latitude
   - **Reference**: `docs/issues/location-point-lessons-learned.md`
5. Methods to convert:
   - `save()` - Use `persist()` and `flush()`
   - `findById()` - Use `find()`
   - `findByMinisiteId()` - Query Builder with `where()`, `orderBy()`, `setMaxResults()`
   - `findLatestVersion()` - Query Builder
   - `findLatestDraft()` - Query Builder with status filter
   - `findPublishedVersion()` - Query Builder with status filter
   - `getNextVersionNumber()` - Query Builder with `MAX()`
   - `delete()` - Use `remove()` and `flush()`
   - `createDraftFromVersion()` - Create new entity instance
   - `getLatestDraftForEditing()` - Query Builder logic

**Reference**: See `ReviewRepository.php` for implementation patterns

#### Step 2.2: Update VersionRepositoryInterface
**File**: `src/Infrastructure/Persistence/Repositories/VersionRepositoryInterface.php`

**Changes**:
- Keep interface as-is (it's already compatible)
- May need to add `getLatestDraftForEditing()` if not already present

### Phase 3: Doctrine Migration

#### Step 3.1: Create Doctrine Migration
**File**: `src/Infrastructure/Migrations/Doctrine/Version20251105000000.php`

**Note**: Using November 5, 2025 00:00:00 timestamp (yesterday's beginning date time as requested)

**Pattern** (following `Version20251104000000.php`):
1. Check if table exists using `information_schema`
2. If exists, skip (idempotent)
3. Create table using Schema API with all 27 columns
4. Handle special types:
   - ⚠️ **`location_point`**: Use `point` type - **DO NOT MODIFY** the working pattern (see warning above)
   - `status` ENUM - Use `string` type with comment
   - `site_json` LONGTEXT - Use `text` type
5. Add indexes:
   - Primary key: `id`
   - Unique: `uniq_minisite_version` on `(minisite_id, version_number)`
   - Index: `idx_minisite_status` on `(minisite_id, status)`
   - Index: `idx_minisite_created` on `(minisite_id, created_at)`

**Reference**: See `Version20251104000000.php` for migration structure

### Phase 4: Integration Updates

#### Step 4.1: Update DoctrineFactory
**File**: `src/Infrastructure/Persistence/Doctrine/DoctrineFactory.php`

**Changes**:
- Add Version entity path to `ORMSetup::createAttributeMetadataConfiguration()` paths array:
  ```php
  paths: array(
      __DIR__ . '/../../../Domain/Entities',
      __DIR__ . '/../../../Features/ReviewManagement/Domain/Entities',
      __DIR__ . '/../../../Features/VersionManagement/Domain/Entities', // NEW
  ),
  ```

#### Step 4.2: Update PluginBootstrap
**File**: `src/Core/PluginBootstrap.php`

**Changes**:
- In `initializeConfigSystem()`, add VersionRepository initialization:
  ```php
  // Initialize VersionRepository
  $versionRepository = new \Minisite\Features\VersionManagement\Repositories\VersionRepository(
      $em,
      $em->getClassMetadata(\Minisite\Features\VersionManagement\Domain\Entities\Version::class)
  );

  // Store in global for backward compatibility
  $GLOBALS['minisite_version_repository'] = $versionRepository;
  ```

#### Step 4.3: Update All VersionRepository Usages
**Files to Update**:
- `src/Features/VersionManagement/WordPress/WordPressVersionManager.php`
- `src/Features/VersionManagement/Services/VersionService.php`
- `src/Features/VersionManagement/Hooks/VersionHooksFactory.php`
- `src/Features/NewMinisite/WordPress/WordPressNewMinisiteManager.php`
- `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php`
- `src/Features/MinisiteListing/WordPress/WordPressListingManager.php`
- `src/Features/MinisiteEdit/WordPress/WordPressEditManager.php`
- `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php`

**Changes**:
- Replace `new VersionRepository($wpdb)` with:
  ```php
  $GLOBALS['minisite_version_repository'] ?? new VersionRepository($em, $em->getClassMetadata(Version::class))
  ```

### Phase 5: Legacy Code Cleanup

#### Step 5.1: Comment Out Old SQL-Based Table Creation
**File**: `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php`

**Changes**:
- Comment out `minisite_versions` table creation (lines 46-49)
- Add note: "Version table creation moved to Doctrine migrations"
- Comment out foreign key constraint for versions table (lines 93-99)
- Keep old code commented for reference (like ReviewManagement did)

#### Step 5.2: Update VersioningController
**File**: `src/Infrastructure/Versioning/VersioningController.php`

**Changes**:
- Update `tablesMissing()` to exclude `minisite_versions` from check (or keep for now)
- Add comment noting that versions table is now managed by Doctrine

### Phase 6: Testing

#### Step 6.1: Create Integration Tests
**File**: `tests/Integration/Features/VersionManagement/Repositories/VersionRepositoryIntegrationTest.php`

**Pattern** (following `ReviewRepositoryIntegrationTest.php`):
1. Extend `AbstractDoctrineMigrationTest`
2. Test all repository methods:
   - `save()` - Insert and update
   - `findById()` - Find existing and non-existing
   - `findByMinisiteId()` - With pagination
   - `findLatestVersion()` - Latest version retrieval
   - `findLatestDraft()` - Draft filtering
   - `findPublishedVersion()` - Published filtering
   - `getNextVersionNumber()` - Version number generation
   - `delete()` - Deletion
   - `createDraftFromVersion()` - Draft creation
   - `getLatestDraftForEditing()` - Draft retrieval logic
3. Test POINT geometry handling
4. Test ENUM status values

#### Step 6.2: Update Unit Tests
**Files**: All existing unit tests in `tests/Unit/Features/VersionManagement/`

**Changes**:
- Update mocks to use Doctrine EntityManager instead of wpdb
- Follow pattern from `ReviewRepositoryTest.php`

#### Step 6.3: Create Feature Integration Tests
**File**: `tests/Integration/Features/VersionManagement/VersionManagementWorkflowIntegrationTest.php`

**Pattern** (following `ReviewWorkflowIntegrationTest.php`):
- Test complete workflows:
  - Create draft version
  - Publish version
  - Rollback to previous version
  - List versions
  - Version history

## Implementation Checklist

### Phase 1: Entity Conversion
- [ ] Move `Version.php` to `src/Features/VersionManagement/Domain/Entities/`
- [ ] Add Doctrine ORM attributes to all properties
- [ ] Handle POINT geometry type
- [ ] Handle ENUM status type
- [ ] Add table indexes and unique constraints
- [ ] Test entity creation and property access

### Phase 2: Repository Conversion
- [ ] Create new `VersionRepository` extending `EntityRepository`
- [ ] Implement all interface methods using Doctrine Query Builder
- [ ] Handle POINT geometry in save/load operations
- [ ] Add logging (following ReviewRepository pattern)
- [ ] Update constructor to accept EntityManager and ClassMetadata

### Phase 3: Doctrine Migration
- [ ] Create `Version20251105000000.php` migration
- [ ] Implement table existence check
- [ ] Create all 27 columns with correct types
- [ ] Add indexes and unique constraints
- [ ] Test migration on fresh database
- [ ] Test migration on existing database (idempotent)

### Phase 4: Integration Updates
- [ ] Update `DoctrineFactory` to register Version entity path
- [ ] Update `PluginBootstrap` to initialize VersionRepository
- [ ] Update all VersionRepository usages across codebase
- [ ] Test backward compatibility with global variable

### Phase 5: Legacy Cleanup
- [ ] Comment out old SQL-based table creation
- [ ] Comment out foreign key constraint creation
- [ ] Add migration notes in comments
- [ ] Update VersioningController comments

### Phase 6: Testing
- [ ] Create `VersionRepositoryIntegrationTest.php`
- [ ] Update all unit tests to use Doctrine mocks
- [ ] Create `VersionManagementWorkflowIntegrationTest.php`
- [ ] Run full test suite
- [ ] Verify test coverage increases

## Special Considerations

### POINT Geometry Handling
⚠️ **CRITICAL**: The `location_point` column uses MySQL POINT type with a specific order that caused bugs in the past.

**Decision**: **KEEP POINT TYPE** and **DO NOT MODIFY** the current working implementation.

**Current Working Pattern** (from `VersionRepository.php`):
- Save: `POINT(lng, lat)` - longitude FIRST, latitude SECOND
- Load: `ST_X()` returns longitude, `ST_Y()` returns latitude

**Reference**: `docs/issues/location-point-lessons-learned.md` - Contains full details of the previous bug and correct implementation pattern.

**Action**: Copy the exact SQL pattern from current `VersionRepository::save()` and `mapRow()` methods. Do not attempt to "improve" or "simplify" this code.

### Backward Compatibility
- Keep `VersionRepositoryInterface` unchanged
- Use global variable `$GLOBALS['minisite_version_repository']` for backward compatibility
- Gradually migrate all usages to use global or dependency injection

### Migration Strategy
- Doctrine migration is idempotent (checks if table exists)
- Old custom migration will be skipped if table already exists
- No data migration needed (table structure remains the same)

## Test Coverage Goals

### Current Coverage
- Review existing test files in `tests/Unit/Features/VersionManagement/`
- Review existing test files in `tests/Integration/Features/VersionManagement/` (if any)

### Target Coverage
Following ReviewManagement pattern:
- **Unit Tests**: All repository methods, service methods, handlers, controllers
- **Integration Tests**: Repository operations, complete workflows, database operations
- **Coverage Target**: 80%+ for VersionManagement feature

## Seeder Code Pattern

### Current State
- **ReviewManagement**: Has `ReviewSeederService` that loads from JSON files in `data/json/reviews/`
- **VersionManagement**: Currently seeds versions in `_1_0_0_CreateBase.php` with hardcoded data
- **Minisites**: Load from JSON files in `data/json/minisites/` via `loadMinisiteFromJson()`

### Future Consideration
After Doctrine migration, consider creating `VersionSeederService` following the `ReviewSeederService` pattern:
- Load version data from JSON files in `data/json/versions/` (if needed)
- Use Doctrine repository for all save operations
- Follow the same pattern as `ReviewSeederService::loadReviewsFromJson()` and `seedReviewsForMinisite()`

**Note**: This is not required for the initial migration, but should be considered for consistency.

## References

### Key Files to Reference
1. **ReviewManagement** (Primary Reference):
   - `src/Features/ReviewManagement/Domain/Entities/Review.php`
   - `src/Features/ReviewManagement/Repositories/ReviewRepository.php`
   - `src/Features/ReviewManagement/Services/ReviewSeederService.php` (JSON loading pattern)
   - `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`
   - `tests/Integration/Features/ReviewManagement/Repositories/ReviewRepositoryIntegrationTest.php`

2. **ConfigManagement** (Secondary Reference):
   - `src/Features/ConfigurationManagement/Domain/Entities/Config.php`
   - `src/Infrastructure/Migrations/Doctrine/Version20251103000000.php`

3. **Current VersionManagement**:
   - `src/Domain/Entities/Version.php`
   - `src/Infrastructure/Persistence/Repositories/VersionRepository.php` ⚠️ **Reference for location_point pattern**
   - `data/db/tables/minisite_versions.sql`

4. **Critical Documentation**:
   - `docs/issues/location-point-lessons-learned.md` ⚠️ **MUST READ before modifying location_point code**

## Timeline Estimate

- **Phase 1** (Entity Conversion): 2-3 hours
- **Phase 2** (Repository Conversion): 4-5 hours
- **Phase 3** (Doctrine Migration): 1-2 hours
- **Phase 4** (Integration Updates): 2-3 hours
- **Phase 5** (Legacy Cleanup): 1 hour
- **Phase 6** (Testing): 4-6 hours

**Total**: 14-20 hours

## Next Steps

1. Review and approve this plan
2. Start with Phase 1 (Entity Conversion)
3. Test incrementally after each phase
4. Complete Phase 6 (Testing) to increase coverage
5. Document any deviations or learnings

