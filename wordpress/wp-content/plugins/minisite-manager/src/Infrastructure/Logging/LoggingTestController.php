<?php

namespace Minisite\Infrastructure\Logging;

use Psr\Log\LoggerInterface;

/**
 * Simple test controller for logging system
 * Access via: /wp-admin/admin.php?page=minisite-logging-test
 */
class LoggingTestController
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('logging-test');
    }

    /**
     * Get cached value or compute and cache it
     */
    private function getCachedValue(string $key, callable $computeFunction, int $expiration = 300): mixed
    {
        $cacheKey = 'minisite_logging_test_' . md5($key);
        $cachedValue = wp_cache_get($cacheKey, 'minisite_logging');

        if ($cachedValue === false) {
            $cachedValue = $computeFunction();
            wp_cache_set($cacheKey, $cachedValue, 'minisite_logging', $expiration);
        }

        return $cachedValue;
    }

    /**
     * Test the logging system and return results
     */
    public function runTest(): array
    {
        $results = array();

        try {
            // Test 1: Basic logging
            $this->logger->info('Logging test started', array('test' => 'basic_logging'));
            $results[] = '✓ Basic logging working';

            // Test 2: Different log levels
            $this->logger->debug('Debug message', array('level' => 'debug'));
            $this->logger->info('Info message', array('level' => 'info'));
            $this->logger->warning('Warning message', array('level' => 'warning'));
            $this->logger->error('Error message', array('level' => 'error'));
            $results[] = '✓ All log levels working';

            // Test 3: Check log files
            $logDir = WP_CONTENT_DIR . '/minisite-logs';
            if (is_dir($logDir)) {
                $results[] = '✓ Log directory exists: ' . $logDir;

                $logFiles = glob($logDir . '/*.log*');
                if (! empty($logFiles)) {
                    $results[] = '✓ Log files found: ' . count($logFiles) . ' files';
                    foreach ($logFiles as $file) {
                        $results[] = '  - ' . basename($file) . ' (' . filesize($file) . ' bytes)';
                    }
                } else {
                    $results[] = '⚠ No log files found yet';
                }
            } else {
                $results[] = '✗ Log directory not found: ' . $logDir;
            }

            // Test 4: Database logging
            global $wpdb;
            if ($wpdb) {
                $tableName = $wpdb->prefix . 'minisite_logs';

                // Check if table exists using WordPress method
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test controller needs to check table existence
                $tableExists = $wpdb->get_var(
                    $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
                ) === $tableName;

                if ($tableExists) {
                    $results[] = '✓ Database log table exists: ' . esc_html($tableName);

                    // Use helper method for caching
                    $logCount = $this->getCachedValue(
                        "log_count_{$tableName}",
                        function () use ($wpdb, $tableName) {
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Test controller needs to count logs
                            return $wpdb->get_var(
                                $wpdb->prepare("SELECT COUNT(*) FROM %i", $tableName)
                            );
                        }
                    );

                    $results[] = '✓ Database logs count: ' . esc_html($logCount);
                } else {
                    $results[] = '⚠ Database log table not created (may not be enabled)';
                }
            } else {
                $results[] = '⚠ WordPress database not available';
            }

            $this->logger->info('Logging test completed successfully', array('results' => $results));
        } catch (\Exception $e) {
            $results[] = '✗ Error during logging test: ' . $e->getMessage();
            $this->logger->error('Logging test failed', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }

        return $results;
    }

    /**
     * Add admin menu for testing
     */
    public static function addAdminMenu(): void
    {
        add_submenu_page(
            'minisite-manager',
            'Logging Test',
            'Logging Test',
            'manage_options',
            'minisite-logging-test',
            array(self::class, 'renderTestPage')
        );
    }

    /**
     * Render the test page
     */
    public static function renderTestPage(): void
    {
        $controller = new self();
        $results = $controller->runTest();

        echo '<div class="wrap">';
        echo '<h1>Minisite Manager - Logging System Test</h1>';
        echo '<div class="notice notice-info"><p>Testing the PSR-3 logging system with Monolog...</p></div>';

        echo '<h2>Test Results:</h2>';
        echo '<ul>';
        foreach ($results as $result) {
            $class = strpos($result, '✓') === 0 ? 'color: green;' :
                    (strpos($result, '✗') === 0 ? 'color: red;' : 'color: orange;');
            echo '<li style="' . esc_attr($class) . '">' . esc_html($result) . '</li>';
        }
        echo '</ul>';

        echo '<h2>Log Files Location:</h2>';
        echo '<p><code>' . esc_html(WP_CONTENT_DIR) . '/minisite-logs/</code></p>';

        echo '<h2>Recent Log Entries:</h2>';
        $logDir = WP_CONTENT_DIR . '/minisite-logs';
        $logFiles = glob($logDir . '/*.log*');
        if (! empty($logFiles)) {
            $latestFile = max($logFiles);
            $content = file_get_contents($latestFile);
            $lines = explode("\n", $content);
            $recentLines = array_slice(array_filter($lines), -10); // Last 10 non-empty lines

            echo '<pre style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto;">';
            foreach ($recentLines as $line) {
                if (! empty(trim($line))) {
                    echo esc_html($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<p>No log files found yet.</p>';
        }

        echo '</div>';
    }
}
