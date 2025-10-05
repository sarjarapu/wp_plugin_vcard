<?php

/**
 * PHPUnit Bootstrap File
 *
 * This file sets up the test environment before any tests run.
 * It defines WordPress constants, loads test support classes, and mocks WordPress functions.
 */

// Enable BypassFinals to allow mocking of final classes
if (class_exists('DG\BypassFinals')) {
    DG\BypassFinals::enable();
}

// Define WordPress constants needed for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

if (!defined('SCRIPT_DEBUG')) {
    define('SCRIPT_DEBUG', false);
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', 'http://localhost/wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/tmp/wp-content/plugins');
}

if (!defined('WP_PLUGIN_URL')) {
    define('WP_PLUGIN_URL', 'http://localhost/wp-content/plugins');
}

if (!defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', '/tmp/wp-content/mu-plugins');
}

if (!defined('WPMU_PLUGIN_URL')) {
    define('WPMU_PLUGIN_URL', 'http://localhost/wp-content/mu-plugins');
}

// Define plugin constants needed for testing
if (!defined('MINISITE_PLUGIN_DIR')) {
    define('MINISITE_PLUGIN_DIR', __DIR__ . '/../');
}

// Mock WordPress global variables
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public function get_charset_collate()
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
        }
        public function prepare($query, ...$args)
        {
            return $query;
        }
        public function get_var($query)
        {
            return null;
        }
        public function get_row($query)
        {
            return null;
        }
        public function get_results($query)
        {
            return [];
        }
        public function query($query)
        {
            return 0;
        }
        public function insert($table, $data, $format = [])
        {
            return 1;
        }
        public function update($table, $data, $where, $format = [], $where_format = [])
        {
            return 1;
        }
        public function delete($table, $where, $where_format = [])
        {
            return 1;
        }
    };
}

// Database connection stubs
if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public function get_charset_collate()
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
        }
        public function prepare($query, ...$args)
        {
            return $query;
        }
        public function get_var($query)
        {
            return null;
        }
        public function get_row($query)
        {
            return null;
        }
        public function get_results($query)
        {
            return [];
        }
        public function query($query)
        {
            return 0;
        }
        public function insert($table, $data, $format = [])
        {
            return 1;
        }
        public function update($table, $data, $where, $format = [], $where_format = [])
        {
            return 1;
        }
        public function delete($table, $where, $where_format = [])
        {
            return 1;
        }
    }
}

// Load test support classes (so tests can `use Tests\Support\...` without Composer autoload-dev)
require_once __DIR__ . '/Support/FakeWpdb.php';

// Option storage stubs for versioning tests
if (!function_exists('get_option')) {
    $GLOBALS['__test_options'] = [];
    function get_option($key, $default = false)
    {
        return $GLOBALS['__test_options'][$key] ?? $default;
    }
    function update_option($key, $value)
    {
        $GLOBALS['__test_options'][$key] = $value;
        return true;
    }
    function delete_option($key)
    {
        unset($GLOBALS['__test_options'][$key]);
        return true;
    }
}

// WordPress function stubs
if (!function_exists('current_time')) {
    function current_time($type = 'mysql')
    {
        return date('Y-m-d H:i:s');
    }
}

// Additional WordPress functions needed for Authentication tests
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        return true; // Default to valid for tests
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return $str;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return $email;
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url($url)
    {
        return $url;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null)
    {
        return 'http://localhost' . $path;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302)
    {
        // In tests, we'll just throw an exception to simulate redirect
        throw new Exception('Redirect to: ' . $location);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('wp_signon')) {
    function wp_signon($credentials = array(), $secure_cookie = '')
    {
        // Mock successful login - return WP_User object
        return new WP_User(1, 'testuser');
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email = '')
    {
        // Mock successful user creation
        return 2;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        // Mock user found - return WP_User object
        // Use the value as the ID if field is 'id', otherwise use 1
        $id = ($field === 'id') ? (int)$value : 1;
        $username = ($field === 'id') ? 'user' . $id : $value;
        return new WP_User($id, $username);
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '')
    {
        return true;
    }
}

