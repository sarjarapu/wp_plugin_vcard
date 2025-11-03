<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory;

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
        
        if ($schema->hasTable($tableName)) {
            // Table already exists, skip
            return;
        }
        
        $table = $schema->createTable($tableName);
        
        $table->addColumn('id', 'bigint', [
            'unsigned' => true,
            'autoincrement' => true,
        ]);
        $table->addColumn('config_key', 'string', ['length' => 100]);
        $table->addColumn('config_value', 'text', ['notnull' => false]);
        $table->addColumn('config_type', 'string', [
            'length' => 20,
            'default' => 'string',
            'comment' => 'string|integer|boolean|json|encrypted|secret'
        ]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('is_sensitive', 'boolean', ['default' => false]);
        $table->addColumn('is_required', 'boolean', ['default' => false]);
        $table->addColumn('created_at', 'datetime_immutable', [
            'notnull' => true,
        ]);
        $table->addColumn('updated_at', 'datetime_immutable', [
            'notnull' => true,
        ]);
        
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['config_key'], 'uniq_config_key');
        $table->addIndex(['is_sensitive'], 'idx_sensitive');
        $table->addIndex(['is_required'], 'idx_required');
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
     * Return true to wrap migration in a transaction
     */
    public function isTransactional(): bool
    {
        return true;
    }
}

