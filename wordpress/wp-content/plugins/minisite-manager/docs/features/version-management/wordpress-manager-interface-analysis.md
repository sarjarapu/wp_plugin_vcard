# WordPressManagerInterface Analysis

## Problem Statement

The `WordPressManagerInterface` contains many methods that are **NOT WordPress-related**:
- `getCurrentUser` - ✅ WordPress-related (wraps `wp_get_current_user()`)
- `findMinisiteById` - ❌ Repository operation, NOT WordPress-related
- `getNextVersionNumber` - ❌ Repository operation, NOT WordPress-related
- `saveVersion` - ❌ Repository operation, NOT WordPress-related
- `hasBeenPublished` - ❌ Repository operation, NOT WordPress-related
- `updateBusinessInfo` - ❌ Repository operation, NOT WordPress-related
- `updateCoordinates` - ❌ Repository operation, NOT WordPress-related
- `updateTitle` - ❌ Repository operation, NOT WordPress-related
- `updateMinisiteFields` - ❌ Repository operation, NOT WordPress-related
- `startTransaction` - ❌ Database operation, NOT WordPress-related
- `commitTransaction` - ❌ Database operation, NOT WordPress-related
- `rollbackTransaction` - ❌ Database operation, NOT WordPress-related
- `getMinisiteRepository` - ❌ Repository access, NOT WordPress-related

## Classes Implementing WordPressManagerInterface

1. `WordPressEditManager` (MinisiteEdit)
2. `WordPressVersionManager` (VersionManagement)
3. `WordPressPublishManager` (PublishMinisite)
4. `WordPressNewMinisiteManager` (NewMinisite)
5. `WordPressUserManager` (Authentication)

---

## Method Usage Analysis

### 1. `getCurrentUser(): ?object`
**WordPress-related?** ✅ YES (wraps `wp_get_current_user()`)

**Active Usage:**
- ✅ `EditService` - Line 45, 135
- ✅ `PublishService` - Line 51
- ✅ `MinisiteViewService` - Line 88
- ✅ `NewMinisiteService` - Line 41, 51, 116, 174, 184
- ✅ `VersionService` - Line 271
- ✅ `VersionRequestHandler` - Line 34, 86, 126, 155
- ✅ `NewMinisiteController` - Line 53
- ✅ `ListingRequestHandler` - Line 31
- ✅ `ListingController` - Line 73
- ✅ `AuthService` - Line 159

**Implementation Status:**
- ✅ `WordPressEditManager` - Active implementation
- ✅ `WordPressVersionManager` - Active implementation
- ✅ `WordPressPublishManager` - Active implementation
- ✅ `WordPressNewMinisiteManager` - Active implementation
- ✅ `WordPressUserManager` - Active implementation

**Recommendation:** ✅ **KEEP** - This is a legitimate WordPress function wrapper.

---

