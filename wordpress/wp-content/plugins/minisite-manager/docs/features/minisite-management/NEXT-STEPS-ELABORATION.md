# Next Steps Elaboration - High-Level Plans

## Overview
This document provides detailed high-level plans for the immediate next steps after Phase 6 completion.

---

## 1. Seeder Invocation Integration (Priority: MEDIUM, 2-3 hours)

### Current State
- Seeders are called **directly after migrations** in `ActivationHandler::runMigrations()`
- But there's also a **deprecated `seedTestData()` method** that uses `init` hook as fallback
- `seedDefaultConfigs()` uses `init` hook with retry logic
- **Disconnected flow**: Migrations → immediate seeding → separate `init` hook for configs

### Problem
- **Inconsistent timing**: Test data seeding happens immediately, but config seeding happens later via `init` hook
- **Retry logic duplication**: Both `seedTestData()` and `seedDefaultConfigs()` have similar retry mechanisms
- **Unclear separation**: Hard to understand what happens when and why
- **Error handling scattered**: Each seeder has its own try/catch, no unified error reporting

### High-Level Solution

#### 1.1 Create Unified Seeder Orchestrator
**What**: Create a new `SeederOrchestrator` service that coordinates all seeding operations

**Why**:
- Single point of control for all seeding
- Consistent error handling and logging
- Better separation of concerns

**How**:
- `src/Core/Services/SeederOrchestrator.php`
- Methods:
  - `seedAll()` - Orchestrates all seeding in correct order
  - `seedTestData()` - Wraps test data seeding (minisites, versions, reviews)
  - `seedDefaultConfigs()` - Wraps config seeding
  - `ensureRepositoriesInitialized()` - Centralized initialization check
- Handles retry logic in one place
- Provides unified error reporting

#### 1.2 Integrate with Activation Flow
**What**: Call `SeederOrchestrator` directly after migrations, remove `init` hook dependencies

**Why**:
- Clear activation flow: Migrations → Seeders → Done
- No async timing issues
- Easier to test and debug

**How**:
- Update `ActivationHandler::runMigrations()`:
  ```php
  $doctrineRunner->migrate();
  $seederOrchestrator = new SeederOrchestrator();
  $seederOrchestrator->seedAll(); // Handles both test data and configs
  ```
- Remove `add_action('init', ...)` calls for seeding
- Remove deprecated `seedTestData()` method

#### 1.3 Improve Error Handling
**What**: Unified error handling with rollback options and detailed logging

**Why**:
- Better visibility into what failed and why
- Ability to retry specific seeders
- Clearer error messages for debugging

**How**:
- Track which seeders succeeded/failed
- Log detailed context (which minisite failed, which config failed, etc.)
- Optionally rollback partial seeding (if needed)
- Return structured result object with success/failure details

### Expected Outcome
- ✅ Single, clear activation flow
- ✅ No `init` hook dependencies for seeding
- ✅ Consistent error handling
- ✅ Easier to test (all seeding in one place)
- ✅ Better logging and debugging

### Files to Modify
- `src/Core/ActivationHandler.php` - Simplify, use orchestrator
- `src/Core/Services/SeederOrchestrator.php` - **NEW** - Orchestration service
- Remove deprecated `seedTestData()` method

---

## 2. Additional Unit Tests for Edge Cases (Priority: MEDIUM, Ongoing)

### Current State
- Good coverage for happy paths
- Some edge cases covered (null values, empty strings, etc.)
- Missing: Error conditions, boundary values, invalid inputs

### High-Level Approach

#### 2.1 Identify Gaps
**What**: Analyze existing tests to find missing edge cases

**How**:
- Review coverage reports for low-coverage methods
- Identify methods with complex logic (conditionals, loops, error handling)
- Look for methods that handle user input or external dependencies

**Focus Areas**:
- Repository methods (find, save, update operations)
- Service methods (business logic, validation)
- Value objects (boundary conditions)
- Form processors (invalid inputs, edge cases)

#### 2.2 Test Categories to Add

**A. Error Conditions**
- Repository methods: What happens when entity not found?
- Service methods: What happens when dependencies fail?
- Validation: What happens with invalid data?

