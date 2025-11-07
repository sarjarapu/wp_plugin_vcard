<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Services;

use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for ConfigurationManagementService
 *
 * Tests ConfigurationManagementService against real MySQL database.
 * This is a REAL integration test - uses actual wp_minisite_config table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(ConfigurationManagementService::class)]
final class ConfigurationManagementServiceIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ConfigRepository $repository;
    private ConfigurationManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider if not already initialized
        \Minisite\Infrastructure\Logging\LoggingServiceProvider::register();

        // Define encryption key for tests that use encrypted type
        if (!defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        // Get database configuration from environment
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        // Create real MySQL connection via Doctrine
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ]);

        // Create EntityManager with MySQL connection
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
            ],
            isDevMode: true
        );

        $this->em = new EntityManager($connection, $config);

        // Reset connection state
        try {
            $connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $connection->beginTransaction();
            $connection->commit();
        } catch (\Exception $e) {
            try {
                $connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();

        // Set up $wpdb object for TablePrefixListener
        if (!isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';

        // Add TablePrefixListener
        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        // Drop tables and migration tracking to ensure clean slate
        $this->cleanupTables();

        // Ensure migrations have run
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Reset connection state again after migrations
        try {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (\Exception $e) {
            try {
                $connection->executeStatement('ROLLBACK');
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();

        try {
            $connection->close();
        } catch (\Exception $e) {
            // Ignore
        }

        // Get repository
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);

        // Create service
        $this->service = new ConfigurationManagementService($this->repository);

        // Clean up test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();

        // Reset static cache
        $reflection = new \ReflectionClass(ConfigurationManagementService::class);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null, null);

        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);

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
     */
    private function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%' OR config_key IN ('int_key', 'bool_key', 'json_key', 'encrypted_key', 'to_delete')"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_set_and_get_string(): void
    {
        $this->service->set('test_key', 'test_value');

        $result = $this->service->get('test_key');

        $this->assertEquals('test_value', $result);
    }

    public function test_set_and_get_integer(): void
    {
        $this->service->set('test_key', 42, 'integer');

        $result = $this->service->get('test_key');

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function test_set_and_get_boolean(): void
    {
        $this->service->set('test_key', true, 'boolean');

        $result = $this->service->get('test_key');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_set_and_get_json(): void
    {
        $jsonData = ['key' => 'value', 'number' => 123];
        $this->service->set('test_key', $jsonData, 'json');

        $result = $this->service->get('test_key');

        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }

    public function test_set_and_get_encrypted(): void
    {
        $originalValue = 'secret_value_12345';
        $this->service->set('test_key', $originalValue, 'encrypted');

        $result = $this->service->get('test_key');

        $this->assertEquals($originalValue, $result);

        // Verify it's actually encrypted in database
        $rawValue = $this->em->getConnection()
            ->fetchOne("SELECT config_value FROM wp_minisite_config WHERE config_key = ?", ['test_key']);

        $this->assertNotEquals($originalValue, $rawValue);
        $this->assertNotEmpty($rawValue);
    }

    public function test_get_returns_default_when_not_exists(): void
    {
        $result = $this->service->get('non_existent', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_get_returns_null_when_no_default(): void
    {
        $result = $this->service->get('non_existent');

        $this->assertNull($result);
    }

    public function test_has_returns_true_when_exists(): void
    {
        $this->service->set('test_key', 'value');

        $this->assertTrue($this->service->has('test_key'));
    }

    public function test_has_returns_false_when_not_exists(): void
    {
        $this->assertFalse($this->service->has('non_existent'));
    }

    public function test_delete_removes_config(): void
    {
        $this->service->set('test_key', 'value');
        $this->assertTrue($this->service->has('test_key'));

        $this->service->delete('test_key');

        $this->assertFalse($this->service->has('test_key'));
    }

    public function test_set_updates_existing_config(): void
    {
        $this->service->set('test_key', 'value1');
        $this->assertEquals('value1', $this->service->get('test_key'));

        $this->service->set('test_key', 'value2');
        $this->assertEquals('value2', $this->service->get('test_key'));
    }

    public function test_all_returns_all_configs(): void
    {
        $this->service->set('test_key1', 'value1');
        $this->service->set('test_key2', 'value2');

        $all = $this->service->all();

        $keys = array_map(fn($c) => $c->key, $all);
        $this->assertContains('test_key1', $keys);
        $this->assertContains('test_key2', $keys);
    }

    public function test_all_excludes_sensitive_when_flag_false(): void
    {
        $this->service->set('normal_key', 'value');
        $this->service->set('sensitive_key', 'secret', 'encrypted');

        $all = $this->service->all(includeSensitive: false);

        $keys = array_map(fn($c) => $c->key, $all);
        $this->assertContains('normal_key', $keys);
        $this->assertNotContains('sensitive_key', $keys);
    }

    public function test_all_includes_sensitive_when_flag_true(): void
    {
        $this->service->set('normal_key', 'value');
        $this->service->set('sensitive_key', 'secret', 'encrypted');

        $all = $this->service->all(includeSensitive: true);

        $keys = array_map(fn($c) => $c->key, $all);
        $this->assertContains('normal_key', $keys);
        $this->assertContains('sensitive_key', $keys);
    }

    public function test_keys_returns_all_keys(): void
    {
        $this->service->set('test_key1', 'value1');
        $this->service->set('test_key2', 'value2');

        $keys = $this->service->keys();

        $this->assertContains('test_key1', $keys);
        $this->assertContains('test_key2', $keys);
    }

    public function test_find_returns_config_entity(): void
    {
        $this->service->set('test_key', 'value');

        $config = $this->service->find('test_key');

        $this->assertInstanceOf(Config::class, $config);
        $this->assertEquals('test_key', $config->key);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        $config = $this->service->find('non_existent');

        $this->assertNull($config);
    }

    public function test_reload_clears_cache(): void
    {
        $this->service->set('test_key', 'value1');
        $this->assertEquals('value1', $this->service->get('test_key'));

        // Update directly in database
        $this->em->getConnection()->executeStatement(
            "UPDATE wp_minisite_config SET config_value = 'value2' WHERE config_key = 'test_key'"
        );

        // Clear EntityManager cache so it doesn't return cached entities
        $this->em->clear();

        // Should still return cached value (service cache, not EntityManager cache)
        $this->assertEquals('value1', $this->service->get('test_key'));

        // Reload should clear service cache
        $this->service->reload();

        // Should now return new value from DB
        $this->assertEquals('value2', $this->service->get('test_key'));
    }

    public function test_typed_getters_return_correct_types(): void
    {
        $this->service->set('string_key', '123', 'string');
        $this->service->set('int_key', 42, 'integer');
        $this->service->set('bool_key', true, 'boolean');
        $this->service->set('json_key', ['key' => 'value'], 'json');

        $this->assertIsString($this->service->getString('string_key', ''));
        $this->assertIsInt($this->service->getInt('int_key', 0));
        $this->assertIsBool($this->service->getBool('bool_key', false));
        $this->assertIsArray($this->service->getJson('json_key', []));
    }
}

