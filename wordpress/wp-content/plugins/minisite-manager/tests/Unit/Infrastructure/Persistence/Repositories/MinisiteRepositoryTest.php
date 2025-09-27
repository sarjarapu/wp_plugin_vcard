<?php
namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use PHPUnit\Framework\TestCase;

class MinisiteRepositoryTest extends TestCase
{
    private function makeEntity(): Minisite
    {
        return new Minisite(
            id: 'ms_u1',
            slugs: new SlugPair('biz', 'loc'),
            title: 'Title',
            name: 'Name',
            city: 'City',
            region: 'RG',
            countryCode: 'US',
            postalCode: '00000',
            geo: new GeoPoint(1.23, 4.56),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['x' => 'y'],
            searchTerms: null,
            status: 'draft',
            createdAt: null,
            updatedAt: null,
            publishedAt: null,
            createdBy: 1,
            updatedBy: 1,
            currentVersionId: null,
            isBookmarked: false,
            canEdit: false
        );
    }

    public function test_save_throws_on_optimistic_lock_failure(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 0; // simulate concurrent modification (no rows updated)
            public function query($sql) { return 0; }
            public function prepare($query, ...$args) {
                foreach ($args as $a) {
                    $query = preg_replace('/%[df]/', (string)(0 + $a), $query, 1);
                    if (preg_match('/%s/', $query)) $query = preg_replace('/%s/', "'".addslashes((string)$a)."'", $query, 1);
                }
                return $query;
            }
            public function get_row($query, $output = null) { return null; }
        };

        $repo = new MinisiteRepository($wpdb);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Concurrent modification detected');
        $repo->save($this->makeEntity(), expectedSiteVersion: 1);
    }

    public function test_update_coordinates_sets_and_clears_point(): void
    {
        $calls = [];
        $wpdb = new class($calls) extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 1; // simulate row exists
            public array $calls = [];
            public function __construct(&$calls) { $this->calls = &$calls; }
            public function query($sql) { $this->calls[] = $sql; return 1; }
            public function prepare($query, ...$args) {
                foreach ($args as $a) {
                    $query = preg_replace('/%[df]/', (string)(0 + $a), $query, 1);
                    if (preg_match('/%s/', $query)) $query = preg_replace('/%s/', "'".addslashes((string)$a)."'", $query, 1);
                }
                return $query;
            }
        };

        $repo = new MinisiteRepository($wpdb);
        // Set coordinates
        $repo->updateCoordinates('ms_u2', 10.5, 20.25, 99);
        // Clear coordinates
        $repo->updateCoordinates('ms_u2', null, null, 99);

        $this->assertCount(3, $wpdb->calls, 'should execute 3 UPDATE statements');
        $this->assertStringContainsString('location_point = ST_SRID(POINT(20.250000, 10.500000), 4326)', $wpdb->calls[1]);
        $this->assertStringContainsString('location_point = NULL', $wpdb->calls[2]);
    }
}
