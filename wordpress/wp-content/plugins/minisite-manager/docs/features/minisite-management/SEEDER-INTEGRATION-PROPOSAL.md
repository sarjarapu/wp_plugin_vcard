# Seeder Integration with Migrations - Proposal (Revised)

## Current State Analysis

### Seed Data Location ✅ (Keep As-Is)
- **Minisites**: `data/json/minisites/*.json` (4 files: acme-dental, lotus-textiles, green-bites, swift-transit)
- **Versions**: `data/json/versions/*.json` (4 files, one per minisite)
- **Reviews**: `data/json/reviews/*.json` (4 files, one per minisite)
- **Configs**: `data/json/config/default-config.json`

**Note**: Current location is perfect - no need to move files. Migrations will reference these existing JSON files.

### Current Seeding Flow
1. `ActivationHandler::runMigrations()` → Runs all pending migrations
2. `ActivationHandler::seedTestDataAfterMigrations()` → Seeds ALL sample data after ALL migrations complete
3. Uses `MinisiteSeederService`, `VersionSeederService`, `ReviewSeederService`
4. **Problem**: Seed data is disconnected from migrations - no version coherence

**Note**: This is **sample data** (not test data). The "Test" keyword should be reserved for testing phases only.

### Migration Tracking
- Doctrine tracks executed migrations in `wp_minisite_migrations` table
- Each migration has `version` (e.g., `20251106000000`)
- `up()` method creates tables
- `down()` method drops tables (automatically removes all seed data - no tracking needed!)
- **Missing**: Seed data is not tied to migrations

---

## Proposed Solution: Integrate Seed Data with Migrations

### Core Principle
**Each migration is responsible for its own seed data** - when a migration runs `up()`, it seeds its data. When it runs `down()`, the table is dropped, so seed data is automatically removed (no tracking needed!).

### Architecture

#### 1. Extend BaseDoctrineMigration with Seed Methods

```php
abstract class BaseDoctrineMigration extends AbstractMigration
{
    // ... existing code ...

    /**
     * Seed sample data for this migration
     *
     * Called automatically after up() completes successfully.
     * Override in subclasses to provide sample seed data.
     *
     * Note: This is sample data (not test data). The "Test" keyword is reserved for testing phases.
     *
     * @return void
     */
    protected function seedSampleData(): void
    {
        // Default: no seed data
        // Override in subclasses that need sample seed data
    }

    /**
     * Remove seed data for this migration
     *
     * Called automatically before down() executes.
     * Override in subclasses to remove seed data.
     *
     * @return void
     */
    protected function unseedData(): void
    {
        // Default: no unseed logic
        // Override in subclasses that need to remove seed data
    }

    /**
     * Check if sample seed data should be executed
     *
     * By default, sample data seeds in all environments (this is live sample data).
     * Can be overridden to skip in specific environments if needed.
     *
     * Note: This is sample data, not test data. The "Test" keyword is reserved for testing phases.
     *
     * @return bool
     */
    protected function shouldSeedSampleData(): bool
    {
        // Sample data can run in all environments by default
        // Override if you need environment-specific logic
        return true;
    }
}
```

#### 2. Modify DoctrineMigrationRunner to Call Seed Methods

```php
// In DoctrineMigrationRunner::runMigrations()
// After migration executes successfully:
foreach ($executedMigrations as $migration) {
    if (method_exists($migration, 'seedSampleData')) {
        try {
            if ($migration->shouldSeedSampleData()) {
                $migration->seedSampleData();
            }
        } catch (\Exception $e) {
            // Log but don't fail migration
            $this->logger->warning('Sample seed data failed', ['error' => $e->getMessage()]);
        }
    }
}

// Note: No unseed logic needed - table drop in down() removes all data automatically
```

#### 3. Migration-Specific Seed Data Implementation

#### Seed Data Implementation (Using Existing JSON Files)

**Approach**: Keep JSON files in `data/json/` - migrations reference them directly.

