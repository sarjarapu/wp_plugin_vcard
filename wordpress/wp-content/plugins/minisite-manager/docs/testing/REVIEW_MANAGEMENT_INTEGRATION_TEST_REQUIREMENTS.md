# Review Management Integration Test Requirements

This document outlines the integration test requirements for the Review Management feature. These tests should be written when the feature has full UI implementation and requires database interactions.

## Overview

The Review Management feature currently has comprehensive unit tests covering:
- ✅ Review entity (domain logic)
- ✅ ReviewRepository (with mocks)
- ✅ ReviewSeederService (with mocks)
- ✅ ReviewHooks (structure)
- ✅ ReviewHooksFactory (structure)
- ✅ ReviewManagementFeature (bootstrap)

However, several components require integration tests to verify behavior with real database connections and WordPress environment.

## Integration Test Requirements

### 1. ReviewRepository Integration Tests

**File**: `tests/Integration/Features/ReviewManagement/Repositories/ReviewRepositoryIntegrationTest.php`

**Test Cases Needed**:

#### 1.1 CRUD Operations
- ✅ `testSaveInsertNewReview()` - Insert a new review and verify it's saved
- ✅ `testSaveUpdateExistingReview()` - Update an existing review
- ✅ `testFindByIdReturnsReview()` - Find review by ID when it exists
- ✅ `testFindByIdReturnsNullWhenNotFound()` - Find review by ID when it doesn't exist
- ✅ `testFindOrFailThrowsWhenNotFound()` - Verify exception is thrown when review not found
- ✅ `testDeleteRemovesReview()` - Delete a review and verify it's removed

#### 1.2 Query Operations
- ✅ `testListApprovedForMinisite()` - List approved reviews for a minisite
- ✅ `testListByStatusForMinisite()` - List reviews by status (pending, approved, rejected, flagged)
- ✅ `testCountByStatusForMinisite()` - Count reviews by status
- ✅ `testListByStatusForMinisiteRespectsLimit()` - Verify limit parameter is respected
- ✅ `testListByStatusForMinisiteOrdersCorrectly()` - Verify ordering by displayOrder, publishedAt, createdAt

#### 1.3 Error Handling
- `testSaveHandlesDatabaseErrors()` - Verify exception handling on save failure
- `testDeleteHandlesDatabaseErrors()` - Verify exception handling on delete failure
- `testFindHandlesDatabaseErrors()` - Verify exception handling on find failure

**Dependencies**: 
- Real MySQL database with WordPress prefix
- Doctrine EntityManager
- WordPress DB constants configured

---

### 2. ReviewSeederService Integration Tests

**File**: `tests/Integration/Features/ReviewManagement/Services/ReviewSeederServiceIntegrationTest.php`

**Test Cases Needed**:

#### 2.1 Review Creation
- `testInsertReviewCreatesReviewWithAllFields()` - Verify all 24 MVP fields are set correctly
- `testInsertReviewWithLoggedInUser()` - Verify createdBy and moderatedBy are set when user is logged in
- `testInsertReviewWithOptionalFields()` - Verify optional fields (email, phone, URL, displayOrder) are saved
- `testInsertReviewAutoDetectsLanguageFromLocale()` - Verify language auto-detection from locale

#### 2.2 JSON Data Creation
- `testCreateReviewFromJsonDataWithAllFields()` - Create review from complete JSON data
- `testCreateReviewFromJsonDataWithDefaults()` - Create review from minimal JSON data (verify defaults)
- `testCreateReviewFromJsonDataParsesTimestamps()` - Verify DateTimeImmutable parsing from JSON strings
- `testCreateReviewFromJsonDataMarksAsPublishedWhenApproved()` - Verify publishedAt is set when status is approved

#### 2.3 Seeding Operations
- `testSeedReviewsForMinisite()` - Seed multiple reviews for a minisite
- `testSeedReviewsForMinisiteWithEmptyArray()` - Verify no errors when seeding empty array
- `testSeedAllTestReviews()` - Seed reviews for all test minisites (ACME, LOTUS, GREEN, SWIFT)

