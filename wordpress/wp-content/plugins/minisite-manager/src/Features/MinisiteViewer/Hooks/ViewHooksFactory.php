<?php

namespace Minisite\Features\MinisiteViewer\Hooks;

use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\Handlers\ViewHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;

/**
 * ViewHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure ViewHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete view system
 */
final class ViewHooksFactory
{
    /**
     * Create and configure ViewHooks
     */
    public static function create(): ViewHooks
    {
        // Create services
        $wordPressManager = new WordPressMinisiteManager();
        $viewService = new MinisiteViewService($wordPressManager);

        // Create handlers
        $viewHandler = new ViewHandler($viewService);

        // Create additional dependencies for refactored controller
        $requestHandler = new \Minisite\Features\MinisiteViewer\Http\ViewRequestHandler($wordPressManager);
        $responseHandler = new \Minisite\Features\MinisiteViewer\Http\ViewResponseHandler();

        // Create the Timber renderer (following the same pattern as main plugin)
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(\Minisite\Application\Rendering\TimberRenderer::class)) {
            $timberRenderer = new \Minisite\Application\Rendering\TimberRenderer(MINISITE_DEFAULT_TEMPLATE ?? 'v2025');
        }

        $renderer = new \Minisite\Features\MinisiteViewer\Rendering\ViewRenderer($timberRenderer, $wordPressManager);

        // Create controller
        $minisitePageController = new MinisitePageController(
            $viewHandler,
            $viewService,
            $requestHandler,
            $responseHandler,
            $renderer,
            $wordPressManager
        );

        // Create and return hooks
        return new ViewHooks($minisitePageController, $wordPressManager);
    }
}
