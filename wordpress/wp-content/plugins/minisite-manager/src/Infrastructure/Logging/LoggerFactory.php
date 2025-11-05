<?php

namespace Minisite\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\DatabaseHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

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

        // Add processors that automatically add metadata (class, method, file, line, etc.)
        // These processors run BEFORE handlers, so all log entries get this info automatically

        // Adds class, function (method), file, line automatically
        // Skip frames: LoggerFactory itself, and any intermediate logging wrappers
        $logger->pushProcessor(
            new IntrospectionProcessor(Logger::DEBUG, array('Monolog\\', 'Minisite\\Infrastructure\\Logging\\'))
        );

        // Adds memory_usage to context
        $logger->pushProcessor(new MemoryUsageProcessor());

        // Processes PSR-3 style placeholders in messages (e.g., {user_id})
        $logger->pushProcessor(new PsrLogMessageProcessor());

        // Add file handler with rotation (daily)
        $fileHandler = new RotatingFileHandler(
            WP_CONTENT_DIR . '/minisite-logs/minisite.log',
            30, // Keep 30 days of logs
            Logger::DEBUG
        );

        // Use JSON formatter for structured logging
        $jsonFormatter = new JsonFormatter();
        $fileHandler->setFormatter($jsonFormatter);
        $logger->pushHandler($fileHandler);

        // Add error log handler for critical issues (skip during tests)
        if (! self::isRunningInTests()) {
            $errorHandler = new StreamHandler('php://stderr', Logger::ERROR);
            $lineFormatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            );
            $errorHandler->setFormatter($lineFormatter);
            $logger->pushHandler($errorHandler);
        }

        // Database logging is disabled for now - DatabaseHandler doesn't exist in Monolog
        // TODO: Implement custom database handler if needed
        // if (self::shouldUseDatabaseLogging()) {
        //     $dbHandler = self::createDatabaseHandler();
        //     if ($dbHandler) {
        //         $logger->pushHandler($dbHandler);
        //     }
        // }

        return $logger;
    }

    /**
     * Create a database handler for WordPress integration
     */
    // private static function createDatabaseHandler(): ?DatabaseHandler
    // {
    //     global $wpdb;
    //
    //     if (!$wpdb) {
    //         return null;
    //     }
    //
    //     try {
    //         // Create logs table if it doesn't exist
    //         self::ensureLogsTableExists();
    //
    //         $dbHandler = new DatabaseHandler(
    //             $wpdb->dbh,
    //             $wpdb->prefix . 'minisite_logs',
    //             ['level', 'message', 'context', 'extra', 'created_at'],
    //             Logger::WARNING // Only log warnings and above to database
    //         );
    //
    //         $dbHandler->setFormatter(new JsonFormatter());
    //         return $dbHandler;
    //     } catch (\Exception $e) {
    //         // Fallback to file logging if database fails
    //         error_log('Failed to create database logger: ' . $e->getMessage());
    //         return null;
    //     }
    // }

    /**
     * Check if database logging should be enabled
     * @phpstan-ignore-next-line
     */
    private static function shouldUseDatabaseLogging(): bool
    {
        // Database logging is disabled for now - DatabaseHandler doesn't exist in Monolog
        return false;
    }

    /**
     * Ensure the logs table exists
     * Note: Currently unused since database logging is disabled
     * @phpstan-ignore-next-line
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
        if ($logger instanceof \Monolog\Logger) {
            $logger->pushProcessor(function ($record) use ($feature) {
                $record['extra']['feature'] = $feature;
                $record['extra']['plugin_version'] = defined('MINISITE_MANAGER_VERSION')
                    ? MINISITE_MANAGER_VERSION : 'unknown';

                return $record;
            });
        }

        return $logger;
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Check if we're running in a test environment
     */
    private static function isRunningInTests(): bool
    {
        // Check for PHPUnit environment
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING) {
            return true;
        }

        // Check for PHPUnit in the call stack
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            if (isset($frame['class']) && strpos($frame['class'], 'PHPUnit') === 0) {
                return true;
            }
        }

        // Check for test-related constants or environment variables
        if (defined('WP_TESTS_DOMAIN') || getenv('WP_TESTS_DOMAIN')) {
            return true;
        }

        return false;
    }
}
