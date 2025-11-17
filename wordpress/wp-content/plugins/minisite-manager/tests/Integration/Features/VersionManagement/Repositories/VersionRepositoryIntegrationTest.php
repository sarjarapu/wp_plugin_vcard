<?php

declare(strict_types=1);

namespace Tests\Integration\Features\VersionManagement\Repositories;

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\VersionManagement\Domain\Entities\Version;
use Minisite\Features\VersionManagement\Repositories\VersionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for VersionRepository
 *
 * Tests VersionRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisite_versions table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(VersionRepository::class)]
final class VersionRepositoryIntegrationTest extends BaseIntegrationTest
{
    private VersionRepository $repository;

    /**
     * Get entity paths for ORM configuration
     */
    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/ReviewManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    /**
     * Setup test-specific services and repositories
     */
    protected function setupTestSpecificServices(): void
    {
        // Create VersionRepository instance
        $this->repository = new VersionRepository(
            $this->em,
            $this->em->getClassMetadata(Version::class)
        );
    }

    /**
     * Clean up test data (but keep table structure)
     */
    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisite_versions WHERE minisite_id LIKE 'test_%' OR minisite_id = 'test-minisite-123'"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_save_and_find_version(): void
    {
        $version = new Version();
        $version->minisiteId = 'test-minisite-123';
        $version->versionNumber = 1;
        $version->status = 'draft';
        $version->title = 'Test Minisite';
        $version->name = 'Test Business';
        $version->createdBy = 1;
        $version->siteJson = json_encode(array());

        $saved = $this->repository->save($version);

        $this->assertNotNull($saved->id);
        $this->assertNotNull($saved->createdAt);

        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertEquals($saved->id, $found->id);
        $this->assertEquals('test-minisite-123', $found->minisiteId);
        $this->assertEquals(1, $found->versionNumber);
        $this->assertEquals('draft', $found->status);
        $this->assertEquals('Test Minisite', $found->title);
        $this->assertEquals('Test Business', $found->name);
    }

    public function test_save_with_location_point(): void
    {
        $version = new Version();
        $version->minisiteId = 'test-minisite-123';
        $version->versionNumber = 1;
        $version->status = 'draft';
        $version->title = 'Test Minisite';
        $version->createdBy = 1;
        $version->siteJson = json_encode(array());

        // Set location point (latitude first, longitude second)
        $version->geo = new GeoPoint(37.7749, -122.4194); // San Francisco

        $saved = $this->repository->save($version);

        $this->assertNotNull($saved->id);
        $this->assertNotNull($saved->geo);
        $this->assertTrue($saved->geo->isSet());

        // Reload from database to verify POINT was saved correctly
        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertNotNull($found->geo);
        $this->assertTrue($found->geo->isSet());
        // Verify coordinates (with small tolerance for floating point)
        $this->assertEqualsWithDelta(-122.4194, $found->geo->getLng(), 0.0001);
        $this->assertEqualsWithDelta(37.7749, $found->geo->getLat(), 0.0001);
    }

    public function test_save_with_slugs(): void
    {
        $version = new Version();
        $version->minisiteId = 'test-minisite-123';
        $version->versionNumber = 1;
        $version->status = 'draft';
        $version->title = 'Test Minisite';
        $version->createdBy = 1;
        $version->siteJson = json_encode(array());

        // Set slugs
        $version->slugs = new SlugPair('acme-dental', 'downtown-location');
        $version->businessSlug = 'acme-dental';
        $version->locationSlug = 'downtown-location';

        $saved = $this->repository->save($version);

        $this->assertNotNull($saved->id);
        $this->assertNotNull($saved->slugs);
        $this->assertEquals('acme-dental', $saved->businessSlug);
        $this->assertEquals('downtown-location', $saved->locationSlug);

        // Reload from database to verify slugs were saved correctly
        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertNotNull($found->slugs);
        $this->assertEquals('acme-dental', $found->businessSlug);
        $this->assertEquals('downtown-location', $found->locationSlug);
        $this->assertEquals('acme-dental', $found->slugs->business);
        $this->assertEquals('downtown-location', $found->slugs->location);
    }

    public function test_find_by_minisite_id(): void
    {
        // Create multiple versions for the same minisite
        $minisiteId = 'test-minisite-123';

        $version1 = new Version();
        $version1->minisiteId = $minisiteId;
        $version1->versionNumber = 1;
        $version1->status = 'draft';
        $version1->title = 'Version 1';
        $version1->createdBy = 1;
        $version1->siteJson = json_encode(array());
        $this->repository->save($version1);

        $version2 = new Version();
        $version2->minisiteId = $minisiteId;
        $version2->versionNumber = 2;
        $version2->status = 'published';
        $version2->title = 'Version 2';
        $version2->createdBy = 1;
        $version2->siteJson = json_encode(array());
        $this->repository->save($version2);

        $versions = $this->repository->findByMinisiteId($minisiteId);

        $this->assertCount(2, $versions);
        // Should be ordered by versionNumber DESC
        $this->assertEquals(2, $versions[0]->versionNumber);
        $this->assertEquals(1, $versions[1]->versionNumber);
    }

