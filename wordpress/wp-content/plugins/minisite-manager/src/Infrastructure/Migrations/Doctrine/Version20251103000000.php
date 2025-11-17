<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * Migration: Create minisite_config table for configuration management
 * Date: 2025-11-03
 */
final class Version20251103000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_config table for configuration management';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            // Get table name with WordPress prefix
            $tableName = $wpdb->prefix . 'minisite_config';

            // In up(), $schema is TARGET (empty), so introspect DB to check if table exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));

                return;
            }

            $this->logger->info('up() - about to create table', array('table' => $tableName));

            // Table doesn't exist - create complete table with all columns using raw SQL
            // Using raw SQL for better readability and easier manual table creation
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `config_key` VARCHAR(100) NOT NULL,
                `config_value` TEXT NULL,
                `config_type` VARCHAR(20) NOT NULL DEFAULT 'string'
                    COMMENT 'string|integer|boolean|json|encrypted|secret',
                `description` TEXT NULL,
                `is_sensitive` TINYINT(1) NOT NULL DEFAULT 0,
                `is_required` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_config_key` (`config_key`),
                KEY `idx_sensitive` (`is_sensitive`),
                KEY `idx_required` (`is_required`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->logger->debug('up() - SQL', array('sql' => $createTableSql));
            $this->addSql($createTableSql);
            $this->logger->info('up() - completed');
        } catch (\Exception $e) {
            $this->logger->error(
                'up() - failed',
                array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString())
            );

            throw $e;
        }
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('down() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_config';

            // In down(), $schema is CURRENT (already introspected), so use directly
            if ($schema->hasTable($tableName)) {
                $dropSql = "DROP TABLE IF EXISTS `{$tableName}`";
                $this->logger->info('down() - about to drop table', array('table' => $tableName));
                $this->logger->debug('down() - SQL', array('sql' => $dropSql));
                $this->addSql($dropSql);
                $this->logger->info('down() - completed');
            } else {
                $this->logger->info('down() - table does not exist, skipping', array('table' => $tableName));
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'down() - failed',
                array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString())
            );

            throw $e;
        }
    }

    public function seedSampleData(): void
    {
        if (! $this->shouldSeedSampleData()) {
            $this->logger->info('Skipping sample seed data for config table');

            return;
        }

        $this->logger->info('Starting sample seed data for config table');

        try {
            // Ensure ConfigManager is initialized
            if (! isset($GLOBALS['minisite_config_manager'])) {
                if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                    \Minisite\Core\PluginBootstrap::initializeConfigSystem();
                }

                if (! isset($GLOBALS['minisite_config_manager'])) {
                    $this->logger->warning('ConfigManager not available - skipping config seeding');

                    return;
                }
            }

            // Clear EntityManager's identity map right before seeding to prevent collisions
            // This ensures we don't have stale entities from previous operations
            if (isset($GLOBALS['minisite_entity_manager'])) {
                $GLOBALS['minisite_entity_manager']->clear();
            }

            // Seed default configs using existing seeder
            $seeder = new \Minisite\Features\ConfigurationManagement\Services\ConfigSeeder();
            $seeder->seedDefaults($GLOBALS['minisite_config_manager']);

            $this->logger->info('Sample seed data completed for config table');
        } catch (\Exception $e) {
            $this->logger->error('Sample seed data failed for config table', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
            // Don't throw - migration succeeded, sample seed data is optional
        }
    }
}
