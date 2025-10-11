<?php
/**
 * Test script to verify NewMinisite logging is working
 * Access via: http://localhost:8000/wp-content/plugins/minisite-manager/test-new-minisite-logging.php
 */

// Bootstrap WordPress
require_once __DIR__ . '/../../../wp-config.php';

// Load the plugin
require_once __DIR__ . '/minisite-manager.php';

use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;

echo "<h1>NewMinisite Logging Test</h1>";
echo "<p>Testing the logging system for NewMinisite feature...</p>";

try {
    // Test 1: Basic logging
    echo "<h2>1. Testing Basic Logging</h2>";
    $logger = LoggingServiceProvider::getFeatureLogger('new-minisite-test');
    $logger->info('NewMinisite logging test started', [
        'test_type' => 'basic_logging',
        'feature' => 'NewMinisite',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    echo "✓ Basic logging test completed<br>";

    // Test 2: Test NewMinisiteService logging
    echo "<h2>2. Testing NewMinisiteService Logging</h2>";
    $wordPressManager = new WordPressNewMinisiteManager();
    $newMinisiteService = new NewMinisiteService($wordPressManager);
    
    // Test with empty form data
    $testFormData = [
        'business_name' => 'Test Business',
        'business_city' => 'Test City',
        'minisite_edit_nonce' => wp_create_nonce('minisite_edit')
    ];
    
    $logger->info('About to test NewMinisiteService::createNewMinisite()', [
        'feature' => 'NewMinisite',
        'test_form_data' => $testFormData
    ]);
    
    // This will likely fail due to validation, but we'll see the logging
    $result = $newMinisiteService->createNewMinisite($testFormData);
    
    $logger->info('NewMinisiteService::createNewMinisite() completed', [
        'feature' => 'NewMinisite',
        'result_success' => $result->success ?? false,
        'result_errors' => $result->errors ?? []
    ]);
    
    echo "✓ NewMinisiteService logging test completed<br>";
    echo "Result: " . ($result->success ? 'Success' : 'Failed') . "<br>";
    if (!empty($result->errors)) {
        echo "Errors: " . implode(', ', $result->errors) . "<br>";
    }

    // Test 3: Check log files
    echo "<h2>3. Checking Log Files</h2>";
    $logDir = WP_CONTENT_DIR . '/minisite-logs';
    if (is_dir($logDir)) {
        echo "✓ Log directory exists: $logDir<br>";
        
        $logFiles = glob($logDir . '/*.log*');
        if (!empty($logFiles)) {
            echo "✓ Log files found: " . count($logFiles) . " files<br>";
            foreach ($logFiles as $file) {
                $size = filesize($file);
                $modified = date('Y-m-d H:i:s', filemtime($file));
                echo "  - " . basename($file) . " ($size bytes, modified: $modified)<br>";
            }
            
            // Show recent log entries
            echo "<h3>Recent Log Entries:</h3>";
            $latestFile = max($logFiles);
            $content = file_get_contents($latestFile);
            $lines = explode("\n", $content);
            $recentLines = array_slice(array_filter($lines), -10); // Last 10 non-empty lines
            
            echo "<pre style='background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto;'>";
            foreach ($recentLines as $line) {
                if (!empty(trim($line))) {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo "</pre>";
        } else {
            echo "⚠ No log files found yet<br>";
        }
    } else {
        echo "✗ Log directory not found: $logDir<br>";
    }

    echo "<h2>4. Test Summary</h2>";
    echo "<p>✅ Logging system test completed successfully!</p>";
    echo "<p>📁 Check log files in: <code>$logDir</code></p>";
    echo "<p>🔍 Look for entries with <code>\"feature\":\"NewMinisite\"</code> to verify correct feature usage</p>";

} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>Error during logging test: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