```php
final class Version20251106000000 extends BaseDoctrineMigration
{
    public function up(Schema $schema): void
    {
        // ... create table ...
    }

    protected function seedSampleData(): void
    {
        if (!$this->shouldSeedSampleData()) {
            return;
        }

        // Use existing seeder service with existing JSON files
        $this->ensureRepositoriesInitialized();
        $seeder = new MinisiteSeederService($GLOBALS['minisite_repository']);
        $seeder->seedAllSampleMinisites(); // Loads from data/json/minisites/
    }

    // No unseedData() needed - table drop removes all data automatically!
}
```

**Why This Works**:
- JSON files stay in `data/json/` (current location is perfect)
- Migrations reference existing JSON files via seeder services
- No file moving needed
- No tracking table needed (table drop handles cleanup)
- Simple and clean!
- **Sample data** (not test data) - can run in all environments

#### 4. Seed Data Removal (Simplified!)

**Key Insight**: When `down()` drops a table, **all seed data is automatically removed**. No tracking needed!

**Solution**:
- `seedSampleData()` method loads JSON files from `data/json/` and seeds sample data
- **No `unseedData()` method needed** - table drop handles everything automatically

**Why This Works**:
- When migration runs `down()`, it calls `DROP TABLE`
- All data in the table (including sample seed data) is automatically removed
- No orphaned data possible
- Much simpler than tracking individual records
- **Sample data** (not test data) - runs in all environments by default

---

## Implementation Plan

### Phase 1: Extend BaseDoctrineMigration
1. Add `seedSampleData()`, `shouldSeedSampleData()` methods
2. Add helper method `ensureRepositoriesInitialized()` for accessing repositories
3. **No tracking table needed** - table drop handles cleanup automatically
4. **Note**: Use "Sample" terminology (not "Test") - sample data runs in all environments

