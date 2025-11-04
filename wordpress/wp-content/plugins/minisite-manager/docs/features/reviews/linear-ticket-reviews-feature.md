# Reviews Feature - Doctrine Migration & Implementation

## Summary
Migrate reviews system from legacy wpdb-based approach to modern Doctrine ORM architecture with complete MVP field support (24 fields).

## Background
The reviews system was previously using:
- Legacy SQL file-based table creation (`data/db/tables/minisite_reviews.sql`)
- Direct `$wpdb` queries in `_1_0_0_CreateBase.php` migration
- No type safety, no entity management, no repository pattern

## Migration to Doctrine ORM

### Completed Work

#### 1. Entity Implementation
- **File**: `src/Domain/Entities/Review.php`
- **Doctrine ORM Entity** with all 24 MVP fields
- Attributes: `#[ORM\Entity]`, `#[ORM\Table]`, `#[ORM\Column]`
- Methods: `markAsPublished()`, `markAsRejected()`, `markAsFlagged()`, `touch()`
- Indexes: `idx_minisite`, `idx_status_date`, `idx_rating`

#### 2. Repository Pattern
- **Interface**: `src/Infrastructure/Persistence/Repositories/ReviewRepositoryInterface.php`
  - `save(Review $review): Review`
  - `findById(int $id): ?Review`
  - `findOrFail(int $id): Review`
  - `delete(Review $review): void`
  - `listApprovedForMinisite(string $minisiteId, int $limit = 20): array`
  - `listByStatusForMinisite(string $minisiteId, string $status, int $limit = 20): array`
  - `countByStatusForMinisite(string $minisiteId, string $status): int`

- **Implementation**: `src/Infrastructure/Persistence/Repositories/ReviewRepository.php`
  - Extends `Doctrine\ORM\EntityRepository`
  - Implements `ReviewRepositoryInterface`
  - Integrated with `LoggingServiceProvider`
  - Uses Doctrine Query Builder for queries

#### 3. Database Migration
- **File**: `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`
- **Approach**: Schema API (matches config table migration pattern)
- Creates complete table with all 24 MVP columns if table doesn't exist
- If table exists, skips (handles existing installations gracefully)
- **Table Name**: `wp_minisite_reviews` (WordPress prefix applied via `TablePrefixListener`)

#### 4. Review Seeding Service
- **File**: `src/Domain/Services/ReviewSeederService.php`
- **Purpose**: Seed sample review data using Doctrine
- **Methods**:
  - `insertReview()` - Creates review with all 24 MVP fields explicitly set
  - `seedReviewsForMinisite()` - Seeds multiple reviews for a minisite
  - `seedAllTestReviews()` - Seeds all 20 test reviews (5 per minisite)

#### 5. Legacy Code Cleanup
- **File**: `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php`
- Commented out old SQL file loading for `minisite_reviews.sql`
- Commented out old `insertReview()` method (wpdb-based)
- Commented out all review seeding calls (20 reviews)
- Commented out foreign key constraint creation
- Commented out table drop in `down()` method
- **Note**: Old code kept for reference, will be removed after confirmation

#### 6. Integration Points Updated
- `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php`
  - Updated `getReviewsForMinisite()` to use global `ReviewRepository`
- `src/Application/Rendering/TimberRenderer.php`
  - Updated `fetchReviews()` to use global `ReviewRepository`
- `src/Core/PluginBootstrap.php`
  - Initializes `ReviewRepository` and stores in `$GLOBALS['minisite_review_repository']`

## MVP Fields (24 Total)

### Core Fields (Required)
1. `id` - Primary key (BIGINT UNSIGNED, AUTO_INCREMENT)
2. `minisiteId` - Foreign key to minisites table (VARCHAR(32))
3. `authorName` - Reviewer name (VARCHAR(160))
4. `authorEmail` - Optional email (VARCHAR(255), nullable)
5. `authorPhone` - Optional phone (VARCHAR(20), nullable)
6. `authorUrl` - Optional reviewer website (VARCHAR(300), nullable)
7. `rating` - 1-5 stars (DECIMAL(2,1))
8. `body` - Review text (MEDIUMTEXT)
9. `language` - Auto-detected language (VARCHAR(10), nullable)
   - Extracted from locale (e.g., 'en-US' -> 'en')
