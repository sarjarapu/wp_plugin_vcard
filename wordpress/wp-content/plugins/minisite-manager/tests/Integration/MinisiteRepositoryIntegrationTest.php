<?php
namespace Tests\Integration;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\GeoPoint;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

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

        $this->createSchema();
        $this->repo = new MinisiteRepository($this->db);
    }

    private function createSchema(): void
    {
        // Drop and recreate table for clean slate
        $this->pdo->exec("DROP TABLE IF EXISTS wp_minisites");
        $this->pdo->exec(<<<SQL
            CREATE TABLE wp_minisites (
                id VARCHAR(64) PRIMARY KEY,
                slug VARCHAR(191) NULL,
                business_slug VARCHAR(191) NOT NULL,
                location_slug VARCHAR(191) NOT NULL,
                title VARCHAR(191) NOT NULL,
                name VARCHAR(191) NOT NULL,
                city VARCHAR(191) NOT NULL,
                region VARCHAR(191) NULL,
                country_code CHAR(2) NOT NULL,
                postal_code VARCHAR(32) NULL,
                site_template VARCHAR(64) NOT NULL,
                palette VARCHAR(64) NOT NULL,
                industry VARCHAR(64) NOT NULL,
                default_locale VARCHAR(16) NOT NULL,
                schema_version INT NOT NULL,
                site_version INT NOT NULL DEFAULT 1,
                site_json JSON NOT NULL,
                search_terms TEXT NULL,
                status VARCHAR(32) NOT NULL,
                publish_status VARCHAR(32) NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                published_at DATETIME NULL,
                created_by INT NULL,
                updated_by INT NULL,
                _minisite_current_version_id INT NULL,
                location_point POINT NULL SRID 4326,
                SPATIAL INDEX(location_point),
                UNIQUE KEY uniq_slugs (business_slug, location_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
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
