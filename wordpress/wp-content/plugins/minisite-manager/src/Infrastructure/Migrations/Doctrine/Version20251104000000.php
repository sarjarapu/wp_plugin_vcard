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

        // Check if table exists using direct SQL query (avoids schema introspection issues with ENUM columns)
        $connection = $this->connection;
        $tableExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            array($connection->getDatabase(), $tableName)
        )->fetchOne() > 0;

        if ($tableExists) {
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
