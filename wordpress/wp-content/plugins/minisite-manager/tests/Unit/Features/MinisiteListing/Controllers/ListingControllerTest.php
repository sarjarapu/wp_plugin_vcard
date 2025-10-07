<?php

namespace Tests\Unit\Features\MinisiteListing\Controllers;

use Minisite\Features\MinisiteListing\Controllers\ListingController;
use Minisite\Features\MinisiteListing\Handlers\ListMinisitesHandler;
use Minisite\Features\MinisiteListing\Services\MinisiteListingService;
use Minisite\Features\MinisiteListing\Http\ListingRequestHandler;
use Minisite\Features\MinisiteListing\Http\ListingResponseHandler;
use Minisite\Features\MinisiteListing\Rendering\ListingRenderer;
use Minisite\Features\MinisiteListing\Commands\ListMinisitesCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test ListingController
 * 
 * Tests the ListingController for proper coordination of listing flow
 */
final class ListingControllerTest extends TestCase
{
    private ListingController $listingController;
    private ListMinisitesHandler|MockObject $listMinisitesHandler;
    private MinisiteListingService|MockObject $listingService;
    private ListingRequestHandler|MockObject $requestHandler;
    private ListingResponseHandler|MockObject $responseHandler;
    private ListingRenderer|MockObject $renderer;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->listMinisitesHandler = $this->createMock(ListMinisitesHandler::class);
        $this->listingService = $this->createMock(MinisiteListingService::class);
        $this->requestHandler = $this->createMock(ListingRequestHandler::class);
        $this->responseHandler = $this->createMock(ListingResponseHandler::class);
        $this->renderer = $this->createMock(ListingRenderer::class);

        // Create ListingController with mocked dependencies
        $this->listingController = new ListingController(
            $this->listMinisitesHandler,
            $this->listingService,
            $this->requestHandler,
            $this->responseHandler,
            $this->renderer
        );
        
