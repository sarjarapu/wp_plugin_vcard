<?php
require __DIR__ . '/../vendor/autoload.php';

// Ensure WP result-type constants exist when WP isn't loaded.
if (!defined('OBJECT'))   define('OBJECT', 'OBJECT');
if (!defined('ARRAY_A'))  define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N'))  define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT_K')) define('OBJECT_K', 'OBJECT_K');

// (Optional) Brain Monkey setup if you use it
// use Brain\Monkey;
// Monkey\setUp();

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
}

// Provide a simple current_time() replacement for tests
if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        $ts = time();
        if ($type === 'timestamp') return $ts;
        // mimic WP's mysql format
        return gmdate('Y-m-d H:i:s', $ts);
    }
}

// Minimal wpdb stub if none exists yet:
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
        public function get_row($query, $output = null) { return null; }
        public function get_results($query, $output = null) { return []; }
        public function get_var($query) { return null; }
        public function query($query) { return 0; }
        public function insert($table, $data, $format = []) { $this->insert_id = 1; return 1; }
    }
}

// Load test support classes (so tests can `use Tests\Support\...` without Composer autoload-dev)
require_once __DIR__ . '/Support/FakeWpdb.php';
require_once __DIR__ . '/Support/SchemaLoader.php';
// Shadow VersionRepository with a test stub for unit tests
require_once __DIR__ . '/Support/Stubs/VersionRepositoryStub.php';

// Option storage stubs for versioning tests
if (!function_exists('get_option')) {
    $GLOBALS['__test_options'] = [];
    function get_option($key, $default = false) {
        return $GLOBALS['__test_options'][$key] ?? $default;
    }
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['__test_options'][$key] = $value;
        return true;
    }
}
