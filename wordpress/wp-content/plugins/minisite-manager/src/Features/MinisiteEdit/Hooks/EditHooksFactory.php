<?php

namespace Minisite\Features\MinisiteEdit\Hooks;

use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\Rendering\EditRenderer;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\MinisiteViewer\Hooks\ViewHooksFactory;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * EditHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure EditHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete edit system
 */
class EditHooksFactory
{
    /**
     * Create and configure EditHooks
     */
    public static function create(): EditHooks
    {
        // Create WordPress manager
        $wordPressManager = new WordPressEditManager();

        // Create service
        $editService = new EditService($wordPressManager);

        // Create renderer
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(TimberRenderer::class)) {
            $template = defined('MINISITE_DEFAULT_TEMPLATE') ? MINISITE_DEFAULT_TEMPLATE : 'v2025';
            $timberRenderer = new TimberRenderer($template);
        }
        $editRenderer = new EditRenderer($timberRenderer);

        // Create form security helper
        $formSecurityHelper = new FormSecurityHelper($wordPressManager);

        // Create controller
        $terminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();
        
        $editController = new EditController(
            $editService,
            $editRenderer,
            $wordPressManager,
            $formSecurityHelper,
            $terminationHandler
        );

        // Get MinisiteViewer controller for preview delegation
        $viewHooks = ViewHooksFactory::create();
        $minisiteViewerController = $viewHooks->getController();

        // Create and return hooks
        return new EditHooks($editController, $wordPressManager, $minisiteViewerController);
    }
}
