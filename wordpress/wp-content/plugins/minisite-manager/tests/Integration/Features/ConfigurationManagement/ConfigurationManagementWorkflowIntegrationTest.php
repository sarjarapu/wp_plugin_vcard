<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement;

use Minisite\Features\ConfigurationManagement\Commands\DeleteConfigCommand;
use Minisite\Features\ConfigurationManagement\Commands\SaveConfigCommand;
use Minisite\Features\ConfigurationManagement\Handlers\DeleteConfigHandler;
use Minisite\Features\ConfigurationManagement\Handlers\SaveConfigHandler;
use Minisite\Features\ConfigurationManagement\Services\ConfigurationManagementService;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ConfigurationManagement workflow
 *
 * Tests the complete workflow from command to handler to service to repository.
 * This is a REAL integration test - uses actual wp_minisite_config table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
final class ConfigurationManagementWorkflowIntegrationTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ConfigRepository $repository;
    private ConfigurationManagementService $service;
    private SaveConfigHandler $saveHandler;
    private DeleteConfigHandler $deleteHandler;

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
                __DIR__ . '/../../../../src/Features/ConfigurationManagement/Domain/Entities',
                __DIR__ . '/../../../../src/Features/VersionManagement/Domain/Entities',
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

        // Create handlers
        $this->saveHandler = new SaveConfigHandler($this->service);
        $this->deleteHandler = new DeleteConfigHandler($this->service);

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
                "DELETE FROM wp_minisite_config WHERE config_key LIKE 'test_%' OR config_key IN ('workflow_key', 'workflow_delete')"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    /**
     * Test complete workflow: Command -> Handler -> Service -> Repository
     */
    public function test_save_workflow_end_to_end(): void
    {
        // Create command
        $command = new SaveConfigCommand('workflow_key', 'workflow_value', 'string', 'Workflow test description');

        // Handle via handler
        $this->saveHandler->handle($command);

        // Verify via service
        $result = $this->service->get('workflow_key');
        $this->assertEquals('workflow_value', $result);

        // Verify via repository
        $config = $this->repository->findByKey('workflow_key');
        $this->assertNotNull($config);
        $this->assertEquals('workflow_key', $config->key);
        $this->assertEquals('workflow_value', $config->value);
        $this->assertEquals('string', $config->type);
        $this->assertEquals('Workflow test description', $config->description);
    }

    /**
     * Test update workflow
     */
    public function test_update_workflow_end_to_end(): void
    {
        // Create initial config
        $command1 = new SaveConfigCommand('workflow_key', 'initial_value', 'string');
        $this->saveHandler->handle($command1);
        $this->assertEquals('initial_value', $this->service->get('workflow_key'));

        // Update config
        $command2 = new SaveConfigCommand('workflow_key', 'updated_value', 'string');
        $this->saveHandler->handle($command2);
        $this->assertEquals('updated_value', $this->service->get('workflow_key'));

        // Verify in repository
        $config = $this->repository->findByKey('workflow_key');
        $this->assertNotNull($config);
        $this->assertEquals('updated_value', $config->value);
    }

    /**
     * Test delete workflow
     */
    public function test_delete_workflow_end_to_end(): void
    {
        // Create config
        $saveCommand = new SaveConfigCommand('workflow_delete', 'delete_me', 'string');
        $this->saveHandler->handle($saveCommand);
        $this->assertTrue($this->service->has('workflow_delete'));

        // Delete config
        $deleteCommand = new DeleteConfigCommand('workflow_delete');
        $this->deleteHandler->handle($deleteCommand);

        // Verify deleted via service
        $this->assertFalse($this->service->has('workflow_delete'));

        // Verify deleted via repository
        $config = $this->repository->findByKey('workflow_delete');
        $this->assertNull($config);
    }

    /**
     * Test workflow with different types
     */
    public function test_workflow_with_different_types(): void
    {
        // String
        $stringCommand = new SaveConfigCommand('test_string', 'string_value', 'string');
        $this->saveHandler->handle($stringCommand);
        $this->assertEquals('string_value', $this->service->get('test_string'));

        // Integer
        $intCommand = new SaveConfigCommand('test_int', 42, 'integer');
        $this->saveHandler->handle($intCommand);
        $this->assertIsInt($this->service->get('test_int'));
        $this->assertEquals(42, $this->service->get('test_int'));

        // Boolean
        $boolCommand = new SaveConfigCommand('test_bool', true, 'boolean');
        $this->saveHandler->handle($boolCommand);
        $this->assertIsBool($this->service->get('test_bool'));
        $this->assertTrue($this->service->get('test_bool'));

        // JSON
        $jsonData = ['key' => 'value', 'number' => 123];
        $jsonCommand = new SaveConfigCommand('test_json', $jsonData, 'json');
        $this->saveHandler->handle($jsonCommand);
        $result = $this->service->get('test_json');
        $this->assertIsArray($result);
        $this->assertEquals($jsonData, $result);
    }

    /**
     * Test workflow with encrypted type
     */
    public function test_workflow_with_encrypted_type(): void
    {
        $originalValue = 'secret_workflow_value_12345';
        $command = new SaveConfigCommand('test_encrypted', $originalValue, 'encrypted');
        $this->saveHandler->handle($command);

        // Verify can retrieve decrypted value via service
        $result = $this->service->get('test_encrypted');
        $this->assertEquals($originalValue, $result);

        // Verify is encrypted in database
        $config = $this->repository->findByKey('test_encrypted');
        $this->assertNotNull($config);
        $this->assertNotEquals($originalValue, $config->value);
        $this->assertNotEmpty($config->value);
        $this->assertTrue($config->isSensitive);
    }
}

