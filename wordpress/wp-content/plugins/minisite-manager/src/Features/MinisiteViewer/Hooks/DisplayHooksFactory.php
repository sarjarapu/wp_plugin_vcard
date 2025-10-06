<?php

namespace Minisite\Features\MinisiteViewer\Hooks;

use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\Handlers\DisplayHandler;
use Minisite\Features\MinisiteViewer\Services\MinisiteDisplayService;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;

/**
 * DisplayHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure DisplayHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete display system
 */
final class DisplayHooksFactory
{
    /**
     * Create and configure DisplayHooks
     */
    public static function create(): DisplayHooks
    {
        // Create services
        $wordPressManager = new WordPressMinisiteManager();
        $displayService = new MinisiteDisplayService($wordPressManager);

        // Create handlers
        $displayHandler = new DisplayHandler($displayService);

        // Create additional dependencies for refactored controller
        $requestHandler = new \Minisite\Features\MinisiteViewer\Http\DisplayRequestHandler();
        $responseHandler = new \Minisite\Features\MinisiteViewer\Http\DisplayResponseHandler();

        // Create the Timber renderer (following the same pattern as main plugin)
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(\Minisite\Application\Rendering\TimberRenderer::class)) {
            $timberRenderer = new \Minisite\Application\Rendering\TimberRenderer(MINISITE_DEFAULT_TEMPLATE ?? 'v2025');
        }

        $renderer = new \Minisite\Features\MinisiteViewer\Rendering\DisplayRenderer($timberRenderer);

        // Create controller
        $minisitePageController = new MinisitePageController(
            $displayHandler,
            $displayService,
            $requestHandler,
            $responseHandler,
            $renderer
        );

        // Create and return hooks
        return new DisplayHooks($minisitePageController);
    }
}
