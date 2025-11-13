<?php

namespace Minisite\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

/**
 * Finds Migration classes under a directory (PSR-4 autoloaded).
 * Files should be named like: _1_0_0_CreateBase.php with class implementing Migration.
 */
class MigrationLocator
{
    public function __construct(private string $dirAbsolute)
    {
    }

    /** @return Migration[] ordered by version ascending */
    public function all(): array
    {
        // Ensure files are loaded (Composer will autoload classes when referenced)
        foreach (glob($this->dirAbsolute . DIRECTORY_SEPARATOR . '*.php') as $file) {
            require_once $file;
        }

        $migrations = array();
        foreach (get_declared_classes() as $class) {
            $ref = new \ReflectionClass($class);
            // Check if class implements Migration interface
            if ($ref->implementsInterface(Migration::class)) {
                // Only include classes that physically live under this directory (safety)
                // Use realpath to handle symlinks (e.g., /var -> /private/var on macOS)
                $fileRealPath = realpath($ref->getFileName());
                $dirRealPath = realpath($this->dirAbsolute);

                if ($fileRealPath && $dirRealPath && str_starts_with($fileRealPath, $dirRealPath)) {
                    /** @var Migration $instance */
                    $instance = $ref->newInstance();
                    $migrations[] = $instance;
                }
            }
        }

        usort(
            $migrations,
            fn (Migration $a, Migration $b) =>
            \version_compare($a->version(), $b->version())
        );

        return $migrations;
    }
}
