<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Http;

use Minisite\Application\Http\RewriteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(RewriteRegistrar::class)]
#[Group('integration')]
final class RewriteRegistrarIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRewriteGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetRewriteGlobals();
        parent::tearDown();
    }

    public function test_register_adds_rewrite_rules_to_wordpress_system(): void
    {
        (new RewriteRegistrar())->register();

        $patterns = array(
            '^b/([^/]+)/([^/]+)/?$',
            '^account/(login|register|dashboard|logout|forgot|sites)/?$',
            '^account/sites/new/?$',
            '^account/sites/publish/?$',
            '^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$',
            '^account/sites/([a-f0-9]{24,32})/edit/?$',
            '^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$',
            '^account/sites/([a-f0-9]{24,32})/versions/?$',
            '^account/sites/([a-f0-9]{24,32})/publish/?$',
            '^account/sites/([a-f0-9]{24,32})/?$',
        );

        foreach ($patterns as $pattern) {
            $this->assertArrayHasKey(
                $pattern,
                $GLOBALS['wp_rewrite']->rules,
                sprintf('WordPress rewrite rules should contain pattern "%s"', $pattern)
            );
        }
    }

    public function test_register_tracks_top_priority_for_all_rules(): void
    {
        (new RewriteRegistrar())->register();

        foreach ($GLOBALS['_test_wp_rewrite_rules'] as $rule) {
            $this->assertSame('top', $rule['after']);
        }
    }

    public function test_register_creates_valid_redirect_targets(): void
    {
        (new RewriteRegistrar())->register();

        foreach ($GLOBALS['_test_wp_rewrite_rules'] as $rule) {
            $this->assertStringStartsWith('index.php?', $rule['redirect']);
            $this->assertStringContainsString('minisite', $rule['redirect']);
        }
    }

    private function resetRewriteGlobals(): void
    {
        $GLOBALS['_test_wp_rewrite_tags'] = array();
        $GLOBALS['_test_wp_rewrite_rules'] = array();
        $GLOBALS['wp_rewrite'] = new class () {
            public array $rules = array();
        };
    }
}
