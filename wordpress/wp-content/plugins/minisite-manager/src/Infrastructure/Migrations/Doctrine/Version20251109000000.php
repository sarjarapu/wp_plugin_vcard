<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Migration: Create minisite_payment_history table
 * Date: 2025-11-09
 *
 * This migration creates the wp_minisite_payment_history table.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
final class Version20251109000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_payment_history table (fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_payment_history';

            // Check if table already exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));

                return;
            }

            $this->logger->info('up() - about to create table', array('table' => $tableName));

            // Create table using raw SQL for better readability
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `minisite_id` VARCHAR(32) NOT NULL,
                `payment_id` BIGINT UNSIGNED NULL,
                `action` ENUM('initial_payment','renewal','expiration','grace_period_start','grace_period_end','reclamation') NOT NULL,
                `amount` DECIMAL(10,2) NULL,
                `currency` CHAR(3) NULL,
                `payment_reference` VARCHAR(255) NULL,
                `expires_at` DATETIME NULL,
                `grace_period_ends_at` DATETIME NULL,
                `new_owner_user_id` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_minisite` (`minisite_id`),
                KEY `idx_payment` (`payment_id`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->logger->debug('up() - SQL', array('sql' => $createTableSql));
            $this->addSql($createTableSql);

            // Add foreign key constraints
            $this->addForeignKeyIfNotExists(
                $tableName,
                'fk_payment_history_minisite_id',
                'minisite_id',
                $wpdb->prefix . 'minisites',
                'id'
            );

            $this->addForeignKeyIfNotExists(
                $tableName,
                'fk_payment_history_payment_id',
                'payment_id',
                $wpdb->prefix . 'minisite_payments',
                'id'
            );

            $this->addForeignKeyIfNotExists(
                $tableName,
                'fk_payment_history_new_owner_user_id',
                'new_owner_user_id',
                $wpdb->prefix . 'users',
                'ID'
            );

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
            $tableName = $wpdb->prefix . 'minisite_payment_history';

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

}
