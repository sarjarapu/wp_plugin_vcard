<?php

/**
 * @deprecated This legacy migration has been replaced by Doctrine migrations and seeder services.
 * All table creation is now handled by Doctrine migrations (Version20251103000000 through Version20251110000000).
 * All test data seeding is now handled by MinisiteSeederService, VersionSeederService, and ReviewSeederService.
 * This file is archived in delete_me/ and will be removed in a future version.
 *
 * DO NOT USE THIS CLASS IN NEW CODE.
 */
namespace delete_me\Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Utils\DatabaseHelper as db;
use Minisite\Infrastructure\Utils\SqlLoader;
use Minisite\Infrastructure\Versioning\Contracts\Migration;

// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps -- Migration class with version-based naming convention
class _1_0_0_CreateBase implements Migration
{
    public function version(): string
    {
        return '1.0.0';
    }

    public function description(): string
    {
        // NOTE: Most table creation has been moved to Doctrine migrations
        // This migration now only creates the minisites table and seeds test data
        return 'Create base table: minisites + seed dev data. ' .
               'NOTE: minisite_reviews table is now created by Doctrine migrations (Version20251104000000). ' .
               'NOTE: minisite_versions table is now created by Doctrine migrations (Version20251105000000). ' .
               'NOTE: minisite_bookmarks table is now created by Doctrine migrations (Version20251107000000). ' .
               'NOTE: minisite_payments table is now created by Doctrine migrations (Version20251108000000). ' .
               'NOTE: minisite_payment_history table is now created by Doctrine migrations (Version20251109000000). ' .
               'NOTE: minisite_reservations table and purge event are now created by Doctrine migrations (Version20251110000000)';
    }

