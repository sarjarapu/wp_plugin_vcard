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

        // Table doesn't exist - create complete table with all columns using Schema API
        // This replaces the old SQL file-based table creation
        $table = $schema->createTable($tableName);

        // Primary key
        $table->addColumn('id', 'bigint', array(
            'unsigned' => true,
            'autoincrement' => true,
        ));

        // Core versioning fields
        $table->addColumn('minisite_id', 'string', array('length' => 32));
        $table->addColumn('version_number', 'integer', array('unsigned' => true));
        $table->addColumn('status', 'string', array(
            'length' => 20,
            'default' => 'draft',
            'comment' => "ENUM('draft','published')",
        ));
        $table->addColumn('label', 'string', array('length' => 120, 'notnull' => false));
        $table->addColumn('comment', 'text', array('notnull' => false));

        // Timestamps and user tracking
        $table->addColumn('created_by', 'bigint', array('unsigned' => true));
        $table->addColumn('created_at', 'datetime_immutable', array(
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ));
        $table->addColumn('published_at', 'datetime_immutable', array('notnull' => false));

        // Rollback tracking
        $table->addColumn('source_version_id', 'bigint', array('unsigned' => true, 'notnull' => false));

        // Minisite fields - slugs
        $table->addColumn('business_slug', 'string', array('length' => 120, 'notnull' => false));
        $table->addColumn('location_slug', 'string', array('length' => 120, 'notnull' => false));

        // Minisite fields - basic info
        $table->addColumn('title', 'string', array('length' => 200, 'notnull' => false));
        $table->addColumn('name', 'string', array('length' => 200, 'notnull' => false));

        // Minisite fields - location
        $table->addColumn('city', 'string', array('length' => 120, 'notnull' => false));
        $table->addColumn('region', 'string', array('length' => 120, 'notnull' => false));
        $table->addColumn('country_code', 'string', array('length' => 2, 'notnull' => false));
        $table->addColumn('postal_code', 'string', array('length' => 20, 'notnull' => false));

        // ⚠️ CRITICAL: location_point - POINT geometry type
        // This is handled via raw SQL in the repository, not through Doctrine
        // DO NOT modify the location_point handling logic
        // See: docs/issues/location-point-lessons-learned.md
        // Note: Doctrine Schema API doesn't support POINT type directly, so we add it via raw SQL
        // We'll add it after the table is created

        // Minisite fields - design and metadata
        $table->addColumn('site_template', 'string', array('length' => 32, 'notnull' => false));
        $table->addColumn('palette', 'string', array('length' => 24, 'notnull' => false));
        $table->addColumn('industry', 'string', array('length' => 40, 'notnull' => false));
        $table->addColumn('default_locale', 'string', array('length' => 10, 'notnull' => false));
        $table->addColumn('schema_version', 'smallint', array('unsigned' => true, 'notnull' => false));
        $table->addColumn('site_version', 'integer', array('unsigned' => true, 'notnull' => false));

        // Content
        $table->addColumn('site_json', 'text'); // LONGTEXT - contains the form data as JSON
        $table->addColumn('search_terms', 'text', array('notnull' => false));

        // Primary key
        $table->setPrimaryKey(array('id'));

        // Unique constraint: one version number per minisite
        $table->addUniqueIndex(array('minisite_id', 'version_number'), 'uniq_minisite_version');

        // Indexes for common queries
        $table->addIndex(array('minisite_id', 'status'), 'idx_minisite_status');
        $table->addIndex(array('minisite_id', 'created_at'), 'idx_minisite_created');

        // ⚠️ CRITICAL: Add location_point column via raw SQL
        // Doctrine Schema API doesn't support POINT type directly
        // This must be added after table creation using raw SQL
        // DO NOT modify the location_point handling logic
        // See: docs/issues/location-point-lessons-learned.md
        $this->addSql("ALTER TABLE `{$tableName}` ADD COLUMN `location_point` POINT NULL AFTER `postal_code`");
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
     */
    public function isTransactional(): bool
    {
        return true;
    }
}

