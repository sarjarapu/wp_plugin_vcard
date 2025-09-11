<?php
require __DIR__ . '/../vendor/autoload.php';

// WP result-type constants used by $wpdb
if (!defined('OBJECT'))   define('OBJECT',   'OBJECT');
if (!defined('ARRAY_A'))  define('ARRAY_A',  'ARRAY_A');
if (!defined('ARRAY_N'))  define('ARRAY_N',  'ARRAY_N');
if (!defined('OBJECT_K')) define('OBJECT_K', 'OBJECT_K');

// (Optional) Brain Monkey setup if you use it
// use Brain\Monkey;
// Monkey\setUp();

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
}

/**
 * Minimal wpdb stub so PHPUnit can reflect/mocking works without WP.
 * Add only what your tests touch.
 */
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public int $rows_affected = 0;
        public int $insert_id = 0;

        public function prepare($query, ...$args) {
            foreach ($args as $a) {
                $query = preg_replace('/%[dfs]/', is_numeric($a) ? (string)$a : "'" . addslashes((string)$a) . "'", $query, 1);
            }
            return $query;
        }
        // Avoid defaulting to ARRAY_A: let tests pass it explicitly
        public function get_row($query, $output = null)      { return null; }
        public function get_results($query, $output = null)  { return []; }
        public function query($query)                        { $this->rows_affected = 0; return 0; }
        public function insert($table, $data, $format = [])  { $this->insert_id = 1; return 1; }
    }
}