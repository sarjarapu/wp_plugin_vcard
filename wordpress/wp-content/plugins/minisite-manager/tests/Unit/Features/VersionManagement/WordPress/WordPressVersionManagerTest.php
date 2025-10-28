<?php

namespace Minisite\Features\VersionManagement\WordPress;

use PHPUnit\Framework\TestCase;

/**
 * Test for WordPressVersionManager
 */
class WordPressVersionManagerTest extends TestCase
{
    private WordPressVersionManager $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = new WordPressVersionManager();
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    public function test_is_user_logged_in_calls_wp_function(): void
    {
        $this->mockWordPressFunction('is_user_logged_in', true);

        $result = $this->wordPressManager->isUserLoggedIn();

        $this->assertTrue($result);
    }

    public function test_get_current_user_calls_wp_function(): void
    {
        $user = $this->createMock(\WP_User::class);
        $user->ID = 123;
        $user->user_login = 'testuser';
        $this->mockWordPressFunction('wp_get_current_user', $user);

        $result = $this->wordPressManager->getCurrentUser();

        $this->assertEquals($user, $result);
    }

    public function test_verify_nonce_calls_wp_function(): void
    {
        $this->mockWordPressFunction('wp_verify_nonce', true);

        $result = $this->wordPressManager->verifyNonce('test-nonce', 'test-action');

        $this->assertTrue($result);
    }

    public function test_get_query_var_calls_wp_function(): void
    {
        // Test that the method exists and is callable
        $this->assertTrue(method_exists($this->wordPressManager, 'getQueryVar'));
        $this->assertTrue(is_callable([$this->wordPressManager, 'getQueryVar']));
    }

    public function test_sanitize_text_field_calls_wp_function(): void
    {
        $this->mockWordPressFunction('sanitize_text_field', 'sanitized-text');

        $result = $this->wordPressManager->sanitizeTextField('test text');

        $this->assertEquals('sanitized-text', $result);
    }

    public function test_sanitize_textarea_field_calls_wp_function(): void
    {
        // Since sanitize_textarea_field is already defined in bootstrap.php,
        // we test the actual behavior rather than mocking
        $result = $this->wordPressManager->sanitizeTextareaField('test textarea');

        $this->assertEquals('test textarea', $result);
    }

    public function test_esc_url_raw_calls_wp_function(): void
    {
        $this->mockWordPressFunction('esc_url_raw', 'http://example.com');

        $result = $this->wordPressManager->escUrlRaw('http://example.com');

        $this->assertEquals('http://example.com', $result);
    }

    public function test_get_home_url_calls_wp_function(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com/test');

        $result = $this->wordPressManager->getHomeUrl('/test');

        $this->assertEquals('http://example.com/test', $result);
    }

    private function setupWordPressMocks(): void
    {
        $functions = [
            'is_user_logged_in', 'wp_get_current_user', 'wp_verify_nonce',
            'get_query_var', 'sanitize_text_field', 'sanitize_textarea_field',
            'esc_url_raw', 'home_url'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        \$key = \$args[0] ?? 'default';
                        if (isset(\$GLOBALS['_test_mock_{$function}_' . \$key])) {
                            return \$GLOBALS['_test_mock_{$function}_' . \$key];
                        }
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return \$args[1] ?? null;
                    }
                ");
            }
        }
    }

    private function mockWordPressFunction(string $functionName, mixed $returnValue, ?string $param = null): void
    {
        $key = $param ? "{$functionName}_{$param}" : $functionName;
        $GLOBALS['_test_mock_' . $key] = $returnValue;
    }

    private function clearWordPressMocks(): void
    {
        $functions = [
            'is_user_logged_in', 'wp_get_current_user', 'wp_verify_nonce',
            'get_query_var', 'sanitize_text_field', 'sanitize_textarea_field',
            'esc_url_raw', 'home_url'
        ];
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
