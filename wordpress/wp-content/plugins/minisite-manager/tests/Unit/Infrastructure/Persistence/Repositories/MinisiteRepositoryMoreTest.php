<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use PHPUnit\Framework\TestCase;

final class MinisiteRepositoryMoreTest extends TestCase
{
    private function mockWpdb(): \wpdb
    {
        $wpdb = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare','get_row','query','insert'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        return $wpdb;
    }

    public function testSaveOptimisticLockFails(): void
    {
        $wpdb = $this->mockWpdb();

        // prepare passthrough
        $wpdb->method('prepare')->willReturnCallback(fn($q, ...$a) => (new \wpdb)->prepare($q, ...$a));

        // UPDATE returns 0 rows_affected
        $wpdb->method('query')->willReturn(0);

        $repo = new MinisiteRepository($wpdb);

        $minisite = new Minisite(
            id: 'test-id-123',                      // string $id
            slugs: new SlugPair('acme', 'nyc'),     // SlugPair $slugs
            title: 'Acme Dental',                   // string $title
            name: 'Acme Dental NYC Office',         // string $name
            city: 'New York',                       // string $city
            region: 'NY',                           // ?string $region
            countryCode: 'US',                      // string $countryCode
            postalCode: '10001',                    // ?string $postalCode
            geo: new GeoPoint(40.7128, -74.0060),  // GeoPoint $geo
            siteTemplate: 'v2025',                  // string $siteTemplate
            palette: 'blue',                        // string $palette
            industry: 'healthcare',                 // string $industry
            defaultLocale: 'en-US',                 // string $defaultLocale
            schemaVersion: 1,                       // int $schemaVersion
            siteVersion: 1,                         // int $siteVersion
            siteJson: ['test' => 'data'],           // array $siteJson
            searchTerms: 'acme dental nyc',         // ?string $searchTerms
            status: 'published',                    // string $status
            createdAt: new \DateTimeImmutable(),    // ?\DateTimeImmutable $createdAt
            updatedAt: new \DateTimeImmutable(),    // ?\DateTimeImmutable $updatedAt
            publishedAt: new \DateTimeImmutable(),  // ?\DateTimeImmutable $publishedAt
            createdBy: 1,                           // ?int $createdBy
            updatedBy: 1,                           // ?int $updatedBy
            currentVersionId: null,                 // ?int $currentVersionId
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected (optimistic lock failed).');

        $repo->save($minisite, 1); // Expected version 1, but someone else modified it
    }

    public function testSaveWithGeoPointUpdatesLocation(): void
    {
        $wpdb = $this->mockWpdb();

        // prepare passthrough
        $wpdb->method('prepare')->willReturnCallback(fn($q, ...$a) => (new \wpdb)->prepare($q, ...$a));

        // First UPDATE (main data) succeeds
        $wpdb->method('query')->willReturnOnConsecutiveCalls(1, 1);

        // Mock the fresh fetch after save
        $freshRow = [
            'id' => 'test-id-123',
            'business_slug' => 'acme',
            'location_slug' => 'nyc',
            'title' => 'Acme Dental',
            'name' => 'Acme Dental NYC Office',
            'city' => 'New York',
            'region' => 'NY',
            'country_code' => 'US',
            'postal_code' => '10001',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'healthcare',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 2,
            'site_json' => '{"test": "data"}',
            'search_terms' => 'acme dental nyc',
            'status' => 'published',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
            'published_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
            'updated_by' => 1,
            '_minisite_current_version_id' => null,
        ];
        $wpdb->method('get_row')->willReturn($freshRow);

        $repo = new MinisiteRepository($wpdb);

        $minisite = new Minisite(
            id: 'test-id-123',
            slugs: new SlugPair('acme', 'nyc'),
            title: 'Acme Dental',
            name: 'Acme Dental NYC Office',
            city: 'New York',
            region: 'NY',
            countryCode: 'US',
            postalCode: '10001',
            geo: new GeoPoint(40.7128, -74.0060), // Has coordinates
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'healthcare',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['test' => 'data'],
            searchTerms: 'acme dental nyc',
            status: 'published',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            publishedAt: new \DateTimeImmutable(),
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: null,
        );

        $result = $repo->save($minisite, 1);

        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertEquals(2, $result->siteVersion);
    }
}
