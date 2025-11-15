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

## ✅ Completed Phases (Continued)

### Phase 5: Refactor TimberRenderer ✅
- Created `MinisiteViewModel` DTO
- Created `MinisiteViewDataService` for data preparation
- Updated `TimberRenderer` to accept `MinisiteViewModel`
- Removed data fetching methods from `TimberRenderer`
- Updated `ViewRenderer` to use `MinisiteViewDataService`
- All rendering now uses view model pattern

---

## ⏳ Pending Tasks

### Phase 6: Final Cleanup & Verification (Priority: HIGH) ✅
**Goal**: Ensure everything is clean and working

**Tasks**:
1. ✅ Run full test suite:
   - ✅ Unit tests: All passing (1,265 tests)
   - ✅ Integration tests: All passing
   - ✅ All tests verified

2. ✅ Run PR checks:
   - ✅ PHPStan: No errors
   - ✅ Code style: Passed
   - ✅ Security audit: 0 vulnerabilities
   - ✅ All PR checks passed

3. ✅ Verify no references to legacy code:
   - ✅ `Minisite\Domain\Entities\Minisite`: Only in `delete_me/` (verified)
   - ✅ `_1_0_0_CreateBase`: Only documentation comments (acceptable)
   - ✅ `VersioningController`: Only in `delete_me/` (verified)

4. ✅ Update documentation:
   - ✅ Phase 5 marked complete
   - ✅ Migration documentation updated (`_1_0_0_CreateBase-status.md`, `doctrine-migration-plan.md`, `legacy-cleanup-plan.md`)
   - ✅ Architecture docs updated

5. ⏳ Git cleanup:
   - ⏳ Commit all changes (user action required)
   - ⏳ Tag if appropriate (user action required)

**Estimated Time**: 1-2 hours
**Risk**: Low (verification only)
**Status**: Complete ✅ (Git cleanup pending user action)

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
3. **minisite-manager.php Refactoring** (2-3 weeks)
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

1. ✅ **Phase 6** (Final Cleanup) - Complete ✅
2. **Seeder Invocation Integration** - Improve cohesion (next)
3. ✅ **Phase 5** (TimberRenderer) - Complete ✅
4. **minisite-manager.php Refactoring** - Major architectural work
5. **WordPressManager Refactoring** - Code quality improvement

---

**Last Updated**: After Phase 6 Completion
**Status**: Phases 1-6 Complete ✅ | Future Tasks Pending ⏳

