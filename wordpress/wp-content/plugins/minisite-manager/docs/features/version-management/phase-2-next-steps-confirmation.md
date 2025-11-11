# Phase 2 Next Steps - Confirmation

## Current Status

✅ **EASY Refactoring Complete:**
- `SubscriptionActivationService` - ✅ Done
- `SlugAvailabilityService` - ✅ Done
- `ReservationService` - ✅ Done

## Next Steps - MEDIUM Refactoring

There are **TWO MEDIUM tasks** in the plan:

### Option 1: Refactor MinisiteFormProcessor (First MEDIUM)
**Complexity:** ⭐⭐ Medium
**Estimated Time:** 1-2 hours
**Files Affected:** 1 service + 4 instantiation points

**What needs to be done:**
1. Add `MinisiteRepository` to `MinisiteFormProcessor` constructor
2. Replace `$this->wordPressManager->findMinisiteById()` with `$this->minisiteRepository->findById()`
3. Update 4 instantiation points:
   - `NewMinisiteService.php` - Line 84, 164
   - `EditService.php` - Line 112
   - `MinisiteDatabaseCoordinator.php` - Line 108, 439

**Why this first?**
- Simpler than `MinisiteDatabaseCoordinator`
- Fewer dependencies
- Good warm-up before the more complex coordinator

---

### Option 2: Refactor MinisiteDatabaseCoordinator (Second MEDIUM)
**Complexity:** ⭐⭐ Medium
**Estimated Time:** 1-2 hours
**Files Affected:** 1 service + 2 instantiation points

**What needs to be done:**

#### Part A: Repository Methods (This is what you're asking about)
1. Add `MinisiteRepository` to `MinisiteDatabaseCoordinator` constructor
2. Replace `$this->wordPressManager->getMinisiteRepository()` (2 occurrences) with `$this->minisiteRepository`
3. Replace `$this->wordPressManager->updateMinisiteFields()` (1 occurrence) with `$this->minisiteRepository->updateMinisiteFields()`
4. Update 2 instantiation points:
   - `NewMinisiteService.php` - Line 85
   - `EditService.php` - Line 113

**Current Usage in MinisiteDatabaseCoordinator:**
- Line 224: `$this->wordPressManager->getMinisiteRepository()->insert($minisite)`
- Line 366: `$this->wordPressManager->getMinisiteRepository()->updateCurrentVersionId($minisiteId, $savedVersion->id)`
- Line 694: `$this->wordPressManager->updateMinisiteFields($siteId, $allUpdateFields, (int) $currentUser->ID)`

#### Part B: Transaction Methods (Separate - HARD task)
- Line 149, 447: `$this->wordPressManager->startTransaction()`
- Line 373, 558: `$this->wordPressManager->commitTransaction()`
- Line 418, 565: `$this->wordPressManager->rollbackTransaction()`

**Note:** Transaction methods are handled separately in the HARD task (TransactionManager creation).

---

## Recommendation

**Yes, `MinisiteDatabaseCoordinator` (Repository Methods) is a good next step**, BUT:

### Suggested Order:
1. ✅ **EASY** - Done
2. ⏭️ **MEDIUM: MinisiteFormProcessor** - Simpler, good warm-up
3. ⏭️ **MEDIUM: MinisiteDatabaseCoordinator (Repository)** - More complex, but manageable
4. ⏭️ **HARD: TransactionManager** - Most complex, requires new abstraction

### Why MinisiteDatabaseCoordinator Next?

**Pros:**
- ✅ `NewMinisiteService` and `EditService` already have `MinisiteRepository` injected
- ✅ Only 3 method calls to replace (2 `getMinisiteRepository()`, 1 `updateMinisiteFields()`)
- ✅ Clear, isolated changes
- ✅ Transaction methods are separate (won't touch those yet)

**Cons:**
- ⚠️ Core service with complex logic
- ⚠️ Multiple transaction boundaries (but we're not touching those)
- ⚠️ Need to ensure repository is properly injected

---

## Confirmation

**Question:** Is `MinisiteDatabaseCoordinator` (Repository Methods) the next step?

**Answer:** ✅ **YES, it's a valid next step**, but consider doing `MinisiteFormProcessor` first as it's simpler.

**What will be changed:**
- ✅ Add `MinisiteRepository` to constructor
- ✅ Replace 3 calls to WordPress manager repository methods
- ✅ Update 2 instantiation points
- ❌ **NOT touching transaction methods** (those are HARD task)

**Files to modify:**
1. `src/Domain/Services/MinisiteDatabaseCoordinator.php`
2. `src/Features/NewMinisite/Services/NewMinisiteService.php`
3. `src/Features/MinisiteEdit/Services/EditService.php`

---

## Summary

✅ **Confirmed:** `MinisiteDatabaseCoordinator` (Repository Methods) is a valid next step.

**Scope:**
- ✅ Repository methods only (`getMinisiteRepository()`, `updateMinisiteFields()`)
- ❌ Transaction methods excluded (handled in HARD task)

**Ready to proceed?** Yes, but consider `MinisiteFormProcessor` first if you want an easier warm-up.

