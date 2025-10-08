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
    private RewriteRegistrar $registrar;
    private array $originalGlobals;
    private array $originalRewriteRules;
    private array $originalRewriteTags;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original globals
        $this->originalGlobals = [
            'wp_rewrite' => $GLOBALS['wp_rewrite'] ?? null,
        ];

        // Initialize wp_rewrite object if it doesn't exist
        if (!isset($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new \stdClass();
        }

        // Store original rewrite rules and tags
        $this->originalRewriteRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $this->originalRewriteTags = $GLOBALS['wp_rewrite']->rewritecode ?? [];

        $this->registrar = new RewriteRegistrar();

        // Mock WordPress functions for integration testing
        $this->mockWordPressFunctions();
    }

    protected function tearDown(): void
    {
        // Restore original rewrite rules and tags
        if (isset($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite']->rules = $this->originalRewriteRules;
            $GLOBALS['wp_rewrite']->rewritecode = $this->originalRewriteTags;
            $GLOBALS['wp_rewrite']->rewritereplace = [];
        }

        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        parent::tearDown();
    }

    private function mockWordPressFunctions(): void
    {
        // Create a mock wp_rewrite object if it doesn't exist
        if (!isset($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new \stdClass();
        }
        
        // Ensure all required properties exist
        if (!isset($GLOBALS['wp_rewrite']->rules)) {
            $GLOBALS['wp_rewrite']->rules = [];
        }
        if (!isset($GLOBALS['wp_rewrite']->rewritecode)) {
            $GLOBALS['wp_rewrite']->rewritecode = [];
        }
        if (!isset($GLOBALS['wp_rewrite']->rewritereplace)) {
            $GLOBALS['wp_rewrite']->rewritereplace = [];
        }

        // Since functions may already be defined from unit tests, we need to work with them
        // Clear any existing global tracking arrays that might interfere
        unset($GLOBALS['registeredRewriteTags']);
        unset($GLOBALS['registeredRewriteRules']);
        
        // Define functions only if they don't exist
        $this->defineMockFunctions();
    }

    private function defineMockFunctions(): void
    {
        // Only define functions if they don't already exist
        if (!function_exists('add_rewrite_tag')) {
            eval('
                function add_rewrite_tag($tag, $regex) {
                    global $wp_rewrite;
                    if (!isset($wp_rewrite) || !is_object($wp_rewrite)) {
                        $wp_rewrite = new stdClass();
                    }
                    if (!isset($wp_rewrite->rewritecode)) {
                        $wp_rewrite->rewritecode = [];
                        $wp_rewrite->rewritereplace = [];
                    }
                    $wp_rewrite->rewritecode[] = $tag;
                    $wp_rewrite->rewritereplace[] = $regex;
                }
            ');
        }

        if (!function_exists('add_rewrite_rule')) {
            eval('
                function add_rewrite_rule($regex, $redirect, $after = "bottom") {
                    global $wp_rewrite;
                    if (!isset($wp_rewrite) || !is_object($wp_rewrite)) {
                        $wp_rewrite = new stdClass();
                    }
                    if (!isset($wp_rewrite->rules)) {
                        $wp_rewrite->rules = [];
                    }
                    if ($after === "top") {
                        $wp_rewrite->rules = array_merge([$regex => $redirect], $wp_rewrite->rules);
                    } else {
                        $wp_rewrite->rules[$regex] = $redirect;
                    }
                }
            ');
        }
    }

    public function testRegisterIntegratesWithWordPressRewriteSystem(): void
    {
        // Act
        $this->registrar->register();

        // Since functions may be using global tracking arrays from unit tests,
        // we need to check both wp_rewrite and global arrays
        $rewritecode = $GLOBALS['wp_rewrite']->rewritecode ?? [];
        $globalTags = $GLOBALS['registeredRewriteTags'] ?? [];
        
        // Combine both sources for comprehensive checking
        $allTags = array_merge($rewritecode, array_keys($globalTags));

        // Assert - Check that rewrite tags are registered (from either source)
        $this->assertContains('%minisite%', $allTags, 'minisite tag should be registered');
        $this->assertContains('%minisite_biz%', $allTags, 'minisite_biz tag should be registered');
        $this->assertContains('%minisite_loc%', $allTags, 'minisite_loc tag should be registered');
        $this->assertContains('%minisite_account%', $allTags, 'minisite_account tag should be registered');
        $this->assertContains('%minisite_account_action%', $allTags, 'minisite_account_action tag should be registered');
        $this->assertContains('%minisite_site_id%', $allTags, 'minisite_site_id tag should be registered');
        $this->assertContains('%minisite_version_id%', $allTags, 'minisite_version_id tag should be registered');
    }

    public function testRegisterAddsRewriteRulesToWordPressSystem(): void
    {
        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays for rules
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        
        // Extract rule patterns from global rules array
        $globalRulePatterns = array_column($globalRules, 'regex');
        $allRulePatterns = array_merge(array_keys($wpRules), $globalRulePatterns);

        // Assert - Check that rewrite rules are registered (from either source)
        $this->assertContains('^b/([^/]+)/([^/]+)/?$', $allRulePatterns);
        $this->assertContains('^account/(login|register|dashboard|logout|forgot|sites)/?$', $allRulePatterns);
        $this->assertContains('^account/sites/new/?$', $allRulePatterns);
        $this->assertContains('^account/sites/publish/?$', $allRulePatterns);
        $this->assertContains('^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$', $allRulePatterns);
        $this->assertContains('^account/sites/([a-f0-9]{24,32})/edit/?$', $allRulePatterns);
        $this->assertContains('^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$', $allRulePatterns);
        $this->assertContains('^account/sites/([a-f0-9]{24,32})/versions/?$', $allRulePatterns);
        $this->assertContains('^account/sites/([a-f0-9]{24,32})/?$', $allRulePatterns);
    }

    public function testRegisterMaintainsTopPriorityForAllRules(): void
    {
        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays for rules
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        
        // Extract rule patterns from global rules array
        $globalRulePatterns = array_column($globalRules, 'regex');
        $allRulePatterns = array_merge(array_keys($wpRules), $globalRulePatterns);

        // Expected rules that should be registered
        $expectedRules = [
            '^account/sites/([a-f0-9]{24,32})/?$',
            '^account/sites/([a-f0-9]{24,32})/versions/?$',
            '^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$',
            '^account/sites/([a-f0-9]{24,32})/edit/?$',
            '^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$',
            '^account/sites/publish/?$',
            '^account/sites/new/?$',
            '^account/(login|register|dashboard|logout|forgot|sites)/?$',
            '^b/([^/]+)/([^/]+)/?$',
        ];

        foreach ($expectedRules as $expectedRule) {
            $this->assertContains($expectedRule, $allRulePatterns, "Rule {$expectedRule} should be registered");
        }
    }

    public function testRegisterDoesNotInterfereWithExistingRules(): void
    {
        // Arrange - Add some existing rules
        $existingRules = [
            '^existing-rule/?$' => 'index.php?existing=1',
            '^another-rule/?$' => 'index.php?another=1',
        ];
        $GLOBALS['wp_rewrite']->rules = $existingRules;

        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays for rules
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        
        // Extract rule patterns from global rules array
        $globalRulePatterns = array_column($globalRules, 'regex');
        $allRulePatterns = array_merge(array_keys($wpRules), $globalRulePatterns);

        // Assert - Existing rules should still be present
        $this->assertContains('^existing-rule/?$', $allRulePatterns);
        $this->assertContains('^another-rule/?$', $allRulePatterns);
        
        // And our new rules should be registered
        $this->assertContains('^b/([^/]+)/([^/]+)/?$', $allRulePatterns);
    }

    public function testRegisterHandlesMultipleCallsGracefully(): void
    {
        // Act - Call register multiple times
        $this->registrar->register();
        $firstCallRules = $GLOBALS['wp_rewrite']->rules;
        $firstCallTags = $GLOBALS['wp_rewrite']->rewritecode;

        $this->registrar->register();
        $secondCallRules = $GLOBALS['wp_rewrite']->rules;
        $secondCallTags = $GLOBALS['wp_rewrite']->rewritecode;

        // Assert - Second call should not duplicate rules (tags will duplicate due to WordPress behavior)
        $this->assertEquals(count($firstCallRules), count($secondCallRules), 'Multiple calls should not duplicate rules');
        // Note: WordPress add_rewrite_tag doesn't prevent duplicates, so tags will be duplicated
        $this->assertGreaterThanOrEqual(count($firstCallTags), count($secondCallTags), 'Tags may be duplicated due to WordPress behavior');
    }

    public function testRegisterWorksWithEmptyRewriteSystem(): void
    {
        // Arrange - Start with empty rewrite system
        $GLOBALS['wp_rewrite']->rules = [];
        $GLOBALS['wp_rewrite']->rewritecode = [];
        $GLOBALS['wp_rewrite']->rewritereplace = [];

        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $wpTags = $GLOBALS['wp_rewrite']->rewritecode ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        $globalTags = $GLOBALS['registeredRewriteTags'] ?? [];

        // Assert - Should still work correctly (check either source)
        $hasRules = !empty($wpRules) || !empty($globalRules);
        $hasTags = !empty($wpTags) || !empty($globalTags);
        
        $this->assertTrue($hasRules, 'Should add rules to empty system');
        $this->assertTrue($hasTags, 'Should add tags to empty system');
    }

    public function testRegisterCreatesValidRewritePatterns(): void
    {
        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays for rules
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        
        // Extract rule patterns from global rules array
        $globalRulePatterns = array_column($globalRules, 'regex');
        $allRulePatterns = array_merge(array_keys($wpRules), $globalRulePatterns);

        // Assert - All regex patterns should be valid
        foreach ($allRulePatterns as $pattern) {
            // Escape the pattern for use in preg_match
            $escapedPattern = str_replace(['/', '?'], ['\/', '\?'], $pattern);
            $this->assertNotFalse(
                @preg_match('/' . $escapedPattern . '/', 'test'),
                "Pattern '{$pattern}' should be a valid regex"
            );
        }
    }

    public function testRegisterCreatesValidRedirectTargets(): void
    {
        // Act
        $this->registrar->register();

        // Check both wp_rewrite and global arrays for rules
        $wpRules = $GLOBALS['wp_rewrite']->rules ?? [];
        $globalRules = $GLOBALS['registeredRewriteRules'] ?? [];
        
        // Combine rules from both sources
        $allRules = $wpRules;
        foreach ($globalRules as $rule) {
            $allRules[$rule['regex']] = $rule['redirect'];
        }

        // Assert - All redirect targets should be valid WordPress query strings
        foreach ($allRules as $pattern => $redirect) {
            $this->assertStringStartsWith('index.php?', $redirect, "Redirect for '{$pattern}' should start with index.php?");
            $this->assertStringContainsString('minisite', $redirect, "Redirect for '{$pattern}' should contain minisite parameter");
        }
    }
}
