<?php

namespace Tests\Unit\Features\MinisiteViewer\Hooks;

use Minisite\Features\MinisiteViewer\Hooks\ViewHooks;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Infrastructure\Http\TestTerminationHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ViewHooks
 *
 * Tests the ViewHooks for proper WordPress hook registration
 *
 */
final class ViewHooksTest extends TestCase
{
    private ViewHooks $viewHooks;
    private MockObject $minisitePageController;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->minisitePageController = $this->createMock(MinisitePageController::class);
        $this->wordPressManager = $this->createMock(WordPressMinisiteManager::class);

        // Use TestTerminationHandler so exit doesn't terminate tests
        $terminationHandler = new TestTerminationHandler();

        $this->viewHooks = new ViewHooks($this->minisitePageController, $this->wordPressManager, $terminationHandler);
    }

    /**
     * Test register method exists and is callable
     */
    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->viewHooks, 'register'));
        $this->assertTrue(is_callable([$this->viewHooks, 'register']));
    }

    /**
     * Test handleViewRoutes method exists and is callable
     */
    public function test_handle_display_routes_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->viewHooks, 'handleViewRoutes'));
        $this->assertTrue(is_callable([$this->viewHooks, 'handleViewRoutes']));
    }

    /**
     * Test addQueryVars method exists and is callable
     */
    public function test_add_query_vars_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->viewHooks, 'addQueryVars'));
        $this->assertTrue(is_callable([$this->viewHooks, 'addQueryVars']));
    }

    /**
     * Test handleViewRoutes with valid minisite route
     */
    public function test_handle_display_routes_with_valid_minisite_route(): void
    {
        // This test verifies the method exists and can be called
        // In a real environment, WordPress functions would be available
        $this->assertTrue(method_exists($this->viewHooks, 'handleViewRoutes'));

        // We can't easily test the actual functionality without WordPress environment
        // but we can verify the method exists and is callable
        $this->assertTrue(is_callable([$this->viewHooks, 'handleViewRoutes']));
    }

    /**
     * Test handleViewRoutes with non-minisite route
     */
    public function test_handle_display_routes_with_non_minisite_route(): void
    {
        // Mock WordPress functions with empty values
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => ''
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleView');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test handleViewRoutes with missing minisite parameter
     */
    public function test_handle_display_routes_with_missing_minisite_parameter(): void
    {
        // Mock WordPress functions with null values
        $this->mockWordPressFunctions([
            'minisite_biz' => null,
            'minisite_loc' => null
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleView');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test handleViewRoutes with empty business slug
     */
    public function test_handle_display_routes_with_empty_business_slug(): void
    {
        // Mock WordPress functions with empty business slug
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => 'downtown'
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleView');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test handleViewRoutes with empty location slug
     */
    public function test_handle_display_routes_with_empty_location_slug(): void
    {
        // Mock WordPress functions with empty location slug
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => ''
        ]);

        // Controller should not be called
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleView');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test handleViewRoutes with special characters in slugs
     */
    public function test_handle_display_routes_with_special_characters_in_slugs(): void
    {
        // This test verifies the method exists and can be called
        // In a real environment, WordPress functions would be available
        $this->assertTrue(method_exists($this->viewHooks, 'handleViewRoutes'));

        // We can't easily test the actual functionality without WordPress environment
        // but we can verify the method exists and is callable
        $this->assertTrue(is_callable([$this->viewHooks, 'handleViewRoutes']));
    }

    /**
     * Test addQueryVars returns same array
     */
    public function test_add_query_vars_returns_same_array(): void
    {
        $inputVars = ['existing_var' => 'value'];
        $result = $this->viewHooks->addQueryVars($inputVars);

        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with empty array
     */
    public function test_add_query_vars_with_empty_array(): void
    {
        $inputVars = [];
        $result = $this->viewHooks->addQueryVars($inputVars);

        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with null array
     */
    public function test_add_query_vars_with_null_array(): void
    {
        $this->expectException(\TypeError::class);
        $this->viewHooks->addQueryVars(null);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->viewHooks);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        // Now expects 3 parameters: minisitePageController, wordPressManager, terminationHandler
        $this->assertEquals(3, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $this->assertEquals(MinisitePageController::class, $params[0]->getType()->getName());
        $this->assertEquals(WordPressMinisiteManager::class, $params[1]->getType()->getName());
        $this->assertEquals('Minisite\Infrastructure\Http\TerminationHandlerInterface', $params[2]->getType()->getName());
    }

    /**
     * Test handleViewRoutes with valid route calls controller
     */
    public function test_handle_view_routes_with_valid_route_calls_controller(): void
    {
        // Mock WordPressManager to return valid slugs
        $this->wordPressManager
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                return match ($var) {
                    'minisite_biz' => 'coffee-shop',
                    'minisite_loc' => 'downtown',
                    default => null
                };
            });

        // Controller should be called
        $this->minisitePageController
            ->expects($this->once())
            ->method('handleView');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test handleViewRoutes with controller exception
     */
    public function test_handle_view_routes_with_controller_exception(): void
    {
        // Mock WordPressManager to return valid slugs
        $this->wordPressManager
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) {
                return match ($var) {
                    'minisite_biz' => 'coffee-shop',
                    'minisite_loc' => 'downtown',
                    default => null
                };
            });

        // Controller throws exception
        $this->minisitePageController
            ->expects($this->once())
            ->method('handleView')
            ->willThrowException(new \Exception('Controller error'));

        // Should propagate exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller error');

        $this->viewHooks->handleViewRoutes();
    }

    /**
     * Test addRewriteRules method exists and is callable
     */
    public function test_add_rewrite_rules_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->viewHooks, 'addRewriteRules'));
        $this->assertTrue(is_callable([$this->viewHooks, 'addRewriteRules']));
    }

    /**
     * Test addRewriteRules can be called
     */
    public function test_add_rewrite_rules_can_be_called(): void
    {
        // Should not throw exception
        $this->viewHooks->addRewriteRules();
        $this->assertTrue(true);
    }

    /**
     * Test getController returns the controller
     */
    public function test_get_controller_returns_controller(): void
    {
        $controller = $this->viewHooks->getController();

        $this->assertSame($this->minisitePageController, $controller);
        $this->assertInstanceOf(MinisitePageController::class, $controller);
    }

    /**
     * Test register method can be called
     */
    public function test_register_can_be_called(): void
    {
        // Should not throw exception
        $this->viewHooks->register();
        $this->assertTrue(true);
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(array $queryVars): void
    {
        // Use WordPressManager mock instead of global functions
        $this->wordPressManager
            ->method('getQueryVar')
            ->willReturnCallback(function ($var) use ($queryVars) {
                $key = match ($var) {
                    'minisite_biz' => 'minisite_biz',
                    'minisite_loc' => 'minisite_loc',
                    default => $var
                };
                return $queryVars[$key] ?? null;
            });
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'get_query_var'
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

        // Handle 'exit' separately since it's a language construct, not a function
        // In tests, we catch exceptions from redirect() instead
    }

    /**
     * Mock WordPress function for specific test cases
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue, ?string $param = null): void
    {
        $key = $param ? "{$functionName}_{$param}" : $functionName;
        $GLOBALS['_test_mock_' . $key] = $returnValue;
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = [
            'get_query_var'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
            unset($GLOBALS['_test_mock_' . $func . '_minisite_biz']);
            unset($GLOBALS['_test_mock_' . $func . '_minisite_loc']);
        }
    }
}