    public function up(): void
    {
        global $wpdb;
        $minisites = $wpdb->prefix . 'minisites';
        // NOTE: Reviews table is now managed by Doctrine migrations - do NOT create it here
        // $reviews        = $wpdb->prefix . 'minisite_reviews'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Versions table is now managed by Doctrine migrations - do NOT create it here
        // $versions = $wpdb->prefix . 'minisite_versions'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Bookmarks table is now managed by Doctrine migrations - do NOT create it here
        // $bookmarks = $wpdb->prefix . 'minisite_bookmarks'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Payments table is now managed by Doctrine migrations - do NOT create it here
        // $payments = $wpdb->prefix . 'minisite_payments'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Payment history table is now managed by Doctrine migrations - do NOT create it here
        // $paymentHistory = $wpdb->prefix . 'minisite_payment_history'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Reservations table is now managed by Doctrine migrations - do NOT create it here
        // $reservations = $wpdb->prefix . 'minisite_reservations'; // COMMENTED OUT - Use Doctrine migrations instead

        // ——— minisites (live) ———
        SqlLoader::loadAndExecute(
            'minisites.sql',
            SqlLoader::createStandardVariables($wpdb)
        );

        // ——— versions (new versioning system) ———
        // NOTE: Version table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All version table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251105000000 in Doctrine migrations
        // (Creates complete table with all 27 columns if table doesn't exist,
        //  or adds new columns if table already exists)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_versions.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // ——— reviews ———
        // NOTE: Review table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All review table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251104000000 in Doctrine migrations
        // (Creates complete table with all MVP fields if table doesn't exist,
        //  or adds new columns if table already exists)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_reviews.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // ——— bookmarks ———
        // NOTE: Bookmarks table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All bookmarks table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251107000000 in Doctrine migrations
        // (Creates complete table with foreign keys if table doesn't exist)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_bookmarks.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // ——— payments (single payment for slug ownership + 1 year public access) ———
        // NOTE: Payments table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All payments table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251108000000 in Doctrine migrations
        // (Creates complete table with foreign keys if table doesn't exist)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_payments.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // ——— payment history (for renewals and reclamations) ———
        // NOTE: Payment history table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All payment history table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251109000000 in Doctrine migrations
        // (Creates complete table with foreign keys if table doesn't exist)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_payment_history.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // Reservations table for 5-minute slug reservations
        // NOTE: Reservations table creation has been moved to Doctrine-based migrations.
        // The old SQL-based table creation is commented out below.
        // All reservations table operations should now use Doctrine migrations.
        //
        // Table creation: See Version20251110000000 in Doctrine migrations
        // (Creates complete table with foreign keys and purge event if table doesn't exist)
        //
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute(
            'minisite_reservations.sql',
            SqlLoader::createStandardVariables($wpdb)
        );
        */

        // Add foreign key constraints after table creation (only if they don't exist)
        // NOTE: Versions table foreign key is now managed by Doctrine migrations
        // $versions = $wpdb->prefix . 'minisite_versions'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $versions,
        //     'fk_versions_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );
        // NOTE: Reviews table foreign key is now managed by Doctrine migrations
        // $reviews = $wpdb->prefix . 'minisite_reviews'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $reviews,
        //     'fk_reviews_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );
        // NOTE: Bookmarks table foreign keys are now managed by Doctrine migrations
        // See Version20251107000000 in Doctrine migrations
        // $bookmarks = $wpdb->prefix . 'minisite_bookmarks'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $bookmarks,
        //     'fk_bookmarks_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );
        // NOTE: Payments table foreign keys are now managed by Doctrine migrations
        // See Version20251108000000 in Doctrine migrations
        // $payments = $wpdb->prefix . 'minisite_payments'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $payments,
        //     'fk_payments_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );
        // $this->addForeignKeyIfNotExists(
        //     $payments,
        //     'fk_payments_user_id',
        //     'user_id',
        //     $wpdb->prefix . 'users',
        //     'ID'
        // );
        // NOTE: Payment history table foreign keys are now managed by Doctrine migrations
        // See Version20251109000000 in Doctrine migrations
        // $paymentHistory = $wpdb->prefix . 'minisite_payment_history'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $paymentHistory,
        //     'fk_payment_history_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );
        // $this->addForeignKeyIfNotExists(
        //     $paymentHistory,
        //     'fk_payment_history_payment_id',
        //     'payment_id',
        //     $payments,
        //     'id'
        // );
        // $this->addForeignKeyIfNotExists(
        //     $paymentHistory,
        //     'fk_payment_history_new_owner_user_id',
        //     'new_owner_user_id',
        //     $wpdb->prefix . 'users',
        //     'ID'
        // );
        // NOTE: Reservations table foreign keys are now managed by Doctrine migrations
        // See Version20251110000000 in Doctrine migrations
        // $reservations = $wpdb->prefix . 'minisite_reservations'; // COMMENTED OUT - Use Doctrine migrations instead
        // $this->addForeignKeyIfNotExists(
        //     $reservations,
        //     'fk_reservations_user_id',
        //     'user_id',
        //     $wpdb->prefix . 'users',
        //     'ID'
        // );
        // $this->addForeignKeyIfNotExists(
        //     $reservations,
        //     'fk_reservations_minisite_id',
        //     'minisite_id',
        //     $minisites,
        //     'id'
        // );

        // Create MySQL event for auto-cleanup of expired reservations
        // NOTE: Event creation has been moved to Doctrine-based migrations.
        // See Version20251110000000 in Doctrine migrations
        // OLD SQL FILE LOADING - COMMENTED OUT - DO NOT USE
        /*
        SqlLoader::loadAndExecute('event_purge_reservations.sql', SqlLoader::createStandardVariables($wpdb));
        */

        // —— dev seed: insert two test minisites + revisions + reviews ——
        $this->seedTestData();
    }

    /**
     * Add a foreign key constraint only if it doesn't already exist
     */
    protected function addForeignKeyIfNotExists(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        global $wpdb;
        // Check if the constraint already exists
        $constraintExists = db::get_var(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            array(DB_NAME, $table, $constraintName)
        );

        if (! $constraintExists) {
            db::query(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName}
                 FOREIGN KEY ({$column}) REFERENCES {$referencedTable}({$referencedColumn})
                 ON DELETE CASCADE"
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
            throw new \RuntimeException('JSON file not found: ' . esc_html($jsonPath));
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid JSON in file: ' . esc_html($jsonFile) . '. Error: ' . esc_html(json_last_error_msg())
            );
        }