**Examples**:
- `MinisiteRepository::findById()` with non-existent ID
- `MinisiteSeederService::loadMinisiteFromJson()` with malformed JSON
- `MinisiteFormProcessor` with missing required fields

**B. Boundary Values**
- Empty strings vs null vs missing properties
- Maximum/minimum values (string lengths, numbers)
- Array boundaries (empty arrays, single item, large arrays)

**Examples**:
- `Minisite` entity with empty `name` vs null `name`
- `SlugPair` with very long slugs
- `GeoPoint` with extreme coordinates

**C. Invalid Inputs**
- Type mismatches
- Malformed data structures
- SQL injection attempts (for repository tests)
- XSS attempts (for form processors)

**Examples**:
- Passing `null` where `string` expected
- Passing `array` where `object` expected
- Special characters in slugs, names, etc.

**D. Concurrent Operations**
- Optimistic locking failures
- Race conditions (if applicable)
- Transaction rollbacks

**Examples**:
- `MinisiteRepository::save()` with stale version number
- Multiple simultaneous updates to same entity

#### 2.3 Implementation Strategy
**What**: Add focused test methods for each edge case

**How**:
- One test method per edge case (clear, focused)
- Use descriptive test names: `test_find_by_id_with_non_existent_id_returns_null()`
- Group related edge cases in same test class
- Use data providers for similar edge cases

**Pattern**:
```php
/**
 * @test
 */
public function test_find_by_id_with_non_existent_id_returns_null(): void
{
    $result = $this->repository->findById('non-existent-id');
    $this->assertNull($result);
}
```

### Expected Outcome
- ✅ Higher code coverage (target: 80%+ for critical paths)
- ✅ Better confidence in error handling
- ✅ Documentation of expected behavior for edge cases
- ✅ Easier to catch regressions

### Files to Enhance
- `tests/Unit/Features/MinisiteManagement/Repositories/MinisiteRepositoryTest.php` - Add edge cases
- `tests/Unit/Features/MinisiteManagement/Services/MinisiteSeederServiceTest.php` - Add error cases
- `tests/Unit/Features/MinisiteViewer/Services/MinisiteViewDataServiceTest.php` - Add edge cases
- `tests/Unit/Domain/Services/MinisiteFormProcessorTest.php` - Add invalid input tests
- `tests/Unit/Domain/ValueObjects/*Test.php` - Add boundary value tests

---

## 3. Integration Tests for Complete Workflows (Priority: MEDIUM, Ongoing)

### Current State
- Good integration tests for individual components (repositories, migrations)
- Missing: End-to-end workflow tests
- Missing: Feature interaction tests

### High-Level Approach

#### 3.1 Identify Key Workflows
**What**: Map out critical user/business workflows that span multiple components

**Key Workflows**:
1. **Minisite Creation Workflow**
   - User submits form → Validation → Save to DB → Create initial version → Return success

2. **Minisite Publishing Workflow**
   - User clicks publish → Update status → Create version → Update current_version_id → Return success

3. **Minisite Viewing Workflow**
   - User visits minisite URL → Load minisite → Load reviews → Check bookmarks → Render template

4. **Minisite Editing Workflow**
   - User edits minisite → Validate → Update entity → Handle optimistic locking → Return success

5. **Activation Workflow**
   - Plugin activates → Run migrations → Seed data → Initialize configs → Ready

#### 3.2 Test Structure
**What**: Create integration tests that exercise complete workflows

**How**:
- One test class per workflow
- Each test method = one complete workflow scenario
- Use real database (integration test pattern)
- Mock only external dependencies (WordPress functions, file system)

