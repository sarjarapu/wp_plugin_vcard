<?php
namespace Minisite\Infrastructure\Versioning;

/**
 * Orchestrates migrations on activation and during admin requests.
 */
class VersioningController
{
    public function __construct(
        private string $targetVersion,
        private string $optionKey
    ){}

    public function activate(): void
    {
        $this->maybeRun(); // Ensure base schema exists on activation
    }

    public function maybeRun(): void
    {
        global $wpdb;

        $locator = new MigrationLocator(
            // directory where migration classes live
            \trailingslashit(\MINISITE_PLUGIN_DIR) . 'src/Infrastructure/Versioning/Migrations'
        );

        $runner  = new MigrationRunner($this->targetVersion, $this->optionKey, $locator);
        if (\version_compare($runner->current(), $this->targetVersion, '<')) {
            $runner->runUpToTarget($wpdb, static function ($msg) {
                // Use error_log for now; swap with your Logger if desired
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($msg);
                }
            });
        }
    }
}
