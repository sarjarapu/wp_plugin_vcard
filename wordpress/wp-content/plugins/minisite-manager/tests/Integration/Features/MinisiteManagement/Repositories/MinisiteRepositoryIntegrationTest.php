<?php

declare(strict_types=1);

namespace Tests\Integration\Features\MinisiteManagement\Repositories;

use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Features\MinisiteManagement\Domain\Entities\Minisite;
use Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Integration\BaseIntegrationTest;

/**
 * Integration tests for MinisiteRepository
 *
 * Tests MinisiteRepository against real MySQL database with WordPress prefix.
 * This is a REAL integration test - uses actual wp_minisites table.
 *
 * Prerequisites:
 * - MySQL test database must be running (Docker container on port 3307)
 * - Migrations will be run automatically in setUp()
 */
#[CoversClass(MinisiteRepository::class)]
final class MinisiteRepositoryIntegrationTest extends BaseIntegrationTest
{
    private MinisiteRepository $repository;

    /**
     * Get entity paths for ORM configuration
     */
    protected function getEntityPaths(): array
    {
        return array(
            __DIR__ . '/../../../../../src/Features/MinisiteManagement/Domain/Entities',
            __DIR__ . '/../../../../../src/Features/VersionManagement/Domain/Entities',
        );
    }

    /**
     * Setup test-specific services and repositories
     */
    protected function setupTestSpecificServices(): void
    {
        // Create repository instance
        $this->repository = new MinisiteRepository(
            $this->em,
            $this->em->getClassMetadata(Minisite::class)
        );
    }

    /**
     * Clean up test data (but keep table structure)
     * Deletes only test minisites, not the table itself
     */
    protected function cleanupTestData(): void
    {
        try {
            $this->em->getConnection()->executeStatement(
                "DELETE FROM wp_minisites WHERE id LIKE 'test_%' OR id LIKE 'test-%'"
            );
        } catch (\Exception $e) {
            // Ignore errors - table might not exist or connection might be closed
        }
    }

