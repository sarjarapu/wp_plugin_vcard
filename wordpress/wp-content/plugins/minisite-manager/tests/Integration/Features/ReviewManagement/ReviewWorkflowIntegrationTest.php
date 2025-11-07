<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ReviewManagement;

use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for Review Management Workflow
 *
 * Tests end-to-end review workflows:
 * - Complete review lifecycle (create → find → update → delete)
 * - Review moderation workflow (status changes)
 * - Review listing and filtering
 * - Review statistics
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 */
#[CoversClass(Review::class)]
#[CoversClass(ReviewRepository::class)]
final class ReviewWorkflowIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ReviewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        LoggingServiceProvider::register();

        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ]);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
            ],
            isDevMode: true
        );

        $this->em = new EntityManager($connection, $config);

        // Reset connection state
        try {
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $connection->beginTransaction();
            $connection->commit();
        } catch (\Exception $e) {
            try {
                $connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();

        // Set up $wpdb object
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';

        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        $this->cleanupTables();
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Reset connection state after migrations
        try {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (\Exception $e) {
            try {
                $connection->executeStatement('ROLLBACK');
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();

        // Force a fresh connection state by closing and letting it reconnect
        // This is the most reliable way to ensure clean state and clear all savepoints
        try {
            $connection->close();
        } catch (\Exception $e) {
            // Ignore - connection might already be closed
        }

        // EntityManager will automatically reconnect when needed

        $this->repository = new ReviewRepository(
            $this->em,
            $this->em->getClassMetadata(Review::class)
        );

        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->em->close();
        parent::tearDown();
    }

    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = ['wp_minisite_reviews', 'wp_minisite_migrations'];

        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }

    private function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_reviews WHERE minisite_id LIKE 'test_%' OR minisite_id LIKE 'test-minisite%'"
            );
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Test complete review lifecycle: create → find → update → delete
     */
    public function test_review_lifecycle_from_creation_to_deletion(): void
    {
        $minisiteId = 'test-minisite-lifecycle';

        // 1. Create review
        $review = new Review();
        $review->minisiteId = $minisiteId;
        $review->authorName = 'Lifecycle User';
        $review->rating = 4.0;
        $review->body = 'Initial review';
        $review->status = 'pending';

        $saved = $this->repository->save($review);
        $reviewId = $saved->id;
        $this->assertNotNull($reviewId);

        // 2. Find review
        $found = $this->repository->findById($reviewId);
        $this->assertNotNull($found);
        $this->assertEquals('pending', $found->status);
        $this->assertEquals('Lifecycle User', $found->authorName);

        // 3. Update review
        usleep(100000); // Small delay
        $found->authorName = 'Updated User';
        $found->rating = 5.0;
        $found->body = 'Updated review';
        $found->status = 'approved';
        $found->markAsPublished(1);

        $updated = $this->repository->save($found);
        $this->assertEquals($reviewId, $updated->id);
        $this->assertEquals('Updated User', $updated->authorName);
        $this->assertEquals(5.0, $updated->rating);
        $this->assertEquals('approved', $updated->status);

        // 4. Delete review
        $this->repository->delete($updated);

        $deleted = $this->repository->findById($reviewId);
        $this->assertNull($deleted);
    }

    /**
     * Test review moderation workflow: pending → approved → flagged → rejected
     */
    public function test_review_moderation_workflow(): void
    {
        $minisiteId = 'test-minisite-moderation';

        // Create pending review
        $review = new Review();
        $review->minisiteId = $minisiteId;
        $review->authorName = 'Moderation User';
        $review->rating = 4.0;
        $review->body = 'Pending review';
        $review->status = 'pending';

        $saved = $this->repository->save($review);
        $this->assertEquals('pending', $saved->status);
        $this->assertNull($saved->publishedAt);

        // Approve review
        usleep(100000);
        $saved->markAsPublished(1);
        $approved = $this->repository->save($saved);
        $this->assertEquals('approved', $approved->status);
        $this->assertNotNull($approved->publishedAt);
        $this->assertEquals(1, $approved->moderatedBy);

        // Flag review
        usleep(100000);
        $approved->markAsFlagged('Needs review', 2);
        $flagged = $this->repository->save($approved);
        $this->assertEquals('flagged', $flagged->status);
        $this->assertEquals('Needs review', $flagged->moderationReason);
        $this->assertEquals(2, $flagged->moderatedBy);

        // Reject review
        usleep(100000);
        $flagged->markAsRejected('Inappropriate content', 2);
        $rejected = $this->repository->save($flagged);
        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Inappropriate content', $rejected->moderationReason);
        $this->assertEquals(2, $rejected->moderatedBy);

        // Verify persisted
        $found = $this->repository->findById($rejected->id);
        $this->assertEquals('rejected', $found->status);
        $this->assertEquals('Inappropriate content', $found->moderationReason);
    }

    /**
     * Test review listing and filtering with various statuses
     */
    public function test_review_listing_and_filtering(): void
    {
        $minisiteId = 'test-minisite-filter';

        // Create reviews with different statuses
        $statuses = ['pending', 'approved', 'rejected', 'flagged'];
        foreach ($statuses as $status) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "User {$status}";
            $review->rating = 4.0;
            $review->body = "Review with status {$status}";
            $review->status = $status;
            if ($status === 'approved') {
                $review->markAsPublished();
            }
            $this->repository->save($review);
        }

        // Test filtering by each status
        foreach ($statuses as $status) {
            $results = $this->repository->listByStatusForMinisite($minisiteId, $status);
            $this->assertGreaterThanOrEqual(1, count($results));
            foreach ($results as $result) {
                $this->assertEquals($status, $result->status);
                $this->assertEquals($minisiteId, $result->minisiteId);
            }
        }

        // Test approved reviews only
        $approved = $this->repository->listApprovedForMinisite($minisiteId);
        $this->assertGreaterThanOrEqual(1, count($approved));
        foreach ($approved as $review) {
            $this->assertEquals('approved', $review->status);
        }
    }

    /**
     * Test review statistics for minisite
     */
    public function test_review_statistics_for_minisite(): void
    {
        $minisiteId = 'test-minisite-stats';

        // Create 3 approved reviews
        for ($i = 0; $i < 3; $i++) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "Approved User $i";
            $review->rating = 4.0 + ($i * 0.5);
            $review->body = "Approved review $i";
            $review->status = 'approved';
            $review->markAsPublished();
            $this->repository->save($review);
        }

        // Create 2 pending reviews
        for ($i = 0; $i < 2; $i++) {
            $review = new Review();
            $review->minisiteId = $minisiteId;
            $review->authorName = "Pending User $i";
            $review->rating = 3.0;
            $review->body = "Pending review $i";
            $review->status = 'pending';
            $this->repository->save($review);
        }

        // Create 1 rejected review
        $review = new Review();
        $review->minisiteId = $minisiteId;
        $review->authorName = 'Rejected User';
        $review->rating = 1.0;
        $review->body = 'Rejected review';
        $review->status = 'rejected';
        $this->repository->save($review);

        // Test counts
        $approvedCount = $this->repository->countByStatusForMinisite($minisiteId, 'approved');
        $this->assertGreaterThanOrEqual(3, $approvedCount);

        $pendingCount = $this->repository->countByStatusForMinisite($minisiteId, 'pending');
        $this->assertGreaterThanOrEqual(2, $pendingCount);

        $rejectedCount = $this->repository->countByStatusForMinisite($minisiteId, 'rejected');
        $this->assertGreaterThanOrEqual(1, $rejectedCount);

        $flaggedCount = $this->repository->countByStatusForMinisite($minisiteId, 'flagged');
        $this->assertGreaterThanOrEqual(0, $flaggedCount);
    }
}

