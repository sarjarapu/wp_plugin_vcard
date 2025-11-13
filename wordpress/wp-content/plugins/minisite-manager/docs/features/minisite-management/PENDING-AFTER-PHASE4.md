# Pending Tasks After Phase 4 Completion

## ✅ Completed Phases

### Phase 1: Update TimberRenderer ✅
- Updated to use Doctrine `Minisite` entity
- Fixed entity property access

### Phase 2: Archive Legacy Entity ✅
- Moved legacy `Minisite.php` to `delete_me/`
- Moved legacy `MinisiteTest.php` to `delete_me/`
- Updated all references

### Phase 3: Create Doctrine Migrations ✅
- All tables migrated to Doctrine migrations:
  - `minisite_config` → `Version20251103000000.php`
  - `minisite_reviews` → `Version20251104000000.php`
  - `minisite_versions` → `Version20251105000000.php`
  - `minisites` → `Version20251106000000.php`
  - `minisite_bookmarks` → `Version20251107000000.php`
  - `minisite_payments` → `Version20251108000000.php`
  - `minisite_payment_history` → `Version20251109000000.php`
  - `minisite_reservations` → `Version20251110000000.php`
- All foreign keys migrated
- MySQL event migrated
- Created `BaseDoctrineMigration` to reduce duplication

### Phase 4: Create MinisiteSeederService ✅
- Created `MinisiteSeederService` with all helper methods
- Created `VersionSeederService::createInitialVersionFromMinisite()`
- Updated `ActivationHandler` to use seeder services
- Moved `_1_0_0_CreateBase.php` to `delete_me/`
- Moved legacy migration system to `delete_me/`
- All seeding now uses Doctrine-based services

---

## ⏳ Pending Tasks

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

3. Verify no references to legacy code:
   - Search codebase for `Minisite\Domain\Entities\Minisite` (should only find in `delete_me/`)
   - Search for `_1_0_0_CreateBase` (should only find in `delete_me/`)
   - Search for `VersioningController` (should only find in `delete_me/`)

4. Update documentation:
   - Update any docs referencing old entity location
   - Update migration documentation
   - Update architecture docs
   - Mark `_1_0_0_CreateBase-status.md` as complete

5. Git cleanup:
   - Commit all changes
   - Tag if appropriate

**Estimated Time**: 1-2 hours
**Risk**: Low (verification only)

---

## 🔄 Future Refactoring Tasks

### 1. Seeder Invocation Integration (Priority: MEDIUM)
**Status**: Tracked in TODO list
**Goal**: Integrate seeder invocation alongside version processing for better cohesion

**Current State**:
- Seeders are called via `init` hook in `ActivationHandler`
- Disconnected from version/migration processing flow

**Future Work**:
- Integrate seeder calls more closely with activation/version flow
- Consider calling seeders directly after migrations complete
- Better error handling and logging integration

**Estimated Time**: 2-3 hours
**Risk**: Low (refactoring only)

---

### 2. minisite-manager.php Refactoring (Priority: HIGH)
**Status**: Tracked in `docs/development/minisite-manager-refactor-tracking.md`

**Major Tasks**:
- [ ] Remove old controller dependencies
- [ ] Migrate remaining AJAX handlers to features
- [ ] Create missing features:
  - `SettingsFeature` for `/account/sites/{id}/settings`
  - `MinisiteEditFeature` for `/account/sites/{id}/edit`
  - `MinisitePreviewFeature` for `/account/sites/{id}/preview`
  - `MinisitePublishFeature` for `/account/sites/{id}/publish`
  - `MinisiteCommerceFeature` for WooCommerce integration
  - `MinisiteAdminFeature` for admin-specific functionality
- [ ] Remove commented code
- [ ] Simplify route handling

**Estimated Time**: 2-3 weeks
**Risk**: Medium (can be done incrementally)

---

### 3. WordPressManager Refactoring (Priority: MEDIUM)
**Status**: Tracked in `docs/development/minisite-manager-refactor-tracking.md`

**Tasks**:
- [ ] Create base WordPress manager class
- [ ] Split `WordPressManagerInterface` into smaller, focused interfaces
- [ ] Use composition instead of inheritance
- [ ] Reduce code duplication across managers

**Estimated Time**: 1-2 weeks
**Risk**: Medium (breaking changes)

---

### 4. Additional Cleanup Tasks

#### Code Organization
- [ ] Split large functions (e.g., `template_redirect` - 200+ lines)
- [ ] Extract AJAX handler registration to separate functions
- [ ] Extract WooCommerce integration to separate functions

#### Testing & Validation
- [ ] Additional unit tests for edge cases
- [ ] Integration tests for complete workflows
- [ ] Performance testing

#### Documentation
- [ ] Update README with current architecture
- [ ] Document Phase 2-4 refactoring completion
- [ ] Update API reference
- [ ] Consolidate duplicate documentation

---

## 📊 Summary

### Immediate Next Steps (High Priority)
1. **Phase 6: Final Cleanup & Verification** (1-2 hours)
   - Run full test suite
   - Verify no legacy code references
   - Update documentation

2. **Seeder Invocation Integration** (2-3 hours)
   - Better integration with activation flow
   - Improve error handling

### Medium Priority
3. **Phase 5: TimberRenderer Refactor** (3-4 hours)
   - Separate concerns
   - Improve maintainability

4. **minisite-manager.php Refactoring** (2-3 weeks)
   - Complete feature migration
   - Remove legacy controllers

### Lower Priority
5. **WordPressManager Refactoring** (1-2 weeks)
   - Reduce duplication
   - Improve interface design

6. **Additional Cleanup** (Ongoing)
   - Code organization
   - Documentation updates
   - Test coverage improvements

---

## ✅ Success Criteria (Phase 4 Complete)

- [x] `MinisiteSeederService` created and functional
- [x] All test data seeding uses seeder services
- [x] `_1_0_0_CreateBase.php` moved to `delete_me/`
- [x] Legacy migration system moved to `delete_me/`
- [x] `ActivationHandler` uses seeder services
- [x] All tables created by Doctrine migrations
- [x] No active code references legacy migration system

---

## 🎯 Recommended Order

1. **Phase 6** (Final Cleanup) - Quick verification, low risk
2. **Seeder Invocation Integration** - Improve cohesion
3. **Phase 5** (TimberRenderer) - Optional enhancement
4. **minisite-manager.php Refactoring** - Major architectural work
5. **WordPressManager Refactoring** - Code quality improvement

---

**Last Updated**: After Phase 4 Completion
**Status**: Phase 4 Complete ✅ | Phases 5-6 Pending ⏳

