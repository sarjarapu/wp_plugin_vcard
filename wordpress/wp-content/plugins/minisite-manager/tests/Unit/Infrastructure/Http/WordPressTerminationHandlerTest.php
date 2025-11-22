<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use Minisite\Infrastructure\Http\TerminationHandlerInterface;
use Minisite\Infrastructure\Http\WordPressTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WordPressTerminationHandler::class)]
final class WordPressTerminationHandlerTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $handler = new WordPressTerminationHandler();

        $this->assertInstanceOf(TerminationHandlerInterface::class, $handler);
    }

    public function test_terminate_calls_exit_in_separate_process(): void
    {
        $pluginRoot = dirname(__DIR__, 4);
        $script = <<<'PHP'
        <?php
        require '%s/vendor/autoload.php';
        require '%s/tests/bootstrap.php';
        $handler = new \Minisite\Infrastructure\Http\WordPressTerminationHandler();
        $handler->terminate();
        PHP;

        $script = sprintf($script, $pluginRoot, $pluginRoot);
        $tmpFile = tempnam(sys_get_temp_dir(), 'wp_term_handler');
        file_put_contents($tmpFile, $script);

        $output = array();
        $exitCode = null;
        exec('php ' . escapeshellarg($tmpFile), $output, $exitCode);
        @unlink($tmpFile);

        $this->assertSame(0, $exitCode);
    }
}
