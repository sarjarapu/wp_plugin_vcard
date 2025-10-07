<?php

namespace Tests\Unit\Features\MinisiteListing\Http;

use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use Minisite\Features\MinisiteListing\Http\ListingRequestHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test ListingRequestHandler
 * 
 * Tests the ListingRequestHandler for proper request processing and validation
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ListingRequestHandlerTest extends TestCase
{
    private ListingRequestHandler $requestHandler;

    protected function setUp(): void
    {
        $this->requestHandler = new ListingRequestHandler();
        
        // Reset $_SERVER and $_GET
        $_SERVER = [];
        $_GET = [];
        
        // Setup WordPress function mocks
        $this->setupWordPressMocks();
    }
    
    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    /**
     * Test parseListMinisitesRequest with logged in user
     */
    public function test_parse_list_minisites_request_with_logged_in_user(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(123, $command->userId);
        $this->assertEquals(50, $command->limit); // Default limit
        $this->assertEquals(0, $command->offset); // Default offset
    }

    /**
     * Test parseListMinisitesRequest with not logged in user
     */
    public function test_parse_list_minisites_request_with_not_logged_in_user(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', false);

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertNull($command);
    }

    /**
     * Test parseListMinisitesRequest with different user ID
     */
    public function test_parse_list_minisites_request_with_different_user_id(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 456;
            $user->user_login = 'anotheruser';
            $user->user_email = 'another@example.com';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(456, $command->userId);
        $this->assertEquals(50, $command->limit);
        $this->assertEquals(0, $command->offset);
    }

    /**
     * Test parseListMinisitesRequest with zero user ID
     */
    public function test_parse_list_minisites_request_with_zero_user_id(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 0; // Guest user
            $user->user_login = '';
            $user->user_email = '';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(0, $command->userId);
        $this->assertEquals(50, $command->limit);
        $this->assertEquals(0, $command->offset);
    }

    /**
     * Test parseListMinisitesRequest with negative user ID
     */
    public function test_parse_list_minisites_request_with_negative_user_id(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = -1; // Invalid user ID
            $user->user_login = 'invalid';
            $user->user_email = 'invalid@example.com';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(-1, $command->userId);
        $this->assertEquals(50, $command->limit);
        $this->assertEquals(0, $command->offset);
    }

    /**
     * Test parseListMinisitesRequest with large user ID
     */
    public function test_parse_list_minisites_request_with_large_user_id(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 999999;
            $user->user_login = 'largeuser';
            $user->user_email = 'large@example.com';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(999999, $command->userId);
        $this->assertEquals(50, $command->limit);
        $this->assertEquals(0, $command->offset);
    }

    /**
     * Test parseListMinisitesRequest always uses default pagination values
     */
    public function test_parse_list_minisites_request_uses_default_pagination(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });

        // Set some GET parameters (should be ignored)
        $_GET['limit'] = '25';
        $_GET['offset'] = '10';
        $_GET['page'] = '2';

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(123, $command->userId);
        $this->assertEquals(50, $command->limit); // Should still be default
        $this->assertEquals(0, $command->offset); // Should still be default
    }

    /**
     * Test parseListMinisitesRequest with null current user
     */
    public function test_parse_list_minisites_request_with_null_current_user(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', null);

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        // Should return null for null user
        $this->assertNull($command);
    }

    /**
     * Test parseListMinisitesRequest with user object without ID property
     */
    public function test_parse_list_minisites_request_with_user_without_id(): void
    {
        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            // No ID property
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });

        $command = $this->requestHandler->parseListMinisitesRequest();
        
        $this->assertInstanceOf(ListMinisitesCommand::class, $command);
        $this->assertEquals(0, $command->userId); // Undefined property should cast to 0
        $this->assertEquals(50, $command->limit);
        $this->assertEquals(0, $command->offset);
    }

    /**
     * Test parseListMinisitesRequest method is public
     */
    public function test_parse_list_minisites_request_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('parseListMinisitesRequest');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test parseListMinisitesRequest method returns correct type
     */
    public function test_parse_list_minisites_request_method_return_type(): void
    {
        $reflection = new \ReflectionClass($this->requestHandler);
        $method = $reflection->getMethod('parseListMinisitesRequest');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertEquals('Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    /**
     * Mock WordPress function
     */
    private function mockWordPressFunction(string $functionName, mixed $returnValue): void
    {
        if (!function_exists($functionName)) {
            if (is_callable($returnValue)) {
                eval("function {$functionName}(...\$args) { 
                    if (isset(\$GLOBALS['_test_mock_{$functionName}'])) {
                        return call_user_func_array(\$GLOBALS['_test_mock_{$functionName}'], \$args);
                    }
                    return null;
                }");
            } else {
                eval("function {$functionName}(...\$args) { 
                    if (isset(\$GLOBALS['_test_mock_{$functionName}'])) {
                        return \$GLOBALS['_test_mock_{$functionName}'];
                    }
                    return null;
                }");
            }
        }
        $GLOBALS['_test_mock_' . $functionName] = $returnValue;
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = ['is_user_logged_in', 'wp_get_current_user'];

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
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        $functions = ['is_user_logged_in', 'wp_get_current_user'];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
