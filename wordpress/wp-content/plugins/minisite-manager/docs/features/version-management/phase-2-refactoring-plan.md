# Phase 2 Refactoring Plan: Remove Non-WordPress Methods from WordPressManagerInterface

## Overview

Phase 2 will refactor the remaining 6 methods that are still used but don't belong in `WordPressManagerInterface`:
1. `findMinisiteById()` - Used in `MinisiteFormProcessor`
2. `updateMinisiteFields()` - Used in `MinisiteDatabaseCoordinator`
3. `getMinisiteRepository()` - Used in 4 services
4. `startTransaction()` - Used in `MinisiteDatabaseCoordinator`
5. `commitTransaction()` - Used in `MinisiteDatabaseCoordinator`
6. `rollbackTransaction()` - Used in `MinisiteDatabaseCoordinator`

---

## 🟢 EASY: Refactor PublishMinisite Services

**Complexity:** ⭐ Easy
**Estimated Time:** 30-45 minutes
**Risk:** Low
**Files Affected:** 3 services

### Tasks:
1. **SubscriptionActivationService**
   - Add `MinisiteRepository` to constructor
   - Replace `$this->wordPressManager->getMinisiteRepository()` with injected repository
   - Update factory/instantiation point

2. **SlugAvailabilityService**
   - Add `MinisiteRepository` to constructor
   - Replace `$this->wordPressManager->getMinisiteRepository()` with injected repository
   - Update factory/instantiation point

3. **ReservationService**
   - Add `MinisiteRepository` to constructor
   - Replace `$this->wordPressManager->getMinisiteRepository()` with injected repository
   - Update factory/instantiation point

### Why Easy?
- Simple dependency injection pattern
- No complex logic changes
- Clear, isolated changes
- Services are already well-structured

### Files to Modify:
- `src/Features/PublishMinisite/Services/SubscriptionActivationService.php`
- `src/Features/PublishMinisite/Services/SlugAvailabilityService.php`
- `src/Features/PublishMinisite/Services/ReservationService.php`
- Factory/instantiation points (likely in hooks or controllers)

### After This:
- `getMinisiteRepository()` can be removed from `WordPressManagerInterface` ✅

---

## 🟡 MEDIUM: Refactor MinisiteFormProcessor

**Complexity:** ⭐⭐ Medium
**Estimated Time:** 1-2 hours
**Risk:** Medium
**Files Affected:** 1 service + 4 instantiation points

### Tasks:
1. **Update MinisiteFormProcessor**
   - Add `MinisiteRepository` to constructor (keep `WordPressManagerInterface` for other methods)
   - Replace `$this->wordPressManager->findMinisiteById()` with `$this->minisiteRepository->findById()`
   - Update method signature if needed

2. **Update All Instantiation Points** (4 locations):
   - `src/Features/NewMinisite/Services/NewMinisiteService.php` - Line 84, 164
   - `src/Features/MinisiteEdit/Services/EditService.php` - Line 112
   - `src/Domain/Services/MinisiteDatabaseCoordinator.php` - Line 108, 439

### Why Medium?
- Multiple instantiation points need updating (4 locations)
- Need to pass repository from parent services to `MinisiteFormProcessor`
- Requires careful testing to ensure no regressions

### Considerations:
- ✅ **GOOD NEWS**: `NewMinisiteService` and `EditService` already have `MinisiteRepository` injected!
- Just need to pass it to `MinisiteFormProcessor` when instantiating
- `MinisiteDatabaseCoordinator` needs `MinisiteRepository` added to its constructor
- Both parent services already have it, so easy to pass down

### Files to Modify:
- `src/Domain/Services/MinisiteFormProcessor.php`
- `src/Features/NewMinisite/Services/NewMinisiteService.php`
- `src/Features/MinisiteEdit/Services/EditService.php`
- `src/Domain/Services/MinisiteDatabaseCoordinator.php`

### After This:
- `findMinisiteById()` can be removed from `WordPressManagerInterface` ✅

---

## 🟡 MEDIUM: Refactor MinisiteDatabaseCoordinator (Repository Methods)

**Complexity:** ⭐⭐ Medium
**Estimated Time:** 1-2 hours
**Risk:** Medium
**Files Affected:** 1 service + 2 instantiation points

