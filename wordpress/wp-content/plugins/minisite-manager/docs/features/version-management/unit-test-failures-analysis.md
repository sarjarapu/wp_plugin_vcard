# Unit Test Failures Analysis

## Summary
- **Total Tests**: 1070
- **Errors**: 8
- **Failures**: 1
- **Skipped**: 14

---

## Issue 1: Version Entity is `final` - Cannot Be Mocked (7 errors)

### Affected Tests:
1. `MinisiteViewServiceTest::test_get_minisite_for_version_specific_preview_with_specific_version`
2. `MinisiteViewServiceTest::test_get_minisite_for_version_specific_preview_with_wrong_minisite_version`
3. `CreateDraftHandlerTest::test_handle_delegates_to_service`
4. `RollbackVersionHandlerTest::test_handle_delegates_to_service`
5. `VersionServiceTest::test_create_draft_returns_version_when_successful`
6. `VersionServiceTest::test_publish_version_throws_exception_when_version_not_draft`
7. `VersionServiceTest::test_create_rollback_version_returns_version_when_successful`

### Root Cause:
```php
// src/Features/VersionManagement/Domain/Entities/Version.php
final class Version  // ← Cannot be mocked!
```

**Error Message:**
```
Class "Minisite\Features\VersionManagement\Domain\Entities\Version" is declared "final" and cannot be doubled
```

### Why This Happens:
- PHPUnit's `createMock()` cannot create mocks of `final` classes
- Tests are trying to mock `Version` entity to return from repository/service methods
- The `Version` entity was made `final` (likely for immutability/encapsulation)

### Solutions:

**Option 1: Create Real Version Instances (Recommended)**
Instead of mocking, create actual `Version` instances:

```php
// Instead of:
$mockVersion = $this->createMock(Version::class);

// Use:
$version = new Version(
    minisiteId: 'test-site',
    versionNumber: 1,
    status: 'draft',
    createdBy: 123,
    siteJson: ['test' => 'data']
);
```

**Option 2: Remove `final` from Version Entity**
- Not recommended - breaks encapsulation
- `final` is likely intentional for immutability

**Option 3: Use Test Doubles/Stubs**
- Create a test-specific non-final class that extends Version
- Complex and not ideal

---

## Issue 2: Wrong Namespace in Tests (2 errors)

### Affected Tests:
1. `CreateDraftHandlerTest::test_handle_delegates_to_service`
2. `RollbackVersionHandlerTest::test_handle_delegates_to_service`

### Root Cause:
Tests are using the **old namespace**:

```php
// ❌ Wrong (old namespace)
$expectedResult = $this->createMock(\Minisite\Domain\Entities\Version::class);

// ✅ Correct (new namespace)
$expectedResult = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
```

### Why This Happens:
- Tests weren't updated when `Version` entity was moved to feature-based structure
- Old namespace: `Minisite\Domain\Entities\Version`
- New namespace: `Minisite\Features\VersionManagement\Domain\Entities\Version`

### Solution:
Update namespace in both test files.

---

## Issue 3: Empty Slugs Validation (2 failures/errors)

### Affected Tests:
1. `MinisiteViewServiceTest::test_get_minisite_for_display_with_empty_slugs` (FAILURE)
2. `MinisiteViewServiceTest::test_minisite_exists_with_empty_slugs` (ERROR)

### Root Cause:
The service now validates slugs **before** calling the repository:

```php
// src/Features/MinisiteViewer/Services/MinisiteViewService.php
public function minisiteExists(ViewMinisiteCommand $command): bool
{
    $slugPair = new SlugPair($command->businessSlug, $command->locationSlug);
    // ↑ This throws InvalidArgumentException if slugs are empty
    return $this->minisiteRepository->findBySlugs($slugPair) !== null;
}
```

**Test Expectation:**
```php
// Test expects:
$this->assertEquals('Minisite not found', $result['error']);

// But gets:
'Error retrieving minisite: Business slug must be a non-empty string.'
```

### Why This Happens:
- `SlugPair` constructor validates that business slug is non-empty
- Validation happens **before** repository is called
- Test expects repository to return `null` and service to handle it
- But validation exception is thrown first

### Solution:

**Option 1: Update Test Expectations**
Tests should expect validation error, not "Minisite not found":

```php
// For test_get_minisite_for_display_with_empty_slugs
$this->assertFalse($result['success']);
$this->assertStringContainsString('Business slug must be a non-empty string', $result['error']);

// For test_minisite_exists_with_empty_slugs
$this->expectException(InvalidArgumentException::class);
$this->expectExceptionMessage('Business slug must be a non-empty string');
$result = $this->viewService->minisiteExists($command);
```

**Option 2: Handle Empty Slugs in Service**
Service could check for empty slugs and return early:

```php
public function minisiteExists(ViewMinisiteCommand $command): bool
{
    if (empty($command->businessSlug)) {
        return false;  // Early return
    }
    $slugPair = new SlugPair($command->businessSlug, $command->locationSlug);
    return $this->minisiteRepository->findBySlugs($slugPair) !== null;
}
```

**Option 3: Make SlugPair Accept Empty Slugs**
- Not recommended - breaks validation contract

---

## Summary of Fixes Needed

### High Priority (Blocking):
1. ✅ Fix namespace in `CreateDraftHandlerTest` and `RollbackVersionHandlerTest`
2. ✅ Replace `createMock(Version::class)` with real `Version` instances in all 7 tests
3. ✅ Update test expectations for empty slug validation in `MinisiteViewServiceTest`

### Files to Fix:
1. `tests/Unit/Features/VersionManagement/Handlers/CreateDraftHandlerTest.php`
2. `tests/Unit/Features/VersionManagement/Handlers/RollbackVersionHandlerTest.php`
3. `tests/Unit/Features/VersionManagement/Services/VersionServiceTest.php`
4. `tests/Unit/Features/MinisiteViewer/Services/MinisiteViewServiceTest.php`

---

## Quick Fix Strategy

1. **For Version mocking**: Create helper method to build real `Version` instances
2. **For namespace**: Simple find/replace
3. **For empty slugs**: Update test expectations to match new validation behavior