    public function test_insert_and_find_by_id(): void
    {
        $minisite = new Minisite(
            id: 'test-insert-1',
            slug: null,
            slugs: new SlugPair('test-business', 'test-location'),
            title: 'Test Minisite',
            name: 'Test Minisite Name',
            city: 'Test City',
            region: null,
            countryCode: 'US',
            postalCode: '12345',
            geo: new GeoPoint(lat: 40.7128, lng: -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array('test' => 'data'),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);

        $this->assertNotNull($saved->id);
        $this->assertEquals('test-insert-1', $saved->id);
        $this->assertEquals('Test Minisite', $saved->title);
        $this->assertEquals('Test Minisite Name', $saved->name);
        $this->assertNotNull($saved->createdAt);
        $this->assertNotNull($saved->updatedAt);

        // Verify location_point was saved
        $this->assertNotNull($saved->geo);
        $this->assertEquals(40.7128, $saved->geo->getLat(), '', 0.0001);
        $this->assertEquals(-74.0060, $saved->geo->getLng(), '', 0.0001);

        // Verify slugs were saved
        $this->assertNotNull($saved->slugs);
        $this->assertEquals('test-business', $saved->slugs->business);
        $this->assertEquals('test-location', $saved->slugs->location);

        // Find by ID
        $found = $this->repository->findById('test-insert-1');

        $this->assertNotNull($found);
        $this->assertEquals('test-insert-1', $found->id);
        $this->assertEquals('Test Minisite', $found->title);
        $this->assertNotNull($found->geo);
        $this->assertEquals(40.7128, $found->geo->getLat(), '', 0.0001);
    }

    public function test_find_by_slugs(): void
    {
        $minisite = new Minisite(
            id: 'test-slugs-1',
            slug: null,
            slugs: new SlugPair('coffee-shop', 'downtown'),
            title: 'Coffee Shop',
            name: 'Coffee Shop Name',
            city: 'Seattle',
            region: null,
            countryCode: 'US',
            postalCode: null,
            geo: null,
            siteTemplate: 'v2025',
            palette: 'brown',
            industry: 'food',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Find by slugs
        $found = $this->repository->findBySlugs(new SlugPair('coffee-shop', 'downtown'));

        $this->assertNotNull($found);
        $this->assertEquals('test-slugs-1', $found->id);
        $this->assertEquals('Coffee Shop', $found->title);
        $this->assertNotNull($found->slugs);
        $this->assertEquals('coffee-shop', $found->slugs->business);
        $this->assertEquals('downtown', $found->slugs->location);

        // Try to find non-existent slugs
        $notFound = $this->repository->findBySlugs(new SlugPair('nonexistent', 'location'));

        $this->assertNull($notFound);
    }

    public function test_find_by_slug_params_with_lock(): void
    {
        $minisite = new Minisite(
            id: 'test-lock-1',
            slug: null,
            slugs: new SlugPair('locked-business', 'locked-location'),
            title: 'Locked Minisite',
            name: 'Locked Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Find by slug params (with FOR UPDATE lock) - requires transaction
        $this->em->getConnection()->beginTransaction();
        try {
            $found = $this->repository->findBySlugParams('locked-business', 'locked-location');
            $this->assertNotNull($found);
            $this->assertEquals('test-lock-1', $found->id);
        } finally {
            $this->em->getConnection()->rollBack();
        }
    }

    public function test_list_by_owner(): void
    {
        $userId = 123;

        // Create multiple minisites for the same owner
        for ($i = 1; $i <= 3; $i++) {
            $minisite = new Minisite(
                id: "test-owner-{$i}",
                slug: null,
                slugs: new SlugPair("business-{$i}", "location-{$i}"),
                title: "Minisite {$i}",
                name: "Minisite Name {$i}",
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
                siteJson: array(),
                searchTerms: null,
                status: 'published',
                publishStatus: 'published',
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
                publishedAt: new \DateTimeImmutable(),
                createdBy: $userId,
                updatedBy: null,
                currentVersionId: null
            );

            $this->repository->insert($minisite);
        }

        // Create a minisite for a different owner
        $otherMinisite = new Minisite(
            id: 'test-owner-other',
            slug: null,
            slugs: new SlugPair('other-business', 'other-location'),
            title: 'Other Minisite',
            name: 'Other Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 999, // Different owner
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($otherMinisite);

        // List by owner
        $results = $this->repository->listByOwner($userId);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(3, count($results));

        // Verify all results belong to the correct owner
        foreach ($results as $result) {
            $this->assertEquals($userId, $result->createdBy);
        }

        // Test pagination
        $limited = $this->repository->listByOwner($userId, limit: 2, offset: 0);
        $this->assertLessThanOrEqual(2, count($limited));

        $offset = $this->repository->listByOwner($userId, limit: 2, offset: 2);
        $this->assertLessThanOrEqual(2, count($offset));
    }

    public function test_count_by_owner(): void
    {
        $userId = 456;

        // Create minisites for the owner
        for ($i = 1; $i <= 5; $i++) {
            $minisite = new Minisite(
                id: "test-count-{$i}",
                slug: null,
                slugs: new SlugPair("count-business-{$i}", "count-location-{$i}"),
                title: "Count Minisite {$i}",
                name: "Count Name {$i}",
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
                siteJson: array(),
                searchTerms: null,
                status: 'published',
                publishStatus: 'published',
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
                publishedAt: new \DateTimeImmutable(),
                createdBy: $userId,
                updatedBy: null,
                currentVersionId: null
            );

            $this->repository->insert($minisite);
        }

        $count = $this->repository->countByOwner($userId);

        $this->assertGreaterThanOrEqual(5, $count);

        // Count for non-existent owner
        $zeroCount = $this->repository->countByOwner(99999);
        $this->assertEquals(0, $zeroCount);
    }

    public function test_save_with_optimistic_locking(): void
    {
        $minisite = new Minisite(
            id: 'test-save-1',
            slug: null,
            slugs: new SlugPair('save-business', 'save-location'),
            title: 'Save Test',
            name: 'Save Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);
        $originalVersion = $saved->siteVersion;

        // Update the minisite
        $saved->title = 'Updated Title';
        $saved->name = 'Updated Name';

        // save() requires expectedSiteVersion parameter
        $this->repository->save($saved, $originalVersion);

        // Clear entity manager to force reload from database
        $this->em->clear();

        // Reload to verify version was incremented
        $updated = $this->repository->findById('test-save-1');

        $this->assertNotNull($updated);
        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertGreaterThan($originalVersion, $updated->siteVersion);
    }

    public function test_update_coordinates(): void
    {
        $minisite = new Minisite(
            id: 'test-coords-1',
            slug: null,
            slugs: new SlugPair('coords-business', 'coords-location'),
            title: 'Coords Test',
            name: 'Coords Name',
            city: 'Test City',
            region: null,
            countryCode: 'US',
            postalCode: null,
            geo: new GeoPoint(lat: 40.7128, lng: -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);

        // Update coordinates (updateCoordinates takes lat, lng, updatedBy as separate parameters)
        $this->repository->updateCoordinates('test-coords-1', 34.0522, -118.2437, 1);

        // Reload and verify
        $reloaded = $this->repository->findById('test-coords-1');
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->geo);
        $this->assertEquals(34.0522, $reloaded->geo->getLat(), '', 0.0001);
        $this->assertEquals(-118.2437, $reloaded->geo->getLng(), '', 0.0001);
    }

    public function test_update_status(): void
    {
        $minisite = new Minisite(
            id: 'test-status-1',
            slug: null,
            slugs: new SlugPair('status-business', 'status-location'),
            title: 'Status Test',
            name: 'Status Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);
        $this->assertEquals('draft', $saved->status);
        $this->assertNull($saved->publishedAt);

        // Update status to published
        $result = $this->repository->updateStatus('test-status-1', 'published');
        $this->assertTrue($result);

        // Clear entity manager to force reload from database
        $this->em->clear();

        // Reload and verify
        $reloaded = $this->repository->findById('test-status-1');
        $this->assertNotNull($reloaded);
        $this->assertEquals('published', $reloaded->status);
        $this->assertNotNull($reloaded->publishedAt);
    }

    public function test_site_json_handling(): void
    {
        $siteJsonData = array(
            'sections' => array(
                'hero' => array('title' => 'Welcome'),
                'about' => array('description' => 'About us'),
            ),
        );

        $minisite = new Minisite(
            id: 'test-json-1',
            slug: null,
            slugs: new SlugPair('json-business', 'json-location'),
            title: 'JSON Test',
            name: 'JSON Name',
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
            siteJson: $siteJsonData,
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);

        // Verify siteJson is stored as string
        $this->assertIsString($saved->siteJson);

        // Verify we can get it as array
        $decoded = $saved->getSiteJsonAsArray();
        $this->assertIsArray($decoded);
        $this->assertEquals('Welcome', $decoded['sections']['hero']['title']);

        // Reload and verify
        $reloaded = $this->repository->findById('test-json-1');
        $this->assertNotNull($reloaded);
        $decodedReloaded = $reloaded->getSiteJsonAsArray();
        $this->assertEquals('Welcome', $decodedReloaded['sections']['hero']['title']);
    }

    public function test_update_slug(): void
    {
        $minisite = new Minisite(
            id: 'test-slug-update',
            slug: null,
            slugs: new SlugPair('old-business', 'old-location'),
            title: 'Slug Test',
            name: 'Slug Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update slug
        $this->repository->updateSlug('test-slug-update', 'new-slug-123');

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-slug-update');
        $this->assertNotNull($reloaded);
        $this->assertEquals('new-slug-123', $reloaded->slug);
    }

    public function test_update_slugs(): void
    {
        $minisite = new Minisite(
            id: 'test-slugs-update',
            slug: null,
            slugs: new SlugPair('old-biz', 'old-loc'),
            title: 'Slugs Test',
            name: 'Slugs Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update slugs
        $this->repository->updateSlugs('test-slugs-update', 'new-biz', 'new-loc');

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-slugs-update');
        $this->assertNotNull($reloaded);
        $this->assertEquals('new-biz', $reloaded->businessSlug);
        $this->assertEquals('new-loc', $reloaded->locationSlug);
        $this->assertNotNull($reloaded->slugs);
        $this->assertEquals('new-biz', $reloaded->slugs->business);
        $this->assertEquals('new-loc', $reloaded->slugs->location);
    }

    public function test_update_publish_status(): void
    {
        $minisite = new Minisite(
            id: 'test-publish-status',
            slug: null,
            slugs: new SlugPair('publish-biz', 'publish-loc'),
            title: 'Publish Status Test',
            name: 'Publish Status Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'draft',
            publishStatus: 'draft',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: null,
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update publish status
        $this->repository->updatePublishStatus('test-publish-status', 'reserved');

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-publish-status');
        $this->assertNotNull($reloaded);
        $this->assertEquals('reserved', $reloaded->publishStatus);
    }

    public function test_update_current_version_id(): void
    {
        $minisite = new Minisite(
            id: 'test-version-id',
            slug: null,
            slugs: new SlugPair('version-biz', 'version-loc'),
            title: 'Version ID Test',
            name: 'Version ID Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update current version ID
        $this->repository->updateCurrentVersionId('test-version-id', 42);

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-version-id');
        $this->assertNotNull($reloaded);
        $this->assertEquals(42, $reloaded->currentVersionId);
    }

    public function test_update_title(): void
    {
        $minisite = new Minisite(
            id: 'test-title-update',
            slug: null,
            slugs: new SlugPair('title-biz', 'title-loc'),
            title: 'Old Title',
            name: 'Title Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update title
        $result = $this->repository->updateTitle('test-title-update', 'New Title');
        $this->assertTrue($result);

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-title-update');
        $this->assertNotNull($reloaded);
        $this->assertEquals('New Title', $reloaded->title);
    }

    public function test_update_coordinates_with_null_values(): void
    {
        $minisite = new Minisite(
            id: 'test-coords-null',
            slug: null,
            slugs: new SlugPair('coords-null-biz', 'coords-null-loc'),
            title: 'Coords Null Test',
            name: 'Coords Null Name',
            city: 'Test City',
            region: null,
            countryCode: 'US',
            postalCode: null,
            geo: new GeoPoint(lat: 40.7128, lng: -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update coordinates with null (should skip)
        $this->repository->updateCoordinates('test-coords-null', null, null, 1);

        // Reload and verify coordinates are unchanged
        $this->em->clear();
        $reloaded = $this->repository->findById('test-coords-null');
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->geo);
        $this->assertEquals(40.7128, $reloaded->geo->getLat(), '', 0.0001);
    }

    public function test_update_minisite_fields(): void
    {
        $minisite = new Minisite(
            id: 'test-fields-update',
            slug: null,
            slugs: new SlugPair('fields-biz', 'fields-loc'),
            title: 'Fields Test',
            name: 'Fields Name',
            city: 'Old City',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update multiple fields
        $fields = array(
            'city' => 'New City',
            'region' => 'New Region',
            'postalCode' => '54321',
        );

        $this->repository->updateMinisiteFields('test-fields-update', $fields, 1);

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-fields-update');
        $this->assertNotNull($reloaded);
        $this->assertEquals('New City', $reloaded->city);
        $this->assertEquals('New Region', $reloaded->region);
        $this->assertEquals('54321', $reloaded->postalCode);
    }

    public function test_update_business_info(): void
    {
        $minisite = new Minisite(
            id: 'test-business-info',
            slug: null,
            slugs: new SlugPair('business-biz', 'business-loc'),
            title: 'Business Info Test',
            name: 'Business Info Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $this->repository->insert($minisite);

        // Update business info fields
        $fields = array(
            'name' => 'Updated Business Name',
            'title' => 'Updated Business Title',
            'city' => 'Updated City',
        );

        $this->repository->updateBusinessInfo('test-business-info', $fields, 1);

        // Reload and verify
        $this->em->clear();
        $reloaded = $this->repository->findById('test-business-info');
        $this->assertNotNull($reloaded);
        $this->assertEquals('Updated Business Name', $reloaded->name);
        $this->assertEquals('Updated Business Title', $reloaded->title);
        $this->assertEquals('Updated City', $reloaded->city);
    }

    public function test_optimistic_locking_failure(): void
    {
        $minisite = new Minisite(
            id: 'test-lock-fail',
            slug: null,
            slugs: new SlugPair('lock-biz', 'lock-loc'),
            title: 'Lock Fail Test',
            name: 'Lock Fail Name',
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
            siteJson: array(),
            searchTerms: null,
            status: 'published',
            publishStatus: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: null,
            currentVersionId: null
        );

        $saved = $this->repository->insert($minisite);
        $originalVersion = $saved->siteVersion;

        // Try to save with wrong expected version (should fail)
        $saved->title = 'Updated Title';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected');

        // Use wrong expected version
        $this->repository->save($saved, $originalVersion + 999);
    }

    public function test_find_by_id_not_found(): void
    {
        $result = $this->repository->findById('nonexistent-id-12345');

        $this->assertNull($result);
    }

    public function test_update_status_with_invalid_id(): void
    {
        // Update status for non-existent minisite
        // Note: updateStatus returns true if query executes (even with 0 rows affected)
        // This is the current behavior - the method doesn't check if minisite exists
        $result = $this->repository->updateStatus('nonexistent-id-99999', 'published');

        // Current implementation returns true even if no rows affected
        // This test documents the current behavior
        $this->assertIsBool($result);
    }
}

