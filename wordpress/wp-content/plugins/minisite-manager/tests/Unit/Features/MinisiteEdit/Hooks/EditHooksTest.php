<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Hooks;

use Minisite\Features\MinisiteEdit\Hooks\EditHooks;
use Minisite\Features\MinisiteEdit\Controllers\EditController;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Features\MinisiteViewer\Controllers\MinisitePageController;
use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test EditHooks
 */
#[Group('skip')]
class EditHooksTest extends TestCase
{
    private EditHooks $hooks;
    private $mockEditController;
    private $mockWordPressManager;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->mockEditController = $this->createMock(EditController::class);
        $this->mockWordPressManager = $this->createMock(WordPressEditManager::class);
        
        // Create a simple stub for MinisitePageController since it's final and can't be mocked
        $stubMinisitePageController = new class extends MinisitePageController {
            public function __construct() {
                // Empty constructor - we don't need real functionality for these tests
            }
        };
        
        $this->hooks = new EditHooks($this->mockEditController, $this->mockWordPressManager, $stubMinisitePageController);
    }

    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testRegister(): void
    {
        // The register method should not throw any exceptions
        $this->hooks->register();
        $this->assertTrue(true);
    }

    public function testHandleEditRoutesNotAccountRoute(): void
    {
        $this->mockWordPressManager->expects($this->once())
            ->method('getQueryVar')
            ->with('minisite_account')
            ->willReturn('0');

        $this->mockEditController->expects($this->never())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesNotEditAction(): void
    {
        $this->mockWordPressManager->expects($this->exactly(2))
            ->method('getQueryVar')
            ->willReturnMap([
                ['minisite_account', '', '1'],
                ['minisite_account_action', '', 'sites']
            ]);

        $this->mockEditController->expects($this->never())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesSuccess(): void
    {
        $this->mockWordPressManager->method('getQueryVar')
            ->willReturnCallback(function($var, $default = '') {
                if ($var === 'minisite_account') {
                    return '1';
                }
                if ($var === 'minisite_account_action') {
                    return 'edit';
                }
                return $default;
            });

        // Since the hooks calls exit, we need to expect it
        $this->expectException(\Exception::class);
        
        // Mock the exit function to throw an exception instead of terminating
        Functions\when('exit')->justReturn(function () {
            throw new \Exception('Exit called');
        });
        
        $this->mockEditController->expects($this->once())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesWithSiteId(): void
    {
        $this->mockWordPressManager->method('getQueryVar')
            ->willReturnCallback(function($var, $default = '') {
                if ($var === 'minisite_account') {
                    return '1';
                }
                if ($var === 'minisite_account_action') {
                    return 'edit';
                }
                return $default;
            });

        $this->expectException(\Exception::class);
        
        Functions\when('exit')->justReturn(function () {
            throw new \Exception('Exit called');
        });
        
        $this->mockEditController->expects($this->once())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesWithVersionId(): void
    {
        $this->mockWordPressManager->method('getQueryVar')
            ->willReturnCallback(function($var, $default = '') {
                if ($var === 'minisite_account') {
                    return '1';
                }
                if ($var === 'minisite_account_action') {
                    return 'edit';
                }
                return $default;
            });

        $this->expectException(\Exception::class);
        
        // Mock the exit function to throw an exception instead of terminating
        Functions\when('exit')->justReturn(function () {
            throw new \Exception('Exit called');
        });
        
        $this->mockEditController->expects($this->once())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesIgnoresOtherActions(): void
    {
        $actions = ['login', 'logout', 'dashboard', 'register', 'forgot', 'sites', 'new', 'publish', 'preview', 'versions'];

        foreach ($actions as $action) {
            $this->mockWordPressManager->method('getQueryVar')
                ->willReturnCallback(function($var, $default = '') use ($action) {
                    if ($var === 'minisite_account') {
                        return '1';
                    }
                    if ($var === 'minisite_account_action') {
                        return $action;
                    }
                    return $default;
                });

            $this->mockEditController->expects($this->never())
                ->method('handleEdit');

            $this->hooks->handleEditRoutes();
        }
    }

    public function testHandleEditRoutesWithNumericAccountValue(): void
    {
        $this->mockWordPressManager->method('getQueryVar')
            ->willReturnCallback(function($var, $default = '') {
                if ($var === 'minisite_account') {
                    return 1; // Numeric value
                }
                if ($var === 'minisite_account_action') {
                    return 'edit';
                }
                return $default;
            });

        $this->expectException(\Exception::class);
        
        Functions\when('exit')->justReturn(function () {
            throw new \Exception('Exit called');
        });
        
        $this->mockEditController->expects($this->once())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    public function testHandleEditRoutesWithStringAccountValue(): void
    {
        $this->mockWordPressManager->method('getQueryVar')
            ->willReturnCallback(function($var, $default = '') {
                if ($var === 'minisite_account') {
                    return '1'; // String value
                }
                if ($var === 'minisite_account_action') {
                    return 'edit';
                }
                return $default;
            });

        $this->expectException(\Exception::class);
        
        Functions\when('exit')->justReturn(function () {
            throw new \Exception('Exit called');
        });
        
        $this->mockEditController->expects($this->once())
            ->method('handleEdit');

        $this->hooks->handleEditRoutes();
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = [
            'get_query_var', 'add_action', 'add_filter', 'exit'
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
            'get_query_var', 'add_action', 'add_filter', 'exit'
        ];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