### 2. `findMinisiteById(string $siteId): ?object`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ✅ `MinisiteFormProcessor` - Line 118

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (returns null, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (returns null, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (returns null, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (returns null, marked "Not used")
- ❌ `WordPressUserManager` - Stub (returns null, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in `MinisiteFormProcessor`. Should inject `MinisiteRepository` directly instead.

---

### 3. `getNextVersionNumber(string $minisiteId): int`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - `VersionRepository::createDraftFromVersion()` calls `$this->getNextVersionNumber()` which is the repository's own method, not the WordPress manager method.

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (returns 1, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (returns 1, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (returns 1, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (returns 1, marked "Not used")
- ❌ `WordPressUserManager` - Stub (returns 1, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere. The repository has its own `getNextVersionNumber()` method.

---

### 4. `saveVersion(object $version): object`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - No active usage

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (returns version as-is, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (returns version as-is, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (returns version as-is, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (returns version as-is, marked "Not used")
- ❌ `WordPressUserManager` - Stub (returns version as-is, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere.

---

### 5. `hasBeenPublished(string $siteId): bool`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - No active usage

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (returns false, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (returns false, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (returns false, marked "Not used")
- ✅ `WordPressNewMinisiteManager` - Active implementation (returns false - correct for new minisites)
- ❌ `WordPressUserManager` - Stub (returns false, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere. `WordPressNewMinisiteManager` has a legitimate implementation but it's not called.

---

### 6. `updateBusinessInfo(string $siteId, array $fields, int $userId): void`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - No active usage

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere.

---

### 7. `updateCoordinates(string $siteId, float $lat, float $lng, int $userId): void`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - No active usage

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere.

---

### 8. `updateTitle(string $siteId, string $title): void`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ❌ **NONE FOUND** - No active usage

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ✅ **REMOVE** - Dead code, not used anywhere.

---

### 9. `updateMinisiteFields(string $siteId, array $fields, int $userId): void`
**WordPress-related?** ❌ NO (Repository operation)

**Active Usage:**
- ✅ `MinisiteDatabaseCoordinator` - Line 694

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (no-op, marked "Not used")
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in `MinisiteDatabaseCoordinator`. Should inject `MinisiteRepository` directly instead.

---

### 10. `startTransaction(): void`
**WordPress-related?** ❌ NO (Database operation)

**Active Usage:**
- ✅ `MinisiteDatabaseCoordinator` - Line 149, 447

**Implementation Status:**
- ✅ `WordPressEditManager` - Active implementation
- ✅ `WordPressVersionManager` - Active implementation
- ✅ `WordPressPublishManager` - Active implementation
- ✅ `WordPressNewMinisiteManager` - Active implementation
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in `MinisiteDatabaseCoordinator`. Should use a dedicated transaction manager or inject database connection directly.

---

### 11. `commitTransaction(): void`
**WordPress-related?** ❌ NO (Database operation)

**Active Usage:**
- ✅ `MinisiteDatabaseCoordinator` - Line 373, 558

**Implementation Status:**
- ✅ `WordPressEditManager` - Active implementation
- ✅ `WordPressVersionManager` - Active implementation
- ✅ `WordPressPublishManager` - Active implementation
- ✅ `WordPressNewMinisiteManager` - Active implementation
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in `MinisiteDatabaseCoordinator`. Should use a dedicated transaction manager or inject database connection directly.

---

### 12. `rollbackTransaction(): void`
**WordPress-related?** ❌ NO (Database operation)

**Active Usage:**
- ✅ `MinisiteDatabaseCoordinator` - Line 418, 565

**Implementation Status:**
- ✅ `WordPressEditManager` - Active implementation
- ✅ `WordPressVersionManager` - Active implementation
- ✅ `WordPressPublishManager` - Active implementation
- ✅ `WordPressNewMinisiteManager` - Active implementation
- ❌ `WordPressUserManager` - Stub (no-op, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in `MinisiteDatabaseCoordinator`. Should use a dedicated transaction manager or inject database connection directly.

---

### 13. `getMinisiteRepository(): object`
**WordPress-related?** ❌ NO (Repository access)

**Active Usage:**
- ✅ `MinisiteDatabaseCoordinator` - Line 224, 366
- ✅ `SubscriptionActivationService` - Line 99, 213
- ✅ `SlugAvailabilityService` - Line 63
- ✅ `ReservationService` - Line 55

**Implementation Status:**
- ❌ `WordPressEditManager` - Stub (creates new instance, marked "Not used")
- ❌ `WordPressVersionManager` - Stub (creates new instance, marked "Not used")
- ❌ `WordPressPublishManager` - Stub (creates new instance, marked "Not used")
- ❌ `WordPressNewMinisiteManager` - Stub (creates new instance, marked "Not used")
- ❌ `WordPressUserManager` - Stub (returns stdClass, marked "not applicable")

**Recommendation:** ⚠️ **REFACTOR** - Still used in multiple services. Should inject `MinisiteRepository` directly instead.

---

## Summary

### Methods to REMOVE (Dead Code):
1. ✅ `saveVersion()` - Not used anywhere
2. ✅ `hasBeenPublished()` - Not used anywhere (except stub in WordPressNewMinisiteManager)
3. ✅ `getNextVersionNumber()` - Not used anywhere (repository has its own method)
4. ✅ `updateBusinessInfo()` - Not used anywhere
5. ✅ `updateCoordinates()` - Not used anywhere
6. ✅ `updateTitle()` - Not used anywhere

### Methods to REFACTOR (Still Used, But Shouldn't Be):
1. ⚠️ `findMinisiteById()` - Used in `MinisiteFormProcessor` → Should inject `MinisiteRepository`
2. ⚠️ `updateMinisiteFields()` - Used in `MinisiteDatabaseCoordinator` → Should inject `MinisiteRepository`
3. ⚠️ `startTransaction()` - Used in `MinisiteDatabaseCoordinator` → Should use transaction manager
4. ⚠️ `commitTransaction()` - Used in `MinisiteDatabaseCoordinator` → Should use transaction manager
5. ⚠️ `rollbackTransaction()` - Used in `MinisiteDatabaseCoordinator` → Should use transaction manager
6. ⚠️ `getMinisiteRepository()` - Used in multiple services → Should inject `MinisiteRepository`

### Methods to KEEP (WordPress-Related):
1. ✅ `getCurrentUser()` - Legitimate WordPress function wrapper

---

## Recommended Actions

### Phase 1: Remove Dead Code
1. Remove `saveVersion()` from interface and all implementations
2. Remove `hasBeenPublished()` from interface and all implementations
3. Remove `getNextVersionNumber()` from interface and all implementations
4. Remove `updateBusinessInfo()` from interface and all implementations
5. Remove `updateCoordinates()` from interface and all implementations
6. Remove `updateTitle()` from interface and all implementations

### Phase 2: Refactor Remaining Non-WordPress Methods
1. Update `MinisiteFormProcessor` to inject `MinisiteRepository` instead of using `findMinisiteById()`
2. Update `MinisiteDatabaseCoordinator` to inject `MinisiteRepository` instead of using `getMinisiteRepository()` and `updateMinisiteFields()`
3. Update `SubscriptionActivationService`, `SlugAvailabilityService`, `ReservationService` to inject `MinisiteRepository` instead of using `getMinisiteRepository()`
4. Create a `TransactionManager` interface and inject it into `MinisiteDatabaseCoordinator` instead of using transaction methods from WordPress manager

### Phase 3: Clean Interface
After Phase 2, `WordPressManagerInterface` should only contain:
- ✅ WordPress-specific function wrappers (sanitize, nonce, URL, etc.)
- ✅ `getCurrentUser()` - WordPress user function
- ❌ Remove all repository and database operation methods

---

## Files That Need Updates

### Services to Refactor:
1. `src/Domain/Services/MinisiteFormProcessor.php` - Inject `MinisiteRepository`
2. `src/Domain/Services/MinisiteDatabaseCoordinator.php` - Inject `MinisiteRepository` and `TransactionManager`
3. `src/Features/PublishMinisite/Services/SubscriptionActivationService.php` - Inject `MinisiteRepository`
4. `src/Features/PublishMinisite/Services/SlugAvailabilityService.php` - Inject `MinisiteRepository`
5. `src/Features/PublishMinisite/Services/ReservationService.php` - Inject `MinisiteRepository`
6. `src/Features/VersionManagement/Repositories/VersionRepository.php` - Refactor `createDraftFromVersion()`

### Interface to Clean:
1. `src/Domain/Interfaces/WordPressManagerInterface.php` - Remove non-WordPress methods

### Implementations to Clean:
1. All 5 WordPress manager classes - Remove stub implementations

