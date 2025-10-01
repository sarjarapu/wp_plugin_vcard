<?php

namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\Support\DbDelta;
use Minisite\Infrastructure\Utils\SqlLoader;

class _1_0_0_CreateBase implements Migration
{
    public function version(): string
    {
        return '1.0.0';
    }

    public function description(): string
    {
        return 'Create base tables: minisites, minisite_versions (with complete profile field versioning), ' .
               'minisite_reviews, minisite_bookmarks, minisite_payments, minisite_payment_history, ' .
               'minisite_reservations + auto-cleanup event + seed dev data';
    }

    public function up(\wpdb $wpdb): void
    {
        $minisites      = $wpdb->prefix . 'minisites';
        $reviews        = $wpdb->prefix . 'minisite_reviews';
        $versions       = $wpdb->prefix . 'minisite_versions';
        $bookmarks      = $wpdb->prefix . 'minisite_bookmarks';
        $payments       = $wpdb->prefix . 'minisite_payments';
        $paymentHistory = $wpdb->prefix . 'minisite_payment_history';
        $reservations   = $wpdb->prefix . 'minisite_reservations';

        // ——— minisites (live) ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisites.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— versions (new versioning system) ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_versions.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— reviews ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_reviews.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— bookmarks ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_bookmarks.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— payments (single payment for slug ownership + 1 year public access) ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_payments.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— payment history (for renewals and reclamations) ———
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_payment_history.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // Reservations table for 5-minute slug reservations
        SqlLoader::loadAndExecute(
            $wpdb,
            'minisite_reservations.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // Add foreign key constraints after table creation (only if they don't exist)
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $versions,
            'fk_versions_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $reviews,
            'fk_reviews_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $bookmarks,
            'fk_bookmarks_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $payments,
            'fk_payments_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $payments,
            'fk_payments_user_id',
            'user_id',
            $wpdb->prefix . 'users',
            'ID'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $paymentHistory,
            'fk_payment_history_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $paymentHistory,
            'fk_payment_history_payment_id',
            'payment_id',
            $payments,
            'id'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $paymentHistory,
            'fk_payment_history_new_owner_user_id',
            'new_owner_user_id',
            $wpdb->prefix . 'users',
            'ID'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $reservations,
            'fk_reservations_user_id',
            'user_id',
            $wpdb->prefix . 'users',
            'ID'
        );
        $this->addForeignKeyIfNotExists(
            $wpdb,
            $reservations,
            'fk_reservations_minisite_id',
            'minisite_id',
            $minisites,
            'id'
        );

        // Create MySQL event for auto-cleanup of expired reservations
        SqlLoader::loadAndExecute($wpdb, 'event_purge_reservations.sql', SqlLoader::createStandardVariables($wpdb));

