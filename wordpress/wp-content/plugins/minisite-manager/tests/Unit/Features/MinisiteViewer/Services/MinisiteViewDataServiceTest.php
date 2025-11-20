<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewDataService;
use Minisite\Features\MinisiteViewer\ViewModels\MinisiteViewModel;
use Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

        // Define is_user_logged_in if not already defined (it's not in WordPressFunctions.php)
        // Note: current_user_can and get_current_user_id are already defined in WordPressFunctions.php
        // Use the same global variable naming pattern as other tests: _test_mock_is_user_logged_in
        if (! function_exists('is_user_logged_in')) {
            eval('function is_user_logged_in() {
                if (isset($GLOBALS["_test_mock_is_user_logged_in"])) {
                    return $GLOBALS["_test_mock_is_user_logged_in"] === true;
                }
                // Fallback to old naming pattern for backward compatibility
                return isset($GLOBALS["_test_is_user_logged_in"]) && $GLOBALS["_test_is_user_logged_in"] === true;
            }');
        }

        // Always reset globals to defaults - use the global names that WordPressFunctions.php expects
        // Use _test_mock_ prefix to match other test files for consistency
        // This ensures test isolation when running the full test suite
        $GLOBALS['_test_mock_is_user_logged_in'] = false;
        $GLOBALS['_test_is_user_logged_in'] = false; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        $GLOBALS['_test_mock_current_user_can'] = false; // Default to false for tests

        $this->reviewRepository = $this->createMock(ReviewRepositoryInterface::class);
        $this->dataService = new MinisiteViewDataService();
    }

    protected function tearDown(): void
    {
        // Clean up globals - reset to defaults to prevent test interference
        unset($GLOBALS['minisite_review_repository']);
        // Reset to defaults (don't unset, as WordPressFunctions.php defaults to true if not set)
        $GLOBALS['_test_mock_is_user_logged_in'] = false;
        $GLOBALS['_test_is_user_logged_in'] = false; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        $GLOBALS['_test_mock_current_user_can'] = false;
        global $wpdb;
        $wpdb = null;

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
        // Use globals to control function behavior (functions are already defined in setUp)
        // Set defaults for this test - user not logged in
        $GLOBALS['_test_mock_is_user_logged_in'] = false;
        $GLOBALS['_test_is_user_logged_in'] = false; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        $GLOBALS['_test_mock_current_user_can'] = false;

        // Create service without repository (uses global)
        unset($GLOBALS['minisite_review_repository']);
        $dataService = new MinisiteViewDataService();

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

    // ===== TESTS FOR checkIfBookmarked() VIA prepareViewModel() =====

    /**
     * Test prepareViewModel with user not logged in (bookmark check)
     * Note: Bookmark check requires wpdb mocking which is complex.
     * Testing the logged-out path which doesn't require database access.
     */
    public function test_prepare_view_model_bookmark_check_user_not_logged_in(): void
    {
        // Set globals - user not logged in (defaults are already set in setUp)
        $GLOBALS['_test_mock_is_user_logged_in'] = false;
        $GLOBALS['_test_is_user_logged_in'] = false; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        $GLOBALS['_test_mock_current_user_can'] = false;

        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;
        $this->reviewRepository
            ->method('listApprovedForMinisite')
            ->willReturn(array());

        $minisite = $this->createMockMinisite('test-not-logged-in');
        $viewModel = $this->dataService->prepareViewModel($minisite);

        // Should return false when user is not logged in
        $this->assertFalse($viewModel->isBookmarked());
    }

    /**
     * Test checkIfBookmarked protected method via reflection - user not logged in
     */
    public function test_check_if_bookmarked_user_not_logged_in(): void
    {
        // Set globals - user not logged in
        $GLOBALS['_test_is_user_logged_in'] = false;
        $GLOBALS['_test_mock_get_current_user_id'] = 0;

        $reflection = new \ReflectionClass($this->dataService);
        $method = $reflection->getMethod('checkIfBookmarked');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataService, 'test-minisite-id');

        $this->assertFalse($result);
    }

    // ===== TESTS FOR checkIfCanEdit() VIA prepareViewModel() =====

    /**
     * Test prepareViewModel with user who can edit
     */
    public function test_prepare_view_model_with_user_can_edit(): void
    {
        // Mock wpdb for bookmark check - create a simple object with methods
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public function prepare($query, ...$args)
            {
                return $query;
            }
            public function get_var($query)
            {
                return null; // No bookmark
            }
        };

        // Set globals to control function behavior - use the global names WordPressFunctions.php expects
        $GLOBALS['_test_mock_is_user_logged_in'] = true;
        $GLOBALS['_test_is_user_logged_in'] = true; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 1;
        // Use a callable that checks the capability and minisiteId
        // Store the minisite ID we're testing with for the callback
        $testMinisiteId = 'test-can-edit';
        $GLOBALS['_test_mock_current_user_can'] = function ($capability, ...$args) use ($testMinisiteId) {
            $minisiteId = $args[0] ?? null;

            return $capability === 'minisite_edit_profile' && $minisiteId === $testMinisiteId;
        };

        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;
        $this->reviewRepository
            ->method('listApprovedForMinisite')
            ->willReturn(array());

        $minisite = $this->createMockMinisite($testMinisiteId);
        $viewModel = $this->dataService->prepareViewModel($minisite);

        $this->assertTrue($viewModel->canEdit(), 'User should be able to edit minisite');
    }

    /**
     * Test prepareViewModel with user who cannot edit
     */
    public function test_prepare_view_model_with_user_cannot_edit(): void
    {
        // Mock wpdb for bookmark check - create a simple object with methods
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public function prepare($query, ...$args)
            {
                return $query;
            }
            public function get_var($query)
            {
                return null; // No bookmark
            }
        };

        // Set globals to control function behavior - use the global names WordPressFunctions.php expects
        $GLOBALS['_test_mock_is_user_logged_in'] = true;
        $GLOBALS['_test_is_user_logged_in'] = true; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 1;
        $GLOBALS['_test_mock_current_user_can'] = false; // User cannot edit

        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;
        $this->reviewRepository
            ->method('listApprovedForMinisite')
            ->willReturn(array());

        $minisite = $this->createMockMinisite('test-cannot-edit');
        $viewModel = $this->dataService->prepareViewModel($minisite);

        $this->assertFalse($viewModel->canEdit());
    }

    /**
     * Test prepareViewModel with user not logged in (edit check)
     */
    public function test_prepare_view_model_edit_check_user_not_logged_in(): void
    {
        // Set globals - user not logged in (defaults are already set in setUp)
        $GLOBALS['_test_mock_is_user_logged_in'] = false;
        $GLOBALS['_test_is_user_logged_in'] = false; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 0;
        $GLOBALS['_test_mock_current_user_can'] = false;

        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;
        $this->reviewRepository
            ->method('listApprovedForMinisite')
            ->willReturn(array());

        $minisite = $this->createMockMinisite('test-not-logged-in-edit');
        $viewModel = $this->dataService->prepareViewModel($minisite);

        // Should return false when user is not logged in
        $this->assertFalse($viewModel->canEdit());
    }

    /**
     * Test prepareViewModel handles edit permission check exception
     */
    public function test_prepare_view_model_edit_check_exception(): void
    {
        // Mock wpdb for bookmark check - create a simple object with methods
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public function prepare($query, ...$args)
            {
                return $query;
            }
            public function get_var($query)
            {
                return null; // No bookmark
            }
        };

        // Set globals to control function behavior - use the global names WordPressFunctions.php expects
        $GLOBALS['_test_mock_is_user_logged_in'] = true;
        $GLOBALS['_test_is_user_logged_in'] = true; // Keep for backward compatibility
        $GLOBALS['_test_mock_get_current_user_id'] = 1;
        $GLOBALS['_test_mock_current_user_can'] = function (...$args) {
            throw new \Exception('Permission check failed');
        };

        $GLOBALS['minisite_review_repository'] = $this->reviewRepository;
        $this->reviewRepository
            ->method('listApprovedForMinisite')
            ->willReturn(array());

        $minisite = $this->createMockMinisite('test-edit-exception');
        $viewModel = $this->dataService->prepareViewModel($minisite);

        // Should return false on exception
        $this->assertFalse($viewModel->canEdit());
    }

}
