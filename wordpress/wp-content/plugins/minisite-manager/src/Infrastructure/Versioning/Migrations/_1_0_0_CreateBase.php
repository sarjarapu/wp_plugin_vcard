<?php
namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\Support\DbDelta;

class _1_0_0_CreateBase implements Migration
{
    public function version(): string { return '1.0.0'; }

    public function description(): string
    {
        return 'Create base tables: minisite_profiles, minisite_profile_revisions, minisite_reviews + seed dev data';
    }

    public function up(\wpdb $wpdb): void
    {
        $charset   = $wpdb->get_charset_collate();
        $profiles  = $wpdb->prefix . 'minisite_profiles';
        $revisions = $wpdb->prefix . 'minisite_profile_revisions';
        $reviews   = $wpdb->prefix . 'minisite_reviews';

        // ——— profiles (live) ———
        DbDelta::run("
        CREATE TABLE {$profiles} (
          id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

          business_slug     VARCHAR(120)    NOT NULL,
          location_slug     VARCHAR(120)    NOT NULL,

          title             VARCHAR(200)    NOT NULL,
          name              VARCHAR(200)    NOT NULL,
          city              VARCHAR(120)    NOT NULL,
          region            VARCHAR(120)    NULL,
          country_code      CHAR(2)         NOT NULL,
          postal_code       VARCHAR(20)     NULL,

          lat               DECIMAL(9,6)    NULL,
          lng               DECIMAL(9,6)    NULL,
          location_point    POINT           NULL,

          site_template     VARCHAR(32)     NOT NULL DEFAULT 'v2025',
          palette           VARCHAR(24)     NOT NULL DEFAULT 'blue',
          industry          VARCHAR(40)     NOT NULL DEFAULT 'services',
          default_locale    VARCHAR(10)     NOT NULL DEFAULT 'en-US',

          schema_version    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
          site_version      INT UNSIGNED      NOT NULL DEFAULT 1,
          site_json         LONGTEXT          NOT NULL,

          search_terms      TEXT              NULL,

          status            ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
          created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          published_at      DATETIME         NULL,
          created_by        BIGINT UNSIGNED  NULL,
          updated_by        BIGINT UNSIGNED  NULL,

          PRIMARY KEY (id),
          UNIQUE KEY uniq_business_location (business_slug, location_slug)
        ) ENGINE=InnoDB {$charset};
        ");

        // ——— revisions ———
        DbDelta::run("
        CREATE TABLE {$revisions} (
          id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          profile_id       BIGINT UNSIGNED NOT NULL,
          revision_number  INT UNSIGNED    NOT NULL,
          status           ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',

          schema_version   SMALLINT UNSIGNED NOT NULL,
          site_json        LONGTEXT          NOT NULL,

          created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          created_by       BIGINT UNSIGNED  NULL,

          PRIMARY KEY (id),
          UNIQUE KEY uniq_profile_revision (profile_id, revision_number),
          KEY idx_profile_status (profile_id, status),
          KEY idx_profile_created (profile_id, created_at)
        ) ENGINE=InnoDB {$charset};
        ");

        // ——— reviews ———
        DbDelta::run("
        CREATE TABLE {$reviews} (
          id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          profile_id       BIGINT UNSIGNED NOT NULL,

          author_name      VARCHAR(160)     NOT NULL,
          author_url       VARCHAR(300)     NULL,
          rating           DECIMAL(2,1)     NOT NULL,
          body             MEDIUMTEXT       NOT NULL,
          locale           VARCHAR(10)      NULL,
          visited_month    CHAR(7)          NULL,

          source           ENUM('manual','google','yelp','facebook','other') NOT NULL DEFAULT 'manual',
          source_id        VARCHAR(160)     NULL,
          status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',

          created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by       BIGINT UNSIGNED  NULL,

          PRIMARY KEY (id),
          KEY idx_profile (profile_id),
          KEY idx_status_date (status, created_at),
          KEY idx_rating (rating)
        ) ENGINE=InnoDB {$charset};
        ");

        // —— dev seed: insert two test minisites + revisions + reviews ——
        $this->seedTestData($wpdb);
    }

    public function down(\wpdb $wpdb): void
    {
        // Dev convenience: clear the seeded data then drop tables
        $this->clearTestData($wpdb);

        $profiles  = $wpdb->prefix . 'minisite_profiles';
        $revisions = $wpdb->prefix . 'minisite_profile_revisions';
        $reviews   = $wpdb->prefix . 'minisite_reviews';

        $wpdb->query("DROP TABLE IF EXISTS {$reviews}");
        $wpdb->query("DROP TABLE IF EXISTS {$revisions}");
        $wpdb->query("DROP TABLE IF EXISTS {$profiles}");
    }

    /**
     * Seed two example businesses and their revisions & reviews.
     * - 'acme-dental' / 'dallas'
     * - 'lotus-textiles' / 'mumbai'
     */
    private function seedTestData(\wpdb $wpdb): void
    {
        $profilesT  = $wpdb->prefix . 'minisite_profiles';
        $revisionsT = $wpdb->prefix . 'minisite_profile_revisions';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Avoid duplicate seeding
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$profilesT} WHERE (business_slug=%s AND location_slug=%s) OR (business_slug=%s AND location_slug=%s)",
            'acme-dental','dallas','lotus-textiles','mumbai'
        ));
        if ($exists > 0) {
            return;
        }

        // Helper to build a richer site_json blob used by front-end templates
        $makeJson = function(string $name, string $city, string $countryCode, string $palette, string $industry): string {
            $data = [
                'seo' => [
                    'title' => "{$name} — {$city}",
                    'description' => "Learn more about {$name} located in {$city}.",
                ],
                'brand' => [
                    'name' => $name,
                    'logo' => null,
                    'palette' => $palette,
                    'industry' => $industry,
                ],
                'hero' => [
                    'heading' => $name,
                    'subheading' => 'Family-friendly services with a modern touch.',
                    'badge' => 'Verified Business',
                    'image' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=1600&q=80&auto=format&fit=crop',
                    'rating' => ['value' => 4.8, 'count' => 238],
                    'ctas' => [
                        ['text' => 'Request Info', 'url' => '#request-info', 'style' => 'primary'],
                        ['text' => 'Contact Us',  'url' => '#contact',       'style' => 'tonal'],
                    ],
                ],
                'about' => [
                    'html' => "<p>{$name} provides quality services in {$city}. Our team uses modern tools to deliver excellent outcomes.</p>",
                ],
                'services' => [
                    [
                        'id' => 'svc-1',
                        'title' => 'Consultation',
                        'description' => 'Initial consult and recommendations.',
                        'icon' => 'fa-solid fa-comments',
                        'features' => ['Assessment', 'Recommendations'],
                        'price' => 'Call for pricing'
                    ],
                    [
                        'id' => 'svc-2',
                        'title' => 'Premium Service',
                        'description' => 'Advanced package for comprehensive needs.',
                        'icon' => 'fa-solid fa-star',
                        'features' => ['Priority', 'Comprehensive'],
                        'price' => '$199'
                    ],
                ],
                'contact' => [
                    'phone' => '+1 512 555 1234',
                    'email' => 'hello@example.com',
                    'website' => 'https://example.com',
                    'address' => "123 Main St, {$city}, {$countryCode} 00000",
                    'hours' => [
                        ['day' => 'Mon–Fri', 'open' => '08:00', 'close' => '17:00'],
                        ['day' => 'Sat',     'open' => '09:00', 'close' => '13:00'],
                    ],
                ],
                'reviews' => [
                    ['author' => 'Jane Doe', 'rating' => 5,   'date' => '2025-04-01', 'text' => 'Fantastic staff and spotless space!'],
                    ['author' => 'Mark T.',  'rating' => 4.5, 'date' => '2025-03-20', 'text' => 'Quick appointment and great results.'],
                ],
                'gallery' => [
                    ['src' => 'https://example.com/images/1.jpg', 'alt' => 'Reception area', 'caption' => 'Welcome desk'],
                    ['src' => 'https://example.com/images/2.jpg', 'alt' => 'Main room',      'caption' => 'Modern equipment'],
                ],
                'social' => [
                    ['network' => 'facebook',  'url' => 'https://facebook.com/example'],
                    ['network' => 'instagram', 'url' => 'https://instagram.com/example'],
                ],
            ];
            return wp_json_encode($data);
        };

        // Insert first profile: ACME Dental (Dallas, US)
        $acme = [
            'business_slug'  => 'acme-dental',
            'location_slug'  => 'dallas',
            'title'          => 'Acme Dental — Dallas',
            'name'           => 'Acme Dental',
            'city'           => 'Dallas',
            'region'         => 'TX',
            'country_code'   => 'US',
            'postal_code'    => '75201',
            'lat'            => 32.7767,
            'lng'            => -96.7970,
            'site_template'  => 'v2025',
            'palette'        => 'blue',
            'industry'       => 'services',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $makeJson('Acme Dental', 'Dallas', 'US', 'blue', 'services'),
            'search_terms'   => 'acme dental dentist dallas tx services clinic',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $acme, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%f','%f','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%d','%d'
        ]);
        $acmeId = (int) $wpdb->insert_id;

