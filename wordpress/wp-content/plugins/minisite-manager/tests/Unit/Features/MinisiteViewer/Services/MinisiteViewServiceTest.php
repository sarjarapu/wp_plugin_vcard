<?php

namespace Tests\Unit\Features\MinisiteViewer\Services;

use Minisite\Features\MinisiteManagement\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteViewer\Commands\ViewMinisiteCommand;
use Minisite\Features\MinisiteViewer\Services\MinisiteViewService;
use Minisite\Features\MinisiteViewer\WordPress\WordPressMinisiteManager;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test MinisiteViewService
 *
 * Tests the MinisiteViewService with mocked repositories and WordPress functions
 *
 */
final class MinisiteViewServiceTest extends TestCase
{
    private MinisiteViewService $viewService;
    private WordPressMinisiteManager|MockObject $wordPressManager;
    private MinisiteRepositoryInterface|MockObject $minisiteRepository;
    private VersionRepositoryInterface|MockObject $versionRepository;

    protected function setUp(): void
    {
        $this->wordPressManager = $this->createMock(WordPressMinisiteManager::class);
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->versionRepository = $this->createMock(VersionRepositoryInterface::class);
        $this->viewService = new MinisiteViewService(
            $this->wordPressManager,
            $this->minisiteRepository,
            $this->versionRepository
        );
    }

    /**
     * Helper to create a mock Minisite entity
     */
    private function createMockMinisite(string $id, string $name, string $businessSlug, string $locationSlug, int $createdBy = 1, array $siteJson = []): Minisite
    {
        return new Minisite(
            id: $id,
            slug: null,
            slugs: new SlugPair($businessSlug, $locationSlug),
            title: $name,
            name: $name,
            city: 'Test City',
            region: null,
            countryCode: 'US',
            postalCode: null,
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: $siteJson,
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: $createdBy,
            updatedBy: null,
            currentVersionId: null
        );
    }

    /**
     * Test getMinisiteForView with valid minisite
     */
    public function test_get_minisite_for_display_with_valid_minisite(): void
    {
        $command = new ViewMinisiteCommand('coffee-shop', 'downtown');
        $mockMinisite = $this->createMockMinisite('123', 'Coffee Shop', 'coffee-shop', 'downtown');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
            ->with($this->callback(function ($slugPair) {
                return $slugPair instanceof SlugPair
                    && $slugPair->business === 'coffee-shop'
                    && $slugPair->location === 'downtown';
            }))
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

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
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

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
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

        // SlugPair validation happens before repository is called
        $this->minisiteRepository
            ->expects($this->never())
            ->method('findBySlugs');

        $result = $this->viewService->getMinisiteForView($command);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Business slug must be a non-empty string', $result['error']);
    }

    /**
     * Test getMinisiteForView with special characters
     */
    public function test_get_minisite_for_display_with_special_characters(): void
    {
        $command = new ViewMinisiteCommand('café-&-restaurant', 'main-street-123');
        $mockMinisite = $this->createMockMinisite('456', 'Café & Restaurant', 'café-&-restaurant', 'main-street-123');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
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
        $mockMinisite = $this->createMockMinisite('123', 'Coffee Shop', 'coffee-shop', 'downtown');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
            ->willReturn($mockMinisite);

        $result = $this->viewService->minisiteExists($command);

        $this->assertTrue($result);
    }

    /**
     * Test minisiteExists with non-existing minisite
     */
    public function test_minisite_exists_with_non_existing_minisite(): void
    {
        $command = new ViewMinisiteCommand('nonexistent', 'location');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
            ->willReturn(null);

        $result = $this->viewService->minisiteExists($command);

        $this->assertFalse($result);
    }

    /**
     * Test minisiteExists with empty slugs
     */
    public function test_minisite_exists_with_empty_slugs(): void
    {
        $command = new ViewMinisiteCommand('', '');

        // SlugPair validation happens before repository is called
        $this->minisiteRepository
            ->expects($this->never())
            ->method('findBySlugs');

        // Expect exception to be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business slug must be a non-empty string');

        $this->viewService->minisiteExists($command);
    }

    /**
     * Test constructor dependency injection
     */
    public function test_constructor_dependency_injection(): void
    {
        $reflection = new \ReflectionClass($this->viewService);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(3, $constructor->getNumberOfParameters());

        $params = $constructor->getParameters();
        $this->assertEquals(WordPressMinisiteManager::class, $params[0]->getType()->getName());
        $this->assertEquals(MinisiteRepositoryInterface::class, $params[1]->getType()->getName());
        $this->assertEquals(VersionRepositoryInterface::class, $params[2]->getType()->getName());
    }

    /**
     * Test getMinisiteForView with null minisite object
     */
    public function test_get_minisite_for_display_with_null_minisite_object(): void
    {
        $command = new ViewMinisiteCommand('business', 'location');

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
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

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findBySlugs')
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1, ['test' => 'current data']);
        $mockUser = (object)['ID' => 1];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1);

        $mockVersion = $this->createMock(Version::class);
        $mockVersion->id = 5;
        $mockVersion->minisiteId = $siteId;
        $mockVersion->name = 'Version 5';
        $mockVersion->city = 'Version City';
        $mockVersion->title = 'Version Title';
        $mockVersion->siteJson = json_encode(['test' => 'version data']);

        $mockUser = (object)['ID' => 1];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->versionRepository
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

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1);
        $mockUser = (object)['ID' => 2]; // Different user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1, ['test' => 'current data']);
        $mockUser = (object)['ID' => 1];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->versionRepository
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1);

        $mockVersion = $this->createMock(Version::class);
        $mockVersion->id = 5;
        $mockVersion->minisiteId = 'different-site-id'; // Different minisite
        $mockVersion->siteJson = json_encode(['test' => 'version data']);

        $mockUser = (object)['ID' => 1];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($mockMinisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($mockUser);

        $this->versionRepository
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
        $mockMinisite = $this->createMockMinisite($siteId, 'Test Minisite', 'test', 'location', 1, ['test' => 'current data']);
        $mockUser = (object)['ID' => 1];

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
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
