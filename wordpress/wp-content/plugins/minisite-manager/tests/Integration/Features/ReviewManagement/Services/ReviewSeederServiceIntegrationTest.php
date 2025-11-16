<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ReviewManagement\Services;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepository;
use Minisite\Features\ReviewManagement\Services\ReviewSeederService;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ReviewSeederService
 *
 * Tests ReviewSeederService against real MySQL database with WordPress prefix.
 * This tests the actual seeding operations and JSON file loading.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - JSON files in data/json/reviews/ directory (optional, some tests will skip if missing)
 */
#[CoversClass(ReviewSeederService::class)]
final class ReviewSeederServiceIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ReviewRepository $repository;
    private ReviewSeederService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider
        LoggingServiceProvider::register();

        // Get database configuration from environment
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        // Create real MySQL connection
        $connection = DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ));

        // Create EntityManager
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(
                __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
            ),
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

        // Set up $wpdb object for TablePrefixListener
        if (! isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';

        // Add TablePrefixListener
        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        // Ensure migrations have run
        $this->cleanupTables();
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Create repository and service
        $this->repository = new ReviewRepository(
            $this->em,
            $this->em->getClassMetadata(Review::class)
        );

        $this->service = new ReviewSeederService($this->repository);

        // Clean up test data
        $this->cleanupTestData();

        // Set up WordPress function mocks
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();

        // Clean up global mocks
        unset($GLOBALS['_test_mock_get_current_user_id']);

        $this->em->close();
        parent::tearDown();
    }

    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = array('wp_minisite_reviews', 'wp_minisite_migrations');

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
                "DELETE FROM wp_minisite_reviews WHERE minisite_id LIKE 'test_%' OR minisite_id = 'test-minisite%'"
            );
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Test insertReview creates review with all fields
     */
    public function test_insertReview_creates_review_with_all_fields(): void
    {
        $review = $this->service->insertReview(
            'test-minisite-insert',
            'Test User',
            4.5,
            'Great service!',
            'en-US',
            'test@example.com',
            '+1234567890',
            'https://example.com',
            1
        );

        $this->assertNotNull($review->id);
        $this->assertEquals('test-minisite-insert', $review->minisiteId);
        $this->assertEquals('Test User', $review->authorName);
        $this->assertEquals(4.5, $review->rating);
        $this->assertEquals('Great service!', $review->body);
        $this->assertEquals('en', $review->language);
        $this->assertEquals('en-US', $review->locale);
        $this->assertEquals('test@example.com', $review->authorEmail);
        $this->assertEquals('+1234567890', $review->authorPhone);
        $this->assertEquals('https://example.com', $review->authorUrl);
        $this->assertEquals(1, $review->displayOrder);
        $this->assertEquals('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
        $this->assertNotNull($review->createdAt);
        $this->assertNotNull($review->updatedAt);

        // Verify persisted
        $found = $this->repository->findById($review->id);
        $this->assertNotNull($found);
        $this->assertEquals('test@example.com', $found->authorEmail);
    }

    /**
     * Test insertReview with logged in user
     */
    public function test_insertReview_with_logged_in_user(): void
    {
        $GLOBALS['_test_mock_get_current_user_id'] = 42;

        // Recreate service to pick up new user ID
        $this->service = new ReviewSeederService($this->repository);

        $review = $this->service->insertReview(
            'test-minisite-user',
            'Logged In User',
            5.0,
            'Great!'
        );

        $this->assertEquals(42, $review->createdBy);
        $this->assertEquals(42, $review->moderatedBy);

        // Verify persisted
        $found = $this->repository->findById($review->id);
        $this->assertEquals(42, $found->createdBy);
        $this->assertEquals(42, $found->moderatedBy);
    }

    /**
     * Test insertReview with optional fields
     */
    public function test_insertReview_with_optional_fields(): void
    {
        $review = $this->service->insertReview(
            'test-minisite-optional',
            'Optional Fields User',
            4.0,
            'Test review',
            'en-GB',
            'optional@example.com',
            '+9876543210',
            'https://optional.com',
            5
        );

        $this->assertEquals('optional@example.com', $review->authorEmail);
        $this->assertEquals('+9876543210', $review->authorPhone);
        $this->assertEquals('https://optional.com', $review->authorUrl);
        $this->assertEquals(5, $review->displayOrder);
        $this->assertEquals('en-GB', $review->locale);
        $this->assertEquals('en', $review->language);
    }

    /**
     * Test insertReview auto-detects language from locale
     */
    public function test_insertReview_auto_detects_language_from_locale(): void
    {
        $review1 = $this->service->insertReview(
            'test-minisite-lang1',
            'User 1',
            4.0,
            'Test',
            'hi-IN'
        );
        $this->assertEquals('hi', $review1->language);
        $this->assertEquals('hi-IN', $review1->locale);

        $review2 = $this->service->insertReview(
            'test-minisite-lang2',
            'User 2',
            4.0,
            'Test',
            'fr-FR'
        );
        $this->assertEquals('fr', $review2->language);
        $this->assertEquals('fr-FR', $review2->locale);
    }

    /**
     * Test createReviewFromJsonData with all fields
     */
    public function test_createReviewFromJsonData_with_all_fields(): void
    {
        $reviewData = array(
            'authorName' => 'JSON User',
            'authorEmail' => 'json@example.com',
            'authorPhone' => '+1111111111',
            'authorUrl' => 'https://json.com',
            'rating' => 5.0,
            'body' => 'JSON review',
            'language' => 'en',
            'locale' => 'en-US',
            'visitedMonth' => '2025-01',
            'source' => 'google',
            'sourceId' => 'g-123',
            'isEmailVerified' => true,
            'isPhoneVerified' => false,
            'helpfulCount' => 10,
            'spamScore' => 0.1,
            'sentimentScore' => 0.9,
            'displayOrder' => 1,
            'status' => 'approved',
            'moderationReason' => null,
            'moderatedBy' => 5,
            'createdAt' => '2025-01-01T00:00:00Z',
            'updatedAt' => '2025-01-02T00:00:00Z',
            'createdBy' => 10,
            'publishedAt' => '2025-01-03T00:00:00Z',
        );

        $review = $this->service->createReviewFromJsonData('test-minisite-json', $reviewData);

        $this->assertEquals('JSON User', $review->authorName);
        $this->assertEquals('json@example.com', $review->authorEmail);
        $this->assertEquals(5.0, $review->rating);
        $this->assertEquals('en', $review->language);
        $this->assertEquals('google', $review->source);
        $this->assertEquals('g-123', $review->sourceId);
        $this->assertTrue($review->isEmailVerified);
        $this->assertEquals(10, $review->helpfulCount);
        $this->assertEquals(0.1, $review->spamScore);
        $this->assertEquals(0.9, $review->sentimentScore);
        $this->assertEquals(1, $review->displayOrder);
        $this->assertEquals(5, $review->moderatedBy);
        $this->assertEquals(10, $review->createdBy);
        $this->assertInstanceOf(\DateTimeImmutable::class, $review->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $review->publishedAt);
    }

    /**
     * Test createReviewFromJsonData with defaults
     */
    public function test_createReviewFromJsonData_with_defaults(): void
    {
        $reviewData = array(
            'authorName' => 'Minimal User',
            'body' => 'Minimal review',
        );

        $review = $this->service->createReviewFromJsonData('test-minisite-minimal', $reviewData);

        $this->assertEquals(5.0, $review->rating); // Default
        $this->assertEquals('en-US', $review->locale); // Default
        $this->assertEquals('en', $review->language); // Auto-detected
        $this->assertEquals('manual', $review->source); // Default
        $this->assertFalse($review->isEmailVerified); // Default
        $this->assertFalse($review->isPhoneVerified); // Default
        $this->assertEquals(0, $review->helpfulCount); // Default
        $this->assertEquals('approved', $review->status); // Default
    }

    /**
     * Test createReviewFromJsonData parses timestamps
     */
    public function test_createReviewFromJsonData_parses_timestamps(): void
    {
        $reviewData = array(
            'authorName' => 'Timestamp User',
            'body' => 'Test',
            'createdAt' => '2025-01-15T10:30:00Z',
            'updatedAt' => '2025-01-16T11:45:00Z',
            'publishedAt' => '2025-01-17T12:00:00Z',
        );

        $review = $this->service->createReviewFromJsonData('test-minisite-timestamp', $reviewData);

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->createdAt);
        $this->assertEquals('2025-01-15 10:30:00', $review->createdAt->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->updatedAt);
        $this->assertEquals('2025-01-16 11:45:00', $review->updatedAt->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->publishedAt);
        $this->assertEquals('2025-01-17 12:00:00', $review->publishedAt->format('Y-m-d H:i:s'));
    }

    /**
     * Test createReviewFromJsonData marks as published when approved
     */
    public function test_createReviewFromJsonData_marks_as_published_when_approved(): void
    {
        $reviewData = array(
            'authorName' => 'Approved User',
            'body' => 'Test',
            'status' => 'approved',
        );

        $review = $this->service->createReviewFromJsonData('test-minisite-approved', $reviewData);

        $this->assertEquals('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
    }

    /**
     * Test seedReviewsForMinisite seeds multiple reviews
     */
    public function test_seedReviewsForMinisite_seeds_multiple_reviews(): void
    {
        $reviewData = array(
            array(
                'authorName' => 'User 1',
                'rating' => 5.0,
                'body' => 'Review 1',
            ),
            array(
                'authorName' => 'User 2',
                'rating' => 4.0,
                'body' => 'Review 2',
            ),
            array(
                'authorName' => 'User 3',
                'rating' => 3.0,
                'body' => 'Review 3',
            ),
        );

        $this->service->seedReviewsForMinisite('test-minisite-seed', $reviewData);

        // Verify reviews were saved
        $reviews = $this->repository->listApprovedForMinisite('test-minisite-seed');
        $this->assertGreaterThanOrEqual(3, count($reviews));

        // Verify all reviews have correct minisite ID
        foreach ($reviews as $review) {
            $this->assertEquals('test-minisite-seed', $review->minisiteId);
        }
    }

    /**
     * Test seedReviewsForMinisite with empty array
     */
    public function test_seedReviewsForMinisite_with_empty_array(): void
    {
        // Should not throw an exception
        $this->service->seedReviewsForMinisite('test-minisite-empty', array());

        $reviews = $this->repository->listApprovedForMinisite('test-minisite-empty');
        $this->assertCount(0, $reviews);
    }

    /**
     * Test seedAllSampleReviews with empty minisite IDs (should skip)
     */
    public function test_seedAllSampleReviews_with_empty_minisite_ids(): void
    {
        // Should not throw an exception when all minisite IDs are empty
        $this->service->seedAllSampleReviews(array());

        // Also test with some keys but empty values
        $this->service->seedAllSampleReviews(array(
            'ACME' => '',
            'LOTUS' => null,
            'GREEN' => '',
            'SWIFT' => '',
        ));

        // Verify no reviews were created (would need to check all minisites, but we'll just verify no exception)
        $this->assertTrue(true);
    }

    /**
     * Test seedAllSampleReviews handles file not found gracefully
     */
    public function test_seedAllSampleReviews_handles_file_not_found(): void
    {
        // Define MINISITE_PLUGIN_DIR to a non-existent path
        if (! defined('MINISITE_PLUGIN_DIR')) {
            define('MINISITE_PLUGIN_DIR', '/nonexistent/path/');
        }

        // Should not throw exception, should log error and continue
        $this->service->seedAllSampleReviews(array(
            'ACME' => 'test-minisite-acme',
            'LOTUS' => 'test-minisite-lotus',
        ));

        // Verify no exception was thrown (error should be logged)
        $this->assertTrue(true);
    }
}
