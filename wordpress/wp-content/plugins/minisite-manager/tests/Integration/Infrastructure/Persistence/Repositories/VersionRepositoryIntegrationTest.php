<?php
declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

#[CoversClass(VersionRepository::class)]
final class VersionRepositoryIntegrationTest extends TestCase
{
    private VersionRepository $repository;
    private FakeWpdb $db;
    private PDO $pdo;

    protected function setUp(): void
    {
        // Set up in-memory SQLite database for integration tests
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->db = new FakeWpdb($this->pdo);
        $this->db->prefix = 'wp_';
        
        $this->repository = new VersionRepository($this->db);
        
        $this->createTestTable();
    }

    private function createTestTable(): void
    {
        $sql = "
            CREATE TABLE wp_minisite_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                minisite_id TEXT NOT NULL,
                version_number INTEGER NOT NULL,
                status TEXT NOT NULL,
                label TEXT,
                comment TEXT,
                created_by INTEGER NOT NULL,
                created_at DATETIME,
                published_at DATETIME,
                source_version_id INTEGER,
                business_slug TEXT,
                location_slug TEXT,
                title TEXT,
                name TEXT,
                city TEXT,
                region TEXT,
                country_code TEXT,
                postal_code TEXT,
                location_point TEXT,
                site_template TEXT,
                palette TEXT,
                industry TEXT,
                default_locale TEXT,
                schema_version INTEGER,
                site_version INTEGER,
                site_json TEXT,
                search_terms TEXT,
                UNIQUE(minisite_id, version_number)
            )
        ";
        
