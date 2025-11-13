<?php

namespace Minisite\Features\VersionManagement\Services;

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepositoryInterface;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for VersionService
 */
class VersionServiceTest extends TestCase
{
    private VersionService $versionService;
    private MockObject $minisiteRepository;
    private MockObject $versionRepository;
    private MockObject $wordPressManager;

    protected function setUp(): void
    {
        $this->minisiteRepository = $this->createMock(MinisiteRepositoryInterface::class);
        $this->versionRepository = $this->createMock(VersionRepositoryInterface::class);
        $this->wordPressManager = $this->createMock(WordPressVersionManager::class);

        $this->versionService = new VersionService(
            $this->minisiteRepository,
            $this->versionRepository,
            $this->wordPressManager
        );

        // Set up global $wpdb mock for db::query() calls
        global $wpdb;
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->prefix = 'wp_';
    }

    protected function tearDown(): void
    {
        // Clean up global $wpdb
        global $wpdb;
        unset($wpdb);
        parent::tearDown();
    }

    public function test_list_versions_returns_versions_when_user_has_access(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;
        $command = new ListVersionsCommand($siteId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $versions = array(
            (object) array('id' => 1, 'versionNumber' => 1),
            (object) array('id' => 2, 'versionNumber' => 2),
        );

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findByMinisiteId')
            ->with($siteId)
            ->willReturn($versions);

        $result = $this->versionService->listVersions($command);

        $this->assertEquals($versions, $result);
    }

    public function test_list_versions_throws_exception_when_minisite_not_found(): void
    {
        $command = new ListVersionsCommand('non-existent', 123);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with('non-existent')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->versionService->listVersions($command);
    }

    public function test_list_versions_throws_exception_when_access_denied(): void
    {
        $command = new ListVersionsCommand('test-site', 123);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 999; // Different user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');

        $this->versionService->listVersions($command);
    }

    public function test_create_draft_returns_version_when_successful(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;
        $label = 'Test Version';
        $comment = 'Test comment';
        $siteJson = array('test' => 'data');
        $command = new CreateDraftCommand($siteId, $userId, $label, $comment, $siteJson);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $nextVersion = 3;
        $savedVersion = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
        $savedVersion->id = 789;
        $savedVersion->versionNumber = $nextVersion;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('getNextVersionNumber')
            ->with($siteId)
            ->willReturn($nextVersion);

        $this->versionRepository
            ->expects($this->once())
            ->method('save')
            ->willReturn($savedVersion);

        $result = $this->versionService->createDraft($command);

        $this->assertEquals($savedVersion, $result);
    }

    public function test_create_draft_throws_exception_when_minisite_not_found(): void
    {
        $command = new CreateDraftCommand('non-existent', 123, 'Label', 'Comment', array());

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with('non-existent')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->versionService->createDraft($command);
    }

    public function test_create_draft_throws_exception_when_access_denied(): void
    {
        $command = new CreateDraftCommand('test-site', 123, 'Label', 'Comment', array());
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 999; // Different user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');

        $this->versionService->createDraft($command);
    }

    public function test_publish_version_throws_exception_when_minisite_not_found(): void
    {
        $command = new PublishVersionCommand('non-existent', 123, 456);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with('non-existent')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->versionService->publishVersion($command);
    }

    public function test_publish_version_throws_exception_when_access_denied(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 999; // Different user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');

        $this->versionService->publishVersion($command);
    }

    public function test_publish_version_throws_exception_when_version_not_found(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Version not found');

        $this->versionService->publishVersion($command);
    }

    public function test_publish_version_throws_exception_when_version_minisite_id_mismatch(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;
        $version = $this->createMock(Version::class);
        $version->status = 'draft';
        $version->minisiteId = 'different-site'; // Mismatch

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($version);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Version not found');

        $this->versionService->publishVersion($command);
    }

    public function test_publish_version_throws_exception_when_version_not_draft(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;
        $version = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
        $version->status = 'published';
        $version->minisiteId = 'test-site';

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($version);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only draft versions can be published');

        $this->versionService->publishVersion($command);
    }

    public function test_create_rollback_version_returns_version_when_successful(): void
    {
        $siteId = 'test-site-123';
        $sourceVersionId = 789;
        $userId = 456;
        $command = new RollbackVersionCommand($siteId, $sourceVersionId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $sourceVersion = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
        $sourceVersion->versionNumber = 2;
        $sourceVersion->minisiteId = $siteId;
        $sourceVersion->siteJson = json_encode(array('test' => 'data'));
        $sourceVersion->slugs = new \Minisite\Domain\ValueObjects\SlugPair('test-business', 'test-location');
        $sourceVersion->title = 'Test Title';
        $sourceVersion->name = 'Test Name';
        $sourceVersion->city = 'Test City';
        $sourceVersion->region = 'Test Region';
        $sourceVersion->countryCode = 'US';
        $sourceVersion->postalCode = '12345';
        $sourceVersion->geo = null;
        $sourceVersion->siteTemplate = 'default';
        $sourceVersion->palette = 'blue';
        $sourceVersion->industry = 'services';
        $sourceVersion->defaultLocale = 'en';
        $sourceVersion->schemaVersion = 1;
        $sourceVersion->siteVersion = 1;
        $sourceVersion->searchTerms = 'test';

        $nextVersion = 3;
        $savedVersion = $this->createMock(\Minisite\Features\VersionManagement\Domain\Entities\Version::class);
        $savedVersion->id = 999;
        $savedVersion->versionNumber = $nextVersion;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($sourceVersionId)
            ->willReturn($sourceVersion);

        $this->versionRepository
            ->expects($this->once())
            ->method('getNextVersionNumber')
            ->with($siteId)
            ->willReturn($nextVersion);

        $this->versionRepository
            ->expects($this->once())
            ->method('save')
            ->willReturn($savedVersion);

        $result = $this->versionService->createRollbackVersion($command);

        $this->assertEquals($savedVersion, $result);
    }

    public function test_create_rollback_version_throws_exception_when_minisite_not_found(): void
    {
        $command = new RollbackVersionCommand('non-existent', 789, 456);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with('non-existent')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Minisite not found');

        $this->versionService->createRollbackVersion($command);
    }

    public function test_create_rollback_version_throws_exception_when_access_denied(): void
    {
        $command = new RollbackVersionCommand('test-site', 789, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 999; // Different user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');

        $this->versionService->createRollbackVersion($command);
    }

    public function test_create_rollback_version_throws_exception_when_source_version_not_found(): void
    {
        $command = new RollbackVersionCommand('test-site', 789, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with(789)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source version not found');

        $this->versionService->createRollbackVersion($command);
    }

    public function test_create_rollback_version_throws_exception_when_source_version_minisite_id_mismatch(): void
    {
        $command = new RollbackVersionCommand('test-site', 789, 456);
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;
        $sourceVersion = $this->createMock(Version::class);
        $sourceVersion->minisiteId = 'different-site'; // Mismatch

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with(789)
            ->willReturn($sourceVersion);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source version not found');

        $this->versionService->createRollbackVersion($command);
    }

    /**
     * Test publishVersion updates location_point when version has geo data
     * Covers: performPublishVersion() lines 185-189
     */
    public function test_publishVersion_updates_location_point_when_geo_exists(): void
    {
        $siteId = 'test-site-123';
        $versionId = 789;
        $userId = 456;
        $command = new PublishVersionCommand($siteId, $versionId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;

        $version = $this->createMock(Version::class);
        $version->status = 'draft';
        $version->minisiteId = $siteId;
        $version->title = 'Test Title';
        $version->name = 'Test Name';
        $version->city = 'Test City';
        $version->region = 'Test Region';
        $version->countryCode = 'US';
        $version->postalCode = '12345';
        $version->siteTemplate = 'v2025';
        $version->palette = 'blue';
        $version->industry = 'services';
        $version->defaultLocale = 'en-US';
        $version->schemaVersion = 1;
        $version->siteVersion = 1;
        $version->searchTerms = 'test';
        $version->geo = new GeoPoint(40.7128, -74.0060); // Has geo data

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with($versionId)
            ->willReturn($version);

        $this->wordPressManager
            ->expects($this->once())
            ->method('jsonEncode')
            ->willReturn('{"test":"data"}');

        // Mock global $wpdb for db::query() calls
        global $wpdb;
        $locationPointQueryCalled = false;
        $wpdb->expects($this->atLeast(4))
            ->method('query')
            ->willReturnCallback(function ($sql) use (&$locationPointQueryCalled) {
                // Verify location_point update query is called
                if (strpos($sql, 'location_point = POINT') !== false) {
                    $locationPointQueryCalled = true;
                }

                return 1; // Success for all queries
            });

        $wpdb->expects($this->atLeast(3))
            ->method('prepare')
            ->willReturnArgument(0);

        $this->versionService->publishVersion($command);

        $this->assertTrue($locationPointQueryCalled, 'Location point update query should be called when geo data exists');
    }

    /**
     * Test publishVersion rolls back transaction on exception
     * Covers: performPublishVersion() lines 193-196
     */
    public function test_publishVersion_rolls_back_transaction_on_exception(): void
    {
        $siteId = 'test-site-123';
        $versionId = 789;
        $userId = 456;
        $command = new PublishVersionCommand($siteId, $versionId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;

        $version = $this->createMock(Version::class);
        $version->status = 'draft';
        $version->minisiteId = $siteId;
        $version->title = 'Test Title';
        $version->name = 'Test Name';
        $version->city = 'Test City';
        $version->region = 'Test Region';
        $version->countryCode = 'US';
        $version->postalCode = '12345';
        $version->siteTemplate = 'v2025';
        $version->palette = 'blue';
        $version->industry = 'services';
        $version->defaultLocale = 'en-US';
        $version->schemaVersion = 1;
        $version->siteVersion = 1;
        $version->searchTerms = 'test';
        $version->geo = null;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with($versionId)
            ->willReturn($version);

        $this->wordPressManager
            ->expects($this->once())
            ->method('jsonEncode')
            ->willThrowException(new \Exception('JSON encode failed'));

        // Mock global $wpdb for db::query() calls
        global $wpdb;
        $rollbackCalled = false;
        $callCount = 0;
        $wpdb->expects($this->atLeast(2))
            ->method('query')
            ->willReturnCallback(function ($sql) use (&$callCount, &$rollbackCalled) {
                $callCount++;
                if ($callCount === 1) {
                    // START TRANSACTION
                    return 1;
                }
                if (strpos($sql, 'ROLLBACK') !== false) {
                    // Verify ROLLBACK is called
                    $rollbackCalled = true;

                    return 1;
                }

                return 1;
            });

        $wpdb->expects($this->atLeast(1))
            ->method('prepare')
            ->willReturnArgument(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('JSON encode failed');

        try {
            $this->versionService->publishVersion($command);
        } finally {
            $this->assertTrue($rollbackCalled, 'ROLLBACK should be called when exception occurs');
        }
    }

    /**
     * Test publishVersion moves existing published version to draft
     * Covers: performPublishVersion() lines 143-148
     */
    public function test_publishVersion_moves_existing_published_to_draft(): void
    {
        $siteId = 'test-site-123';
        $versionId = 789;
        $userId = 456;
        $command = new PublishVersionCommand($siteId, $versionId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;

        $version = $this->createMock(Version::class);
        $version->status = 'draft';
        $version->minisiteId = $siteId;
        $version->title = 'Test Title';
        $version->name = 'Test Name';
        $version->city = 'Test City';
        $version->region = 'Test Region';
        $version->countryCode = 'US';
        $version->postalCode = '12345';
        $version->siteTemplate = 'v2025';
        $version->palette = 'blue';
        $version->industry = 'services';
        $version->defaultLocale = 'en-US';
        $version->schemaVersion = 1;
        $version->siteVersion = 1;
        $version->searchTerms = 'test';
        $version->geo = null;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->once())
            ->method('findById')
            ->with($versionId)
            ->willReturn($version);

        $this->wordPressManager
            ->expects($this->once())
            ->method('jsonEncode')
            ->willReturn('{"test":"data"}');

        // Mock global $wpdb for db::query() calls
        global $wpdb;
        $updatePublishedToDraftCalled = false;
        $wpdb->expects($this->atLeast(4))
            ->method('query')
            ->willReturnCallback(function ($sql) use (&$updatePublishedToDraftCalled) {
                // Verify the query that moves published to draft is called
                if (strpos($sql, "SET status = 'draft'") !== false &&
                    strpos($sql, "status = 'published'") !== false) {
                    $updatePublishedToDraftCalled = true;
                }

                return 1;
            });

        $wpdb->expects($this->atLeast(3))
            ->method('prepare')
            ->willReturnArgument(0);

        $this->versionService->publishVersion($command);

        $this->assertTrue($updatePublishedToDraftCalled, 'Query to move published version to draft should be called');
    }

    /**
     * Test getMinisiteForRendering returns null when user not logged in
     * Covers: hasUserAccess() lines 267-268
     */
    public function test_getMinisiteForRendering_returns_null_when_user_not_logged_in(): void
    {
        $siteId = 'test-site-123';
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(false);

        $result = $this->versionService->getMinisiteForRendering($siteId);

        $this->assertNull($result);
    }

    /**
     * Test getMinisiteForRendering returns null when user mismatch
     * Covers: hasUserAccess() lines 271-273
     */
    public function test_getMinisiteForRendering_returns_null_when_user_mismatch(): void
    {
        $siteId = 'test-site-123';
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = 123; // Different user

        $currentUser = (object) array('ID' => 456); // Current user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $result = $this->versionService->getMinisiteForRendering($siteId);

        $this->assertNull($result);
    }

    /**
     * Test getMinisiteForRendering returns minisite when user has access
     */
    public function test_getMinisiteForRendering_returns_minisite_when_user_has_access(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;
        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;

        $currentUser = (object) array('ID' => $userId); // Same user

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->wordPressManager
            ->expects($this->once())
            ->method('isUserLoggedIn')
            ->willReturn(true);

        $this->wordPressManager
            ->expects($this->once())
            ->method('getCurrentUser')
            ->willReturn($currentUser);

        $result = $this->versionService->getMinisiteForRendering($siteId);

        $this->assertEquals($minisite, $result);
    }

    /**
     * Test createRollbackVersion copies all fields from source version
     * Covers: performCreateRollbackVersion() lines 224-238
     */
    public function test_createRollbackVersion_copies_all_fields_from_source(): void
    {
        $siteId = 'test-site-123';
        $sourceVersionId = 789;
        $userId = 456;
        $command = new RollbackVersionCommand($siteId, $sourceVersionId, $userId);

        $minisite = $this->createMock(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;

        $sourceVersion = $this->createMock(Version::class);
        $sourceVersion->versionNumber = 2;
        $sourceVersion->minisiteId = $siteId;
        $sourceVersion->siteJson = json_encode(array('test' => 'data'));
        $sourceVersion->slugs = new \Minisite\Domain\ValueObjects\SlugPair('test-business', 'test-location');
        $sourceVersion->title = 'Source Title';
        $sourceVersion->name = 'Source Name';
        $sourceVersion->city = 'Source City';
        $sourceVersion->region = 'Source Region';
        $sourceVersion->countryCode = 'CA';
        $sourceVersion->postalCode = 'M5H 2N2';
        $sourceVersion->geo = new GeoPoint(43.6532, -79.3832);
        $sourceVersion->siteTemplate = 'v2025';
        $sourceVersion->palette = 'green';
        $sourceVersion->industry = 'retail';
        $sourceVersion->defaultLocale = 'en-CA';
        $sourceVersion->schemaVersion = 2;
        $sourceVersion->siteVersion = 5;
        $sourceVersion->searchTerms = 'source search terms';

        $nextVersion = 3;
        $savedVersion = $this->createMock(Version::class);
        $savedVersion->id = 999;
        $savedVersion->versionNumber = $nextVersion;

        $this->minisiteRepository
            ->expects($this->once())
            ->method('findById')
            ->with($siteId)
            ->willReturn($minisite);

        $this->versionRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->with($sourceVersionId)
            ->willReturn($sourceVersion);

        $this->versionRepository
            ->expects($this->once())
            ->method('getNextVersionNumber')
            ->with($siteId)
            ->willReturn($nextVersion);

        // Verify that save() is called with a version that has all fields copied
        $this->versionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($version) use ($sourceVersion, $nextVersion, $userId, $siteId, $sourceVersionId) {
                // Verify all fields are copied from source
                return $version instanceof Version &&
                    $version->minisiteId === $siteId &&
                    $version->versionNumber === $nextVersion &&
                    $version->status === 'draft' &&
                    $version->createdBy === $userId &&
                    $version->sourceVersionId === $sourceVersionId &&
                    $version->slugs === $sourceVersion->slugs &&
                    $version->title === $sourceVersion->title &&
                    $version->name === $sourceVersion->name &&
                    $version->city === $sourceVersion->city &&
                    $version->region === $sourceVersion->region &&
                    $version->countryCode === $sourceVersion->countryCode &&
                    $version->postalCode === $sourceVersion->postalCode &&
                    $version->geo === $sourceVersion->geo &&
                    $version->siteTemplate === $sourceVersion->siteTemplate &&
                    $version->palette === $sourceVersion->palette &&
                    $version->industry === $sourceVersion->industry &&
                    $version->defaultLocale === $sourceVersion->defaultLocale &&
                    $version->schemaVersion === $sourceVersion->schemaVersion &&
                    $version->siteVersion === $sourceVersion->siteVersion &&
                    $version->searchTerms === $sourceVersion->searchTerms;
            }))
            ->willReturn($savedVersion);

        $result = $this->versionService->createRollbackVersion($command);

        $this->assertEquals($savedVersion, $result);
    }
}
