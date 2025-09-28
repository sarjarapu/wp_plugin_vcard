<?php
declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\Entities\Version;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Infrastructure\Persistence\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\DatabaseTestHelper;

#[CoversClass(MinisiteRepository::class)]
final class MinisiteRepositoryIntegrationTest extends TestCase
{
    private MinisiteRepository $repository;
    private VersionRepository $versionRepository;
    private DatabaseTestHelper $dbHelper;

    protected function setUp(): void
    {
        $this->dbHelper = new DatabaseTestHelper();
        $this->dbHelper->cleanupTestTables();
        $this->dbHelper->createAllTables();
        
        $this->repository = new MinisiteRepository($this->dbHelper->getWpdb());
        $this->versionRepository = new VersionRepository($this->dbHelper->getWpdb());
    }

    protected function tearDown(): void
    {
        $this->dbHelper->cleanupTestTables();
    }


    public function testInsertAndRetrieveMinisite(): void
    {
        $minisite = $this->createTestMinisite();
        
        // Insert the minisite
        $savedMinisite = $this->repository->insert($minisite);
        
        $this->assertSame($minisite->id, $savedMinisite->id);
        $this->assertSame($minisite->title, $savedMinisite->title);
        $this->assertSame($minisite->name, $savedMinisite->name);
        $this->assertSame($minisite->city, $savedMinisite->city);
        $this->assertSame($minisite->region, $savedMinisite->region);
        $this->assertSame($minisite->countryCode, $savedMinisite->countryCode);
        $this->assertSame($minisite->postalCode, $savedMinisite->postalCode);
        $this->assertSame($minisite->siteTemplate, $savedMinisite->siteTemplate);
        $this->assertSame($minisite->palette, $savedMinisite->palette);
        $this->assertSame($minisite->industry, $savedMinisite->industry);
        $this->assertSame($minisite->defaultLocale, $savedMinisite->defaultLocale);
        $this->assertSame($minisite->schemaVersion, $savedMinisite->schemaVersion);
        $this->assertSame($minisite->siteVersion, $savedMinisite->siteVersion);
        $this->assertSame($minisite->status, $savedMinisite->status);
        $this->assertSame($minisite->createdBy, $savedMinisite->createdBy);
        $this->assertSame($minisite->updatedBy, $savedMinisite->updatedBy);
        
        // Test geo coordinates (can now be properly tested with MySQL)
        $this->assertNull($savedMinisite->geo);
        
        // Test slugs
        $this->assertSame('test-business', $savedMinisite->slugs->business);
        $this->assertSame('test-location', $savedMinisite->slugs->location);
        
        // Test site JSON
        $this->assertSame(['test' => 'data'], $savedMinisite->siteJson);
    }

    public function testFindBySlugs(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $slugs = new SlugPair('test-business', 'test-location');
        $found = $this->repository->findBySlugs($slugs);
        
        $this->assertNotNull($found);
        $this->assertSame($minisite->id, $found->id);
        $this->assertSame($minisite->title, $found->title);
    }

    public function testFindBySlugsReturnsNullWhenNotFound(): void
    {
        $slugs = new SlugPair('nonexistent', 'location');
        $found = $this->repository->findBySlugs($slugs);
        
        $this->assertNull($found);
    }

    public function testFindBySlugParams(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test the findBySlugParams method with FOR UPDATE clause (now supported in MySQL)
        $found = $this->repository->findBySlugParams('test-business', 'test-location');
        
        $this->assertNotNull($found);
        $this->assertSame($minisite->id, $found->id);
        $this->assertSame($minisite->title, $found->title);
    }

    public function testFindById(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $found = $this->repository->findById($minisite->id);
        
        $this->assertNotNull($found);
        $this->assertSame($minisite->id, $found->id);
        $this->assertSame($minisite->title, $found->title);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $found = $this->repository->findById('nonexistent');
        
        $this->assertNull($found);
    }

