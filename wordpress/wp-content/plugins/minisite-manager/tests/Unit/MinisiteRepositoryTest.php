<?php
namespace Tests\Unit;

use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Domain\ValueObjects\GeoPoint;
use PHPUnit\Framework\TestCase;

final class MinisiteRepositoryTest extends TestCase
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

        $repo = new MinisiteRepository($wpdb);
        $this->assertNull($repo->findBySlugs(new SlugPair('nope','missing')));
    }

    public function testSaveWithOptimisticLockUpdatesAndReturnsFresh(): void
    {
        $wpdb = $this->fakeWpdb();

        // Mock the update query to return 1 affected row (success)
        $wpdb->method('query')->willReturn(true);
        $wpdb->method('rows_affected')->willReturn(1);

        // Mock the fresh fetch after save
        $freshRow = [
            'id' => 'test-id-123',
            'business_slug' => 'acme-dental',
            'location_slug' => 'dallas',
            'title' => 'Acme Dental — Dallas',
            'name' => 'Acme Dental',
            'city' => 'Dallas',
            'region' => 'TX',
            'country_code' => 'US',
            'postal_code' => '75201',
            'location_point' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'healthcare',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 2, // Incremented after save
            'site_json' => '{"test": "data"}',
            'search_terms' => 'acme dental dallas healthcare blue',
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
            industry: 'healthcare',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1, // Expected version
            siteJson: ['test' => 'data'],
            searchTerms: 'acme dental dallas healthcare blue',
            status: 'published',
            createdAt: new \DateTimeImmutable('2024-01-01 00:00:00'),
            updatedAt: new \DateTimeImmutable('2024-01-01 00:00:00'),
            publishedAt: new \DateTimeImmutable('2024-01-01 00:00:00'),
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: null,
        );

        $result = $repo->save($minisite, 1); // Expected version 1

        $this->assertInstanceOf(Minisite::class, $result);
        $this->assertEquals(2, $result->siteVersion); // Should be incremented
    }

    private function fakeWpdb(): \Mockery\MockInterface
    {
        $wpdb = \Mockery::mock(\wpdb::class);
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) {
            return $sql; // Simple mock - just return the SQL
        });
        return $wpdb;
    }
}
