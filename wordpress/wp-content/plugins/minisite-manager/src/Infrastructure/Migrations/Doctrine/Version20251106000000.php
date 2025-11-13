<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Migration: Create minisites table for minisite management
 * Date: 2025-11-06 00:00:00
 *
 * This migration creates the complete wp_minisites table from scratch.
 *
 * ⚠️ CRITICAL: location_point is handled via raw SQL in the repository.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table with all columns
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 */
final class Version20251106000000 extends BaseDoctrineMigration
{
    public function getDescription(): string
    {
        return 'Create minisites table for minisite management '
            . '(fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisites';

            // In up(), $schema is TARGET (empty), so introspect DB to check if table exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));

                return;
            }

            $this->logger->info('up() - about to create table', array('table' => $tableName));

            // Table doesn't exist - create complete table with all columns using raw SQL
            // This includes location_point (POINT type) which Doctrine Schema API doesn't support
            // ⚠️ CRITICAL: DO NOT modify the location_point handling logic
            // See: docs/issues/location-point-lessons-learned.md
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
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
                `status` VARCHAR(20) NOT NULL DEFAULT 'published'
                    COMMENT 'ENUM(''draft'',''published'',''archived'')',
                `publish_status` VARCHAR(20) NOT NULL DEFAULT 'draft'
                    COMMENT 'ENUM(''draft'',''reserved'',''published'')',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `published_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `_minisite_current_version_id` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_slug` (`slug`),
                UNIQUE KEY `uniq_business_location` (`business_slug`, `location_slug`)
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
            $tableName = $wpdb->prefix . 'minisites';

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
}
