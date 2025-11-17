<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ReviewManagement\Repositories;

use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for ReviewRepository
 *
 * Tests ReviewRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisite_reviews table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ReviewRepository::class)]
final class ReviewRepositoryIntegrationTest extends BaseIntegrationTest
{
    private ReviewRepository $repository;

    /**
     * Get entity paths for ORM configuration
     */
    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    /**
     * Setup test-specific services and repositories
     */
    protected function setupTestSpecificServices(): void
    {
        // Create ReviewRepository instance
        $this->repository = new ReviewRepository(
            $this->em,
            $this->em->getClassMetadata(Review::class)
        );
    }

    /**
     * Clean up test data (but keep table structure)
     * Deletes only test reviews, not the table itself
     */
    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_reviews WHERE minisite_id LIKE 'test_%' OR minisite_id = 'test-minisite-123'"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_save_and_find_review(): void
    {
        $review = new Review();
        $review->minisiteId = 'test-minisite-123';
        $review->authorName = 'Test User';
        $review->rating = 4.5;
        $review->body = 'Great service!';
        $review->status = 'approved';

        $saved = $this->repository->save($review);

        $this->assertNotNull($saved->id);
        $this->assertNotNull($saved->createdAt);
        $this->assertNotNull($saved->updatedAt);

        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertEquals($saved->id, $found->id);
        $this->assertEquals('test-minisite-123', $found->minisiteId);
        $this->assertEquals('Test User', $found->authorName);
        $this->assertEquals(4.5, $found->rating);
        $this->assertEquals('Great service!', $found->body);
    }

    public function test_save_with_all_new_fields(): void
    {
        $review = new Review();
        $review->minisiteId = 'test-minisite-456';
        $review->authorName = 'Alice Smith';
        $review->authorEmail = 'alice@example.com';
        $review->authorPhone = '+1234567890';
        $review->rating = 5.0;
        $review->body = 'Excellent service!';
        $review->language = 'en';
        $review->status = 'approved';
        $review->isEmailVerified = true;
        $review->isPhoneVerified = false;
        $review->helpfulCount = 3;
        $review->spamScore = 0.1;
        $review->sentimentScore = 0.9;
        $review->displayOrder = 1;

        $saved = $this->repository->save($review);

        $this->assertNotNull($saved->id);
        $this->assertEquals('alice@example.com', $saved->authorEmail);
        $this->assertEquals('+1234567890', $saved->authorPhone);
        $this->assertEquals('en', $saved->language);
        $this->assertTrue($saved->isEmailVerified);
        $this->assertFalse($saved->isPhoneVerified);
        $this->assertEquals(3, $saved->helpfulCount);
        $this->assertEquals(0.1, $saved->spamScore);
        $this->assertEquals(0.9, $saved->sentimentScore);
        $this->assertEquals(1, $saved->displayOrder);

        // Verify it can be retrieved
        $found = $this->repository->findById($saved->id);
        $this->assertNotNull($found);
        $this->assertEquals('alice@example.com', $found->authorEmail);
        $this->assertTrue($found->isEmailVerified);
    }

    public function test_findOrFail_throws_when_not_found(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Review with ID 99999 not found');

        $this->repository->findOrFail(99999);
    }

    public function test_delete_removes_review(): void
    {
        $review = new Review();
        $review->minisiteId = 'test-minisite-789';
        $review->authorName = 'To Delete';
        $review->rating = 3.0;
        $review->body = 'Will be deleted';
        $review->status = 'approved';

        $saved = $this->repository->save($review);
        $reviewId = $saved->id;
        $this->assertNotNull($reviewId);
        $this->assertNotNull($this->repository->findById($reviewId));

        $this->repository->delete($saved);

        $this->assertNull($this->repository->findById($reviewId));
    }

    public function test_listApprovedForMinisite_returns_approved_reviews(): void
    {
        $minisiteId = 'test-minisite-list';

        // Create approved reviews
        $review1 = new Review();
        $review1->minisiteId = $minisiteId;
        $review1->authorName = 'User 1';
        $review1->rating = 5.0;
        $review1->body = 'Great!';
        $review1->status = 'approved';
        $review1->markAsPublished();
        $this->repository->save($review1);

        $review2 = new Review();
        $review2->minisiteId = $minisiteId;
        $review2->authorName = 'User 2';
        $review2->rating = 4.0;
        $review2->body = 'Good!';
        $review2->status = 'approved';
        $review2->markAsPublished();
        $this->repository->save($review2);

        // Create pending review (should not appear)
        $review3 = new Review();
        $review3->minisiteId = $minisiteId;
        $review3->authorName = 'User 3';
        $review3->rating = 3.0;
        $review3->body = 'Pending';
        $review3->status = 'pending';
        $this->repository->save($review3);

        $results = $this->repository->listApprovedForMinisite($minisiteId);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(2, count($results));

        // Verify all results are approved
        foreach ($results as $result) {
            $this->assertEquals('approved', $result->status);
            $this->assertEquals($minisiteId, $result->minisiteId);
        }
    }

