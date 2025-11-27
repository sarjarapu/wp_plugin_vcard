<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Logging;

use Minisite\Infrastructure\Logging\LoggerFactory;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggerFactory::class)]
final class LoggerFactoryTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logsDir = WP_CONTENT_DIR . '/minisite-logs';
        if (is_dir($this->logsDir)) {
            $this->removeLogs();
        }
        mkdir($this->logsDir, 0777, true);
        LoggerFactory::reset();
    }

    protected function tearDown(): void
    {
        LoggerFactory::reset();
        $this->removeLogs();
        parent::tearDown();
    }

    private function removeLogs(): void
    {
        if (! is_dir($this->logsDir)) {
            return;
        }

        $files = glob($this->logsDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($this->logsDir);
    }

    public function test_get_logger_returns_singleton(): void
    {
        $loggerA = LoggerFactory::getLogger();
        $loggerB = LoggerFactory::getLogger();

        $this->assertSame($loggerA, $loggerB);
    }

    public function test_create_logger_creates_new_logger(): void
    {
        $loggerA = LoggerFactory::createLogger('a');
        $loggerB = LoggerFactory::createLogger('b');

        $this->assertNotSame($loggerA, $loggerB);
    }

    public function test_create_logger_configures_handlers(): void
    {
        $logger = LoggerFactory::createLogger('handler-test');
        $this->assertInstanceOf(Logger::class, $logger);

        $handlers = $logger->getHandlers();
        $this->assertNotEmpty($handlers);
        $this->assertInstanceOf(RotatingFileHandler::class, $handlers[0]);
        $this->assertInstanceOf(
            \Monolog\Formatter\JsonFormatter::class,
            $handlers[0]->getFormatter()
        );
    }

    public function test_create_logger_configures_processors(): void
    {
        $logger = LoggerFactory::createLogger('processor-test');
        $processors = $logger->getProcessors();

        $this->assertGreaterThanOrEqual(3, count($processors));
    }

    public function test_create_feature_logger_adds_feature_context(): void
    {
        $logger = LoggerFactory::createFeatureLogger('feature-x');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $logger->info('testing feature context');

        $records = $testHandler->getRecords();
        $this->assertNotEmpty($records);
        $this->assertSame('feature-x', $records[0]['extra']['feature']);
    }

    public function test_is_running_in_tests_detects_phpunit(): void
    {
        $reflection = new \ReflectionClass(LoggerFactory::class);
        $method = $reflection->getMethod('isRunningInTests');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_reset_clears_singleton(): void
    {
        $loggerA = LoggerFactory::getLogger();
        LoggerFactory::reset();
        $loggerB = LoggerFactory::getLogger();

        $this->assertNotSame($loggerA, $loggerB);
    }

    public function test_create_logger_skips_stderr_in_tests(): void
    {
        $logger = LoggerFactory::createLogger('stderr-test');
        $handlers = $logger->getHandlers();

        foreach ($handlers as $handler) {
            $this->assertFalse(
                $handler instanceof StreamHandler && $handler->getUrl() === 'php://stderr',
                'StreamHandler to stderr should not be registered during tests'
            );
        }
    }
}
