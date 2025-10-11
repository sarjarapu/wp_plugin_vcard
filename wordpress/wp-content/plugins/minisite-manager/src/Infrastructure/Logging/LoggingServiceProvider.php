<?php

namespace Minisite\Infrastructure\Logging;

use Psr\Log\LoggerInterface;

/**
 * Service provider for logging dependencies
 */
class LoggingServiceProvider
{
    /**
     * Register logging services
     */
    public static function register(): void
    {
        // Ensure logs directory exists
        self::ensureLogsDirectory();
    }
    
    /**
     * Get the main application logger
     */
    public static function getLogger(): LoggerInterface
    {
        return LoggerFactory::getLogger();
    }
    
    /**
     * Get a feature-specific logger
     */
    public static function getFeatureLogger(string $feature): LoggerInterface
    {
        return LoggerFactory::createFeatureLogger($feature);
    }
    
    /**
     * Ensure the logs directory exists and is writable
     */
    private static function ensureLogsDirectory(): void
    {
        $logsDir = WP_CONTENT_DIR . '/uploads/minisite-logs';
        
        if (!file_exists($logsDir)) {
            wp_mkdir_p($logsDir);
        }
        
        // Create .htaccess to protect log files
        $htaccessFile = $logsDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Order deny,allow\nDeny from all\n");
        }
        
        // Create index.php to prevent directory listing
        $indexFile = $logsDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }
    }
}
