<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Doctrine;

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
 * Integration tests for ReviewRepository
 * 
 * Tests ReviewRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisite_reviews table.
 * 
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Base table must exist (created by custom migration system)
 * - Migrations will ensure new columns exist
 */
#[CoversClass(ReviewRepository::class)]
final class ReviewRepositoryIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ReviewRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize LoggingServiceProvider (required by ReviewRepository)
        LoggingServiceProvider::register();
        
        // Get database configuration from environment (same as AbstractDoctrineMigrationTest)
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';
        
        // Create real MySQL connection via Doctrine
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ]);
        
        // Create EntityManager with MySQL connection
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__ . '/../../../../src/Domain/Entities',
                __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',
            ],
            isDevMode: true
        );
        
        $this->em = new EntityManager($connection, $config);
        
        // Reset connection state to ensure clean transaction state
        // This prevents savepoint/transaction errors from previous tests
        try {
            // Clear any existing savepoints and transactions by executing ROLLBACK
            // This is safe even if no transaction is active
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore - connection might already be clean or ROLLBACK might not be needed
        }
        
        // Ensure connection is ready for new operations
        try {
            // Reset any savepoint counter by starting and immediately committing a transaction
            $connection->beginTransaction();
            $connection->commit();
        } catch (\Exception $e) {
            // If this fails, try to rollback
            try {
                $connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore - just continue
            }
        }
        
        // Clear any UnitOfWork state
        $this->em->clear();
        
        // Set up $wpdb object for TablePrefixListener (needed for prefix)
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';
        
        // Add TablePrefixListener (required for wp_minisite_reviews table)
        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );
        
        // Drop tables and migration tracking to ensure clean slate
        // This ensures migrations will run fresh every time
        $this->cleanupTables();
        
        // Ensure migrations have run (table and new columns exist)
        // Now that tables are dropped, migrations will run fresh
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();
        
        // Reset connection state again after migrations (migrations may leave connection in bad state)
        // This is critical because migrations might leave the connection in an inconsistent state
        try {
            // Rollback any active transactions
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (\Exception $e) {
            // If rollback fails, try to execute a direct ROLLBACK
            try {
                $connection->executeStatement('ROLLBACK');
            } catch (\Exception $e2) {
                // Ignore - connection might already be clean
            }
        }
        
        // Clear EntityManager state again after migrations
        // This ensures any UnitOfWork state from migrations is cleared
        $this->em->clear();
        
        // Force a fresh connection state by closing and letting it reconnect
        // This is the most reliable way to ensure clean state
        try {
            $connection->close();
        } catch (\Exception $e) {
            // Ignore - connection might already be closed
        }
        
        // EntityManager will automatically reconnect when needed
        
        // Create ReviewRepository instance directly (same pattern as ConfigRepository)
        $this->repository = new ReviewRepository(
            $this->em,
            $this->em->getClassMetadata(Review::class)
        );
        
        // Clean up test data (but keep table structure)
        $this->cleanupTestData();
    }
    
    protected function tearDown(): void
    {
        // Clean up test data (but keep table structure)
        $this->cleanupTestData();
        
        $this->em->close();
        parent::tearDown();
    }
    
    /**
     * Drop tables and migration tracking to ensure clean slate
     * This ensures migrations can run fresh before each test
     */
    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = ['wp_minisite_reviews', 'wp_minisite_migrations'];
        
        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            } catch (\Exception $e) {
                // Ignore errors - table might not exist
            }
        }
    }
    
    /**
     * Clean up test data (but keep table structure)
     * Deletes only test reviews, not the table itself
     */
    private function cleanupTestData(): void
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
}

