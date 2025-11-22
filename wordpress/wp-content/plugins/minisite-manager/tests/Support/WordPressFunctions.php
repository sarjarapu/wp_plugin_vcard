<?php

/**
 * WordPress Function Mocks for Testing
 *
 * This file contains mock implementations of WordPress functions
 * needed for unit and integration tests. Functions are defined with
 * `if (! function_exists())` checks to prevent conflicts with Patchwork
 * and allow Brain Monkey to mock them when needed.
 *
 * These functions are loaded in bootstrap.php BEFORE Patchwork initializes
 * to ensure they exist for code that calls them directly.
 */

// Basic WordPress functions needed by the plugin
if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}

if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'http://localhost/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (! function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback)
    {
        // Mock - do nothing in tests
    }
}

if (! function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback)
    {
        // Mock - do nothing in tests
    }
}

if (! function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Store hooks in global $wp_filter for testing
        global $wp_filter;
        if (! isset($wp_filter)) {
            $wp_filter = new class {
                public $callbacks = array();
                public function __get($name)
                {
                    if ($name === 'callbacks') {
                        return $this->callbacks;
                    }
                    return null;
                }
            };
        }
        if (! isset($wp_filter->callbacks[$hook])) {
            $wp_filter->callbacks[$hook] = array();
        }
        if (! isset($wp_filter->callbacks[$hook][$priority])) {
            $wp_filter->callbacks[$hook][$priority] = array();
        }
        $wp_filter->callbacks[$hook][$priority][] = $callback;
    }
}

if (! function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Reuse add_action storage to simplify hook assertions
        add_action($hook, $callback, $priority, $accepted_args);
    }
}

if (! function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null)
    {
        if (! isset($GLOBALS['_test_admin_menus'])) {
            $GLOBALS['_test_admin_menus'] = array('menu' => array(), 'submenu' => array());
        }
        $GLOBALS['_test_admin_menus']['menu'][] = array(
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'icon_url' => $icon_url,
            'position' => $position,
        );

        return $menu_slug;
    }
}

if (! function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null)
    {
        if (! isset($GLOBALS['_test_admin_menus'])) {
            $GLOBALS['_test_admin_menus'] = array('menu' => array(), 'submenu' => array());
        }
        $GLOBALS['_test_admin_menus']['submenu'][] = array(
            'parent_slug' => $parent_slug,
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'position' => $position,
        );

        return $menu_slug;
    }
}

if (! function_exists('add_rewrite_tag')) {
    function add_rewrite_tag($tag, $regex, $query = '')
    {
        // Mock - do nothing in tests
    }
}

if (! function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $redirect, $after = 'bottom')
    {
        // Mock - do nothing in tests
    }
}

if (! function_exists('get_query_var')) {
    function get_query_var($var, $default = '')
    {
        // Check for test-specific mock override
        if (isset($GLOBALS['_test_mock_get_query_var'])) {
            $callback = $GLOBALS['_test_mock_get_query_var'];
            if (is_callable($callback)) {
                return $callback($var, $default);
            }
        }

        return $default;
    }
}

// WordPress utility functions
if (! function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return untrailingslashit($string) . '/';
    }
}

if (! function_exists('untrailingslashit')) {
    function untrailingslashit($string)
    {
        return rtrim($string, '/\\');
    }
}

if (! function_exists('get_template_directory')) {
    function get_template_directory()
    {
        return '/tmp/test-templates';
    }
}

if (! function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return '/tmp/test-styles';
    }
}

if (! function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri()
    {
        return 'http://example.com/style.css';
    }
}

if (! function_exists('get_template_uri')) {
    function get_template_uri()
    {
        return 'http://example.com/template.css';
    }
}

if (! function_exists('get_theme_root')) {
    function get_theme_root()
    {
        return '/tmp/themes';
    }
}

if (! function_exists('get_theme_root_uri')) {
    function get_theme_root_uri()
    {
        return 'http://example.com/themes';
    }
}

if (! function_exists('wp_get_theme')) {
    function wp_get_theme($stylesheet = null)
    {
        return new stdClass();
    }
}

if (! function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'Test Blog';
    }
}

if (! function_exists('wp_get_environment_type')) {
    function wp_get_environment_type()
    {
        return 'local';
    }
}

if (! function_exists('wp_is_serving_rest_request')) {
    function wp_is_serving_rest_request()
    {
        return false;
    }
}

if (! function_exists('wp_is_json_request')) {
    function wp_is_json_request()
    {
        return false;
    }
}

if (! function_exists('wp_is_xml_request')) {
    function wp_is_xml_request()
    {
        return false;
    }
}

if (! function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return false;
    }
}

if (! function_exists('wp_doing_cron')) {
    function wp_doing_cron()
    {
        return false;
    }
}

if (! function_exists('wp_doing_autosave')) {
    function wp_doing_autosave()
    {
        return false;
    }
}

