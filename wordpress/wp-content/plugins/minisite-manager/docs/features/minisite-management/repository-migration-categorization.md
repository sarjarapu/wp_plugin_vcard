# MinisiteRepository Migration Categorization

## Overview
Categorizing all MinisiteRepository usages into Simple, Easy, and Complex replacements for systematic migration.

## Categories

### SIMPLE (Direct replacement: `new MinisiteRepository($wpdb)` → `$GLOBALS['minisite_repository']`)

**Pattern**: Factory classes that instantiate repository for dependency injection
**Effort**: ~5 minutes each
**Risk**: Low

1. ✅ **COMPLETED** `src/Features/VersionManagement/Hooks/VersionHooksFactory.php` (line 31)
2. ✅ **COMPLETED** `src/Features/PublishMinisite/Hooks/PublishHooksFactory.php` (line 39)
3. ✅ **COMPLETED** `src/Features/NewMinisite/Hooks/NewMinisiteHooksFactory.php` (line 35)
4. ✅ **COMPLETED** `src/Features/MinisiteViewer/Hooks/ViewHooksFactory.php` (line 33)
5. ✅ **COMPLETED** `src/Features/MinisiteListing/Hooks/ListingHooksFactory.php` (line 36)
6. ✅ **COMPLETED** `src/Features/MinisiteEdit/Hooks/EditHooksFactory.php` (line 36)

**Total**: 6 files - **ALL COMPLETED**

---

### EASY (Replace repository + update entity namespace references)

**Pattern**: Services/coordinators that use repository and may reference old entity namespace
**Effort**: ~15-30 minutes each
**Risk**: Medium (need to verify entity property access)

1. ✅ **COMPLETED** `src/Domain/Services/MinisiteDatabaseCoordinator.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`
   - Updated: Entity namespace `\Minisite\Domain\Entities\Minisite` → `\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite`

2. ✅ **COMPLETED** `src/Features/VersionManagement/Services/VersionService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

3. ✅ **COMPLETED** `src/Domain/Services/MinisiteFormProcessor.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

4. ✅ **COMPLETED** `src/Features/NewMinisite/Services/NewMinisiteService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

5. ✅ **COMPLETED** `src/Features/MinisiteEdit/Services/EditService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`
   - Fixed: Version entity namespace

6. ✅ **COMPLETED** `src/Features/PublishMinisite/Services/PublishService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

7. ✅ **COMPLETED** `src/Features/PublishMinisite/Services/SubscriptionActivationService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

8. ✅ **COMPLETED** `src/Features/PublishMinisite/Services/SlugAvailabilityService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

9. ✅ **COMPLETED** `src/Features/PublishMinisite/Services/ReservationService.php`
   - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

10. ✅ **COMPLETED** `src/Features/MinisiteViewer/Services/MinisiteViewService.php`
    - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

11. ✅ **COMPLETED** `src/Features/MinisiteListing/Services/MinisiteListingService.php`
    - Updated: `MinisiteRepository` → `MinisiteRepositoryInterface`

**Total**: 11 files - **ALL COMPLETED**

---

### COMPLEX (Tests, integration tests, and special cases)

**Pattern**: Tests that mock or create repository instances, integration tests
**Effort**: ~30-60 minutes each
**Risk**: High (test infrastructure changes)

1. ⏳ `tests/Unit/Infrastructure/Persistence/Repositories/MinisiteRepositoryTest.php`
   - Tests old `$wpdb`-based repository
   - Needs: Either update to test new Doctrine repository OR mark as deprecated

2. ⏳ `tests/Integration/Features/VersionManagement/VersionManagementWorkflowIntegrationTest.php` (line 132)
   - Uses: `new MinisiteRepository($GLOBALS['wpdb'])`
   - Needs: Replace with `$GLOBALS['minisite_repository']`

3. ⏳ `tests/Unit/Features/MinisiteViewer/Services/MinisiteViewServiceTest.php`
   - Likely mocks repository
   - Needs: Update mocks to use new repository

4. ⏳ `tests/Unit/Features/MinisiteListing/Services/MinisiteListingServiceTest.php`
   - Likely mocks repository
   - Needs: Update mocks to use new repository

5. ⏳ `tests/Unit/Features/MinisiteEdit/Services/EditServiceTest.php`
   - Likely mocks repository
   - Needs: Update mocks to use new repository

6. ⏳ `tests/Unit/Features/VersionManagement/Services/VersionServiceTest.php`
   - Likely mocks repository
   - Needs: Update mocks to use new repository

**Total**: 6 files

---

## Migration Strategy

### Phase 1: Simple Replacements ✅ COMPLETED
1. ✅ Updated all 6 Factory classes
2. ⏳ Test each feature manually (pending)
3. ⏳ Run unit tests for affected features (pending)

### Phase 2: Easy Replacements ✅ COMPLETED
1. ✅ Updated MinisiteDatabaseCoordinator
2. ✅ Updated VersionService
3. ✅ Updated all other services (11 total)
4. ✅ Updated entity namespace references
5. ⏳ Verify entity property access works (pending - needs testing)
6. ⏳ Run integration tests (pending)

### Phase 3: Complex Replacements ⏳ PENDING
1. ⏳ Update integration tests
2. ⏳ Update unit test mocks
3. ⏳ Decide on old repository test file (deprecate or update)
4. ⏳ Run full test suite

---

## Notes

- **Old repository class**: `src/Infrastructure/Persistence/Repositories/MinisiteRepository.php` - DO NOT DELETE
- **New repository class**: `src/Features/MinisiteManagement/Repositories/MinisiteRepository.php`
- **Global variable**: `$GLOBALS['minisite_repository']` (initialized in PluginBootstrap)
- **Entity namespace change**: `Minisite\Domain\Entities\Minisite` → `Minisite\Features\MinisiteManagement\Domain\Entities\Minisite`