### Tasks:
1. **Update MinisiteDatabaseCoordinator**
   - Add `MinisiteRepository` to constructor (already has `VersionRepositoryInterface`)
   - Replace `$this->wordPressManager->getMinisiteRepository()` with `$this->minisiteRepository`
   - Replace `$this->wordPressManager->updateMinisiteFields()` with `$this->minisiteRepository->updateMinisiteFields()`

2. **Update Instantiation Points** (2 locations):
   - `src/Features/NewMinisite/Services/NewMinisiteService.php` - Line 85
   - `src/Features/MinisiteEdit/Services/EditService.php` - Line 113

### Why Medium?
- Core service with complex logic
- Multiple transaction points
- Need to ensure repository is properly injected
- May need to verify transaction boundaries still work correctly

### Considerations:
- ✅ **GOOD NEWS**: `NewMinisiteService` and `EditService` already have `MinisiteRepository` injected!
- `MinisiteDatabaseCoordinator` already has `VersionRepositoryInterface` injected
- Just need to add `MinisiteRepository` to constructor and pass it from parent services
- Transaction methods are separate (handled in next section)

### Files to Modify:
- `src/Domain/Services/MinisiteDatabaseCoordinator.php`
- `src/Features/NewMinisite/Services/NewMinisiteService.php`
- `src/Features/MinisiteEdit/Services/EditService.php`

### After This:
- `updateMinisiteFields()` can be removed from `WordPressManagerInterface` ✅
- `getMinisiteRepository()` can be removed from `WordPressManagerInterface` ✅ (if not already done)

---

## 🔴 HARD: Create TransactionManager and Refactor Transactions

**Complexity:** ⭐⭐⭐ Hard
**Estimated Time:** 3-4 hours
**Risk:** High
**Files Affected:** New interface + implementation + 1 service + 2 instantiation points

### Tasks:

#### 1. Create TransactionManager Interface
```php
// src/Domain/Interfaces/TransactionManagerInterface.php
interface TransactionManagerInterface
{
    public function startTransaction(): void;
    public function commitTransaction(): void;
    public function rollbackTransaction(): void;
}
```

#### 2. Create WordPress Implementation
```php
// src/Infrastructure/Persistence/WordPressTransactionManager.php
class WordPressTransactionManager implements TransactionManagerInterface
{
    // Uses $wpdb or DatabaseHelper
}
```

✅ Implemented: `WordPressTransactionManager` now lives in `src/Infrastructure/Persistence/WordPressTransactionManager.php`.

#### 3. Create Doctrine Implementation (Optional but Recommended)
```php
// src/Infrastructure/Persistence/Doctrine/DoctrineTransactionManager.php
class DoctrineTransactionManager implements TransactionManagerInterface
{
    // Uses EntityManager transaction methods
    // entityManager->beginTransaction()
    // entityManager->commit()
    // entityManager->rollback()
}
```
▶️ Deferred: Not required yet—the current flows still rely on `$wpdb`. Revisit once Doctrine write-paths need coordinated transactions.

#### 4. Update MinisiteDatabaseCoordinator
- Add `TransactionManagerInterface` to constructor
- Replace `$this->wordPressManager->startTransaction()` with `$this->transactionManager->startTransaction()`
- Replace `$this->wordPressManager->commitTransaction()` with `$this->transactionManager->commitTransaction()`
- Replace `$this->wordPressManager->rollbackTransaction()` with `$this->transactionManager->rollbackTransaction()`

#### Why Transaction Coordinators Matter
- Coordinators centralize transaction handling for multi-table workflows (e.g., minisite + version updates).
- Keeps services from mixing WordPress manager plumbing with domain logic.
- Makes it simpler to swap underlying implementations (wpdb today, Doctrine later) without touching business code.
- Provides a single place to extend when we introduce Doctrine transactions or cross-repository orchestration.

#### 5. Update Instantiation Points
- `src/Features/NewMinisite/Services/NewMinisiteService.php` - Line 85
- `src/Features/MinisiteEdit/Services/EditService.php` - Line 113
- Need to create and inject appropriate `TransactionManager` instance

### Why Hard?
- **New Abstraction Layer**: Creating a new interface and implementations
- **Multiple Implementations**: Need both WordPress and potentially Doctrine implementations
- **Transaction Coordination**: Need to ensure transactions work correctly across both $wpdb and Doctrine
- **Testing Complexity**: Need to test transaction rollback scenarios
- **Doctrine Integration**: If using Doctrine, need to coordinate with EntityManager transactions
- **Potential Issues**:
  - Mixed transactions ($wpdb + Doctrine) may not work correctly
  - Need to decide: single transaction manager or separate ones?
  - Doctrine EntityManager already has transaction methods - should we use those?