        $this->pdo->exec($sql);
    }

    public function testSaveAndRetrieveVersion(): void
    {
        $version = new Version(
            id: null,
            minisiteId: 'test-minisite-1',
            versionNumber: 1,
            status: 'draft',
            label: 'Initial Draft',
            comment: 'First version of the minisite',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['title' => 'Test Site', 'content' => 'Test content'],
            slugs: new SlugPair('test-business', 'test-location'),
            title: 'Test Business',
            name: 'Test Business Name',
            city: 'Test City',
            region: 'Test Region',
            countryCode: 'US',
            postalCode: '12345',
            geo: null, // Skip geo for SQLite compatibility
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            searchTerms: 'test business services'
        );

        // Save the version
        $savedVersion = $this->repository->save($version);
        
        $this->assertNotNull($savedVersion->id);
        $this->assertSame('test-minisite-1', $savedVersion->minisiteId);
        $this->assertSame(1, $savedVersion->versionNumber);
        $this->assertSame('draft', $savedVersion->status);
        $this->assertSame('Initial Draft', $savedVersion->label);
        $this->assertSame('First version of the minisite', $savedVersion->comment);
        $this->assertSame(1, $savedVersion->createdBy);
        $this->assertSame('Test Business', $savedVersion->title);
        $this->assertSame('test-business', $savedVersion->slugs->business);
        $this->assertSame('test-location', $savedVersion->slugs->location);
        $this->assertNull($savedVersion->geo); // Geo is null for SQLite compatibility

        // Retrieve the version
        $retrievedVersion = $this->repository->findById($savedVersion->id);
        
        $this->assertNotNull($retrievedVersion);
        $this->assertSame($savedVersion->id, $retrievedVersion->id);
        $this->assertSame('test-minisite-1', $retrievedVersion->minisiteId);
        $this->assertSame(1, $retrievedVersion->versionNumber);
        $this->assertSame('draft', $retrievedVersion->status);
        $this->assertSame('Initial Draft', $retrievedVersion->label);
        $this->assertSame('Test Business', $retrievedVersion->title);
        $this->assertSame('test-business', $retrievedVersion->slugs->business);
        $this->assertSame('test-location', $retrievedVersion->slugs->location);
        $this->assertNull($retrievedVersion->geo); // Geo is null for SQLite compatibility
    }

    public function testUpdateExistingVersion(): void
    {
        // Create initial version
        $version = new Version(
            id: null,
            minisiteId: 'test-minisite-2',
            versionNumber: 1,
            status: 'draft',
            label: 'Initial Draft',
            comment: 'First version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['title' => 'Original Title'],
            title: 'Original Title',
            name: 'Original Name'
        );

        $savedVersion = $this->repository->save($version);
        $originalId = $savedVersion->id;

        // Update the version
        $savedVersion->label = 'Updated Draft';
        $savedVersion->comment = 'Updated comment';
        $savedVersion->title = 'Updated Title';
        $savedVersion->name = 'Updated Name';
        $savedVersion->status = 'published';
        $savedVersion->publishedAt = new DateTimeImmutable('2025-01-02T10:00:00Z');

        $updatedVersion = $this->repository->save($savedVersion);

        $this->assertSame($originalId, $updatedVersion->id);
        $this->assertSame('Updated Draft', $updatedVersion->label);
        $this->assertSame('Updated comment', $updatedVersion->comment);
        $this->assertSame('Updated Title', $updatedVersion->title);
        $this->assertSame('Updated Name', $updatedVersion->name);
        $this->assertSame('published', $updatedVersion->status);
        $this->assertNotNull($updatedVersion->publishedAt);

        // Verify the update persisted
        $retrievedVersion = $this->repository->findById($originalId);
        $this->assertSame('Updated Draft', $retrievedVersion->label);
        $this->assertSame('published', $retrievedVersion->status);
    }

    public function testFindByMinisiteIdReturnsVersionsInCorrectOrder(): void
    {
        $minisiteId = 'test-minisite-3';
        
        // Create multiple versions
        $version1 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'draft',
            label: 'Version 1',
            comment: 'First version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['version' => 1]
        );

        $version2 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 2,
            status: 'published',
            label: 'Version 2',
            comment: 'Second version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            sourceVersionId: null,
            siteJson: ['version' => 2]
        );

        $version3 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 3,
            status: 'draft',
            label: 'Version 3',
            comment: 'Third version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-03T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['version' => 3]
        );

        $this->repository->save($version1);
        $this->repository->save($version2);
        $this->repository->save($version3);

        $versions = $this->repository->findByMinisiteId($minisiteId);
        
        $this->assertCount(3, $versions);
        // Should be ordered by version_number DESC
        $this->assertSame(3, $versions[0]->versionNumber);
        $this->assertSame(2, $versions[1]->versionNumber);
        $this->assertSame(1, $versions[2]->versionNumber);
    }

    public function testFindLatestVersionReturnsHighestVersionNumber(): void
    {
        $minisiteId = 'test-minisite-4';
        
        // Create versions with different numbers
        $version1 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'draft',
            label: 'Version 1',
            comment: 'First version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $version5 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 5,
            status: 'published',
            label: 'Version 5',
            comment: 'Fifth version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-05T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-05T10:00:00Z'),
            sourceVersionId: null,
            siteJson: []
        );

        $version3 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 3,
            status: 'draft',
            label: 'Version 3',
            comment: 'Third version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-03T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $this->repository->save($version1);
        $this->repository->save($version5);
        $this->repository->save($version3);

        $latestVersion = $this->repository->findLatestVersion($minisiteId);
        
        $this->assertNotNull($latestVersion);
        $this->assertSame(5, $latestVersion->versionNumber);
        $this->assertSame('Version 5', $latestVersion->label);
    }

    public function testFindLatestDraftReturnsLatestDraftVersion(): void
    {
        $minisiteId = 'test-minisite-5';
        
        // Create mixed versions
        $draft1 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'draft',
            label: 'Draft 1',
            comment: 'First draft',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $published = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 2,
            status: 'published',
            label: 'Published',
            comment: 'Published version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            sourceVersionId: null,
            siteJson: []
        );

        $draft2 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 3,
            status: 'draft',
            label: 'Draft 2',
            comment: 'Second draft',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-03T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $this->repository->save($draft1);
        $this->repository->save($published);
        $this->repository->save($draft2);

        $latestDraft = $this->repository->findLatestDraft($minisiteId);
        
        $this->assertNotNull($latestDraft);
        $this->assertSame(3, $latestDraft->versionNumber);
        $this->assertSame('draft', $latestDraft->status);
        $this->assertSame('Draft 2', $latestDraft->label);
    }

    public function testFindPublishedVersionReturnsPublishedVersion(): void
    {
        $minisiteId = 'test-minisite-6';
        
        // Create versions with only one published
        $draft = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'draft',
            label: 'Draft',
            comment: 'Draft version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $published = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 2,
            status: 'published',
            label: 'Published',
            comment: 'Published version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            sourceVersionId: null,
            siteJson: []
        );

        $this->repository->save($draft);
        $this->repository->save($published);

        $publishedVersion = $this->repository->findPublishedVersion($minisiteId);
        
        $this->assertNotNull($publishedVersion);
        $this->assertSame(2, $publishedVersion->versionNumber);
        $this->assertSame('published', $publishedVersion->status);
        $this->assertSame('Published', $publishedVersion->label);
    }

    public function testGetNextVersionNumberCalculatesCorrectly(): void
    {
        $minisiteId = 'test-minisite-7';
        
        // Create versions with gaps
        $version1 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'draft',
            label: 'Version 1',
            comment: 'First version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $version5 = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 5,
            status: 'published',
            label: 'Version 5',
            comment: 'Fifth version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-05T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-05T10:00:00Z'),
            sourceVersionId: null,
            siteJson: []
        );

        $this->repository->save($version1);
        $this->repository->save($version5);

        $nextVersion = $this->repository->getNextVersionNumber($minisiteId);
        
        $this->assertSame(6, $nextVersion);
    }

    public function testGetNextVersionNumberReturnsOneForNewMinisite(): void
    {
        $minisiteId = 'test-minisite-8';
        
        $nextVersion = $this->repository->getNextVersionNumber($minisiteId);
        
        $this->assertSame(1, $nextVersion);
    }

    public function testDeleteVersionRemovesFromDatabase(): void
    {
        $version = new Version(
            id: null,
            minisiteId: 'test-minisite-9',
            versionNumber: 1,
            status: 'draft',
            label: 'To Delete',
            comment: 'This will be deleted',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: []
        );

        $savedVersion = $this->repository->save($version);
        $versionId = $savedVersion->id;

        // Verify it exists
        $this->assertNotNull($this->repository->findById($versionId));

        // Delete it
        $result = $this->repository->delete($versionId);
        
        $this->assertTrue($result);
        
        // Verify it's gone
        $this->assertNull($this->repository->findById($versionId));
    }

    public function testGetLatestDraftForEditingReturnsExistingDraft(): void
    {
        $minisiteId = 'test-minisite-10';
        
        $draft = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 2,
            status: 'draft',
            label: 'Existing Draft',
            comment: 'Existing draft version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-02T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: ['draft' => true]
        );

        $this->repository->save($draft);

        $result = $this->repository->getLatestDraftForEditing($minisiteId);
        
        $this->assertNotNull($result);
        $this->assertSame(2, $result->versionNumber);
        $this->assertSame('draft', $result->status);
        $this->assertSame('Existing Draft', $result->label);
    }

    public function testGetLatestDraftForEditingCreatesDraftFromPublished(): void
    {
        $minisiteId = 'test-minisite-11';
        
        $published = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 1,
            status: 'published',
            label: 'Published Version',
            comment: 'Published version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            sourceVersionId: null,
            siteJson: ['published' => true],
            title: 'Published Title',
            name: 'Published Name'
        );

        $savedPublished = $this->repository->save($published);

        $result = $this->repository->getLatestDraftForEditing($minisiteId);
        
        $this->assertNotNull($result);
        $this->assertSame(2, $result->versionNumber);
        $this->assertSame('draft', $result->status);
        $this->assertSame('Draft from v1', $result->label);
        $this->assertSame($savedPublished->id, $result->sourceVersionId);
        $this->assertSame('Published Title', $result->title);
        $this->assertSame('Published Name', $result->name);
        $this->assertSame(['published' => true], $result->siteJson);
    }

    public function testCreateDraftFromVersionCreatesNewDraft(): void
    {
        $minisiteId = 'test-minisite-12';
        
        $sourceVersion = new Version(
            id: null,
            minisiteId: $minisiteId,
            versionNumber: 3,
            status: 'published',
            label: 'Source Version',
            comment: 'Source version',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-03T10:00:00Z'),
            publishedAt: new DateTimeImmutable('2025-01-03T10:00:00Z'),
            sourceVersionId: null,
            siteJson: ['source' => true],
            title: 'Source Title',
            name: 'Source Name',
            city: 'Source City'
        );

        $savedSource = $this->repository->save($sourceVersion);

        $result = $this->repository->createDraftFromVersion($savedSource);
        
        $this->assertNotNull($result);
        $this->assertSame(4, $result->versionNumber);
        $this->assertSame('draft', $result->status);
        $this->assertSame('Draft from v3', $result->label);
        $this->assertSame($savedSource->id, $result->sourceVersionId);
        $this->assertSame('Source Title', $result->title);
        $this->assertSame('Source Name', $result->name);
        $this->assertSame('Source City', $result->city);
        $this->assertSame(['source' => true], $result->siteJson);
    }

    public function testVersionWithGeoLocationHandling(): void
    {
        // Skip geo location test for SQLite compatibility
        $this->markTestSkipped('Geo location functionality requires MySQL with spatial extensions');
    }

    public function testVersionWithSlugPairHandling(): void
    {
        $version = new Version(
            id: null,
            minisiteId: 'test-minisite-14',
            versionNumber: 1,
            status: 'draft',
            label: 'Slug Version',
            comment: 'Version with slugs',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: [],
            slugs: new SlugPair('my-business', 'my-location')
        );

        $savedVersion = $this->repository->save($version);
        
        $this->assertNotNull($savedVersion->slugs);
        $this->assertSame('my-business', $savedVersion->slugs->business);
        $this->assertSame('my-location', $savedVersion->slugs->location);

        $retrievedVersion = $this->repository->findById($savedVersion->id);
        
        $this->assertNotNull($retrievedVersion->slugs);
        $this->assertSame('my-business', $retrievedVersion->slugs->business);
        $this->assertSame('my-location', $retrievedVersion->slugs->location);
    }

    public function testComplexSiteJsonHandling(): void
    {
        $complexJson = [
            'sections' => [
                'hero' => [
                    'title' => 'Welcome',
                    'subtitle' => 'To our business',
                    'image' => 'hero.jpg'
                ],
                'about' => [
                    'title' => 'About Us',
                    'content' => 'We are a great business...',
                    'features' => ['feature1', 'feature2', 'feature3']
                ]
            ],
            'settings' => [
                'theme' => 'modern',
                'colors' => ['primary' => '#007bff', 'secondary' => '#6c757d'],
                'fonts' => ['heading' => 'Arial', 'body' => 'Helvetica']
            ],
            'metadata' => [
                'created' => '2025-01-01T10:00:00Z',
                'modified' => '2025-01-01T10:00:00Z',
                'version' => '1.0.0'
            ]
        ];

        $version = new Version(
            id: null,
            minisiteId: 'test-minisite-15',
            versionNumber: 1,
            status: 'draft',
            label: 'Complex JSON Version',
            comment: 'Version with complex JSON',
            createdBy: 1,
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00Z'),
            publishedAt: null,
            sourceVersionId: null,
            siteJson: $complexJson
        );

        $savedVersion = $this->repository->save($version);
        
        $this->assertSame($complexJson, $savedVersion->siteJson);

        $retrievedVersion = $this->repository->findById($savedVersion->id);
        
        $this->assertSame($complexJson, $retrievedVersion->siteJson);
        $this->assertSame('Welcome', $retrievedVersion->siteJson['sections']['hero']['title']);
        $this->assertSame(['feature1', 'feature2', 'feature3'], $retrievedVersion->siteJson['sections']['about']['features']);
    }
}
