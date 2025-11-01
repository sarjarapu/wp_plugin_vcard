<?php

namespace Minisite\Features\MinisiteViewer\Controllers;

use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\Http\ViewRequestHandler;
use Minisite\Features\MinisiteViewer\Http\ViewResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\ViewRenderer;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;

/**
 * Refactored Minisite Page Controller
 *
 * SINGLE RESPONSIBILITY: Coordinate minisite view flow
 * - Delegates HTTP handling to ViewRequestHandler
 * - Delegates business logic to ViewHandler
 * - Delegates responses to ViewResponseHandler
 * - Delegates rendering to ViewRenderer
 *
 * This controller only orchestrates the flow - it doesn't do the work itself!
 */
class MinisitePageController
{
    public function __construct(
        private ViewHandler $viewHandler,
        private MinisiteViewService $viewService,
        private ViewRequestHandler $requestHandler,
        private ViewResponseHandler $responseHandler,
        private ViewRenderer $renderer,
        private WordPressMinisiteManager $wordPressManager
    ) {
    }

    /**
     * Handle minisite page view
     */
    public function handleView(): void
    {
        try {
            $command = $this->requestHandler->handleViewRequest();

            if (!$command) {
                $this->handleInvalidRequest();
                return;
            }

            $this->processView($command);
        } catch (\Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    /**
     * Process view command
     */
    private function processView($command): void
    {
        $result = $this->viewHandler->handle($command);

        if ($result['success']) {
            $this->renderMinisite($result['minisite']);
            return;
        }

        $this->handleNotFound($result['error']);
    }

    /**
     * Render minisite page
     */
    private function renderMinisite(object $minisite): void
    {
        $context = $this->responseHandler->createSuccessContext($minisite);
        $this->renderer->renderMinisite($minisite);
    }

    /**
     * Handle invalid request (missing slugs)
     */
    private function handleInvalidRequest(): void
    {
        $this->responseHandler->set404Response();
        $this->renderer->render404('Invalid request - missing minisite parameters');
    }

    /**
     * Handle not found (minisite doesn't exist)
     */
    private function handleNotFound(string $errorMessage): void
    {
        $this->responseHandler->set404Response();
        $this->renderer->render404($errorMessage);
    }

    /**
     * Handle general errors
     */
    private function handleError(string $errorMessage): void
    {
        $this->responseHandler->set404Response();
        $this->renderer->render404('Error: ' . $errorMessage);
    }

    /**
     * Handle version-specific preview request (authenticated)
     */
    public function handleVersionSpecificPreview(): void
    {
        // Check authentication
        if (!$this->wordPressManager->isUserLoggedIn()) {
            $this->wordPressManager->redirect($this->wordPressManager->getLoginRedirectUrl());
        }

        $siteId = $this->wordPressManager->getQueryVar('minisite_id');
        if (!$siteId) {
            $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
        }

        $versionId = $this->wordPressManager->getQueryVar('minisite_version_id');

        try {
            // Get minisite for version-specific preview
            $previewData = $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);
            $this->renderer->renderVersionSpecificPreview($previewData);
        } catch (\Exception $e) {
            // Handle access denied or not found
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
            } elseif (strpos($e->getMessage(), 'not found') !== false) {
                $this->wordPressManager->redirect($this->wordPressManager->getHomeUrl('/account/sites'));
            } else {
                $this->renderer->render404($e->getMessage());
            }
        }
    }
}
