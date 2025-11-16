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

    /**
     * Seed sample data for this migration
     *
     * Called automatically after up() completes successfully.
     * Override in subclasses to provide sample seed data.
     *
     * Note: This is sample data (not test data). The "Test" keyword is reserved for testing phases.
     *
     * @return void
     */
    public function seedSampleData(): void
    {
        // Default: no seed data
        // Override in subclasses that need sample seed data
    }

    /**
     * Check if sample seed data should be executed
     *
     * By default, sample data seeds in all environments (this is live sample data).
     * Can be overridden to skip in specific environments if needed.
     *
     * Note: This is sample data, not test data. The "Test" keyword is reserved for testing phases.
     *
     * @return bool
     */
    public function shouldSeedSampleData(): bool
    {
        // Sample data can run in all environments by default
        // Override if you need environment-specific logic
        return true;
    }

    /**
     * Ensure repositories are initialized for seeding
     *
     * Helper method to ensure repositories are available before seeding sample data.
     *
     * @return void
     */
    protected function ensureRepositoriesInitialized(): void
    {
        if (
            ! isset($GLOBALS['minisite_repository']) ||
            ! isset($GLOBALS['minisite_version_repository']) ||
            ! isset($GLOBALS['minisite_review_repository'])
        ) {
            // Initialize repositories if not already available
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                \Minisite\Core\PluginBootstrap::initializeConfigSystem();
            }
        }
    }
}
