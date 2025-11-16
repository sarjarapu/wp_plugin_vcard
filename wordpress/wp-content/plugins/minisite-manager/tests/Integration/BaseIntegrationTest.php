<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use PHPUnit\Framework\TestCase;

/**
 * Base class for all integration tests
 *
 * Provides common setup and teardown functionality:
 * - Database connection and EntityManager setup
 * - WordPress constants and globals
 * - Table cleanup
 * - Migration running
 * - wp_users table stub for foreign keys
 *
 * Subclasses must implement:
 * - getEntityPaths(): array - Return array of entity paths for ORM configuration
 * - setupTestSpecificServices(): void - Initialize test-specific repositories/services
 * - cleanupTestData(): void - Clean up test-specific data (not tables)
 */
#[PHPUnit\Framework\Attributes\ExcludeFromClassCodeCoverage]
abstract class BaseIntegrationTest extends TestCase
{
    protected EntityManager $em;
    protected Connection $connection;
    protected string $dbName;

    /**
     * Get entity paths for ORM configuration
     * Each test must specify which entity paths it needs
     *
     * @return array<string> Array of absolute or relative paths to entity directories
     */
    abstract protected function getEntityPaths(): array;

    /**
     * Setup test-specific services, repositories, handlers, etc.
     * Called after migrations have run and tables are ready
     *
     * @return void
     */
    abstract protected function setupTestSpecificServices(): void;

    /**
     * Clean up test-specific data (but keep table structure)
     * Called in setUp() after migrations and in tearDown()
     *
     * @return void
     */
    abstract protected function cleanupTestData(): void;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeLogging();
        $this->defineConstants();
        $this->setupWordPressGlobals();
        $this->createDatabaseConnection();
        $this->createEntityManager();
        $this->resetConnectionState();
        $this->registerTablePrefixListener();
        $this->cleanupTables();
        $this->createWordPressUsersTableStub();
        $this->createTestUser();
        $this->runMigrations();
        $this->setupTestSpecificServices();
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();

        try {
            $this->em->clear();
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }

        $this->em->close();
        parent::tearDown();
    }

    /**
     * Initialize LoggingServiceProvider
     */
    protected function initializeLogging(): void
    {
        LoggingServiceProvider::register();
    }

    /**
     * Define WordPress and plugin constants required for tests
     */
    protected function defineConstants(): void
    {
        // Define encryption key for tests that use encrypted type
        if (! defined('MINISITE_ENCRYPTION_KEY')) {
            define('MINISITE_ENCRYPTION_KEY', base64_encode(random_bytes(32)));
        }

        // Get database configuration from environment
        $dbConfig = $this->getDatabaseConfig();

        // Define WordPress DB constants (required by DoctrineFactory when migrations call ensureRepositoriesInitialized())
        if (! defined('DB_HOST')) {
            define('DB_HOST', $dbConfig['host']);
        }
        if (! defined('DB_PORT')) {
            define('DB_PORT', $dbConfig['port']);
        }
        if (! defined('DB_USER')) {
            define('DB_USER', $dbConfig['user']);
        }
        if (! defined('DB_PASSWORD')) {
            define('DB_PASSWORD', $dbConfig['password']);
        }
        if (! defined('DB_NAME')) {
            define('DB_NAME', $dbConfig['dbname']);
        }
    }

    /**
     * Get database configuration from environment variables
     *
     * @return array{host: string, port: string, dbname: string, user: string, password: string}
     */
    protected function getDatabaseConfig(): array
    {
        return array(
            'host' => getenv('MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('MYSQL_PORT') ?: '3307',
            'dbname' => getenv('MYSQL_DATABASE') ?: 'minisite_test',
            'user' => getenv('MYSQL_USER') ?: 'minisite',
            'password' => getenv('MYSQL_PASSWORD') ?: 'minisite',
        );
    }

    /**
     * Setup WordPress globals required for TablePrefixListener
     */
    protected function setupWordPressGlobals(): void
    {
        // Ensure $wpdb is set (required by TablePrefixListener)
        if (! isset($GLOBALS['wpdb'])) {
            $GLOBALS['wpdb'] = new \wpdb();
        }
        $GLOBALS['wpdb']->prefix = 'wp_';
    }

    /**
     * Create database connection via Doctrine
     */
    protected function createDatabaseConnection(): void
    {
        $dbConfig = $this->getDatabaseConfig();
        $this->dbName = $dbConfig['dbname'];

        $this->connection = DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => $dbConfig['host'],
            'port' => (int) $dbConfig['port'],
            'user' => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'dbname' => $dbConfig['dbname'],
            'charset' => 'utf8mb4',
        ));
    }

    /**
     * Create EntityManager with MySQL connection
     */
    protected function createEntityManager(): void
    {
        $entityPaths = $this->getEntityPaths();
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $entityPaths,
            isDevMode: true
        );

        $this->em = new EntityManager($this->connection, $config);
    }

    /**
     * Reset connection state to ensure clean transaction state
     */
    protected function resetConnectionState(): void
    {
        try {
            $this->connection->executeStatement('ROLLBACK');
        } catch (\Exception $e) {
            // Ignore - connection might already be clean
        }

        try {
            $this->connection->beginTransaction();
            $this->connection->commit();
        } catch (\Exception $e) {
            try {
                $this->connection->rollBack();
            } catch (\Exception $e2) {
                // Ignore
            }
        }

        $this->em->clear();
    }

    /**
     * Register TablePrefixListener for WordPress table prefix support
     */
    protected function registerTablePrefixListener(): void
    {
        // Ensure $wpdb is set (might be called multiple times)
        $this->setupWordPressGlobals();

        $tablePrefixListener = new TablePrefixListener($GLOBALS['wpdb']->prefix);
        $this->em->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            $tablePrefixListener
        );
    }

    /**
     * Drop all migration tables and wp_users to ensure clean slate
     */
    protected function cleanupTables(): void
    {
        // Find all tables with wp_minisite prefix that were created by migrations
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = ? AND (TABLE_NAME LIKE 'wp_minisite_%' OR TABLE_NAME = 'wp_minisites')",
            array($this->dbName)
        );

        // Always include the migration tracking table
        if (! in_array('wp_minisite_migrations', $tables, true)) {
            $tables[] = 'wp_minisite_migrations';
        }

        // Always include wp_users table (created for foreign key tests)
        $tables[] = 'wp_users';

        // Disable foreign key checks to allow dropping tables in any order
        // This is safe in test cleanup since we're dropping all tables anyway
        try {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                try {
                    $this->connection->executeStatement("DROP TABLE IF EXISTS `{$table}`");
                } catch (\Exception $e) {
                    // Ignore errors - table might not exist
                }
            }

            // Re-enable foreign key checks
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Exception $e) {
            // Ensure foreign key checks are re-enabled even if cleanup fails
            try {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }

    /**
     * Create a minimal wp_users table stub for foreign key tests
     * This allows migrations that reference wp_users to work in tests
     * Always creates the table (no existence check) since this is an integration test
     */
    protected function createWordPressUsersTableStub(): void
    {
        // Always create wp_users table (no existence check needed for integration tests)
        $this->connection->executeStatement(
            "CREATE TABLE IF NOT EXISTS `wp_users` (
                `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Create a test user (ID = 1) for foreign key constraints
     * Integration tests need at least one user record for migrations that reference wp_users
     */
    protected function createTestUser(): void
    {
        // Always delete and recreate to ensure clean state
        $this->connection->executeStatement("DELETE FROM wp_users WHERE ID = 1");
        $this->connection->executeStatement("INSERT INTO wp_users (ID) VALUES (1)");
    }

    /**
     * Run Doctrine migrations to ensure all tables exist
     */
    protected function runMigrations(): void
    {
        $migrationRunner = new DoctrineMigrationRunner($this->em);
        $migrationRunner->migrate();
    }
}