        // Set POINT (lng, lat) with SRID 4326 if available (MySQL 8)
        if ($acmeId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                $acme['lng'], $acme['lat'], $acmeId
            ));
        }

        // Insert second profile: Lotus Textiles (Mumbai, IN)
        $lotus = [
            'business_slug'  => 'lotus-textiles',
            'location_slug'  => 'mumbai',
            'title'          => 'Lotus Textiles — Mumbai',
            'name'           => 'Lotus Textiles',
            'city'           => 'Mumbai',
            'region'         => 'MH',
            'country_code'   => 'IN',
            'postal_code'    => '400001',
            'lat'            => 19.0760,
            'lng'            => 72.8777,
            'site_template'  => 'v2025',
            'palette'        => 'rose',
            'industry'       => 'textile',
            'default_locale' => 'en-IN',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $makeJson('Lotus Textiles', 'Mumbai', 'IN', 'rose', 'textile'),
            'search_terms'   => 'lotus textiles fabric mumbai india showroom boutique',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $lotus, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%f','%f','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%d','%d'
        ]);
        $lotusId = (int) $wpdb->insert_id;

        if ($lotusId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                $lotus['lng'], $lotus['lat'], $lotusId
            ));
        }

        // ——— Revisions for each profile (rev 1 draft, rev 2 published snapshot) ———
        $nowUser = get_current_user_id() ?: null;
        foreach ([ $acmeId => 'US', $lotusId => 'IN' ] as $pid => $cc) {
            if (!$pid) { continue; }

            // rev 1: draft
            $wpdb->insert($revisionsT, [
                'profile_id'      => $pid,
                'revision_number' => 1,
                'status'          => 'draft',
                'schema_version'  => 1,
                'site_json'       => wp_json_encode(['note'=>'draft seed','country'=>$cc]),
                'created_by'      => $nowUser,
            ], ['%d','%d','%s','%d','%s','%d']);

            // rev 2: published snapshot
            $wpdb->insert($revisionsT, [
                'profile_id'      => $pid,
                'revision_number' => 2,
                'status'          => 'published',
                'schema_version'  => 1,
                'site_json'       => wp_json_encode(['note'=>'published seed','country'=>$cc]),
                'created_by'      => $nowUser,
            ], ['%d','%d','%s','%d','%s','%d']);
        }

        // ——— Reviews (2 per profile) ———
        $insertReview = function(int $pid, string $name, float $rating, string $body, ?string $locale='en-US') use ($wpdb, $reviewsT, $nowUser) {
            $wpdb->insert($reviewsT, [
                'profile_id'   => $pid,
                'author_name'  => $name,
                'author_url'   => null,
                'rating'       => $rating,
                'body'         => $body,
                'locale'       => $locale,
                'visited_month'=> date('Y-m'),
                'source'       => 'manual',
                'source_id'    => null,
                'status'       => 'approved',
                'created_by'   => $nowUser,
            ], ['%d','%s','%s','%f','%s','%s','%s','%s','%s','%d']);
        };

        if ($acmeId) {
            $insertReview($acmeId, 'Jane Doe', 4.8, 'Fantastic service and friendly staff. Clean clinic!');
            $insertReview($acmeId, 'Mark T.', 4.5, 'Quick appointment and great results.');
        }
        if ($lotusId) {
            $insertReview($lotusId, 'Asha P.', 5.0, 'Beautiful fabric collection and helpful staff.', 'en-IN');
            $insertReview($lotusId, 'Rohit K.', 4.6, 'Great pricing and quality.', 'en-IN');
        }
    }

    /**
     * Remove ONLY the seeded test data (safe to call repeatedly).
     */
    private function clearTestData(\wpdb $wpdb): void
    {
        $profilesT  = $wpdb->prefix . 'minisite_profiles';
        $revisionsT = $wpdb->prefix . 'minisite_profile_revisions';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Get IDs for our seeded slugs
        $rows = $wpdb->get_results("
            SELECT id FROM {$profilesT}
            WHERE (business_slug='acme-dental' AND location_slug='dallas')
               OR (business_slug='lotus-textiles' AND location_slug='mumbai')
        ");
        if (!$rows) { return; }

        $ids = array_map(fn($r) => (int)$r->id, $rows);
        $in  = implode(',', array_fill(0, count($ids), '%d'));

        // Delete child tables first
        $wpdb->query($wpdb->prepare("DELETE FROM {$reviewsT}   WHERE profile_id IN ($in)", ...$ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$revisionsT} WHERE profile_id IN ($in)", ...$ids));
        // Delete profiles
        $wpdb->query($wpdb->prepare("DELETE FROM {$profilesT}  WHERE id IN ($in)", ...$ids));
    }
}
