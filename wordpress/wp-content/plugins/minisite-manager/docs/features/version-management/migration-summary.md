# VersionManagement Doctrine Migration & Test Coverage - Summary

## Context

Working on **MIN-29** (Linear ticket) to improve VersionManagement feature through:
1. **Doctrine Migration**: Port from custom database versioning to Doctrine Migration model
2. **Test Coverage**: Increase test coverage following ConfigManagement/ReviewManagement patterns

## Current State Analysis

### VersionManagement Current Implementation
- ✅ **Entity**: `src/Domain/Entities/Version.php` - Plain PHP class (27 fields)
- ✅ **Repository**: `src/Infrastructure/Persistence/Repositories/VersionRepository.php` - Uses `$wpdb`
- ✅ **Table Creation**: SQL file-based (`data/db/tables/minisite_versions.sql`)
- ✅ **Migration**: Custom `VersioningController` with `_1_0_0_CreateBase.php`
- ✅ **Unit Tests**: 15 test files exist in `tests/Unit/Features/VersionManagement/`
- ❌ **Integration Tests**: None exist (directory doesn't exist)

### Reference Implementations

#### ReviewManagement (Primary Reference)
- ✅ Doctrine ORM Entity with attributes
- ✅ Repository extends `EntityRepository`
- ✅ Doctrine migration (`Version20251104000000.php`)
- ✅ Integration tests: `ReviewRepositoryIntegrationTest.php`, `ReviewWorkflowIntegrationTest.php`
- ✅ Unit tests with Doctrine mocks

#### ConfigManagement (Secondary Reference)
- ✅ Doctrine ORM Entity
- ✅ Repository extends `EntityRepository`
- ✅ Doctrine migration (`Version20251103000000.php`)
- ✅ Integration tests for repository and workflows

## Migration Plan Overview

### Phase 1: Entity Conversion (2-3 hours)
**Goal**: Convert Version entity to Doctrine ORM entity

**Tasks**:
1. Move `Version.php` from `src/Domain/Entities/` to `src/Features/VersionManagement/Domain/Entities/`
2. Add Doctrine ORM attributes (`#[ORM\Entity]`, `#[ORM\Table]`, `#[ORM\Column]`)
3. Handle special types:
   - `location_point` (POINT) - Keep as POINT, use raw SQL for operations
   - `status` (ENUM) - Map to string
   - `site_json` (LONGTEXT) - Use `text` type
4. Add indexes and unique constraints

**Reference**: `src/Features/ReviewManagement/Domain/Entities/Review.php`

### Phase 2: Repository Conversion (4-5 hours)
**Goal**: Convert VersionRepository to use Doctrine ORM

**Tasks**:
1. Create new `VersionRepository` in `src/Features/VersionManagement/Repositories/`
2. Extend `Doctrine\ORM\EntityRepository`
3. Implement `VersionRepositoryInterface`
4. ⚠️ **CRITICAL**: For `location_point` handling:
   - Copy EXACTLY from current `VersionRepository::save()` (lines 83-131)
   - Copy EXACTLY from current `VersionRepository::mapRow()` (lines 302-328)
   - Use raw SQL: `POINT(lng, lat)` - longitude FIRST, latitude SECOND
   - Reference: `docs/issues/location-point-lessons-learned.md`
5. Convert all other methods to use Doctrine Query Builder:
   - `save()` → `persist()` + `flush()`
   - `findById()` → `find()`
   - `findByMinisiteId()` → Query Builder
   - `findLatestVersion()` → Query Builder
   - `findLatestDraft()` → Query Builder
   - `findPublishedVersion()` → Query Builder
   - `getNextVersionNumber()` → Query Builder with `MAX()`
   - `delete()` → `remove()` + `flush()`
5. Handle POINT geometry with raw SQL (like current implementation)

**Reference**: `src/Features/ReviewManagement/Repositories/ReviewRepository.php`

### Phase 3: Doctrine Migration (1-2 hours)
**Goal**: Create Doctrine migration for `minisite_versions` table

**Tasks**:
1. Create `src/Infrastructure/Migrations/Doctrine/Version20251105000000.php` (using Nov 5, 2025 00:00:00 timestamp)
2. Implement idempotent table creation (check if exists, skip if present)
3. Create all 27 columns with correct types
4. Add indexes: Primary key, unique constraint, status index, created_at index

**Reference**: `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`

### Phase 4: Integration Updates (2-3 hours)
**Goal**: Wire up Doctrine entity and repository

**Tasks**:
1. Update `DoctrineFactory` to register Version entity path
2. Update `PluginBootstrap` to initialize VersionRepository
3. Update all VersionRepository usages (8 files):
   - `WordPressVersionManager.php`
   - `VersionService.php`
   - `VersionHooksFactory.php`
   - `WordPressNewMinisiteManager.php`
   - `WordPressMinisiteManager.php`
   - `WordPressListingManager.php`
   - `WordPressEditManager.php`
   - `MinisiteRepository.php`

**Reference**: `src/Core/PluginBootstrap.php` (lines 88-96 for ReviewRepository pattern)

### Phase 5: Legacy Cleanup (1 hour)
**Goal**: Comment out old SQL-based table creation

**Tasks**:
1. Comment out `minisite_versions` table creation in `_1_0_0_CreateBase.php`
2. Comment out foreign key constraint
3. Add migration notes

**Reference**: `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php` (lines 592-626 for Review pattern)

### Phase 6: Testing (4-6 hours)
**Goal**: Increase test coverage to match ReviewManagement

**Tasks**:
1. **Create Integration Tests**:
   - `tests/Integration/Features/VersionManagement/Repositories/VersionRepositoryIntegrationTest.php`
     - Test all repository methods
     - Test POINT geometry handling
     - Test ENUM status values
   - `tests/Integration/Features/VersionManagement/VersionManagementWorkflowIntegrationTest.php`
     - Test complete workflows (create draft, publish, rollback, list)

2. **Update Unit Tests**:
   - Update all 15 existing unit test files to use Doctrine mocks
   - Replace `$wpdb` mocks with `EntityManager` mocks

**Reference**:
- `tests/Integration/Features/ReviewManagement/Repositories/ReviewRepositoryIntegrationTest.php`
- `tests/Integration/Features/ReviewManagement/ReviewWorkflowIntegrationTest.php`

## Test Coverage Goals

### Current Coverage
- **Unit Tests**: 15 test files covering:
  - Commands (4 files)
  - Controllers (1 file)
  - Handlers (4 files)
  - Hooks (2 files)
  - Http (2 files)
  - Rendering (1 file)
  - Services (1 file)
  - WordPress (1 file)
- **Integration Tests**: 0 files

### Target Coverage (Following ReviewManagement Pattern)
- **Unit Tests**: All existing + updated for Doctrine
- **Integration Tests**:
  - Repository integration test (all methods)
  - Workflow integration test (end-to-end scenarios)
- **Coverage Target**: 80%+ for VersionManagement feature

## Key Files Reference

### To Create/Modify
1. `src/Features/VersionManagement/Domain/Entities/Version.php` (move and convert)
2. `src/Features/VersionManagement/Repositories/VersionRepository.php` (new)
3. `src/Infrastructure/Migrations/Doctrine/Version20251105000000.php` (new)
4. `tests/Integration/Features/VersionManagement/Repositories/VersionRepositoryIntegrationTest.php` (new)
5. `tests/Integration/Features/VersionManagement/VersionManagementWorkflowIntegrationTest.php` (new)

### To Update
1. `src/Infrastructure/Persistence/Doctrine/DoctrineFactory.php` (add entity path)
2. `src/Core/PluginBootstrap.php` (initialize repository)
3. `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php` (comment out old code)
4. All VersionRepository usages (8 files)

### Reference Files
1. `src/Features/ReviewManagement/Domain/Entities/Review.php`
2. `src/Features/ReviewManagement/Repositories/ReviewRepository.php`
3. `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`
4. `tests/Integration/Features/ReviewManagement/Repositories/ReviewRepositoryIntegrationTest.php`
5. `tests/Integration/Infrastructure/Migrations/Doctrine/AbstractDoctrineMigrationTest.php`

## Special Considerations

### ⚠️ CRITICAL: POINT Geometry (location_point)

**DO NOT MODIFY location_point LOGIC** - See `docs/issues/location-point-lessons-learned.md`

There was a previous critical bug where longitude/latitude values kept swapping, taking several days to debug. The current implementation is correct and working.

**Current Working Pattern**:
- **Save**: `POINT(lng, lat)` - **longitude FIRST, latitude SECOND**
- **Load**: `ST_X()` returns longitude, `ST_Y()` returns latitude

**Action Required**:
- Copy the exact SQL pattern from `VersionRepository::save()` (lines 87-90) and `mapRow()` (lines 311-321)
- Do not attempt to "improve" or change this code
- Reference `docs/issues/location-point-lessons-learned.md` for full details

**Decision**: Keep POINT type and use raw SQL for POINT operations (exactly as current implementation)

### Backward Compatibility
- Keep `VersionRepositoryInterface` unchanged
- Use `$GLOBALS['minisite_version_repository']` for backward compatibility
- Gradually migrate usages

### Migration Strategy
- Doctrine migration is idempotent (checks if table exists)
- Old custom migration will be skipped if table already exists
- No data migration needed (table structure remains the same)

## Timeline

- **Phase 1** (Entity): 2-3 hours
- **Phase 2** (Repository): 4-5 hours
- **Phase 3** (Migration): 1-2 hours
- **Phase 4** (Integration): 2-3 hours
- **Phase 5** (Cleanup): 1 hour
- **Phase 6** (Testing): 4-6 hours

**Total**: 14-20 hours

## Next Steps

1. ✅ Review and approve plan
2. Start with **Phase 1** (Entity Conversion)
3. Test incrementally after each phase
4. Complete **Phase 6** (Testing) to increase coverage
5. Document any deviations or learnings

## Success Criteria

- [ ] Version entity uses Doctrine ORM attributes
- [ ] VersionRepository extends EntityRepository
- [ ] Doctrine migration creates `minisite_versions` table
- [ ] All existing functionality works with Doctrine
- [ ] Integration tests exist and pass
- [ ] Unit tests updated for Doctrine
- [ ] Test coverage ≥ 80% for VersionManagement
- [ ] Old SQL-based code commented out (not deleted)

