<?php
namespace Tests\Unit\Infrastructure\Persistence\Repositories;

use Minisite\Domain\Entities\Minisite;
use Minisite\Domain\ValueObjects\SlugPair;
use Minisite\Infrastructure\Persistence\Repositories\MinisiteRepository;
use PHPUnit\Framework\TestCase;

class MinisiteRepositoryMoreMethodsTest extends TestCase
{
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 'ms_1',
            'business_slug' => 'biz',
            'location_slug' => 'loc',
            'title' => 'T',
            'name' => 'N',
            'city' => 'C',
            'region' => null,
            'country_code' => 'US',
            'postal_code' => null,
            'site_template' => 'v2025',
            'palette' => 'blue',
            'industry' => 'services',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version' => 1,
            'site_json' => '{}',
            'search_terms' => null,
            'status' => 'draft',
            'publish_status' => null,
            'created_at' => null,
            'updated_at' => null,
            'published_at' => null,
            'created_by' => 1,
            'updated_by' => 1,
            '_minisite_current_version_id' => null,
            'location_point' => null,
        ], $overrides);
    }

    public function test_findBySlugs_maps_row_to_entity(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function get_row($q, $out = null) {
                return [
                    'id'=>'ms_1','business_slug'=>'biz','location_slug'=>'loc','title'=>'T','name'=>'N','city'=>'C','region'=>null,'country_code'=>'US','postal_code'=>null,
                    'site_template'=>'v2025','palette'=>'blue','industry'=>'services','default_locale'=>'en-US','schema_version'=>1,'site_version'=>1,'site_json'=>'{}','search_terms'=>null,
                    'status'=>'draft','publish_status'=>null,'created_at'=>null,'updated_at'=>null,'published_at'=>null,'created_by'=>1,'updated_by'=>1,'_minisite_current_version_id'=>null,'location_point'=>null
                ];
            }
        };
        $repo = new MinisiteRepository($wpdb);
        $ms = $repo->findBySlugs(new SlugPair('biz','loc'));
        $this->assertInstanceOf(Minisite::class, $ms);
        $this->assertSame('biz', $ms->slugs->business);
        $this->assertSame('loc', $ms->slugs->location);
    }

    public function test_findById_returns_null_when_not_found(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_row($q, $out = null) { return null; }
        };
        $repo = new MinisiteRepository($wpdb);
        $this->assertNull($repo->findById('missing'));
    }

    public function test_findBySlugParams_maps_row(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function get_row($q, $out = null) {
                return [
                    'id'=>'ms_1','business_slug'=>'biz','location_slug'=>'loc','title'=>'T','name'=>'N','city'=>'C','region'=>null,'country_code'=>'US','postal_code'=>null,
                    'site_template'=>'v2025','palette'=>'blue','industry'=>'services','default_locale'=>'en-US','schema_version'=>1,'site_version'=>1,'site_json'=>'{}','search_terms'=>null,
                    'status'=>'draft','publish_status'=>null,'created_at'=>null,'updated_at'=>null,'published_at'=>null,'created_by'=>1,'updated_by'=>1,'_minisite_current_version_id'=>null,'location_point'=>null
                ];
            }
        };
        $repo = new MinisiteRepository($wpdb);
        $ms = $repo->findBySlugParams('biz','loc');
        $this->assertInstanceOf(Minisite::class, $ms);
    }

    public function test_updateSlug_throws_when_no_row(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 0;
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function query($sql) { return 0; }
        };
        $repo = new MinisiteRepository($wpdb);
        $this->expectException(\RuntimeException::class);
        $repo->updateSlug('ms_1', 'new-slug');
    }

    public function test_updateSlug_success(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 1;
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function query($sql) { return 1; }
        };
        $repo = new MinisiteRepository($wpdb);
        $repo->updateSlug('ms_1', 'new-slug');
        $this->assertTrue(true);
    }

    public function test_updatePublishStatus_and_updateSlugs_success(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 1;
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function query($sql) { return 1; }
        };
        $repo = new MinisiteRepository($wpdb);
        $repo->updatePublishStatus('ms_1', 'published');
        $repo->updateSlugs('ms_1', 'biz', 'loc');
        $this->assertTrue(true);
    }

    public function test_updateTitle_and_status_success(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function update($table, $data, $where, $format = [], $where_format = []) { return 1; }
        };
        $repo = new MinisiteRepository($wpdb);
        $this->assertTrue($repo->updateTitle('ms_1', 'X'));
        $this->assertTrue($repo->updateStatus('ms_1', 'published'));
    }

    public function test_updateCurrentVersionId_success(): void
    {
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public int $rows_affected = 1;
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function query($sql) { return 1; }
        };
        $repo = new MinisiteRepository($wpdb);
        $repo->updateCurrentVersionId('ms_1', 10);
        $this->assertTrue(true);
    }

    public function test_updateBusinessInfo_success_and_failure(): void
    {
        // success
        $wpdb1 = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function update($t, $d, $w, $f = [], $wf = []) { return 1; }
        };
        $repo1 = new MinisiteRepository($wpdb1);
        $repo1->updateBusinessInfo('ms_1', ['title' => 'New'], 123);
        $this->assertTrue(true);

        // failure
        $wpdb2 = new class extends \wpdb {
            public string $prefix = 'wp_';
            public function update($t, $d, $w, $f = [], $wf = []) { return false; }
        };
        $repo2 = new MinisiteRepository($wpdb2);
        $this->expectException(\RuntimeException::class);
        $repo2->updateBusinessInfo('ms_1', ['title' => 'New'], 123);
    }

    public function test_listByOwner_builds_results(): void
    {
        $wpdb = new class($this) extends \wpdb {
            public string $prefix = 'wp_';
            private $test;
            public function __construct($test) { $this->test = $test; }
            public function prepare($q, ...$a) { foreach ($a as $x) { $q = preg_replace('/%[df]/', (string)(0+$x), $q, 1); if (preg_match('/%s/',$q)) $q = preg_replace('/%s/', "'".addslashes((string)$x)."'", $q, 1);} return $q; }
            public function get_results($q, $out = null) {
                return [
                    [
                        'id'=>'ms_1','business_slug'=>'biz','location_slug'=>'loc','title'=>'T','name'=>'N','city'=>'C','region'=>null,'country_code'=>'US','postal_code'=>null,
                        'site_template'=>'v2025','palette'=>'blue','industry'=>'services','default_locale'=>'en-US','schema_version'=>1,'site_version'=>1,'site_json'=>'{}','search_terms'=>null,
                        'status'=>'draft','publish_status'=>null,'created_at'=>null,'updated_at'=>null,'published_at'=>null,'created_by'=>1,'updated_by'=>1,'_minisite_current_version_id'=>null,'location_point'=>null
                    ],
                ];
            }
        };
        $repo = new MinisiteRepository($wpdb);
        $rows = $repo->listByOwner(1, 10, 0);
        $this->assertCount(1, $rows);
        $this->assertInstanceOf(Minisite::class, $rows[0]);
    }
}
