<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Authentication\WordPress;

use PHPUnit\Framework\TestCase;
use Minisite\Features\Authentication\WordPress\WordPressUserManager;

/**
 * Tests for WordPressUserManager
 * 
 * Tests the WordPress function wrapper class to ensure it properly delegates
 * to WordPress functions and maintains the correct interface.
 */
final class WordPressUserManagerTest extends TestCase
{
    private WordPressUserManager $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = new WordPressUserManager();
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    /**
     * Test signon method
     */
    public function test_signon(): void
    {
        $credentials = ['user_login' => 'testuser', 'user_password' => 'password'];
        $mockUser = new \WP_User(1, 'testuser');
        
        $this->mockWordPressFunction('wp_signon', $mockUser);
        
        $result = $this->wordPressManager->signon($credentials, false);
        
        $this->assertEquals($mockUser, $result);
    }

    /**
     * Test createUser method
     */
    public function test_create_user(): void
    {
        $this->mockWordPressFunction('wp_create_user', 123);
        
        $result = $this->wordPressManager->createUser('testuser', 'password', 'test@example.com');
        
        $this->assertEquals(123, $result);
    }

    /**
     * Test getUserBy method
     */
    public function test_get_user_by(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $this->mockWordPressFunction('get_user_by', $mockUser);
        
        $result = $this->wordPressManager->getUserBy('login', 'testuser');
        
        $this->assertEquals($mockUser, $result);
    }

    /**
     * Test getCurrentUser method
     */
    public function test_get_current_user(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $this->mockWordPressFunction('wp_get_current_user', $mockUser);
        
        $result = $this->wordPressManager->getCurrentUser();
        
        $this->assertEquals($mockUser, $result);
    }

    /**
     * Test setCurrentUser method
     */
    public function test_set_current_user(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $this->mockWordPressFunction('wp_set_current_user', $mockUser);
        
        $result = $this->wordPressManager->setCurrentUser(1);
        
        $this->assertEquals($mockUser, $result);
    }

