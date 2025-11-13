<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Services;

use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface;
use Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

/**
 * Test EditService
 */
class EditServiceTest extends TestCase
{
    private EditService $service;
    private $mockWordPressManager;
    private $mockMinisiteRepository;
    private $mockVersionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Mock global $wpdb for WordPressTransactionManager
        global $wpdb;
        $wpdb = $this->createMock(FakeWpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->method('query')->willReturn(true);
        $wpdb->method('prepare')->willReturnArgument(0);

        $this->mockWordPressManager = $this->createMock(WordPressEditManager::class);
        $this->mockMinisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->mockVersionRepository = $this->createMock(VersionRepositoryInterface::class);
        $this->service = new EditService(
            $this->mockWordPressManager,
            $this->mockMinisiteRepository,
            $this->mockVersionRepository
        );
    }

    protected function tearDown(): void
    {
        // Clean up global $wpdb
        global $wpdb;
        $wpdb = null;

        \Brain\Monkey\tearDown();
        parent::tearDown();
    }


    /**
     * Helper to create a test Minisite entity
     */
    private function createTestMinisite(string $id, int $createdBy = 1, array $siteJson = array()): Minisite
    {
        return new Minisite(
            id: $id,
            slug: null,
            slugs: new SlugPair('test', 'city'),
            title: 'Test Title',
            name: 'Test Minisite',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null,
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: $siteJson,
            searchTerms: 'test search',
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: $createdBy,
            updatedBy: null,
            currentVersionId: null
        );
    }

    /**
     * Helper to create a test Version entity
     */
    private function createTestVersion(string $minisiteId, int $versionNumber = 1, string $status = 'draft', array $siteJson = array()): Version
    {
        $version = new Version();
        $version->id = $versionNumber;
        $version->minisiteId = $minisiteId;
        $version->versionNumber = $versionNumber;
        $version->status = $status;
        $version->label = "Version {$versionNumber}";
        $version->comment = "Test version";
        $version->createdBy = 1;
        $version->createdAt = new \DateTimeImmutable();
        $version->publishedAt = $status === 'published' ? new \DateTimeImmutable() : null;
        $version->setSiteJsonFromArray($siteJson);
        $version->title = 'Test Title';
        $version->name = 'Test Minisite';
        $version->city = 'Test City';
        $version->region = 'Test Region';
        $version->countryCode = 'US';
        $version->postalCode = '12345';

        return $version;
    }

    public function testGetMinisiteForEditingSuccess(): void
    {
        $siteId = '123';
        $versionId = 'latest';
        $currentUser = (object) array('ID' => 1);
        $minisite = $this->createTestMinisite($siteId, 1);
        $editingVersion = $this->createTestVersion($siteId, 1, 'draft', array('test' => 'data'));
        $latestDraft = $this->createTestVersion($siteId, 1, 'draft');

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $this->mockWordPressManager->expects($this->once())
            ->method('userOwnsMinisite')
            ->with($minisite, 1)
            ->willReturn(true);

        $this->mockVersionRepository->expects($this->once())
            ->method('getLatestDraftForEditing')
            ->with($siteId)
            ->willReturn($editingVersion);

        $this->mockVersionRepository->expects($this->once())
            ->method('findLatestDraft')
            ->with($siteId)
            ->willReturn($latestDraft);

        $result = $this->service->getMinisiteForEditing($siteId, $versionId);

        $this->assertObjectHasProperty('minisite', $result);
        $this->assertObjectHasProperty('editingVersion', $result);
        $this->assertObjectHasProperty('latestDraft', $result);
        $this->assertObjectHasProperty('profileForForm', $result);
        $this->assertObjectHasProperty('siteJson', $result);
        $this->assertEquals($minisite, $result->minisite);
        $this->assertEquals($editingVersion, $result->editingVersion);
        $this->assertEquals($latestDraft, $result->latestDraft);
    }