        if (! isset($data['minisite'])) {
            throw new \RuntimeException(
                'Invalid JSON structure in file: ' . esc_html($jsonFile) . '. Missing \'minisite\' property.'
            );
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
                    'latitude' => (float) $data['location']['latitude'],
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
        $now = current_time('mysql');
        $userId = get_current_user_id() ?: null;

        // Set audit fields - always use current values for seeding
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;
        $data['published_at'] = $data['published_at'] ?? $now;
        $data['created_by'] = $userId; // Always reset to current user for proper ownership
        $data['updated_by'] = $userId; // Always reset to current user for proper ownership

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
    protected function insertMinisite(array $minisiteData, string $name): string
    {
        global $wpdb;
        $minisitesT = $wpdb->prefix . 'minisites';

        db::query(
            "INSERT INTO {$minisitesT} (
                id, slug, business_slug, location_slug, title, name, city, region,
                country_code, postal_code, location_point, site_template, palette,
                industry, default_locale, schema_version, site_version, site_json,
                search_terms, status, publish_status, created_at, updated_at,
                published_at, created_by, updated_by, _minisite_current_version_id
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, POINT(%f, %f), %s, %s, %s,
                %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s
            )",
            array(
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
                wp_json_encode($minisiteData['site_json']),
                $minisiteData['search_terms'],
                $minisiteData['status'],
                $minisiteData['publish_status'],
                $minisiteData['created_at'],
                $minisiteData['updated_at'],
                $minisiteData['published_at'],
                $minisiteData['created_by'],
                $minisiteData['updated_by'],
                $minisiteData['_minisite_current_version_id'],
            )
        );

        // // Debug: Check if minisite was inserted correctly
        // $debugResult = db::get_row(
        //     "SELECT id, business_slug, location_slug, status, _minisite_current_version_id
        //      FROM {$minisitesT} WHERE id = %s",
        //     [$minisiteData['id']]
        // );
        // error_log("{$name} INSERT DEBUG: " . print_r($debugResult, true));

        return $minisiteData['id'];
    }

    /**
     * OLD METHOD - Insert a review using wpdb
     *
     * NOTE: This method has been DEPRECATED and COMMENTED OUT.
     * All review processing, editing, and sample data creation should now use the
     * Doctrine-based ReviewRepository mechanism through ReviewSeederService.
     *
     * This method is kept (commented) for reference only and should NOT be used.
     *
     * @deprecated Use ReviewSeederService or ReviewRepository instead
     * @see \Minisite\Features\ReviewManagement\Services\ReviewSeederService
     */
    /*
    protected function insertReview(
        string $minisiteId,
        string $authorName,
        float $rating,
        string $body,
        ?string $locale = 'en-US'
    ): void {
        global $wpdb;
        $reviewsT = $wpdb->prefix . 'minisite_reviews';
        $nowUser  = get_current_user_id() ?: null;

        db::insert(
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
    */

    public function down(): void
    {
        global $wpdb;
        $minisites = $wpdb->prefix . 'minisites';
        // NOTE: Versions table is now managed by Doctrine migrations - do NOT drop it here
        // $versions = $wpdb->prefix . 'minisite_versions'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Reviews table is now managed by Doctrine migrations - do NOT drop it here
        // $reviews = $wpdb->prefix . 'minisite_reviews'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Bookmarks table is now managed by Doctrine migrations - do NOT drop it here
        // $bookmarks = $wpdb->prefix . 'minisite_bookmarks'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Payments table is now managed by Doctrine migrations - do NOT drop it here
        // $payments = $wpdb->prefix . 'minisite_payments'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Payment history table is now managed by Doctrine migrations - do NOT drop it here
        // $paymentHistory = $wpdb->prefix . 'minisite_payment_history'; // COMMENTED OUT - Use Doctrine migrations instead
        // NOTE: Reservations table is now managed by Doctrine migrations - do NOT drop it here
        // $reservations = $wpdb->prefix . 'minisite_reservations'; // COMMENTED OUT - Use Doctrine migrations instead

        // Drop MySQL event
        // NOTE: Event is now managed by Doctrine migrations - do NOT drop it here
        // See Version20251110000000 in Doctrine migrations
        // db::query("DROP EVENT IF EXISTS {$wpdb->prefix}minisite_purge_reservations_event");

        // NOTE: All table drops are now managed by Doctrine migrations
        // db::query("DROP TABLE IF EXISTS {$reservations}"); // COMMENTED OUT - Use Doctrine migrations instead
        // db::query("DROP TABLE IF EXISTS {$paymentHistory}"); // COMMENTED OUT - Use Doctrine migrations instead
        // db::query("DROP TABLE IF EXISTS {$payments}"); // COMMENTED OUT - Use Doctrine migrations instead
        // db::query("DROP TABLE IF EXISTS {$bookmarks}"); // COMMENTED OUT - Use Doctrine migrations instead
        // db::query("DROP TABLE IF EXISTS {$reviews}"); // COMMENTED OUT - Use Doctrine migrations instead
        // db::query("DROP TABLE IF EXISTS {$versions}"); // COMMENTED OUT - Use Doctrine migrations instead
        db::query("DROP TABLE IF EXISTS {$minisites}");
    }

