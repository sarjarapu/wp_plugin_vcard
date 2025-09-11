<?php
namespace Minisite\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

/**
 * Finds Migration classes under a directory (PSR-4 autoloaded).
 * Files should be named like: _1_0_0_CreateBase.php with class implementing Migration.
 */
class MigrationLocator
{
    public function __construct(private string $dirAbsolute) {}

    /** @return Migration[] ordered by version ascending */
    public function all(): array
    {
        // Ensure files are loaded (Composer will autoload classes when referenced)
        foreach (glob($this->dirAbsolute . DIRECTORY_SEPARATOR . '*.php') as $file) {
            require_once $file;
        }

        $migrations = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Migration::class)) {
                $ref = new \ReflectionClass($class);
                // Only include classes that physically live under this directory (safety)
                if (str_starts_with($ref->getFileName(), $this->dirAbsolute)) {
                    /** @var Migration $instance */
                    $instance = $ref->newInstance();
                    $migrations[] = $instance;
                }
            }
        }

        usort($migrations, fn(Migration $a, Migration $b) =>
            \version_compare($a->version(), $b->version())
        );

        return $migrations;
    }
}

