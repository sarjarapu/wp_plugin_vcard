<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create minisite_reviews table with all MVP fields (fresh start)
 * Date: 2025-11-04
 *
 * This migration creates the complete wp_minisite_reviews table from scratch.
 *
 * Assumptions:
 * - Fresh start: This migration creates the complete table with all 24 MVP columns
 * - If table exists: It was created by this migration, so skip gracefully (idempotent)
 * - No upgrade scenario: Old SQL-based tables are not supported
 *
 * All MVP columns (24 fields):
 * - Core: id, minisite_id, author_name, author_email, author_phone, author_url
 * - Review content: rating, body, language, locale, visited_month
 * - Metadata: source, source_id, status, created_at, updated_at, created_by
 * - Verification: is_email_verified, is_phone_verified
 * - Metrics: helpful_count, spam_score, sentiment_score
 * - Display: display_order, published_at
 * - Moderation: moderation_reason, moderated_by
 */
final class Version20251104000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_reviews table with all MVP fields (fresh start, replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_reviews';

        // Check if table exists using Schema API (more readable than raw SQL)
        // Note: In up(), $schema is the TARGET schema (what we want to build), so we need
        // to introspect the actual database to check if table exists
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->introspectSchema()->hasTable($tableName)) {
            // Table already exists, skip (like config table migration)
            // Note: If table was created by old migration and needs new columns,
            // a separate migration should handle that to keep migrations simple and focused
            return;
        }

        // Table doesn't exist - create complete table with all columns using raw SQL
        // Using raw SQL for better readability and easier manual table creation
        $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `minisite_id` VARCHAR(32) NOT NULL,
            `author_name` VARCHAR(160) NOT NULL,
            `author_email` VARCHAR(255) NULL,
            `author_phone` VARCHAR(20) NULL,
            `author_url` VARCHAR(300) NULL,
            `rating` DECIMAL(2,1) NOT NULL,
            `body` TEXT NOT NULL,
            `language` VARCHAR(10) NULL,
            `locale` VARCHAR(10) NULL,
            `visited_month` VARCHAR(7) NULL,
            `source` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'ENUM(''manual'',''google'',''yelp'',''facebook'',''other'')',
            `source_id` VARCHAR(160) NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'approved' COMMENT 'ENUM(''pending'',''approved'',''rejected'',''flagged'')',
            `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `helpful_count` INT NOT NULL DEFAULT 0,
            `spam_score` DECIMAL(3,2) NULL,
            `sentiment_score` DECIMAL(3,2) NULL,
            `display_order` INT NULL,
            `published_at` DATETIME NULL,
            `moderation_reason` VARCHAR(200) NULL,
            `moderated_by` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_by` BIGINT UNSIGNED NULL,
            PRIMARY KEY (`id`),
            KEY `idx_minisite` (`minisite_id`),
            KEY `idx_status_date` (`status`, `created_at`),
            KEY `idx_rating` (`rating`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->addSql($createTableSql);
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_reviews';

        // Use Schema API for readability
        // Note: In down(), $schema is the CURRENT database schema (what exists now)
        if ($schema->hasTable($tableName)) {
            // Use addSql() instead of $schema->dropTable() because:
            // - $schema->dropTable() modifies the schema object but doesn't generate SQL via getSql()
            // - addSql() explicitly queues SQL that can be retrieved via getSql() for testing
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