    /**
     * Test setAuthCookie method
     */
    public function test_set_auth_cookie(): void
    {
        $this->mockWordPressFunction('wp_set_auth_cookie', true);
        
        $this->wordPressManager->setAuthCookie(1, true, false);
        
        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test logout method
     */
    public function test_logout(): void
    {
        $this->mockWordPressFunction('wp_logout', true);
        
        $this->wordPressManager->logout();
        
        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test isUserLoggedIn method
     */
    public function test_is_user_logged_in(): void
    {
        $this->mockWordPressFunction('is_user_logged_in', true);
        
        $result = $this->wordPressManager->isUserLoggedIn();
        
        $this->assertTrue($result);
    }

    /**
     * Test isWpError method
     */
    public function test_is_wp_error(): void
    {
        $wpError = new \WP_Error('test', 'Test error');
        $this->mockWordPressFunction('is_wp_error', true);
        
        $result = $this->wordPressManager->isWpError($wpError);
        
        $this->assertTrue($result);
    }

    /**
     * Test isEmail method
     */
    public function test_is_email(): void
    {
        $this->mockWordPressFunction('is_email', true);
        
        $result = $this->wordPressManager->isEmail('test@example.com');
        
        $this->assertTrue($result);
    }

    /**
     * Test sanitizeText method
     */
    public function test_sanitize_text(): void
    {
        $this->mockWordPressFunction('sanitize_text_field', 'sanitized text');
        
        $result = $this->wordPressManager->sanitizeText('raw text');
        
        $this->assertEquals('sanitized text', $result);
    }

    /**
     * Test sanitizeEmail method
     */
    public function test_sanitize_email(): void
    {
        $this->mockWordPressFunction('sanitize_email', 'test@example.com');
        
        $result = $this->wordPressManager->sanitizeEmail('test@example.com');
        
        $this->assertEquals('test@example.com', $result);
    }

    /**
     * Test sanitizeUrl method
     */
    public function test_sanitize_url(): void
    {
        $this->mockWordPressFunction('sanitize_url', 'http://example.com');
        
        $result = $this->wordPressManager->sanitizeUrl('http://example.com');
        
        $this->assertEquals('http://example.com', $result);
    }

    /**
     * Test unslash method
     */
    public function test_unslash(): void
    {
        $this->mockWordPressFunction('wp_unslash', 'unslashed text');
        
        $result = $this->wordPressManager->unslash('slashed\\text');
        
        $this->assertEquals('unslashed text', $result);
    }

    /**
     * Test verifyNonce method
     */
    public function test_verify_nonce(): void
    {
        $this->mockWordPressFunction('wp_verify_nonce', 1);
        
        $result = $this->wordPressManager->verifyNonce('test_nonce', 'test_action');
        
        $this->assertEquals(1, $result);
    }

    /**
     * Test redirect method
     */
    public function test_redirect(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Redirect to: /dashboard (Status: 302)');
        
        $this->wordPressManager->redirect('/dashboard', 302);
    }

    /**
     * Test getHomeUrl method
     */
    public function test_get_home_url(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com/dashboard');
        
        $result = $this->wordPressManager->getHomeUrl('/dashboard');
        
        $this->assertEquals('http://example.com/dashboard', $result);
    }

    /**
     * Test getQueryVar method
     */
    public function test_get_query_var(): void
    {
        // get_query_var is mocked in bootstrap.php to return the default value
        $result = $this->wordPressManager->getQueryVar('test_var', 'expected_default');
        
        $this->assertEquals('expected_default', $result);
    }

    /**
     * Test setStatusHeader method
     */
    public function test_set_status_header(): void
    {
        $this->mockWordPressFunction('status_header', true);
        
        $this->wordPressManager->setStatusHeader(404);
        
        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test getTemplatePart method
     */
    public function test_get_template_part(): void
    {
        $this->mockWordPressFunction('get_template_part', true);
        
        $this->wordPressManager->getTemplatePart('404', 'error');
        
        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test getWpQuery method
     */
    public function test_get_wp_query(): void
    {
        $mockQuery = new \stdClass();
        $GLOBALS['wp_query'] = $mockQuery;
        
        $result = $this->wordPressManager->getWpQuery();
        
        $this->assertEquals($mockQuery, $result);
    }

    /**
     * Test retrievePassword method
     */
    public function test_retrieve_password(): void
    {
        $this->mockWordPressFunction('retrieve_password', true);
        
        $result = $this->wordPressManager->retrievePassword('testuser');
        
        $this->assertTrue($result);
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'wp_signon', 'wp_create_user', 'get_user_by', 'wp_get_current_user',
            'wp_set_current_user', 'wp_set_auth_cookie', 'wp_logout',
            'is_user_logged_in', 'is_wp_error', 'is_email', 'sanitize_text_field',
            'sanitize_email', 'sanitize_url', 'wp_unslash', 'wp_verify_nonce',
            'wp_redirect', 'home_url', 'get_query_var', 'status_header',
            'get_template_part', 'get_wp_query', 'retrieve_password'
        ];

        foreach ($functions as $function) {
            if (!function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            return \$GLOBALS['_test_mock_{$function}'];
                        }
                        return null;
                    }
                ");
            }
        }
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = [
            'wp_signon', 'wp_create_user', 'get_user_by', 'wp_get_current_user',
            'wp_set_current_user', 'wp_set_auth_cookie', 'wp_logout',
            'is_user_logged_in', 'is_wp_error', 'is_email', 'sanitize_text_field',
            'sanitize_email', 'sanitize_url', 'wp_unslash', 'wp_verify_nonce',
            'wp_redirect', 'home_url', 'get_query_var', 'status_header',
            'get_template_part', 'get_wp_query', 'retrieve_password'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
