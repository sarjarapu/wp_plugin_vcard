# Minisite Doctrine Migration - Test Fix Strategy

## Analysis Summary

After migrating Minisite to Doctrine ORM, we have **3 consistent patterns** of test failures that can be fixed efficiently:

### Pattern 1: Factory Tests Missing Global Repository (~10 tests)
**Issue**: Factory classes now require `$GLOBALS['minisite_repository']` to be set
**Affected Tests**:
- `EditHooksFactoryTest` (10 tests)
- `ListingHooksFactoryTest` (5 tests)
- `ViewHooksFactoryTest` (5 tests)

**Fix**: Mock `$GLOBALS['minisite_repository']` in `setUp()` method
**Impact**: Single fix addresses ~20 tests

---

### Pattern 2: isBookmarked/canEdit Constructor Parameters (~4 tests)
**Issue**: Old `mapRow()` method passes `isBookmarked` and `canEdit` as constructor parameters, but new entity doesn't accept them
**Affected Tests**:
- `MinisiteRepositoryTest::testFindBySlugsReturnsMinisiteWhenFound`
- `MinisiteRepositoryTest::testFindBySlugParamsReturnsMinisiteWithForUpdate`
- `MinisiteRepositoryTest::testFindByIdReturnsMinisiteWhenFound`
- `MinisiteRepositoryTest::testListByOwnerReturnsArrayOfMinisites`

**Fix**: Remove `isBookmarked` and `canEdit` from constructor call in `mapRow()`, set as properties after construction
**Impact**: Single fix addresses 4 tests

---

### Pattern 3: siteJson Array Assignment (1 test)
**Issue**: Test directly assigns array to `siteJson` property instead of using constructor/setter
**Affected Test**:
- `MinisiteViewServiceTest::test_get_minisite_for_version_specific_preview_with_specific_version`

**Fix**: Use `setSiteJsonFromArray()` method or pass array to constructor
**Impact**: Single fix addresses 1 test

---

## Fix Priority

1. **Pattern 1** (Factory Tests) - Highest impact, easiest fix
2. **Pattern 2** (mapRow method) - Medium impact, straightforward fix
3. **Pattern 3** (siteJson assignment) - Low impact, simple fix

## Estimated Fix Time

- Pattern 1: ~15 minutes (update 3 test files)
- Pattern 2: ~5 minutes (update 1 method)
- Pattern 3: ~2 minutes (update 1 test)

**Total**: ~22 minutes to fix ~25 test failures