        // —— dev seed: insert two test minisites + revisions + reviews ——
        $this->seedTestData($wpdb);
    }

    /**
     * Add a foreign key constraint only if it doesn't already exist
     */
    protected function addForeignKeyIfNotExists(
        \wpdb $wpdb,
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        // Check if the constraint already exists
        $constraintExists = $wpdb->get_var(
            $wpdb->prepare(
                '
            SELECT COUNT(*) 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND CONSTRAINT_NAME = %s
        ',
                DB_NAME,
                $table,
                $constraintName
            )
        );

        if (! $constraintExists) {
            $wpdb->query(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} " .
                "FOREIGN KEY ({$column}) REFERENCES {$referencedTable}({$referencedColumn}) " .
                "ON DELETE CASCADE"
            );
        }
    }

    /**
     * Load minisite data from JSON file and apply overrides
     */
    protected function loadMinisiteFromJson(string $jsonFile, array $overrides = array()): array
    {
        $jsonPath = __DIR__ . '/../../../../data/json/minisites/' . $jsonFile;

        if (! file_exists($jsonPath)) {
            throw new \RuntimeException("JSON file not found: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $data        = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in file: {$jsonFile}. Error: " . json_last_error_msg());
        }

        if (! isset($data['minisite'])) {
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
    protected function convertLocationFormat(array $data): array
    {
        if (isset($data['location']) && is_array($data['location'])) {
            // Convert from JSON format {latitude, longitude} to database format {longitude, latitude}
            if (isset($data['location']['latitude']) && isset($data['location']['longitude'])) {
                $data['location_point'] = array(
                    'longitude' => (float) $data['location']['longitude'],
                    'latitude'  => (float) $data['location']['latitude'],
                );
                unset($data['location']); // Remove the JSON location format
            }
        }
        return $data;
    }

    /**
     * Set computed and audit fields that are not in JSON
     */
    protected function setComputedFields(array $data): array
    {
        $now    = current_time('mysql');
        $userId = get_current_user_id() ?: null;

        // Set audit fields - always use current values for seeding
        $data['created_at']   = $data['created_at'] ?? $now;
        $data['updated_at']   = $data['updated_at'] ?? $now;
        $data['published_at'] = $data['published_at'] ?? $now;
        $data['created_by']   = $userId; // Always reset to current user for proper ownership
        $data['updated_by']   = $userId; // Always reset to current user for proper ownership

        // Set computed slug if not provided
        if (! isset($data['slug']) && isset($data['business_slug']) && isset($data['location_slug'])) {
            $data['slug'] = $data['business_slug'] . '-' . $data['location_slug'];
        }

        // Set version reference (will be updated after version creation)
        $data['_minisite_current_version_id'] = null;

        return $data;
    }

    /**
     * Insert a minisite into the database
     */
    protected function insertMinisite(\wpdb $wpdb, array $minisiteData, string $name): string
    {
        $minisitesT = $wpdb->prefix . 'minisites';

        $wpdb->query(
            $wpdb->prepare(
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
                $minisiteData['id'],
                $minisiteData['slug'],
                $minisiteData['business_slug'],
                $minisiteData['location_slug'],
                $minisiteData['title'],
                $minisiteData['name'],
                $minisiteData['city'],
                $minisiteData['region'],
                $minisiteData['country_code'],
                $minisiteData['postal_code'],
                $minisiteData['location_point']['longitude'],
                $minisiteData['location_point']['latitude'],
                $minisiteData['site_template'],
                $minisiteData['palette'],
                $minisiteData['industry'],
                $minisiteData['default_locale'],
                $minisiteData['schema_version'],
                $minisiteData['site_version'],
                $minisiteData['site_json'],
                $minisiteData['search_terms'],
                $minisiteData['status'],
                $minisiteData['publish_status'],
                $minisiteData['created_at'],
                $minisiteData['updated_at'],
                $minisiteData['published_at'],
                $minisiteData['created_by'],
                $minisiteData['updated_by'],
                $minisiteData['_minisite_current_version_id']
            )
        );

        // Debug: Check if minisite was inserted correctly
        $debugResult = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, business_slug, location_slug, status, _minisite_current_version_id " .
                "FROM {$minisitesT} WHERE id = %s",
                $minisiteData['id']
            ),
            ARRAY_A
        );
        // error_log("{$name} INSERT DEBUG: " . print_r($debugResult, true));

        return $minisiteData['id'];
    }

    /**
     * Insert a review into the database
     */
    protected function insertReview(
        \wpdb $wpdb,
        string $minisiteId,
        string $authorName,
        float $rating,
        string $body,
        ?string $locale = 'en-US'
    ): void {
        $reviewsT = $wpdb->prefix . 'minisite_reviews';
        $nowUser  = get_current_user_id() ?: null;

        $wpdb->insert(
            $reviewsT,
            array(
                'minisite_id'   => $minisiteId,
                'author_name'   => $authorName,
                'author_url'    => null,
                'rating'        => $rating,
                'body'          => $body,
                'locale'        => $locale,
                'visited_month' => date('Y-m'),
                'source'        => 'manual',
                'source_id'     => null,
                'status'        => 'approved',
                'created_by'    => $nowUser,
            ),
            array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );
    }

    public function down(\wpdb $wpdb): void
    {
        $minisites      = $wpdb->prefix . 'minisites';
        $versions       = $wpdb->prefix . 'minisite_versions';
        $reviews        = $wpdb->prefix . 'minisite_reviews';
        $bookmarks      = $wpdb->prefix . 'minisite_bookmarks';
        $payments       = $wpdb->prefix . 'minisite_payments';
        $paymentHistory = $wpdb->prefix . 'minisite_payment_history';
        $reservations   = $wpdb->prefix . 'minisite_reservations';

        // Drop MySQL event
        $wpdb->query("DROP EVENT IF EXISTS {$wpdb->prefix}minisite_purge_reservations_event");

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
    protected function seedTestData(\wpdb $wpdb): void
    {
        $minisitesT = $wpdb->prefix . 'minisites';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Avoid duplicate seeding (check any of our seeded slugs)
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$minisitesT}
             WHERE (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)",
                'acme-dental',
                'dallas',
                'lotus-textiles',
                'mumbai',
                'green-bites',
                'london',
                'swift-transit',
                'sydney'
            )
        );
        if ($exists > 0) {
            return;
        }

        // Insert first profile: ACME Dental (Dallas, US)
        $acmeId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $acme   = $this->loadMinisiteFromJson(
            'acme-dental.json',
            array(
                'id' => $acmeId,
            )
        );
        $acmeId = $this->insertMinisite($wpdb, $acme, 'ACME');

        // Insert second profile: Lotus Textiles (Mumbai, IN)
        $lotusId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $lotus   = $this->loadMinisiteFromJson(
            'lotus-textiles.json',
            array(
                'id' => $lotusId,
            )
        );
        $lotusId = $this->insertMinisite($wpdb, $lotus, 'LOTUS');

        // Insert third profile: Green Bites (London, GB)
        $greenId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $green   = $this->loadMinisiteFromJson(
            'green-bites.json',
            array(
                'id' => $greenId,
            )
        );
        $greenId = $this->insertMinisite($wpdb, $green, 'GREEN');

        // Insert fourth profile: Swift Transit (Sydney, AU)
        $swiftId = \Minisite\Domain\Services\MinisiteIdGenerator::generate();
        $swift   = $this->loadMinisiteFromJson(
            'swift-transit.json',
            array(
                'id' => $swiftId,
            )
        );
        $swiftId = $this->insertMinisite($wpdb, $swift, 'SWIFT');

        // ——— Versions for each profile (version 1 as published) ———
        $versionsT = $wpdb->prefix . 'minisite_versions';
        $nowUser   = get_current_user_id() ?: null;
        foreach (
            array(
            $acmeId  => 'US',
            $lotusId => 'IN',
            $greenId => 'GB',
            $swiftId => 'AU',
            ) as $pid => $cc
        ) {
            if (! $pid) {
                continue;
            }

            // Get the profile data for the initial version
            $minisite = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$minisitesT} WHERE id = %s", $pid), ARRAY_A);
            $siteJson = $minisite ? $minisite['site_json'] : wp_json_encode(
                array(
                    'note'    => 'initial version',
                    'country' => $cc,
                )
            );

            // Create version 1 as published with all profile fields
            $versionData = array(
                'minisite_id'       => $pid,
                'version_number'    => 1,
                'status'            => 'published',
                'label'             => 'Initial version',
                'comment'           => 'Migrated from existing data',
                'created_by'        => $nowUser,
                'created_at'        => current_time('mysql'),
                'published_at'      => current_time('mysql'),
                'source_version_id' => null,

                // Profile fields (exact match with profiles table order)
                'business_slug'     => $minisite['business_slug'] ?? null,
                'location_slug'     => $minisite['location_slug'] ?? null,
                'title'             => $minisite['title'] ?? null,
                'name'              => $minisite['name'] ?? null,
                'city'              => $minisite['city'] ?? null,
                'region'            => $minisite['region'] ?? null,
                'country_code'      => $minisite['country_code'] ?? null,
                'postal_code'       => $minisite['postal_code'] ?? null,
                'location_point'    => null, // Will be set separately if needed
                'site_template'     => $minisite['site_template'] ?? null,
                'palette'           => $minisite['palette'] ?? null,
                'industry'          => $minisite['industry'] ?? null,
                'default_locale'    => $minisite['default_locale'] ?? null,
                'schema_version'    => $minisite['schema_version'] ?? null,
                'site_version'      => $minisite['site_version'] ?? null,
                'site_json'         => $siteJson,
                'search_terms'      => $minisite['search_terms'] ?? null,
            );

            $wpdb->insert(
                $versionsT,
                $versionData,
                array(
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                )
            );

            $versionId = (int) $wpdb->insert_id;

            // Set location_point based on profile ID (hardcoded coordinates)
            $coordinates = array(
                $acmeId  => array( -96.7970, 32.7767 ),   // Dallas, TX
                $lotusId => array( 72.8777, 19.0760 ),   // Mumbai, IN
                $greenId => array( -0.118092, 51.509865 ), // London, GB
                $swiftId => array( 151.2093, -33.8688 ),   // Sydney, AU
            );

            if (isset($coordinates[ $pid ])) {
                [$lng, $lat] = $coordinates[ $pid ];
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$versionsT} SET location_point = POINT(%f, %f) WHERE id = %d",
                        $lng,
                        $lat,
                        $versionId
                    )
                );
            }

            // Update profile with current version ID
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$minisitesT} SET _minisite_current_version_id = %d WHERE id = %s",
                    $versionId,
                    $pid
                )
            );
        }

        // ——— Reviews (5 per minisite) ———

        if ($acmeId) {
            // ACME Dental reviews (5 total)
            $this->insertReview(
                $wpdb,
                $acmeId,
                'Jane Doe',
                5.0,
                'The hygienist was incredibly gentle and explained every step before she started. ' .
                'The clinic is spotless and the equipment looks brand new. ' .
                'I left feeling well cared for and finally not dreading my next visit.'
            );
            $this->insertReview(
                $wpdb,
                $acmeId,
                'Mark T.',
                4.5,
                'Booked a last‑minute appointment for a chipped tooth and they fit me in the same day. ' .
                'The repair was quick and painless, and the billing was clear. ' .
                'Parking was easy which is a bonus in Dallas.'
            );
            $this->insertReview(
                $wpdb,
                $acmeId,
                'Priya S.',
                4.8,
                'I had whitening done here and the results were immediate. ' .
                'The dentist checked sensitivity throughout and gave me clear aftercare instructions. ' .
                'Front desk followed up the next day to see how I was doing.'
            );
            $this->insertReview(
                $wpdb,
                $acmeId,
                'Daniel K.',
                4.9,
                'Super organized practice with on‑time appointments. ' .
                'They walked me through options for a crown and never pushed extras. ' .
                'Waiting area is calm and the coffee machine is a nice touch.'
            );
            $this->insertReview(
                $wpdb,
                $acmeId,
                'Alicia M.',
                5.0,
                'Brought my teen for Invisalign and the consultation was thorough without being overwhelming. ' .
                'Clear timeline, fair pricing, and they answered all our questions. ' .
                'We feel confident continuing care here.'
            );
        }
        if ($lotusId) {
            // Lotus Textiles reviews (5 total)
            $this->insertReview(
                $wpdb,
                $lotusId,
                'Asha P.',
                5.0,
                'Beautiful fabric selection and honest pricing. ' .
                'The team helped me pick the right silk and arranged quick alterations. ' .
                'I received so many compliments at the event.',
                'en-IN'
            );
            $this->insertReview(
                $wpdb,
                $lotusId,
                'Rohit K.',
                4.6,
                'Quality linens and attentive staff. ' .
                'Turnaround for tailoring was faster than expected and the fit was perfect.',
                'en-IN'
            );
            $this->insertReview(
                $wpdb,
                $lotusId,
                'Neha S.',
                4.8,
                'They sourced a specific shade of chiffon for me within two days. ' .
                'Great communication throughout and careful packaging.',
                'en-IN'
            );
            $this->insertReview(
                $wpdb,
                $lotusId,
                'Imran V.',
                4.7,
                'Got a sherwani tailored here. ' .
                'Professional fittings and precise embroidery work. ' .
                'Delivery was on the promised date.',
                'en-IN'
            );
            $this->insertReview(
                $wpdb,
                $lotusId,
                'Kavita D.',
                4.9,
                'Staff were patient while I compared several silks. ' .
                'They suggested blouse lining and care tips that really helped.',
                'en-IN'
            );
        }
        if ($greenId) {
            // Green Bites reviews (5 total)
            $this->insertReview(
                $wpdb,
                $greenId,
                'Alex P.',
                5.0,
                'Best sourdough in the City. ' .
                'The crust has real depth of flavor and the bowls are generous. ' .
                'Staff remembered my usual after two visits.',
                'en-GB'
            );
            $this->insertReview(
                $wpdb,
                $greenId,
                'Maria G.',
                4.7,
                'Delicious bowls and quick service at lunch. ' .
                'Great coffee with oat milk, and I love the rotating specials.',
                'en-GB'
            );
            $this->insertReview(
                $wpdb,
                $greenId,
                'Tom H.',
                4.6,
                'Great place for a quick, healthy lunch. ' .
                'Seating fills up at noon but the line moves fast.',
                'en-GB'
            );
            $this->insertReview(
                $wpdb,
                $greenId,
                'Ella R.',
                4.8,
                'Excellent espresso and friendly baristas. ' .
                'The vegan bowl had great textures and bright flavors.',
                'en-GB'
            );
            $this->insertReview(
                $wpdb,
                $greenId,
                'Ben S.',
                4.9,
                'Love the seasonal menu changes and the sourdough loaves on Fridays. ' .
                'Consistently great quality.',
                'en-GB'
            );
        }
        if ($swiftId) {
            // Swift Transit reviews (5 total)
            $this->insertReview(
                $wpdb,
                $swiftId,
                'Zoe L.',
                5.0,
                'Super fast and careful with fragile items. ' .
                'They handled our clinic samples with documented chain-of-custody ' .
                'and delivered earlier than promised.',
                'en-AU'
            );
            $this->insertReview(
                $wpdb,
                $swiftId,
                'Nick R.',
                4.8,
                'Great communication and tracking. ' .
                'Dispatch answered within seconds, and the driver called ahead for loading dock access.',
                'en-AU'
            );
            $this->insertReview(
                $wpdb,
                $swiftId,
                'Sam D.',
                4.7,
                'Booked an urgent pickup at 4 pm and it reached the CBD in under an hour. ' .
                'Clear proof‑of‑delivery emailed instantly.',
                'en-AU'
            );
            $this->insertReview(
                $wpdb,
                $swiftId,
                'Priya V.',
                4.9,
                'Courteous drivers and clean vehicles. ' .
                'Our bulk transfers were secured properly and arrived without damage.',
                'en-AU'
            );
            $this->insertReview(
                $wpdb,
                $swiftId,
                'Owen C.',
                4.8,
                'We use their scheduled routes daily. ' .
                'Reliable timings and proactive updates whenever traffic is heavy.',
                'en-AU'
            );
        }
    }
}
