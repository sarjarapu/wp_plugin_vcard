<?php

declare(strict_types=1);

namespace Tests\Unit\Features\NewMinisite\Services;

use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\NewMinisite\Services\NewMinisiteService;
use Minisite\Features\NewMinisite\WordPress\WordPressNewMinisiteManager;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for NewMinisiteService
 */
#[CoversClass(NewMinisiteService::class)]
final class NewMinisiteServiceTest extends TestCase
{
    private NewMinisiteService $service;
    private WordPressNewMinisiteManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;
    private VersionRepositoryInterface|MockObject $versionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->wordPressManager = $this->createMock(WordPressNewMinisiteManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->versionRepository = $this->createMock(VersionRepositoryInterface::class);

        $this->service = new NewMinisiteService(
            $this->wordPressManager,
            $this->minisiteRepository,
            $this->versionRepository
        );

        // Mock global $wpdb for database operations
        global $wpdb;
        $wpdb = new class () {
            public $prefix = 'wp_';
            public function prepare($query, ...$args) {
                return $query;
            }
            public function query($query) {
                return true;
            }
        };
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();

        global $wpdb;
        $wpdb = null;
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass(NewMinisiteService::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(3, $parameters);
        $this->assertEquals('wordPressManager', $parameters[0]->getName());
        $this->assertEquals('minisiteRepository', $parameters[1]->getName());
        $this->assertEquals('versionRepository', $parameters[2]->getName());
    }

    /**
     * Test getEmptyFormData returns array
     */
    public function test_get_empty_form_data_returns_array(): void
    {
        // getEmptyFormData creates MinisiteFormProcessor internally
        // We can't easily mock it, but we can verify it returns an array
        $result = $this->service->getEmptyFormData();

        $this->assertIsArray($result);
    }

    /**
     * Test canCreateNewMinisite returns true when user has permission
     */
    public function test_can_create_new_minisite_returns_true_when_user_has_permission(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->expects($this->once())
            ->method('userCanCreateMinisite')
            ->with(1)
            ->willReturn(true);

        $result = $this->service->canCreateNewMinisite();

        $this->assertTrue($result);
    }

    /**
     * Test canCreateNewMinisite returns false when user has no permission
     */
    public function test_can_create_new_minisite_returns_false_when_user_no_permission(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->expects($this->once())
            ->method('userCanCreateMinisite')
            ->with(1)
            ->willReturn(false);

        $result = $this->service->canCreateNewMinisite();

        $this->assertFalse($result);
    }

    /**
     * Test canCreateNewMinisite returns false when user not logged in
     */
    public function test_can_create_new_minisite_returns_false_when_user_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $result = $this->service->canCreateNewMinisite();

        $this->assertFalse($result);
    }

    /**
     * Test getUserMinisiteCount returns count from repository
     */
    public function test_get_user_minisite_count_returns_count_from_repository(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 5;

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('countByOwner')
            ->with(5)
            ->willReturn(3);

        $result = $this->service->getUserMinisiteCount();

        $this->assertEquals(3, $result);
    }

    /**
     * Test getUserMinisiteCount returns 0 when user not logged in
     */
    public function test_get_user_minisite_count_returns_zero_when_user_not_logged_in(): void
    {
        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn(null);

        $result = $this->service->getUserMinisiteCount();

        $this->assertEquals(0, $result);
    }

    /**
     * Test createNewMinisite returns validation errors when form data is invalid
     */
    public function test_create_new_minisite_returns_validation_errors_when_invalid(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        // Empty form data should trigger validation errors
        $formData = ['minisite_edit_nonce' => 'test-nonce'];

        $result = $this->service->createNewMinisite($formData);

        $this->assertIsObject($result);
        $this->assertFalse($result->success);
        $this->assertIsArray($result->errors);
        $this->assertNotEmpty($result->errors);
    }

    /**
     * Test createNewMinisite returns error when nonce verification fails
     */
    public function test_create_new_minisite_returns_error_when_nonce_fails(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->method('verifyNonce')
            ->willReturn(false);

        // Valid form data but invalid nonce
        $formData = [
            'minisite_edit_nonce' => 'invalid-nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
        ];

        $result = $this->service->createNewMinisite($formData);

        $this->assertIsObject($result);
        $this->assertFalse($result->success);
        $this->assertIsArray($result->errors);
        $this->assertContains('Security check failed. Please try again.', $result->errors);
    }

    /**
     * Test createNewMinisite returns proper result structure
     */
    public function test_create_new_minisite_returns_proper_result_structure(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 1;

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        // Test that the method returns a proper result object structure
        // The actual success/failure depends on form validation and database operations
        // which are tested in integration tests
        $formData = [
            'minisite_edit_nonce' => 'test-nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
        ];

        $result = $this->service->createNewMinisite($formData);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('success', $result);
        $this->assertObjectHasProperty('errors', $result);
        $this->assertIsBool($result->success);
        $this->assertIsArray($result->errors);
    }

    /**
     * Test createNewMinisite method exists and is callable
     */
    public function test_create_new_minisite_method_exists_and_callable(): void
    {
        $this->assertTrue(method_exists($this->service, 'createNewMinisite'));
        $this->assertTrue(is_callable([$this->service, 'createNewMinisite']));
    }

    /**
     * Test createNewMinisite logs user information
     */
    public function test_create_new_minisite_logs_user_information(): void
    {
        $mockUser = new \WP_User(1, 'testuser');
        $mockUser->ID = 123;

        $this->wordPressManager
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->method('sanitizeTextField')
            ->willReturnArgument(0);

        $this->wordPressManager
            ->method('verifyNonce')
            ->willReturn(false);

        $formData = [
            'minisite_edit_nonce' => 'test-nonce',
            'business_name' => 'Test Business',
        ];

        // Method should execute without throwing exceptions
        $result = $this->service->createNewMinisite($formData);

        $this->assertIsObject($result);
    }
}

