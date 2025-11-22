<?php

namespace Minisite\Infrastructure\ErrorHandling;

use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Comprehensive error handling for the Minisite Manager plugin
 */
class ErrorHandler
{
    private LoggerInterface $logger;
    private bool $isRegistered = false;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? LoggingServiceProvider::getFeatureLogger('error-handler');
    }

    /**
     * Register all error handlers
     */
    public function register(): void
    {
        if ($this->isRegistered) {
            return;
        }

        // Set error handler for PHP errors (E_ERROR, E_WARNING, etc.)
        set_error_handler(array($this, 'handleError'));

        // Set exception handler for uncaught exceptions
        set_exception_handler(array($this, 'handleException'));

        // Set shutdown function for fatal errors
        register_shutdown_function(array($this, 'handleShutdown'));

        $this->isRegistered = true;
        $this->logger->info('Error handlers registered successfully');
    }

    /**
     * Handle PHP errors (warnings, notices, etc.)
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Don't handle errors that are suppressed with @
        if (! (error_reporting() & $severity)) {
            return false;
        }

        $this->logger->error('PHP Error caught', array(
            'severity' => $this->getSeverityName($severity),
            'message' => sanitize_text_field($message),
            'file' => sanitize_text_field($file),
            'line' => $line,
            'context' => array(
                'request_uri' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown')),
                'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
                'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
            ),
        ));

        // For fatal errors, convert to exception so it can be caught
        if (in_array($severity, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
            throw new \ErrorException(
                esc_html($message),
                0,
                (int) $severity,
                esc_html($file),
                (int) $line
            );
        }

        return true; // Don't execute PHP's internal error handler
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $this->logger->error('Uncaught Exception', array(
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => array(
                'request_uri' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown')),
                'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
                'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
            ),
        ));

        // Show user-friendly error page
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        // In development, show detailed error
        if ($this->isDebugMode()) {
            echo "<h1>Minisite Manager Error</h1>";
            echo "<p><strong>Error:</strong> " . esc_html($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . esc_html($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . esc_html($exception->getLine()) . "</p>";
            echo "<pre>" . esc_html($exception->getTraceAsString()) . "</pre>";
        } else {
            echo "<h1>Something went wrong</h1>";
            echo "<p>We're sorry, but something went wrong. Please try again later.</p>";
        }
    }

    /**
     * Handle fatal errors and other shutdown events
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->logger->error('Fatal Error detected on shutdown', array(
                'type' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'context' => array(
                    'request_uri' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? 'unknown')),
                    'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')),
                    'request_method' => sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'unknown')),
                ),
            ));
        }
    }

    /**
     * Restore default error handlers
     */
    public function unregister(): void
    {
        if ($this->isRegistered) {
            restore_error_handler();
            restore_exception_handler();
            $this->isRegistered = false;
            $this->logger->info('Error handlers unregistered');
        }
    }

    /**
     * Get human-readable severity name
     */
    private function getSeverityName(int $severity): string
    {
        $severities = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );

        return $severities[$severity] ?? 'UNKNOWN';
    }

    private function isDebugMode(): bool
    {
        if (array_key_exists('_minisite_error_handler_debug', $GLOBALS)) {
            return (bool) $GLOBALS['_minisite_error_handler_debug'];
        }

        if (defined('MINISITE_ERROR_HANDLER_DEBUG')) {
            return (bool) constant('MINISITE_ERROR_HANDLER_DEBUG');
        }

        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