    public function testListByOwner(): void
    {
        $userId = 123;
        
        // Create multiple minisites for the same user
        $minisite1 = $this->createTestMinisite(['id' => 'test-1', 'title' => 'Business 1', 'slug' => 'business-1-location-1', 'businessSlug' => 'business-1', 'locationSlug' => 'location-1']);
        $minisite2 = $this->createTestMinisite(['id' => 'test-2', 'title' => 'Business 2', 'slug' => 'business-2-location-2', 'businessSlug' => 'business-2', 'locationSlug' => 'location-2']);
        $minisite3 = $this->createTestMinisite(['id' => 'test-3', 'title' => 'Business 3', 'createdBy' => 456, 'slug' => 'business-3-location-3', 'businessSlug' => 'business-3', 'locationSlug' => 'location-3']);
        
        $this->repository->insert($minisite1);
        $this->repository->insert($minisite2);
        $this->repository->insert($minisite3);
        
        $minisites = $this->repository->listByOwner($userId);
        
        $this->assertCount(2, $minisites);
        // Order is by updated_at DESC, id DESC, so test-2 comes first
        $this->assertSame('test-2', $minisites[0]->id);
        $this->assertSame('test-1', $minisites[1]->id);
    }

    public function testListByOwnerWithLimitAndOffset(): void
    {
        $userId = 123;
        
        // Create 5 minisites
        for ($i = 1; $i <= 5; $i++) {
            $minisite = $this->createTestMinisite(['id' => "test-$i", 'title' => "Business $i", 'slug' => "business-$i-location-$i", 'businessSlug' => "business-$i", 'locationSlug' => "location-$i"]);
            $this->repository->insert($minisite);
        }
        
        $minisites = $this->repository->listByOwner($userId, 2, 1);
        
        $this->assertCount(2, $minisites);
        $this->assertSame('test-4', $minisites[0]->id);
        $this->assertSame('test-3', $minisites[1]->id);
    }

    public function testUpdateCurrentVersionId(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $this->repository->updateCurrentVersionId($minisite->id, 456);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame(456, $updated->currentVersionId);
    }

    public function testUpdateCurrentVersionIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minisite not found or update failed.');
        
