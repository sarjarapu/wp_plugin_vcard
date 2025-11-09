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

        // Check if table exists using direct SQL query (avoids schema introspection issues with ENUM columns)
        $connection = $this->connection;
        $tableExists = $connection->executeQuery(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            array($connection->getDatabase(), $tableName)
        )->fetchOne() > 0;

        if ($tableExists) {
            // Table already exists, skip
            return;
        }

        $table = $schema->createTable($tableName);

        $table->addColumn('id', 'bigint', array(
            'unsigned' => true,
            'autoincrement' => true,
        ));
        $table->addColumn('config_key', 'string', array('length' => 100));
        $table->addColumn('config_value', 'text', array('notnull' => false));
        $table->addColumn('config_type', 'string', array(
            'length' => 20,
            'default' => 'string',
            'comment' => 'string|integer|boolean|json|encrypted|secret',
        ));
        $table->addColumn('description', 'text', array('notnull' => false));
        $table->addColumn('is_sensitive', 'boolean', array('default' => false));
        $table->addColumn('is_required', 'boolean', array('default' => false));
        $table->addColumn('created_at', 'datetime_immutable', array(
            'notnull' => true,
        ));
        $table->addColumn('updated_at', 'datetime_immutable', array(
            'notnull' => true,
        ));

        $table->setPrimaryKey(array('id'));
        $table->addUniqueIndex(array('config_key'), 'uniq_config_key');
        $table->addIndex(array('is_sensitive'), 'idx_sensitive');
        $table->addIndex(array('is_required'), 'idx_required');
    }

    public function down(Schema $schema): void
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'minisite_config';

        if ($schema->hasTable($tableName)) {
            $schema->dropTable($tableName);
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
