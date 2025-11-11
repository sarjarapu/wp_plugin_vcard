<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Persistence;

use Minisite\Domain\Interfaces\TransactionManagerInterface;
use Minisite\Infrastructure\Utils\DatabaseHelper as db;

/**
 * Transaction manager that delegates to WordPress `$wpdb`.
 */
class WordPressTransactionManager implements TransactionManagerInterface
{
    public function startTransaction(): void
    {
        db::query('START TRANSACTION');
    }

    public function commitTransaction(): void
    {
        db::query('COMMIT');
    }

    public function rollbackTransaction(): void
    {
        db::query('ROLLBACK');
    }
}
