<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ReviewManagement\Services;

use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface;
use Minisite\Features\ReviewManagement\Services\ReviewSeederService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ReviewSeederService::seedAllTestReviews()
 * 
 * Tests the seedAllTestReviews method which loads JSON files and seeds reviews
 * for multiple minisites.
 */
#[CoversClass(ReviewSeederService::class)]
final class ReviewSeederServiceSeedAllTest extends TestCase
{
    private ReviewRepositoryInterface|MockObject $reviewRepository;
    private ReviewSeederService $service;
    private string $testJsonDir;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
        
        // Use global variable approach for get_current_user_id
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        
        $this->service = new ReviewSeederService($this->reviewRepository);
        
        // Create a testable subclass that can override loadReviewsFromJson
        $this->testJsonDir = sys_get_temp_dir() . '/minisite-test-reviews-' . uniqid();
        mkdir($this->testJsonDir, 0755, true);
        mkdir($this->testJsonDir . '/data/json/reviews', 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up global mocks
        unset($GLOBALS['_test_mock_get_current_user_id']);
        
        // Clean up test JSON directory
        if (is_dir($this->testJsonDir)) {
            $this->deleteDirectory($this->testJsonDir);
        }
        
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test seedAllTestReviews with all minisite IDs
     */
    public function test_seedAllTestReviews_with_all_minisite_ids(): void
    {
        // Create testable service that uses temp directory
        $service = new class($this->reviewRepository, $this->testJsonDir) extends ReviewSeederService {
            private string $testJsonDir;

            public function __construct($repository, string $testJsonDir)
            {
                parent::__construct($repository);
                $this->testJsonDir = $testJsonDir;
            }

            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = $this->testJsonDir . '/data/json/reviews/' . $jsonFile;
                
                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }
        };

        // Create test JSON files
        $testReviews = [
            ['authorName' => 'User 1', 'rating' => 5.0, 'body' => 'Great!'],
            ['authorName' => 'User 2', 'rating' => 4.0, 'body' => 'Good!']
        ];

        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/acme-dental-reviews.json',
            json_encode(['reviews' => $testReviews])
        );
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/lotus-textiles-reviews.json',
            json_encode(['reviews' => $testReviews])
        );
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/green-bites-reviews.json',
            json_encode(['reviews' => $testReviews])
        );
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/swift-transit-reviews.json',
            json_encode(['reviews' => $testReviews])
        );

        $minisiteIds = [
            'ACME' => 'test-minisite-acme',
            'LOTUS' => 'test-minisite-lotus',
            'GREEN' => 'test-minisite-green',
            'SWIFT' => 'test-minisite-swift'
        ];

        // Expect save to be called for each review in each file (4 minisites × 2 reviews = 8 calls)
        $this->reviewRepository
            ->expects($this->exactly(8))
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = rand(1, 1000);
                return $review;
            });

        $service->seedAllTestReviews($minisiteIds);
    }

    /**
     * Test seedAllTestReviews with partial minisite IDs
     */
    public function test_seedAllTestReviews_with_partial_minisite_ids(): void
    {
        $service = new class($this->reviewRepository, $this->testJsonDir) extends ReviewSeederService {
            private string $testJsonDir;

            public function __construct($repository, string $testJsonDir)
            {
                parent::__construct($repository);
                $this->testJsonDir = $testJsonDir;
            }

            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = $this->testJsonDir . '/data/json/reviews/' . $jsonFile;
                
                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }
        };

        // Create test JSON file for ACME only
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/acme-dental-reviews.json',
            json_encode(['reviews' => [['authorName' => 'User 1', 'rating' => 5.0, 'body' => 'Great!']]])
        );

        $minisiteIds = [
            'ACME' => 'test-minisite-acme',
            // Missing LOTUS, GREEN, SWIFT
        ];

        // Expect save to be called only once (ACME has 1 review)
        $this->reviewRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = rand(1, 1000);
                return $review;
            });

        $service->seedAllTestReviews($minisiteIds);
    }

    /**
     * Test seedAllTestReviews handles missing JSON files gracefully
     */
    public function test_seedAllTestReviews_handles_missing_json_files(): void
    {
        $service = new class($this->reviewRepository, $this->testJsonDir) extends ReviewSeederService {
            private string $testJsonDir;

            public function __construct($repository, string $testJsonDir)
            {
                parent::__construct($repository);
                $this->testJsonDir = $testJsonDir;
            }

            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = $this->testJsonDir . '/data/json/reviews/' . $jsonFile;
                
                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }
        };

        // Create only ACME file, others will be missing
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/acme-dental-reviews.json',
            json_encode(['reviews' => [['authorName' => 'User 1', 'rating' => 5.0, 'body' => 'Great!']]])
        );

        $minisiteIds = [
            'ACME' => 'test-minisite-acme',
            'LOTUS' => 'test-minisite-lotus', // File doesn't exist
            'GREEN' => 'test-minisite-green', // File doesn't exist
            'SWIFT' => 'test-minisite-swift'  // File doesn't exist
        ];

        // Expect save to be called only once (ACME succeeds, others fail but continue)
        $this->reviewRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = rand(1, 1000);
                return $review;
            });

        // Should not throw exception, should continue processing
        $service->seedAllTestReviews($minisiteIds);
    }

    /**
     * Test seedAllTestReviews with empty minisite IDs array
     */
    public function test_seedAllTestReviews_with_empty_array(): void
    {
        $this->reviewRepository
            ->expects($this->never())
            ->method('save');

        $this->service->seedAllTestReviews([]);
    }

    /**
     * Test seedAllTestReviews continues when one minisite fails
     */
    public function test_seedAllTestReviews_continues_when_one_fails(): void
    {
        $service = new class($this->reviewRepository, $this->testJsonDir) extends ReviewSeederService {
            private string $testJsonDir;

            public function __construct($repository, string $testJsonDir)
            {
                parent::__construct($repository);
                $this->testJsonDir = $testJsonDir;
            }

            protected function loadReviewsFromJson(string $jsonFile): array
            {
                $jsonPath = $this->testJsonDir . '/data/json/reviews/' . $jsonFile;
                
                // Simulate failure for LOTUS file
                if ($jsonFile === 'lotus-textiles-reviews.json') {
                    throw new \RuntimeException('Simulated failure for LOTUS');
                }
                
                if (!file_exists($jsonPath)) {
                    throw new \RuntimeException('JSON file not found: ' . $jsonPath);
                }

                $jsonContent = file_get_contents($jsonPath);
                $data = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        'Invalid JSON in file: ' . $jsonFile . '. Error: ' . json_last_error_msg()
                    );
                }

                if (!isset($data['reviews']) || !is_array($data['reviews'])) {
                    throw new \RuntimeException(
                        'Invalid JSON structure in file: ' . $jsonFile . '. Missing \'reviews\' array.'
                    );
                }

                return $data['reviews'];
            }
        };

        // Create test JSON files for ACME and GREEN (LOTUS will fail)
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/acme-dental-reviews.json',
            json_encode(['reviews' => [['authorName' => 'User 1', 'rating' => 5.0, 'body' => 'Great!']]])
        );
        file_put_contents(
            $this->testJsonDir . '/data/json/reviews/green-bites-reviews.json',
            json_encode(['reviews' => [['authorName' => 'User 2', 'rating' => 4.0, 'body' => 'Good!']]])
        );

        $minisiteIds = [
            'ACME' => 'test-minisite-acme',
            'LOTUS' => 'test-minisite-lotus', // Will fail
            'GREEN' => 'test-minisite-green',
            'SWIFT' => 'test-minisite-swift'  // File doesn't exist
        ];

        // Expect save to be called twice (ACME and GREEN succeed)
        $this->reviewRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = rand(1, 1000);
                return $review;
            });

        // Should not throw exception, should continue processing
        $service->seedAllTestReviews($minisiteIds);
    }
}

