<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Migration: Create minisite_reservations table and purge event
 * Date: 2025-11-10
 *
 * This migration creates the wp_minisite_reservations table and the MySQL event
 * for auto-cleanup of expired reservations.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
final class Version20251110000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_reservations table and purge event '
            . '(fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_reservations';

            // Check if table already exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));
            } else {
                $this->logger->info('up() - about to create table', array('table' => $tableName));

                // Create table using raw SQL for better readability
                $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `business_slug` VARCHAR(255) NOT NULL,
                    `location_slug` VARCHAR(255) NULL,
                    `user_id` BIGINT UNSIGNED NOT NULL,
                    `minisite_id` VARCHAR(32) NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_slug_reservation` (`business_slug`, `location_slug`),
                    KEY `idx_expires_at` (`expires_at`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_minisite_id` (`minisite_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                $this->logger->debug('up() - SQL', array('sql' => $createTableSql));
                $this->addSql($createTableSql);

                // Add foreign key constraints
                $this->addForeignKeyIfNotExists(
                    $tableName,
                    'fk_reservations_user_id',
                    'user_id',
                    $wpdb->prefix . 'users',
                    'ID'
                );

                $this->addForeignKeyIfNotExists(
                    $tableName,
                    'fk_reservations_minisite_id',
                    'minisite_id',
                    $wpdb->prefix . 'minisites',
                    'id'
                );
            }

            // Create MySQL event for auto-cleanup of expired reservations
            $this->createPurgeEventIfNotExists($wpdb->prefix);

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
            // Drop event first
            $eventName = $wpdb->prefix . 'minisite_purge_reservations_event';
            $dropEventSql = "DROP EVENT IF EXISTS `{$eventName}`";
            $this->logger->info('down() - about to drop event', array('event' => $eventName));
            $this->logger->debug('down() - SQL', array('sql' => $dropEventSql));
            $this->addSql($dropEventSql);

            // Drop table
            $tableName = $wpdb->prefix . 'minisite_reservations';
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
     * Create MySQL event for auto-cleanup of expired reservations if it doesn't exist
     */
    private function createPurgeEventIfNotExists(string $prefix): void
    {
        $eventName = $prefix . 'minisite_purge_reservations_event';

        // Check if event already exists
        $eventExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.EVENTS
             WHERE EVENT_SCHEMA = ? AND EVENT_NAME = ?",
            array(DB_NAME, $eventName)
        );

        if (! $eventExists) {
            $createEventSql = "CREATE EVENT IF NOT EXISTS `{$eventName}`
                ON SCHEDULE EVERY 15 MINUTE
                DO
                  DELETE FROM `{$prefix}minisite_reservations`
                  WHERE expires_at < NOW()";
            $this->logger->debug('up() - creating purge event', array('sql' => $createEventSql));
            $this->addSql($createEventSql);
        } else {
            $this->logger->info('up() - event already exists, skipping', array('event' => $eventName));
        }
    }

}
