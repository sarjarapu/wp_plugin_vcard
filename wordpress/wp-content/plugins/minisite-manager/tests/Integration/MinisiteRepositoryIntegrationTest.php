<?php
namespace Tests\Integration;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;
use Tests\Support\SchemaLoader;

/**
 * @group integration
 */
class MinisiteRepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;
    private FakeWpdb $db;
    private MinisiteRepository $repo;

    protected function setUp(): void
    {
        $host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('MYSQL_PORT') ?: '3307';
        $db   = getenv('MYSQL_DATABASE') ?: 'minisite_test';
        $user = getenv('MYSQL_USER') ?: 'minisite';
        $pass = getenv('MYSQL_PASSWORD') ?: 'minisite';

        $this->pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->db = new FakeWpdb($this->pdo);

        SchemaLoader::rebuild($this->pdo, 'wp_');
        $this->repo = new MinisiteRepository($this->db);
    }

    public function test_insert_and_fetch_with_geometry(): void
    {
        $entity = new Minisite(
            id: 'ms_1',
            slugs: new SlugPair('acme', 'nyc'),
            title: 'Title',
            name: 'Acme NYC',
            city: 'New York',
            region: 'NY',
            countryCode: 'US',
            postalCode: '10001',
            geo: new GeoPoint(40.7128, -74.0060),
            siteTemplate: 'v2025',
            palette: 'blue',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['hello' => 'world'],
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

        $inserted = $this->repo->insert($entity);
        $this->assertSame('ms_1', $inserted->id);
        $this->assertSame('acme', $inserted->slugs->business);
        $this->assertSame('nyc', $inserted->slugs->location);
        $this->assertNotNull($inserted->geo);
        $this->assertEqualsWithDelta(40.7128, $inserted->geo->lat, 0.0001);
        $this->assertEqualsWithDelta(-74.0060, $inserted->geo->lng, 0.0001);
    }

    public function test_save_increments_site_version_and_updates_point(): void
    {
        // Seed row without geometry
        $this->pdo->exec("INSERT INTO wp_minisites (id,business_slug,location_slug,title,name,city,region,country_code,postal_code,site_template,palette,industry,default_locale,schema_version,site_version,site_json,search_terms,status,created_by,updated_by) VALUES (
            'ms_2','acme','bos','Title','Acme BOS','Boston','MA','US','02108','v2025','blue','services','en-US',1,1,'{}',NULL,'draft',1,1
        )");

        $entity = new Minisite(
            id: 'ms_2',
            slugs: new SlugPair('acme', 'bos'),
            title: 'New Title',
            name: 'Acme BOS',
            city: 'Boston',
            region: 'MA',
            countryCode: 'US',
            postalCode: '02108',
            geo: new GeoPoint(42.3601, -71.0589),
            siteTemplate: 'v2025',
            palette: 'green',
            industry: 'services',
            defaultLocale: 'en-US',
            schemaVersion: 1,
            siteVersion: 1,
            siteJson: ['a' => 'b'],
            searchTerms: null,
            status: 'draft',
            createdAt: null,
            updatedAt: null,
            publishedAt: null,
            createdBy: 1,
            updatedBy: 2,
            currentVersionId: null,
            isBookmarked: false,
            canEdit: false
        );

        $updated = $this->repo->save($entity, expectedSiteVersion: 1);
        $this->assertSame('New Title', $updated->title);
        $this->assertSame(2, $updated->siteVersion, 'site_version should increment');
        $this->assertNotNull($updated->geo);
        $this->assertEqualsWithDelta(42.3601, $updated->geo->lat, 0.0001);
        $this->assertEqualsWithDelta(-71.0589, $updated->geo->lng, 0.0001);
    }
}
