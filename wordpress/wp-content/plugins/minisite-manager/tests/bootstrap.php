<?php

/**
 * PHPUnit Bootstrap File
 *
 * This file sets up the test environment before any tests run.
 * It defines WordPress constants and loads test support classes.
 * 
 * Note: WordPress function mocks are now handled by individual test classes
 * for better isolation and maintainability.
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

// MINISITE_PLUGIN_DIR is defined in the main plugin file

// WordPress classes (minimal stubs)
if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $last_error = '';
        public $last_query = '';
        public $last_result = [];
        public $num_rows = 0;
        public $insert_id = 0;
        public $rows_affected = 0;
        
        public function get_results($query, $output = null)
        {
            return [];
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
    }
}

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

if (!class_exists('WP_User')) {
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
            $this->caps = [];
            $this->allcaps = [];
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
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'http://localhost/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback)
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback)
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('add_rewrite_tag')) {
    function add_rewrite_tag($tag, $regex, $query = '')
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $redirect, $after = 'bottom')
    {
        // Mock - do nothing in tests
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($var, $default = '')
    {
        return $default;
    }
    
    function trailingslashit($string)
    {
        return untrailingslashit($string) . '/';
}

    function untrailingslashit($string)
    {
        return rtrim($string, '/\\');
    }
    
    function get_template_directory()
    {
        return '/tmp/test-templates';
    }
    
    function get_stylesheet_directory()
    {
        return '/tmp/test-styles';
    }
    
    function get_stylesheet_uri()
    {
        return 'http://example.com/style.css';
    }
    
    function get_template_uri()
    {
        return 'http://example.com/template.css';
    }
    
    function get_theme_root()
    {
        return '/tmp/themes';
    }
    
    function get_theme_root_uri()
    {
        return 'http://example.com/themes';
    }
    
    function wp_get_theme($stylesheet = null)
    {
        return new stdClass();
    }
    
    function get_locale()
    {
        return 'en_US';
    }
    
    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'Test Blog';
    }
    
    function wp_get_environment_type()
    {
        return 'local';
    }
    
    function wp_is_serving_rest_request()
    {
        return false;
    }
    
    function wp_is_json_request()
    {
        return false;
    }
    
    function wp_is_xml_request()
    {
        return false;
    }
    
    function wp_doing_ajax()
    {
        return false;
    }
    
    function wp_doing_cron()
    {
        return false;
    }
    
    function wp_doing_autosave()
    {
        return false;
    }
    
    function is_admin()
    {
        return false;
    }
    
    function is_blog_admin()
    {
        return false;
    }
    
    function is_network_admin()
    {
        return false;
    }
    
    function is_user_admin()
    {
        return false;
    }
    
    function wp_debug_mode()
    {
        return false;
    }
    
    function wp_set_current_user($id, $name = '')
    {
        $user = new WP_User($id, $name ?: 'testuser');
        $user->user_email = 'testuser@example.com';
        return $user;
    }
    
    function wp_get_current_user_id()
    {
        return 1;
    }
    
    function current_user_can($capability, ...$args)
    {
        return true;
    }
    
    function user_can($user, $capability, ...$args)
    {
        return true;
    }
    
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false)
    {
        return true;
    }
    
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all')
    {
        return true;
    }
    
    function wp_localize_script($handle, $object_name, $l10n)
    {
        return true;
    }
    
    function wp_register_script($handle, $src, $deps = array(), $ver = false, $in_footer = false)
    {
        return true;
    }
    
    function wp_register_style($handle, $src, $deps = array(), $ver = false, $media = 'all')
    {
        return true;
    }
    
    function wp_head()
    {
        return '';
    }
    
    function wp_footer()
    {
        return '';
    }
    
    function body_class($class = '')
    {
        return 'class="' . $class . '"';
    }
    
    function language_attributes($doctype = 'html')
    {
        return 'lang="en"';
    }
    
    function bloginfo($show = '')
    {
        return 'Test Blog';
    }
    
    function apply_filters_deprecated($hook_name, $value, $version, $replacement = false, $message = '')
    {
        return $value;
    }
    
    function do_action_deprecated($hook_name, $args, $version, $replacement = false, $message = '')
    {
        return;
    }
    
    function _deprecated_function($function, $version, $replacement = null)
    {
        return;
    }
    
    function _deprecated_argument($function, $version, $message = '')
    {
        return;
    }
    
    function _deprecated_file($file, $version, $replacement = null, $message = '')
    {
        return;
    }
    
    function _deprecated_hook($hook, $version, $replacement = null, $message = '')
    {
        return;
    }
    
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
    
    function sanitize_email($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
    
    function sanitize_url($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
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
    
    function wp_verify_nonce($nonce, $action = -1)
    {
        return true; // Always return true in tests
    }
    
    function apply_filters($hook_name, $value, ...$args)
    {
        return $value;
    }
    
    function do_action($hook_name, ...$args)
    {
        return;
    }
    
    function home_url($path = '', $scheme = null)
    {
        return 'http://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
    
    function wp_redirect($location, $status = 302)
    {
        // In tests, throw an exception instead of actually redirecting
        throw new Exception("Redirect to: $location (Status: $status)");
    }
    
    // Mock global $wp_query
    $GLOBALS['wp_query'] = new class {
        public function set_404() {
            return true;
        }
    };
}

// Mock Timber\Timber class for testing
if (!class_exists('Timber\Timber')) {
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

if (!function_exists('flush_rewrite_rules')) {
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