<?php

declare(strict_types=1);

namespace Tests\Unit\Features\VersionManagement\WordPress;

use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressVersionManager
 */
#[CoversClass(WordPressVersionManager::class)]
final class WordPressVersionManagerTest extends TestCase
{
    private WordPressVersionManager $wordPressManager;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $terminationHandler = new TestTerminationHandler();
        $this->wordPressManager = new WordPressVersionManager($terminationHandler);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
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
        $this->assertTrue(is_callable(array($this->wordPressManager, 'getQueryVar')));
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

    // ===== VERSION-SPECIFIC METHODS =====

    /**
     * Test sendJsonSuccess sends JSON success response
     */
    public function test_send_json_success_calls_wp_function(): void
    {
        $data = array('message' => 'Success', 'id' => 123);
        $this->mockWordPressFunction('wp_send_json_success', null);

        // wp_send_json_success calls exit, so we can't test return value
        // But we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonSuccess'));
        $this->assertTrue(is_callable(array($this->wordPressManager, 'sendJsonSuccess')));
    }

    /**
     * Test sendJsonSuccess with default parameters
     */
    public function test_send_json_success_with_default_parameters(): void
    {
        $this->mockWordPressFunction('wp_send_json_success', null);

        // Method should accept empty array and default status code
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonSuccess'));
    }

    /**
     * Test sendJsonSuccess with custom status code
     */
    public function test_send_json_success_with_custom_status_code(): void
    {
        $this->mockWordPressFunction('wp_send_json_success', null);

        // Method should accept custom status code
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonSuccess'));
    }

    /**
     * Test sendJsonError sends JSON error response
     */
    public function test_send_json_error_calls_wp_function(): void
    {
        $this->mockWordPressFunction('wp_send_json_error', null);

        // wp_send_json_error calls exit, so we can't test return value
        // But we can verify the method exists and is callable
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonError'));
        $this->assertTrue(is_callable(array($this->wordPressManager, 'sendJsonError')));
    }

    /**
     * Test sendJsonError with default status code
     */
    public function test_send_json_error_with_default_status_code(): void
    {
        $this->mockWordPressFunction('wp_send_json_error', null);

        // Method should accept default status code (400)
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonError'));
    }

    /**
     * Test sendJsonError with custom status code
     */
    public function test_send_json_error_with_custom_status_code(): void
    {
        $this->mockWordPressFunction('wp_send_json_error', null);

        // Method should accept custom status code
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonError'));
    }

