<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use Minisite\Infrastructure\Persistence\WordPressTransactionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPressTransactionManager::class)]
final class WordPressTransactionManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_start_transaction_calls_database_helper(): void
    {
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Utils\DatabaseHelper');
        $mock->shouldReceive('query')->once()->with('START TRANSACTION');

        (new WordPressTransactionManager())->startTransaction();
    }

    public function test_commit_transaction_calls_database_helper(): void
    {
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Utils\DatabaseHelper');
        $mock->shouldReceive('query')->once()->with('COMMIT');

        (new WordPressTransactionManager())->commitTransaction();
    }

    public function test_rollback_transaction_calls_database_helper(): void
    {
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Utils\DatabaseHelper');
        $mock->shouldReceive('query')->once()->with('ROLLBACK');

        (new WordPressTransactionManager())->rollbackTransaction();
    }

    public function test_implements_interface(): void
    {
        $manager = new WordPressTransactionManager();
        $this->assertInstanceOf(
            \Minisite\Infrastructure\Persistence\Contracts\TransactionManagerInterface::class,
            $manager
        );
    }
}
