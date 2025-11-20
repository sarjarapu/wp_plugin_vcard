<?php

declare(strict_types=1);

namespace Tests\Unit\Features\NewMinisite\Controllers;

use Minisite\Features\NewMinisite\Controllers\NewMinisiteController;
use Minisite\Features\NewMinisite\Rendering\NewMinisiteRenderer;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Infrastructure\Security\FormSecurityHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NewMinisiteController
 */
#[CoversClass(NewMinisiteController::class)]
final class NewMinisiteControllerTest extends TestCase
{
    private NewMinisiteController $controller;
    private NewMinisiteService|MockObject $newMinisiteService;
    private NewMinisiteRenderer|MockObject $newMinisiteRenderer;
    private WordPressNewMinisiteManager|MockObject $wordPressManager;
    private FormSecurityHelper|MockObject $formSecurityHelper;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->newMinisiteService = $this->createMock(NewMinisiteService::class);
        $this->newMinisiteRenderer = $this->createMock(NewMinisiteRenderer::class);
        $this->wordPressManager = $this->createMock(WordPressNewMinisiteManager::class);
        $this->formSecurityHelper = $this->createMock(FormSecurityHelper::class);

        $this->controller = new NewMinisiteController(
            $this->newMinisiteService,
            $this->newMinisiteRenderer,
            $this->wordPressManager,
            $this->formSecurityHelper
        );

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
        $reflection = new \ReflectionClass(NewMinisiteController::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('newMinisiteService', $parameters[0]->getName());
        $this->assertEquals('newMinisiteRenderer', $parameters[1]->getName());
        $this->assertEquals('wordPressManager', $parameters[2]->getName());
        $this->assertEquals('formSecurityHelper', $parameters[3]->getName());
    }