    /**
     * Seed four example minisites and their versions & reviews.
     * - 'acme-dental' / 'dallas'
     * - 'lotus-textiles' / 'mumbai'
     * - 'green-bites' / 'london'
     * - 'swift-transit' / 'sydney'
     *
     * NOTE: This method now uses Doctrine-based seeder services:
     * - MinisiteSeederService for minisites
     * - VersionSeederService for versions
     * - ReviewSeederService for reviews
     */
    protected function seedTestData(): void
    {
        global $wpdb;
        $minisitesT = $wpdb->prefix . 'minisites';

        // Avoid duplicate seeding (check any of our seeded slugs)
        $exists = (int) db::get_var(
            "SELECT COUNT(*) FROM {$minisitesT}
             WHERE (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)
                OR (business_slug=%s AND location_slug=%s)",
            array(
                'acme-dental',
                'dallas',
                'lotus-textiles',
                'mumbai',
                'green-bites',
                'london',
                'swift-transit',
                'sydney',
            )
        );
        if ($exists > 0) {
            return;
        }

        // Ensure Doctrine is initialized and repositories are available
        // If not in global, try to initialize it (migration might run before PluginBootstrap)
        if (! isset($GLOBALS['minisite_entity_manager'])) {
            if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                $GLOBALS['minisite_entity_manager'] =
                    \Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory::createEntityManager();
            } else {
                error_log('Doctrine ORM not available - skipping minisite seeding');

                return;
            }
        }

        // Ensure Doctrine migrations have run (create tables if needed)
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $GLOBALS['minisite_entity_manager'];
        $migrationRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner($em);
        $migrationRunner->migrate();