**Pattern**:
```php
class MinisiteCreationWorkflowIntegrationTest extends IntegrationTestCase
{
    /**
     * @test
     */
    public function test_complete_minisite_creation_workflow(): void
    {
        // 1. Setup: Prepare form data
        $formData = [...];

        // 2. Execute: Run through complete workflow
        $processor = new MinisiteFormProcessor(...);
        $result = $processor->process($formData);

        // 3. Verify: Check all side effects
        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getMinisiteId());

        // Verify minisite exists in DB
        $minisite = $this->repository->findById($result->getMinisiteId());
        $this->assertNotNull($minisite);

        // Verify initial version was created
        $version = $this->versionRepository->findByMinisiteId($minisite->id);
        $this->assertNotNull($version);

        // Verify minisite has correct status
        $this->assertEquals('draft', $minisite->status);
    }
}
```

#### 3.3 Workflow Test Categories

**A. Happy Path Workflows**
- Complete workflows with valid data
- Verify all steps succeed
- Verify all side effects occur

**B. Error Path Workflows**
- Workflows that fail at various points
- Verify proper error handling
- Verify no partial state (rollbacks work)

**C. Edge Case Workflows**
- Workflows with boundary values
- Workflows with missing optional data
- Workflows with concurrent operations

**D. Performance Workflows**
- Workflows with large datasets
- Workflows with many concurrent requests (if applicable)
- Verify acceptable performance

#### 3.4 Implementation Strategy
**What**: Build workflow tests incrementally, starting with most critical

**Priority Order**:
1. **Activation Workflow** (highest priority - ensures plugin works)
   - Test: Migrations run → Seeders execute → Configs initialized
   - Verify: All tables created, test data seeded, configs available

2. **Minisite Creation Workflow** (core functionality)
   - Test: Form submission → Validation → Save → Version creation
   - Verify: Entity saved, version created, status correct

3. **Minisite Publishing Workflow** (critical business logic)
   - Test: Publish action → Status update → Version update → Slug update
   - Verify: Status changed, version linked, slugs updated

4. **Minisite Viewing Workflow** (user-facing)
   - Test: URL request → Load minisite → Load reviews → Render
   - Verify: Correct data loaded, template rendered

5. **Minisite Editing Workflow** (data integrity)
   - Test: Edit → Validation → Update → Optimistic locking
   - Verify: Updates saved, locking works, conflicts handled

### Expected Outcome
- ✅ Confidence that complete workflows work end-to-end
- ✅ Catch integration bugs early
- ✅ Documentation of how workflows should behave
- ✅ Regression protection for critical paths

### Files to Create
- `tests/Integration/Workflows/ActivationWorkflowIntegrationTest.php` - **NEW**
- `tests/Integration/Workflows/MinisiteCreationWorkflowIntegrationTest.php` - **NEW**
- `tests/Integration/Workflows/MinisitePublishingWorkflowIntegrationTest.php` - **NEW**
- `tests/Integration/Workflows/MinisiteViewingWorkflowIntegrationTest.php` - **NEW**
- `tests/Integration/Workflows/MinisiteEditingWorkflowIntegrationTest.php` - **NEW**

---

## Summary: Recommended Execution Order

### Immediate (Next Session)
1. **Seeder Invocation Integration** (2-3 hours)
   - Clear, bounded task
   - Improves code quality immediately
   - Sets foundation for better testing

### Short Term (Next Few Sessions)
2. **Additional Unit Tests for Edge Cases** (Ongoing, 1-2 hours per session)
   - Focus on one area at a time
   - Start with repositories (most critical)
   - Then services, then value objects

### Medium Term (Ongoing)
3. **Integration Tests for Complete Workflows** (Ongoing, 2-3 hours per workflow)
   - Start with Activation Workflow (highest priority)
   - Then Minisite Creation
   - Then others as needed

---

## Success Metrics

### Seeder Invocation Integration
- ✅ No `init` hook dependencies for seeding
- ✅ Single activation flow (migrations → seeders)
- ✅ Unified error handling
- ✅ All tests pass

### Additional Unit Tests
- ✅ Coverage increases by 10-15% (target: 80%+ for critical paths)
- ✅ All edge cases documented via tests
- ✅ Error conditions tested

### Integration Tests for Workflows
- ✅ 5+ complete workflow tests
- ✅ All critical paths covered
- ✅ Confidence in end-to-end functionality

---

**Last Updated**: After Phase 6 Completion
**Status**: Planning Complete | Ready for Implementation

