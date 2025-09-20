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

        // Safety (dev only): if our tables are missing but option says up-to-date, force a migration run
        if ((defined('MINISITE_LIVE_PRODUCTION') ? !MINISITE_LIVE_PRODUCTION : true) && $this->tablesMissing($wpdb)) {
            // Reset stored version so runner applies base migration
            update_option($this->optionKey, '0.0.0', false);
        }

        $locator = new MigrationLocator(
            // directory where migration classes live
            \trailingslashit(\MINISITE_PLUGIN_DIR) . 'src/Infrastructure/Versioning/Migrations'
        );

        $runner  = new MigrationRunner($this->targetVersion, $this->optionKey, $locator);
        if (\version_compare($runner->current(), $this->targetVersion, '<')) {
            $runner->upgradeTo($wpdb, static function ($msg) {
                // Use error_log for now; swap with your Logger if desired
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($msg);
                }
            });
        }
    }

    private function tablesMissing(\wpdb $wpdb): bool
    {
        $prefix = $wpdb->prefix;
        $tables = [
            $prefix . 'minisites',
            $prefix . 'minisite_versions',
            $prefix . 'minisite_reviews',
            $prefix . 'minisite_bookmarks',
        ];

        foreach ($tables as $t) {
            // SHOW TABLES LIKE is portable enough for our case
            $exists = (string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
            if ($exists !== $t) {
                return true; // Missing at least one table
            }
        }
        return false;
    }
}
