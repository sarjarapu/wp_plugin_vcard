<?php

namespace Minisite\Features\NewMinisite\Hooks;

use Minisite\Features\NewMinisite\Controllers\NewMinisiteController;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Application\Rendering\TimberRenderer;

/**
 * NewMinisiteHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure NewMinisiteHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete new minisite system
 */
class NewMinisiteHooksFactory
{
    /**
     * Create and configure NewMinisiteHooks
     */
    public static function create(): NewMinisiteHooks
    {
        // Create WordPress manager
        $wordPressManager = new WordPressNewMinisiteManager();

        // Create service
        $newMinisiteService = new NewMinisiteService($wordPressManager);

        // Create renderer
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(TimberRenderer::class)) {
            $template = defined('MINISITE_DEFAULT_TEMPLATE') ? MINISITE_DEFAULT_TEMPLATE : 'v2025';
            $timberRenderer = new TimberRenderer($template);
        }
        $newMinisiteRenderer = new NewMinisiteRenderer($timberRenderer);

        // Create controller
        $newMinisiteController = new NewMinisiteController(
            $newMinisiteService,
            $newMinisiteRenderer,
            $wordPressManager
        );

        // Create and return hooks
        return new NewMinisiteHooks($newMinisiteController, $wordPressManager);
    }
}