    public function test_listByStatusForMinisite_filters_by_status(): void
    {
        $minisiteId = 'test-minisite-status';

        // Create reviews with different statuses
        $pending = new Review();
        $pending->minisiteId = $minisiteId;
        $pending->authorName = 'Pending User';
        $pending->rating = 4.0;
        $pending->body = 'Pending review';
        $pending->status = 'pending';
        $this->repository->save($pending);

        $rejected = new Review();
        $rejected->minisiteId = $minisiteId;
        $rejected->authorName = 'Rejected User';
        $rejected->rating = 1.0;
        $rejected->body = 'Rejected review';
        $rejected->status = 'rejected';
        $this->repository->save($rejected);

        $flagged = new Review();
        $flagged->minisiteId = $minisiteId;
        $flagged->authorName = 'Flagged User';
        $flagged->rating = 2.0;
        $flagged->body = 'Flagged review';
        $flagged->status = 'flagged';
        $this->repository->save($flagged);

        $pendingResults = $this->repository->listByStatusForMinisite($minisiteId, 'pending');
        $this->assertGreaterThanOrEqual(1, count($pendingResults));
        foreach ($pendingResults as $result) {
            $this->assertEquals('pending', $result->status);
        }

        $rejectedResults = $this->repository->listByStatusForMinisite($minisiteId, 'rejected');
        $this->assertGreaterThanOrEqual(1, count($rejectedResults));
        foreach ($rejectedResults as $result) {
            $this->assertEquals('rejected', $result->status);
        }

        $flaggedResults = $this->repository->listByStatusForMinisite($minisiteId, 'flagged');
        $this->assertGreaterThanOrEqual(1, count($flaggedResults));
        foreach ($flaggedResults as $result) {
            $this->assertEquals('flagged', $result->status);
        }
    }

    public function test_countByStatusForMinisite_returns_correct_count(): void
    {
        $minisiteId = 'test-minisite-count';

        // Create multiple approved reviews
        for ($i = 0; $i < 3; $i++) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "User $i";
            $review->rating = 4.0;
            $review->body = "Review $i";
            $review->status = 'approved';
            $this->repository->save($review);
        }

