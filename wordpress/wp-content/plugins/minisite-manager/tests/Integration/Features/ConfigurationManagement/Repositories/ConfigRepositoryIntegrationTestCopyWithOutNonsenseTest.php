<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Simplified ConfigRepository Integration Test
 *
 * This is a clean clone of ConfigRepositoryIntegrationTest for focused testing.
 */
#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryIntegrationTestCopyWithOutNonsenseTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ConfigRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Get database configuration from environment
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        // Create real MySQL connection via Doctrine
        $connection = DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ));

        // Create EntityManager with MySQL connection
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(
                __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
                __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
                __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
            ),
            isDevMode: true
        );

        $this->em = new EntityManager($connection, $config);

        // Set up $wpdb object for TablePrefixListener
        if (! isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';

        // Add TablePrefixListener
        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );

        // Drop tables to ensure clean slate
        $this->cleanupTables();

        // Run migrations
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();

        // Create repository
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);

        // Clean up test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->em->close();
        parent::tearDown();
    }

    /**
     * Drop tables and migration tracking to ensure clean slate
     */
    private function cleanupTables(): void
    {
        $connection = $this->em->getConnection();
        $tables = array('wp_minisite_config', 'wp_minisite_migrations');

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
            $this->em->getConnection()->executeStatement('DELETE FROM wp_minisite_config');
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Test: Save and find config
     */
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
}
