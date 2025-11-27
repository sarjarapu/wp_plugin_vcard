<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Logging;

use Minisite\Infrastructure\Logging\LoggingTestController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestLogger;

#[CoversClass(LoggingTestController::class)]
final class LoggingTestControllerTest extends TestCase
{
    private string $logsDir;
    private TestLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logsDir = WP_CONTENT_DIR . '/minisite-logs';
        if (! is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0777, true);
        }
        file_put_contents($this->logsDir . '/minisite.log', '{"message":"test"}');

        $this->logger = new TestLogger();
        $mock = \Mockery::mock('alias:Minisite\Infrastructure\Logging\LoggingServiceProvider');
        $mock->shouldReceive('getFeatureLogger')->andReturn($this->logger);

        unset($GLOBALS['_test_wp_cache']);
        $this->mockWpdb();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        $this->removeLogs();
        unset($GLOBALS['_test_wp_cache'], $GLOBALS['wpdb']);
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

    private function mockWpdb(): void
    {
        $wpdb = new class () {
            public string $prefix = 'wp_';

            public function prepare($query, $value)
            {
                return str_replace(array('%s', '%i'), $value, $query);
            }

            public function get_var($query)
            {
                if (str_starts_with($query, 'SHOW TABLES')) {
                    return 'wp_minisite_logs';
                }

                if (str_starts_with($query, 'SELECT COUNT(*)')) {
                    return 5;
                }

                return null;
            }
        };

        $GLOBALS['wpdb'] = $wpdb;
    }

    public function test_run_test_returns_success_messages(): void
    {
        $controller = new LoggingTestController();
        $results = $controller->runTest();

        $this->assertNotEmpty($results);
        $this->assertContains('✓ Basic logging working', $results);
        $this->assertContains('✓ All log levels working', $results);
        $this->assertTrue($this->logger->hasRecord('Logging test completed successfully', 'info'));
    }

    public function test_get_cached_value_caches_results(): void
    {
        $controller = new LoggingTestController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getCachedValue');
        $method->setAccessible(true);

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return 'computed';
        };

        $first = $method->invoke($controller, 'key', $compute, 60);
        $second = $method->invoke($controller, 'key', $compute, 60);

        $this->assertSame('computed', $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls, 'Computation should be cached');
    }

    public function test_add_admin_menu_registers_menu(): void
    {
        \Brain\Monkey\setUp();
        \Brain\Monkey\Functions\expect('add_submenu_page')
            ->once()
            ->with(
                'minisite-manager',
                'Logging Test',
                'Logging Test',
                'manage_options',
                'minisite-logging-test',
                array(LoggingTestController::class, 'renderTestPage')
            );

        LoggingTestController::addAdminMenu();
        \Brain\Monkey\tearDown();
    }

    public function test_render_test_page_outputs_results(): void
    {
        ob_start();
        LoggingTestController::renderTestPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('Minisite Manager - Logging System Test', $output);
        $this->assertStringContainsString('<ul>', $output);
        $this->assertStringContainsString('Log Files Location', $output);
    }
}
