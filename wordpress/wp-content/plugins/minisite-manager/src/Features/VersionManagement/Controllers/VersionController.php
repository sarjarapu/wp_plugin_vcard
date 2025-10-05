<?php

namespace Minisite\Features\VersionManagement\Controllers;

use Minisite\Features\VersionManagement\Handlers\CreateDraftHandler;
use Minisite\Features\VersionManagement\Handlers\ListVersionsHandler;
use Minisite\Features\VersionManagement\Handlers\PublishVersionHandler;
use Minisite\Features\VersionManagement\Handlers\RollbackVersionHandler;
use Minisite\Features\VersionManagement\Http\VersionRequestHandler;
use Minisite\Features\VersionManagement\Http\VersionResponseHandler;
use Minisite\Features\VersionManagement\Rendering\VersionRenderer;
use Minisite\Features\VersionManagement\Services\VersionService;

/**
 * Controller for version management operations
 */
class VersionController
{
    public function __construct(
        private ListVersionsHandler $listVersionsHandler,
        private CreateDraftHandler $createDraftHandler,
        private PublishVersionHandler $publishVersionHandler,
        private RollbackVersionHandler $rollbackVersionHandler,
        private VersionRequestHandler $requestHandler,
        private VersionResponseHandler $responseHandler,
        private VersionRenderer $renderer,
        private VersionService $versionService
    ) {
    }

    /**
     * Handle listing versions
     */
    public function handleListVersions(): void
    {
        if (!is_user_logged_in()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ?
                sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $this->responseHandler->redirectToLogin($redirectUrl);
            return;
        }

        $command = $this->requestHandler->parseListVersionsRequest();
        if (!$command) {
            $this->responseHandler->redirectToSites();
            return;
        }

        try {
            $versions = $this->listVersionsHandler->handle($command);
            
            // Get minisite for rendering
            $minisite = $this->getMinisiteForRendering($command->siteId);
            if (!$minisite) {
                error_log('VersionManagement: Minisite not found for ID: ' . $command->siteId);
                $this->responseHandler->redirectToSites();
                return;
            }

            error_log('VersionManagement: Rendering version history for minisite: ' . $minisite->title);
            $this->renderer->renderVersionHistory([
                'page_title' => 'Version History: ' . $minisite->title,
                'profile' => $minisite,
                'versions' => $versions,
            ]);
        } catch (\Exception $e) {
            error_log('VersionManagement: Exception in handleListVersions: ' . $e->getMessage());
            $this->responseHandler->redirectToSites();
        }
    }

    /**
     * Handle creating draft
     */
    public function handleCreateDraft(): void
    {
        if (!is_user_logged_in()) {
            $this->responseHandler->sendJsonError('Not authenticated', 401);
            return;
        }

        $command = $this->requestHandler->parseCreateDraftRequest();
        if (!$command) {
            $this->responseHandler->sendJsonError('Invalid request', 400);
            return;
        }

        try {
            $version = $this->createDraftHandler->handle($command);
            
            $this->responseHandler->sendJsonSuccess([
                'id' => $version->id,
                'version_number' => $version->versionNumber,
                'status' => $version->status,
                'message' => 'Draft created successfully',
            ]);
        } catch (\Exception $e) {
            $this->responseHandler->sendJsonError('Failed to create draft: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle publishing version
     */
    public function handlePublishVersion(): void
    {
        if (!is_user_logged_in()) {
            $this->responseHandler->sendJsonError('Not authenticated', 401);
            return;
        }

        $command = $this->requestHandler->parsePublishVersionRequest();
        if (!$command) {
            $this->responseHandler->sendJsonError('Invalid request', 400);
            return;
        }

        try {
            $this->publishVersionHandler->handle($command);
            
            $this->responseHandler->sendJsonSuccess([
                'message' => 'Version published successfully',
                'published_version_id' => $command->versionId,
            ]);
        } catch (\Exception $e) {
            $this->responseHandler->sendJsonError('Failed to publish version: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle rollback version
     */
    public function handleRollbackVersion(): void
    {
        if (!is_user_logged_in()) {
            $this->responseHandler->sendJsonError('Not authenticated', 401);
            return;
        }

        $command = $this->requestHandler->parseRollbackVersionRequest();
        if (!$command) {
            $this->responseHandler->sendJsonError('Invalid request', 400);
            return;
        }

        try {
            $version = $this->rollbackVersionHandler->handle($command);
            
            $this->responseHandler->sendJsonSuccess([
                'id' => $version->id,
                'version_number' => $version->versionNumber,
                'status' => $version->status,
                'message' => 'Rollback draft created',
            ]);
        } catch (\Exception $e) {
            $this->responseHandler->sendJsonError('Failed to create rollback: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get minisite for rendering (helper method)
     */
    private function getMinisiteForRendering(string $siteId): ?object
    {
        return $this->versionService->getMinisiteForRendering($siteId);
    }
}
