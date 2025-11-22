<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\ErrorHandling;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Minisite\Infrastructure\ErrorHandling\ErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestLogger;

#[CoversClass(ErrorHandler::class)]
final class ErrorHandlerTest extends TestCase
{
    private TestLogger $logger;
    private ErrorHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->logger = new TestLogger();
        $this->handler = new ErrorHandler($this->logger);
        error_reporting(E_ALL);
    }

    protected function tearDown(): void
    {
        $this->handler->unregister();
        unset($GLOBALS['_minisite_error_handler_debug']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_registers_all_handlers(): void
    {
        $callbacks = array();
        Functions\when('register_shutdown_function')->alias(function ($callback) use (&$callbacks): void {
            $callbacks[] = $callback;
        });

        $this->handler->register();

        $currentErrorHandler = set_error_handler(static function (): void {
        });
        restore_error_handler();

        $currentExceptionHandler = set_exception_handler(static function (): void {
        });
        restore_exception_handler();

        $this->assertSame(array($this->handler, 'handleError'), $currentErrorHandler);
        $this->assertSame(array($this->handler, 'handleException'), $currentExceptionHandler);
        $this->assertCount(1, $callbacks);
        $this->assertEquals(array($this->handler, 'handleShutdown'), $callbacks[0]);
    }

    public function test_register_prevents_duplicate_registration(): void
    {
        Functions\expect('register_shutdown_function')
            ->once()
            ->with(array($this->handler, 'handleShutdown'));

        $this->handler->register();
        $this->handler->register();
    }

    public function test_handle_error_logs_error(): void
    {
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->handler->handleError(E_WARNING, 'Something went wrong', 'file.php', 42);

        $this->assertTrue($result);
        $record = $this->logger->findRecord(
            fn ($record) => $record['message'] === 'PHP Error caught'
        );
        $this->assertNotNull($record);
        $this->assertSame('E_WARNING', $record['context']['severity']);
        $this->assertSame(42, $record['context']['line']);
        $this->assertSame('GET', $record['context']['context']['request_method']);
    }

    public function test_handle_error_ignores_suppressed_errors(): void
    {
        $previousLevel = error_reporting(E_ERROR);
        $result = $this->handler->handleError(E_NOTICE, 'Notice', 'file.php', 10);
        error_reporting($previousLevel);

        $this->assertFalse($result);
        $this->assertSame(array(), $this->logger->getRecords());
    }

    public function test_handle_error_converts_fatal_to_exception(): void
    {
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('fatal');

        $this->handler->handleError(E_ERROR, 'fatal', 'fatal.php', 12);
    }

    public function test_handle_exception_logs_and_displays_user_friendly_message(): void
    {
        $GLOBALS['_minisite_error_handler_debug'] = false;
        Functions\expect('headers_sent')->andReturn(false);
        Functions\expect('http_response_code')->once()->with(500);
        Functions\expect('header')->once()->with('Content-Type: text/html; charset=utf-8');

        ob_start();
        $this->handler->handleException(new \RuntimeException('boom'));
        $output = ob_get_clean();

        $this->assertStringContainsString('Something went wrong', $output);
        $this->assertTrue($this->logger->hasRecord('Uncaught Exception', 'error'));
    }

    public function test_handle_exception_shows_debug_page_in_development(): void
    {
        $GLOBALS['_minisite_error_handler_debug'] = true;
        Functions\expect('headers_sent')->andReturn(false);
        Functions\expect('http_response_code')->once()->with(500);
        Functions\expect('header')->once()->with('Content-Type: text/html; charset=utf-8');

        ob_start();
        $this->handler->handleException(new \RuntimeException('debug mode'));
        $output = ob_get_clean();

        $this->assertStringContainsString('Minisite Manager Error', $output);
        $this->assertStringContainsString('debug mode', $output);
    }

    public function test_handle_shutdown_detects_fatal_errors(): void
    {
        Functions\expect('error_get_last')->andReturn(array(
            'type' => E_ERROR,
            'message' => 'fatal',
            'file' => 'fatal.php',
            'line' => 5,
        ));

        $this->handler->handleShutdown();

        $this->assertTrue($this->logger->hasRecord('Fatal Error detected on shutdown', 'error'));
    }

    public function test_handle_shutdown_ignores_non_fatal_shutdowns(): void
    {
        Functions\expect('error_get_last')->andReturn(array(
            'type' => E_NOTICE,
            'message' => 'notice',
            'file' => 'file.php',
            'line' => 10,
        ));

        $this->handler->handleShutdown();

        $this->assertSame(array(), $this->logger->getRecords());
    }

    public function test_unregister_restores_handlers(): void
    {
        $this->handler->register();
        $this->handler->unregister();

        $previous = set_error_handler(static function (): void {
        });
        restore_error_handler();

        $this->assertNotSame(array($this->handler, 'handleError'), $previous);
    }

    public function test_get_severity_name_returns_correct_names(): void
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('getSeverityName');
        $method->setAccessible(true);

        $this->assertSame('E_WARNING', $method->invoke($this->handler, E_WARNING));
        $this->assertSame('UNKNOWN', $method->invoke($this->handler, 999999));
    }
}
