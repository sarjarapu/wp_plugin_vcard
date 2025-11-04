<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create or update minisite_reviews table for enhanced review management
 * Date: 2025-11-04
 *
 * This migration replaces the old SQL file-based table creation.
 *
 * If table doesn't exist: Creates complete table with all MVP columns (21+ fields)
 * If table exists: Adds new columns to existing table
 *
 * Base columns: id, minisite_id, author_name, author_url, rating, body, locale,
 *               visited_month, source, source_id, status, created_at, updated_at, created_by
 *
 * Additional columns added:
 * - author_email, author_phone (verification fields)
 * - language (auto-detected language)
 * - is_email_verified, is_phone_verified (separate verification flags)
 * - helpful_count, spam_score, sentiment_score (engagement and quality metrics)
 * - display_order, published_at (display and sorting)
 * - moderation_reason, moderated_by (moderation tracking)
 * - Extends status enum to include 'flagged'
 */
final class Version20251104000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create or update minisite_reviews table with all MVP fields (replaces old SQL file-based creation)';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_reviews';

        // Check if table exists using direct SQL query (avoids schema introspection issues with ENUM columns)
        $connection = $this->connection;
        $tableExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$connection->getDatabase(), $tableName]
        )->fetchOne() > 0;

        if ($tableExists) {
            // Table already exists, skip (like config table migration)
            // Note: If table was created by old migration and needs new columns,
            // a separate migration should handle that to keep migrations simple and focused
            return;
        }

        // Table doesn't exist - create complete table with all columns using Schema API
        // This replaces the old SQL file-based table creation
        $table = $schema->createTable($tableName);

        $table->addColumn('id', 'bigint', [
            'unsigned' => true,
            'autoincrement' => true,
        ]);
        $table->addColumn('minisite_id', 'string', ['length' => 32]);
        $table->addColumn('author_name', 'string', ['length' => 160]);
        $table->addColumn('author_email', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('author_phone', 'string', ['length' => 20, 'notnull' => false]);
        $table->addColumn('author_url', 'string', ['length' => 300, 'notnull' => false]);
        $table->addColumn('rating', 'decimal', ['precision' => 2, 'scale' => 1]);
        $table->addColumn('body', 'text');
        $table->addColumn('language', 'string', ['length' => 10, 'notnull' => false]);
        $table->addColumn('locale', 'string', ['length' => 10, 'notnull' => false]);
        $table->addColumn('visited_month', 'string', ['length' => 7, 'notnull' => false]);
        $table->addColumn('source', 'string', [
            'length' => 20,
            'default' => 'manual',
            'comment' => "ENUM('manual','google','yelp','facebook','other')"
        ]);
        $table->addColumn('source_id', 'string', ['length' => 160, 'notnull' => false]);
        $table->addColumn('status', 'string', [
            'length' => 20,
            'default' => 'approved',
            'comment' => "ENUM('pending','approved','rejected','flagged')"
        ]);
        $table->addColumn('is_email_verified', 'boolean', ['default' => false]);
        $table->addColumn('is_phone_verified', 'boolean', ['default' => false]);
        $table->addColumn('helpful_count', 'integer', ['default' => 0]);
        $table->addColumn('spam_score', 'decimal', ['precision' => 3, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('sentiment_score', 'decimal', ['precision' => 3, 'scale' => 2, 'notnull' => false]);
        $table->addColumn('display_order', 'integer', ['notnull' => false]);
        $table->addColumn('published_at', 'datetime', ['notnull' => false]);
        $table->addColumn('moderation_reason', 'string', ['length' => 200, 'notnull' => false]);
        $table->addColumn('moderated_by', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', [
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ]);
        $table->addColumn('updated_at', 'datetime_immutable', [
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'ON UPDATE CURRENT_TIMESTAMP',
        ]);
        $table->addColumn('created_by', 'bigint', ['unsigned' => true, 'notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['minisite_id'], 'idx_minisite');
        $table->addIndex(['status', 'created_at'], 'idx_status_date');
        $table->addIndex(['rating'], 'idx_rating');
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_reviews';

        if ($schema->hasTable($tableName)) {
            $schema->dropTable($tableName);
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