        // Create repositories if not already in globals
        if (! isset($GLOBALS['minisite_repository'])) {
            $minisiteRepo = new \Minisite\Features\MinisiteManagement\Repositories\MinisiteRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\MinisiteManagement\Domain\Entities\Minisite::class)
            );
            $GLOBALS['minisite_repository'] = $minisiteRepo;
        }

        if (! isset($GLOBALS['minisite_version_repository'])) {
            $versionRepo = new \Minisite\Features\VersionManagement\Repositories\VersionRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\VersionManagement\Domain\Entities\Version::class)
            );
            $GLOBALS['minisite_version_repository'] = $versionRepo;
        }

        if (! isset($GLOBALS['minisite_review_repository'])) {
            $reviewRepo = new \Minisite\Features\ReviewManagement\Repositories\ReviewRepository(
                $em,
                $em->getClassMetadata(\Minisite\Features\ReviewManagement\Domain\Entities\Review::class)
            );
            $GLOBALS['minisite_review_repository'] = $reviewRepo;
        }

        // Get repositories
        /** @var \Minisite\Features\MinisiteManagement\Domain\Interfaces\MinisiteRepositoryInterface $minisiteRepo */
        $minisiteRepo = $GLOBALS['minisite_repository'];
        /** @var \Minisite\Features\VersionManagement\Domain\Interfaces\VersionRepositoryInterface $versionRepo */
        $versionRepo = $GLOBALS['minisite_version_repository'];
        /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface $reviewRepo */
        $reviewRepo = $GLOBALS['minisite_review_repository'];

        // Seed minisites using MinisiteSeederService
        $minisiteSeeder = new \Minisite\Features\MinisiteManagement\Services\MinisiteSeederService($minisiteRepo);
        $minisiteIds = $minisiteSeeder->seedAllTestMinisites();

        // Extract individual IDs for backward compatibility
        $acmeId = $minisiteIds['ACME'] ?? null;
        $lotusId = $minisiteIds['LOTUS'] ?? null;
        $greenId = $minisiteIds['GREEN'] ?? null;
        $swiftId = $minisiteIds['SWIFT'] ?? null;

        // ——— Versions for each profile (version 1 as published) ———
        // NOTE: Version seeding now uses Doctrine-based VersionSeederService
        // All version operations use VersionRepository through VersionSeederService
        $versionSeeder = new \Minisite\Features\VersionManagement\Services\VersionSeederService($versionRepo);

        // Create version 1 for each minisite (published, with all minisite fields)
        $minisiteIdMap = array(
            'ACME' => $acmeId,
            'LOTUS' => $lotusId,
            'GREEN' => $greenId,
            'SWIFT' => $swiftId,
        );

        foreach ($minisiteIdMap as $key => $minisiteId) {
            if (empty($minisiteId)) {
                continue;
            }

            // Get the minisite entity
            $minisite = $minisiteRepo->findById($minisiteId);
            if (! $minisite) {
                continue;
            }

            // Create version 1 as published with all profile fields
            $versionData = array(
                'minisite_id' => $minisiteId,
                'version_number' => 1,
                'status' => 'published',
                'label' => 'Initial version',
                'comment' => 'Migrated from existing data',
                'created_by' => get_current_user_id() ?: null,
                'created_at' => current_time('mysql'),
                'published_at' => current_time('mysql'),
                'source_version_id' => null,

                // Profile fields (exact match with profiles table order)
                'business_slug' => $minisite->businessSlug ?? null,
                'location_slug' => $minisite->locationSlug ?? null,
                'title' => $minisite->title ?? null,
                'name' => $minisite->name ?? null,
                'city' => $minisite->city ?? null,
                'region' => $minisite->region ?? null,
                'country_code' => $minisite->countryCode ?? null,
                'postal_code' => $minisite->postalCode ?? null,
                'location_point' => null, // Will be set separately if needed
                'site_template' => $minisite->siteTemplate ?? null,
                'palette' => $minisite->palette ?? null,
                'industry' => $minisite->industry ?? null,
                'default_locale' => $minisite->defaultLocale ?? null,
                'schema_version' => $minisite->schemaVersion ?? null,
                'site_version' => $minisite->siteVersion ?? null,
                'site_json' => $minisite->siteJson ?? '{}',
                'search_terms' => $minisite->searchTerms ?? null,
            );

            db::insert(
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

            $versionId = db::get_insert_id();

            // Set location_point based on profile ID (hardcoded coordinates)
            $coordinates = array(
                $acmeId => array( -96.7970, 32.7767 ),   // Dallas, TX
                $lotusId => array( 72.8777, 19.0760 ),   // Mumbai, IN
                $greenId => array( -0.118092, 51.509865 ), // London, GB
                $swiftId => array( 151.2093, -33.8688 ),   // Sydney, AU
            );

            // Create initial version from minisite using VersionSeederService
            $version = $versionSeeder->createInitialVersionFromMinisite($minisite);
            $savedVersion = $versionRepo->save($version);

            // Update minisite with current version ID
            $minisiteRepo->updateCurrentVersionId($minisiteId, $savedVersion->id);
        }

        // ——— Reviews (5 per minisite) ———
        // NOTE: Review seeding now uses Doctrine-based ReviewSeederService
        // All review operations use ReviewRepository through ReviewSeederService
        if (! empty($acmeId) || ! empty($lotusId) || ! empty($greenId) || ! empty($swiftId)) {
            // Ensure Doctrine is initialized and ReviewRepository is available
            // If not in global, try to initialize it (migration might run before PluginBootstrap)
            if (! isset($GLOBALS['minisite_review_repository'])) {
                // Try to initialize Doctrine if not already done
                if (! isset($GLOBALS['minisite_entity_manager'])) {
                    if (class_exists(\Doctrine\ORM\EntityManager::class)) {
                        $GLOBALS['minisite_entity_manager'] =
                            \Minisite\Infrastructure\Persistence\Doctrine\DoctrineFactory::createEntityManager();
                    } else {
                        error_log('Doctrine ORM not available - skipping review seeding');

                        return;
                    }
                }

                // Ensure Doctrine migrations have run (create reviews table if needed)
                /** @var \Doctrine\ORM\EntityManager $em */
                $em = $GLOBALS['minisite_entity_manager'];
                $migrationRunner = new \Minisite\Infrastructure\Migrations\Doctrine\DoctrineMigrationRunner($em);
                $migrationRunner->migrate();

                // Create ReviewRepository
                $reviewRepo = new \Minisite\Features\ReviewManagement\Repositories\ReviewRepository(
                    $em,
                    $em->getClassMetadata(\Minisite\Features\ReviewManagement\Domain\Entities\Review::class)
                );
                $GLOBALS['minisite_review_repository'] = $reviewRepo;
            }

            /** @var \Minisite\Features\ReviewManagement\Repositories\ReviewRepositoryInterface $reviewRepo */
            $reviewRepo = $GLOBALS['minisite_review_repository'];
            $seeder = new \Minisite\Features\ReviewManagement\Services\ReviewSeederService($reviewRepo);
            $seeder->seedAllTestReviews(array(
                'ACME' => $acmeId,
                'LOTUS' => $lotusId,
                'GREEN' => $greenId,
                'SWIFT' => $swiftId,
            ));
        }

        // OLD REVIEW INSERTION CODE - COMMENTED OUT - DO NOT USE
        // All review operations should use Doctrine ReviewRepository instead
        /*
        if ($acmeId) {
            // ACME Dental reviews (5 total)
            $this->insertReview(
                $acmeId,
                'Jane Doe',
                5.0,
                'The hygienist was incredibly gentle and explained every step before she started. ' .
                'The clinic is spotless and the equipment looks brand new. ' .
                'I left feeling well cared for and finally not dreading my next visit.'
            );
            $this->insertReview(
                $acmeId,
                'Mark T.',
                4.5,
                'Booked a last‑minute appointment for a chipped tooth and they fit me in the same day. ' .
                'The repair was quick and painless, and the billing was clear. ' .
                'Parking was easy which is a bonus in Dallas.'
            );
            $this->insertReview(
                $acmeId,
                'Priya S.',
                4.8,
                'I had whitening done here and the results were immediate. ' .
                'The dentist checked sensitivity throughout and gave me clear aftercare instructions. ' .
                'Front desk followed up the next day to see how I was doing.'
            );
            $this->insertReview(
                $acmeId,
                'Daniel K.',
                4.9,
                'Super organized practice with on‑time appointments. ' .
                'They walked me through options for a crown and never pushed extras. ' .
                'Waiting area is calm and the coffee machine is a nice touch.'
            );
            $this->insertReview(
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
                $lotusId,
                'Asha P.',
                5.0,
                'Beautiful fabric selection and honest pricing. ' .
                'The team helped me pick the right silk and arranged quick alterations. ' .
                'I received so many compliments at the event.',
                'en-IN'
            );
            $this->insertReview(
                $lotusId,
                'Rohit K.',
                4.6,
                'Quality linens and attentive staff. ' .
                'Turnaround for tailoring was faster than expected and the fit was perfect.',
                'en-IN'
            );
            $this->insertReview(
                $lotusId,
                'Neha S.',
                4.8,
                'They sourced a specific shade of chiffon for me within two days. ' .
                'Great communication throughout and careful packaging.',
                'en-IN'
            );
            $this->insertReview(
                $lotusId,
                'Imran V.',
                4.7,
                'Got a sherwani tailored here. ' .
                'Professional fittings and precise embroidery work. ' .
                'Delivery was on the promised date.',
                'en-IN'
            );
            $this->insertReview(
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
                $greenId,
                'Alex P.',
                5.0,
                'Best sourdough in the City. ' .
                'The crust has real depth of flavor and the bowls are generous. ' .
                'Staff remembered my usual after two visits.',
                'en-GB'
            );
            $this->insertReview(
                $greenId,
                'Maria G.',
                4.7,
                'Delicious bowls and quick service at lunch. ' .
                'Great coffee with oat milk, and I love the rotating specials.',
                'en-GB'
            );
            $this->insertReview(
                $greenId,
                'Tom H.',
                4.6,
                'Great place for a quick, healthy lunch. ' .
                'Seating fills up at noon but the line moves fast.',
                'en-GB'
            );
            $this->insertReview(
                $greenId,
                'Ella R.',
                4.8,
                'Excellent espresso and friendly baristas. ' .
                'The vegan bowl had great textures and bright flavors.',
                'en-GB'
            );
            $this->insertReview(
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
                $swiftId,
                'Zoe L.',
                5.0,
                'Super fast and careful with fragile items. ' .
                'They handled our clinic samples with documented chain-of-custody ' .
                'and delivered earlier than promised.',
                'en-AU'
            );
            $this->insertReview(
                $swiftId,
                'Nick R.',
                4.8,
                'Great communication and tracking. ' .
                'Dispatch answered within seconds, and the driver called ahead for loading dock access.',
                'en-AU'
            );
            $this->insertReview(
                $swiftId,
                'Sam D.',
                4.7,
                'Booked an urgent pickup at 4 pm and it reached the CBD in under an hour. ' .
                'Clear proof‑of‑delivery emailed instantly.',
                'en-AU'
            );
            $this->insertReview(
                $swiftId,
                'Priya V.',
                4.9,
                'Courteous drivers and clean vehicles. ' .
                'Our bulk transfers were secured properly and arrived without damage.',
                'en-AU'
            );
            $this->insertReview(
                $swiftId,
                'Owen C.',
                4.8,
                'We use their scheduled routes daily. ' .
                'Reliable timings and proactive updates whenever traffic is heavy.',
                'en-AU'
            );
        }
        */
        // END OF COMMENTED OUT REVIEW INSERTION CODE
    }
}
