<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;

/**
 * Migration: Create minisite_versions table for version management
 * Date: 2025-11-05 00:00:00
 *
 * This migration creates the complete wp_minisite_versions table from scratch.
 *
 * ⚠️ CRITICAL: location_point is handled via raw SQL in the repository.
 * DO NOT modify the location_point handling logic.
 * See: docs/issues/location-point-lessons-learned.md
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table with all 27 columns
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 *
 * All columns (27 fields):
 * - Core versioning: id, minisite_id, version_number, status, label, comment
 * - Timestamps: created_at, published_at, created_by
 * - Rollback tracking: source_version_id
 * - Minisite fields: business_slug, location_slug, title, name, city, region, country_code,
 *   postal_code, location_point (POINT), site_template, palette, industry, default_locale,
 *   schema_version, site_version, site_json, search_terms
 */
final class Version20251105000000 extends BaseDoctrineMigration
{

    public function getDescription(): string
    {
        return 'Create minisite_versions table for version management '
            . '(fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $this->logger->info('up() - starting');

        try {
            $tableName = $wpdb->prefix . 'minisite_versions';

            // In up(), $schema is TARGET (empty), so introspect DB to check if table exists
            $schemaManager = $this->connection->createSchemaManager();
            if ($schemaManager->introspectSchema()->hasTable($tableName)) {
                $this->logger->info('up() - table already exists, skipping', array('table' => $tableName));

                // Table already exists, skip (like config and reviews table migrations)
                // Note: If table was created by old migration and needs new columns,
                // a separate migration should handle that to keep migrations simple and focused
                return;
            }

            $this->logger->info('up() - about to create table', array('table' => $tableName));

            // Table doesn't exist - create complete table with all columns using raw SQL
            // This includes location_point (POINT type) which Doctrine Schema API doesn't support
            // ⚠️ CRITICAL: DO NOT modify the location_point handling logic
            // See: docs/issues/location-point-lessons-learned.md
            $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `minisite_id` VARCHAR(32) NOT NULL,
            `version_number` INT UNSIGNED NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'ENUM(''draft'',''published'')',
            `label` VARCHAR(120) NULL,
            `comment` TEXT NULL,
            `created_by` BIGINT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `published_at` DATETIME NULL,
            `source_version_id` BIGINT UNSIGNED NULL,
            `business_slug` VARCHAR(120) NULL,
            `location_slug` VARCHAR(120) NULL,
            `title` VARCHAR(200) NULL,
            `name` VARCHAR(200) NULL,
            `city` VARCHAR(120) NULL,
            `region` VARCHAR(120) NULL,
            `country_code` VARCHAR(2) NULL,
            `postal_code` VARCHAR(20) NULL,
            `location_point` POINT NULL,
            `site_template` VARCHAR(32) NULL,
            `palette` VARCHAR(24) NULL,
            `industry` VARCHAR(40) NULL,
            `default_locale` VARCHAR(10) NULL,
            `schema_version` SMALLINT UNSIGNED NULL,
            `site_version` INT UNSIGNED NULL,
            `site_json` TEXT NOT NULL,
            `search_terms` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_minisite_version` (`minisite_id`, `version_number`),
            KEY `idx_minisite_status` (`minisite_id`, `status`),
            KEY `idx_minisite_created` (`minisite_id`, `created_at`)
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
            $tableName = $wpdb->prefix . 'minisite_versions';

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
