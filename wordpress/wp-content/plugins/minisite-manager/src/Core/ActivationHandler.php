<?php

namespace Minisite\Core;

/**
 * Activation Handler
 *
 * SINGLE RESPONSIBILITY: Handle plugin activation
 * - Database migrations
 * - Role and capability setup
 * - Initial configuration
 */
final class ActivationHandler
{
    public static function handle(): void
    {
        // Set flag to flush rewrite rules after init
        update_option('minisite_flush_rewrites', 1, false);

        // Run database migrations
        self::runMigrations();

        // Sync roles and capabilities
        RoleManager::syncRolesAndCapabilities();

        // Seed default configurations (after migrations, before init)
        // Note: ConfigManager will be initialized in initializeCore()
        // So we delay seeding until init hook
        add_action('init', array(self::class, 'seedDefaultConfigs'), 15);
    }

    private static function runMigrations(): void
    {
        // Run Doctrine migrations (all tables are now managed by Doctrine)
        try {
            // Check if Doctrine is available before attempting migration
            if (! class_exists(\Doctrine\ORM\EntityManager::class)) {
                $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
                $logger->warning('Doctrine ORM not available - skipping migrations. Run: composer install');

                return;
            }

            $doctrineRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner();
            $doctrineRunner->migrate();
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Doctrine migrations failed', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }

        // Seed test data in non-production environments
        // NOTE: Legacy migration system (_1_0_0_CreateBase) has been replaced by seeder services
        if (! defined('MINISITE_LIVE_PRODUCTION') || ! MINISITE_LIVE_PRODUCTION) {
            add_action('init', array(self::class, 'seedTestData'), 20);
        }
    }

    /**
     * Seed test data using Doctrine-based seeder services
     * Called on 'init' hook after Doctrine is initialized
     */
    public static function seedTestData(): void
    {
        // Ensure repositories are initialized first
        if (
            ! isset($GLOBALS['minisite_repository']) ||
            ! isset($GLOBALS['minisite_version_repository']) ||
            ! isset($GLOBALS['minisite_review_repository'])
        ) {
            // Try to initialize them now (might not have run yet)
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }

            // If still not available, retry on next init hook
            if (
                ! isset($GLOBALS['minisite_repository']) ||
                ! isset($GLOBALS['minisite_version_repository']) ||
                ! isset($GLOBALS['minisite_review_repository'])
            ) {
                // Prevent infinite loop - only retry once
                static $retryCount = 0;
                if ($retryCount < 2) {
                    $retryCount++;
                    add_action('init', array(self::class, 'seedTestData'), 25);
                }

                return;
            }
        }

        try {
            // Seed minisites first
            $minisiteIds = self::seedTestMinisites();

            // Seed versions for each minisite
            self::seedTestVersions($minisiteIds);

            // Seed reviews for each minisite
            self::seedTestReviews($minisiteIds);
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Failed to seed test data', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }

    /**
     * Seed test minisites
     *
     * @return array Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT' => minisite IDs
     */
    private static function seedTestMinisites(): array
    {
        /** @var \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface $minisiteRepo */
        $minisiteRepo = $GLOBALS['minisite_repository'];

        $minisiteSeeder = new \Minisite\Features\MinisiteManagement\Services\MinisiteSeederService($minisiteRepo);

        return $minisiteSeeder->seedAllTestMinisites();
    }

    /**
     * Seed test versions for minisites
     *
     * @param array $minisiteIds Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT' => minisite IDs
     */
    private static function seedTestVersions(array $minisiteIds): void
    {
        /** @var \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface $minisiteRepo */
        $minisiteRepo = $GLOBALS['minisite_repository'];
        /** @var \Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface $versionRepo */
        $versionRepo = $GLOBALS['minisite_version_repository'];

        $versionSeeder = new \Minisite\Features\VersionManagement\Services\VersionSeederService($versionRepo);

        foreach ($minisiteIds as $minisiteId) {
            if (empty($minisiteId)) {
                continue;
            }

            $minisite = $minisiteRepo->findById($minisiteId);
            if (! $minisite) {
                continue;
            }

            $version = $versionSeeder->createInitialVersionFromMinisite($minisite);
            $savedVersion = $versionRepo->save($version);
            $minisiteRepo->updateCurrentVersionId($minisiteId, $savedVersion->id);
        }
    }

    /**
     * Seed test reviews for minisites
     *
     * @param array $minisiteIds Array with keys: 'ACME', 'LOTUS', 'GREEN', 'SWIFT' => minisite IDs
     */
    private static function seedTestReviews(array $minisiteIds): void
    {
        /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface $reviewRepo */
        $reviewRepo = $GLOBALS['minisite_review_repository'];

        $reviewSeeder = new \Minisite\Features\ReviewManagement\Services\ReviewSeederService($reviewRepo);
        $reviewSeeder->seedAllTestReviews($minisiteIds);
    }

    /**
     * Seed default configurations
     * Called on 'init' hook after ConfigManager is initialized
     */
    public static function seedDefaultConfigs(): void
    {
        // Ensure ConfigManager is initialized first
        if (! isset($GLOBALS['minisite_config_manager'])) {
            // Try to initialize it now (might not have run yet)
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }

            // If still not available, retry on next init hook
            if (! isset($GLOBALS['minisite_config_manager'])) {
                // Prevent infinite loop - only retry once
                static $retryCount = 0;
                if ($retryCount < 2) {
                    $retryCount++;
                    add_action('init', array(self::class, 'seedDefaultConfigs'), 20);
                }

                return;
            }
        }

        try {
            $seeder = new \Minisite\Features\ConfigurationManagement\Services\ConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);
        } catch (\Exception $e) {
            // Log error with full details
            $logger = \Minisite\Infrastructure\Logging\LoggingServiceProvider::getFeatureLogger('activation');
            $logger->error('Failed to seed default configs', array(
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ));
        }
    }
}
