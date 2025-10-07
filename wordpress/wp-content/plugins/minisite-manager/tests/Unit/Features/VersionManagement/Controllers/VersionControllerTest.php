<?php

namespace Minisite\Features\VersionManagement\Controllers;

use Minisite\Features\VersionManagement\Handlers\CreateDraftHandler;
use Minisite\Features\VersionManagement\Handlers\ListVersionsHandler;
use Minisite\Features\VersionManagement\Handlers\PublishVersionHandler;
use Minisite\Features\VersionManagement\Handlers\RollbackVersionHandler;
use Minisite\Features\VersionManagement\Http\VersionRequestHandler;
use Minisite\Features\VersionManagement\Http\VersionResponseHandler;
use Minisite\Features\VersionManagement\Rendering\VersionRenderer;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionController
 */
class VersionControllerTest extends TestCase
{
    private VersionController $controller;
    private MockObject $listVersionsHandler;
    private MockObject $createDraftHandler;
    private MockObject $publishVersionHandler;
    private MockObject $rollbackVersionHandler;
    private MockObject $requestHandler;
    private MockObject $responseHandler;
    private MockObject $renderer;
    private MockObject $versionService;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->listVersionsHandler = $this->createMock(ListVersionsHandler::class);
        $this->createDraftHandler = $this->createMock(CreateDraftHandler::class);
        $this->publishVersionHandler = $this->createMock(PublishVersionHandler::class);
        $this->rollbackVersionHandler = $this->createMock(RollbackVersionHandler::class);
        $this->requestHandler = $this->createMock(VersionRequestHandler::class);
        $this->responseHandler = $this->createMock(VersionResponseHandler::class);
        $this->renderer = $this->createMock(VersionRenderer::class);
        $this->versionService = $this->createMock(\Minisite\Features\VersionManagement\Services\VersionService::class);
        $this->wordPressManager = $this->createMock(WordPressVersionManager::class);

        $this->controller = new VersionController(
            $this->listVersionsHandler,
            $this->createDraftHandler,
            $this->publishVersionHandler,
            $this->rollbackVersionHandler,
            $this->requestHandler,
            $this->responseHandler,
            $this->renderer,
            $this->versionService,
            $this->wordPressManager
        );

        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_handle_list_versions_redirects_when_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->responseHandler
            ->expects($this->once())
            ->method('redirectToLogin');

        $this->controller->handleListVersions();
    }

    public function test_handle_list_versions_redirects_when_no_command(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->requestHandler
            ->expects($this->once())
            ->method('parseListVersionsRequest')
            ->willReturn(null);

        $this->responseHandler
            ->expects($this->once())
            ->method('redirectToSites');

        $this->controller->handleListVersions();
    }

    public function test_handle_create_draft_returns_error_when_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->responseHandler
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handleCreateDraft();
    }

    public function test_handle_publish_version_returns_error_when_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->responseHandler
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handlePublishVersion();
    }

    public function test_handle_rollback_version_returns_error_when_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->responseHandler
            ->expects($this->once())
            ->method('sendJsonError')
            ->with('Not authenticated', 401);

        $this->controller->handleRollbackVersion();
    }

    private function setupWordPressMocks(): void
    {
        $functions = ['sanitize_text_field', 'wp_unslash'];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return null;
                    }
                ");
            }
        }
    }

    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    private function clearWordPressMocks(): void
    {
        $functions = ['is_user_logged_in', 'sanitize_text_field', 'wp_unslash'];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
