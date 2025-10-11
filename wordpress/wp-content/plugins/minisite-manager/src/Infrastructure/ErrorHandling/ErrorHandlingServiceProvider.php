<?php

namespace Minisite\Infrastructure\ErrorHandling;

/**
 * Service provider for error handling
 */
class ErrorHandlingServiceProvider
{
    private static ?ErrorHandler $errorHandler = null;

    /**
     * Register error handling
     */
    public static function register(): void
    {
        if (self::$errorHandler === null) {
            self::$errorHandler = new ErrorHandler();
            self::$errorHandler->register();
        }
    }

    /**
     * Unregister error handling
     */
    public static function unregister(): void
    {
        if (self::$errorHandler !== null) {
            self::$errorHandler->unregister();
            self::$errorHandler = null;
        }
    }

    /**
     * Get the error handler instance
     */
    public static function getErrorHandler(): ?ErrorHandler
    {
        return self::$errorHandler;
    }
}
