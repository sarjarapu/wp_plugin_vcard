# Minisite Doctrine Migration Plan

## Overview
Migrate Minisite entity and repository from custom `$wpdb`-based implementation to Doctrine ORM, following the same pattern as Reviews and Configuration features.

## Current State

### Entity
- **Location**: `src/Domain/Entities/Minisite.php`
- **Type**: Plain PHP class (constructor-based)
- **Fields**: 38 properties including `id`, `slug`, `slugs` (SlugPair), `title`, `name`, location fields, `siteJson` (array), `geo` (GeoPoint), timestamps, etc.

### Repository
- **Location**: `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php`
- **Type**: Uses `$wpdb` directly
- **Methods**:
  - `findBySlugs()`, `findById()`, `findBySlugParams()`
  - `insert()`, `save()` (with optimistic locking)
  - `updateSlug()`, `updateSlugs()`, `updatePublishStatus()`
  - `updateCurrentVersionId()`, `updateCoordinates()`, `updateMinisiteFields()`
  - `updateTitle()`, `updateStatus()`, `updateBusinessInfo()`
  - `listByOwner()`, `countByOwner()`
  - `publishMinisite()` (complex transaction logic)
  - `mapRow()` (private - maps DB row to entity)

### Database Schema
- **Table**: `wp_minisites`
- **SQL File**: `data/db/tables/minisites.sql`
- **Special Types**:
  - `location_point` (POINT) - spatial data type
  - `site_json` (LONGTEXT) - JSON string
  - `status` (ENUM) - 'draft'|'published'|'archived'
  - `publish_status` (ENUM) - 'draft'|'reserved'|'published'

## Target State (Following Reviews/Config Pattern)

### Entity
- **Location**: `src/Features/MinisiteManagement/Domain/Entities/Minisite.php`
- **Type**: Doctrine ORM entity with attributes
- **Pattern**: Similar to `Version` entity (handles POINT via raw SQL)

### Repository
- **Location**: `src/Features/MinisiteManagement/Repositories/MinisiteRepository.php`
- **Type**: Extends `EntityRepository`, implements `MinisiteRepositoryInterface`
- **Pattern**: Similar to `VersionRepository` (handles POINT via raw SQL after flush)

### Migration
- **Location**: `src/Infrastructure/Migrations/Doctrine/Version20251106000000.php`
- **Pattern**: Similar to `Version20251103000000.php` (Config) and `Version20251104000000.php` (Reviews)
- **Approach**: Raw SQL for `CREATE TABLE` (readability, easier indexing)

## Migration Steps

### Phase 1: Entity Conversion
1. Create `src/Features/MinisiteManagement/Domain/Entities/Minisite.php`
2. Add Doctrine ORM attributes:
   - `#[ORM\Entity]` with repository class
   - `#[ORM\Table]` with name 'minisites'
   - `#[ORM\Column]` for each field
   - Indexes: `uniq_slug`, `uniq_business_location`
3. Handle special fields:
   - `location_point`: NOT mapped (handled via raw SQL like Version)
   - `siteJson`: Map as `text` type, handle JSON encode/decode
   - `slugs`: Map to `business_slug` and `location_slug` columns
   - `geo`: Property only (not mapped), populated from `location_point` via raw SQL
4. Keep constructor-based initialization or convert to setters?

### Phase 2: Migration File
1. Create `Version20251106000000.php`
2. Use raw SQL for `CREATE TABLE` (following Config/Reviews pattern)
3. Include all columns from `data/db/tables/minisites.sql`
4. Handle `location_point` as `POINT NULL`
5. Set `isTransactional()` to `false` (MySQL DDL implicit commits)

### Phase 3: Repository Conversion
1. Move to `src/Features/MinisiteManagement/Repositories/MinisiteRepository.php`
2. Extend `EntityRepository`, implement `MinisiteRepositoryInterface`
3. Convert methods:
   - `findBySlugs()` → QueryBuilder with `business_slug` and `location_slug`
   - `findById()` → Use parent `find()` method
   - `insert()` → `persist()` + `flush()`
   - `save()` → Handle optimistic locking with `site_version`
   - `update*()` methods → Use QueryBuilder or raw SQL for POINT
