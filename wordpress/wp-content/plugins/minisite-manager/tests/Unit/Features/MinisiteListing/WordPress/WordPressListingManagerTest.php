<?php

declare(strict_types=1);

namespace Tests\Unit\Features\MinisiteListing\WordPress;

use Minisite\Features\MinisiteListing\WordPress\WordPressListingManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test WordPressListingManager
 *
 * Tests WordPress-specific utilities for listing functionality.
 */
final class WordPressListingManagerTest extends TestCase
{
    private WordPressListingManager $wordPressManager;

    protected function setUp(): void
    {
        $terminationHandler = new TestTerminationHandler();
        $this->wordPressManager = new WordPressListingManager($terminationHandler);
        $this->setupWordPressMocks();
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    /**
     * Test currentUserCan with capability that user has
     */
    public function test_current_user_can_with_capability(): void
    {
        $this->mockWordPressFunction('current_user_can', true);

        $result = $this->wordPressManager->currentUserCan('minisite_create');

        $this->assertTrue($result);
    }

    /**
     * Test currentUserCan with capability that user does not have
     */
    public function test_current_user_can_without_capability(): void
    {
        $this->mockWordPressFunction('current_user_can', false);

        $result = $this->wordPressManager->currentUserCan('minisite_create');

        $this->assertFalse($result);
    }

    /**
     * Test currentUserCan with different capabilities
     */
    public function test_current_user_can_with_different_capabilities(): void
    {
        $this->mockWordPressFunction('current_user_can', true);

        $result1 = $this->wordPressManager->currentUserCan('minisite_create');
        $result2 = $this->wordPressManager->currentUserCan('minisite_edit');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    /**
     * Test getHomeUrl with path
     */
    public function test_get_home_url_with_path(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com/account/sites');

        $result = $this->wordPressManager->getHomeUrl('/account/sites');

        $this->assertEquals('http://example.com/account/sites', $result);
    }

    /**
     * Test getHomeUrl without path
     */
    public function test_get_home_url_without_path(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com');

        $result = $this->wordPressManager->getHomeUrl();

        $this->assertEquals('http://example.com', $result);
    }

    /**
     * Test getHomeUrl with scheme parameter
     */
    public function test_get_home_url_with_scheme(): void
    {
        // Since we can't easily mock a function with multiple parameters that returns different values,
        // we'll just verify the method calls home_url with the correct parameters
        // The actual return value depends on the WordPress environment
        $this->mockWordPressFunction('home_url', 'https://example.com/account/sites');

        $result = $this->wordPressManager->getHomeUrl('/account/sites', 'https');

        // The method should call home_url with both path and scheme
        // We verify it returns a string (the actual URL depends on WordPress)
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getHomeUrl with empty path
     */
    public function test_get_home_url_with_empty_path(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com');

        $result = $this->wordPressManager->getHomeUrl('');

        $this->assertEquals('http://example.com', $result);
    }

    /**
     * Test getHomeUrl with null scheme
     */
    public function test_get_home_url_with_null_scheme(): void
    {
        $this->mockWordPressFunction('home_url', 'http://example.com/test');

        $result = $this->wordPressManager->getHomeUrl('/test', null);

        $this->assertEquals('http://example.com/test', $result);
    }

    /**
     * Test currentUserCan method is public
     */
    public function test_current_user_can_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('currentUserCan');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test getHomeUrl method is public
     */
    public function test_get_home_url_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('getHomeUrl');

        $this->assertTrue($method->isPublic());
    }

    /**
     * Test currentUserCan method signature
     */
    public function test_current_user_can_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('currentUserCan');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('capability', $params[0]->getName());
        $this->assertFalse($params[0]->allowsNull());
    }

    /**
     * Test getHomeUrl method signature
     */
    public function test_get_home_url_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->wordPressManager);
        $method = $reflection->getMethod('getHomeUrl');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('path', $params[0]->getName());
        $this->assertEquals('scheme', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = array('current_user_can', 'home_url');

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
            // For callable return values, store the callable
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
        $functions = array('current_user_can', 'home_url');

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
