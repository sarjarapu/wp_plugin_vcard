<?php

namespace Minisite\Features\Authentication;

use Minisite\Features\Authentication\Hooks\AuthHooksFactory;

/**
 * Authentication Feature
 *
 * SINGLE RESPONSIBILITY: Bootstrap the Authentication feature
 * - Initializes the authentication system
 * - Registers all authentication hooks
 * - Provides a clean entry point for the feature
 */
final class AuthenticationFeature
{
    /**
     * Initialize the Authentication feature
     */
    public static function initialize(): void
    {
        $authHooks = AuthHooksFactory::create();
        $authHooks->register();
        
        // Register template_redirect handler immediately
        add_action('template_redirect', [$authHooks, 'handleAuthRoutes'], 5);
    }
}
