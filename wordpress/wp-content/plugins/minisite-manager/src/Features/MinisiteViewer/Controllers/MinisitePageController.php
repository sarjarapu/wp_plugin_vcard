<?php

namespace Minisite\Features\MinisiteViewer\Controllers;

use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\Http\ViewRequestHandler;
use Minisite\Features\MinisiteViewer\Http\ViewResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\ViewRenderer;

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
final class MinisitePageController
{
    public function __construct(
        private ViewHandler $viewHandler,
        private MinisiteViewService $viewService,
        private ViewRequestHandler $requestHandler,
        private ViewResponseHandler $responseHandler,
        private ViewRenderer $renderer
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
}
