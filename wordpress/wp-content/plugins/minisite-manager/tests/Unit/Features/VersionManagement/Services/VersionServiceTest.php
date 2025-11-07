<?php

namespace Minisite\Features\VersionManagement\Services;

use Minisite\Features\VersionManagement\Commands\CreateDraftCommand;
use Minisite\Features\VersionManagement\Commands\ListVersionsCommand;
use Minisite\Features\VersionManagement\Commands\PublishVersionCommand;
use Minisite\Features\VersionManagement\Commands\RollbackVersionCommand;
use Minisite\Features\VersionManagement\WordPress\WordPressVersionManager;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
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
        $this->minisiteRepository = $this->createMock(MinisiteRepository::class);
        $this->versionRepository = $this->createMock(VersionRepositoryInterface::class);
        $this->wordPressManager = $this->createMock(WordPressVersionManager::class);

        $this->versionService = new VersionService(
            $this->minisiteRepository,
            $this->versionRepository,
            $this->wordPressManager
        );
    }

    public function test_list_versions_returns_versions_when_user_has_access(): void
    {
        $siteId = 'test-site-123';
        $userId = 456;
        $command = new ListVersionsCommand($siteId, $userId);

        $minisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $versions = [
            (object) ['id' => 1, 'versionNumber' => 1],
            (object) ['id' => 2, 'versionNumber' => 2]
        ];

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
        $minisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);
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
        $siteJson = ['test' => 'data'];
        $command = new CreateDraftCommand($siteId, $userId, $label, $comment, $siteJson);

        $minisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $nextVersion = 3;
        $savedVersion = $this->createMock(\Minisite\Domain\Entities\Version::class);
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

    public function test_publish_version_throws_exception_when_version_not_draft(): void
    {
        $command = new PublishVersionCommand('test-site', 123, 456);
        $minisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);
        $minisite->createdBy = 456;
        $version = $this->createMock(\Minisite\Domain\Entities\Version::class);
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

        $minisite = $this->createMock(\Minisite\Domain\Entities\Minisite::class);
        $minisite->createdBy = $userId;
        $sourceVersion = $this->createMock(\Minisite\Domain\Entities\Version::class);
        $sourceVersion->versionNumber = 2;
        $sourceVersion->minisiteId = $siteId;
        $sourceVersion->siteJson = ['test' => 'data'];
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
        $savedVersion = $this->createMock(\Minisite\Domain\Entities\Version::class);
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
}
