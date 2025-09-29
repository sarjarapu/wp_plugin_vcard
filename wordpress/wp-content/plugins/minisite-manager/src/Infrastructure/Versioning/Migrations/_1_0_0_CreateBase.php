<?php
namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\Support\DbDelta;
use Minisite\Infrastructure\Utils\SqlLoader;

class _1_0_0_CreateBase implements Migration
{
    public function version(): string { return '1.0.0'; }

    public function description(): string
    {
        return 'Create base tables: minisites, minisite_versions (with complete profile field versioning), minisite_reviews, minisite_bookmarks, minisite_payments, minisite_payment_history, minisite_reservations + auto-cleanup event + seed dev data';
    }

    public function up(\wpdb $wpdb): void
    {
        $minisites  = $wpdb->prefix . 'minisites';
        $reviews   = $wpdb->prefix . 'minisite_reviews';
        $versions = $wpdb->prefix . 'minisite_versions';
        $bookmarks = $wpdb->prefix . 'minisite_bookmarks';
        $payments = $wpdb->prefix . 'minisite_payments';
        $paymentHistory = $wpdb->prefix . 'minisite_payment_history';
        $reservations = $wpdb->prefix . 'minisite_reservations';

        // ——— minisites (live) ———
        SqlLoader::loadAndExecute($wpdb, 'minisites.sql', SqlLoader::createStandardVariables($wpdb));


        // ——— versions (new versioning system) ———
        SqlLoader::loadAndExecute($wpdb, 'minisite_versions.sql', SqlLoader::createStandardVariables($wpdb));

        // ——— reviews ———
        SqlLoader::loadAndExecute($wpdb, 'minisite_reviews.sql', SqlLoader::createStandardVariables($wpdb));

        // ——— bookmarks ———
        SqlLoader::loadAndExecute($wpdb, 'minisite_bookmarks.sql', SqlLoader::createStandardVariables($wpdb));

        // ——— payments (single payment for slug ownership + 1 year public access) ———
        SqlLoader::loadAndExecute($wpdb, 'minisite_payments.sql', SqlLoader::createStandardVariables($wpdb));

        // ——— payment history (for renewals and reclamations) ———
        SqlLoader::loadAndExecute($wpdb, 'minisite_payment_history.sql', SqlLoader::createStandardVariables($wpdb));

        // Reservations table for 5-minute slug reservations
        SqlLoader::loadAndExecute($wpdb, 'minisite_reservations.sql', SqlLoader::createStandardVariables($wpdb));

        // Add foreign key constraints after table creation (only if they don't exist)
        $this->addForeignKeyIfNotExists($wpdb, $versions, 'fk_versions_minisite_id', 'minisite_id', $minisites, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $reviews, 'fk_reviews_minisite_id', 'minisite_id', $minisites, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $bookmarks, 'fk_bookmarks_minisite_id', 'minisite_id', $minisites, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $payments, 'fk_payments_minisite_id', 'minisite_id', $minisites, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $payments, 'fk_payments_user_id', 'user_id', $wpdb->prefix . 'users', 'ID');
        $this->addForeignKeyIfNotExists($wpdb, $paymentHistory, 'fk_payment_history_minisite_id', 'minisite_id', $minisites, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $paymentHistory, 'fk_payment_history_payment_id', 'payment_id', $payments, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $paymentHistory, 'fk_payment_history_new_owner_user_id', 'new_owner_user_id', $wpdb->prefix . 'users', 'ID');
        $this->addForeignKeyIfNotExists($wpdb, $reservations, 'fk_reservations_user_id', 'user_id', $wpdb->prefix . 'users', 'ID');
        $this->addForeignKeyIfNotExists($wpdb, $reservations, 'fk_reservations_minisite_id', 'minisite_id', $minisites, 'id');

        // Create MySQL event for auto-cleanup of expired reservations
        $wpdb->query("
            CREATE EVENT IF NOT EXISTS cleanup_expired_reservations
            ON SCHEDULE EVERY 1 MINUTE
            DO
              DELETE FROM {$reservations} 
              WHERE expires_at < NOW()
        ");

        // —— dev seed: insert two test minisites + revisions + reviews ——
        $this->seedTestData($wpdb);
    }

    /**
     * Add a foreign key constraint only if it doesn't already exist
     */
    private function addForeignKeyIfNotExists(\wpdb $wpdb, string $table, string $constraintName, string $column, string $referencedTable, string $referencedColumn): void
    {
        // Check if the constraint already exists
        $constraintExists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND CONSTRAINT_NAME = %s
        ", DB_NAME, $table, $constraintName));

        if (!$constraintExists) {
            $wpdb->query("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} FOREIGN KEY ({$column}) REFERENCES {$referencedTable}({$referencedColumn}) ON DELETE CASCADE");
        }
    }

    /**
     * Load minisite data from JSON file and apply overrides
     */
    private function loadMinisiteFromJson(string $jsonFile, array $overrides = []): array
    {
        $jsonPath = __DIR__ . '/../../../../data/json/minisites/' . $jsonFile;
        
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("JSON file not found: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in file: {$jsonFile}. Error: " . json_last_error_msg());
        }

        if (!isset($data['minisite'])) {
            throw new \RuntimeException("Invalid JSON structure in file: {$jsonFile}. Missing 'minisite' property.");
        }

        $minisiteData = $data['minisite'];

        // Convert location format from JSON to database format
        $minisiteData = $this->convertLocationFormat($minisiteData);

        // Apply overrides
        $minisiteData = array_merge($minisiteData, $overrides);

        // Set computed/audit fields
        $minisiteData = $this->setComputedFields($minisiteData);

        return $minisiteData;
    }

