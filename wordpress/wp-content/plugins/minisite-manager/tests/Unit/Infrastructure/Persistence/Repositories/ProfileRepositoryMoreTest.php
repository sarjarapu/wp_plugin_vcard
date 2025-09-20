<?php
declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;
use Minisite\Domain\Entities\Profile;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use PHPUnit\Framework\TestCase;

final class ProfileRepositoryMoreTest extends TestCase
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

        $repo = new ProfileRepository($wpdb);

        $profile = new Profile(
            id: 'test-id-123',                      // string $id
            slugs: new SlugPair('acme', 'nyc'),     // SlugPair $slugs
            title: 'Acme Dental',                   // string $title
            name: 'Acme Dental NYC Office',         // string $name
            city: 'New York',                       // string $city
            region: 'NY',                           // ?string $region
            countryCode: 'US',                      // string $countryCode
            postalCode: '10001',                    // ?string $postalCode
            geo: new GeoPoint(40.7128, -74.0060),   // GeoPoint $geo
            siteTemplate: 'v2025',                  // string $siteTemplate
            palette: 'blue',                        // string $palette
            industry: 'services',                   // string $industry
            defaultLocale: 'en-US',                 // string $defaultLocale
            schemaVersion: 1,                       // int $schemaVersion
            siteVersion: 5,                         // int $siteVersion
            siteJson: ['about' => 'Great dental care'],  // array $siteJson
            searchTerms: 'acme dental nyc',          // ?string $searchTerms
            status: 'published',                     // string $status
            createdAt: new \DateTimeImmutable('2025-09-12 00:00:00'),  // ?DateTimeImmutable
            updatedAt: new \DateTimeImmutable('2025-09-12 12:00:00'),  // ?DateTimeImmutable
            publishedAt: new \DateTimeImmutable('2025-09-13 09:00:00'),// ?DateTimeImmutable
            createdBy: 1,                             // ?int $createdBy
            updatedBy: 2                              // ?int $updatedBy
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected (optimistic lock failed).');
        $repo->save($profile, 5);
    }

    public function testSaveSyncsGeoPointWhenLatLngPresent(): void
    {
        $wpdb = $this->mockWpdb();

        $wpdb->method('prepare')->willReturnCallback(fn($q, ...$a) => (new \wpdb)->prepare($q, ...$a));

        $callCount = 0;
        $wpdb->method('query')->willReturnCallback(function(string $sql) use (&$callCount, $wpdb) {
            $callCount++;
            // Mark one row affected to satisfy optimistic lock check
            $wpdb->rows_affected = 1;
            return 1;
        });

        // After save(), repository re-fetches the row:
        $wpdb->method('get_row')->willReturn([
            'id' => 999,
            'business_slug' => 'acme',
            'location_slug' => 'nyc',
            'title' => 'Acme Dental',
            'name' => 'Acme Dental NYC Office',
            'city' => 'New York',
            'region' => 'NY',
            'country_code' => 'US',
            'postal_code' => '10001',
            'lat' => 40.7128,
            'lng' => -74.0060,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 6, // incremented
            'site_json' => wp_json_encode(['about' => 'Great dental care']),
            'search_terms' => 'acme dental nyc',
            'status' => 'published',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-02 00:00:00',
            'published_at' => null,
            'created_by' => 1,
            'updated_by' => 2,
        ]);

        $repo = new ProfileRepository($wpdb);

        $profile = new Profile(
            id: 'test-id-123',                      // string $id
            slugs: new SlugPair('acme', 'nyc'),     // SlugPair $slugs
            title: 'Acme Dental',                   // string $title
            name: 'Acme Dental NYC Office',         // string $name
            city: 'New York',                       // string $city
            region: 'NY',                           // ?string $region
            countryCode: 'US',                      // string $countryCode
            postalCode: '10001',                    // ?string $postalCode
            geo: new GeoPoint(40.7128, -74.0060),   // GeoPoint $geo
            siteTemplate: 'v2025',                  // string $siteTemplate
            palette: 'blue',                        // string $palette
            industry: 'services',                   // string $industry
            defaultLocale: 'en-US',                 // string $defaultLocale
            schemaVersion: 1,                       // int $schemaVersion
            siteVersion: 5,                         // int $siteVersion
            siteJson: ['about' => 'Great dental care'],  // array $siteJson
            searchTerms: 'acme dental nyc',          // ?string $searchTerms
            status: 'published',                     // string $status
            createdAt: new \DateTimeImmutable('2025-09-12 00:00:00'),  // ?DateTimeImmutable
            updatedAt: new \DateTimeImmutable('2025-09-12 12:00:00'),  // ?DateTimeImmutable
            publishedAt: new \DateTimeImmutable('2025-09-13 09:00:00'),// ?DateTimeImmutable
            createdBy: 1,                             // ?int $createdBy
            updatedBy: 2                              // ?int $updatedBy
        );

        $saved = $repo->save($profile, 5);
        $this->assertSame(6, $saved->siteVersion);
        $this->assertGreaterThanOrEqual(2, $callCount, 'expects at least the UPDATE and the POINT sync SQL to run');
    }
}