4. Handle `location_point`:
   - After `flush()`, use raw SQL to update POINT (like VersionRepository)
   - Load POINT via raw SQL in `find()` methods (like VersionRepository)
5. Keep `mapRow()` logic but convert to entity hydration

### Phase 4: Registration
1. Register entity in `DoctrineFactory::registerEntities()`
2. Register repository in `PluginBootstrap` (similar to Version/Config/Review)
3. Update dependency injection

### Phase 5: Update Usages
1. Find all `MinisiteRepository` usages
2. Update imports to new namespace
3. Test all integration points

### Phase 6: Tests
1. Create integration tests (similar to ReviewRepositoryIntegrationTest)
2. Update unit tests
3. Test POINT handling
4. Test optimistic locking

## Key Considerations

### location_point Handling
- **CRITICAL**: Follow exact pattern from `VersionRepository`
- Use raw SQL after `flush()` to update POINT
- Use raw SQL to load POINT in `find()` methods
- DO NOT try to map POINT as Doctrine column type
- See: `docs/issues/location-point-lessons-learned.md`

### siteJson Handling
- Store as `text` type in database (LONGTEXT)
- Entity property: `string` (JSON string)
- Helper methods: `getSiteJsonAsArray()`, `setSiteJsonFromArray()` (like Version)

### SlugPair Value Object
- Map to two columns: `business_slug` and `location_slug`
- Entity has both: `?string $businessSlug` and `?string $locationSlug` (for Doctrine)
- Also keep `SlugPair $slugs` property (computed from columns)
- Sync in `save()` method (like Version does)

### Optimistic Locking
- `site_version` column used for optimistic locking
- In `save()`, check `site_version` matches expected value
- Increment `site_version` on update
- Throw exception if version mismatch

### Complex Methods
- `publishMinisite()`: Complex transaction logic, uses VersionRepository
- Keep transaction management but use Doctrine EntityManager
- May need to coordinate with VersionRepository (both Doctrine now)

## Reference Implementations

### Version Entity
- `src/Features/VersionManagement/Domain/Entities/Version.php`
- Handles POINT via raw SQL
- Handles siteJson as string with array helpers

### VersionRepository
- `src/Features/VersionManagement/Repositories/VersionRepository.php`
- POINT handling in `save()` and `loadLocationPoint()`
- siteJson handling

### ReviewRepository
- `src/Features/ReviewManagement/Repositories/ReviewRepository.php`
- Simple Doctrine repository pattern

### ConfigRepository
- `src/Features/ConfigurationManagement/Repositories/ConfigRepository.php`
- Simple Doctrine repository pattern

## Migration Checklist

- [ ] Create migration plan document (this file)
- [ ] Create Minisite entity with Doctrine attributes
- [ ] Create Doctrine migration file
- [ ] Convert MinisiteRepository to Doctrine
- [ ] Handle location_point (POINT) via raw SQL
- [ ] Handle siteJson (JSON string)
- [ ] Handle SlugPair (two columns)
- [ ] Register entity in DoctrineFactory
- [ ] Register repository in PluginBootstrap
- [ ] Update all MinisiteRepository usages
- [ ] Create integration tests
- [ ] Update unit tests
- [ ] Test POINT handling
- [ ] Test optimistic locking
- [ ] Test complex methods (publishMinisite)
- [ ] Remove old SQL file (or mark as deprecated)
- [ ] Update documentation

## Estimated Time
- Phase 1 (Entity): 2-3 hours
- Phase 2 (Migration): 1 hour
- Phase 3 (Repository): 4-5 hours
- Phase 4 (Registration): 1 hour
- Phase 5 (Usages): 2-3 hours
- Phase 6 (Tests): 3-4 hours
- **Total**: 13-17 hours

