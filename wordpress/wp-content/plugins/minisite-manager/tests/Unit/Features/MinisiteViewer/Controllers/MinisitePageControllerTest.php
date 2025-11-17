<?php

namespace Tests\Unit\Features\MinisiteViewer\Controllers;

use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\Http\ViewRequestHandler;
use Minisite\Features\MinisiteViewer\Http\ViewResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\ViewRenderer;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use PHPUnit\Framework\TestCase;

/**
 * Test MinisitePageController
 *
 * Tests the MinisitePageController for proper coordination of minisite display flow
 */
final class MinisitePageControllerTest extends TestCase
{
    private MinisitePageController $minisitePageController;
    private $viewHandler;
    private $viewService;
    private $requestHandler;
    private $responseHandler;
    private $renderer;
    private $wordPressManager;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->viewHandler = $this->createMock(ViewHandler::class);
        $this->viewService = $this->createMock(MinisiteViewService::class);
        $this->requestHandler = $this->createMock(ViewRequestHandler::class);
        $this->responseHandler = $this->createMock(ViewResponseHandler::class);
        $this->renderer = $this->createMock(ViewRenderer::class);
        $this->wordPressManager = $this->createMock(WordPressMinisiteManager::class);

        // Create MinisitePageController with mocked dependencies
        $this->minisitePageController = new MinisitePageController(
            $this->viewHandler,
            $this->viewService,
            $this->requestHandler,
            $this->responseHandler,
            $this->renderer,
            $this->wordPressManager
        );
    }

    /**
     * Test MinisitePageController can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(MinisitePageController::class, $this->minisitePageController);
    }

    /**
     * Test handleView with successful minisite display
     */
    public function test_handle_display_with_successful_minisite_display(): void
    {
        $command = new ViewMinisiteCommand('coffee-shop', 'downtown');
        $mockMinisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $mockMinisite->id = '123';
        $mockMinisite->name = 'Coffee Shop';
        $mockMinisite->businessSlug = 'coffee-shop';
        $mockMinisite->locationSlug = 'downtown';

        // Mock request handler to return a display command
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to return success
        $this->viewHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisite' => $mockMinisite]);

        // Mock renderer to render minisite
        $this->renderer->expects($this->once())
            ->method('renderMinisite')
            ->with($mockMinisite);

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test handleView with minisite not found
     */
    public function test_handle_display_with_minisite_not_found(): void
    {
        $command = new ViewMinisiteCommand('nonexistent', 'location');

        // Mock request handler to return a display command
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to return failure
        $this->viewHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Minisite not found']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Minisite not found');

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test handleView with invalid request (no command)
     */
    public function test_handle_display_with_invalid_request(): void
    {
        // Mock request handler to return null (invalid request)
        $this->requestHandler->method('handleViewRequest')
            ->willReturn(null);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Invalid request - missing minisite parameters');

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test handleView with exception
     */
    public function test_handle_display_with_exception(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        // Mock request handler to return a command
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to throw exception
        $this->viewHandler->method('handle')
            ->with($command)
            ->willThrowException(new \Exception('Database error'));

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404 with error
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Error: Database error');

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test handleView with empty slugs
     */
    public function test_handle_display_with_empty_slugs(): void
    {
        $command = new ViewMinisiteCommand('', '');

        // Mock request handler to return a command with empty slugs
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to return failure
        $this->viewHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Minisite not found']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Minisite not found');

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test handleView with special characters in slugs
     */
    public function test_handle_display_with_special_characters(): void
    {
        $command = new ViewMinisiteCommand('café-&-restaurant', 'main-street-123');
        $mockMinisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $mockMinisite->id = '456';
        $mockMinisite->name = 'Café & Restaurant';
        $mockMinisite->businessSlug = 'café-&-restaurant';
        $mockMinisite->locationSlug = 'main-street-123';

        // Mock request handler to return a command
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to return success
        $this->viewHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisite' => $mockMinisite]);

        // Mock renderer to render minisite
        $this->renderer->expects($this->once())
            ->method('renderMinisite')
            ->with($mockMinisite);

        // Call the method
        $this->minisitePageController->handleView();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->minisitePageController);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(6, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $expectedTypes = [
            ViewHandler::class,
            MinisiteViewService::class,
            ViewRequestHandler::class,
            ViewResponseHandler::class,
            ViewRenderer::class,
            WordPressMinisiteManager::class
        ];

        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }

    /**
     * Test handleView with database error
     */
    public function test_handle_display_with_database_error(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        // Mock request handler to return a command
        $this->requestHandler->method('handleViewRequest')
            ->willReturn($command);

        // Mock display handler to return database error
        $this->viewHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Error retrieving minisite: Database connection failed']);

        // Mock response handler to set 404
        $this->responseHandler->expects($this->once())
            ->method('set404Response');

        // Mock renderer to render 404
        $this->renderer->expects($this->once())
            ->method('render404')
            ->with('Error retrieving minisite: Database connection failed');

        // Call the method
        $this->minisitePageController->handleView();
    }

    // ===== VERSION-SPECIFIC PREVIEW TESTS =====

    /**
     * Test handleVersionSpecificPreview with successful preview
     */
    public function test_handle_version_specific_preview_with_successful_preview(): void
    {
        $siteId = '123';
        $versionId = '5';
        $previewData = (object)[
            'minisite' => (object)[
                'id' => $siteId,
                'name' => 'Test Minisite',
                'siteJson' => ['test' => 'data']
            ],
            'version' => (object)[
                'id' => 5,
                'label' => 'Version 5',
                'siteJson' => ['test' => 'version data']
            ],
            'siteJson' => ['test' => 'version data'],
            'versionId' => $versionId
        ];

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        // Mock service call
        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForVersionSpecificPreview')
            ->with($siteId, $versionId)
            ->willReturn($previewData);

        // Mock renderer call
        $this->renderer
            ->expects($this->once())
            ->method('renderVersionSpecificPreview')
            ->with($previewData);

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with user not logged in
     */
    public function test_handle_version_specific_preview_with_user_not_logged_in(): void
    {
        $siteId = '123';
        $versionId = '5';

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        // Mock redirect calls
        $this->wordPressManager
            ->expects($this->once())
            ->method('getLoginRedirectUrl')
            ->willReturn('/login');

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('/login')
            ->willThrowException(new \Exception('Redirected')); // Simulate exit behavior

        // Call the method
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Redirected');
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with missing site ID
     */
    public function test_handle_version_specific_preview_with_missing_site_id(): void
    {
        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval - no site ID
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', ''],
                ['minisite_version_id', '', '5']
            ]);

        // Mock redirect calls
        $this->wordPressManager
            ->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('/account/sites');

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('/account/sites');

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with access denied
     */
    public function test_handle_version_specific_preview_with_access_denied(): void
    {
        $siteId = '123';
        $versionId = '5';

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        // Mock service call to throw access denied exception
        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForVersionSpecificPreview')
            ->with($siteId, $versionId)
            ->willThrowException(new \RuntimeException('Access denied'));

        // Mock redirect calls
        $this->wordPressManager
            ->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('/account/sites');

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('/account/sites');

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with minisite not found
     */
    public function test_handle_version_specific_preview_with_minisite_not_found(): void
    {
        $siteId = 'nonexistent';
        $versionId = '5';

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        // Mock service call to throw not found exception
        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForVersionSpecificPreview')
            ->with($siteId, $versionId)
            ->willThrowException(new \RuntimeException('Minisite not found'));

        // Mock redirect calls
        $this->wordPressManager
            ->expects($this->once())
            ->method('getHomeUrl')
            ->with('/account/sites')
            ->willReturn('/account/sites');

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('/account/sites');

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with general exception
     */
    public function test_handle_version_specific_preview_with_general_exception(): void
    {
        $siteId = '123';
        $versionId = '5';

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        // Mock service call to throw general exception
        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForVersionSpecificPreview')
            ->with($siteId, $versionId)
            ->willThrowException(new \RuntimeException('Database error'));

        // Mock renderer to render 404
        $this->renderer
            ->expects($this->once())
            ->method('render404')
            ->with('Database error');

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }

    /**
     * Test handleVersionSpecificPreview with current version
     */
    public function test_handle_version_specific_preview_with_current_version(): void
    {
        $siteId = '123';
        $versionId = 'current';
        $previewData = (object)[
            'minisite' => (object)[
                'id' => $siteId,
                'name' => 'Test Minisite',
                'siteJson' => ['test' => 'current data']
            ],
            'version' => null, // Current version
            'siteJson' => ['test' => 'current data'],
            'versionId' => $versionId
        ];

        // Mock authentication check
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        // Mock query var retrieval
        $this->wordPressManager
            ->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_id', '', $siteId],
                ['minisite_version_id', '', $versionId]
            ]);

        // Mock service call
        $this->viewService
            ->expects($this->once())
            ->method('getMinisiteForVersionSpecificPreview')
            ->with($siteId, $versionId)
            ->willReturn($previewData);

        // Mock renderer call
        $this->renderer
            ->expects($this->once())
            ->method('renderVersionSpecificPreview')
            ->with($previewData);

        // Call the method
        $this->minisitePageController->handleVersionSpecificPreview();
    }
}
