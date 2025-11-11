# VersionRepositoryInterface Usage Analysis

## Current State: WordPress Managers with VersionRepositoryInterface

### 1. WordPressEditManager
**Location:** `src/Features/MinisiteEdit/WordPress/WordPressEditManager.php`

**Has:**
- `private ?VersionRepositoryInterface $versionRepository = null;`
- `public function getVersionRepository(): VersionRepositoryInterface`

**Exposes these methods (delegating to repository):**
- `findVersionById(int $versionId): ?object` → `$repo->findById()`
- `getLatestDraftForEditing(string $siteId): ?object` → `$repo->getLatestDraftForEditing()`
- `findLatestDraft(string $siteId): ?object` → `$repo->findLatestDraft()`
- `getNextVersionNumber(string $siteId): int` → `$repo->getNextVersionNumber()`
- `saveVersion(object $version): object` → `$repo->save()`
- `findPublishedVersion(string $siteId): ?object` → `$repo->findPublishedVersion()`
- `hasBeenPublished(string $siteId): bool` → uses `findPublishedVersion()`

**Called by:**
- `EditService::getEditingVersion()` → calls `findVersionById()` and `getLatestDraftForEditing()`
- `EditService::getMinisiteForEditing()` → calls `findLatestDraft()`
- `EditService::saveMinisiteData()` → calls `hasBeenPublished()`

---

### 2. WordPressNewMinisiteManager
**Location:** `src/Features/NewMinisite/WordPress/WordPressNewMinisiteManager.php`

**Has:**
- `private ?VersionRepositoryInterface $versionRepository = null;`
- `public function getVersionRepository(): VersionRepositoryInterface`

**Exposes these methods (delegating to repository):**
- `getNextVersionNumber(string $minisiteId): int` → `$repo->getNextVersionNumber()`
- `saveVersion(object $version): object` → `$repo->save()`

**Called by:**
- `MinisiteDatabaseCoordinator::createNewDraft()` → calls `getNextVersionNumber()` and `saveVersion()`
- `MinisiteDatabaseCoordinator::updateDraftVersion()` → calls `saveVersion()`

---

### 3. WordPressMinisiteManager (Viewer)
**Location:** `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php`

**Has:**
- `private ?VersionRepositoryInterface $versionRepository = null;`
- `public function getVersionRepository(): VersionRepositoryInterface`

**Exposes:**
- Only exposes `getVersionRepository()` directly (doesn't wrap methods)

**Called by:**
- `MinisiteViewService::getMinisiteForViewing()` → calls `getVersionRepository()` then uses it directly

---

### 4. WordPressVersionManager
**Location:** `src/Features/VersionManagement/WordPress/WordPressVersionManager.php`

**Has:**
- `private function getVersionRepository(): VersionRepositoryInterface` (private method)

**Exposes these methods (delegating to repository):**
- `hasBeenPublished(string $id): bool` → uses `$repo->findPublishedVersion()`
- `getNextVersionNumber(string $id): int` → `$repo->getNextVersionNumber()`
- `saveVersion(object $version): object` → `$repo->save()`

**Called by:**
- `VersionService` does NOT use these - it injects `VersionRepositoryInterface` directly ✅ (CORRECT PATTERN)
- These methods exist because `WordPressManagerInterface` requires them (interface bloat issue)

---

### 5. WordPressListingManager
**Location:** `src/Features/MinisiteListing/WordPress/WordPressListingManager.php`

**Has:**
- `private VersionRepositoryInterface $versionRepository;` (in constructor)
- **Does NOT expose any methods** - just stores it

**Usage:**
- Currently unused - just stored but never accessed

---

## Dependency Chain Analysis

### ❌ Problem Pattern (Services accessing repositories through WordPress managers):

```
Service → WordPressManager → VersionRepository
```

**Examples:**
1. `EditService` → `WordPressEditManager` → `VersionRepository`
2. `MinisiteViewService` → `WordPressMinisiteManager` → `VersionRepository`
3. `MinisiteDatabaseCoordinator` → `WordPressNewMinisiteManager` → `VersionRepository`

### ✅ Correct Pattern (Services injecting repositories directly):

```
Service → VersionRepository (direct injection)
```

**Example:**
- `VersionService` → `VersionRepositoryInterface` (direct injection) ✅

---

## Summary of Issues

1. **Architectural Violation**: WordPress managers should only handle WordPress-specific functions (sanitization, nonces, URLs, etc.), not repository operations.

2. **Unnecessary Indirection**: Services are accessing repositories through WordPress managers, adding an extra layer that serves no purpose.

3. **Interface Bloat**: `WordPressManagerInterface` includes repository-related methods (`getNextVersionNumber()`, `saveVersion()`, `hasBeenPublished()`) that don't belong in a WordPress manager interface.

4. **Inconsistent Patterns**:
   - `VersionService` correctly injects `VersionRepositoryInterface` directly
   - Other services (`EditService`, `MinisiteViewService`, `MinisiteDatabaseCoordinator`) incorrectly access repositories through WordPress managers

---

## Refactoring Plan

### Step 1: Update Services to Inject Repositories Directly
- `EditService` → inject `VersionRepositoryInterface`
- `MinisiteViewService` → inject `VersionRepositoryInterface`
- `MinisiteDatabaseCoordinator` → inject `VersionRepositoryInterface` (or pass it as parameter)

### Step 2: Remove Repository Dependencies from WordPress Managers
- Remove `VersionRepositoryInterface` from all WordPress managers
- Remove repository-related methods from WordPress managers
- Keep only WordPress-specific functions (sanitize, nonce, URLs, etc.)

### Step 3: Update Factories
- Update `EditHooksFactory`, `ViewHooksFactory`, etc. to inject repositories into services
- Remove repository initialization from WordPress managers

### Step 4: Clean Up Interface (Optional)
- Consider splitting `WordPressManagerInterface` to remove repository-related methods
- Or create separate interfaces for different concerns

---

## Files That Need Changes

### Services (need repository injection):
1. `src/Features/MinisiteEdit/Services/EditService.php`
2. `src/Features/MinisiteViewer/Services/MinisiteViewService.php`
3. `src/Domain/Services/MinisiteDatabaseCoordinator.php`

### WordPress Managers (remove repository):
1. `src/Features/MinisiteEdit/WordPress/WordPressEditManager.php`
2. `src/Features/NewMinisite/WordPress/WordPressNewMinisiteManager.php`
3. `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php`
4. `src/Features/VersionManagement/WordPress/WordPressVersionManager.php`
5. `src/Features/MinisiteListing/WordPress/WordPressListingManager.php`

### Factories (update dependency injection):
1. `src/Features/MinisiteEdit/Hooks/EditHooksFactory.php`
2. `src/Features/MinisiteViewer/Hooks/ViewHooksFactory.php`
3. `src/Features/NewMinisite/Hooks/NewMinisiteHooksFactory.php`

### Interface (optional cleanup):
1. `src/Domain/Interfaces/WordPressManagerInterface.php`