#### 2.4 JSON File Loading
- `testLoadReviewsFromJsonFileExists()` - Load reviews from existing JSON file
- `testLoadReviewsFromJsonFileNotFound()` - Verify RuntimeException when file doesn't exist
- `testLoadReviewsFromJsonInvalidJson()` - Verify RuntimeException when JSON is invalid
- `testLoadReviewsFromJsonMissingReviewsArray()` - Verify RuntimeException when JSON structure is invalid

**Dependencies**:
- Real MySQL database
- JSON files in `data/json/reviews/` directory
- WordPress functions: `get_current_user_id()`, `date()`
- WordPress constants: `MINISITE_PLUGIN_DIR`

---

### 3. ReviewHooksFactory Integration Tests

**File**: `tests/Integration/Features/ReviewManagement/Hooks/ReviewHooksFactoryIntegrationTest.php`

**Test Cases Needed**:

#### 3.1 Factory Creation
- `testCreateReturnsReviewHooksInstance()` - Verify factory creates ReviewHooks instance
- `testCreateInjectsCorrectDependencies()` - Verify all dependencies are injected correctly
- `testCreateUsesRealDoctrineEntityManager()` - Verify Doctrine EntityManager is created correctly

**Dependencies**:
- Doctrine EntityManager
- WordPress DB constants
- Real database connection (may be skipped if DB unavailable)

---

### 4. End-to-End Review Workflow Integration Tests

**File**: `tests/Integration/Features/ReviewManagement/ReviewWorkflowIntegrationTest.php`

**Test Cases Needed**:

#### 4.1 Complete Review Lifecycle
- `testReviewLifecycleFromCreationToDeletion()` - Complete flow: create → find → update → delete
- `testReviewModerationWorkflow()` - Test status changes: pending → approved → flagged → rejected
- `testReviewListingAndFiltering()` - Test listing reviews with various filters and statuses

#### 4.2 Review Statistics
- `testReviewStatisticsForMinisite()` - Test counting reviews by status for a minisite
- `testReviewAverageRatingCalculation()` - Calculate average rating from reviews (if implemented)

**Dependencies**:
- Real database
- Complete Review Management feature implementation

---

### 5. WordPress Integration Tests

**File**: `tests/Integration/Features/ReviewManagement/WordPressIntegrationTest.php`

**Test Cases Needed**:

#### 5.1 WordPress Hook Registration
- `testReviewHooksRegisterWordPressHooks()` - Verify hooks are registered when feature is initialized
- `testReviewRoutesInterceptCorrectly()` - Test route interception when review management UI is added

**Dependencies**:
- WordPress environment
- Brain Monkey or WordPress test framework
- Complete Review Management UI implementation

---

## Test Data Requirements

### Database Setup
- Clean database state before each test
- Test minisites with IDs: `test-minisite-1`, `test-minisite-2`, etc.
- Test reviews with various statuses, ratings, and metadata

### JSON Files
- `acme-dental-reviews.json` - Sample reviews for ACME Dental minisite
- `lotus-textiles-reviews.json` - Sample reviews for Lotus Textiles minisite
- `green-bites-reviews.json` - Sample reviews for Green Bites minisite
- `swift-transit-reviews.json` - Sample reviews for Swift Transit minisite

### Test Users
- Anonymous user (ID: 0)
- Logged-in user (ID: 1)
- Moderator user (ID: 2)

---

## Priority Order

1. **High Priority**:
   - ReviewRepository CRUD operations
   - ReviewRepository query operations
   - ReviewSeederService basic operations

2. **Medium Priority**:
   - ReviewSeederService JSON file loading
   - ReviewSeederService seeding operations
   - Error handling tests

3. **Low Priority** (when UI is implemented):
   - WordPress hook registration
   - End-to-end workflow tests
   - Review statistics

---

## Notes

- Unit tests currently cover most logic with mocks
- Integration tests should focus on database interactions, Doctrine queries, and real-world scenarios
- Some tests (like JSON file loading) may require file system setup
- WordPress integration tests should be added when the review management UI is implemented

