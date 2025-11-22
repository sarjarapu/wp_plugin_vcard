<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Logging;

use Minisite\Infrastructure\Logging\LoggingServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggingServiceProvider::class)]
final class LoggingServiceProviderTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logsDir = WP_CONTENT_DIR . '/minisite-logs';
        $this->removeLogs();
    }

    protected function tearDown(): void
    {
        $this->removeLogs();
        \Mockery::close();
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

    public function test_register_creates_logs_directory(): void
    {
        LoggingServiceProvider::register();

        $this->assertDirectoryExists($this->logsDir);
    }

    public function test_register_creates_htaccess_file(): void
    {
        LoggingServiceProvider::register();

        $htaccess = $this->logsDir . '/.htaccess';
        $this->assertFileExists($htaccess);
        $this->assertStringContainsString('Deny from all', file_get_contents($htaccess));
    }

    public function test_register_creates_index_php(): void
    {
        LoggingServiceProvider::register();

        $index = $this->logsDir . '/index.php';
        $this->assertFileExists($index);
        $this->assertStringContainsString('Silence is golden', file_get_contents($index));
    }

    public function test_register_creates_nginx_conf(): void
    {
        LoggingServiceProvider::register();

        $nginx = $this->logsDir . '/nginx.conf';
        $this->assertFileExists($nginx);
        $this->assertStringContainsString('deny all', file_get_contents($nginx));
    }

    public function test_get_logger_returns_logger(): void
    {
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Logging\LoggerFactory');
        $mock->shouldReceive('getLogger')->once()->andReturn('logger');

        $this->assertSame('logger', LoggingServiceProvider::getLogger());
    }

    public function test_get_feature_logger_returns_logger(): void
    {
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Logging\LoggerFactory');
        $mock->shouldReceive('createFeatureLogger')->once()->with('feature')->andReturn('feature-logger');

        $this->assertSame('feature-logger', LoggingServiceProvider::getFeatureLogger('feature'));
    }
}
