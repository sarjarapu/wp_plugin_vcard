<?php

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\AvailableMigrationsSet;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManager;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;
use Psr\Log\LoggerInterface;

/**
 * Doctrine Migrations Runner
 *
 * Handles running Doctrine migrations on plugin activation/update
 *
 * @param EntityManager|null $entityManager Optional EntityManager for testing.
 *                                         If null, creates one via DoctrineFactory.
 */
class DoctrineMigrationRunner
{
    private LoggerInterface $logger;
    private ?EntityManager $entityManager;

    /**
     * @param EntityManager|null $entityManager Optional EntityManager for dependency injection (testing)
     */
    public function __construct(?EntityManager $entityManager = null)
    {
        $this->logger = LoggingServiceProvider::getFeatureLogger('doctrine-migrations');
        $this->entityManager = $entityManager;
    }

    /**
     * Run pending Doctrine migrations
     */
    public function migrate(): void
    {
        $this->logger->info("migrate() entry");

        try {
            if (! $this->isDoctrineAvailable()) {
                return;
            }

            $em = $this->getEntityManager();
            $connection = $em->getConnection();

            // Register custom types and mappings
            // This ensures types are registered even if EntityManager was injected (not created via DoctrineFactory)
            // Safe to call multiple times (idempotent)
            DoctrineFactory::registerCustomTypes($connection);

            $config = $this->createMigrationConfiguration();
            $dependencyFactory = $this->createDependencyFactory($config, $connection);

            $this->ensureMetadataStorageInitialized($dependencyFactory);

            $this->executePendingMigrations($dependencyFactory);
        } catch (\Exception $e) {
            $this->handleMigrationError($e);

            throw $e;
        }
    }

    /**
     * Check if Doctrine Migrations is available
     *
     * @return bool True if available, false otherwise
     */
    private function isDoctrineAvailable(): bool
    {
        if (! class_exists(\Doctrine\Migrations\DependencyFactory::class)) {
            $this->logger->warning("Doctrine Migrations not available - skipping");

            return false;
        }

        return true;
    }

    /**
     * Get EntityManager (injected or create via factory)
     *
     * @return EntityManager
     */
    private function getEntityManager(): EntityManager
    {
        return $this->entityManager ?? DoctrineFactory::createEntityManager();
    }

    /**
     * Create migration configuration
     *
     * @return ConfigurationArray
     */
    private function createMigrationConfiguration(): ConfigurationArray
    {
        $migrationPath = __DIR__;
        $migrationNamespace = 'Minisite\\Infrastructure\\Migrations\\Doctrine';

        return new ConfigurationArray(array(
            'migrations_paths' => array(
                $migrationNamespace => $migrationPath,
            ),
            'all_or_nothing' => true,
            'check_database_platform' => true,
            'organize_migrations' => 'none',
            'table_storage' => array(
                'table_name' => $this->getTablePrefix() . 'minisite_migrations',
            ),
        ));
    }

    /**
     * Create Doctrine Migrations dependency factory
     *
     * @param ConfigurationArray $config
     * @param \Doctrine\DBAL\Connection $connection
     * @return DependencyFactory
     */
    private function createDependencyFactory(
        ConfigurationArray $config,
        \Doctrine\DBAL\Connection $connection
    ): DependencyFactory {
        return DependencyFactory::fromConnection(
            $config,
            new ExistingConnection($connection)
        );
    }

