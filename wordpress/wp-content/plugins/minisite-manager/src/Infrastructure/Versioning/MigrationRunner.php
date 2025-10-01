<?php

namespace Minisite\Infrastructure\Versioning;

use Minisite\Infrastructure\Versioning\Contracts\Migration;

/**
 * Applies pending migrations up to the target version.
 * Stores current version in wp_options.
 */
class MigrationRunner
{
    public function __construct(
        private string $targetVersion,
        private string $optionKey,
        private MigrationLocator $locator
    ) {
    }

    public function current(): string
    {
        return get_option($this->optionKey, '0.0.0');
    }

    /**
     * Run all migrations whose version is > current and <= targetVersion.
     * Each migration must be idempotent.
     */
    public function upgradeTo(\wpdb $wpdb, ?callable $logger = null): void
    {
        $logger ??= static function ($msg) {
        };
        $current = $this->current();
        $target  = $this->targetVersion;

        $migs = $this->locator->all();
        foreach ($migs as $m) {
            $ver = $m->version();
            if (\version_compare($current, $ver, '<=') && \version_compare($ver, $target, '<=')) {
                // Skip if already at this version (only run strictly greater)
                if (\version_compare($current, $ver, '<')) {
                    $logger(sprintf('[minisite] Applying migration %s: %s', $ver, $m->description()));
                    $m->up($wpdb);
                    update_option($this->optionKey, $ver, false);
                    $current = $ver;
                }
            }
        }
    }

    /**
     * Optional downgrade (rare in WP plugins). Best-effort only.
     */
    public function downgradeTo(\wpdb $wpdb, string $target, ?callable $logger = null): void
    {
        $logger ??= static function ($msg) {
        };
        $current = $this->current();

        // We go reverse order for down
        $migs = $this->locator->all();
        usort($migs, fn($a, $b) => \version_compare($b->version(), $a->version()));

        foreach ($migs as $m) {
            $ver = $m->version();
            if (\version_compare($current, $ver, '>=') && \version_compare($ver, $target, '>')) {
                $logger(sprintf('[minisite] Reverting migration %s: %s', $ver, $m->description()));
                $m->down($wpdb);
                update_option($this->optionKey, $target, false);
                $current = $target;
            }
        }
    }
}