    /**
     * Convert location format between JSON and database formats
     */
    private function convertLocationFormat(array $data): array
    {
        if (isset($data['location']) && is_array($data['location'])) {
            // Convert from JSON format {latitude, longitude} to database format {longitude, latitude}
            if (isset($data['location']['latitude']) && isset($data['location']['longitude'])) {
                $data['location_point'] = [
                    'longitude' => (float) $data['location']['longitude'],
                    'latitude' => (float) $data['location']['latitude']
                ];
                unset($data['location']); // Remove the JSON location format
            }
        }
        return $data;
    }

    /**
     * Set computed and audit fields that are not in JSON
     */
    private function setComputedFields(array $data): array
    {
        $now = current_time('mysql');
        $userId = get_current_user_id() ?: null;

        // Set audit fields - always use current values for seeding
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['published_at'] = $data['published_at'] ?? $now;
        $data['created_by'] = $userId; // Always reset to current user for proper ownership
        $data['updated_by'] = $userId; // Always reset to current user for proper ownership

        // Set computed slug if not provided
        if (!isset($data['slug']) && isset($data['business_slug']) && isset($data['location_slug'])) {
            $data['slug'] = $data['business_slug'] . '-' . $data['location_slug'];
        }

        // Set version reference (will be updated after version creation)
        $data['_minisite_current_version_id'] = null;

        return $data;
    }

    public function down(\wpdb $wpdb): void
    {
        // Dev convenience: clear the seeded data then drop tables
        $this->clearTestData($wpdb);

        $minisites  = $wpdb->prefix . 'minisites';
        $versions  = $wpdb->prefix . 'minisite_versions';
        $reviews   = $wpdb->prefix . 'minisite_reviews';
        $bookmarks = $wpdb->prefix . 'minisite_bookmarks';
        $payments = $wpdb->prefix . 'minisite_payments';
        $paymentHistory = $wpdb->prefix . 'minisite_payment_history';
        $reservations = $wpdb->prefix . 'minisite_reservations';

        // Drop MySQL event
        $wpdb->query("DROP EVENT IF EXISTS cleanup_expired_reservations");
        
        $wpdb->query("DROP TABLE IF EXISTS {$reservations}");
        $wpdb->query("DROP TABLE IF EXISTS {$paymentHistory}");
        $wpdb->query("DROP TABLE IF EXISTS {$payments}");
        $wpdb->query("DROP TABLE IF EXISTS {$bookmarks}");
        $wpdb->query("DROP TABLE IF EXISTS {$reviews}");
        $wpdb->query("DROP TABLE IF EXISTS {$versions}");
        $wpdb->query("DROP TABLE IF EXISTS {$minisites}");
    }

