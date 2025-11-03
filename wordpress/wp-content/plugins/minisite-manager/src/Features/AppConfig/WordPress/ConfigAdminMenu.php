<?php

namespace Minisite\Features\AppConfig\WordPress;

/**
 * Register Configuration admin menu
 */
class ConfigAdminMenu
{
    public static function register(): void
    {
        add_action('admin_menu', function () {
            // Add submenu under existing Minisite Manager menu
            add_submenu_page(
                'minisite-manager', // Parent slug (existing minisite admin menu)
                'Configuration', // Page title
                'Configuration', // Menu title
                'manage_options', // Capability (minisite admin only - using manage_options for now)
                'minisite-config', // Menu slug
                [self::class, 'renderPage'] // Callback
            );
        });
        
        // Register admin_post handler for delete action
        add_action('admin_post_minisite_config_delete', function() {
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            
            // Verify nonce
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'minisite_config_delete')) {
                wp_die('Security check failed');
            }
            
            $key = sanitize_text_field($_GET['key'] ?? '');
            if (empty($key)) {
                wp_die('Invalid configuration key');
            }
            
            if (!isset($GLOBALS['minisite_config_manager'])) {
                wp_die('ConfigManager not initialized');
            }
            
            $configManager = $GLOBALS['minisite_config_manager'];
            $configManager->delete($key);
            
            wp_redirect(add_query_arg([
                'page' => 'minisite-config',
                'deleted' => '1'
            ], admin_url('admin.php')));
            exit;
        });
    }
    
    public static function renderPage(): void
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }
        
        $controller = new ConfigAdminController();
        $controller->handleRequest();
        $controller->render();
    }
}

