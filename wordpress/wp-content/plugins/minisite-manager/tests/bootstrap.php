<?php

/**
 * PHPUnit Bootstrap File
 *
 * This file sets up the test environment before any tests run.
 * It defines WordPress constants, loads test support classes, and mocks WordPress functions.
 */

// Define WordPress constants needed for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
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
