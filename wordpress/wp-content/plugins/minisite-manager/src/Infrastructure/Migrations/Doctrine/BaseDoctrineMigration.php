<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Migrations\Doctrine;

use Doctrine\Migrations\AbstractMigration;
use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Base Doctrine Migration Class
 *
 * Provides common functionality for all Doctrine migrations:
 * - Logging setup
 * - Foreign key management
 * - Transaction handling
 */
abstract class BaseDoctrineMigration extends AbstractMigration
{
    protected LoggerInterface $logger;

    public function __construct(\Doctrine\DBAL\Connection $connection, \Psr\Log\LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);
        $this->logger = LoggingServiceProvider::getFeatureLogger(static::class);
    }

    /**
     * Add a foreign key constraint only if it doesn't already exist
     *
     * @param string $table Table name (with prefix)
     * @param string $constraintName Foreign key constraint name
     * @param string $column Column name in the table
     * @param string $referencedTable Referenced table name (with prefix)
     * @param string $referencedColumn Referenced column name
     */
    protected function addForeignKeyIfNotExists(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        // Check if the constraint already exists
        $constraintExists = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
            array(DB_NAME, $table, $constraintName)
        );

        if (! $constraintExists) {
            $fkSql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}`
                     FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}`(`{$referencedColumn}`)
                     ON DELETE CASCADE";
            $this->logger->debug('up() - adding foreign key', array('sql' => $fkSql));
            $this->addSql($fkSql);
        } else {
            $this->logger->info('up() - foreign key already exists, skipping', array(
                'constraint' => $constraintName,
                'table' => $table,
            ));
        }
    }

    /**
     * MySQL doesn't support transactional DDL (CREATE TABLE causes implicit commit).
     * Return false to avoid Doctrine SAVEPOINT exception errors.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/explanation/implicit-commits.html
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