if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie($user_id, $remember = false, $secure = '')
    {
        return true;
    }
}

if (!function_exists('retrieve_password')) {
    function retrieve_password($user_login)
    {
        return true;
    }
}

if (!function_exists('wp_logout')) {
    function wp_logout()
    {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return true;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        return new WP_User(1, 'testuser');
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($var, $default = '')
    {
        return $default;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args)
    {
        // For tests, just return the value unchanged
        return $value;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10)
    {
        return true;
    }
}

if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook, $priority = false)
    {
        return true;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($hook, $callback = false)
    {
        return false;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args)
    {
        return true;
    }
}

if (!function_exists('do_action_ref_array')) {
    function do_action_ref_array($hook, $args)
    {
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook, $callback, $priority = 10)
    {
        return true;
    }
}

if (!function_exists('remove_all_actions')) {
    function remove_all_actions($hook, $priority = false)
    {
        return true;
    }
}

if (!function_exists('has_action')) {
    function has_action($hook, $callback = false)
    {
        return false;
    }
}

if (!function_exists('apply_filters_deprecated')) {
    function apply_filters_deprecated($hook, $args, $version, $replacement = '', $message = '')
    {
        // For tests, just return the first argument unchanged
        // This handles the case where args is an array like [$locs]
        return is_array($args) ? $args[0] : $args;
    }
}

if (!function_exists('do_action_deprecated')) {
    function do_action_deprecated($hook, $args, $version, $replacement = '', $message = '')
    {
        return true;
    }
}

if (!function_exists('_deprecated_function')) {
    function _deprecated_function($function, $version, $replacement = '')
    {
        return true;
    }
}

if (!function_exists('_deprecated_argument')) {
    function _deprecated_argument($function, $version, $message = '')
    {
        return true;
    }
}

if (!function_exists('_deprecated_file')) {
    function _deprecated_file($file, $version, $replacement = '', $message = '')
    {
        return true;
    }
}

if (!function_exists('_deprecated_hook')) {
    function _deprecated_hook($hook, $version, $replacement = '', $message = '')
    {
        return true;
    }
}

if (!function_exists('status_header')) {
    function status_header($code, $description = '')
    {
        return true;
    }
}

if (!function_exists('get_template_part')) {
    function get_template_part($slug, $name = null)
    {
        return true;
    }
}

if (!function_exists('get_template_directory')) {
    function get_template_directory()
    {
        return '/tmp/templates';
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return '/tmp/stylesheets';
    }
}

if (!function_exists('get_stylesheet_uri')) {
    function get_stylesheet_uri()
    {
        return 'http://localhost/wp-content/themes/default/style.css';
    }
}

if (!function_exists('get_template_uri')) {
    function get_template_uri()
    {
        return 'http://localhost/wp-content/themes/default';
    }
}

if (!function_exists('get_theme_root')) {
    function get_theme_root()
    {
        return '/tmp/themes';
    }
}

if (!function_exists('get_theme_root_uri')) {
    function get_theme_root_uri()
    {
        return 'http://localhost/wp-content/themes';
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme($stylesheet = null, $theme_root = null)
    {
        return new class {
            public $Name = 'Test Theme';
            public $Version = '1.0.0';
            
            public function get_stylesheet() {
                return 'test-theme';
            }
            
            public function get_template() {
                return 'test-theme';
            }
        };
    }
}

if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        $info = [
            'name' => 'Test Site',
            'description' => 'Test Site Description',
            'url' => 'http://localhost',
            'admin_email' => 'admin@localhost',
            'charset' => 'UTF-8',
            'version' => '6.0',
            'language' => 'en-US'
        ];
        return $info[$show] ?? '';
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type()
    {
        return 'local';
    }
}

if (!function_exists('wp_is_serving_rest_request')) {
    function wp_is_serving_rest_request()
    {
        return false;
    }
}

if (!function_exists('wp_is_json_request')) {
    function wp_is_json_request()
    {
        return false;
    }
}

if (!function_exists('wp_is_xml_request')) {
    function wp_is_xml_request()
    {
        return false;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return false;
    }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron()
    {
        return false;
    }
}