10. `locale` - Reviewer's locale (VARCHAR(10), nullable)
    - Format: 'en-US', 'en-GB', 'en-IN', 'en-AU', etc.
11. `visitedMonth` - Visit month in YYYY-MM format (CHAR(7), nullable)
12. `status` - Review status (ENUM: 'pending', 'approved', 'rejected', 'flagged')
13. `source` - Review source (ENUM: 'manual', 'google', 'yelp', 'facebook', 'other')
14. `sourceId` - External source ID (VARCHAR(160), nullable)

### Verification Fields
15. `isEmailVerified` - Email verified flag (BOOLEAN, default: false)
16. `isPhoneVerified` - Phone verified flag (BOOLEAN, default: false)

### Engagement & Quality Metrics
17. `helpfulCount` - Helpful votes count (INT, default: 0)
18. `spamScore` - Auto-calculated spam probability (DECIMAL(3,2), nullable, 0-1)
19. `sentimentScore` - Auto-calculated sentiment (DECIMAL(3,2), nullable, -1 to +1)

### Display & Sorting
20. `displayOrder` - Manual sorting for featured reviews (INT, nullable)
21. `publishedAt` - When review was approved/published (DATETIME, nullable)

### Moderation Tracking
22. `moderationReason` - Why rejected/flagged (VARCHAR(200), nullable)
23. `moderatedBy` - User ID who moderated (BIGINT UNSIGNED, nullable)

