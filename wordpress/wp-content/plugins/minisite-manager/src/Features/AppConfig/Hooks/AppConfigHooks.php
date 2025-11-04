<?php

namespace Minisite\Features\AppConfig\Hooks;

use Minisite\Features\AppConfig\Controllers\AppConfigController;
use Minisite\Features\AppConfig\Commands\DeleteConfigCommand;
use Minisite\Features\AppConfig\Handlers\DeleteConfigHandler;

/**
 * AppConfigHooks
 *
 * SINGLE RESPONSIBILITY: WordPress hook registration and routing for configuration management
 * - Registers WordPress admin menu hooks
 * - Handles admin page rendering
 * - Delegates to controller
 */
final class AppConfigHooks
{
    public function __construct(
        private AppConfigController $controller,
        private DeleteConfigHandler $deleteHandler
    ) {
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_post_minisite_config_delete', [$this, 'handleDeleteAction']);
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        // Add submenu under existing Minisite Manager menu
        add_submenu_page(
            'minisite-manager', // Parent slug (existing minisite admin menu)
            'Configuration', // Page title
            'Configuration', // Menu title
            'manage_options', // Capability
            'minisite-config', // Menu slug
            [$this, 'renderPage'] // Callback
        );
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $this->controller->handleRequest();
        $this->controller->render();
    }

    /**
     * Handle delete action via admin_post
     */
    public function handleDeleteAction(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Verify nonce
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'minisite_config_delete')) {
            wp_die('Security check failed');
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        if (empty($key)) {
            wp_die('Invalid configuration key');
        }

        // Create command and handle via handler
        $command = new DeleteConfigCommand($key);
        $this->deleteHandler->handle($command);

        wp_redirect(add_query_arg([
            'page' => 'minisite-config',
            'deleted' => '1'
        ], admin_url('admin.php')));
        exit;
    }
}
