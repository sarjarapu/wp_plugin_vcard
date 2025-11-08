<?php

/**
 * Reproduce Savepoint Error Test
 *
 * Purpose: REPRODUCE the actual savepoint error that occurs in the codebase
 *
 * Strategy:
 * 1. Run migrations (this might create savepoints internally)
 * 2. DO NOT close connection (simulating what happens if we remove the close)
 * 3. Try to use flush() - this should trigger the savepoint error
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;

echo "=== Reproducing Savepoint Error ===\n\n";

// Define WordPress constants
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

// Initialize LoggingServiceProvider
\Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

// Create EntityManager (same as integration tests)
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
echo "  - Auto-commit: " . ($connection->isAutoCommit() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

// Step 2: Run migrations MULTIPLE TIMES (this might cause the issue)
echo "Step 2: Running migrations...\n";
echo "  - Running migrations first time...\n";
$migrationRunner = new DoctrineMigrationRunner($em);
$migrationRunner->migrate();
echo "  - First migration completed\n";
echo "  - Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";

// Try running migrations again (simulating what happens in tests that run multiple times)
echo "\n  - Running migrations second time (simulating test re-run)...\n";
$migrationRunner2 = new DoctrineMigrationRunner($em);
$migrationRunner2->migrate();
echo "  - Second migration completed\n";
echo "  - Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";

// Step 2.5: Force the corrupted state by creating nested transactions
// This simulates what might happen if migrations leave the connection in a bad state
echo "\nStep 2.5: Simulating corrupted savepoint state...\n";
try {
    // Create nested transactions to increase nesting level
    $connection->beginTransaction();
    echo "  - Started transaction (nesting level: " . $connection->getTransactionNestingLevel() . ")\n";

    // Create a savepoint (like Doctrine does internally)
    $connection->executeStatement('SAVEPOINT test_sp_1');
    echo "  - Created savepoint test_sp_1\n";

    $connection->beginTransaction();
    echo "  - Started nested transaction (nesting level: " . $connection->getTransactionNestingLevel() . ")\n";

    // Now rollback - this should leave nesting level > 0 but savepoints might be gone
    $connection->rollBack();
    echo "  - Rolled back (nesting level: " . $connection->getTransactionNestingLevel() . ")\n";

    // Try to rollback again - this might fail if savepoint is gone
    try {
        $connection->rollBack();
        echo "  - Rolled back again (nesting level: " . $connection->getTransactionNestingLevel() . ")\n";
    } catch (\Exception $e) {
        echo "  - Rollback error (this is the corrupted state): " . $e->getMessage() . "\n";
        echo "  - Nesting level: " . $connection->getTransactionNestingLevel() . "\n";
    }
} catch (\Exception $e) {
    echo "  - Error during state corruption: " . $e->getMessage() . "\n";
}

echo "  - Final nesting level: " . $connection->getTransactionNestingLevel() . "\n";
echo "  - Transaction active: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n\n";

// Step 3: Try to clean up transactions (but NOT close connection)
echo "Step 3: Cleaning up transactions (but NOT closing connection)...\n";
try {
    while ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
} catch (\Exception $e) {
    echo "  - Rollback error (ignored): " . $e->getMessage() . "\n";
}
echo "  - Transaction active after cleanup: " . ($connection->isTransactionActive() ? 'YES' : 'NO') . "\n";
echo "  - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
echo "  - Connection still open (NOT closed)\n\n";

// Step 4: Clear EntityManager
echo "Step 4: Clearing EntityManager...\n";
$em->clear();
echo "  - EntityManager cleared\n\n";

// Step 5: NOW try to use flush() - this should trigger the savepoint error
echo "Step 5: Attempting to use flush() WITHOUT closing connection...\n";
echo "  - This simulates what happens in real tests if we don't close connection\n";
echo "  - If savepoint state is corrupted, this will fail\n\n";

$errorCount = 0;
$successCount = 0;

// Try multiple operations to see if we can reproduce the error
for ($i = 1; $i <= 30; $i++) {
    try {
        echo "  Attempt $i: ";

        // Start a transaction
        $connection->beginTransaction();

        // Create and persist entity
        $testConfig = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
        $testConfig->key = "reproduce_test_$i";
        $testConfig->value = "value_$i";

        $em->persist($testConfig);

        // This is where the savepoint error should occur
        $em->flush();

        // If we get here, flush() succeeded
        $connection->rollBack();
        $successCount++;
        echo "SUCCESS\n";

    } catch (\Exception $e) {
        $errorCount++;
        $errorMessage = $e->getMessage();

        if (strpos($errorMessage, 'SAVEPOINT') !== false) {
            echo "SAVEPOINT ERROR: " . $errorMessage . "\n";
            echo "    - Error type: " . get_class($e) . "\n";
            echo "    - This is the error we're trying to reproduce!\n";

            // Try to clean up
            try {
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (\Exception $e2) {
                // Ignore
            }

            // Break on first error to see the pattern
            break;
        } else {
            echo "OTHER ERROR: " . $errorMessage . "\n";

            // Try to clean up
            try {
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }
}

echo "\n=== Results ===\n";
echo "  - Successful operations: $successCount\n";
echo "  - Errors: $errorCount\n";

if ($errorCount > 0) {
    echo "\n✅ SUCCESS: Reproduced the savepoint error!\n";
    echo "   The error occurs when:\n";
    echo "   1. Migrations run (might create savepoints)\n";
    echo "   2. Connection is NOT closed\n";
    echo "   3. flush() is called - tries to create savepoint\n";
    echo "   4. Savepoint state is corrupted → ERROR\n";
} else {
    echo "\n❌ Could not reproduce the error with this approach\n";
    echo "   Trying alternative approach...\n\n";

    // Alternative: Try to simulate what happens when migrations create savepoints
    // and then we try to use them
    echo "=== Alternative Approach: Simulating Migration Savepoints ===\n\n";

    try {
        // Manually create a savepoint (like migrations might do)
        echo "Step A: Manually creating savepoint (simulating migration behavior)...\n";
        $connection->beginTransaction();
        $connection->executeStatement('SAVEPOINT test_savepoint_1');
        echo "  - Savepoint created\n";

        // Now rollback the transaction (but savepoint might still be in connection state)
        $connection->rollBack();
        echo "  - Transaction rolled back\n";
        echo "  - But savepoint state might still be in connection\n\n";

        // Now try to use flush() - Doctrine will try to create its own savepoint
        echo "Step B: Trying flush() - Doctrine will try to create SAVEPOINT DOCTRINE_1...\n";
        $connection->beginTransaction();

        $testConfig = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
        $testConfig->key = 'reproduce_test_alt';
        $testConfig->value = 'value_alt';
        $em->persist($testConfig);

        $em->flush();  // This should trigger the error
        echo "  - flush() succeeded (no error)\n";

        $connection->rollBack();

    } catch (\Exception $e) {
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, 'SAVEPOINT') !== false) {
            echo "  ✅ SAVEPOINT ERROR REPRODUCED: " . $errorMessage . "\n";
        } else {
            echo "  - Other error: " . $errorMessage . "\n";
        }
    }
}

// Cleanup
try {
    $connection->executeStatement("DELETE FROM wp_minisite_config WHERE config_key LIKE 'reproduce_test%'");
} catch (\Exception $e) {
    // Ignore
}

echo "\n=== Test Complete ===\n";