### Timestamps & Audit
24. `createdAt` - When submitted (DATETIME, DEFAULT CURRENT_TIMESTAMP)
25. `updatedAt` - Last update (DATETIME, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
26. `createdBy` - User ID who created (BIGINT UNSIGNED, nullable)
    - NULL for anonymous submissions
    - User ID if registered user

## Sample Data Structure

### Test Minisites
- **ACME Dental** (Dallas) - 5 reviews
- **Lotus Textiles** (Mumbai) - 5 reviews
- **Green Bites** (London) - 5 reviews
- **Swift Transit** (Sydney) - 5 reviews

**Total: 20 sample reviews**

### Review Data Populated
Each review includes:
- Author name, rating (4.5-5.0), body text
- Locale (en-US, en-IN, en-GB, en-AU)
- Language (auto-detected: 'en')
- Visited month (current month in YYYY-MM)
- Status: 'approved'
- Source: 'manual'
- Verification flags: `isEmailVerified = false`, `isPhoneVerified = false`
- Engagement metrics: `helpfulCount = 0`
- All timestamps: `createdAt`, `updatedAt`, `publishedAt`
- `createdBy`: Current user ID (if logged in)

## Testing

### Unit Tests
- **File**: `tests/Unit/Infrastructure/Persistence/Repositories/ReviewRepositoryTest.php`
- Tests all repository methods with mocks
- Includes `LoggingServiceProvider` initialization

### Integration Tests
- **File**: `tests/Integration/Infrastructure/Persistence/Doctrine/ReviewRepositoryIntegrationTest.php`
- Tests against real MySQL database
- Tests: save, find, delete, list by status, count by status
- Covers all 24 MVP fields

### Migration Tests
- **File**: `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251104000000Test.php`
- Tests table creation with all columns
- Tests `up()` and `down()` methods
- Verifies idempotency

## Current Status

### ✅ Completed
- [x] Review entity with Doctrine ORM mappings (24 MVP fields)
- [x] ReviewRepository interface and Doctrine implementation
- [x] Database migration (Version20251104000000)
- [x] ReviewSeederService with all fields populated
- [x] Legacy code commented out in CreateBase
- [x] Integration points updated (WordPressMinisiteManager, TimberRenderer)
- [x] ReviewRepository initialized in PluginBootstrap
- [x] Unit and integration tests
- [x] Migration tests

### 🚧 Pending
- [ ] Remove old commented code from `_1_0_0_CreateBase.php` (after confirmation)
- [ ] Create admin UI for review management
- [ ] Build review submission form
- [ ] Implement review moderation interface
- [ ] Add review display templates (Twig/Timber)
- [ ] Implement spam detection (spam_score calculation)
- [ ] Implement sentiment analysis (sentiment_score calculation)
- [ ] Add review verification workflow (email/phone)
- [ ] Build review analytics dashboard

## Technical Decisions

1. **Doctrine ORM over wpdb**: Type safety, entity management, easier testing
2. **Repository Pattern**: Separation of concerns, easier to mock/test
3. **Schema API for Migrations**: Consistent with config table migration
4. **Global Repository Access**: `$GLOBALS['minisite_review_repository']` for easy WordPress integration
5. **Explicit Field Setting**: All 24 fields set explicitly in seeder (no reliance on defaults)
6. **String Status**: Using string instead of enum for flexibility (ENUM at DB level)

## Files Changed/Created

### New Files
- `src/Domain/Entities/Review.php`
- `src/Infrastructure/Persistence/Repositories/ReviewRepositoryInterface.php`
- `src/Infrastructure/Persistence/Repositories/ReviewRepository.php`
- `src/Domain/Services/ReviewSeederService.php`
- `src/Infrastructure/Migrations/Doctrine/Version20251104000000.php`
- `tests/Unit/Infrastructure/Persistence/Repositories/ReviewRepositoryTest.php`
- `tests/Integration/Infrastructure/Persistence/Doctrine/ReviewRepositoryIntegrationTest.php`
- `tests/Integration/Infrastructure/Migrations/Doctrine/Version20251104000000Test.php`

### Modified Files
- `src/Core/PluginBootstrap.php` - Added ReviewRepository initialization
- `src/Features/MinisiteViewer/WordPress/WordPressMinisiteManager.php` - Updated to use ReviewRepository
- `src/Application/Rendering/TimberRenderer.php` - Updated to use ReviewRepository
- `src/Infrastructure/Versioning/Migrations/_1_0_0_CreateBase.php` - Commented out old review code

## Next Steps

1. **Review Submission Form**
   - Create public-facing form for submitting reviews
   - Validate input (rating 1-5, required fields)
   - Store as 'pending' status initially
   - Send notification to admin for moderation

2. **Moderation Interface**
   - Admin dashboard to view pending reviews
   - Approve/reject/flag actions
   - Add moderation reason
   - Track moderator user ID

3. **Review Display**
   - Update Twig templates to show reviews
   - Sort by displayOrder, then publishedAt
   - Show verified badges if email/phone verified
   - Display helpful count
   - Show moderation transparency (if rejected, show reason)

4. **Analytics & Quality**
   - Implement spam detection algorithm (spam_score)
   - Implement sentiment analysis (sentiment_score)
   - Review analytics dashboard (ratings distribution, trends)
   - Review quality metrics

5. **Verification Workflow**
   - Email verification flow
   - Phone verification flow (SMS)
   - Update verification flags after confirmation

6. **Cleanup**
   - Remove old commented code from CreateBase
   - Remove old SQL file (`data/db/tables/minisite_reviews.sql`)
   - Archive old integration tests if any

## Acceptance Criteria

- [ ] All 24 MVP fields are properly mapped in Review entity
- [ ] ReviewRepository implements all interface methods
- [ ] Migration creates table with all columns correctly
- [ ] ReviewSeederService populates all 24 fields
- [ ] Sample data (20 reviews) can be seeded successfully
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Migration tests pass
- [ ] Code style checks pass
- [ ] Static analysis passes
- [ ] No legacy wpdb code in active use for reviews

## Related Documentation
- `docs/features/reviews/review-entity-revised-recommendations.md` - Original field specifications
- `src/Domain/Entities/Review.php` - Entity implementation
- `src/Infrastructure/Persistence/Repositories/ReviewRepository.php` - Repository implementation