### Key Decisions Needed:

1. **Single vs. Dual Transaction Managers?**
   - Option A: Single `TransactionManager` that handles both $wpdb and Doctrine
   - Option B: Separate managers (`WordPressTransactionManager` and `DoctrineTransactionManager`)
   - **Recommendation**: Option B - clearer separation, easier to test

2. **Doctrine Integration?**
   - Should `DoctrineTransactionManager` use `EntityManager::beginTransaction()`?
   - Or should we continue using raw SQL (`START TRANSACTION`)?
   - **Recommendation**: Use Doctrine's transaction methods for Doctrine operations

3. **Transaction Scope?**
   - Are transactions only for $wpdb operations?
   - Or do we need to coordinate $wpdb + Doctrine transactions?
   - **Current Usage**: Only in `MinisiteDatabaseCoordinator` which uses both repositories

### Considerations:
- `MinisiteDatabaseCoordinator` uses both `VersionRepositoryInterface` (Doctrine) and `MinisiteRepository` ($wpdb)
- Transactions need to work across both systems
- May need to coordinate transactions if mixing $wpdb and Doctrine
- Need to ensure rollback works correctly in all scenarios

### Files to Create:
- `src/Domain/Interfaces/TransactionManagerInterface.php`
- `src/Infrastructure/Persistence/WordPressTransactionManager.php`
- `src/Infrastructure/Persistence/Doctrine/DoctrineTransactionManager.php` (optional)

### Files to Modify:
- `src/Domain/Services/MinisiteDatabaseCoordinator.php`
- `src/Features/NewMinisite/Services/NewMinisiteService.php`
- `src/Features/MinisiteEdit/Services/EditService.php`
- Factory/instantiation points

### After This:
- `startTransaction()` can be removed from `WordPressManagerInterface` ✅
- `commitTransaction()` can be removed from `WordPressManagerInterface` ✅
- `rollbackTransaction()` can be removed from `WordPressManagerInterface` ✅

---

## Recommended Execution Order

1. **Start with EASY** (PublishMinisite Services)
   - Low risk, quick wins
   - Builds confidence
   - Removes `getMinisiteRepository()` usage

2. **Then MEDIUM (MinisiteFormProcessor)**
   - Moderate complexity
   - Removes `findMinisiteById()` usage

3. **Then MEDIUM (MinisiteDatabaseCoordinator Repository)**
   - Removes `updateMinisiteFields()` and remaining `getMinisiteRepository()` usage
   - Prepares for transaction refactoring

4. **Finally HARD (TransactionManager)**
   - Most complex, requires careful planning
   - Removes all transaction methods
   - Completes Phase 2

---

## Outstanding Follow-Ups

Even with Phase 2 implemented, a few items remain on the backlog:

1. **Fix the failing composer tests** – resolve existing suite failures (e.g. `VersionRepository::save()` signature mismatch) so the build is green.
2. **Add coverage for new flows** – add unit/integration tests for the Doctrine-backed version repository and transaction manager usage (mirroring Config/Review).
3. **Retire the legacy `$wpdb` version repository** – remove the old implementation/fallbacks once end-to-end verification is complete.
4. **Verify hooks/bootstrap wiring** – double-check that all hooks/controllers now rely solely on the Doctrine repository (no lingering globals).
5. **Seeder/documentation refresh** – ensure JSON seeding notes/tests line up with the Doctrine entity workflow.
6. **Deferred** – introduce `DoctrineTransactionManager` when Doctrine write-paths require shared transactions.

---

## Final State After Phase 2

### WordPressManagerInterface will only contain:
- ✅ `getCurrentUser()` - WordPress function wrapper
- ✅ `sanitizeTextField()` - WordPress function wrapper
- ✅ `sanitizeTextareaField()` - WordPress function wrapper
- ✅ `sanitizeUrl()` - WordPress function wrapper
- ✅ `sanitizeEmail()` - WordPress function wrapper
- ✅ `verifyNonce()` - WordPress function wrapper
- ✅ `createNonce()` - WordPress function wrapper
- ✅ `getHomeUrl()` - WordPress function wrapper

**Total: 8 methods, all WordPress-related** ✅

### All Non-WordPress Methods Removed:
- ❌ `findMinisiteById()` - Replaced with direct `MinisiteRepository` injection
- ❌ `updateMinisiteFields()`
