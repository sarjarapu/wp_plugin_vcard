<?php

declare(strict_types=1);

namespace Tests\Unit\Features\PublishMinisite\WordPress;

use Minisite\Features\PublishMinisite\WordPress\WordPressPublishManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WordPressPublishManager
 */
#[CoversClass(WordPressPublishManager::class)]
final class WordPressPublishManagerTest extends TestCase
{
    private WordPressPublishManager $wordPressManager;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $terminationHandler = new TestTerminationHandler();
        $this->wordPressManager = new WordPressPublishManager($terminationHandler);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
        $this->clearWordPressMocks();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(WordPressPublishManager::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('terminationHandler', $parameters[0]->getName());
    }

    /**
     * Test getCurrentUserId returns user ID
     */
    public function test_get_current_user_id_returns_user_id(): void
    {
        // get_current_user_id is defined in WordPressFunctions.php and uses $GLOBALS['_test_mock_get_current_user_id']
        $GLOBALS['_test_mock_get_current_user_id'] = 123;

        $result = $this->wordPressManager->getCurrentUserId();

        $this->assertEquals(123, $result);

        // Clean up
        unset($GLOBALS['_test_mock_get_current_user_id']);
    }

    /**
     * Test getAdminUrl returns admin URL
     */
    public function test_get_admin_url_returns_admin_url(): void
    {
        $this->mockWordPressFunction('admin_url', 'http://example.com/wp-admin/test');

        $result = $this->wordPressManager->getAdminUrl('test');

        $this->assertIsString($result);
        $this->assertStringContainsString('wp-admin', $result);
    }

    /**
     * Test sendJsonSuccess method exists and is callable
     */
    public function test_send_json_success_method_exists(): void
    {
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonSuccess'));
        $this->assertTrue(is_callable([$this->wordPressManager, 'sendJsonSuccess']));
    }

    /**
     * Test sendJsonError method exists and is callable
     */
    public function test_send_json_error_method_exists(): void
    {
        $this->assertTrue(method_exists($this->wordPressManager, 'sendJsonError'));
        $this->assertTrue(is_callable([$this->wordPressManager, 'sendJsonError']));
    }

    /**
     * Test isWooCommerceActive returns boolean
     */
    public function test_is_woocommerce_active_returns_boolean(): void
    {
        // Test when WooCommerce class doesn't exist
        $result = $this->wordPressManager->isWooCommerceActive();

        $this->assertIsBool($result);
    }

    /**
     * Test getPostData returns post data
     */
    public function test_get_post_data_returns_post_data(): void
    {
        // Mock $_POST
        $_POST['test_field'] = 'test_value';

        $result = $this->wordPressManager->getPostData('test_field');

        $this->assertEquals('test_value', $result);

        // Clean up
        unset($_POST['test_field']);
    }

    /**
     * Test getPostData returns default when field not found
     */
    public function test_get_post_data_returns_default_when_not_found(): void
    {
        $result = $this->wordPressManager->getPostData('non_existent_field', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = ['admin_url', 'wp_send_json_success', 'wp_send_json_error'];

        foreach ($functions as $function) {
            if (! function_exists($function)) {
                eval("
                    function {$function}(...\$args) {
                        if (isset(\$GLOBALS['_test_mock_{$function}'])) {
                            \$mock = \$GLOBALS['_test_mock_{$function}'];
                            if (is_callable(\$mock)) {
                                return call_user_func_array(\$mock, \$args);
                            }
                            return \$mock;
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
        if (is_callable($returnValue)) {
            $GLOBALS['_test_mock_' . $functionName] = $returnValue;
        } else {
            $GLOBALS['_test_mock_' . $functionName] = $returnValue;
        }
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = ['admin_url', 'wp_send_json_success', 'wp_send_json_error'];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}

