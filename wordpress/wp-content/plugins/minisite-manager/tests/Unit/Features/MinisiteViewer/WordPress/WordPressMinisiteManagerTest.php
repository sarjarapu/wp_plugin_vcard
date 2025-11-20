<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteViewer\WordPress;

use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test WordPressMinisiteManager
 *
 * Tests the WordPressMinisiteManager for WordPress-specific operations
 */
final class WordPressMinisiteManagerTest extends TestCase
{
    private WordPressMinisiteManager $manager;
    private ReviewRepositoryInterface|MockObject $reviewRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock WordPress functions
        if (! function_exists('wp_login_url')) {
            eval('function wp_login_url($redirect = "") { return "http://example.com/wp-login.php?redirect_to=" . urlencode($redirect); }');
        }

        $terminationHandler = new TestTerminationHandler();
        $this->manager = new WordPressMinisiteManager($terminationHandler);
        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['minisite_review_repository']);

        parent::tearDown();
    }

    /**
     * Test getLoginRedirectUrl returns login URL
     */
    public function test_get_login_redirect_url_returns_login_url(): void
    {
        $url = $this->manager->getLoginRedirectUrl();

        $this->assertIsString($url);
        $this->assertStringContainsString('wp-login.php', $url);
    }

    /**
     * Test getLoginRedirectUrl returns valid URL
     */
    public function test_get_login_redirect_url_returns_valid_url(): void
    {
        $url = $this->manager->getLoginRedirectUrl();

        $this->assertNotEmpty($url);
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    /**
     * Test getReviewsForMinisite returns reviews when repository available
     */
    public function test_get_reviews_for_minisite_returns_reviews_when_repository_available(): void
    {
        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;

        $minisiteId = 'test-minisite-123';
        $expectedReviews = array(
            (object) array('id' => 1, 'rating' => 5.0, 'body' => 'Great!'),
            (object) array('id' => 2, 'rating' => 4.0, 'body' => 'Good!'),
        );

        $this->reviewRepository
            ->expects($this->once())
            ->method('listApprovedForMinisite')
            ->with($minisiteId)
            ->willReturn($expectedReviews);

        $result = $this->manager->getReviewsForMinisite($minisiteId);

        $this->assertEquals($expectedReviews, $result);
        $this->assertCount(2, $result);
    }

    /**
     * Test getReviewsForMinisite returns empty array when repository not available
     */
    public function test_get_reviews_for_minisite_returns_empty_array_when_repository_not_available(): void
    {
        unset($GLOBALS['minisite_review_repository']);

        $result = $this->manager->getReviewsForMinisite('test-minisite-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getReviewsForMinisite returns empty array when repository is null
     */
    public function test_get_reviews_for_minisite_returns_empty_array_when_repository_is_null(): void
    {
        $GLOBALS['minisite_review_repository'] = null;

        $result = $this->manager->getReviewsForMinisite('test-minisite-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getReviewsForMinisite handles repository exception
     */
    public function test_get_reviews_for_minisite_handles_repository_exception(): void
    {
        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;

        $minisiteId = 'test-minisite-123';

        $this->reviewRepository
            ->expects($this->once())
            ->method('listApprovedForMinisite')
            ->with($minisiteId)
            ->willThrowException(new \Exception('Database error'));

        // Should propagate exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->manager->getReviewsForMinisite($minisiteId);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->manager);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $this->assertEquals('Minisite\Infrastructure\Http\TerminationHandlerInterface', $params[0]->getType()->getName());
    }
}