if (!function_exists('wp_doing_autosave')) {
    function wp_doing_autosave()
    {
        return false;
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
    {
        return false;
    }
}

if (!function_exists('is_blog_admin')) {
    function is_blog_admin()
    {
        return false;
    }
}

if (!function_exists('is_network_admin')) {
    function is_network_admin()
    {
        return false;
    }
}

if (!function_exists('is_user_admin')) {
    function is_user_admin()
    {
        return false;
    }
}

if (!function_exists('wp_debug_mode')) {
    function wp_debug_mode()
    {
        return false;
    }
}

// Remove duplicate definitions - these are already defined above

if (!function_exists('wp_get_current_user_id')) {
    function wp_get_current_user_id()
    {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args)
    {
        return true; // Default to allowing everything in tests
    }
}

if (!function_exists('user_can')) {
    function user_can($user, $capability, ...$args)
    {
        return true; // Default to allowing everything in tests
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = [])
    {
        throw new Exception('wp_die: ' . $message);
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null, $options = 0)
    {
        throw new Exception('wp_send_json: ' . json_encode($response));
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $options = 0)
    {
        throw new Exception('wp_send_json_success: ' . json_encode($data));
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $options = 0)
    {
        throw new Exception('wp_send_json_error: ' . json_encode($data));
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '')
    {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r =& $args;
        } else {
            wp_parse_str($args, $r);
        }

        if (is_array($defaults)) {
            return array_merge($defaults, $r);
        }
        return $r;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array)
    {
        parse_str($string, $array);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = [])
    {
        return ['body' => 'mock response', 'response' => ['code' => 200]];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = [])
    {
        return ['body' => 'mock response', 'response' => ['code' => 200]];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) && isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) && isset($response['response']['code']) ? $response['response']['code'] : 0;
    }
}


// Mock Timber class globally for all tests
eval('
    namespace Timber {
        class Timber {
            public static $locations = [];
            
            public static function render($template, $context) {
                // Mock implementation - output test HTML based on context
                $pageTitle = $context["page_title"] ?? "Authentication";
                $errorMsg = $context["error_msg"] ?? "";
                $successMsg = $context["success_msg"] ?? "";
                
                echo "<!doctype html><html><head><title>" . htmlspecialchars($pageTitle) . "</title></head><body>";
                echo "<h1>" . htmlspecialchars($pageTitle) . "</h1>";
                
                if ($errorMsg) {
                    echo "<div class=\"error\">" . htmlspecialchars($errorMsg) . "</div>";
                }
                
                if ($successMsg) {
                    echo "<div class=\"success\">" . htmlspecialchars($successMsg) . "</div>";
                }
                
                echo "</body></html>";
            }
        }
    }
');

// urlencode is a built-in PHP function, no need to define it

if (!function_exists('defined')) {
    function defined($name)
    {
        return false;
    }
}

// Additional WordPress functions that might be needed
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
    {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n)
    {
        return true;
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all')
    {
        return true;
    }
}

if (!function_exists('wp_head')) {
    function wp_head()
    {
        return true;
    }
}

if (!function_exists('wp_footer')) {
    function wp_footer()
    {
        return true;
    }
}

if (!function_exists('body_class')) {
    function body_class($class = '')
    {
        return 'class="test-class"';
    }
}

if (!function_exists('language_attributes')) {
    function language_attributes($doctype = 'html')
    {
        return 'lang="en-US"';
    }
}

if (!function_exists('bloginfo')) {
    function bloginfo($show = '')
    {
        echo get_bloginfo($show);
    }
}

// get_bloginfo already defined above

// WordPress classes
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $errors = [];
        public $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '')
        {
            if (empty($code)) {
                return;
            }
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public $ID = 0;
        public $user_login = '';
        public $user_email = '';
        
        public function __construct($id = 0, $name = '', $site_id = '')
        {
            if ($id) {
                $this->ID = $id;
                $this->user_login = 'user' . $id;
                $this->user_email = 'user' . $id . '@example.com';
            }
        }
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 1;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return rtrim($string, '/') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string)
    {
        return rtrim($string, '/');
    }
}

// Define WordPress database constants
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
