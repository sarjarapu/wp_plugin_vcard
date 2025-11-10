<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * Migration: Create minisite_config table for configuration management
 * Date: 2025-11-03
 */
final class Version20251103000000 extends AbstractMigration
{
    private LoggerInterface $logger;

    public function __construct(\Doctrine\DBAL\Connection $connection, \Psr\Log\LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);
        $this->logger = LoggingServiceProvider::getFeatureLogger('Version20251103000000');
    }

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

    /**
     * MySQL doesn't support transactional DDL (CREATE TABLE causes implicit commit).
     * Return false to avoid Doctrine SAVEPOINT exception errors.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
