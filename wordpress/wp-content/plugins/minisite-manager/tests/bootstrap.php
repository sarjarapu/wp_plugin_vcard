<?php

// BypassFinals disabled - we've removed final from classes that need mocking
// This prevents Patchwork conflicts and is no longer needed
// if (class_exists('DG\BypassFinals')) {
//     DG\BypassFinals::enable();
// }

/**
 * PHPUnit Bootstrap File
 *
 * This file sets up the test environment before any tests run.
 * It defines WordPress constants and loads test support classes.
 *
 * Note: WordPress function mocks are now handled by individual test classes
 * for better isolation and maintainability.
 */

// Define WordPress constants needed for testing
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Define WordPress database constants
if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (! defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (! defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (! defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (! defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

if (! defined('SCRIPT_DEBUG')) {
    define('SCRIPT_DEBUG', false);
}

if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

if (! defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', 'http://localhost/wp-content');
}

if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/tmp/wp-content/plugins');
}

if (! defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', 'http://localhost/wp-content/plugins');
}

if (! defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', '/tmp/wp-content/mu-plugins');
}

if (! defined('WPMU_PLUGIN_URL')) {
    define('WPMU_PLUGIN_URL', 'http://localhost/wp-content/mu-plugins');
}

// Define WordPress database constants for Doctrine
if (! defined('DB_HOST')) {
    define('DB_HOST', getenv('MYSQL_HOST') ?: '127.0.0.1');
}

if (! defined('DB_USER')) {
    define('DB_USER', getenv('MYSQL_USER') ?: 'minisite');
}

if (! defined('DB_PASSWORD')) {
    define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'minisite');
}

if (! defined('DB_NAME')) {
    define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'minisite_test');
}

// Define plugin constants
if (! defined('MINISITE_PLUGIN_DIR')) {
    define('MINISITE_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// WordPress classes (minimal stubs)
if (! class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $last_error = '';
        public $last_query = '';
        public $last_result = array();
        public $num_rows = 0;
        public $insert_id = 0;
        public $rows_affected = 0;

        public function get_results($query, $output = null)
        {
            return array();
        }

        public function get_row($query, $output = null)
        {
            return null;
        }

        public function get_var($query)
        {
            return null;
        }

        public function query($query)
        {
            return 0;
        }

        public function prepare($query, ...$args)
        {
            return $query;
        }

        public function insert($table, $data, $format = null)
        {
            return false;
        }

        public function update($table, $data, $where, $format = null, $where_format = null)
        {
            return false;
        }

        public function delete($table, $where, $where_format = null)
        {
            return false;
        }

        public function get_charset_collate()
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '')
        {
            if (empty($code)) {
                return;
            }
            $this->errors[$code][] = $message;
            if (! empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_message($code = '')
        {
            if (empty($code)) {
                // Return first error message if no code specified
                foreach ($this->errors as $error_code => $messages) {
                    return $messages[0] ?? '';
                }

                return '';
            }

            return $this->errors[$code][0] ?? '';
        }
    }
}

if (! class_exists('WP_User')) {
    class WP_User
    {
        public $ID;
        public $user_login;
        public $user_email;
        public $user_pass;
        public $user_nicename;
        public $user_url;
        public $user_registered;
        public $user_activation_key;
        public $user_status;
        public $display_name;
        public $spam;
        public $deleted;
        public $locale;
        public $cap_key;
        public $caps;
        public $allcaps;
        public $filter;

        public function __construct($id = 0, $name = '', $site_id = '')
        {
            $this->ID = $id;
            $this->user_login = $name;
            $this->user_email = $name . '@example.com';
            $this->user_pass = '';
            $this->user_nicename = $name;
            $this->user_url = '';
            $this->user_registered = '2023-01-01 00:00:00';
            $this->user_activation_key = '';
            $this->user_status = 0;
            $this->display_name = $name;
            $this->spam = 0;
            $this->deleted = 0;
            $this->locale = '';
            $this->cap_key = 'wp_capabilities';
            $this->caps = array();
            $this->allcaps = array();
            $this->filter = null;
        }

        public function set_role($role)
        {
            // Mock implementation - just return true
            return true;
        }
    }
}

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
        // Mock - do nothing in tests
    }
}

if (! function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Mock - do nothing in tests
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

// WordPress utility functions (defined outside get_query_var function)
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
        return true;
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

        // Simulate WordPress unslash: remove slashes and return clean text
        $value = stripslashes($value);

        return $value ?: 'unslashed text';
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        return true; // Always return true in tests
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
    function admin_url($path = '')
    {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array())
    {
        throw new \Exception("wp_die called: $message");
    }
}

// Mock global $wp_query
$GLOBALS['wp_query'] = new class () {
    public function set_404()
    {
        return true;
    }
};

// Mock Timber\Timber class for testing
if (! class_exists('Timber\Timber')) {
    eval('
        namespace Timber {
            class Timber {
                public static function render($template, $context = array())
                {
                    // Output mock HTML based on context
                    $output = "<div class=\"auth-page\">";
                    if (isset($context["page_title"])) {
                        $output .= "<h1>" . htmlspecialchars($context["page_title"]) . "</h1>";
                    }
                    if (isset($context["error_msg"])) {
                        $output .= "<div class=\"error\">" . htmlspecialchars($context["error_msg"]) . "</div>";
                    }
                    if (isset($context["success_msg"])) {
                        $output .= "<div class=\"success\">" . htmlspecialchars($context["success_msg"]) . "</div>";
                    }
                    $output .= "</div>";
                    echo $output;
                }
            }
        }
    ');
}

if (! function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true)
    {
        // Mock - do nothing in tests
    }
}

// Load test support classes
require_once __DIR__ . '/Support/FakeWpdb.php';
require_once __DIR__ . '/Support/TestDatabaseUtils.php';

// Load the main plugin file to get autoloader
require_once __DIR__ . '/../minisite-manager.php';
