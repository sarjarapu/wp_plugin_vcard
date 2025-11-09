<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Simplified ConfigRepository Integration Test - For Investigation
 *
 * This is a clean clone of ConfigRepositoryIntegrationTest for debugging savepoint issues.
 * Removed unnecessary complexity to focus on core functionality.
 */
#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryIntegrationTestCopyWithOutNonsenseTest extends TestCase
{
    private \Doctrine\ORM\EntityManager $em;
    private ConfigRepository $repository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize LoggingServiceProvider and get logger
        LoggingServiceProvider::register();
        $this->logger = LoggingServiceProvider::getFeatureLogger('config-repo-test-debug');

        // Add console output handler for tests (so we can see logs in real-time)
        if ($this->logger instanceof Logger) {
            $consoleHandler = new StreamHandler('php://stdout', Logger::DEBUG);

            // Custom formatter that shows only filename and class name (not full paths/namespaces)
            $formatter = new class () extends \Monolog\Formatter\LineFormatter {
                public function format(\Monolog\LogRecord $record): string
                {
                    // Extract just filename from full path
                    $filename = null;
                    if (isset($record->extra['file'])) {
                        $filename = basename($record->extra['file']);
                    }

                    // Extract just class name from full namespace
                    $className = null;
                    if (isset($record->extra['class'])) {
                        $parts = explode('\\', $record->extra['class']);
                        $className = end($parts);
                    }

                    // Format: [time] LEVEL: message [file:line] [class::method]
                    $output = sprintf(
                        "[%s] %s: %s",
                        $record->datetime->format('H:i:s.v'),
                        $record->level->getName(),
                        $record->message
                    );

                    if ($filename && isset($record->extra['line'])) {
                        $output .= sprintf(" [%s:%d]", $filename, $record->extra['line']);
                    }

                    if ($className && isset($record->extra['function'])) {
                        $output .= sprintf(" [%s::%s()]", $className, $record->extra['function']);
                    }

                    $output .= "\n";

                    return $output;
                }
            };

            $consoleHandler->setFormatter($formatter);
            $this->logger->pushHandler($consoleHandler);
        }

        $this->logger->info("setUp()");

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

        // Clean connection state
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

        // Reset connection state after migrations
        $connection = $this->em->getConnection();

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

        // ⚠️ CONNECTION CLOSE - COMMENT OUT TO TEST SAVEPOINT ERROR
        // Close connection to clear ALL savepoints (connection-scoped)
        // EntityManager will automatically reconnect when needed
        try {
            $connection->close();
        } catch (\Exception $e) {
            // Ignore - connection might already be closed
        }

        // Create repository
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);

        // Clean up test data
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->logger->info("tearDown()");
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
     *
     * This is the simplest test - perfect for investigating savepoint errors.
     */
    public function test_save_and_find_config(): void
    {
        $this->logger->info("test_save_and_find_config()");

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