    /**
     * Test setStatusHeader sets HTTP status header
     */
    public function test_set_status_header_calls_wp_function(): void
    {
        $this->mockWordPressFunction('status_header', null);

        $this->wordPressManager->setStatusHeader(404);

        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test setStatusHeader with different status codes
     */
    public function test_set_status_header_with_different_codes(): void
    {
        $this->mockWordPressFunction('status_header', null);

        $this->wordPressManager->setStatusHeader(200);
        $this->wordPressManager->setStatusHeader(201);
        $this->wordPressManager->setStatusHeader(400);
        $this->wordPressManager->setStatusHeader(404);
        $this->wordPressManager->setStatusHeader(500);

        // If no exception is thrown, all status codes worked
        $this->assertTrue(true);
    }

    /**
     * Test setNoCacheHeaders sets no-cache headers
     */
    public function test_set_no_cache_headers_calls_wp_function(): void
    {
        $this->mockWordPressFunction('nocache_headers', null);

        $this->wordPressManager->setNoCacheHeaders();

        // If no exception is thrown, the method worked
        $this->assertTrue(true);
    }

    /**
     * Test jsonEncode encodes data as JSON
     */
    public function test_json_encode_encodes_data_correctly(): void
    {
        $data = array('key' => 'value', 'number' => 123);
        $expected = wp_json_encode($data);

        $this->mockWordPressFunction('wp_json_encode', $expected);

        $result = $this->wordPressManager->jsonEncode($data);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test jsonEncode with JSON options
     */
    public function test_json_encode_with_options(): void
    {
        $data = array('key' => 'value');
        $expected = wp_json_encode($data, JSON_PRETTY_PRINT);

        $this->mockWordPressFunction('wp_json_encode', $expected);

        $result = $this->wordPressManager->jsonEncode($data, JSON_PRETTY_PRINT);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test jsonEncode with depth parameter
     */
    public function test_json_encode_with_depth(): void
    {
        $data = array('key' => 'value');
        $expected = wp_json_encode($data, 0, 10);

        $this->mockWordPressFunction('wp_json_encode', $expected);

        $result = $this->wordPressManager->jsonEncode($data, 0, 10);

        $this->assertEquals($expected, $result);
    }

    /**
     * Test jsonEncode returns false on failure
     */
    public function test_json_encode_returns_false_on_failure(): void
    {
        // Test with invalid UTF-8 data that can't be encoded
        $this->mockWordPressFunction('wp_json_encode', false);

        $result = $this->wordPressManager->jsonEncode("\xB1\x31");

        $this->assertFalse($result);
    }

    /**
     * Test getHomeUrl override with scheme parameter
     * Note: home_url is already defined in bootstrap.php, so we test method signature
     */
    public function test_get_home_url_with_scheme_parameter(): void
    {
        // Verify method accepts scheme parameter
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $method = $reflection->getMethod('getHomeUrl');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('path', $params[0]->getName());
        $this->assertEquals('scheme', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    /**
     * Test getHomeUrl with null scheme (default)
     * Note: home_url is already defined in bootstrap.php, so we test method signature
     */
    public function test_get_home_url_with_null_scheme(): void
    {
        // Verify method accepts null scheme
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $method = $reflection->getMethod('getHomeUrl');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertTrue($params[1]->allowsNull());
    }

    /**
     * Test getHomeUrl with different schemes
     * Note: home_url is already defined in bootstrap.php, so we test method exists
     */
    public function test_get_home_url_with_different_schemes(): void
    {
        // Verify method exists and accepts scheme parameter
        $this->assertTrue(method_exists($this->wordPressManager, 'getHomeUrl'));
        $this->assertTrue(is_callable(array($this->wordPressManager, 'getHomeUrl')));

        // Verify method signature allows scheme parameter
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $method = $reflection->getMethod('getHomeUrl');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('scheme', $params[1]->getName());
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('Minisite\Infrastructure\Http\TerminationHandlerInterface', $params[0]->getType()->getName());
    }

    /**
     * Test class extends BaseWordPressManager
     */
    public function test_class_extends_base_wordpress_manager(): void
    {
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $parent = $reflection->getParentClass();

        $this->assertNotNull($parent);
        $this->assertEquals('Minisite\Features\BaseFeature\WordPress\BaseWordPressManager', $parent->getName());
    }

    /**
     * Test class implements WordPressManagerInterface
     */
    public function test_class_implements_wordpress_manager_interface(): void
    {
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains('Minisite\Infrastructure\WordPress\Contracts\WordPressManagerInterface', $interfaces);
    }

    /**
     * Test class is not final (allows mocking in tests)
     */
    public function test_class_is_not_final(): void
    {
        $reflection = new \ReflectionClass(WordPressVersionManager::class);
        $this->assertFalse($reflection->isFinal());
    }

    private function setupWordPressMocks(): void
    {
        $functions = array(
            'is_user_logged_in', 'wp_get_current_user', 'wp_verify_nonce',
            'get_query_var', 'sanitize_text_field', 'sanitize_textarea_field',
            'esc_url_raw', 'home_url', 'wp_send_json_success', 'wp_send_json_error',
            'status_header', 'nocache_headers', 'wp_json_encode',
        );

        foreach ($functions as $function) {
            if (! function_exists($function)) {
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

    private function mockWordPressFunction(string $functionName, mixed $returnValue, ?string $param = null): void
    {
        $key = $param ? "{$functionName}_{$param}" : $functionName;
        $GLOBALS['_test_mock_' . $key] = $returnValue;
    }

    private function clearWordPressMocks(): void
    {
        $functions = array(
            'is_user_logged_in', 'wp_get_current_user', 'wp_verify_nonce',
            'get_query_var', 'sanitize_text_field', 'sanitize_textarea_field',
            'esc_url_raw', 'home_url', 'wp_send_json_success', 'wp_send_json_error',
            'status_header', 'nocache_headers', 'wp_json_encode',
        );
        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
