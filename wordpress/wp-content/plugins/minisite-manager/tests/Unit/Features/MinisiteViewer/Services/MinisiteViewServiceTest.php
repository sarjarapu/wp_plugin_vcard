<?php

namespace Tests\Unit\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteViewService
 * 
 * Tests the MinisiteViewService with mocked WordPress functions
 * 
 */
final class MinisiteViewServiceTest extends TestCase
{
    private MinisiteViewService $viewService;
    private WordPressMinisiteManager|MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->wordPressManager = $this->createMock(WordPressMinisiteManager::class);
        $this->viewService = new MinisiteViewService($this->wordPressManager);
    }

    /**
     * Test getMinisiteForView with valid minisite
     */
    public function test_get_minisite_for_display_with_valid_minisite(): void
    {
        $command = new ViewMinisiteCommand('coffee-shop', 'downtown');
        $mockMinisite = (object)[
            'id' => '123',
            'name' => 'Coffee Shop',
            'business_slug' => 'coffee-shop',
            'location_slug' => 'downtown'
        ];

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('coffee-shop', 'downtown')
            ->willReturn($mockMinisite);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisite, $result['minisite']);
        $this->assertArrayNotHasKey('error', $result);
    }

    /**
     * Test getMinisiteForView with minisite not found
     */
    public function test_get_minisite_for_display_with_minisite_not_found(): void
    {
        $command = new ViewMinisiteCommand('nonexistent', 'location');

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('nonexistent', 'location')
            ->willReturn(null);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('Minisite not found', $result['error']);
        $this->assertArrayNotHasKey('minisite', $result);
    }

    /**
     * Test getMinisiteForView with database exception
     */
    public function test_get_minisite_for_display_with_database_exception(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('business', 'location')
            ->willThrowException(new \Exception('Database connection failed'));

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Error retrieving minisite: Database connection failed', $result['error']);
        $this->assertArrayNotHasKey('minisite', $result);
    }

    /**
     * Test getMinisiteForView with empty slugs
     */
    public function test_get_minisite_for_display_with_empty_slugs(): void
    {
        $command = new ViewMinisiteCommand('', '');

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('', '')
            ->willReturn(null);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('Minisite not found', $result['error']);
    }

    /**
     * Test getMinisiteForView with special characters
     */
    public function test_get_minisite_for_display_with_special_characters(): void
    {
        $command = new ViewMinisiteCommand('café-&-restaurant', 'main-street-123');
        $mockMinisite = (object)[
            'id' => '456',
            'name' => 'Café & Restaurant',
            'business_slug' => 'café-&-restaurant',
            'location_slug' => 'main-street-123'
        ];

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('café-&-restaurant', 'main-street-123')
            ->willReturn($mockMinisite);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertTrue($result['success']);
        $this->assertEquals($mockMinisite, $result['minisite']);
    }

    /**
     * Test minisiteExists with existing minisite
     */
    public function test_minisite_exists_with_existing_minisite(): void
    {
        $command = new ViewMinisiteCommand('coffee-shop', 'downtown');

        $this->wordPressManager
            ->expects($this->once())
            ->method('minisiteExists')
            ->with('coffee-shop', 'downtown')
            ->willReturn(true);

        $result = $this->viewService->minisiteExists($command);

        $this->assertTrue($result);
    }

    /**
     * Test minisiteExists with non-existing minisite
     */
    public function test_minisite_exists_with_non_existing_minisite(): void
    {
        $command = new ViewMinisiteCommand('nonexistent', 'location');

        $this->wordPressManager
            ->expects($this->once())
            ->method('minisiteExists')
            ->with('nonexistent', 'location')
            ->willReturn(false);

        $result = $this->viewService->minisiteExists($command);

        $this->assertFalse($result);
    }

    /**
     * Test minisiteExists with empty slugs
     */
    public function test_minisite_exists_with_empty_slugs(): void
    {
        $command = new ViewMinisiteCommand('', '');

        $this->wordPressManager
            ->expects($this->once())
            ->method('minisiteExists')
            ->with('', '')
            ->willReturn(false);

        $result = $this->viewService->minisiteExists($command);

        $this->assertFalse($result);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->viewService);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals(WordPressMinisiteManager::class, $params[0]->getType()->getName());
    }

    /**
     * Test getMinisiteForView with null minisite object
     */
    public function test_get_minisite_for_display_with_null_minisite_object(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('business', 'location')
            ->willReturn(null);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('Minisite not found', $result['error']);
    }

    /**
     * Test getMinisiteForView with invalid minisite object
     */
    public function test_get_minisite_for_display_with_invalid_minisite_object(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteBySlugs')
            ->with('business', 'location')
            ->willReturn(null);

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertEquals('Minisite not found', $result['error']);
    }
}
