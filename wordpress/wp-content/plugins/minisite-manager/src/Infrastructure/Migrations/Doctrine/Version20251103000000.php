<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 *
 * Migration: Create minisite_config table for configuration management
 * Date: 2025-11-03
 */
final class Version20251103000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create minisite_config table for configuration management';
    }

    public function up(Schema $schema): void
    {
        global $wpdb;

        // Get table name with WordPress prefix
        $tableName = $wpdb->prefix . 'minisite_config';

        // Check if table exists using Schema API (more readable than raw SQL)
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->introspectSchema()->hasTable($tableName)) {
            // Table already exists, skip
            return;
        }

        // Table doesn't exist - create complete table with all columns using raw SQL
        // Using raw SQL for better readability and easier manual table creation
        $createTableSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `config_key` VARCHAR(100) NOT NULL,
            `config_value` TEXT NULL,
            `config_type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT 'string|integer|boolean|json|encrypted|secret',
            `description` TEXT NULL,
            `is_sensitive` TINYINT(1) NOT NULL DEFAULT 0,
            `is_required` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_config_key` (`config_key`),
            KEY `idx_sensitive` (`is_sensitive`),
            KEY `idx_required` (`is_required`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->addSql($createTableSql);
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_config';

        // Use Schema API for readability (checks target schema)
        if ($schema->hasTable($tableName)) {
            // Use addSql() for compatibility with migration framework
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
