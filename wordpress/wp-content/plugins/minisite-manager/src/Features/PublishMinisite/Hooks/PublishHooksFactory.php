<?php

namespace Minisite\Features\PublishMinisite\Hooks;

use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Services\SlugAvailabilityService;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\Rendering\PublishRenderer;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Infrastructure\Security\FormSecurityHelper;

/**
 * PublishHooks Factory
 *
 * SINGLE RESPONSIBILITY: Create and configure PublishHooks with all dependencies
 * - Handles dependency injection
 * - Creates all required services and handlers
 * - Configures the complete publish system
 */
class PublishHooksFactory
{
    /**
     * Create and configure PublishHooks
     */
    public static function create(): PublishHooks
    {
        // Create WordPress manager
        $wordPressManager = new WordPressPublishManager();

        // Create services
        $slugAvailabilityService = new SlugAvailabilityService($wordPressManager);
        $reservationService = new ReservationService($wordPressManager);
        $publishService = new PublishService(
            $wordPressManager,
            $slugAvailabilityService,
            $reservationService
        );

        // Create renderer
        $timberRenderer = null;
        if (class_exists('Timber\Timber') && class_exists(TimberRenderer::class)) {
            $template = defined('MINISITE_DEFAULT_TEMPLATE') ? MINISITE_DEFAULT_TEMPLATE : 'v2025';
            $timberRenderer = new TimberRenderer($template);
        }
        $publishRenderer = new PublishRenderer($timberRenderer);

        // Create form security helper
        $formSecurityHelper = new FormSecurityHelper($wordPressManager);

        // Create controller
        $publishController = new PublishController(
            $publishService,
            $publishRenderer,
            $wordPressManager,
            $formSecurityHelper
        );

        // Create and return hooks
        return new PublishHooks($publishController, $wordPressManager);
    }
}

