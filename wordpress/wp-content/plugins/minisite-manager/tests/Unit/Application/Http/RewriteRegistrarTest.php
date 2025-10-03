<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http;

use Minisite\Application\Http\RewriteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(RewriteRegistrar::class)]
final class RewriteRegistrarTest extends TestCase
{
    private RewriteRegistrar $registrar;
    private array $originalGlobals;
    private array $registeredRewriteTags;
    private array $registeredRewriteRules;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original globals
        $this->originalGlobals = [
            'wp_rewrite' => $GLOBALS['wp_rewrite'] ?? null,
        ];

        // Initialize arrays to track registered items
        $this->registeredRewriteTags = [];
        $this->registeredRewriteRules = [];

        $this->registrar = new RewriteRegistrar();

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void
    {
        // Clear our tracking arrays
        $this->registeredRewriteTags = [];
        $this->registeredRewriteRules = [];
        
        // Clear global tracking arrays
        unset($GLOBALS['registeredRewriteTags']);
        unset($GLOBALS['registeredRewriteRules']);

        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        parent::tearDown();
    }

    private function mockWordPressFunctions(): void
    {
        // Mock add_rewrite_tag function
        if (!function_exists('add_rewrite_tag')) {
            eval('
                function add_rewrite_tag($tag, $regex) {
                    global $registeredRewriteTags;
                    if (!isset($registeredRewriteTags)) {
                        $registeredRewriteTags = [];
                    }
                    $registeredRewriteTags[$tag] = $regex;
                }
            ');
        }

        // Mock add_rewrite_rule function
        if (!function_exists('add_rewrite_rule')) {
            eval('
                function add_rewrite_rule($regex, $redirect, $after = "bottom") {
                    global $registeredRewriteRules;
                    if (!isset($registeredRewriteRules)) {
                        $registeredRewriteRules = [];
                    }
                    $registeredRewriteRules[] = [
                        "regex" => $regex,
                        "redirect" => $redirect,
                        "after" => $after
                    ];
                }
            ');
        }

        // Set up global arrays for tracking
        $GLOBALS['registeredRewriteTags'] = &$this->registeredRewriteTags;
        $GLOBALS['registeredRewriteRules'] = &$this->registeredRewriteRules;
    }

    public function testRegisterRegistersAllRewriteTags(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check that all expected rewrite tags are registered
        $expectedTags = [
            '%minisite%' => '([0-1])',
            '%minisite_biz%' => '([^&]+)',
            '%minisite_loc%' => '([^&]+)',
            '%minisite_account%' => '([0-1])',
            '%minisite_account_action%' => '([^&]+)',
            '%minisite_site_id%' => '([a-f0-9]{24,32})',
            '%minisite_version_id%' => '([0-9]+|current|latest)',
        ];

        foreach ($expectedTags as $tag => $regex) {
            $this->assertArrayHasKey($tag, $this->registeredRewriteTags, "Rewrite tag {$tag} should be registered");
            $this->assertEquals($regex, $this->registeredRewriteTags[$tag], "Rewrite tag {$tag} should have correct regex");
        }
    }

    public function testRegisterRegistersMinisiteProfileRoutes(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check minisite profile route
        $minisiteRoute = $this->findRewriteRule('^b/([^/]+)/([^/]+)/?$');
        $this->assertNotNull($minisiteRoute, 'Minisite profile route should be registered');
        $this->assertEquals(
            'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]',
            $minisiteRoute['redirect']
        );
        $this->assertEquals('top', $minisiteRoute['after']);
    }

    public function testRegisterRegistersAccountRoutes(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account routes
        $accountRoutes = $this->findRewriteRule('^account/(login|register|dashboard|logout|forgot|sites)/?$');
        $this->assertNotNull($accountRoutes, 'Account routes should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=$matches[1]',
            $accountRoutes['redirect']
        );
        $this->assertEquals('top', $accountRoutes['after']);
    }

    public function testRegisterRegistersAccountSitesNewRoute(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites new route
        $newSiteRoute = $this->findRewriteRule('^account/sites/new/?$');
        $this->assertNotNull($newSiteRoute, 'Account sites new route should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=new',
            $newSiteRoute['redirect']
        );
        $this->assertEquals('top', $newSiteRoute['after']);
    }

    public function testRegisterRegistersAccountSitesPublishRoute(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites publish route
        $publishRoute = $this->findRewriteRule('^account/sites/publish/?$');
        $this->assertNotNull($publishRoute, 'Account sites publish route should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=publish',
            $publishRoute['redirect']
        );
        $this->assertEquals('top', $publishRoute['after']);
    }

    public function testRegisterRegistersAccountSitesEditRoutes(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites edit routes
        $editWithVersionRoute = $this->findRewriteRule('^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$');
        $this->assertNotNull($editWithVersionRoute, 'Account sites edit with version route should be registered');
        $this->assertStringContainsString(
            'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]&minisite_version_id=$matches[2]',
            $editWithVersionRoute['redirect']
        );

        $editRoute = $this->findRewriteRule('^account/sites/([a-f0-9]{24,32})/edit/?$');
        $this->assertNotNull($editRoute, 'Account sites edit route should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]',
            $editRoute['redirect']
        );
    }

    public function testRegisterRegistersAccountSitesPreviewRoute(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites preview route
        $previewRoute = $this->findRewriteRule('^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$');
        $this->assertNotNull($previewRoute, 'Account sites preview route should be registered');
        $this->assertStringContainsString(
            'index.php?minisite_account=1&minisite_account_action=preview&minisite_site_id=$matches[1]&minisite_version_id=$matches[2]',
            $previewRoute['redirect']
        );
    }

    public function testRegisterRegistersAccountSitesVersionsRoute(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites versions route
        $versionsRoute = $this->findRewriteRule('^account/sites/([a-f0-9]{24,32})/versions/?$');
        $this->assertNotNull($versionsRoute, 'Account sites versions route should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=versions&minisite_site_id=$matches[1]',
            $versionsRoute['redirect']
        );
    }

    public function testRegisterRegistersAccountSitesDefaultRoute(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Check account sites default route (fallback to edit)
        $defaultRoute = $this->findRewriteRule('^account/sites/([a-f0-9]{24,32})/?$');
        $this->assertNotNull($defaultRoute, 'Account sites default route should be registered');
        $this->assertEquals(
            'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]',
            $defaultRoute['redirect']
        );
    }

    public function testRegisterRegistersAllRoutesWithTopPriority(): void
    {
        // Act
        $this->registrar->register();

        // Assert - All routes should have 'top' priority
        foreach ($this->registeredRewriteRules as $rule) {
            $this->assertEquals('top', $rule['after'], 'All routes should have top priority');
        }
    }

    public function testRegisterRegistersCorrectNumberOfRules(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Should register exactly 9 rewrite rules
        $expectedRuleCount = 9;
        $this->assertCount($expectedRuleCount, $this->registeredRewriteRules, 'Should register exactly 9 rewrite rules');
    }

    public function testRegisterRegistersCorrectNumberOfTags(): void
    {
        // Act
        $this->registrar->register();

        // Assert - Should register exactly 7 rewrite tags
        $expectedTagCount = 7;
        $this->assertCount($expectedTagCount, $this->registeredRewriteTags, 'Should register exactly 7 rewrite tags');
    }

    /**
     * Helper method to find a rewrite rule by regex pattern
     */
    private function findRewriteRule(string $regex): ?array
    {
        foreach ($this->registeredRewriteRules as $rule) {
            if ($rule['regex'] === $regex) {
                return $rule;
            }
        }
        return null;
    }
}
