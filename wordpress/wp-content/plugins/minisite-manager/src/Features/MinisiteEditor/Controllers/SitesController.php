<?php

namespace Minisite\Features\MinisiteEditor\Controllers;

use Minisite\Features\MinisiteEditor\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteEditor\Handlers\EditMinisiteHandler;
use Minisite\Features\MinisiteEditor\Handlers\PreviewMinisiteHandler;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;
use Minisite\Features\MinisiteEditor\Http\EditorRequestHandler;
use Minisite\Features\MinisiteEditor\Http\EditorResponseHandler;
use Minisite\Features\MinisiteEditor\Rendering\EditorRenderer;

/**
 * Refactored Sites Controller
 *
 * SINGLE RESPONSIBILITY: Coordinate minisite editing flow
 * - Delegates HTTP handling to EditorRequestHandler
 * - Delegates business logic to Handlers
 * - Delegates responses to EditorResponseHandler
 * - Delegates rendering to EditorRenderer
 *
 * This controller only orchestrates the flow - it doesn't do the work itself!
 */
final class SitesController
{
    public function __construct(
        private ListMinisitesHandler $listMinisitesHandler,
        private EditMinisiteHandler $editMinisiteHandler,
        private PreviewMinisiteHandler $previewMinisiteHandler,
        private MinisiteEditorService $editorService,
        private EditorRequestHandler $requestHandler,
        private EditorResponseHandler $responseHandler,
        private EditorRenderer $renderer
    ) {
    }

    /**
     * Handle listing minisites
     */
    public function handleList(): void
    {
        if (!is_user_logged_in()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ?
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectUrl);
            return;
        }

        try {
            $command = $this->requestHandler->parseListMinisitesRequest();
            if (!$command) {
                $this->responseHandler->redirectToLogin();
                return;
            }

            $result = $this->listMinisitesHandler->handle($command);
            
            if (!$result['success']) {
                $this->renderListPage($result['error'] ?? 'Failed to load minisites');
                return;
            }

            $this->renderListPage(null, $result['minisites'] ?? []);
        } catch (\Exception $e) {
            $this->renderListPage('An error occurred while loading minisites');
        }
    }

    /**
     * Handle editing minisite
     */
    public function handleEdit(): void
    {
        if (!is_user_logged_in()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ?
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectUrl);
            return;
        }

        try {
            $command = $this->requestHandler->parseEditMinisiteRequest();
            if (!$command) {
                $this->responseHandler->redirectToSites();
                return;
            }

            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->processEditMinisite($command);
                return;
            }

            // Display edit form
            $this->renderEditPage($command);
        } catch (\Exception $e) {
            $this->responseHandler->redirectToSites();
        }
    }

    /**
     * Handle previewing minisite
     */
    public function handlePreview(): void
    {
        if (!is_user_logged_in()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ?
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectUrl);
            return;
        }

        try {
            $command = $this->requestHandler->parsePreviewMinisiteRequest();
            if (!$command) {
                $this->responseHandler->redirectToSites();
                return;
            }

            $result = $this->previewMinisiteHandler->handle($command);
            
            if (!$result['success']) {
                $this->responseHandler->redirectToSites();
                return;
            }

            $this->renderPreviewPage($result['minisite']);
        } catch (\Exception $e) {
            $this->responseHandler->redirectToSites();
        }
    }

    /**
     * Process edit minisite form submission
     */
    private function processEditMinisite($command): void
    {
        $result = $this->editMinisiteHandler->handle($command);
        
        if ($result['success']) {
            $redirectUrl = home_url('/account/sites/' . $command->siteId . '/edit?draft_saved=1');
            $this->responseHandler->redirect($redirectUrl);
        } else {
            $this->renderEditPage($command, $result['error'] ?? 'Failed to save minisite');
        }
    }

    /**
     * Render list page
     */
    private function renderListPage(?string $error = null, array $minisites = []): void
    {
        $data = [
            'page_title' => 'My Minisites',
            'minisites' => $minisites,
            'error' => $error,
        ];

        $this->renderer->renderListPage($data);
    }

    /**
     * Render edit page
     */
    private function renderEditPage($command, ?string $error = null): void
    {
        $minisite = $this->editorService->getMinisiteForEditing($command->siteId);
        
        $data = [
            'page_title' => 'Edit Minisite',
            'minisite' => $minisite,
            'error' => $error,
            'form_data' => $_POST ?? [],
        ];

        $this->renderer->renderEditPage($data);
    }

    /**
     * Render preview page
     */
    private function renderPreviewPage(object $minisite): void
    {
        $data = [
            'page_title' => 'Preview Minisite',
            'minisite' => $minisite,
        ];

        $this->renderer->renderPreviewPage($data);
    }
}
