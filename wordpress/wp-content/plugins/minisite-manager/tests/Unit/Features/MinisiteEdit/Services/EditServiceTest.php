<?php

namespace Minisite\Tests\Unit\Features\MinisiteEdit\Services;

use Minisite\Features\MinisiteEdit\Services\EditService;
use Minisite\Features\MinisiteEdit\WordPress\WordPressEditManager;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\Entities\Version;
use PHPUnit\Framework\TestCase;
use Brain\Monkey\Functions;

/**
 * Test EditService
 */
class EditServiceTest extends TestCase
{
    private EditService $service;
    private $mockWordPressManager;

    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->mockWordPressManager = $this->createMock(WordPressEditManager::class);
        $this->service = new EditService($this->mockWordPressManager);
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetMinisiteForEditingSuccess(): void
    {
        $siteId = '123';
        $versionId = 'latest';
        $currentUser = (object) ['ID' => 1];
        $minisite = (object) [
            'id' => $siteId,
            'createdBy' => 1,
            'name' => 'Test Minisite',
            'title' => 'Test Title',
            'city' => 'Test City',
            'region' => 'Test Region',
            'countryCode' => 'US',
            'postalCode' => '12345',
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'searchTerms' => 'test search',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'slugs' => new \Minisite\Domain\ValueObjects\SlugPair('test', 'city'),
            'siteJson' => null
        ];
        $editingVersion = (object) [
            'siteJson' => ['test' => 'data'],
            'title' => 'Updated Title',
            'name' => 'Updated Name'
        ];
        $latestDraft = (object) ['id' => 1, 'status' => 'draft'];

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $this->mockWordPressManager->expects($this->once())
            ->method('userOwnsMinisite')
            ->with($minisite, 1)
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('getLatestDraftForEditing')
            ->with($siteId)
            ->willReturn($editingVersion);

        $this->mockWordPressManager->expects($this->once())
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

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->service->getMinisiteForEditing($siteId);
    }

    public function testGetMinisiteForEditingAccessDenied(): void
    {
        $siteId = '123';
        $currentUser = (object) ['ID' => 1];
        $minisite = (object) [
            'id' => $siteId,
            'createdBy' => 2, // Different user
            'name' => 'Test Minisite'
        ];

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
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
        $formData = [
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
            'seo_title' => 'Test Title',
            'version_label' => 'Test Version',
            'version_comment' => 'Test Comment',
            'contact_lat' => '40.7128',
            'contact_lng' => '-74.0060'
        ];
        $currentUser = (object) ['ID' => 1];
        $minisite = (object) [
            'id' => $siteId,
            'createdBy' => 1,
            'name' => 'Original Name',
            'title' => 'Original Title',
            'city' => 'Original City',
            'region' => 'Original Region',
            'countryCode' => 'US',
            'postalCode' => '12345',
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'searchTerms' => 'original search',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'slugs' => new \Minisite\Domain\ValueObjects\SlugPair('test', 'city'),
            'siteJson' => null
        ];
        $savedVersion = (object) ['id' => 1, 'versionNumber' => 1];

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $this->mockWordPressManager->expects($this->once())
            ->method('startTransaction');

        $this->mockWordPressManager->expects($this->once())
            ->method('getNextVersionNumber')
            ->with($siteId)
            ->willReturn(1);

        $this->mockWordPressManager->expects($this->once())
            ->method('saveVersion')
            ->willReturn($savedVersion);

        $this->mockWordPressManager->expects($this->once())
            ->method('hasBeenPublished')
            ->with($siteId)
            ->willReturn(false);

        $this->mockWordPressManager->expects($this->once())
            ->method('updateMinisiteFields');

        $this->mockWordPressManager->expects($this->once())
            ->method('commitTransaction');

        $this->mockWordPressManager->method('rollbackTransaction');

        $this->mockWordPressManager->expects($this->once())
            ->method('getHomeUrl')
            ->with("/account/sites/{$siteId}/edit?draft_saved=1")
            ->willReturn("http://example.com/account/sites/{$siteId}/edit?draft_saved=1");

        // Mock sanitization methods
        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);
        $this->mockWordPressManager->method('sanitizeTextareaField')->willReturnArgument(0);

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('draft_saved=1', $result->redirectUrl);
    }

    public function testSaveDraftValidationError(): void
    {
        $siteId = '123';
        $formData = [
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => '', // Empty name should cause validation error
            'business_city' => 'Test City'
        ];

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
        $formData = [
            'minisite_edit_nonce' => 'invalid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City'
        ];

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
        $formData = [
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City',
            'seo_title' => 'Test Title'
        ];
        $currentUser = (object) ['ID' => 1];
        $minisite = (object) [
            'id' => $siteId,
            'createdBy' => 1,
            'name' => 'Original Name',
            'title' => 'Original Title',
            'city' => 'Original City',
            'region' => 'Original Region',
            'countryCode' => 'US',
            'postalCode' => '12345',
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'searchTerms' => 'original search',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'slugs' => new \Minisite\Domain\ValueObjects\SlugPair('test', 'city'),
            'siteJson' => null
        ];
        $savedVersion = (object) ['id' => 1, 'versionNumber' => 1];

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->mockWordPressManager->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $this->mockWordPressManager->expects($this->once())
            ->method('startTransaction');

        $this->mockWordPressManager->expects($this->once())
            ->method('getNextVersionNumber')
            ->with($siteId)
            ->willReturn(1);

        $this->mockWordPressManager->expects($this->once())
            ->method('saveVersion')
            ->willReturn($savedVersion);

        $this->mockWordPressManager->expects($this->once())
            ->method('hasBeenPublished')
            ->with($siteId)
            ->willReturn(true); // Minisite has been published

        // Should NOT call update methods for published minisites
        $this->mockWordPressManager->expects($this->never())
            ->method('updateBusinessInfo');

        $this->mockWordPressManager->expects($this->never())
            ->method('updateCoordinates');

        $this->mockWordPressManager->expects($this->never())
            ->method('updateTitle');

        $this->mockWordPressManager->expects($this->once())
            ->method('getHomeUrl')
            ->with("/account/sites/{$siteId}/edit?draft_saved=1")
            ->willReturn("http://example.com/account/sites/{$siteId}/edit?draft_saved=1");

        // Mock sanitization methods
        $this->mockWordPressManager->method('sanitizeTextField')->willReturnArgument(0);
        $this->mockWordPressManager->method('sanitizeTextareaField')->willReturnArgument(0);

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertTrue($result->success);
    }

    public function testSaveDraftExceptionHandling(): void
    {
        $siteId = '123';
        $formData = [
            'minisite_edit_nonce' => 'valid_nonce',
            'business_name' => 'Test Business',
            'business_city' => 'Test City'
        ];

        $this->mockWordPressManager->expects($this->once())
            ->method('verifyNonce')
            ->with($this->anything(), 'minisite_edit')
            ->willReturn(true);

        $this->mockWordPressManager->expects($this->once())
            ->method('findMinisiteById')
            ->with($siteId)
            ->willThrowException(new \Exception('Database error'));

        $result = $this->service->saveDraft($siteId, $formData);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to save draft: Database error', $result->errors[0]);
    }
}
