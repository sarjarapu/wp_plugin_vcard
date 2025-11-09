<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

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
 * - Minisite fields: business_slug, location_slug, title, name, city, region, country_code, postal_code, location_point (POINT), site_template, palette, industry, default_locale, schema_version, site_version, site_json, search_terms
 */
final class Version20251105000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_versions table for version management (fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_versions';

        // Check if table exists using direct SQL query (avoids schema introspection issues with ENUM columns)
        $connection = $this->connection;
        $tableExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            array($connection->getDatabase(), $tableName)
        )->fetchOne() > 0;

        if ($tableExists) {
            // Table already exists, skip (like config and reviews table migrations)
            // Note: If table was created by old migration and needs new columns,
            // a separate migration should handle that to keep migrations simple and focused
            return;
        }

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

        $this->addSql($createTableSql);
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_versions';

        // Use direct SQL to drop table (more reliable than Schema API for down migrations)
        $connection = $this->connection;
        $tableExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            array($connection->getDatabase(), $tableName)
        )->fetchOne() > 0;

        if ($tableExists) {
            $this->addSql("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    /**
     * Indicate if this migration is transactional
     * 
     * MySQL doesn't support transactional DDL (CREATE TABLE causes implicit commit).
     * Setting this to false prevents Doctrine from wrapping the migration in a transaction,
     * which avoids savepoint errors when MySQL auto-commits the DDL statement.
     * 
     * @see https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html
     */
    public function isTransactional(): bool
    {
        return false; // MySQL doesn't support transactional DDL
    }
}

