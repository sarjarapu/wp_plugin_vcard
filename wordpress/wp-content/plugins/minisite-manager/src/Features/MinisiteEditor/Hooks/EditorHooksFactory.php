<?php

namespace Minisite\Features\MinisiteEditor\Hooks;

use Minisite\Features\MinisiteEditor\Controllers\SitesController;
use Minisite\Features\MinisiteEditor\Controllers\NewMinisiteController;
use Minisite\Features\MinisiteEditor\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteEditor\Handlers\CreateMinisiteHandler;
use Minisite\Features\MinisiteEditor\Handlers\EditMinisiteHandler;
use Minisite\Features\MinisiteEditor\Handlers\PreviewMinisiteHandler;
use Minisite\Features\MinisiteEditor\Services\MinisiteEditorService;
use Minisite\Features\MinisiteEditor\WordPress\WordPressMinisiteManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;

/**
 * EditorHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure EditorHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete editor system
 */
final class EditorHooksFactory
{
    /**
     * Create and configure EditorHooks
     */
    public static function create(): EditorHooks
    {
        global $wpdb;

        // Create repositories
        $minisiteRepository = new MinisiteRepository($wpdb);
        $versionRepository = new VersionRepository($wpdb);

        // Create services
        $wordPressManager = new WordPressMinisiteManager($minisiteRepository, $versionRepository);
        $editorService = new MinisiteEditorService($wordPressManager);

        // Create handlers
        $listMinisitesHandler = new ListMinisitesHandler($editorService);
        $createMinisiteHandler = new CreateMinisiteHandler($editorService);
        $editMinisiteHandler = new EditMinisiteHandler($editorService);
        $previewMinisiteHandler = new PreviewMinisiteHandler($editorService);

        // Create additional dependencies for refactored controllers
        $requestHandler = new \Minisite\Features\MinisiteEditor\Http\EditorRequestHandler();
        $responseHandler = new \Minisite\Features\MinisiteEditor\Http\EditorResponseHandler();
        $renderer = new \Minisite\Features\MinisiteEditor\Rendering\EditorRenderer();

        // Create controllers
        $sitesController = new SitesController(
            $listMinisitesHandler,
            $editMinisiteHandler,
            $previewMinisiteHandler,
            $editorService,
            $requestHandler,
            $responseHandler,
            $renderer
        );

        $newMinisiteController = new NewMinisiteController(
            $createMinisiteHandler,
            $editorService,
            $requestHandler,
            $responseHandler,
            $renderer
        );

        // Create and return hooks
        return new EditorHooks($sitesController, $newMinisiteController);
    }
}
