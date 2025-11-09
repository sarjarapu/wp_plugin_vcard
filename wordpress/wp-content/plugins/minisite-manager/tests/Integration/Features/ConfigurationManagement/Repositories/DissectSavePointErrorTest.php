<?php

declare(strict_types=1);

namespace Tests\Integration\Features\ConfigurationManagement\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
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
    public function test_raw_sql_operations(): void
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
    public function test_raw_sql_create_then_repository_operations(): void
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
     * Test: Create table using migration ->up() method, then use ConfigRepository
     * Uses manual ->up() method (bypasses Doctrine Migrations framework)
     * Expected: No savepoint errors since framework transaction management is bypassed
     */
    public function test_migration_up_method_then_repository_operations(): void
    {
        // Step 1: Setup ORM (needed for migrations)
        $this->setupORM();

        // Step 2: Create table using migration ->up() method (bypasses framework)
        $this->createTableViaMigrationUp();

        // Step 3: Setup Repository
        $this->setupRepository();

        // Step 4: Use ConfigRepository operations
        $this->performRepositoryOperations();
    }

    /**
     * Test: Create table using migrator ->migrate() method, then use ConfigRepository
     * Uses the actual Doctrine Migrations framework ($migrator->migrate())
     * With isTransactional() => false on migration - Expected: No savepoint error
     */
    public function test_migrator_migrate_method_then_repository_operations(): void
    {
        // Step 1: Setup ORM (needed for migrations)
        $this->setupORM();

        // Step 2: Create table using migrator ->migrate() method (uses framework)
        // Migration has isTransactional() => false to avoid MySQL DDL implicit commit issues
        $this->createTableViaMigratorMigrate();

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
     * Create table using migration ->up() method (bypasses Doctrine Migrations framework)
     * This manually executes the migration's up() method without using the migrator
     */
    private function createTableViaMigrationUp(): void
    {
        // Drop tables to ensure clean slate
        // Only drop config table and migrations tracking table
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_config`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_migrations`');

        // Create wp_minisites table with POINT column to test introspection behavior
        // This simulates a table from previous test runs that has a POINT column
        // When introspectSchema() is called, it will encounter this POINT column
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisites`');
        $createMinisitesTableSql = <<<SQL
CREATE TABLE `wp_minisites` (
    `id` VARCHAR(32) NOT NULL,
    `slug` VARCHAR(255) NULL,
    `business_slug` VARCHAR(120) NULL,
    `location_slug` VARCHAR(120) NULL,
    `title` VARCHAR(200) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `city` VARCHAR(120) NOT NULL,
    `region` VARCHAR(120) NULL,
    `country_code` CHAR(2) NOT NULL,
    `postal_code` VARCHAR(20) NULL,
    `location_point` POINT NULL,
    `site_template` VARCHAR(32) NOT NULL DEFAULT 'v2025',
    `palette` VARCHAR(24) NOT NULL DEFAULT 'blue',
    `industry` VARCHAR(40) NOT NULL DEFAULT 'services',
    `default_locale` VARCHAR(10) NOT NULL DEFAULT 'en-US',
    `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `site_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `site_json` LONGTEXT NOT NULL,
    `search_terms` TEXT NULL,
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    `publish_status` ENUM('draft','reserved','published') NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at` DATETIME NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `_minisite_current_version_id` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    UNIQUE KEY `uniq_business_location` (`business_slug`, `location_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->connection->executeStatement($createMinisitesTableSql);

        // Register type mappings BEFORE any schema introspection
        // This must be done BEFORE introspectSchema() to avoid "Unknown database type point" errors
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->hasDoctrineTypeMappingFor('enum')) {
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }

        // Register custom POINT type (proper implementation, not just blob mapping)
        // This provides proper type conversion between GeoPoint value object and MySQL POINT
        if (! \Doctrine\DBAL\Types\Type::hasType('point')) {
            \Doctrine\DBAL\Types\Type::addType('point', \Minisite\Infrastructure\Persistence\Doctrine\Types\PointType::class);
        }
        // Map MySQL POINT type to our custom PointType
        if (! $platform->hasDoctrineTypeMappingFor('point')) {
            $platform->registerDoctrineTypeMapping('point', 'point');
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

    }

    /**
     * Create table using migrator ->migrate() method (uses Doctrine Migrations framework)
     * This uses the actual Doctrine Migrations framework
     */
    private function createTableViaMigratorMigrate(): void
    {
        // Drop tables to ensure clean slate
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_config`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `wp_minisite_migrations`');

        // Register type mappings BEFORE any schema introspection
        // This must be done BEFORE introspectSchema() to avoid "Unknown database type point" errors
        $platform = $this->connection->getDatabasePlatform();
        if (! $platform->hasDoctrineTypeMappingFor('enum')) {
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }

        // Register custom POINT type (proper implementation, not just blob mapping)
        // This provides proper type conversion between GeoPoint value object and MySQL POINT
        if (! Type::hasType('point')) {
            Type::addType('point', \Minisite\Infrastructure\Persistence\Doctrine\Types\PointType::class);
        }
        // Map MySQL POINT type to our custom PointType
        if (! $platform->hasDoctrineTypeMappingFor('point')) {
            $platform->registerDoctrineTypeMapping('point', 'point');
        }

        // Create migration configuration (from DoctrineMigrationRunner lines 112-128)
        $migrationPath = __DIR__ . '/../../../../../src/Infrastructure/Migrations/Doctrine';
        $migrationNamespace = 'Minisite\\Infrastructure\\Migrations\\Doctrine';
        $config = new ConfigurationArray(array(
            'migrations_paths' => array(
                $migrationNamespace => $migrationPath,
            ),
            'all_or_nothing' => true, // Standard configuration
            'check_database_platform' => true,
            'organize_migrations' => 'none',
            'table_storage' => array(
                'table_name' => 'wp_minisite_migrations',
            ),
        ));

        // Create dependency factory (from DoctrineMigrationRunner lines 137-145)
        $dependencyFactory = DependencyFactory::fromConnection(
            $config,
            new ExistingConnection($this->connection)
        );

        // Ensure metadata storage initialized (from DoctrineMigrationRunner lines 153-165)
        $metadataStorage = $dependencyFactory->getMetadataStorage();
        $metadataStorage->ensureInitialized();

        // Execute migrations - ONLY Version20251103000000 (config table)
        // Filter to only run the config table migration, skip reviews and versions migrations
        $statusCalculator = $dependencyFactory->getMigrationStatusCalculator();
        $availableMigrations = $statusCalculator->getNewMigrations();

        if (count($availableMigrations) > 0) {
            $migrationItems = $availableMigrations->getItems();

            // Filter to only include Version20251103000000 (config table migration)
            $targetVersion = null;
            foreach ($migrationItems as $migration) {
                $version = $migration->getVersion();
                $versionString = $version->__toString();
                // Match Version20251103000000 (config table only) - skip 20251104000000 and 20251105000000
                if (str_ends_with($versionString, 'Version20251103000000')) {
                    $targetVersion = $version;

                    break;
                }
            }

            if ($targetVersion !== null) {
                $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
                $plan = $planCalculator->getPlanUntilVersion($targetVersion);

                $migrator = $dependencyFactory->getMigrator();
                $migratorConfig = new MigratorConfiguration();

                // Execute migrations
                // With isTransactional() => false, MySQL DDL won't cause savepoint errors
                $migrator->migrate($plan, $migratorConfig);
            } else {
                // Migration not found
                throw new \RuntimeException('Version20251103000000 migration not found in available migrations');
            }
        } else {
            // No migrations available
            throw new \RuntimeException('No new migrations found - migrations table may not have been properly dropped');
        }
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
}