    public function test_find_latest_version(): void
    {
        $minisiteId = 'test-minisite-123';

        $version1 = new Version();
        $version1->minisiteId = $minisiteId;
        $version1->versionNumber = 1;
        $version1->status = 'draft';
        $version1->title = 'Version 1';
        $version1->createdBy = 1;
        $version1->siteJson = json_encode(array());
        $this->repository->save($version1);

        $version2 = new Version();
        $version2->minisiteId = $minisiteId;
        $version2->versionNumber = 2;
        $version2->status = 'published';
        $version2->title = 'Version 2';
        $version2->createdBy = 1;
        $version2->siteJson = json_encode(array());
        $this->repository->save($version2);

        $latest = $this->repository->findLatestVersion($minisiteId);

        $this->assertNotNull($latest);
        $this->assertEquals(2, $latest->versionNumber);
        $this->assertEquals('Version 2', $latest->title);
    }

    public function test_find_latest_draft(): void
    {
        $minisiteId = 'test-minisite-123';

        $version1 = new Version();
        $version1->minisiteId = $minisiteId;
        $version1->versionNumber = 1;
        $version1->status = 'draft';
        $version1->title = 'Draft 1';
        $version1->createdBy = 1;
        $version1->siteJson = json_encode(array());
        $this->repository->save($version1);

        $version2 = new Version();
        $version2->minisiteId = $minisiteId;
        $version2->versionNumber = 2;
        $version2->status = 'published';
        $version2->title = 'Published 2';
        $version2->createdBy = 1;
        $version2->siteJson = json_encode(array());
        $this->repository->save($version2);

        $version3 = new Version();
        $version3->minisiteId = $minisiteId;
        $version3->versionNumber = 3;
        $version3->status = 'draft';
        $version3->title = 'Draft 3';
        $version3->createdBy = 1;
        $version3->siteJson = json_encode(array());
        $this->repository->save($version3);

        $latestDraft = $this->repository->findLatestDraft($minisiteId);

        $this->assertNotNull($latestDraft);
        $this->assertEquals(3, $latestDraft->versionNumber);
        $this->assertEquals('draft', $latestDraft->status);
        $this->assertEquals('Draft 3', $latestDraft->title);
    }

    public function test_find_published_version(): void
    {
        $minisiteId = 'test-minisite-123';

        $version1 = new Version();
        $version1->minisiteId = $minisiteId;
        $version1->versionNumber = 1;
        $version1->status = 'draft';
        $version1->title = 'Draft 1';
        $version1->createdBy = 1;
        $version1->siteJson = json_encode(array());
        $this->repository->save($version1);

        $version2 = new Version();
        $version2->minisiteId = $minisiteId;
        $version2->versionNumber = 2;
        $version2->status = 'published';
        $version2->title = 'Published 2';
        $version2->publishedAt = new \DateTimeImmutable();
        $version2->createdBy = 1;
        $version2->siteJson = json_encode(array());
        $this->repository->save($version2);

        $published = $this->repository->findPublishedVersion($minisiteId);

        $this->assertNotNull($published);
        $this->assertEquals(2, $published->versionNumber);
        $this->assertEquals('published', $published->status);
        $this->assertEquals('Published 2', $published->title);
    }

    public function test_get_next_version_number(): void
    {
        $minisiteId = 'test-minisite-123';

        // No versions yet - should return 1
        $next1 = $this->repository->getNextVersionNumber($minisiteId);
        $this->assertEquals(1, $next1);

        // Create version 1
        $version1 = new Version();
        $version1->minisiteId = $minisiteId;
        $version1->versionNumber = 1;
        $version1->status = 'draft';
        $version1->title = 'Version 1';
        $version1->createdBy = 1;
        $version1->siteJson = json_encode(array());
        $this->repository->save($version1);

        // Next should be 2
        $next2 = $this->repository->getNextVersionNumber($minisiteId);
        $this->assertEquals(2, $next2);

        // Create version 2
        $version2 = new Version();
        $version2->minisiteId = $minisiteId;
        $version2->versionNumber = 2;
        $version2->status = 'draft';
        $version2->title = 'Version 2';
        $version2->createdBy = 1;
        $version2->siteJson = json_encode(array());
        $this->repository->save($version2);

        // Next should be 3
        $next3 = $this->repository->getNextVersionNumber($minisiteId);
        $this->assertEquals(3, $next3);
    }

