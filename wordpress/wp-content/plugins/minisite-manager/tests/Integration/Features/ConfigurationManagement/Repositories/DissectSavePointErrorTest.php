<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Minisite\Features\ConfigurationManagement\Domain\Entities\Config;
use Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Migrations\Doctrine\Version20251103000000;
use Minisite\Infrastructure\Persistence\Doctrine\TablePrefixListener;
use PHPUnit\Framework\TestCase;

/**
 * Dissect Savepoint Error - Raw SQL Test
 *
 * This test uses ONLY raw SQL commands (no ORM) to isolate savepoint issues.
 * Expected: No savepoint errors since no ORM is involved.
 */
final class DissectSavePointErrorTest extends TestCase
{
    private Connection $connection;
    private ?EntityManager $em = null;
    private ?ConfigRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupDatabaseConnection();
    }

    protected function tearDown(): void
    {
        if ($this->em !== null) {
            $this->em->close();
        }
        if (isset($this->connection)) {
            $this->connection->close();
        }
        parent::tearDown();
    }

    /**
     * Create database connection
     */
    private function setupDatabaseConnection(): void
    {
        // Get database configuration from environment
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $dbName = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        // Create real MySQL connection via Doctrine DBAL (for connection only, no ORM)
        $this->connection = DriverManager::getConnection(array(
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => (int)$port,
            'user' => $user,
            'password' => $pass,
            'dbname' => $dbName,
            'charset' => 'utf8mb4',
        ));
    }

    /**
     * Test: Drop table, create table, and insert using raw SQL
     */
    public function t1est_raw_sql_operations(): void
    {
        // Step 1: Drop table if exists
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_config`');

        // Step 2: Create table
        $createTableSql = <<<SQL
CREATE TABLE `wp_minisite_config` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `value` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $this->connection->executeStatement($createTableSql);

        // Step 3: Insert data
        $insertSql = <<<SQL
INSERT INTO `wp_minisite_config` (`key`, `type`, `value`)
VALUES ('test_key', 'string', 'test_value')
SQL;

        $this->connection->executeStatement($insertSql);

        // Step 4: Verify insert
        $result = $this->connection->fetchAssociative(
            'SELECT * FROM `wp_minisite_config` WHERE `key` = ?',
            array('test_key')
        );

        $this->assertNotNull($result);
        $this->assertEquals('test_key', $result['key']);
        $this->assertEquals('string', $result['type']);
        $this->assertEquals('test_value', $result['value']);
    }

    /**
     * Test: Create table using raw SQL, then use ConfigRepository for insert/select
     */
    public function t1est_raw_sql_create_then_repository_operations(): void
    {
        // Step 1: Setup ORM (needed for repository)
        $this->setupORM();

        // Step 2: Drop and create table using raw SQL
        $this->createTableUsingRawSQL();

        // Step 3: Setup Repository
        $this->setupRepository();

        // Step 4: Use ConfigRepository operations
        $this->performRepositoryOperations();
    }

    /**
     * Setup ORM (EntityManager) with WordPress table prefix
     */
    private function setupORM(): void
    {
        // Create EntityManager with MySQL connection
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: array(
                __DIR__ . '/../../../../../src/Features/ConfigurationManagement/Domain/Entities',
            ),
            isDevMode: true
        );

        $this->em = new EntityManager($this->connection, $config);

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
    }

    /**
     * Create repository instance
     */
    private function setupRepository(): void
    {
        $classMetadata = $this->em->getClassMetadata(Config::class);
        $this->repository = new ConfigRepository($this->em, $classMetadata);
    }

    /**
     * Test: Use Doctrine Migrations to create table, then use ConfigRepository
     * This brings in Doctrine Migrations components slowly to isolate savepoint issues
     */
    public function test_doctrine_migrations_then_repository_operations(): void
    {
        // Step 1: Setup ORM (needed for migrations)
        $this->setupORM();

        // Step 2: Create table using Doctrine Migrations
        $this->createTableUsingDoctrineMigrations();

        // Step 3: Setup Repository
        $this->setupRepository();

        // Step 4: Use ConfigRepository operations
        $this->performRepositoryOperations();
    }

    /**
     * Create table using raw SQL
     */
    private function createTableUsingRawSQL(): void
    {
        // Drop table if exists
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_config`');

        // Create table using raw SQL
        $createTableSql = <<<SQL
CREATE TABLE `wp_minisite_config` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `config_key` VARCHAR(100) NOT NULL,
    `config_type` VARCHAR(20) NOT NULL,
    `config_value` TEXT,
    `description` TEXT,
    `is_sensitive` TINYINT(1) NOT NULL DEFAULT 0,
    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $this->connection->executeStatement($createTableSql);
    }

    /**
     * Create table using Doctrine Migrations
     */
    private function createTableUsingDoctrineMigrations(): void
    {
        // Drop tables to ensure clean slate
        // Only drop config table and migrations tracking table
        // We also drop other tables that might have POINT columns (from previous test runs)
        // to avoid schema introspection errors when introspectSchema() is called
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_config`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_migrations`');
        // Drop tables with POINT columns to avoid introspection errors (not using these migrations)
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_versions`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_reviews`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisites`');

        // Register type mappings BEFORE any schema introspection
        // This must be done BEFORE introspectSchema() to avoid "Unknown database type point" errors
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->hasDoctrineTypeMappingFor('enum')) {
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }
        if (! $platform->hasDoctrineTypeMappingFor('point')) {
            $platform->registerDoctrineTypeMapping('point', 'blob');
        }

        $logger = LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $schemaManager = $this->connection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();
        // Create and invoke up directly for Version20251103000000 (config table migration)
        $migration = new Version20251103000000($this->connection, $logger);
        $toSchema = clone $fromSchema;
        $migration->up($toSchema);

        // Apply schema changes to database
        $schemaDiff = $schemaManager->createComparator()->compareSchemas($fromSchema, $toSchema);
        $platform = $this->connection->getDatabasePlatform();
        $sql = $platform->getAlterSchemaSQL($schemaDiff);
        foreach ($sql as $query) {
            $this->connection->executeStatement($query);
        }

        // // Create migration configuration (from DoctrineMigrationRunner lines 112-128)
        // // KEY: 'all_or_nothing' => true means all migrations run in a single transaction
        // $migrationPath = __DIR__ . '/../../../../../src/Infrastructure/Migrations/Doctrine';
        // $migrationNamespace = 'Minisite\\Infrastructure\\Migrations\\Doctrine';
        // $config = new ConfigurationArray(array(
        //     'migrations_paths' => array(
        //         $migrationNamespace => $migrationPath,
        //     ),
        //     'all_or_nothing' => true, // ⚠️ THIS IS THE KEY SETTING
        //     'check_database_platform' => true,
        //     'organize_migrations' => 'none',
        //     'table_storage' => array(
        //         'table_name' => 'wp_minisite_migrations',
        //     ),
        // ));

        // // Create dependency factory (from DoctrineMigrationRunner lines 137-145)
        // $dependencyFactory = DependencyFactory::fromConnection(
        //     $config,
        //     new ExistingConnection($this->connection)
        // );

        // // Ensure metadata storage initialized (from DoctrineMigrationRunner lines 153-165)
        // $metadataStorage = $dependencyFactory->getMetadataStorage();
        // $metadataStorage->ensureInitialized();

        // // Execute migrations - ONLY Version20251103000000 (config table)
        // // Filter to only run the config table migration, skip reviews and versions migrations
        // $statusCalculator = $dependencyFactory->getMigrationStatusCalculator();
        // $availableMigrations = $statusCalculator->getNewMigrations();

        // if (count($availableMigrations) > 0) {
        //     $migrationItems = $availableMigrations->getItems();

        //     // Filter to only include Version20251103000000 (config table migration)
        //     $targetVersion = null;
        //     foreach ($migrationItems as $migration) {
        //         $version = $migration->getVersion();
        //         $versionString = $version->__toString();
        //         # print the version string
        //         echo " ==> Version string: " . $versionString . PHP_EOL;
        //         // Extract version number from class name (e.g., "Minisite\...\Version20251103000000" -> "20251103000000")
        //         // Match Version20251103000000 (config table only) - skip 20251104000000 and 20251105000000
        //         if (str_ends_with($versionString, 'Version20251103000000')) {
        //             $targetVersion = $version;

        //             break;
        //         }
        //     }

        //     if ($targetVersion !== null) {
        //         $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
        //         $plan = $planCalculator->getPlanUntilVersion($targetVersion);

        //         $migrator = $dependencyFactory->getMigrator();
        //         $migratorConfig = new MigratorConfiguration();
        //         $migrator->migrate($plan, $migratorConfig); // ⚠️ THIS IS WHERE SAVEPOINT ERRORS OCCUR
        //     } else {
        //         // Migration not found - show what was found for debugging
        //         throw new \RuntimeException(
        //             'Version20251103000000 migration not found. ' .
        //             'Available versions: ' . implode(', ', $foundVersions)
        //         );
        //     }
        // } else {
        //     // No migrations available - this shouldn't happen if migrations table was dropped
        //     throw new \RuntimeException('No new migrations found - migrations table may not have been properly dropped');
        // }
    }

    /**
     * Perform repository operations (save and find)
     */
    private function performRepositoryOperations(): void
    {
        // Use ConfigRepository to save
        $config = new Config();
        $config->key = 'test_key';
        $config->type = 'string';
        $config->setTypedValue('test_value');

        $saved = $this->repository->save($config);

        $this->assertNotNull($saved->id);

        // Use ConfigRepository to find
        $found = $this->repository->findByKey('test_key');

        $this->assertNotNull($found);
        $this->assertEquals('test_key', $found->key);
        $this->assertEquals('test_value', $found->getTypedValue());
    }

    /**
     * Find the latest migration version (from DoctrineMigrationRunner lines 230-242)
     */
    private function findLatestMigrationVersion(DependencyFactory $dependencyFactory): ?Version
    {
        $migrationRepository = $dependencyFactory->getMigrationRepository();
        $allMigrations = $migrationRepository->getMigrations();
        $migrationItems = $allMigrations->getItems();

        $latestVersion = null;
        foreach ($migrationItems as $migration) {
            $latestVersion = $migration->getVersion();
        }

        return $latestVersion;
    }
}
