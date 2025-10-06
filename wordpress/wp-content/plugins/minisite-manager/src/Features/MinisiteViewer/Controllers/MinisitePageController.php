<?php

namespace Minisite\Features\MinisiteViewer\Controllers;

use Minisite\Features\MinisiteViewer\Handlers\DisplayHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteDisplayService;
use Minisite\Features\MinisiteViewer\Http\DisplayRequestHandler;
use Minisite\Features\MinisiteViewer\Http\DisplayResponseHandler;
use Minisite\Features\MinisiteViewer\Rendering\DisplayRenderer;

/**
 * Refactored Minisite Page Controller
 *
 * SINGLE RESPONSIBILITY: Coordinate minisite display flow
 * - Delegates HTTP handling to DisplayRequestHandler
 * - Delegates business logic to DisplayHandler
 * - Delegates responses to DisplayResponseHandler
 * - Delegates rendering to DisplayRenderer
 *
 * This controller only orchestrates the flow - it doesn't do the work itself!
 */
final class MinisitePageController
{
    public function __construct(
        private DisplayHandler $displayHandler,
        private MinisiteDisplayService $displayService,
        private DisplayRequestHandler $requestHandler,
        private DisplayResponseHandler $responseHandler,
        private DisplayRenderer $renderer
    ) {
    }

    /**
     * Handle minisite page display
     */
    public function handleDisplay(): void
    {
        try {
            $command = $this->requestHandler->handleDisplayRequest();

            if (!$command) {
                $this->handleInvalidRequest();
                return;
            }

            $this->processDisplay($command);
        } catch (\Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    /**
     * Process display command
     */
    private function processDisplay($command): void
    {
        $result = $this->displayHandler->handle($command);

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
