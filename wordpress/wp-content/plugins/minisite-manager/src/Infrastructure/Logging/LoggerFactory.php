<?php

namespace Minisite\Infrastructure\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\DatabaseHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;
use wpdb;

/**
 * Factory for creating PSR-3 compatible loggers using Monolog
 */
class LoggerFactory
{
    private static ?LoggerInterface $instance = null;
    
    /**
     * Get the main application logger instance (singleton)
     */
    public static function getLogger(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }
        
        return self::$instance;
    }
    
    /**
     * Create a new logger instance with configured handlers
     */
    public static function createLogger(string $name = 'minisite-manager'): LoggerInterface
    {
        $logger = new Logger($name);
        
        // Add file handler with rotation (daily)
        $fileHandler = new RotatingFileHandler(
            WP_CONTENT_DIR . '/uploads/minisite-logs/minisite.log',
            30, // Keep 30 days of logs
            Logger::DEBUG
        );
        
        // Use JSON formatter for structured logging
        $jsonFormatter = new JsonFormatter();
        $fileHandler->setFormatter($jsonFormatter);
        $logger->pushHandler($fileHandler);
        
        // Add error log handler for critical issues
        $errorHandler = new StreamHandler('php://stderr', Logger::ERROR);
        $lineFormatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        );
        $errorHandler->setFormatter($lineFormatter);
        $logger->pushHandler($errorHandler);
        
        // Add database handler for WordPress integration (optional)
        if (self::shouldUseDatabaseLogging()) {
            $dbHandler = self::createDatabaseHandler();
            if ($dbHandler) {
                $logger->pushHandler($dbHandler);
            }
        }
        
        return $logger;
    }
    
    /**
     * Create a database handler for WordPress integration
     */
    private static function createDatabaseHandler(): ?DatabaseHandler
    {
        global $wpdb;
        
        if (!$wpdb) {
            return null;
        }
        
        try {
            // Create logs table if it doesn't exist
            self::ensureLogsTableExists();
            
            $dbHandler = new DatabaseHandler(
                $wpdb->dbh,
                $wpdb->prefix . 'minisite_logs',
                ['level', 'message', 'context', 'extra', 'created_at'],
                Logger::WARNING // Only log warnings and above to database
            );
            
            $dbHandler->setFormatter(new JsonFormatter());
            return $dbHandler;
            
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            error_log('Failed to create database logger: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if database logging should be enabled
     */
    private static function shouldUseDatabaseLogging(): bool
    {
        // Enable database logging in development or when explicitly configured
        return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
    
    /**
     * Ensure the logs table exists
     */
    private static function ensureLogsTableExists(): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'minisite_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message longtext NOT NULL,
            context longtext,
            extra longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create a logger for a specific feature/component
     */
    public static function createFeatureLogger(string $feature): LoggerInterface
    {
        $logger = self::createLogger("minisite-manager.{$feature}");
        
        // Add feature-specific context
        $logger->pushProcessor(function ($record) use ($feature) {
            $record['extra']['feature'] = $feature;
            $record['extra']['plugin_version'] = defined('MINISITE_MANAGER_VERSION') ? MINISITE_MANAGER_VERSION : 'unknown';
            return $record;
        });
        
        return $logger;
    }
    
    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
