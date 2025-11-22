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

// Load WordPress function mocks
require_once __DIR__ . '/Support/WordPressFunctions.php';
require_once __DIR__ . '/Support/ApplicationRenderingTestStubs.php';

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
                public static array $locations = array();
                public static array $renderCalls = array();

                public static function render($template, $context = array())
                {
                    self::$renderCalls[] = array(
                        'templates' => (array) $template,
                        'context' => $context,
                    );

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

// Load test support classes
require_once __DIR__ . '/Support/FakeWpdb.php';
require_once __DIR__ . '/Support/TestDatabaseUtils.php';

// Load the main plugin file to get autoloader
require_once __DIR__ . '/../minisite-manager.php';