if (! function_exists('is_admin')) {
    function is_admin()
    {
        return false;
    }
}

if (! function_exists('is_blog_admin')) {
    function is_blog_admin()
    {
        return false;
    }
}

if (! function_exists('is_network_admin')) {
    function is_network_admin()
    {
        return false;
    }
}

if (! function_exists('is_user_admin')) {
    function is_user_admin()
    {
        return false;
    }
}

if (! function_exists('wp_debug_mode')) {
    function wp_debug_mode()
    {
        return false;
    }
}

if (! function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '')
    {
        $user = new WP_User($id, $name ?: 'testuser');
        $user->user_email = 'testuser@example.com';

        return $user;
    }
}

if (! function_exists('wp_get_current_user_id')) {
    function wp_get_current_user_id()
    {
        return 1;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can($capability, ...$args)
    {
        // Check for test-specific mock override
        if (isset($GLOBALS['_test_mock_current_user_can'])) {
            $callback = $GLOBALS['_test_mock_current_user_can'];
            if (is_callable($callback)) {
                return $callback($capability, ...$args);
            }

            return $GLOBALS['_test_mock_current_user_can'];
        }

        return true; // Default: allow in tests
    }
}

if (! function_exists('user_can')) {
    function user_can($user, $capability, ...$args)
    {
        return true;
    }
}

if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all')
    {
        return true;
    }
}

if (! function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n)
    {
        return true;
    }
}

if (! function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = array(), $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (! function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = array(), $ver = false, $media = 'all')
    {
        return true;
    }
}

if (! function_exists('wp_head')) {
    function wp_head()
    {
        return '';
    }
}

if (! function_exists('wp_footer')) {
    function wp_footer()
    {
        return '';
    }
}

if (! function_exists('body_class')) {
    function body_class($class = '')
    {
        return 'class="' . $class . '"';
    }
}

if (! function_exists('language_attributes')) {
    function language_attributes($doctype = 'html')
    {
        return 'lang="en"';
    }
}

if (! function_exists('bloginfo')) {
    function bloginfo($show = '')
    {
        return 'Test Blog';
    }
}

if (! function_exists('apply_filters_deprecated')) {
    function apply_filters_deprecated($hook_name, $value, $version, $replacement = false, $message = '')
    {
        return $value;
    }
}

if (! function_exists('do_action_deprecated')) {
    function do_action_deprecated($hook_name, $args, $version, $replacement = false, $message = '')
    {
        return;
    }
}

if (! function_exists('_deprecated_function')) {
    function _deprecated_function($function, $version, $replacement = null)
    {
        return;
    }
}

if (! function_exists('_deprecated_argument')) {
    function _deprecated_argument($function, $version, $message = '')
    {
        return;
    }
}

if (! function_exists('_deprecated_file')) {
    function _deprecated_file($file, $version, $replacement = null, $message = '')
    {
        return;
    }
}

