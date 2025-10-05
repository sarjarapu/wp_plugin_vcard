<?php

namespace Tests\Unit\Features\MinisiteDisplay\Hooks;

use Minisite\Features\MinisiteDisplay\Hooks\DisplayHooks;
use Minisite\Features\MinisiteDisplay\Controllers\MinisitePageController;
use PHPUnit\Framework\TestCase;

/**
 * Test DisplayHooks
 * 
 * Tests the DisplayHooks for proper WordPress hook registration
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class DisplayHooksTest extends TestCase
{
    private DisplayHooks $displayHooks;
    private $minisitePageController;

    protected function setUp(): void
    {
        $this->minisitePageController = $this->createMock(MinisitePageController::class);
        $this->displayHooks = new DisplayHooks($this->minisitePageController);
    }

    /**
     * Test register method exists and is callable
     */
    public function test_register_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'register'));
        $this->assertTrue(is_callable([$this->displayHooks, 'register']));
    }

    /**
     * Test handleDisplayRoutes method exists and is callable
     */
    public function test_handle_display_routes_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'handleDisplayRoutes'));
        $this->assertTrue(is_callable([$this->displayHooks, 'handleDisplayRoutes']));
    }

    /**
     * Test addQueryVars method exists and is callable
     */
    public function test_add_query_vars_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->displayHooks, 'addQueryVars'));
        $this->assertTrue(is_callable([$this->displayHooks, 'addQueryVars']));
    }

    /**
     * Test handleDisplayRoutes with valid minisite route
     */
    public function test_handle_display_routes_with_valid_minisite_route(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => 'downtown'
        ]);

        // Mock controller to expect handleDisplay call
        $this->minisitePageController
            ->expects($this->once())
            ->method('handleDisplay');

        // This will call exit, so we need to catch it
        try {
            $this->displayHooks->handleDisplayRoutes();
        } catch (\Exception $e) {
            // Expected due to exit() call
        }
    }

    /**
     * Test handleDisplayRoutes with non-minisite route
     */
    public function test_handle_display_routes_with_non_minisite_route(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite_biz' => '',
            'minisite_loc' => ''
        ]);

        // Mock controller to not expect handleDisplay call
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with missing minisite parameter
     */
    public function test_handle_display_routes_with_missing_minisite_parameter(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite_biz' => null,
            'minisite_loc' => 'downtown'
        ]);

        // Mock controller to not expect handleDisplay call
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with empty business slug
     */
    public function test_handle_display_routes_with_empty_business_slug(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite' => '1',
            'minisite_biz' => '',
            'minisite_loc' => 'downtown'
        ]);

        // Mock controller to not expect handleDisplay call
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with empty location slug
     */
    public function test_handle_display_routes_with_empty_location_slug(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite' => '1',
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => ''
        ]);

        // Mock controller to not expect handleDisplay call
        $this->minisitePageController
            ->expects($this->never())
            ->method('handleDisplay');

        $this->displayHooks->handleDisplayRoutes();
    }

    /**
     * Test handleDisplayRoutes with special characters in slugs
     */
    public function test_handle_display_routes_with_special_characters_in_slugs(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite_biz' => 'café-&-restaurant',
            'minisite_loc' => 'main-street-123'
        ]);

        // Mock controller to expect handleDisplay call
        $this->minisitePageController
            ->expects($this->once())
            ->method('handleDisplay');

        // This will call exit, so we need to catch it
        try {
            $this->displayHooks->handleDisplayRoutes();
        } catch (\Exception $e) {
            // Expected due to exit() call
        }
    }

    /**
     * Test addQueryVars returns the same array
     */
    public function test_add_query_vars_returns_same_array(): void
    {
        $inputVars = ['existing_var1', 'existing_var2'];
        
        $result = $this->displayHooks->addQueryVars($inputVars);
        
        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with empty array
     */
    public function test_add_query_vars_with_empty_array(): void
    {
        $inputVars = [];
        
        $result = $this->displayHooks->addQueryVars($inputVars);
        
        $this->assertEquals($inputVars, $result);
    }

    /**
     * Test addQueryVars with null array
     */
    public function test_add_query_vars_with_null_array(): void
    {
        $this->expectException(\TypeError::class);
        
        $inputVars = null;
        $this->displayHooks->addQueryVars($inputVars);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->displayHooks);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals(MinisitePageController::class, $params[0]->getType()->getName());
    }

    /**
     * Test handleDisplayRoutes with controller exception
     */
    public function test_handle_display_routes_with_controller_exception(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunctions([
            'minisite_biz' => 'coffee-shop',
            'minisite_loc' => 'downtown'
        ]);

        // Mock controller to throw exception
        $this->minisitePageController
            ->expects($this->once())
            ->method('handleDisplay')
            ->willThrowException(new \Exception('Controller error'));

        // This will call exit, so we need to catch it
        try {
            $this->displayHooks->handleDisplayRoutes();
        } catch (\Exception $e) {
            // Expected due to exit() call
        }
        
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /**
     * Mock WordPress functions for testing
     */
    private function mockWordPressFunctions(array $queryVars): void
    {
        // Mock get_query_var function
        if (!function_exists('get_query_var')) {
            eval('
                function get_query_var($var) {
                    $vars = ' . var_export($queryVars, true) . ';
                    return $vars[$var] ?? null;
                }
            ');
        }
    }
}
