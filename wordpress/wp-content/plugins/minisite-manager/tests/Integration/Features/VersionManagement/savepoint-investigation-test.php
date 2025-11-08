<?php

/**
 * Savepoint Investigation Test
 *
 * Purpose: Verify Doctrine's automatic savepoint creation and test fixes
 *
 * Hypothesis:
 * 1. Doctrine creates savepoints automatically when flush() is called within a transaction
 * 2. After migrations, savepoint state can be corrupted
 * 3. Closing connection clears all savepoints (connection-scoped)
 *
 * This test file is isolated - we'll verify the fix here before rolling out to the codebase.
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;

echo "=== Savepoint Investigation Test ===\n\n";

// Define WordPress constants (required for logging)
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

// Initialize LoggingServiceProvider (required for migrations)
\Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

// Step 1: Create EntityManager (same pattern as integration tests)
$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '3307';
$dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
$user = getenv('MYSQL_USER') ?: 'minisite';
$pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

$connection = DriverManager::getConnection(array(
    'driver' => 'pdo_mysql',
    'host' => $host,
    'port' => (int)$port,
    'user' => $user,
    'password' => $pass,
    'dbname' => $dbName,
    'charset' => 'utf8mb4',
));

$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: array(
        __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
        __DIR__ . '/../../../../src/Features/ReviewManagement/Domain/Entities',
        __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
    ),
    isDevMode: true
);

$em = new EntityManager($connection, $config);

// Set up WordPress prefix
if (! isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class () {
        public string $prefix = 'wp_';
    };
}

$tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
$em->getEventManager()->addEventListener(Events::loadClassMetadata, $tablePrefixListener);

echo "Step 1: Initial state\n";
echo "  - Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Auto-commit: " . ($connection->isAutoCommit() ? 'YES' : 'NO') . "\n\n";

// Step 2: Run migrations (simulating test setup)
echo "Step 2: Running migrations...\n";
$migrationRunner = new DoctrineMigrationRunner($em);
$migrationRunner->migrate();
echo "  - Migrations completed\n";
echo "  - Transaction active after migrations: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Auto-commit after migrations: " . ($connection->isAutoCommit() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

// Step 2.5: Simulate the problematic scenario - start a transaction AFTER migrations
// This is what happens in real tests - they start transactions after migrations
echo "Step 2.5: Starting transaction AFTER migrations (simulating test scenario)...\n";
$connection->beginTransaction();
echo "  - Transaction started\n";
echo "  - Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

// Step 3: Call flush() within the existing transaction (this should create a savepoint)
echo "Step 3: Testing savepoint creation with flush() in existing transaction\n";
echo "  - Transaction already active from Step 2.5\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";

// Try to check if savepoints exist (MySQL doesn't have a direct way, but we can try to use one)
echo "  - Attempting flush() within transaction (this should create a savepoint)...\n";

try {
    // We need an entity to flush - let's use Config entity
    $configRepo = $em->getRepository(\Minisite\Features\ConfigurationManagement\Domain\Entities\Config::class);

    // Create a test config
    $testConfig = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $testConfig->key = 'savepoint_test';
    $testConfig->value = 'test_value';

    $em->persist($testConfig);
    echo "  - Calling flush()...\n";
    $em->flush();
    echo "  - flush() completed successfully\n";

    // Check if we can see savepoints (MySQL doesn't expose this easily, but we can try)
    echo "  - Checking savepoint state...\n";

    // Now try another flush() - this should create another savepoint
    echo "  - Attempting second flush() (should create another savepoint)...\n";
    $testConfig2 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $testConfig2->key = 'savepoint_test_2';
    $testConfig2->value = 'test_value_2';
    $em->persist($testConfig2);
    $em->flush();
    echo "  - Second flush() completed\n";

    // Now try to rollback - this is where the error might occur
    echo "  - Rolling back transaction (this might trigger savepoint error)...\n";
    $connection->rollBack();
    echo "  - Rollback completed successfully\n";

} catch (\Exception $e) {
    echo "  - ERROR: " . $e->getMessage() . "\n";
    echo "  - Error type: " . get_class($e) . "\n";

    // Try to clean up
    try {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e2) {
        // Ignore
    }
}

echo "\n";

// Step 4: Simulate the problematic scenario - multiple operations
// Step 4: Testing WITHOUT closing connection - this should reproduce the error
echo "Step 4: Testing WITHOUT closing connection (should reproduce savepoint error)\n";
echo "  - Note: We still have the connection from Step 2.5\n";

try {
    // Start a new transaction (nested or new)
    if (! $connection->isTransactionActive()) {
        $connection->beginTransaction();
        echo "  - New transaction started\n";
    } else {
        echo "  - Transaction already active (nesting level: " . $connection->getTransactionNestingLevel() . ")\n";
    }

    // First flush
    $testConfig2 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $testConfig2->key = 'savepoint_test_2';
    $testConfig2->value = 'test_value_2';
    $em->persist($testConfig2);
    $em->flush();
    echo "  - First flush() completed\n";

    // Second flush (should create another savepoint)
    $testConfig3 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $testConfig3->key = 'savepoint_test_3';
    $testConfig3->value = 'test_value_3';
    $em->persist($testConfig3);
    $em->flush();
    echo "  - Second flush() completed\n";

    // Now try to rollback to a specific savepoint (this is where errors occur)
    echo "  - Attempting rollback...\n";
    $connection->rollBack();
    echo "  - Rollback completed successfully\n";

} catch (\Exception $e) {
    echo "  - ERROR: " . $e->getMessage() . "\n";
    echo "  - This is the error we're trying to fix!\n";

    // Clean up
    try {
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e2) {
        // Ignore
    }
}

echo "\n";

// Step 5: Test the fix - close connection
echo "Step 5: Testing fix - close connection\n";

try {
    echo "  - Closing connection...\n";
    $connection->close();
    echo "  - Connection closed\n";

    // EntityManager should auto-reconnect
    echo "  - Testing if EntityManager auto-reconnects...\n";
    $connection = $em->getConnection();
    echo "  - Connection restored: " . ($connection->isConnected() ? 'YES' : 'NO') . "\n";

    // Now try the same operation again
    echo "  - Testing flush() after connection close...\n";
    $connection->beginTransaction();

    $testConfig4 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $testConfig4->key = 'savepoint_test_4';
    $testConfig4->value = 'test_value_4';
    $em->persist($testConfig4);
    $em->flush();
    echo "  - flush() after connection close: SUCCESS\n";

    $connection->rollBack();
    echo "  - Rollback after connection close: SUCCESS\n";

} catch (\Exception $e) {
    echo "  - ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Step 6: Clean up test data
echo "Step 6: Cleaning up test data...\n";

try {
    $connection->executeStatement("DELETE FROM wp_minisite_config WHERE config_key LIKE 'savepoint_test%'");
    echo "  - Test data cleaned up\n";
} catch (\Exception $e) {
    echo "  - Cleanup error (ignored): " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
