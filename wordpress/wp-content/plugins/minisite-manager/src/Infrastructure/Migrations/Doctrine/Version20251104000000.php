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

        // Table doesn't exist - create complete table with all columns using Schema API
        // This replaces the old SQL file-based table creation
        $table = $schema->createTable($tableName);

        $table->addColumn('id', 'bigint', array(
            'unsigned' => true,
            'autoincrement' => true,
        ));
        $table->addColumn('minisite_id', 'string', array('length' => 32));
        $table->addColumn('author_name', 'string', array('length' => 160));
        $table->addColumn('author_email', 'string', array('length' => 255, 'notnull' => false));
        $table->addColumn('author_phone', 'string', array('length' => 20, 'notnull' => false));
        $table->addColumn('author_url', 'string', array('length' => 300, 'notnull' => false));
        $table->addColumn('rating', 'decimal', array('precision' => 2, 'scale' => 1));
        $table->addColumn('body', 'text');
        $table->addColumn('language', 'string', array('length' => 10, 'notnull' => false));
        $table->addColumn('locale', 'string', array('length' => 10, 'notnull' => false));
        $table->addColumn('visited_month', 'string', array('length' => 7, 'notnull' => false));
        $table->addColumn('source', 'string', array(
            'length' => 20,
            'default' => 'manual',
            'comment' => "ENUM('manual','google','yelp','facebook','other')",
        ));
        $table->addColumn('source_id', 'string', array('length' => 160, 'notnull' => false));
        $table->addColumn('status', 'string', array(
            'length' => 20,
            'default' => 'approved',
            'comment' => "ENUM('pending','approved','rejected','flagged')",
        ));
        $table->addColumn('is_email_verified', 'boolean', array('default' => false));
        $table->addColumn('is_phone_verified', 'boolean', array('default' => false));
        $table->addColumn('helpful_count', 'integer', array('default' => 0));
        $table->addColumn('spam_score', 'decimal', array('precision' => 3, 'scale' => 2, 'notnull' => false));
        $table->addColumn('sentiment_score', 'decimal', array('precision' => 3, 'scale' => 2, 'notnull' => false));
        $table->addColumn('display_order', 'integer', array('notnull' => false));
        $table->addColumn('published_at', 'datetime', array('notnull' => false));
        $table->addColumn('moderation_reason', 'string', array('length' => 200, 'notnull' => false));
        $table->addColumn('moderated_by', 'bigint', array('unsigned' => true, 'notnull' => false));
        $table->addColumn('created_at', 'datetime_immutable', array(
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP',
        ));
        $table->addColumn('updated_at', 'datetime_immutable', array(
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'ON UPDATE CURRENT_TIMESTAMP',
        ));
        $table->addColumn('created_by', 'bigint', array('unsigned' => true, 'notnull' => false));

        $table->setPrimaryKey(array('id'));
        $table->addIndex(array('minisite_id'), 'idx_minisite');
        $table->addIndex(array('status', 'created_at'), 'idx_status_date');
        $table->addIndex(array('rating'), 'idx_rating');
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