    /**
     * Seed four example minisites and their versions & reviews.
     * - 'acme-dental' / 'dallas'
     * - 'lotus-textiles' / 'mumbai'
     * - 'green-bites' / 'london'
     * - 'swift-transit' / 'sydney'
     */
    private function seedTestData(\wpdb $wpdb): void
    {
        $minisitesT  = $wpdb->prefix . 'minisites';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Avoid duplicate seeding (check any of our seeded slugs)
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$minisitesT}
             WHERE (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)",
            'acme-dental','dallas',
            'lotus-textiles','mumbai',
            'green-bites','london',
            'swift-transit','sydney'
        ));
        if ($exists > 0) {
            return;
        }

        // Helper function to insert minisite and debug
        $insertMinisite = function($minisiteData, $name) use ($wpdb, $minisitesT) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$minisitesT} (
                    id, slug, business_slug, location_slug, title, name, city, region, 
                    country_code, postal_code, location_point, site_template, palette, 
                    industry, default_locale, schema_version, site_version, site_json, 
                    search_terms, status, publish_status, created_at, updated_at, 
                    published_at, created_by, updated_by, _minisite_current_version_id
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, 
                    %s, %s, POINT(%f, %f), %s, %s, %s, %s, %d, 
                    %d, %s, %s, %s, %s, %s, %s, %s, 
                    %d, %d, %s
                )",
                $minisiteData['id'], $minisiteData['slug'], $minisiteData['business_slug'], $minisiteData['location_slug'],
                $minisiteData['title'], $minisiteData['name'], $minisiteData['city'], $minisiteData['region'],
                $minisiteData['country_code'], $minisiteData['postal_code'], 
                $minisiteData['location_point']['longitude'], $minisiteData['location_point']['latitude'],
                $minisiteData['site_template'], $minisiteData['palette'], $minisiteData['industry'], $minisiteData['default_locale'], $minisiteData['schema_version'],
                $minisiteData['site_version'], $minisiteData['site_json'], $minisiteData['search_terms'], $minisiteData['status'],
                $minisiteData['publish_status'], $minisiteData['created_at'], $minisiteData['updated_at'], $minisiteData['published_at'],
                $minisiteData['created_by'], $minisiteData['updated_by'], $minisiteData['_minisite_current_version_id']
            ));
            
            // Debug: Check if minisite was inserted correctly
            $debugResult = $wpdb->get_row($wpdb->prepare(
                "SELECT id, business_slug, location_slug, status, _minisite_current_version_id FROM {$minisitesT} WHERE id = %s",
                $minisiteData['id']
            ), ARRAY_A);
            error_log("{$name} INSERT DEBUG: " . print_r($debugResult, true));
            
            return $minisiteData['id'];
        };

        // Insert first profile: ACME Dental (Dallas, US)
        $acmeId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $acme = $this->loadMinisiteFromJson('acme-dental.json', [
            'id' => $acmeId
        ]);
        $acmeId = $insertMinisite($acme, 'ACME');

        // Insert second profile: Lotus Textiles (Mumbai, IN)
        $lotusId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $lotus = $this->loadMinisiteFromJson('lotus-textiles.json', [
            'id' => $lotusId
        ]);
        $lotusId = $insertMinisite($lotus, 'LOTUS');

        // Insert third profile: Green Bites (London, GB)
        $greenId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $green = $this->loadMinisiteFromJson('green-bites.json', [
            'id' => $greenId
        ]);
        $greenId = $insertMinisite($green, 'GREEN');

        // Insert fourth profile: Swift Transit (Sydney, AU)
        $swiftId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $swift = $this->loadMinisiteFromJson('swift-transit.json', [
            'id' => $swiftId
        ]);
        $swiftId = $insertMinisite($swift, 'SWIFT');

        // ——— Versions for each profile (version 1 as published) ———
        $versionsT = $wpdb->prefix . 'minisite_versions';
        $nowUser = get_current_user_id() ?: null;
        foreach ([ $acmeId => 'US', $lotusId => 'IN', $greenId => 'GB', $swiftId => 'AU' ] as $pid => $cc) {
            if (!$pid) { continue; }

            // Get the profile data for the initial version
            $minisite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$minisitesT} WHERE id = %s", $pid), ARRAY_A);
            $siteJson = $minisite ? $minisite['site_json'] : wp_json_encode(['note'=>'initial version','country'=>$cc]);
            
            // Create version 1 as published with all profile fields
            $versionData = [
                'minisite_id'      => $pid,
                'version_number'   => 1,
                'status'           => 'published',
                'label'            => 'Initial version',
                'comment'          => 'Migrated from existing data',
                'created_by'       => $nowUser,
                'created_at'       => current_time('mysql'),
                'published_at'     => current_time('mysql'),
                'source_version_id' => null,
                
                // Profile fields (exact match with profiles table order)
                'business_slug'    => $minisite['business_slug'] ?? null,
                'location_slug'    => $minisite['location_slug'] ?? null,
                'title'            => $minisite['title'] ?? null,
                'name'             => $minisite['name'] ?? null,
                'city'             => $minisite['city'] ?? null,
                'region'           => $minisite['region'] ?? null,
                'country_code'     => $minisite['country_code'] ?? null,
                'postal_code'      => $minisite['postal_code'] ?? null,
                'location_point'   => null, // Will be set separately if needed
                'site_template'    => $minisite['site_template'] ?? null,
                'palette'          => $minisite['palette'] ?? null,
                'industry'         => $minisite['industry'] ?? null,
                'default_locale'   => $minisite['default_locale'] ?? null,
                'schema_version'   => $minisite['schema_version'] ?? null,
                'site_version'     => $minisite['site_version'] ?? null,
                'site_json'        => $siteJson,
                'search_terms'     => $minisite['search_terms'] ?? null,
            ];

            $wpdb->insert($versionsT, $versionData, [
                '%s','%d','%s','%s','%s','%d','%s','%s','%d',
                '%s','%s','%s','%s','%s','%s','%s','%s',
                '%s','%s','%s','%s','%d','%d','%s','%s'
            ]);

            $versionId = (int) $wpdb->insert_id;

            // Set location_point based on profile ID (hardcoded coordinates)
            $coordinates = [
                $acmeId => [-96.7970, 32.7767],   // Dallas, TX
                $lotusId => [72.8777, 19.0760],   // Mumbai, IN
                $greenId => [-0.118092, 51.509865], // London, GB
                $swiftId => [151.2093, -33.8688]   // Sydney, AU
            ];
            
            if (isset($coordinates[$pid])) {
                [$lng, $lat] = $coordinates[$pid];
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$versionsT} SET location_point = POINT(%f, %f) WHERE id = %d",
                    $lng, $lat, $versionId
                ));
            }

            // Update profile with current version ID
            $wpdb->query($wpdb->prepare(
                "UPDATE {$minisitesT} SET _minisite_current_version_id = %d WHERE id = %s",
                $versionId, $pid
            ));
        }


        // ——— Reviews (5 per minisite) ———
        $insertReview = function(string $pid, string $name, float $rating, string $body, ?string $locale='en-US') use ($wpdb, $reviewsT, $nowUser) {
            $wpdb->insert($reviewsT, [
                'minisite_id'  => $pid,
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
            ], ['%s','%s','%s','%f','%s','%s','%s','%s','%s','%d']);
        };

        if ($acmeId) {
            // ACME Dental reviews (5 total)
            $insertReview($acmeId, 'Jane Doe', 5.0, 'The hygienist was incredibly gentle and explained every step before she started. The clinic is spotless and the equipment looks brand new. I left feeling well cared for and finally not dreading my next visit.');
            $insertReview($acmeId, 'Mark T.', 4.5, 'Booked a last‑minute appointment for a chipped tooth and they fit me in the same day. The repair was quick and painless, and the billing was clear. Parking was easy which is a bonus in Dallas.');
            $insertReview($acmeId, 'Priya S.', 4.8, 'I had whitening done here and the results were immediate. The dentist checked sensitivity throughout and gave me clear aftercare instructions. Front desk followed up the next day to see how I was doing.');
            $insertReview($acmeId, 'Daniel K.', 4.9, 'Super organized practice with on‑time appointments. They walked me through options for a crown and never pushed extras. Waiting area is calm and the coffee machine is a nice touch.');
            $insertReview($acmeId, 'Alicia M.', 5.0, 'Brought my teen for Invisalign and the consultation was thorough without being overwhelming. Clear timeline, fair pricing, and they answered all our questions. We feel confident continuing care here.');
        }
        if ($lotusId) {
            // Lotus Textiles reviews (5 total)
            $insertReview($lotusId, 'Asha P.', 5.0, 'Beautiful fabric selection and honest pricing. The team helped me pick the right silk and arranged quick alterations. I received so many compliments at the event.', 'en-IN');
            $insertReview($lotusId, 'Rohit K.', 4.6, 'Quality linens and attentive staff. Turnaround for tailoring was faster than expected and the fit was perfect.', 'en-IN');
            $insertReview($lotusId, 'Neha S.', 4.8, 'They sourced a specific shade of chiffon for me within two days. Great communication throughout and careful packaging.', 'en-IN');
            $insertReview($lotusId, 'Imran V.', 4.7, 'Got a sherwani tailored here. Professional fittings and precise embroidery work. Delivery was on the promised date.', 'en-IN');
            $insertReview($lotusId, 'Kavita D.', 4.9, 'Staff were patient while I compared several silks. They suggested blouse lining and care tips that really helped.', 'en-IN');
        }
        if ($greenId) {
            // Green Bites reviews (5 total)
            $insertReview($greenId, 'Alex P.', 5.0, 'Best sourdough in the City. The crust has real depth of flavor and the bowls are generous. Staff remembered my usual after two visits.', 'en-GB');
            $insertReview($greenId, 'Maria G.', 4.7, 'Delicious bowls and quick service at lunch. Great coffee with oat milk, and I love the rotating specials.', 'en-GB');
            $insertReview($greenId, 'Tom H.', 4.6, 'Great place for a quick, healthy lunch. Seating fills up at noon but the line moves fast.', 'en-GB');
            $insertReview($greenId, 'Ella R.', 4.8, 'Excellent espresso and friendly baristas. The vegan bowl had great textures and bright flavors.', 'en-GB');
            $insertReview($greenId, 'Ben S.', 4.9, 'Love the seasonal menu changes and the sourdough loaves on Fridays. Consistently great quality.', 'en-GB');
        }
        if ($swiftId) {
            // Swift Transit reviews (5 total)
            $insertReview($swiftId, 'Zoe L.', 5.0, 'Super fast and careful with fragile items. They handled our clinic samples with documented chain-of-custody and delivered earlier than promised.', 'en-AU');
            $insertReview($swiftId, 'Nick R.', 4.8, 'Great communication and tracking. Dispatch answered within seconds, and the driver called ahead for loading dock access.', 'en-AU');
            $insertReview($swiftId, 'Sam D.', 4.7, 'Booked an urgent pickup at 4 pm and it reached the CBD in under an hour. Clear proof‑of‑delivery emailed instantly.', 'en-AU');
            $insertReview($swiftId, 'Priya V.', 4.9, 'Courteous drivers and clean vehicles. Our bulk transfers were secured properly and arrived without damage.', 'en-AU');
            $insertReview($swiftId, 'Owen C.', 4.8, 'We use their scheduled routes daily. Reliable timings and proactive updates whenever traffic is heavy.', 'en-AU');
        }
    }

    /**
     * Remove ONLY the seeded test data (safe to call repeatedly).
     */
    private function clearTestData(\wpdb $wpdb): void
    {
        $minisitesT  = $wpdb->prefix . 'minisites';
        $versionsT  = $wpdb->prefix . 'minisite_versions';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Check if the main profiles table exists before trying to query it
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            $wpdb->dbname,
            $minisitesT
        ));

        if (!$tableExists) {
            // Tables don't exist yet, nothing to clear
            return;
        }

        // Get IDs for our seeded slugs
        $rows = $wpdb->get_results("
            SELECT id FROM {$minisitesT}
            WHERE (business_slug='acme-dental' AND location_slug='dallas')
               OR (business_slug='lotus-textiles' AND location_slug='mumbai')
               OR (business_slug='green-bites' AND location_slug='london')
               OR (business_slug='swift-transit' AND location_slug='sydney')
        ");
        if (!$rows) { return; }

        $ids = array_map(fn($r) => $r->id, $rows);
        $in  = implode(',', array_fill(0, count($ids), '%s'));

        // Delete child tables first (only if they exist)
        $wpdb->query($wpdb->prepare("DELETE FROM {$reviewsT}   WHERE minisite_id IN ($in)", ...$ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$versionsT}  WHERE minisite_id IN ($in)", ...$ids));
        // Delete minisites
        $wpdb->query($wpdb->prepare("DELETE FROM {$minisitesT}  WHERE id IN ($in)", ...$ids));
    }
}
