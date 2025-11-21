<?php

declare(strict_types=1);

namespace Minisite\Infrastructure\Persistence\Contracts;

/**
 * Transaction Manager Interface
 *
 * Provides database transaction controls that can be implemented
 * by different persistence layers (WordPress `$wpdb`, Doctrine, etc.).
 */
interface TransactionManagerInterface
{
    /**
     * Begin a transaction.
     */
    public function startTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commitTransaction(): void;

    /**
     * Roll back the current transaction.
     */
    public function rollbackTransaction(): void;
}

