<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Migration: Create minisite_payments table
 * Date: 2025-11-08
 *
 * This migration creates the wp_minisite_payments table.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
final class Version20251108000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_payments table (fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_payments';

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
                `user_id` BIGINT UNSIGNED NOT NULL,
                `woocommerce_order_id` BIGINT UNSIGNED NULL,
                `status` ENUM('active','expired','grace_period','reclaimed') NOT NULL DEFAULT 'active',
                `amount` DECIMAL(10,2) NOT NULL,
                `currency` CHAR(3) NOT NULL DEFAULT 'USD',
                `payment_method` VARCHAR(100) NULL,
                `payment_reference` VARCHAR(255) NULL,
                `paid_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `grace_period_ends_at` DATETIME NOT NULL,
                `renewed_at` DATETIME NULL,
                `reclaimed_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_minisite_status` (`minisite_id`, `status`),
                KEY `idx_user_status` (`user_id`, `status`),
                KEY `idx_expires_at` (`expires_at`),
                KEY `idx_grace_period_ends_at` (`grace_period_ends_at`),
                KEY `idx_woocommerce_order` (`woocommerce_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->logger->debug('up() - SQL', array('sql' => $createTableSql));
            $this->addSql($createTableSql);

            // Add foreign key constraints
            $this->addForeignKeyIfNotExists(
                $tableName,
                'fk_payments_minisite_id',
                'minisite_id',
                $wpdb->prefix . 'minisites',
                'id'
            );

            $this->addForeignKeyIfNotExists(
                $tableName,
                'fk_payments_user_id',
                'user_id',
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
            $tableName = $wpdb->prefix . 'minisite_payments';

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
