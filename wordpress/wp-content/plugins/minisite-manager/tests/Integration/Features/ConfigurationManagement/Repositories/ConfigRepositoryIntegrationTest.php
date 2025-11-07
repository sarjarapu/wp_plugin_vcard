<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for ConfigRepository
 *
 * Tests ConfigRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisite_config table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ConfigRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Get database configuration from environment (same as AbstractDoctrineMigrationTest)
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        // Create real MySQL connection via Doctrine (same as AbstractDoctrineMigrationTest)
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ]);

        // Create EntityManager with MySQL connection (same as AbstractDoctrineMigrationTest)
        // Use the new feature-based entity path
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
                __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
            ],
            isDevMode: true
        );

        $this->em = new EntityManager($connection, $config);

        // Reset connection state to ensure clean transaction state
        // This prevents savepoint/transaction errors from previous tests
        try {
            // Clear any existing savepoints and transactions by executing ROLLBACK
            // This is safe even if no transaction is active
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore - connection might already be clean or ROLLBACK might not be needed
        }

        // Ensure connection is ready for new operations
        try {
            // Reset any savepoint counter by starting and immediately committing a transaction
            $connection->beginTransaction();
            $connection->commit();
        } catch (\Exception $e) {
            // If this fails, try to rollback
            try {
                $connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore - just continue
            }
        }

        // Clear any UnitOfWork state
        $this->em->clear();

        // Set up $wpdb object for TablePrefixListener (needed for prefix)
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';

        // Add TablePrefixListener (required for wp_minisite_config table)
        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        // Drop tables and migration tracking to ensure clean slate
        // This ensures migrations will run fresh every time
        $this->cleanupTables();

        // Ensure migrations have run (table exists)
        // Now that tables are dropped, migrations will run fresh
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Reset connection state again after migrations (migrations may leave connection in bad state)
        // This is critical because migrations might leave the connection in an inconsistent state
        try {
            // Rollback any active transactions
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (\Exception $e) {
            // If rollback fails, try to execute a direct ROLLBACK
            try {
                $connection->executeStatement('ROLLBACK');
            } catch (\Exception $e2) {
                // Ignore - connection might already be clean
            }
        }

        // Clear EntityManager state again after migrations
        // This ensures any UnitOfWork state from migrations is cleared
        $this->em->clear();

        // Force a fresh connection state by closing and letting it reconnect
        // This is the most reliable way to ensure clean state
        try {
            $connection->close();
        } catch (\Exception $e) {
            // Ignore - connection might already be closed
        }

        // EntityManager will automatically reconnect when needed

        // Get repository (automatically uses wp_minisite_config via TablePrefixListener)
        // Use the new feature-based repository
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);

        // Clean up test data (but keep table structure)
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data (but keep table structure)
        $this->cleanupTestData();

        // Clear EntityManager state and close connection properly
        try {
            $this->em->clear();
            $connection = $this->em->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }

        $this->em->close();
        parent::tearDown();
    }

    /**
     * Drop tables and migration tracking to ensure clean slate
     * This ensures migrations can run fresh before each test
     */
    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = ['wp_minisite_config', 'wp_minisite_migrations'];

        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
            } catch (\Exception $e) {
                // Ignore errors - table might not exist
            }
        }
    }

    /**
     * Clean up test data (but keep table structure)
     * Deletes only test configs, not the table itself
     */
    private function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%' OR config_key IN ('to_delete', 'encrypted_key', 'alpha', 'zebra')"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_save_and_find_config(): void
    {
        $config = new Config();
        $config->key = 'test_key';
        $config->type = 'string';
        $config->setTypedValue('test_value');

        $saved = $this->repository->save($config);

        $this->assertNotNull($saved->id);

        $found = $this->repository->findByKey('test_key');

        $this->assertNotNull($found);
        $this->assertEquals('test_key', $found->key);
        $this->assertEquals('test_value', $found->getTypedValue());
    }

    public function test_getAll_returns_all_configs_ordered_by_key(): void
    {
        // Create multiple configs
        $config1 = new Config();
        $config1->key = 'zebra';
        $config1->type = 'string';
        $config1->setTypedValue('value1');

        $config2 = new Config();
        $config2->key = 'alpha';
        $config2->type = 'string';
        $config2->setTypedValue('value2');

        $this->repository->save($config1);
        $this->repository->save($config2);

        $all = $this->repository->getAll();

        // Filter to our test data (might have other configs from migrations/seeding)
        $testConfigs = array_filter($all, fn($c) => in_array($c->key, ['alpha', 'zebra']));
        $testConfigs = array_values($testConfigs); // Re-index

        $this->assertGreaterThanOrEqual(2, count($testConfigs), 'Should have at least 2 test configs');
        $this->assertEquals('alpha', $testConfigs[0]->key);
        $this->assertEquals('zebra', $testConfigs[1]->key);
    }

    public function test_delete_removes_config(): void
    {
        $config = new Config();
        $config->key = 'to_delete';
        $config->type = 'string';
        $config->setTypedValue('value');

        $this->repository->save($config);

        $this->assertNotNull($this->repository->findByKey('to_delete'));

        $this->repository->delete('to_delete');

        $this->assertNull($this->repository->findByKey('to_delete'));
    }

    public function test_encrypted_config_stores_encrypted_value(): void
    {
        // Define encryption key for testing (generate a random key)
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        $originalValue = 'secret_value_12345';

        $config = new Config();
        $config->key = 'encrypted_key';
        $config->type = 'encrypted';
        $config->setTypedValue($originalValue);

        $this->repository->save($config);

        // Verify raw value in DB is encrypted (not plain text)
        // Note: Uses wp_minisite_config (with prefix) via TablePrefixListener
        $rawValue = $this->em->getConnection()
            ->fetchOne("SELECT config_value FROM wp_minisite_config WHERE config_key = ?", ['encrypted_key']);

        // Verify stored value is encrypted
        $this->assertNotEquals($originalValue, $rawValue, 'Stored value should be encrypted, not plain text');
        $this->assertNotEmpty($rawValue, 'Encrypted value should not be empty');

        // Verify we can decrypt it back to original
        $found = $this->repository->findByKey('encrypted_key');

        $this->assertNotNull($found, 'Config should be found');
        $this->assertEquals('encrypted', $found->type, 'Type should be encrypted');
        $this->assertEquals($originalValue, $found->getTypedValue(), 'Decrypted value should match original');

        // Test updating encrypted value
        $newValue = 'new_secret_67890';
        $found->setTypedValue($newValue);
        $this->repository->save($found);

        // Verify new value is encrypted in DB
        $newRawValue = $this->em->getConnection()
            ->fetchOne("SELECT config_value FROM wp_minisite_config WHERE config_key = ?", ['encrypted_key']);

        $this->assertNotEquals($newValue, $newRawValue, 'New stored value should be encrypted');
        $this->assertNotEquals($rawValue, $newRawValue, 'New encrypted value should be different from old');

        // Verify we can decrypt new value
        $updated = $this->repository->findByKey('encrypted_key');
        $this->assertEquals($newValue, $updated->getTypedValue(), 'Decrypted new value should match');
    }
}

