<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Lightweight test logger that captures log records for assertions.
 */
final class TestLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    private array $records = array();

    public function log($level, $message, array $context = array()): void
    {
        $this->records[] = array(
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        );
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function hasRecord(string $message, string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(array{level: string, message: string, context: array<string, mixed>}): bool $predicate
     */
    public function findRecord(callable $predicate): ?array
    {
        foreach ($this->records as $record) {
            if ($predicate($record)) {
                return $record;
            }
        }

        return null;
    }

    public function clear(): void
    {
        $this->records = array();
    }
}
