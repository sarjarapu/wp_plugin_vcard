<?php

namespace Minisite\Features\PublishMinisite\Hooks;

use Minisite\Application\Rendering\TimberRenderer;
use Minisite\Features\PublishMinisite\Controllers\PublishController;
use Minisite\Features\PublishMinisite\Rendering\PublishRenderer;
use Minisite\Features\PublishMinisite\Services\PublishService;
use Minisite\Features\PublishMinisite\Services\ReservationService;
use Minisite\Features\PublishMinisite\Services\SlugAvailabilityService;
use Minisite\Features\PublishMinisite\Services\SubscriptionActivationService;
use Minisite\Features\PublishMinisite\Services\WooCommerceIntegration;
use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
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
        // Create termination handler for WordPress manager
        $terminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create WordPress manager (requires TerminationHandlerInterface)
        $wordPressManager = new WordPressPublishManager($terminationHandler);

        // Require Doctrine-based MinisiteRepository from global (initialized by PluginBootstrap)
        if (! isset($GLOBALS['minisite_repository'])) {
            throw new \RuntimeException(
                'MinisiteRepository not initialized. Ensure PluginBootstrap::initializeConfigSystem() is called.'
            );
        }
        $minisiteRepository = $GLOBALS['minisite_repository'];

        // Create services
        $slugAvailabilityService = new SlugAvailabilityService($wordPressManager, $minisiteRepository);
        $reservationService = new ReservationService($wordPressManager, $minisiteRepository);
        $subscriptionActivationService = new SubscriptionActivationService($wordPressManager, $minisiteRepository);
        $wooCommerceIntegration = new WooCommerceIntegration($wordPressManager, $subscriptionActivationService);
        $publishService = new PublishService(
            $wordPressManager,
            $minisiteRepository,
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
            $formSecurityHelper,
            $subscriptionActivationService,
            $reservationService
        );

        // Create termination handler for hook (separate instance for hook)
        $hookTerminationHandler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();

        // Create and return hooks
        return new PublishHooks(
            $publishController,
            $wordPressManager,
            $wooCommerceIntegration,
            $hookTerminationHandler
        );
    }
}