### Phase 2: Modify DoctrineMigrationRunner
1. Hook into migration execution lifecycle
2. Call `seedSampleData()` after successful `up()` (if `shouldSeedSampleData()` returns true)
3. **No `unseedData()` call needed** - table drop in `down()` handles cleanup
4. Handle errors gracefully (log but don't fail migration)

### Phase 3: Update Existing Migrations
1. **Version20251106000000** (minisites table):
   - Add `seedSampleData()` to seed 4 sample minisites from `data/json/minisites/`
   - No `unseedData()` needed - table drop removes all data

2. **Version20251105000000** (versions table):
   - Add `seedSampleData()` to create initial versions from `data/json/versions/`
   - Depend on minisites being seeded first (migration order handles this)
   - No `unseedData()` needed - table drop removes all data

3. **Version20251104000000** (reviews table):
   - Add `seedSampleData()` to seed reviews from `data/json/reviews/`
   - Depend on minisites being seeded first
   - No `unseedData()` needed - table drop removes all data

4. **Version20251103000000** (config table):
   - Add `seedSampleData()` to seed default configs from `data/json/config/`
   - No `unseedData()` needed - table drop removes all data

### Phase 4: Remove Old Seeding Logic & Update Naming
1. Remove `ActivationHandler::seedTestDataAfterMigrations()` (rename to seedSampleData if keeping temporarily)
2. Remove `ActivationHandler::seedTestData()` (deprecated method)
3. Update seeder service method names: `seedAllTestMinisites()` → `seedAllSampleMinisites()`
4. Update documentation to use "Sample" terminology (not "Test")

### Phase 5: Testing
1. Test migration up() with sample seed data
2. Test migration down() (verify table drop removes sample seed data)
3. Test partial rollback scenarios
4. Test version coherence (rollback drops tables, removing sample seed data automatically)
5. Verify sample data runs in all environments (not just non-production)

---

## Benefits

### ✅ Version Coherence
- Seed data is tied to specific migration version
- Rollback automatically removes seed data
- No orphaned data after rollback

### ✅ Testable
- Each migration is self-contained
- Can test migration + seed data together
- Can test rollback + unseed together

### ✅ Manageable
- Seed data co-located with migration
- Clear which data belongs to which migration
- Easy to see what gets seeded/unseeded

### ✅ Clean
- No separate orchestrator needed
- No version tracking in separate system
- Doctrine migration system handles everything

---

## Considerations

### Seed Data Dependencies
**Problem**: Some seed data depends on other seed data (e.g., versions depend on minisites).

**Solution**:
- Migrations run in order (by version number)
- Seed data runs after `up()` completes
- Dependencies are implicit in migration order
- If dependency fails, seed data fails (logged, doesn't break migration)

### Production vs Development Sample Data
**Problem**: Sample data should run in all environments (it's live sample data, not test data).

**Solution**:
- `shouldSeedSampleData()` returns `true` by default (runs in all environments)
- Migrations can override if they need environment-specific logic
- Default behavior: seed sample data in all environments
- **Note**: "Test" keyword is reserved for testing phases only

### Large Seed Data Files
**Problem**: Large JSON files might be unwieldy in migration class.

**Solution**:
- Use Option B (co-located files) for large data
- Keep migration class clean
- Load JSON files in `seedData()` method

### Seed Data Performance
**Problem**: Seeding might be slow for large datasets.

**Solution**:
- Seed data runs after migration (not blocking)
- Can be async if needed (future enhancement)
- Log progress for large seed operations

---

## Migration Example (Complete - Simplified!)

```php
final class Version20251106000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisites table and seed sample data';
    }

    public function up(Schema $schema): void
    {
        // ... existing table creation code ...
    }

    public function down(Schema $schema): void
    {
        // ... existing table drop code ...
        // When table is dropped, all seed data is automatically removed!
    }

    protected function seedSampleData(): void
    {
        if (!$this->shouldSeedSampleData()) {
            $this->logger->info('Skipping sample seed data');
            return;
        }

        $this->logger->info('Starting sample seed data for minisites table');

        try {
            // Ensure repositories are initialized
            $this->ensureRepositoriesInitialized();

            // Seed sample minisites using existing JSON files in data/json/minisites/
            $seeder = new MinisiteSeederService($GLOBALS['minisite_repository']);
            $minisiteIds = $seeder->seedAllSampleMinisites();

            $this->logger->info('Sample seed data completed', [
                'minisites_seeded' => count($minisiteIds)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Sample seed data failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw - migration succeeded, sample seed data is optional
        }
    }

    // No unseedData() needed! Table drop in down() removes all data automatically.

    private function ensureRepositoriesInitialized(): void
    {
        if (!isset($GLOBALS['minisite_repository'])) {
            \Minisite\Core\PluginBootstrap::initializeConfigSystem();
        }
    }
}
```

**Key Points**:
- ✅ JSON files stay in `data/json/` (no moving needed)
- ✅ Uses existing seeder services (with updated naming: `seedAllSampleMinisites()`)
- ✅ No tracking table needed
- ✅ No `unseedData()` needed - table drop handles cleanup
- ✅ Simple and clean!
- ✅ **Sample data** (not test data) - runs in all environments by default
- ✅ "Test" keyword reserved for testing phases only

---

## Next Steps

1. ✅ **Proposal reviewed** - Simplified approach confirmed:
   - Keep JSON files in `data/json/` (no moving needed)
   - No tracking table needed (table drop handles cleanup)
   - No `unseedData()` needed (table drop removes all data)
   - Use "Sample" terminology (not "Test") - sample data runs in all environments
2. **Implement Phase 1** - Extend BaseDoctrineMigration with `seedSampleData()` and `shouldSeedSampleData()`
3. **Implement Phase 2** - Modify DoctrineMigrationRunner to call `seedSampleData()` after `up()`
4. **Update migrations** - Add `seedSampleData()` methods (reference existing JSON files)
5. **Update seeder services** - Rename methods: `seedAllTestMinisites()` → `seedAllSampleMinisites()`
6. **Test thoroughly** - Migration up/down with sample seed data
7. **Remove old code** - Clean up ActivationHandler seeding logic

---

**Last Updated**: After Phase 6 Completion
**Status**: Proposal - Ready for Review