        $this->repository->updateCurrentVersionId('nonexistent', 456);
    }

    public function testUpdateCoordinates(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test updating coordinates with MySQL spatial functions
        $this->repository->updateCoordinates($minisite->id, 40.7128, -74.0060, 123);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertNotNull($updated->geo);
        $this->assertSame(40.7128, $updated->geo->latitude);
        $this->assertSame(-74.0060, $updated->geo->longitude);
    }

    public function testUpdateCoordinatesWithNullValues(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test updating coordinates to null (clearing location)
        $this->repository->updateCoordinates($minisite->id, null, null, 123);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertNull($updated->geo);
    }

    public function testUpdateSlug(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test updating slug with MySQL NOW() function
        $this->repository->updateSlug($minisite->id, 'new-business-slug');
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('new-business-slug', $updated->slug);
    }

    public function testUpdateSlugs(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test updating both slugs with MySQL NOW() function
        $this->repository->updateSlugs($minisite->id, 'new-business', 'new-location');
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('new-business', $updated->slugs->business);
        $this->assertSame('new-location', $updated->slugs->location);
    }

    public function testUpdatePublishStatus(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Test updating publish status with MySQL NOW() function
        $this->repository->updatePublishStatus($minisite->id, 'published');
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('published', $updated->publishStatus);
    }

    public function testSaveMinisiteWithOptimisticLocking(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Update the minisite
        $minisite->title = 'Updated Title';
        $minisite->name = 'Updated Name';
        
        $saved = $this->repository->save($minisite, 1);
        
        $this->assertSame('Updated Title', $saved->title);
        $this->assertSame('Updated Name', $saved->name);
        $this->assertSame(2, $saved->siteVersion); // Should be incremented
    }

    public function testSaveMinisiteThrowsExceptionOnConcurrentModification(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $minisite->title = 'Updated Title';
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected (optimistic lock failed).');
        
        // Try to save with wrong expected version
        $this->repository->save($minisite, 999);
    }

    public function testUpdateTitle(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $result = $this->repository->updateTitle($minisite->id, 'New Title');
        
        $this->assertTrue($result);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('New Title', $updated->title);
    }

    public function testUpdateStatus(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $result = $this->repository->updateStatus($minisite->id, 'published');
        
        $this->assertTrue($result);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('published', $updated->status);
    }

    public function testUpdateBusinessInfo(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $fields = [
            'title' => 'New Title',
            'name' => 'New Name',
            'city' => 'New City'
        ];
        
        $this->repository->updateBusinessInfo($minisite->id, $fields, 123);
        
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('New Title', $updated->title);
        $this->assertSame('New Name', $updated->name);
        $this->assertSame('New City', $updated->city);
    }

    public function testPublishMinisiteWithVersioning(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Create a version for the minisite
        $version = $this->createTestVersion($minisite->id, [
            'status' => 'draft',
            'label' => 'Initial Draft'
        ]);
        $this->versionRepository->save($version);
        
        // Publish the minisite
        $this->repository->publishMinisite($minisite->id);
        
        // Verify the minisite status was updated
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('published', $updated->publishStatus);
    }

    public function testPublishMinisiteWithExistingPublishedVersion(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Create a published version
        $publishedVersion = $this->createTestVersion($minisite->id, [
            'status' => 'published',
            'label' => 'Published Version',
            'publishedAt' => new DateTimeImmutable('2025-01-01T10:00:00Z')
        ]);
        $this->versionRepository->save($publishedVersion);
        
        // Create a new draft version
        $draftVersion = $this->createTestVersion($minisite->id, [
            'versionNumber' => 2,
            'status' => 'draft',
            'label' => 'New Draft'
        ]);
        $this->versionRepository->save($draftVersion);
        
        // Publish the new version
        $this->repository->publishMinisite($minisite->id);
        
        // Verify the minisite was updated
        $updated = $this->repository->findById($minisite->id);
        $this->assertSame('published', $updated->publishStatus);
    }

    public function testPublishMinisiteCreatesDraftFromPublishedVersion(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        // Create a published version
        $publishedVersion = $this->createTestVersion($minisite->id, [
            'status' => 'published',
            'label' => 'Published Version',
            'publishedAt' => new DateTimeImmutable('2025-01-01T10:00:00Z')
        ]);
        $this->versionRepository->save($publishedVersion);
        
        // Publish should create a new draft from the published version
        $this->repository->publishMinisite($minisite->id);
        
        // Verify a new draft version was created
        $versions = $this->versionRepository->findByMinisiteId($minisite->id);
        $this->assertCount(2, $versions);
        
        $draftVersion = $versions[0]; // Latest version
        $this->assertSame('draft', $draftVersion->status);
        $this->assertSame(2, $draftVersion->versionNumber);
    }

    public function testPublishMinisiteThrowsExceptionWhenNoVersionFound(): void
    {
        $minisite = $this->createTestMinisite();
        $this->repository->insert($minisite);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No version found for minisite.');
        
        $this->repository->publishMinisite($minisite->id);
    }

    public function testMinisiteWithSpecialCharacters(): void
    {
        $minisite = $this->createTestMinisite([
            'id' => 'test-special',
            'title' => 'Café & Restaurant "Le Bistro"',
            'name' => 'José María\'s Café',
            'city' => 'São Paulo',
            'searchTerms' => 'café restaurant "le bistro" josé maría são paulo'
        ]);
        
        $saved = $this->repository->insert($minisite);
        
        $this->assertSame('Café & Restaurant "Le Bistro"', $saved->title);
        $this->assertSame('José María\'s Café', $saved->name);
        $this->assertSame('São Paulo', $saved->city);
        
        // Verify it can be retrieved correctly
        $retrieved = $this->repository->findById('test-special');
        $this->assertSame('Café & Restaurant "Le Bistro"', $retrieved->title);
        $this->assertSame('José María\'s Café', $retrieved->name);
        $this->assertSame('São Paulo', $retrieved->city);
    }

    public function testMinisiteWithLongText(): void
    {
        $longTitle = str_repeat('Very long business title. ', 50);
        $longName = str_repeat('Very long business name. ', 50);
        
        $minisite = $this->createTestMinisite([
            'id' => 'test-long',
            'title' => $longTitle,
            'name' => $longName
        ]);
        
        $saved = $this->repository->insert($minisite);
        
        // Database truncates to field limits (title: 200 chars, name: 200 chars)
        $this->assertSame(substr($longTitle, 0, 200), $saved->title);
        $this->assertSame(substr($longName, 0, 200), $saved->name);
    }

    private function createTestMinisite(array $overrides = []): Minisite
    {
        $defaults = [
            'id' => 'test-123',
            'slug' => 'test-business-location',
            'businessSlug' => 'test-business',
            'locationSlug' => 'test-location',
            'title' => 'Test Business',
            'name' => 'Test Business Name',
            'city' => 'New York',
            'region' => 'NY',
            'countryCode' => 'US',
            'postalCode' => '10001',
            'lat' => null, // Can be set to test geo coordinates with MySQL
            'lng' => null,
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'siteJson' => ['test' => 'data'],
            'searchTerms' => 'test business name new york blue test business',
            'status' => 'draft',
            'publishStatus' => 'draft',
            'createdBy' => 123,
            'updatedBy' => 123
        ];
        
        $data = array_merge($defaults, $overrides);
        
        return new Minisite(
            id: $data['id'],
            slug: $data['slug'],
            slugs: new SlugPair($data['businessSlug'], $data['locationSlug']),
            title: $data['title'],
            name: $data['name'],
            city: $data['city'],
            region: $data['region'],
            countryCode: $data['countryCode'],
            postalCode: $data['postalCode'],
            geo: $data['lat'] && $data['lng'] ? new GeoPoint($data['lat'], $data['lng']) : null,
            siteTemplate: $data['siteTemplate'],
            palette: $data['palette'],
            industry: $data['industry'],
            defaultLocale: $data['defaultLocale'],
            schemaVersion: $data['schemaVersion'],
            siteVersion: $data['siteVersion'],
            siteJson: $data['siteJson'],
            searchTerms: $data['searchTerms'],
            status: $data['status'],
            publishStatus: $data['publishStatus'],
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            updatedAt: new DateTimeImmutable('2025-01-15T10:30:00Z'),
            publishedAt: null,
            createdBy: $data['createdBy'],
            updatedBy: $data['updatedBy'],
            currentVersionId: null
        );
    }

    private function createTestVersion(string $minisiteId, array $overrides = []): Version
    {
        $defaults = [
            'id' => null,
            'versionNumber' => 1,
            'status' => 'draft',
            'label' => 'Test Version',
            'comment' => 'Test comment',
            'createdBy' => 123,
            'publishedAt' => null,
            'sourceVersionId' => null,
            'businessSlug' => 'test-business',
            'locationSlug' => 'test-location',
            'title' => 'Test Business',
            'name' => 'Test Business Name',
            'city' => 'New York',
            'region' => 'NY',
            'countryCode' => 'US',
            'postalCode' => '10001',
            'lat' => null,
            'lng' => null,
            'siteTemplate' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'defaultLocale' => 'en-US',
            'schemaVersion' => 1,
            'siteVersion' => 1,
            'siteJson' => ['test' => 'data'],
            'searchTerms' => 'test business name new york blue test business'
        ];
        
        $data = array_merge($defaults, $overrides);
        
        return new Version(
            id: $data['id'],
            minisiteId: $minisiteId,
            versionNumber: $data['versionNumber'],
            status: $data['status'],
            label: $data['label'],
            comment: $data['comment'],
            createdBy: $data['createdBy'],
            createdAt: new DateTimeImmutable('2025-01-15T10:00:00Z'),
            publishedAt: $data['publishedAt'],
            sourceVersionId: $data['sourceVersionId'],
            slugs: new SlugPair($data['businessSlug'], $data['locationSlug']),
            title: $data['title'],
            name: $data['name'],
            city: $data['city'],
            region: $data['region'],
            countryCode: $data['countryCode'],
            postalCode: $data['postalCode'],
            geo: $data['lat'] && $data['lng'] ? new GeoPoint($data['lat'], $data['lng']) : null,
            siteTemplate: $data['siteTemplate'],
            palette: $data['palette'],
            industry: $data['industry'],
            defaultLocale: $data['defaultLocale'],
            schemaVersion: $data['schemaVersion'],
            siteVersion: $data['siteVersion'],
            siteJson: $data['siteJson'],
            searchTerms: $data['searchTerms']
        );
    }
}