if (! function_exists('_deprecated_hook')) {
    function _deprecated_hook($hook, $version, $replacement = null, $message = '')
    {
        return;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        // Check for test-specific mock override
        if (isset($GLOBALS['_test_mock_sanitize_text_field'])) {
            return $GLOBALS['_test_mock_sanitize_text_field'];
        }

        // Simulate WordPress sanitization: trim, strip tags, and clean
        $str = trim($str);

        // Return empty string if result is empty after trim
        if ($str === '') {
            return '';
        }

        $str = strip_tags($str);
        $str = preg_replace('/[<>"\']/', '', $str);

        return $str ?: 'sanitized text';
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (! function_exists('sanitize_url')) {
    function sanitize_url($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        // Check for test-specific mock override
        if (isset($GLOBALS['_test_mock_wp_unslash'])) {
            return $GLOBALS['_test_mock_wp_unslash'];
        }

        // Handle arrays recursively
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        // Simulate WordPress unslash: remove slashes and return clean text
        if (is_string($value)) {
            $value = stripslashes($value);
            return $value ?: 'unslashed text';
        }

        return $value;
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1)
    {
        // Check for test-specific mock override (for Brain Monkey compatibility)
        if (isset($GLOBALS['_test_mock_wp_create_nonce'])) {
            $callback = $GLOBALS['_test_mock_wp_create_nonce'];
            if (is_callable($callback)) {
                return $callback($action);
            }

            return $GLOBALS['_test_mock_wp_create_nonce'];
        }

        // Default: return a predictable nonce based on action for testing
        return 'test_nonce_' . md5($action);
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        // Check for test-specific mock override
        if (isset($GLOBALS['_test_mock_wp_verify_nonce'])) {
            $callback = $GLOBALS['_test_mock_wp_verify_nonce'];
            if (is_callable($callback)) {
                return $callback($nonce, $action);
            }

            return $GLOBALS['_test_mock_wp_verify_nonce'];
        }

        return true; // Default: always return true in tests
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args)
    {
        return $value;
    }
}

if (! function_exists('do_action')) {
    function do_action($hook_name, ...$args)
    {
        return;
    }
}

if (! function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return 'http://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg($key, $value = null, $url = null)
    {
        // If $key is an array, merge all key-value pairs
        if (is_array($key)) {
            $args = $key;
            $url = $value;
        } else {
            $args = array($key => $value);
        }

        // Default URL is current request URI
        if ($url === null) {
            $url = 'http://example.com/wp-admin/admin.php';
        }

        // Parse URL
        $parsed = parse_url($url);
        $query = array();
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        // Merge new args
        $query = array_merge($query, $args);

        // Rebuild URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . $host . $path . '?' . http_build_query($query) . $fragment;
    }
}

if (! function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302)
    {
        // In tests, throw an exception instead of actually redirecting
        throw new Exception("Redirect to: $location (Status: $status)");
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0)
    {
        return date('Y-m-d H:i:s');
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return sanitize_text_field($str);
    }
}

if (! function_exists('wp_kses')) {
    function wp_kses($string, $allowed_html, $allowed_protocols = array())
    {
        // Simple mock implementation for testing
        return $string;
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        // Check for test-specific mock override (for Brain Monkey compatibility)
        if (isset($GLOBALS['_test_mock_get_current_user_id'])) {
            $callback = $GLOBALS['_test_mock_get_current_user_id'];
            if (is_callable($callback)) {
                return $callback();
            }

            return $GLOBALS['_test_mock_get_current_user_id'];
        }

        return 1;
    }
}

if (! function_exists('delete_option')) {
    function delete_option($option)
    {
        // Simple in-memory storage for testing
        if (! isset($GLOBALS['_test_options'])) {
            $GLOBALS['_test_options'] = array();
        }
        unset($GLOBALS['_test_options'][$option]);

        return true;
    }
}

if (! class_exists('WP_Role')) {
    class WP_Role
    {
        public string $name;
        public array $capabilities;

        public function __construct(string $name, array $capabilities = array())
        {
            $this->name = $name;
            $this->capabilities = $capabilities;
        }

        public function add_cap(string $cap): void
        {
            $this->capabilities[$cap] = true;
        }

        public function has_cap(string $cap): bool
        {
            return ! empty($this->capabilities[$cap]);
        }
    }
}

if (! function_exists('add_role')) {
    function add_role($role, $display_name, $capabilities = array())
    {
        if (! isset($GLOBALS['_test_roles'])) {
            $GLOBALS['_test_roles'] = array();
        }
        $roleObject = new WP_Role($display_name, $capabilities);
        $GLOBALS['_test_roles'][$role] = $roleObject;

        return $roleObject;
    }
}

if (! function_exists('get_role')) {
    function get_role($role)
    {
        if (! isset($GLOBALS['_test_roles'])) {
            $GLOBALS['_test_roles'] = array();
        }

        return $GLOBALS['_test_roles'][$role] ?? null;
    }
}

if (! function_exists('remove_role')) {
    function remove_role($role)
    {
        if (! isset($GLOBALS['_test_roles'])) {
            $GLOBALS['_test_roles'] = array();
        }

        unset($GLOBALS['_test_roles'][$role]);
    }
}

if (! function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        // Simple in-memory storage for testing
        if (! isset($GLOBALS['_test_options'])) {
            $GLOBALS['_test_options'] = array();
        }

        return $GLOBALS['_test_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        // Simple in-memory storage for testing
        if (! isset($GLOBALS['_test_options'])) {
            $GLOBALS['_test_options'] = array();
        }
        $GLOBALS['_test_options'][$option] = $value;

        return true;
    }
}

if (! function_exists('admin_url')) {
    function admin_url($path = '', $scheme = null)
    {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error')
    {
        // Store settings errors in a global array for testing
        if (! isset($GLOBALS['wp_settings_errors'])) {
            $GLOBALS['wp_settings_errors'] = array();
        }
        $GLOBALS['wp_settings_errors'][] = array(
            'setting' => $setting,
            'code' => $code,
            'message' => $message,
            'type' => $type,
        );
    }
}

if (! function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array())
    {
        throw new \Exception("wp_die called: $message");
    }
}

if (! function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true)
    {
        // Mock - do nothing in tests
    }
}

if (! function_exists('get_settings_errors')) {
    function get_settings_errors($setting = '', $sanitize = false)
    {
        if (! isset($GLOBALS['wp_settings_errors'])) {
            return array();
        }

        if (empty($setting)) {
            return $GLOBALS['wp_settings_errors'];
        }

        // Filter by setting if provided
        return array_filter(
            $GLOBALS['wp_settings_errors'],
            function ($error) use ($setting) {
                return $error['setting'] === $setting;
            }
        );
    }
}

