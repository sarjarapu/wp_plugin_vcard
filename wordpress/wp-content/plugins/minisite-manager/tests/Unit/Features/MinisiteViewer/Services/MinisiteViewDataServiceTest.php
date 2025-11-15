<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewDataService;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteViewDataService
 *
 * Tests the MinisiteViewDataService for preparing view data
 * Note: Uses global mocks for WordPress functions that are already defined in bootstrap
 */
final class MinisiteViewDataServiceTest extends TestCase
{
    private MinisiteViewDataService $dataService;
    private ReviewRepositoryInterface|MockObject $reviewRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Define WordPress functions if not already defined
        if (! function_exists('is_user_logged_in')) {
            eval('function is_user_logged_in() { return false; }');
        }
        if (! function_exists('get_current_user_id')) {
            eval('function get_current_user_id() { return 0; }');
        }
        if (! function_exists('current_user_can')) {
            eval('function current_user_can(...$args) { return false; }');
        }

        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
        $this->dataService = new MinisiteViewDataService($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['minisite_review_repository']);
        unset($GLOBALS['wpdb']);

        parent::tearDown();
    }

    public function test_prepare_view_model_with_reviews(): void
    {
        // Service uses global repository, not injected one
        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;

        $minisite = $this->createMockMinisite('test-123');

        // Mock reviews
        $mockReview1 = (object) array('id' => 1, 'rating' => 5.0, 'body' => 'Great!');
        $mockReview2 = (object) array('id' => 2, 'rating' => 4.0, 'body' => 'Good!');

        $this->reviewRepository
            ->expects($this->once())
            ->method('listApprovedForMinisite')
            ->with('test-123')
            ->willReturn(array($mockReview1, $mockReview2));

        $viewModel = $this->dataService->prepareViewModel($minisite);

        $this->assertInstanceOf(MinisiteViewModel::class, $viewModel);
        $this->assertEquals($minisite, $viewModel->getMinisite());
        $this->assertCount(2, $viewModel->getReviews());

        unset($GLOBALS['minisite_review_repository']);
    }

    public function test_prepare_view_model_without_reviews(): void
    {
        // Service uses global repository
        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;

        $minisite = $this->createMockMinisite('test-456');

        $this->reviewRepository
            ->expects($this->once())
            ->method('listApprovedForMinisite')
            ->with('test-456')
            ->willReturn(array());

        $viewModel = $this->dataService->prepareViewModel($minisite);

        $this->assertInstanceOf(MinisiteViewModel::class, $viewModel);
        $this->assertEmpty($viewModel->getReviews());
        $this->assertFalse($viewModel->isBookmarked());
        $this->assertFalse($viewModel->canEdit());
    }

    public function test_prepare_view_model_when_review_repository_not_available(): void
    {
        // Define WordPress functions for this test
        if (! function_exists('is_user_logged_in')) {
            eval('function is_user_logged_in() { return false; }');
        }
        if (! function_exists('get_current_user_id')) {
            eval('function get_current_user_id() { return 0; }');
        }
        if (! function_exists('current_user_can')) {
            eval('function current_user_can(...$args) { return false; }');
        }

        // Create service without repository (uses global)
        unset($GLOBALS['minisite_review_repository']);
        $dataService = new MinisiteViewDataService(null);

        $minisite = $this->createMockMinisite('test-no-repo');

        $viewModel = $dataService->prepareViewModel($minisite);

        $this->assertInstanceOf(MinisiteViewModel::class, $viewModel);
        $this->assertEmpty($viewModel->getReviews());
    }

    public function test_prepare_view_model_handles_review_repository_exception(): void
    {
        // Service uses global repository
        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;

        $minisite = $this->createMockMinisite('test-exception');

        $this->reviewRepository
            ->expects($this->once())
            ->method('listApprovedForMinisite')
            ->with('test-exception')
            ->willThrowException(new \Exception('Database error'));

        $viewModel = $this->dataService->prepareViewModel($minisite);

        // Should handle exception gracefully and return empty reviews
        $this->assertInstanceOf(MinisiteViewModel::class, $viewModel);
        $this->assertEmpty($viewModel->getReviews());

        unset($GLOBALS['minisite_review_repository']);
    }

    /**
     * Helper to create a mock Minisite entity
     */
    private function createMockMinisite(string $id): Minisite
    {
        $minisite = $this->createMock(Minisite::class);
        $minisite->id = $id;
        $minisite->name = 'Test Minisite';
        $minisite->title = 'Test Title';

        return $minisite;
    }
}

