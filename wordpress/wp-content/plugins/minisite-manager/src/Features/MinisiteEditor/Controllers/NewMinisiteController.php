<?php

namespace Minisite\Features\MinisiteEditor\Controllers;

use Minisite\Features\MinisiteEditor\Handlers\CreateMinisiteHandler;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;
use Minisite\Features\MinisiteEditor\Http\EditorRequestHandler;
use Minisite\Features\MinisiteEditor\Http\EditorResponseHandler;
use Minisite\Features\MinisiteEditor\Rendering\EditorRenderer;

/**
 * Refactored New Minisite Controller
 *
 * SINGLE RESPONSIBILITY: Coordinate new minisite creation flow
 * - Delegates HTTP handling to EditorRequestHandler
 * - Delegates business logic to CreateMinisiteHandler
 * - Delegates responses to EditorResponseHandler
 * - Delegates rendering to EditorRenderer
 *
 * This controller only orchestrates the flow - it doesn't do the work itself!
 */
final class NewMinisiteController
{
    public function __construct(
        private CreateMinisiteHandler $createMinisiteHandler,
        private MinisiteEditorService $editorService,
        private EditorRequestHandler $requestHandler,
        private EditorResponseHandler $responseHandler,
        private EditorRenderer $renderer
    ) {
    }

    /**
     * Handle new minisite page
     */
    public function handleNew(): void
    {
        if (!is_user_logged_in()) {
            $this->responseHandler->redirectToLogin();
            return;
        }

        try {
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->processCreateMinisite();
                return;
            }

            // Display new minisite form
            $this->renderNewPage();
        } catch (\Exception $e) {
            $this->renderNewPage($e->getMessage());
        }
    }

    /**
     * Handle simple minisite creation
     */
    public function handleCreateSimple(): void
    {
        if (!is_user_logged_in()) {
            $this->responseHandler->redirectToLogin();
            return;
        }

        try {
            $command = $this->requestHandler->parseCreateMinisiteRequest();
            if (!$command) {
                $this->renderNewPage('Please provide all required fields.');
                return;
            }

            $result = $this->createMinisiteHandler->handle($command);
            
            if ($result['success']) {
                $redirectUrl = home_url('/account/sites/' . $result['minisite']->id . '/edit?created=1');
                $this->responseHandler->redirect($redirectUrl);
            } else {
                $this->renderNewPage($result['error'] ?? 'Failed to create minisite');
            }
        } catch (\Exception $e) {
            $this->renderNewPage('An error occurred while creating the minisite');
        }
    }

    /**
     * Process create minisite form submission
     */
    private function processCreateMinisite(): void
    {
        $command = $this->requestHandler->parseCreateMinisiteRequest();
        if (!$command) {
            $this->renderNewPage('Please provide all required fields.');
            return;
        }

        $result = $this->createMinisiteHandler->handle($command);
        
        if ($result['success']) {
            $redirectUrl = home_url('/account/sites/' . $result['minisite']->id . '/edit?created=1');
            $this->responseHandler->redirect($redirectUrl);
        } else {
            $this->renderNewPage($result['error'] ?? 'Failed to create minisite');
        }
    }

    /**
     * Render new minisite page
     */
    private function renderNewPage(?string $error = null): void
    {
        $currentUser = wp_get_current_user();
        
        $data = [
            'page_title' => 'Create New Minisite',
            'page_subtitle' => 'Start by providing your business and location slugs',
            'form_data' => $_POST ?? [],
            'error' => $error,
            'success' => sanitize_text_field(wp_unslash($_GET['success'] ?? null)),
            'user' => $currentUser,
        ];

        $this->renderer->renderNewPage($data);
    }
}
