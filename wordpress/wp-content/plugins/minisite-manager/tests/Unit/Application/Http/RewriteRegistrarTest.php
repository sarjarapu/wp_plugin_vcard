<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Http;

use Brain\Monkey\Functions;
use Minisite\Application\Http\RewriteRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RewriteRegistrar::class)]
final class RewriteRegistrarTest extends TestCase
{
    /**
     * @var array<string, array{regex: string, query: string}>
     */
    private array $registeredTags = array();

    /**
     * @var array<int, array{pattern: string, redirect: string, after: string}>
     */
    private array $registeredRules = array();

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        $this->registeredTags = array();
        $this->registeredRules = array();

        Functions\when('add_rewrite_tag')->alias(function (string $tag, string $regex, string $query = ''): void {
            $this->registeredTags[$tag] = array(
                'regex' => $regex,
                'query' => $query,
            );
        });

        Functions\when('add_rewrite_rule')->alias(function (string $pattern, string $redirect, string $after = 'bottom'): void {
            $this->registeredRules[] = array(
                'pattern' => $pattern,
                'redirect' => $redirect,
                'after' => $after,
            );
        });
    }

    protected function tearDown(): void
    {
        $this->registeredTags = array();
        $this->registeredRules = array();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_registers_expected_rewrite_tags(): void
    {
        (new RewriteRegistrar())->register();

        $expected = array(
            '%minisite%' => '([0-1])',
            '%minisite_biz%' => '([^&]+)',
            '%minisite_loc%' => '([^&]+)',
            '%minisite_account%' => '([0-1])',
            '%minisite_account_action%' => '([^&]+)',
            '%minisite_id%' => '([a-f0-9]{24,32})',
            '%minisite_version_id%' => '([0-9]+|current|latest)',
        );

        $this->assertCount(count($expected), $this->registeredTags);
        foreach ($expected as $tag => $regex) {
            $this->assertArrayHasKey($tag, $this->registeredTags);
            $this->assertSame($regex, $this->registeredTags[$tag]['regex']);
        }
    }

    #[DataProvider('rewriteRuleProvider')]
    public function test_register_registers_expected_rewrite_rule(string $pattern, string $redirect): void
    {
        (new RewriteRegistrar())->register();

        $rule = $this->findRule($pattern);
        $this->assertNotNull($rule, sprintf('Expected rule for pattern "%s" to be registered', $pattern));
        $this->assertSame($redirect, $rule['redirect']);
        $this->assertSame('top', $rule['after']);
    }

    public function test_register_records_expected_number_of_rules(): void
    {
        (new RewriteRegistrar())->register();
        $this->assertCount(10, $this->registeredRules);
    }

    public function test_register_sets_top_priority_for_all_rules(): void
    {
        (new RewriteRegistrar())->register();

        foreach ($this->registeredRules as $rule) {
            $this->assertSame('top', $rule['after'], sprintf('Rule "%s" should have top priority', $rule['pattern']));
        }
    }

    public function test_register_preserves_existing_rules(): void
    {
        $legacyRule = array(
            'pattern' => '^legacy/route/?$',
            'redirect' => 'index.php?legacy=1',
            'after' => 'bottom',
        );
        $this->registeredRules[] = $legacyRule;

        (new RewriteRegistrar())->register();

        $this->assertContains($legacyRule, $this->registeredRules);
        $this->assertCount(11, $this->registeredRules);
    }

    public function test_register_creates_valid_regex_patterns(): void
    {
        (new RewriteRegistrar())->register();

        foreach ($this->registeredRules as $rule) {
            $pattern = $rule['pattern'];
            $result = @preg_match('~' . $pattern . '~', '');
            $this->assertNotFalse($result, sprintf('Pattern "%s" should be a valid regex', $pattern));
        }
    }

    /**
     * @return array<string, array{pattern: string, redirect: string}>
     */
    public static function rewriteRuleProvider(): array
    {
        return array(
            'minisite profile route' => array(
                'pattern' => '^b/([^/]+)/([^/]+)/?$',
                'redirect' => 'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]',
            ),
            'account action routes' => array(
                'pattern' => '^account/(login|register|dashboard|logout|forgot|sites)/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=$matches[1]',
            ),
            'account sites new' => array(
                'pattern' => '^account/sites/new/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=new',
            ),
            'account sites publish' => array(
                'pattern' => '^account/sites/publish/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=publish',
            ),
            'account site edit with version' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]&minisite_version_id=$matches[2]',
            ),
            'account site edit default' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/edit/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]',
            ),
            'account site preview' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=preview&minisite_id=$matches[1]&minisite_version_id=$matches[2]',
            ),
            'account site versions' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/versions/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=versions&minisite_id=$matches[1]',
            ),
            'account site publish with id' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/publish/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=publish&minisite_id=$matches[1]',
            ),
            'account site default edit' => array(
                'pattern' => '^account/sites/([a-f0-9]{24,32})/?$',
                'redirect' => 'index.php?minisite_account=1&minisite_account_action=edit&minisite_id=$matches[1]',
            ),
        );
    }

    /**
     * Find a registered rule by its pattern.
     *
     * @return array{pattern: string, redirect: string, after: string}|null
     */
    private function findRule(string $pattern): ?array
    {
        foreach ($this->registeredRules as $rule) {
            if ($rule['pattern'] === $pattern) {
                return $rule;
            }
        }

        return null;
    }
}
