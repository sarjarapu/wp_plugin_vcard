<?php
/**
 * Plugin Name: BizCard Simple
 * Description: Simple test following movies plugin exactly
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class BizCard_Simple {
    const CPT  = 'bizcard_profile';
    const SLUG = 'bizcard';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_filter('template_include', [$this, 'load_templates']);
    }

    public function register_cpt() {
        $args = [
            'labels' => [
                'name' => 'Business Profiles',
                'singular_name' => 'Business Profile',
            ],
            'public'             => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-businessperson',
            'supports'           => ['title', 'editor', 'excerpt', 'thumbnail'],
            'has_archive'        => true,
            'rewrite'            => ['slug' => self::SLUG],
        ];
        register_post_type(self::CPT, $args);
    }

    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function load_templates($template) {
        if (is_post_type_archive(self::CPT)) {
            $theme_template = locate_template(['archive-' . self::CPT . '.php']);
            if (!$theme_template) {
                return plugin_dir_path(__FILE__) . 'templates/archive-' . self::CPT . '.php';
            }
        }
        if (is_singular(self::CPT)) {
            $theme_template = locate_template(['single-' . self::CPT . '.php']);
            if (!$theme_template) {
                return plugin_dir_path(__FILE__) . 'templates/single-' . self::CPT . '.php';
            }
        }
        return $template;
    }
}

new BizCard_Simple();