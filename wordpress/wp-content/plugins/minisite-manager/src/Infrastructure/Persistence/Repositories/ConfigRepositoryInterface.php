<?php

namespace Minisite\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Config;

interface ConfigRepositoryInterface
{
    /**
     * Get all configurations (ordered by key)
     */
    public function getAll(): array;

    /**
     * Find configuration by key
     */
    public function findByKey(string $key): ?Config;

    /**
     * Save configuration (insert or update)
     */
    public function save(Config $config): Config;

    /**
     * Delete configuration by key
     */
    public function delete(string $key): void;
}
