<?php

declare(strict_types=1);

namespace Tests\Unit\Features\ReviewManagement\Services;

use DateTimeImmutable;
use Minisite\Features\ReviewManagement\Domain\Entities\Review;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface;
use Minisite\Features\ReviewManagement\Services\ReviewSeederService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Brain\Monkey\Functions;

/**
 * Unit tests for ReviewSeederService
 */
#[CoversClass(ReviewSeederService::class)]
final class ReviewSeederServiceTest extends TestCase
{
    private ReviewRepositoryInterface|MockObject $reviewRepository;
    private ReviewSeederService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
        
        // Use global variable approach instead of Brain Monkey for get_current_user_id
        // since it's already defined in bootstrap.php before Brain Monkey can intercept
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        
        $this->service = new ReviewSeederService($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        // Clean up global mocks
        unset($GLOBALS['_test_mock_get_current_user_id']);
        
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(ReviewSeederService::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface', $params[0]->getType()->getName());
    }

    /**
     * Test insertReview creates review with required fields
     */
    public function test_insertReview_creates_review_with_required_fields(): void
    {
        $this->reviewRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = 1;
                return $review;
            });

        $result = $this->service->insertReview(
            'minisite-123',
            'John Doe',
            4.5,
            'Great service!'
        );

        $this->assertInstanceOf(Review::class, $result);
        $this->assertSame('minisite-123', $result->minisiteId);
        $this->assertSame('John Doe', $result->authorName);
        $this->assertSame(4.5, $result->rating);
        $this->assertSame('Great service!', $result->body);
    }

    /**
     * Test insertReview sets default values correctly
     */
    public function test_insertReview_sets_default_values(): void
    {
        $this->reviewRepository
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = 1;
                return $review;
            });

        $result = $this->service->insertReview(
            'minisite-123',
            'John Doe',
            4.5,
            'Great service!',
            'en-US'
        );

        $this->assertSame('en', $result->language);
        $this->assertSame('en-US', $result->locale);
        $this->assertSame('manual', $result->source);
        $this->assertNull($result->sourceId);
        $this->assertSame('approved', $result->status);
        $this->assertFalse($result->isEmailVerified);
        $this->assertFalse($result->isPhoneVerified);
        $this->assertSame(0, $result->helpfulCount);
        $this->assertNotNull($result->publishedAt);
    }

    /**
     * Test insertReview with optional fields
     */
    public function test_insertReview_with_optional_fields(): void
    {
        $this->reviewRepository
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = 1;
                return $review;
            });

        $result = $this->service->insertReview(
            'minisite-123',
            'Jane Smith',
            5.0,
            'Excellent!',
            'en-GB',
            'jane@example.com',
            '+1234567890',
            'https://example.com/jane',
            1
        );

        $this->assertSame('jane@example.com', $result->authorEmail);
        $this->assertSame('+1234567890', $result->authorPhone);
        $this->assertSame('https://example.com/jane', $result->authorUrl);
        $this->assertSame(1, $result->displayOrder);
        $this->assertSame('en', $result->language);
        $this->assertSame('en-GB', $result->locale);
    }

    /**
     * Test insertReview with null locale
     */
    public function test_insertReview_with_null_locale(): void
    {
        $review = new Review();
        $review->id = 1;

        $this->reviewRepository
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = 1;
                return $review;
            })
            ->willReturn($review);

        $result = $this->service->insertReview(
            'minisite-123',
            'John Doe',
            4.5,
            'Great service!',
            null
        );

        $this->assertNull($result->language);
        $this->assertNull($result->locale);
    }

    /**
     * Test insertReview with logged in user
     */
    public function test_insertReview_with_logged_in_user(): void
    {
        // Set user ID before creating service
        $GLOBALS['_test_mock_get_current_user_id'] = 42;
        
        // Recreate service to pick up the new user ID
        $this->service = new ReviewSeederService($this->reviewRepository);

        $this->reviewRepository
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = 1;
                return $review;
            });

        $result = $this->service->insertReview(
            'minisite-123',
            'John Doe',
            4.5,
            'Great service!'
        );

        $this->assertSame(42, $result->createdBy);
        $this->assertSame(42, $result->moderatedBy);
    }

    /**
     * Test createReviewFromJsonData with minimal data
     */
    public function test_createReviewFromJsonData_with_minimal_data(): void
    {
        $reviewData = [
            'authorName' => 'Test User',
            'rating' => 4.0,
            'body' => 'Test review'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame('minisite-123', $review->minisiteId);
        $this->assertSame('Test User', $review->authorName);
        $this->assertSame(4.0, $review->rating);
        $this->assertSame('Test review', $review->body);
        $this->assertSame('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
    }

    /**
     * Test createReviewFromJsonData with all fields
     */
    public function test_createReviewFromJsonData_with_all_fields(): void
    {
        $reviewData = [
            'authorName' => 'John Doe',
            'authorEmail' => 'john@example.com',
            'authorPhone' => '+1234567890',
            'authorUrl' => 'https://example.com',
            'rating' => 5.0,
            'body' => 'Excellent service!',
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
            'publishedAt' => '2025-01-03T00:00:00Z'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame('John Doe', $review->authorName);
        $this->assertSame('john@example.com', $review->authorEmail);
        $this->assertSame('+1234567890', $review->authorPhone);
        $this->assertSame('https://example.com', $review->authorUrl);
        $this->assertSame(5.0, $review->rating);
        $this->assertSame('Excellent service!', $review->body);
        $this->assertSame('en', $review->language);
        $this->assertSame('en-US', $review->locale);
        $this->assertSame('2025-01', $review->visitedMonth);
        $this->assertSame('google', $review->source);
        $this->assertSame('g-123', $review->sourceId);
        $this->assertTrue($review->isEmailVerified);
        $this->assertFalse($review->isPhoneVerified);
        $this->assertSame(10, $review->helpfulCount);
        $this->assertSame(0.1, $review->spamScore);
        $this->assertSame(0.9, $review->sentimentScore);
        $this->assertSame(1, $review->displayOrder);
        $this->assertSame('approved', $review->status);
        $this->assertSame(5, $review->moderatedBy);
        $this->assertSame(10, $review->createdBy);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->updatedAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $review->publishedAt);
    }

    /**
     * Test createReviewFromJsonData with defaults when fields missing
     */
    public function test_createReviewFromJsonData_with_defaults(): void
    {
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame(5.0, $review->rating); // Default rating
        $this->assertSame('en-US', $review->locale); // Default locale
        $this->assertSame('en', $review->language); // Auto-detected from locale
        $this->assertSame('manual', $review->source); // Default source
        $this->assertFalse($review->isEmailVerified); // Default
        $this->assertFalse($review->isPhoneVerified); // Default
        $this->assertSame(0, $review->helpfulCount); // Default
        $this->assertSame('approved', $review->status); // Default
    }

    /**
     * Test createReviewFromJsonData auto-detects language from locale
     */
    public function test_createReviewFromJsonData_auto_detects_language(): void
    {
        $reviewData = [
            'authorName' => 'Test',
            'body' => 'Test',
            'locale' => 'hi-IN'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame('hi', $review->language);
        $this->assertSame('hi-IN', $review->locale);
    }

    /**
     * Test createReviewFromJsonData marks as published when status is approved
     */
    public function test_createReviewFromJsonData_marks_as_published_when_approved(): void
    {
        $reviewData = [
            'authorName' => 'Test',
            'body' => 'Test',
            'status' => 'approved'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame('approved', $review->status);
        $this->assertNotNull($review->publishedAt);
    }

    /**
     * Test createReviewFromJsonData does not mark as published when status is pending
     */
    public function test_createReviewFromJsonData_does_not_mark_as_published_when_pending(): void
    {
        $reviewData = [
            'authorName' => 'Test',
            'body' => 'Test',
            'status' => 'pending'
        ];

        $review = $this->service->createReviewFromJsonData('minisite-123', $reviewData);

        $this->assertSame('pending', $review->status);
        $this->assertNull($review->publishedAt);
    }

    /**
     * Test seedReviewsForMinisite calls repository save for each review
     */
    public function test_seedReviewsForMinisite_calls_repository_save(): void
    {
        $reviewData1 = [
            'authorName' => 'User 1',
            'body' => 'Review 1',
            'rating' => 4.0
        ];
        $reviewData2 = [
            'authorName' => 'User 2',
            'body' => 'Review 2',
            'rating' => 5.0
        ];

        $this->reviewRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Review $review) {
                $review->id = rand(1, 1000);
                return $review;
            });

        $this->service->seedReviewsForMinisite('minisite-123', [$reviewData1, $reviewData2]);
    }

    /**
     * Test seedReviewsForMinisite with empty array
     */
    public function test_seedReviewsForMinisite_with_empty_array(): void
    {
        $this->reviewRepository
            ->expects($this->never())
            ->method('save');

        $this->service->seedReviewsForMinisite('minisite-123', []);
    }

    /**
     * Test createReviewFromJsonData with publishedAt provided in JSON
     */
    public function test_createReviewFromJsonData_with_publishedAt_in_json(): void
    {
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test',
            'publishedAt' => '2025-01-15T10:00:00Z',
            'status' => 'approved'
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-pub', $reviewData);

        $this->assertNotNull($review->publishedAt);
        $this->assertEquals('2025-01-15 10:00:00', $review->publishedAt->format('Y-m-d H:i:s'));
    }

    /**
     * Test createReviewFromJsonData with approved status but no publishedAt
     */
    public function test_createReviewFromJsonData_approved_without_publishedAt(): void
    {
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test',
            'status' => 'approved'
            // No publishedAt provided
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-approve', $reviewData);

        $this->assertEquals('approved', $review->status);
        $this->assertNotNull($review->publishedAt); // Should be set by markAsPublished
    }

    /**
     * Test createReviewFromJsonData with missing createdAt uses current time
     */
    public function test_createReviewFromJsonData_missing_createdAt_uses_current_time(): void
    {
        $before = new \DateTimeImmutable();
        
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test'
            // No createdAt provided
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-time', $reviewData);
        
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->createdAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $review->createdAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $review->createdAt->getTimestamp());
    }

    /**
     * Test createReviewFromJsonData with missing updatedAt uses current time
     */
    public function test_createReviewFromJsonData_missing_updatedAt_uses_current_time(): void
    {
        $before = new \DateTimeImmutable();
        
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test'
            // No updatedAt provided
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-time2', $reviewData);
        
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->updatedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $review->updatedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $review->updatedAt->getTimestamp());
    }

    /**
     * Test createReviewFromJsonData with missing createdBy uses current user
     */
    public function test_createReviewFromJsonData_missing_createdBy_uses_current_user(): void
    {
        $GLOBALS['_test_mock_get_current_user_id'] = 99;
        
        // Recreate service to pick up new user ID
        $this->service = new ReviewSeederService($this->reviewRepository);

        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test'
            // No createdBy provided
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-user2', $reviewData);

        $this->assertEquals(99, $review->createdBy);
    }

    /**
     * Test createReviewFromJsonData with null createdBy uses current user
     */
    public function test_createReviewFromJsonData_null_createdBy_uses_current_user(): void
    {
        $GLOBALS['_test_mock_get_current_user_id'] = 88;
        
        // Recreate service to pick up new user ID
        $this->service = new ReviewSeederService($this->reviewRepository);

        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test',
            'createdBy' => null // Explicitly null
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-user3', $reviewData);

        $this->assertEquals(88, $review->createdBy);
    }

    /**
     * Test createReviewFromJsonData with non-approved status doesn't set publishedAt
     */
    public function test_createReviewFromJsonData_pending_status_no_publishedAt(): void
    {
        $reviewData = [
            'authorName' => 'Test User',
            'body' => 'Test',
            'status' => 'pending'
            // No publishedAt provided
        ];

        $review = $this->service->createReviewFromJsonData('test-minisite-pending', $reviewData);

        $this->assertEquals('pending', $review->status);
        $this->assertNull($review->publishedAt); // Should not be set for non-approved
    }
}