    /**
     * Ensure metadata storage table is initialized
     *
     * @param DependencyFactory $dependencyFactory
     * @return void
     */
    private function ensureMetadataStorageInitialized(DependencyFactory $dependencyFactory): void
    {
        try {
            $metadataStorage = $dependencyFactory->getMetadataStorage();
            $metadataStorage->ensureInitialized();
        } catch (\Exception $e) {
            // If metadata storage fails to initialize, log and continue
            // The migration executor will try to initialize it anyway
            $this->logger->warning("migrate() metadata storage initialization failed", array(
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Execute pending migrations
     *
     * @param DependencyFactory $dependencyFactory
     * @return void
     */
    private function executePendingMigrations(DependencyFactory $dependencyFactory): void
    {
        $statusCalculator = $dependencyFactory->getMigrationStatusCalculator();
        $availableMigrations = $statusCalculator->getNewMigrations();

        if (count($availableMigrations) > 0) {
            // Convert AvailableMigrationsList to AvailableMigrationsSet
            // getItems() returns array of AvailableMigration objects
            $migrationItems = $availableMigrations->getItems();
            $migrationSet = new \Doctrine\Migrations\Metadata\AvailableMigrationsSet(
                $migrationItems
            );
            $this->runMigrations($dependencyFactory, $migrationSet);
        } else {
            $this->logger->info("migrate() exit - no pending migrations");
        }
    }

    /**
     * Run migrations
     *
     * @param DependencyFactory $dependencyFactory
     * @param AvailableMigrationsSet $availableMigrations
     * @return void
     */
    private function runMigrations(
        DependencyFactory $dependencyFactory,
        AvailableMigrationsSet $availableMigrations
    ): void {
        $this->logger->info("migrate() executing migrations", array(
            'count' => count($availableMigrations),
        ));

        $latestVersion = $this->findLatestMigrationVersion($dependencyFactory);

        if ($latestVersion === null) {
            $this->handleNoMigrationsFound();
        }

        $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
        $plan = $planCalculator->getPlanUntilVersion($latestVersion);

        // Track which migrations will be executed (before execution)
        // Use the available migrations that are in the plan
        $migrationsToSeed = array();
        $statusCalculator = $dependencyFactory->getMigrationStatusCalculator();
        $newMigrations = $statusCalculator->getNewMigrations();

        // Get migration class names from new migrations (these are the ones that will be executed)
        foreach ($newMigrations->getItems() as $availableMigration) {
            $migrationInstance = $availableMigration->getMigration();
            $migrationsToSeed[] = get_class($migrationInstance);
        }

        $migrator = $dependencyFactory->getMigrator();
        $migratorConfig = new MigratorConfiguration();
        $migrator->migrate($plan, $migratorConfig);

        // After migrations complete successfully, call seedSampleData() on each executed migration
        $this->executeSeedDataForMigrations($dependencyFactory, $migrationsToSeed);

        $this->logger->info("migrate() exit - migrations completed", array(
            'count' => count($availableMigrations),
        ));
    }

    /**
     * Execute seed sample data for successfully executed migrations
     *
     * @param DependencyFactory $dependencyFactory
     * @param array<string> $migrationClassNames Array of migration class names that were executed
     * @return void
     */
    private function executeSeedDataForMigrations(
        DependencyFactory $dependencyFactory,
        array $migrationClassNames
    ): void {
        $connection = $dependencyFactory->getConnection();

        foreach ($migrationClassNames as $migrationClassName) {
            try {
                // Create migration instance
                $migration = new $migrationClassName($connection, $this->logger);

                // Check if migration has seedSampleData method and should execute it
                if (method_exists($migration, 'seedSampleData')) {
                    // Type check for PHPStan - ensure it's a BaseDoctrineMigration
                    if ($migration instanceof \Minisite\Infrastructure\Migrations\Doctrine\BaseDoctrineMigration) {
                        if ($migration->shouldSeedSampleData()) {
                            $this->logger->info('Executing seed sample data for migration', array(
                                'class' => $migrationClassName,
                            ));

                            $migration->seedSampleData();
                        } else {
                            $this->logger->info('Skipping seed sample data for migration', array(
                                'class' => $migrationClassName,
                            ));
                        }
                    } else {
                        // Fallback for migrations that don't extend BaseDoctrineMigration
                        // Use reflection to safely call shouldSeedSampleData if it exists
                        if (method_exists($migration, 'shouldSeedSampleData')) {
                            $reflection = new \ReflectionMethod($migration, 'shouldSeedSampleData');
                            if ($reflection->isPublic() && $migration->shouldSeedSampleData()) {
                                $this->logger->info('Executing seed sample data for migration', array(
                                    'class' => $migrationClassName,
                                ));
                                $migration->seedSampleData();
                            }
                        } else {
                            // No shouldSeedSampleData method, call seedSampleData directly
                            $this->logger->info('Executing seed sample data for migration', array(
                                'class' => $migrationClassName,
                            ));
                            $migration->seedSampleData();
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail migration - seed data is optional
                $this->logger->warning('Sample seed data failed for migration', array(
                    'class' => $migrationClassName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ));
            }
        }
    }

    /**
     * Find the latest migration version
     *
     * @param DependencyFactory $dependencyFactory
     * @return Version|null
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
     * Handle case where no migrations are found
     *
     * @throws \RuntimeException
     * @return void
     */
    private function handleNoMigrationsFound(): void
    {
        $migrationPath = __DIR__;
        $migrationNamespace = 'Minisite\\Infrastructure\\Migrations\\Doctrine';

        $this->logger->error("migrate() no migrations found", array(
            'migration_path' => $migrationPath,
            'namespace' => $migrationNamespace,
            'path_exists' => is_dir($migrationPath),
            'glob_files' => glob($migrationPath . '/Version*.php'),
            'class_exists' => class_exists($migrationNamespace . '\\Version20251103000000'),
        ));

        $filesFound = is_dir($migrationPath)
            ? implode(', ', glob($migrationPath . '/Version*.php') ?: array())
            : 'path does not exist';

        throw new \RuntimeException(
            'No migrations found in repository. ' .
            'Path: ' . esc_html($migrationPath) . ', ' .
            'Namespace: ' . esc_html($migrationNamespace) . ', ' .
            'Files found: ' . esc_html($filesFound)
        );
    }

    /**
     * Handle migration errors
     *
     * @param \Exception $e
     * @return void
     */
    private function handleMigrationError(\Exception $e): void
    {
        $this->logger->error("migrate() failed", array(
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ));
    }

    /**
     * Get WordPress table prefix
     */
    private function getTablePrefix(): string
    {
        global $wpdb;

        return $wpdb->prefix;
    }
}