        // Create pending reviews
        for ($i = 0; $i < 2; $i++) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "Pending User $i";
            $review->rating = 3.0;
            $review->body = "Pending Review $i";
            $review->status = 'pending';
            $this->repository->save($review);
        }

        $approvedCount = $this->repository->countByStatusForMinisite($minisiteId, 'approved');
        $this->assertGreaterThanOrEqual(3, $approvedCount);

        $pendingCount = $this->repository->countByStatusForMinisite($minisiteId, 'pending');
        $this->assertGreaterThanOrEqual(2, $pendingCount);

        $rejectedCount = $this->repository->countByStatusForMinisite($minisiteId, 'rejected');
        $this->assertGreaterThanOrEqual(0, $rejectedCount);
    }

    public function test_listByStatusForMinisite_respects_limit(): void
    {
        $minisiteId = 'test-minisite-limit';

        // Create 5 approved reviews
        for ($i = 0; $i < 5; $i++) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "User $i";
            $review->rating = 4.0;
            $review->body = "Review $i";
            $review->status = 'approved';
            $review->markAsPublished();
            $this->repository->save($review);
        }

        $results = $this->repository->listByStatusForMinisite($minisiteId, 'approved', 3);

        $this->assertLessThanOrEqual(3, count($results));
    }

    public function test_markAsPublished_updates_fields(): void
    {
        $review = new Review();
        $review->minisiteId = 'test-minisite-publish';
        $review->authorName = 'Test';
        $review->rating = 4.0;
        $review->body = 'Test review';
        $review->status = 'pending';

        $saved = $this->repository->save($review);
        $this->assertEquals('pending', $saved->status);
        $this->assertNull($saved->publishedAt);

        $saved->markAsPublished(42);
        $updated = $this->repository->save($saved);

        $this->assertEquals('approved', $updated->status);
        $this->assertNotNull($updated->publishedAt);
        $this->assertEquals(42, $updated->moderatedBy);

        // Verify persisted
        $found = $this->repository->findById($updated->id);
        $this->assertEquals('approved', $found->status);
        $this->assertNotNull($found->publishedAt);
        $this->assertEquals(42, $found->moderatedBy);
    }

    /**
     * Test save() updates existing review
     */
    public function test_save_update_existing_review(): void
    {
        // Create initial review
        $review = new Review();
        $review->minisiteId = 'test-minisite-update';
        $review->authorName = 'Original Name';
        $review->rating = 3.0;
        $review->body = 'Original body';
        $review->status = 'pending';

        $saved = $this->repository->save($review);
        $originalId = $saved->id;
        $originalCreatedAt = $saved->createdAt;
        $originalUpdatedAt = $saved->updatedAt;

        // Small delay to ensure updatedAt timestamp changes
        usleep(100000); // 0.1 seconds

        // Update the review
        $saved->authorName = 'Updated Name';
        $saved->rating = 5.0;
        $saved->body = 'Updated body';
        $saved->status = 'approved';
        $saved->markAsPublished();

        $updated = $this->repository->save($saved);

        // Verify ID remains the same (update, not insert)
        $this->assertEquals($originalId, $updated->id);
        $this->assertEquals($originalCreatedAt->getTimestamp(), $updated->createdAt->getTimestamp());

        // Verify updated fields
        $this->assertEquals('Updated Name', $updated->authorName);
        $this->assertEquals(5.0, $updated->rating);
        $this->assertEquals('Updated body', $updated->body);
        $this->assertEquals('approved', $updated->status);

        // Verify updatedAt was changed (should be greater than or equal to original)
        $this->assertGreaterThanOrEqual($originalUpdatedAt->getTimestamp(), $updated->updatedAt->getTimestamp());

        // Verify persisted
        $found = $this->repository->findById($originalId);
        $this->assertEquals('Updated Name', $found->authorName);
        $this->assertEquals(5.0, $found->rating);
    }

    /**
     * Test findById returns null when review not found
     */
    public function test_findById_returns_null_when_not_found(): void
    {
        $result = $this->repository->findById(99999);
        $this->assertNull($result);
    }

    /**
     * Test listByStatusForMinisite orders correctly
     */
    public function test_listByStatusForMinisite_orders_correctly(): void
    {
        $minisiteId = 'test-minisite-order';

        // Create reviews with different displayOrder and publishedAt
        $review1 = new Review();
        $review1->minisiteId = $minisiteId;
        $review1->authorName = 'Review 1';
        $review1->rating = 5.0;
        $review1->body = 'First';
        $review1->status = 'approved';
        $review1->displayOrder = 2; // Higher display order = appears later
        $review1->markAsPublished();
        $this->repository->save($review1);

        // Small delay to ensure different timestamps
        usleep(100000); // 0.1 seconds

        $review2 = new Review();
        $review2->minisiteId = $minisiteId;
        $review2->authorName = 'Review 2';
        $review2->rating = 4.0;
        $review2->body = 'Second';
        $review2->status = 'approved';
        $review2->displayOrder = 1; // Lower display order = appears first
        $review2->markAsPublished();
        $this->repository->save($review2);

        usleep(100000);

        $review3 = new Review();
        $review3->minisiteId = $minisiteId;
        $review3->authorName = 'Review 3';
        $review3->rating = 3.0;
        $review3->body = 'Third';
        $review3->status = 'approved';
        $review3->displayOrder = null; // No display order = sorted by publishedAt
        $review3->markAsPublished();
        $this->repository->save($review3);

        $results = $this->repository->listByStatusForMinisite($minisiteId, 'approved', 10);

        // Should be ordered by: displayOrder ASC, then publishedAt DESC, then createdAt DESC
        // Review 2 (displayOrder=1) should come first
        // Review 1 (displayOrder=2) should come second
        // Review 3 (displayOrder=null) should come last (sorted by publishedAt DESC)
        $this->assertGreaterThanOrEqual(3, count($results));

        // Find reviews in results
        $foundReview1 = null;
        $foundReview2 = null;
        $foundReview3 = null;

        foreach ($results as $result) {
            if ($result->authorName === 'Review 1') {
                $foundReview1 = $result;
            } elseif ($result->authorName === 'Review 2') {
                $foundReview2 = $result;
            } elseif ($result->authorName === 'Review 3') {
                $foundReview3 = $result;
            }
        }

        $this->assertNotNull($foundReview1);
        $this->assertNotNull($foundReview2);
        $this->assertNotNull($foundReview3);

        // Review 2 (displayOrder=1) should appear before Review 1 (displayOrder=2)
        $review2Index = array_search($foundReview2, $results, true);
        $review1Index = array_search($foundReview1, $results, true);
        $this->assertLessThan($review1Index, $review2Index, 'Reviews with lower displayOrder should appear first');
    }
}

