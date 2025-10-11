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
        // Use a more secure location outside of uploads directory
        $logsDir = WP_CONTENT_DIR . '/minisite-logs';

        if (!file_exists($logsDir)) {
            wp_mkdir_p($logsDir);
        }

        // Create .htaccess to protect log files (Apache)
        $htaccessFile = $logsDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccessContent = "# Deny access to all files in this directory\n";
            $htaccessContent .= "Order deny,allow\n";
            $htaccessContent .= "Deny from all\n";
            $htaccessContent .= "\n";
            $htaccessContent .= "# Additional security headers\n";
            $htaccessContent .= "<Files \"*.log*\">\n";
            $htaccessContent .= "    Order deny,allow\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</Files>\n";

            file_put_contents($htaccessFile, $htaccessContent);
        }

        // Create index.php to prevent directory listing
        $indexFile = $logsDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }

        // Create nginx.conf for Nginx servers
        $nginxFile = $logsDir . '/nginx.conf';
        if (!file_exists($nginxFile)) {
            $nginxContent = "# Nginx configuration to deny access to log files\n";
            $nginxContent .= "location ~* \\.log$ {\n";
            $nginxContent .= "    deny all;\n";
            $nginxContent .= "    return 403;\n";
            $nginxContent .= "}\n";

            file_put_contents($nginxFile, $nginxContent);
        }
    }
}