    public function test_get_latest_draft_for_editing(): void
    {
        $minisiteId = 'test-minisite-123';

        // Create a published version first (required for getLatestDraftForEditing)
        $published = new Version();
        $published->minisiteId = $minisiteId;
        $published->versionNumber = 1;
        $published->status = 'published';
        $published->title = 'Published Version';
        $published->createdBy = 1;
        $published->siteJson = json_encode(array());
        $this->repository->save($published);

        // Should return the published version (or create a draft from it)
        $draft1 = $this->repository->getLatestDraftForEditing($minisiteId);

        $this->assertNotNull($draft1);
        $this->assertEquals('draft', $draft1->status);
        $this->assertEquals(2, $draft1->versionNumber); // Created from published version, so version 2
        $this->assertEquals($minisiteId, $draft1->minisiteId);

        // Call again - should return the same draft
        $draft2 = $this->repository->getLatestDraftForEditing($minisiteId);

        $this->assertNotNull($draft2);
        $this->assertEquals($draft1->id, $draft2->id);
    }

    public function test_create_draft_from_version(): void
    {
        $minisiteId = 'test-minisite-123';

        // Create source version
        $source = new Version();
        $source->minisiteId = $minisiteId;
        $source->versionNumber = 1;
        $source->status = 'published';
        $source->title = 'Source Version';
        $source->name = 'Source Business';
        $source->city = 'San Francisco';
        $source->createdBy = 1;
        $source->siteJson = json_encode(array());
        $source->geo = new GeoPoint(37.7749, -122.4194); // lat, lng
        $source->slugs = new SlugPair('acme-dental', 'downtown-location');
        $source->businessSlug = 'acme-dental';
        $source->locationSlug = 'downtown-location';
        $savedSource = $this->repository->save($source);

        // Create draft from source
        $draft = $this->repository->createDraftFromVersion($savedSource);

        $this->assertNotNull($draft);
        $this->assertNotEquals($savedSource->id, $draft->id);
        $this->assertEquals('draft', $draft->status);
        $this->assertEquals(2, $draft->versionNumber); // Next version number
        $this->assertEquals($savedSource->title, $draft->title);
        $this->assertEquals($savedSource->name, $draft->name);
        $this->assertEquals($savedSource->city, $draft->city);
        $this->assertEquals($savedSource->id, $draft->sourceVersionId);
        // Verify location point was copied
        $this->assertNotNull($draft->geo);
        $this->assertEqualsWithDelta(-122.4194, $draft->geo->getLng(), 0.0001);
        $this->assertEqualsWithDelta(37.7749, $draft->geo->getLat(), 0.0001);
    }

    public function test_delete_version(): void
    {
        $version = new Version();
        $version->minisiteId = 'test-minisite-123';
        $version->versionNumber = 1;
        $version->status = 'draft';
        $version->title = 'Test Version';
        $version->createdBy = 1;
        $version->siteJson = json_encode(array());
        $saved = $this->repository->save($version);

        $this->assertNotNull($saved->id);
        $savedId = $saved->id; // Store ID before deletion

        // Delete the version
        $deleted = $this->repository->delete($savedId);

        $this->assertTrue($deleted);

        // Verify it's gone
        $found = $this->repository->findById($savedId);
        $this->assertNull($found);
    }

    public function test_enum_status_values(): void
    {
        $minisiteId = 'test-minisite-123';

        // Test draft status
        $draft = new Version();
        $draft->minisiteId = $minisiteId;
        $draft->versionNumber = 1;
        $draft->status = 'draft';
        $draft->title = 'Draft';
        $draft->createdBy = 1;
        $draft->siteJson = json_encode(array());
        $savedDraft = $this->repository->save($draft);
        $this->assertEquals('draft', $savedDraft->status);

        // Test published status
        $published = new Version();
        $published->minisiteId = $minisiteId;
        $published->versionNumber = 2;
        $published->status = 'published';
        $published->title = 'Published';
        $published->publishedAt = new \DateTimeImmutable();
        $published->createdBy = 1;
        $published->siteJson = json_encode(array());
        $savedPublished = $this->repository->save($published);
        $this->assertEquals('published', $savedPublished->status);

        // Test archived status
        $archived = new Version();
        $archived->minisiteId = $minisiteId;
        $archived->versionNumber = 3;
        $archived->status = 'archived';
        $archived->title = 'Archived';
        $archived->createdBy = 1;
        $archived->siteJson = json_encode(array());
        $savedArchived = $this->repository->save($archived);
        $this->assertEquals('archived', $savedArchived->status);
    }

    public function test_save_with_site_json(): void
    {
        $version = new Version();
        $version->minisiteId = 'test-minisite-123';
        $version->versionNumber = 1;
        $version->status = 'draft';
        $version->title = 'Test Minisite';
        $version->createdBy = 1;

        // Set siteJson as array (will be converted to JSON string)
        $siteJsonData = array(
            'sections' => array(
                'hero' => array('title' => 'Welcome'),
                'about' => array('content' => 'About us'),
            ),
        );
        $version->setSiteJsonFromArray($siteJsonData);

        $saved = $this->repository->save($version);

        $this->assertNotNull($saved->id);
        $this->assertIsString($saved->siteJson);

        // Reload and verify
        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertIsString($found->siteJson);
        $decoded = json_decode($found->siteJson, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('Welcome', $decoded['sections']['hero']['title']);
    }
}
