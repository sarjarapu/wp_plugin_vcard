<?php

/**
 * Flush() and Transaction Explanation Test
 *
 * Purpose: Demonstrate how flush() works with multiple persist() calls
 * and how it interacts with transactions and savepoints.
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;

echo "=== Flush() and Transaction Explanation ===\n\n";

// Define WordPress constants
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '/tmp/wp-content');
}

// Initialize LoggingServiceProvider
\Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

// Create EntityManager
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

echo "=== Scenario 1: Multiple persist() + Single flush() ===\n";
echo "This demonstrates batching - all persist() calls are executed in ONE flush()\n\n";

try {
    $connection->beginTransaction();
    echo "1. Transaction started\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

    // Multiple persist() calls - all queued
    echo "2. Creating and persisting multiple entities...\n";
    $config1 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config1->key = 'flush_test_1';
    $config1->value = 'value_1';
    $em->persist($config1);
    echo "   - persist(config1) - queued, NOT saved yet\n";

    $config2 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config2->key = 'flush_test_2';
    $config2->value = 'value_2';
    $em->persist($config2);
    echo "   - persist(config2) - queued, NOT saved yet\n";

    $config3 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config3->key = 'flush_test_3';
    $config3->value = 'value_3';
    $em->persist($config3);
    echo "   - persist(config3) - queued, NOT saved yet\n\n";

    echo "3. Calling flush() - NOW all 3 entities are saved in ONE batch\n";
    echo "   - Doctrine converts all 3 persist() calls into SQL\n";
    echo "   - All 3 INSERT statements run in the SAME transaction\n";
    echo "   - Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
    $em->flush();
    echo "   ✅ flush() completed - all 3 entities saved\n\n";

    echo "4. Transaction nesting level after flush: " . $connection->getTransactionNestingLevel() . "\n";
    echo "   - Still the SAME transaction (not nested)\n";
    echo "   - But Doctrine created a SAVEPOINT internally\n\n";

    $connection->rollBack();
    echo "5. Rolled back transaction - all 3 entities NOT saved\n\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    try {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e2) {
        // Ignore
    }
}

echo "\n=== Scenario 2: flush() with Existing Transaction ===\n";
echo "This demonstrates that flush() does NOT create a nested transaction\n";
echo "It uses SAVEPOINTS instead (markers within the same transaction)\n\n";

try {
    $connection->beginTransaction();
    echo "1. Outer transaction started\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

    echo "2. Calling flush() within existing transaction...\n";
    $config4 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config4->key = 'flush_test_4';
    $config4->value = 'value_4';
    $em->persist($config4);
    $em->flush();
    echo "   ✅ flush() completed\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
    echo "   - Still level 1 (NOT nested transaction)\n";
    echo "   - Doctrine created SAVEPOINT DOCTRINE_1 internally\n";
    echo "   - Savepoint is a MARKER, not a new transaction\n\n";

    echo "3. Calling flush() again...\n";
    $config5 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config5->key = 'flush_test_5';
    $config5->value = 'value_5';
    $em->persist($config5);
    $em->flush();
    echo "   ✅ flush() completed\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
    echo "   - Still level 1 (same transaction)\n";
    echo "   - Doctrine created SAVEPOINT DOCTRINE_2 internally\n\n";

    echo "4. Key Point: Savepoints are NOT nested transactions\n";
    echo "   - They're markers within the SAME transaction\n";
    echo "   - If you rollback, you rollback the ENTIRE transaction\n";
    echo "   - Savepoints just let Doctrine track its internal state\n\n";

    $connection->rollBack();
    echo "5. Rolled back - entire transaction undone\n\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    try {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e2) {
        // Ignore
    }
}

echo "\n=== Scenario 3: Nested Transactions (Manual) ===\n";
echo "This shows what REAL nested transactions look like\n\n";

try {
    $connection->beginTransaction();
    echo "1. Outer transaction started\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

    $connection->beginTransaction();
    echo "2. Inner transaction started (nested)\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n";
    echo "   - MySQL doesn't support true nested transactions\n";
    echo "   - Doctrine creates a SAVEPOINT for the inner transaction\n\n";

    $config6 = new \Minisite\Features\ConfigurationManagement\Domain\Entities\Config();
    $config6->key = 'flush_test_6';
    $config6->value = 'value_6';
    $em->persist($config6);
    $em->flush();
    echo "3. flush() called in nested transaction\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

    $connection->rollBack();
    echo "4. Rolled back inner transaction (rolls back to savepoint)\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

    $connection->rollBack();
    echo "5. Rolled back outer transaction\n";
    echo "   Transaction nesting level: " . $connection->getTransactionNestingLevel() . "\n\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    try {
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    } catch (\Exception $e2) {
        // Ignore
    }
}

echo "\n=== Summary ===\n";
echo "1. Multiple persist() + single flush() = ALL operations batched into ONE transaction\n";
echo "2. flush() does NOT create nested transactions - it uses SAVEPOINTS\n";
echo "3. Savepoints are MARKERS within the same transaction, not separate transactions\n";
echo "4. If you have an outer transaction, flush() works WITHIN it using savepoints\n";
echo "5. Real nested transactions (beginTransaction() twice) also use savepoints\n";
echo "   because MySQL doesn't support true nested transactions\n\n";

// Cleanup
try {
    $connection->executeStatement("DELETE FROM wp_minisite_config WHERE config_key LIKE 'flush_test%'");
} catch (\Exception $e) {
    // Ignore
}

echo "=== Test Complete ===\n";