        // Setup WordPress function mocks
        $this->setupWordPressMocks();
    }
    
    protected function tearDown(): void
    {
        $this->clearWordPressMocks();
    }

    /**
     * Test ListingController can be instantiated
     */
    public function test_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ListingController::class, $this->listingController);
    }

    /**
     * Test handleList with successful listing
     */
    public function test_handle_list_with_successful_listing(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $mockMinisites = [
            [
                'id' => '1',
                'title' => 'Test Minisite 1',
                'name' => 'test-minisite-1',
                'slugs' => ['business' => 'test', 'location' => 'business'],
                'route' => '/b/test/business',
                'location' => 'New York, NY, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 10:00',
                'published_at' => '2025-01-06 09:00',
                'subscription' => 'Pro',
                'online' => 'Yes'
            ]
        ];

        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });
        $this->mockWordPressFunction('current_user_can', function($capability) {
            return $capability === 'minisite_create';
        });

        // Mock request handler to return a command
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn($command);

        // Mock handler to return success
        $this->listMinisitesHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisites' => $mockMinisites]);

        // Mock renderer to render list page
        $this->renderer->expects($this->once())
            ->method('renderListPage')
            ->with($this->callback(function($data) use ($mockMinisites) {
                return isset($data['sites']) && $data['sites'] === $mockMinisites &&
                       isset($data['can_create']) && $data['can_create'] === true &&
                       isset($data['page_title']) && $data['page_title'] === 'My Minisites';
            }));

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test handleList with failed listing
     */
    public function test_handle_list_with_failed_listing(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });
        $this->mockWordPressFunction('current_user_can', function($capability) {
            return $capability === 'minisite_create';
        });

        // Mock request handler to return a command
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn($command);

        // Mock handler to return failure
        $this->listMinisitesHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => false, 'error' => 'Database connection failed']);

        // Mock renderer to render list page with error
        $this->renderer->expects($this->once())
            ->method('renderListPage')
            ->with($this->callback(function($data) {
                return isset($data['error']) && $data['error'] === 'Database connection failed' &&
                       isset($data['sites']) && $data['sites'] === [];
            }));

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test handleList with no command (user not logged in)
     */
    public function test_handle_list_with_no_command(): void
    {
        // Mock request handler to return null (user not logged in)
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn(null);

        // Mock response handler to redirect to login
        $this->responseHandler->expects($this->once())
            ->method('redirectToLogin');

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test handleList with exception
     */
    public function test_handle_list_with_exception(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });
        $this->mockWordPressFunction('current_user_can', function($capability) {
            return $capability === 'minisite_create';
        });

        // Mock request handler to return a command
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn($command);

        // Mock handler to throw exception
        $this->listMinisitesHandler->method('handle')
            ->with($command)
            ->willThrowException(new \Exception('Unexpected error'));

        // Mock renderer to render list page with error
        $this->renderer->expects($this->once())
            ->method('renderListPage')
            ->with($this->callback(function($data) {
                return isset($data['error']) && $data['error'] === 'An error occurred while loading minisites' &&
                       isset($data['sites']) && $data['sites'] === [];
            }));

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test handleList with user without create permission
     */
    public function test_handle_list_with_user_without_create_permission(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);
        $mockMinisites = [
            [
                'id' => '1',
                'title' => 'Test Minisite 1',
                'name' => 'test-minisite-1',
                'slugs' => ['business' => 'test', 'location' => 'business'],
                'route' => '/b/test/business',
                'location' => 'New York, NY, US',
                'status' => 'published',
                'status_chip' => 'Published',
                'updated_at' => '2025-01-06 10:00',
                'published_at' => '2025-01-06 09:00',
                'subscription' => 'Pro',
                'online' => 'Yes'
            ]
        ];

        // Mock WordPress functions
        $GLOBALS['wp_get_current_user'] = function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        };

        $this->mockWordPressFunction('current_user_can', function($capability) {
            return false; // User cannot create minisites
        });

        // Mock request handler to return a command
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn($command);

        // Mock handler to return success
        $this->listMinisitesHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisites' => $mockMinisites]);

        // Mock renderer to render list page
        $this->renderer->expects($this->once())
            ->method('renderListPage')
            ->with($this->callback(function($data) use ($mockMinisites) {
                return isset($data['sites']) && $data['sites'] === $mockMinisites &&
                       isset($data['can_create']) && $data['can_create'] === false &&
                       isset($data['page_title']) && $data['page_title'] === 'My Minisites';
            }));

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test handleList with empty minisites array
     */
    public function test_handle_list_with_empty_minisites(): void
    {
        $command = new ListMinisitesCommand(123, 50, 0);

        // Mock WordPress functions
        $this->mockWordPressFunction('is_user_logged_in', true);
        $this->mockWordPressFunction('wp_get_current_user', function() {
            $user = new \stdClass();
            $user->ID = 123;
            $user->user_login = 'testuser';
            $user->user_email = 'test@example.com';
            return $user;
        });
        $this->mockWordPressFunction('current_user_can', function($capability) {
            return $capability === 'minisite_create';
        });

        // Mock request handler to return a command
        $this->requestHandler->method('parseListMinisitesRequest')
            ->willReturn($command);

        // Mock handler to return empty array
        $this->listMinisitesHandler->method('handle')
            ->with($command)
            ->willReturn(['success' => true, 'minisites' => []]);

        // Mock renderer to render list page
        $this->renderer->expects($this->once())
            ->method('renderListPage')
            ->with($this->callback(function($data) {
                return isset($data['sites']) && $data['sites'] === [] &&
                       isset($data['can_create']) && $data['can_create'] === true &&
                       !isset($data['error']);
            }));

        // Call the method
        $this->listingController->handleList();
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->listingController);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(5, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $expectedTypes = [
            ListMinisitesHandler::class,
            MinisiteListingService::class,
            ListingRequestHandler::class,
            ListingResponseHandler::class,
            ListingRenderer::class
        ];
        
        foreach ($params as $index => $param) {
            $this->assertEquals($expectedTypes[$index], $param->getType()->getName());
        }
    }

    /**
     * Test renderListPage method is private
     */
    public function test_render_list_page_method_is_private(): void
    {
        $reflection = new \ReflectionClass($this->listingController);
        $method = $reflection->getMethod('renderListPage');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test handleList method is public
     */
    public function test_handle_list_method_is_public(): void
    {
        $reflection = new \ReflectionClass($this->listingController);
        $method = $reflection->getMethod('handleList');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = ['is_user_logged_in', 'wp_get_current_user', 'current_user_can'];

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
        $functions = ['is_user_logged_in', 'wp_get_current_user', 'current_user_can'];

        foreach ($functions as $func) {
            unset($GLOBALS['_test_mock_' . $func]);
        }
    }
}
