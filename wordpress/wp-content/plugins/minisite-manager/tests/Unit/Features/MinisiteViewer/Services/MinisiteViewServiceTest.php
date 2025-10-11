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

    // ===== VERSION-SPECIFIC PREVIEW TESTS =====

    /**
     * Test getMinisiteForVersionSpecificPreview with valid minisite and current version
     */
    public function test_get_minisite_for_version_specific_preview_with_current_version(): void
    {
        $siteId = '123';
        $versionId = 'current';
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1,
            'siteJson' => ['test' => 'current data']
        ];
        $mockUser = (object)['ID' => 1];

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $result = $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);

        $this->assertIsObject($result);
        $this->assertEquals($mockMinisite, $result->minisite);
        $this->assertNull($result->version);
        $this->assertEquals(['test' => 'current data'], $result->siteJson);
        $this->assertEquals('current', $result->versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with specific version
     */
    public function test_get_minisite_for_version_specific_preview_with_specific_version(): void
    {
        $siteId = '123';
        $versionId = '5';
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1,
            'siteJson' => ['test' => 'current data']
        ];
        $mockVersion = $this->createMock(\Minisite\Domain\Entities\Version::class);
        $mockVersion->id = 5;
        $mockVersion->minisiteId = $siteId;
        $mockVersion->name = 'Version 5';
        $mockVersion->city = 'Version City';
        $mockVersion->title = 'Version Title';
        $mockVersion->label = 'Version 5';
        $mockVersion->siteJson = ['test' => 'version data'];
        $mockUser = (object)['ID' => 1];
        $mockVersionRepo = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepository::class);

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getVersionRepository')
            ->willReturn($mockVersionRepo);

        $mockVersionRepo
            ->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($mockVersion);

        $result = $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);

        $this->assertIsObject($result);
        $this->assertEquals($mockMinisite, $result->minisite);
        $this->assertEquals($mockVersion, $result->version);
        $this->assertEquals(['test' => 'version data'], $result->siteJson);
        $this->assertEquals('5', $result->versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with minisite not found
     */
    public function test_get_minisite_for_version_specific_preview_with_minisite_not_found(): void
    {
        $siteId = 'nonexistent';
        $versionId = 'current';

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with access denied
     */
    public function test_get_minisite_for_version_specific_preview_with_access_denied(): void
    {
        $siteId = '123';
        $versionId = 'current';
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1 // Different user
        ];
        $mockUser = (object)['ID' => 2]; // Different user

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with version not found
     */
    public function test_get_minisite_for_version_specific_preview_with_version_not_found(): void
    {
        $siteId = '123';
        $versionId = '999';
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1,
            'siteJson' => ['test' => 'current data']
        ];
        $mockUser = (object)['ID' => 1];
        $mockVersionRepo = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepository::class);

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getVersionRepository')
            ->willReturn($mockVersionRepo);

        $mockVersionRepo
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Version not found');

        $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with version from different minisite
     */
    public function test_get_minisite_for_version_specific_preview_with_wrong_minisite_version(): void
    {
        $siteId = '123';
        $versionId = '5';
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1,
            'siteJson' => ['test' => 'current data']
        ];
        $mockVersion = $this->createMock(\Minisite\Domain\Entities\Version::class);
        $mockVersion->id = 5;
        $mockVersion->minisiteId = 'different-site-id'; // Different minisite
        $mockVersion->name = 'Version 5';
        $mockVersion->siteJson = ['test' => 'version data'];
        $mockUser = (object)['ID' => 1];
        $mockVersionRepo = $this->createMock(\Minisite\Infrastructure\Persistence\Repositories\VersionRepository::class);

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getVersionRepository')
            ->willReturn($mockVersionRepo);

        $mockVersionRepo
            ->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($mockVersion);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Version not found');

        $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);
    }

    /**
     * Test getMinisiteForVersionSpecificPreview with null version ID (should use current)
     */
    public function test_get_minisite_for_version_specific_preview_with_null_version_id(): void
    {
        $siteId = '123';
        $versionId = null;
        $mockMinisite = (object)[
            'id' => $siteId,
            'name' => 'Test Minisite',
            'createdBy' => 1,
            'siteJson' => ['test' => 'current data']
        ];
        $mockUser = (object)['ID' => 1];

        $this->wordPressManager
            ->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $result = $this->viewService->getMinisiteForVersionSpecificPreview($siteId, $versionId);

        $this->assertIsObject($result);
        $this->assertEquals($mockMinisite, $result->minisite);
        $this->assertNull($result->version);
        $this->assertEquals(['test' => 'current data'], $result->siteJson);
        $this->assertNull($result->versionId);
    }
}