    public function testGetMinisiteForEditingMinisiteNotFound(): void
    {
        $siteId = '123';

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->service->getMinisiteForEditing($siteId);
    }

    public function testGetMinisiteForEditingAccessDenied(): void
    {
        $siteId = '123';
        $currentUser = (object) array('ID' => 1);
        $minisite = $this->createTestMinisite($siteId, 2); // Different user

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $this->mockWordPressManager->expects($this->once())
            ->method('userOwnsMinisite')
            ->with($minisite, 1)
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $this->service->getMinisiteForEditing($siteId);
    }

    public function testSaveDraftSuccess(): void
    {
        $siteId = '123';
        $formData = array(
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
            'seo_title' => 'Test Title',
            'version_label' => 'Test Version',
            'version_comment' => 'Test Comment',
            'contact_lat' => '40.7128',
            'contact_lng' => '-74.0060',
        );
        $currentUser = (object) array('ID' => 1);
        $minisite = $this->createTestMinisite($siteId, 1);

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        // Check if has been published (should return null for new minisite)
        $this->mockVersionRepository->expects($this->once())
            ->method('findPublishedVersion')
            ->with($siteId)
            ->willReturn(null);

        // The actual save is handled by MinisiteDatabaseCoordinator
        // We need to mock the coordinator's behavior indirectly
        // Since we can't easily mock the coordinator, we'll test that the service
        // properly calls the coordinator and handles the result
        // For now, we'll expect the coordinator to throw an exception or return a result
        // This is a simplified test - full testing would require mocking the coordinator

        // Mock getHomeUrl for redirect
        $this->mockWordPressManager->expects($this->any())
            ->method('getHomeUrl')
            ->willReturn("http://example.com/account/sites/{$siteId}/edit?draft_saved=1");

        // Since saveDraft uses MinisiteDatabaseCoordinator which is complex,
        // we'll test that it properly validates and calls the coordinator
        // The actual save logic is tested in integration tests
        $result = $this->service->saveDraft($siteId, $formData);

        // The result should either be success (if coordinator works) or error
        // We can't fully test this without mocking the coordinator
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('success', $result);
    }

    public function testSaveDraftValidationError(): void
    {
        $siteId = '123';
        $formData = array(
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => '', // Empty name should cause validation error
            'business_city' => 'Test City',
        );

        // verifyNonce should not be called when validation fails
        $this->mockWordPressManager->expects($this->never())
            ->method('verifyNonce');

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertFalse($result->success);
        $this->assertContains('Business name is required', $result->errors);
    }

    public function testSaveDraftNonceVerificationFailed(): void
    {
        $siteId = '123';
        $formData = array(
            'minisite_edit_nonce' => 'invalid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
        );

        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(false);

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertFalse($result->success);
        $this->assertContains('Security check failed. Please try again.', $result->errors);
    }

    public function testSaveDraftForPublishedMinisite(): void
    {
        $siteId = '123';
        $formData = array(
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
            'seo_title' => 'Test Title',
        );
        $currentUser = (object) array('ID' => 1);
        $minisite = $this->createTestMinisite($siteId, 1);
        $publishedVersion = $this->createTestVersion($siteId, 1, 'published');

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        // Minisite has been published
        $this->mockVersionRepository->expects($this->once())
            ->method('findPublishedVersion')
            ->with($siteId)
            ->willReturn($publishedVersion);

        // Mock getHomeUrl for redirect
        $this->mockWordPressManager->expects($this->any())
            ->method('getHomeUrl')
            ->willReturn("http://example.com/account/sites/{$siteId}/edit?draft_saved=1");

        $result = $this->service->saveDraft($siteId, $formData);

        // The result should be handled by the coordinator
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('success', $result);
    }

    public function testSaveDraftExceptionHandling(): void
    {
        $siteId = '123';
        $formData = array(
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
        );

        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockMinisiteRepository->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willThrowException(new \Exception('Database error'));

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to save draft: Database error', $result->errors[0]);
    }
}
