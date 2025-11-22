<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Minisite\Core\PluginBootstrap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CoreTestCase;

#[Group('integration')]
#[CoversClass(PluginBootstrap::class)]
final class PluginBootstrapIntegrationTest extends CoreTestCase
{
    public function testInitializeRegistersAllLifecycleHooks(): void
    {
        $registered = array();

        \Brain\Monkey\Functions\when('register_activation_hook')->alias(function ($file, $callback) use (&$registered): void {
            $registered['activation'] = array($file, $callback);
        });
        \Brain\Monkey\Functions\when('register_deactivation_hook')->alias(function ($file, $callback) use (&$registered): void {
            $registered['deactivation'] = array($file, $callback);
        });

        PluginBootstrap::initialize();

        $this->assertArrayHasKey('activation', $registered);
        $this->assertArrayHasKey('deactivation', $registered);
    }
}
