<?php

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManager;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
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
            // Check if Doctrine is available
            if (!class_exists(\Doctrine\Migrations\DependencyFactory::class)) {
                $this->logger->warning("Doctrine Migrations not available - skipping");
                return;
            }
            
            // Use injected EntityManager or create new one
            $em = $this->entityManager ?? DoctrineFactory::createEntityManager();
            $connection = $em->getConnection();
            
            // Create migrations configuration
            // ConfigurationArray accepts table name via array config
            // Note: Doctrine Migrations uses GlobFinder which expects files matching "Version*.php"
            // Our migration file: Version20251103000000.php matches this pattern
            $migrationPath = __DIR__;
            $migrationNamespace = 'Minisite\\Infrastructure\\Migrations\\Doctrine';
            
            $config = new ConfigurationArray([
                'migrations_paths' => [
                    $migrationNamespace => $migrationPath,
                ],
                'all_or_nothing' => true,
                'check_database_platform' => true,
                'organize_migrations' => 'none',
                // Configure metadata storage table with WordPress prefix
                'table_storage' => [
                    'table_name' => $this->getTablePrefix() . 'doctrine_migration_versions',
                ],
            ]);
            
            // Create dependency factory
            $dependencyFactory = DependencyFactory::fromConnection(
                $config,
                new ExistingConnection($connection)
            );
            
            // Ensure metadata storage table exists before running migrations
            // This will create the tracking table if it doesn't exist
            try {
                $metadataStorage = $dependencyFactory->getMetadataStorage();
                $metadataStorage->ensureInitialized();
            } catch (\Exception $e) {
                // If metadata storage fails to initialize, log and continue
                // The migration executor will try to initialize it anyway
                $this->logger->warning("migrate() metadata storage initialization failed", [
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Run migrations
            $migrator = $dependencyFactory->getMigrator();
            $statusCalculator = $dependencyFactory->getMigrationStatusCalculator();
            $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
            
            // Get new migrations to execute
            $availableMigrations = $statusCalculator->getNewMigrations();
            
            if (count($availableMigrations) > 0) {
                $this->logger->info("migrate() executing migrations", [
                    'count' => count($availableMigrations),
                ]);
                
                // Get all available migrations to find latest version
                $migrationRepository = $dependencyFactory->getMigrationRepository();
                $allMigrations = $migrationRepository->getMigrations();
                
                // Find the latest version from all migrations
                // Note: getMigrations() returns AvailableMigrationsSet, we need ->getItems()
                $migrationItems = $allMigrations->getItems();
                
                $latestVersion = null;
                foreach ($migrationItems as $migration) {
                    $latestVersion = $migration->getVersion();
                }
                
                if ($latestVersion === null) {
                    // Log detailed error for debugging
                    $this->logger->error("migrate() no migrations found", [
                        'migration_path' => $migrationPath,
                        'namespace' => $migrationNamespace,
                        'path_exists' => is_dir($migrationPath),
                        'glob_files' => glob($migrationPath . '/Version*.php'),
                        'class_exists' => class_exists($migrationNamespace . '\\Version20251103000000'),
                    ]);
                    throw new \RuntimeException(
                        'No migrations found in repository. ' .
                        'Path: ' . $migrationPath . ', ' .
                        'Namespace: ' . $migrationNamespace . ', ' .
                        'Files found: ' . (is_dir($migrationPath) ? implode(', ', glob($migrationPath . '/Version*.php') ?: []) : 'path does not exist')
                    );
                }
                
                // Create migration plan: migrate from current state to latest version
                $plan = $planCalculator->getPlanUntilVersion($latestVersion);
                
                // Create migrator configuration (not dry-run, execute migrations)
                $migratorConfig = new MigratorConfiguration();
                
                // Execute migrations
                $migrator->migrate($plan, $migratorConfig);
                
                $this->logger->info("migrate() exit - migrations completed", [
                    'count' => count($availableMigrations),
                ]);
            } else {
                $this->logger->info("migrate() exit - no pending migrations");
            }
        } catch (\Exception $e) {
            $this->logger->error("migrate() failed", [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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

