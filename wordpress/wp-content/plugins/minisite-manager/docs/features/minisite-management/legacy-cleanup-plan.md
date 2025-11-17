# Legacy Code Cleanup Plan: Minisite Entity & Migration Files

## Overview
This plan addresses the cleanup of legacy code remnants from the Doctrine migration effort, specifically focusing on:
1. Legacy Minisite entity (`src/Domain/Entities/Minisite.php`)
2. Legacy migration file (`_1_0_0_CreateBase.php`)
3. Directory restructuring
4. Test file updates

## Current State Analysis

### ✅ Already Using Doctrine Entity
- `MinisiteDatabaseCoordinator.php` - Uses `Minisite\Features\MinisiteManagement\Domain\Entities\Minisite`
- `PluginBootstrap.php` - Uses Doctrine entity
- `MinisiteRepository` - Uses Doctrine entity

### ✅ All Using Doctrine Entity
- `TimberRenderer.php` - ✅ Now uses `MinisiteViewModel` (Phase 5 complete)
- All services and repositories use Doctrine entity

### ✅ Legacy Files Status (All Archived)
- ✅ `src/Domain/Entities/Minisite.php` - Moved to `delete_me/` (Phase 2 complete)
- ✅ `tests/Unit/Domain/Entities/MinisiteTest.php` - Moved to `delete_me/` (Phase 2 complete)
- ✅ `_1_0_0_CreateBase.php` - Moved to `delete_me/` (Phase 4 complete)
- ✅ All tables now created by Doctrine migrations (Phase 3 complete)
- ✅ All seeding now uses seeder services (Phase 4 complete)

## Execution Plan

### Phase 1: Update TimberRenderer (Priority: HIGH)
**Goal**: Switch to Doctrine entity and prepare for refactoring

**Tasks**:
1. Update import in `TimberRenderer.php`:
   - Change: `use Minisite\Domain\Entities\Minisite;`
   - To: `use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;`

2. Update `fetchMinisiteWithUserData()` method:
   - Current: Creates new legacy entity instance with all fields
   - New: Update Doctrine entity properties directly (set `isBookmarked` and `canEdit` properties)
   - Doctrine entity already has these properties as public fields

3. Handle `siteJson` difference:
   - Legacy entity: `array $siteJson`
   - Doctrine entity: `string $siteJson` (JSON string)
   - Use `getSiteJsonAsArray()` method from Doctrine entity

4. Test rendering still works correctly

**Estimated Time**: 1-2 hours
**Risk**: Low (straightforward import/type changes)

---

### Phase 2: Archive Legacy Minisite Entity (Priority: HIGH)
**Goal**: Move legacy entity to `delete_me/` folder

**Tasks**:
1. Move `src/Domain/Entities/Minisite.php` → `delete_me/src/Domain/Entities/Minisite.php`
2. Update namespace to `delete_me\Minisite\Domain\Entities\Minisite`
3. Add deprecation notice in file header
4. Move `tests/Unit/Domain/Entities/MinisiteTest.php` → `delete_me/tests/Unit/Domain/Entities/MinisiteTest.php`
5. Update test namespace if needed
6. Verify no remaining references in active `src/` code
7. Remove empty `src/Domain/Entities/` directory if empty

**Estimated Time**: 30 minutes
**Risk**: Low (just moving files)

---

### Phase 3: Create Doctrine Migrations for Remaining Tables (Priority: MEDIUM)
**Goal**: Migrate table creation from `_1_0_0_CreateBase.php` to Doctrine migrations

**Tables to Migrate**:
1. `minisite_bookmarks`
2. `minisite_payments`
3. `minisite_payment_history`
4. `minisite_reservations`

**Tasks**:
1. **Create Migration for Bookmarks**:
   - File: `src/Infrastructure/Migrations/Doctrine/Version20251107000000.php`
   - Create `minisite_bookmarks` table
   - Add foreign key to `minisites` table
   - Follow pattern from `Version20251104000000.php` (Reviews)

2. **Create Migration for Payments**:
   - File: `src/Infrastructure/Migrations/Doctrine/Version20251108000000.php`
   - Create `minisite_payments` table
   - Add foreign keys to `minisites` and `users` tables

3. **Create Migration for Payment History**:
   - File: `src/Infrastructure/Migrations/Doctrine/Version20251109000000.php`
   - Create `minisite_payment_history` table
   - Add foreign keys to `minisites`, `minisite_payments`, and `users` tables

4. **Create Migration for Reservations**:
   - File: `src/Infrastructure/Migrations/Doctrine/Version20251110000000.php`
   - Create `minisite_reservations` table
   - Add foreign keys to `minisites` and `users` tables
   - Create MySQL event `event_purge_reservations` (or move to separate service)

5. **Update `_1_0_0_CreateBase.php`**:
   - Remove SQL file loading for migrated tables
   - Remove foreign key creation for migrated tables
   - Keep only tables that haven't been migrated yet
   - Add comments indicating tables are now in Doctrine migrations

**Reference Files**:
- `data/db/tables/minisite_bookmarks.sql`
- `data/db/tables/minisite_payments.sql`
- `data/db/tables/minisite_payment_history.sql`
- `data/db/tables/minisite_reservations.sql`
- `data/db/events/event_purge_reservations.sql`

**Estimated Time**: 4-6 hours
**Risk**: Medium (database migrations require careful testing)

---

### Phase 4: Create MinisiteSeederService (Priority: MEDIUM)
**Goal**: Move test data seeding from `_1_0_0_CreateBase.php` to dedicated seeder service

