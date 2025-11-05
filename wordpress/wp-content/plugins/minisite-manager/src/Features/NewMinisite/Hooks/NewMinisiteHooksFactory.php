<?php

namespace Minisite\Features\NewMinisite\Hooks;

use Minisite\Features\NewMinisite\Controllers\NewMinisiteController;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Infrastructure\Security\FormSecurityHelper;

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
        // Create termination handler for WordPress manager
        $terminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create WordPress manager (requires TerminationHandlerInterface)
        $wordPressManager = new WordPressNewMinisiteManager($terminationHandler);

        // Create service
        $newMinisiteService = new NewMinisiteService($wordPressManager);

        // Create renderer
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(TimberRenderer::class)) {
            $template = defined('MINISITE_DEFAULT_TEMPLATE') ? MINISITE_DEFAULT_TEMPLATE : 'v2025';
            $timberRenderer = new TimberRenderer($template);
        }
        $newMinisiteRenderer = new NewMinisiteRenderer($timberRenderer);

        // Create form security helper
        $formSecurityHelper = new FormSecurityHelper($wordPressManager);

        // Create controller
        $newMinisiteController = new NewMinisiteController(
            $newMinisiteService,
            $newMinisiteRenderer,
            $wordPressManager,
            $formSecurityHelper
        );

        // Create termination handler for hook (separate instance for hook)
        $hookTerminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create and return hooks
        return new NewMinisiteHooks($newMinisiteController, $wordPressManager, $hookTerminationHandler);
    }
}