    /**
     * Test handleNewMinisite redirects when user not logged in
     */
    public function test_handle_new_minisite_user_not_logged_in_redirects(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getLoginRedirectUrl')
            ->willReturn('http://example.com/wp-login.php');

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/wp-login.php');

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleNewMinisite renders error when user has no permission
     */
    public function test_handle_new_minisite_user_no_permission_renders_error(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;
        $mockUser->user_login = 'testuser';

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('canCreateNewMinisite')
            ->willReturn(false);

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderError')
            ->with('You do not have permission to create new minisites.');

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleNewMinisite displays form for GET request
     */
    public function test_handle_new_minisite_get_request_displays_form(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;
        $mockUser->user_login = 'testuser';

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('canCreateNewMinisite')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('isPostRequest')
            ->willReturn(false);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getEmptyFormData')
            ->willReturn([]);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getUserMinisiteCount')
            ->willReturn(0);

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderNewMinisiteForm')
            ->with($this->callback(function ($data) {
                return is_object($data) &&
                    isset($data->formData) &&
                    isset($data->userMinisiteCount) &&
                    isset($data->errorMessage) &&
                    isset($data->successMessage);
            }));

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleNewMinisite handles form submission for POST request
     */
    public function test_handle_new_minisite_post_request_submits_form(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;
        $mockUser->user_login = 'testuser';

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('canCreateNewMinisite')
            ->willReturn(true);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('isPostRequest')
            ->willReturn(true);

        // Mock $_POST for form submission
        $_POST = ['minisite_edit_nonce' => 'test-nonce', 'business_name' => 'Test Business'];

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('minisite_edit', 'minisite_edit_nonce')
            ->willReturn(true);

        $result = (object) [
            'success' => true,
            'redirectUrl' => 'http://example.com/account/sites/test-id',
            'errors' => [],
        ];

        $this->newMinisiteService
            ->expects($this->once())
            ->method('createNewMinisite')
            ->willReturn($result);

        $this->wordPressManager
            ->expects($this->once())
            ->method('redirect')
            ->with('http://example.com/account/sites/test-id');

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleFormSubmission renders error when nonce is invalid
     */
    public function test_handle_form_submission_invalid_nonce_renders_error(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->method('canCreateNewMinisite')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('isPostRequest')
            ->willReturn(true);

        $_POST = ['minisite_edit_nonce' => 'invalid-nonce'];

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('verifyNonce')
            ->with('minisite_edit', 'minisite_edit_nonce')
            ->willReturn(false);

        $this->formSecurityHelper
            ->expects($this->once())
            ->method('getPostData')
            ->with('minisite_edit_nonce', 'MISSING')
            ->willReturn('invalid-nonce');

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderError')
            ->with('Security check failed. Please try again.');

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleFormSubmission displays form with errors when validation fails
     */
    public function test_handle_form_submission_validation_errors_displays_form(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->method('canCreateNewMinisite')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('isPostRequest')
            ->willReturn(true);

        $_POST = ['minisite_edit_nonce' => 'test-nonce', 'business_name' => ''];

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $result = (object) [
            'success' => false,
            'errors' => ['Business name is required'],
            'redirectUrl' => null,
        ];

        $this->newMinisiteService
            ->expects($this->once())
            ->method('createNewMinisite')
            ->willReturn($result);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getEmptyFormData')
            ->willReturn([]);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getUserMinisiteCount')
            ->willReturn(0);

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderNewMinisiteForm')
            ->with($this->callback(function ($data) {
                return is_object($data) &&
                    $data->errorMessage === 'Business name is required';
            }));

        $this->controller->handleNewMinisite();
    }

    /**
     * Test handleFormSubmission handles service exception gracefully
     */
    public function test_handle_form_submission_service_exception_handles_gracefully(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->newMinisiteService
            ->method('canCreateNewMinisite')
            ->willReturn(true);

        $this->formSecurityHelper
            ->method('isPostRequest')
            ->willReturn(true);

        $_POST = ['minisite_edit_nonce' => 'test-nonce'];

        $this->formSecurityHelper
            ->method('verifyNonce')
            ->willReturn(true);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('createNewMinisite')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getEmptyFormData')
            ->willReturn([]);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getUserMinisiteCount')
            ->willReturn(0);

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderNewMinisiteForm')
            ->with($this->callback(function ($data) {
                return is_object($data) &&
                    str_contains($data->errorMessage, 'Database connection failed');
            }));

        $this->controller->handleNewMinisite();
    }

    /**
     * Test displayNewMinisiteForm calls renderer
     */
    public function test_display_new_minisite_form_calls_renderer(): void
    {
        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('displayNewMinisiteForm');
        $method->setAccessible(true);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getEmptyFormData')
            ->willReturn(['business' => ['name' => '']]);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getUserMinisiteCount')
            ->willReturn(5);

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderNewMinisiteForm')
            ->with($this->callback(function ($data) {
                return is_object($data) &&
                    $data->userMinisiteCount === 5;
            }));

        $method->invoke($this->controller);
    }

    /**
     * Test displayNewMinisiteForm renders error on exception
     */
    public function test_display_new_minisite_form_exception_renders_error(): void
    {
        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('displayNewMinisiteForm');
        $method->setAccessible(true);

        $this->newMinisiteService
            ->expects($this->once())
            ->method('getEmptyFormData')
            ->willThrowException(new \RuntimeException('Service unavailable'));

        $this->newMinisiteRenderer
            ->expects($this->once())
            ->method('renderError')
            ->with('Service unavailable');

        $method->invoke($this->controller);
    }

    /**
     * Setup WordPress function mocks for this test class
     */
    private function setupWordPressMocks(): void
    {
        $functions = ['sanitize_text_field', 'wp_unslash'];

        foreach ($functions as $function) {
            if (! function_exists($function)) {
                eval("
                    function {$function}(\$value) {
                        return \$value;
                    }
                ");
            }
        }

        // Mock $_SERVER if not set
        if (! isset($_SERVER['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
        if (! isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/account/sites/new';
        }
        if (! isset($_SERVER['HTTP_USER_AGENT'])) {
            $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
        }
    }

    /**
     * Clear WordPress function mocks
     */
    private function clearWordPressMocks(): void
    {
        // Clean up $_POST
        unset($_POST);
        $_POST = [];
    }
}