**Tasks**:
1. Create `src/Features/MinisiteManagement/Services/MinisiteSeederService.php`:
   - Follow pattern from `ReviewSeederService`
   - Inject `MinisiteRepository` and `VersionRepository`
   - Move `seedTestData()` logic from `_1_0_0_CreateBase.php`
   - Handle minisite creation, version creation, and location_point updates

2. Update `_1_0_0_CreateBase.php`:
   - Remove `seedTestData()` method
   - Remove `loadMinisiteFromJson()` helper
   - Remove `convertLocationFormat()` helper
   - Remove `setComputedFields()` helper
   - Remove `insertMinisite()` helper
   - Call seeder service instead (if needed for activation)

3. Update activation/deactivation handlers:
   - Use seeder service for test data instead of migration
   - Ensure seeder only runs in dev/test environments

**Estimated Time**: 3-4 hours
**Risk**: Medium (data seeding logic is complex)

---

### Phase 5: Refactor TimberRenderer (Priority: LOW - Separate Concern)
**Goal**: Separate data fetching from rendering (as per existing TODO)

**Tasks**:
1. Create view model/DTO:
   - `src/Features/MinisiteViewer/ViewModels/MinisiteViewModel.php`
   - Contains: minisite data, reviews, user-specific flags (isBookmarked, canEdit)

2. Create data service:
   - `src/Features/MinisiteViewer/Services/MinisiteViewDataService.php`
   - Handles: fetching reviews, checking bookmarks, checking edit permissions
   - Returns: `MinisiteViewModel`

3. Update `TimberRenderer`:
   - Accept `MinisiteViewModel` instead of `Minisite` entity
   - Remove `fetchReviews()`, `fetchMinisiteWithUserData()`, `checkIfBookmarked()`, `checkIfCanEdit()`
   - Only handle rendering logic

4. Update callers of `TimberRenderer`:
   - Use `MinisiteViewDataService` to prepare data
   - Pass view model to renderer

**Estimated Time**: 3-4 hours
**Risk**: Low (refactoring, not critical path)

**Note**: This can be done separately from the cleanup work, but it's listed here for completeness.

---

### Phase 6: Final Cleanup & Verification (Priority: HIGH)
**Goal**: Ensure everything is clean and working

**Tasks**:
1. Run full test suite:
   - Unit tests
   - Integration tests
   - Verify all tests pass

2. Run PR checks:
   - PHPStan
   - Code style
   - Security audit

3. Verify no references to legacy entity:
   - Search codebase for `Minisite\Domain\Entities\Minisite`
   - Should only find references in `delete_me/` folder

4. Update documentation:
   - Update any docs referencing old entity location
   - Update migration documentation
   - Update architecture docs

5. Git cleanup:
   - Commit all changes
   - Tag if appropriate

**Estimated Time**: 1-2 hours
**Risk**: Low (verification only)

---

## Execution Order

### Recommended Sequence:
1. **Phase 1** (Update TimberRenderer) - Quick win, unblocks Phase 2
2. **Phase 2** (Archive Legacy Entity) - Clean up immediately after Phase 1
3. **Phase 6** (Verification) - Verify Phases 1-2 work correctly
4. **Phase 3** (Doctrine Migrations) - More complex, can be done independently
5. **Phase 4** (Seeder Service) - Depends on Phase 3 completion
6. **Phase 5** (TimberRenderer Refactor) - Separate concern, can be done later

### Critical Path:
- Phases 1 → 2 → 6 (Core cleanup)
- Phases 3 → 4 (Migration work)
- Phase 5 (Optional enhancement)

## Dependencies

### Blockers:
- None - All work can proceed independently

### Prerequisites:
- Doctrine migration system must be functional ✅
- Doctrine Minisite entity must exist ✅
- Doctrine MinisiteRepository must exist ✅

### Related Work:
- Linear ticket MIN-33 (this cleanup)
- `docs/features/minisite-management/doctrine-migration-plan.md` (entity migration - already done)
- `docs/development/minisite-manager-refactor-tracking.md` (main plugin refactor)

## Risk Assessment

### Low Risk:
- Phase 1: Simple import/type changes
- Phase 2: File moves only
- Phase 6: Verification only

### Medium Risk:
- Phase 3: Database migrations require careful testing
- Phase 4: Data seeding logic is complex

### Low Risk (Separate):
- Phase 5: Refactoring, not critical path

## Success Criteria

- [ ] No references to `Minisite\Domain\Entities\Minisite` in active `src/` code
- [ ] Legacy entity moved to `delete_me/` folder
- [ ] All table creation moved to Doctrine migrations
- [ ] Test data seeding uses seeder service
- [ ] All tests passing
- [ ] PR checks passing
- [ ] Documentation updated

## Estimated Total Effort

- Phase 1: 1-2 hours
- Phase 2: 30 minutes
- Phase 3: 4-6 hours
- Phase 4: 3-4 hours
- Phase 5: 3-4 hours (optional)
- Phase 6: 1-2 hours

**Total (Phases 1-4, 6)**: ~10-15 hours
**Total (All phases)**: ~13-19 hours

## Next Steps

1. Start with Phase 1 (Update TimberRenderer)
2. Immediately follow with Phase 2 (Archive Legacy Entity)
3. Verify with Phase 6 (Final Cleanup)
4. Then proceed with Phases 3-4 (Migration work)
5. Phase 5 can be done separately as enhancement

