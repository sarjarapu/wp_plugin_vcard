<?php
namespace Tests\Unit;

use Minisite\Infrastructure\Persistence\Repositories\ProfileRepository;
use Minisite\Domain\Entities\Profile;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;
use PHPUnit\Framework\TestCase;

final class ProfileRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function testFindBySlugsReturnsNullWhenMissing(): void
    {
        $wpdb = $this->fakeWpdb();
        $wpdb->method('get_row')->willReturn(null);

        $repo = new ProfileRepository($wpdb);
        $this->assertNull($repo->findBySlugs(new SlugPair('nope','missing')));
    }

    public function testSaveWithOptimisticLockUpdatesAndReturnsFresh(): void
    {
        $wpdb = $this->fakeWpdb();

        // Simulate UPDATE affecting 1 row
        $wpdb->method('query')->willReturnCallback(function($sql) use ($wpdb) {
            $wpdb->rows_affected = 1;
            return 1;
        });

        // After saving, repo re-fetches: return a row
        $wpdb->method('get_row')->willReturn([
            'id' => 1,
            'business_slug' => 'acme-dental',
            'location_slug' => 'dallas',
            'title' => 'Acme Dental — Dallas',
            'name' => 'Acme Dental',
            'city' => 'Dallas',
            'region' => 'TX',
            'country_code' => 'US',
            'postal_code' => '75201',
            'lat' => 32.7767,
            'lng' => -96.7970,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 2,
            'site_json' => json_encode(['ok'=>true]),
            'search_terms' => 'acme dental dallas',
            'status' => 'published',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
            'published_at'=> null,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $repo = new ProfileRepository($wpdb);
        $profile = new Profile(
            id: 'test-id-123',
            slugs: new SlugPair('acme-dental','dallas'),
            title: 'Acme Dental — Dallas',
            name: 'Acme Dental',
            city: 'Dallas',
            region: 'TX',
            countryCode: 'US',
            postalCode: '75201',
            geo: new GeoPoint(32.7767, -96.7970),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['ok'=>true],
            searchTerms: null,
            status: 'published',
            createdAt: null,
            updatedAt: null,
            publishedAt: null,
            createdBy: 1,
            updatedBy: 1
        );

        $fresh = $repo->save($profile, expectedSiteVersion: 1);
        $this->assertSame(2, $fresh->siteVersion);
        $this->assertSame('Dallas', $fresh->city);
    }

    private function fakeWpdb(): \wpdb
    {
        // Create a PHPUnit mock that looks like wpdb
        $wpdb = $this->getMockBuilder(\wpdb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare','get_row','get_results','query'])
            ->getMock();

        // Simulate $wpdb->prefix
        $wpdb->prefix = 'wp_';

        // prepare just returns sprintf-like string; in tests we can bypass strict behavior
        $wpdb->method('prepare')->willReturnCallback(function($query, ...$args) {
            // naive replacement for tests only
            foreach ($args as $a) {
                $query = preg_replace('/%[dfs]/', is_numeric($a) ? (string)$a : "'" . addslashes((string)$a) . "'", $query, 1);
            }
            return $query;
        });

        return $wpdb;
    }
}