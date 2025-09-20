<?php
namespace Minisite\Infrastructure\Versioning\Migrations;

use Minisite\Infrastructure\Versioning\Contracts\Migration;
use Minisite\Infrastructure\Versioning\Support\DbDelta;

class _1_0_0_CreateBase implements Migration
{
    public function version(): string { return '1.0.0'; }

    public function description(): string
    {
        return 'Create base tables: minisite_profiles, minisite_profile_revisions, minisite_versions (with complete profile field versioning), minisite_reviews + seed dev data';
    }

    public function up(\wpdb $wpdb): void
    {
        $charset   = $wpdb->get_charset_collate();
        $profiles  = $wpdb->prefix . 'minisites';
        $reviews   = $wpdb->prefix . 'minisite_reviews';
        $versions = $wpdb->prefix . 'minisite_versions';
        $bookmarks = $wpdb->prefix . 'minisite_bookmarks';

        // ——— minisites (live) ———
        DbDelta::run("CREATE TABLE {$profiles} (
          id VARCHAR(32) NOT NULL,
          slug VARCHAR(255) NULL,
          business_slug VARCHAR(120) NULL,
          location_slug VARCHAR(120) NULL,
          title VARCHAR(200) NOT NULL,
          name VARCHAR(200) NOT NULL,
          city VARCHAR(120) NOT NULL,
          region VARCHAR(120) NULL,
          country_code CHAR(2) NOT NULL,
          postal_code VARCHAR(20) NULL,
          location_point POINT NULL,
          site_template VARCHAR(32) NOT NULL DEFAULT 'v2025',
          palette VARCHAR(24) NOT NULL DEFAULT 'blue',
          industry VARCHAR(40) NOT NULL DEFAULT 'services',
          default_locale VARCHAR(10) NOT NULL DEFAULT 'en-US',
          schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
          site_version INT UNSIGNED NOT NULL DEFAULT 1,
          site_json LONGTEXT NOT NULL,
          search_terms TEXT NULL,
          status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
          publish_status ENUM('draft','reserved','published') NOT NULL DEFAULT 'draft',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          published_at DATETIME NULL,
          created_by BIGINT UNSIGNED NULL,
          updated_by BIGINT UNSIGNED NULL,
          _minisite_current_version_id BIGINT UNSIGNED NULL,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_slug (slug),
          UNIQUE KEY uniq_business_location (business_slug, location_slug)
        ) ENGINE=InnoDB {$charset};");


        // ——— versions (new versioning system) ———
        DbDelta::run("CREATE TABLE {$versions} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          minisite_id VARCHAR(32) NOT NULL,
          version_number INT UNSIGNED NOT NULL,
          status ENUM('draft','published') NOT NULL,
          label VARCHAR(120) NULL,
          comment TEXT NULL,
          created_by BIGINT UNSIGNED NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          published_at DATETIME NULL,
          source_version_id BIGINT UNSIGNED NULL,
          business_slug VARCHAR(120) NULL,
          location_slug VARCHAR(120) NULL,
          title VARCHAR(200) NULL,
          name VARCHAR(200) NULL,
          city VARCHAR(120) NULL,
          region VARCHAR(120) NULL,
          country_code CHAR(2) NULL,
          postal_code VARCHAR(20) NULL,
          location_point POINT NULL,
          site_template VARCHAR(32) NULL,
          palette VARCHAR(24) NULL,
          industry VARCHAR(40) NULL,
          default_locale VARCHAR(10) NULL,
          schema_version SMALLINT UNSIGNED NULL,
          site_version INT UNSIGNED NULL,
          site_json LONGTEXT NOT NULL,
          search_terms TEXT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_minisite_version (minisite_id, version_number),
          KEY idx_minisite_status (minisite_id, status),
          KEY idx_minisite_created (minisite_id, created_at)
        ) ENGINE=InnoDB {$charset};");

        // ——— reviews ———
        DbDelta::run("CREATE TABLE {$reviews} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          minisite_id VARCHAR(32) NOT NULL,
          author_name VARCHAR(160) NOT NULL,
          author_url VARCHAR(300) NULL,
          rating DECIMAL(2,1) NOT NULL,
          body MEDIUMTEXT NOT NULL,
          locale VARCHAR(10) NULL,
          visited_month CHAR(7) NULL,
          source ENUM('manual','google','yelp','facebook','other') NOT NULL DEFAULT 'manual',
          source_id VARCHAR(160) NULL,
          status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          created_by BIGINT UNSIGNED NULL,
          PRIMARY KEY (id),
          KEY idx_minisite (minisite_id),
          KEY idx_status_date (status, created_at),
          KEY idx_rating (rating)
        ) ENGINE=InnoDB {$charset};");

        // ——— bookmarks ———
        DbDelta::run("CREATE TABLE {$bookmarks} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          minisite_id VARCHAR(32) NOT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_user_minisite (user_id, minisite_id),
          KEY idx_user (user_id),
          KEY idx_minisite (minisite_id)
        ) ENGINE=InnoDB {$charset};");

        // Add foreign key constraints after table creation (only if they don't exist)
        $this->addForeignKeyIfNotExists($wpdb, $versions, 'fk_versions_minisite_id', 'minisite_id', $profiles, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $reviews, 'fk_reviews_minisite_id', 'minisite_id', $profiles, 'id');
        $this->addForeignKeyIfNotExists($wpdb, $bookmarks, 'fk_bookmarks_minisite_id', 'minisite_id', $profiles, 'id');

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

    public function down(\wpdb $wpdb): void
    {
        // Dev convenience: clear the seeded data then drop tables
        $this->clearTestData($wpdb);

        $profiles  = $wpdb->prefix . 'minisites';
        $versions  = $wpdb->prefix . 'minisite_versions';
        $reviews   = $wpdb->prefix . 'minisite_reviews';
        $bookmarks = $wpdb->prefix . 'minisite_bookmarks';

        $wpdb->query("DROP TABLE IF EXISTS {$bookmarks}");
        $wpdb->query("DROP TABLE IF EXISTS {$reviews}");
        $wpdb->query("DROP TABLE IF EXISTS {$versions}");
        $wpdb->query("DROP TABLE IF EXISTS {$profiles}");
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
        $profilesT  = $wpdb->prefix . 'minisites';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Avoid duplicate seeding (check any of our seeded slugs)
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$profilesT}
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

        // Helper to build site_json with realistic per-business data
        $buildJson = function(array $a): string {
            $name   = $a['name'];
            $city   = $a['city'];
            $seo    = $a['seo']    ?? [];
            $brand  = $a['brand']  ?? [];
            $hero   = $a['hero']   ?? [];
            $about  = $a['about']  ?? [];
            $whyUs  = $a['whyUs']  ?? [];
            $svcs   = $a['services'] ?? [];
            $contact= $a['contact']?? [];
            $gallery= $a['gallery']?? [];
            $social = $a['social'] ?? [];
            $data = [
                'seo'   => $seo, # + [ 'title' => "$name — $city", 'description' => $description, 'keywords' => $keywords ],
                'brand' => $brand + [ 'name' => $name, 'logo' => null, 'palette' => 'blue', 'industry' => 'services' ],
                'hero'  => $hero,
                'about' => $about,
                'whyUs' => $whyUs,
                'services' => $svcs,
                'contact'  => $contact,
                'gallery'  => $gallery,
                'social'   => $social,
            ];
            return wp_json_encode($data);
        };

        // Insert first profile: ACME Dental (Dallas, US)
        $acmeId = bin2hex(random_bytes(16));
        $acme = [
            'id'             => $acmeId,
            'business_slug'  => 'acme-dental',
            'location_slug'  => 'dallas',
            'title'          => 'Acme Dental — Dallas',
            'name'           => 'Acme Dental',
            'city'           => 'Dallas',
            'region'         => 'TX',
            'country_code'   => 'US',
            'postal_code'    => '75201',
            'site_template'  => 'v2025',
            'palette'        => 'blue',
            'industry'       => 'dentist',
            'default_locale' => 'en-US',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name' => 'Acme Dental', 'city' => 'Dallas',
                'brand' => ['palette' => 'blue', 'industry' => 'services', 'logo' => 'https://cdn-icons-png.flaticon.com/512/8718/8718971.png'],
                'seo'   => [ 
                    'title' => 'Acme Dental — Dallas', 
                    'description' => 'Acme Dental delivers modern, patient-first dental care backed by advanced imaging and minimally invasive techniques.',
                    'keywords' => 'acme dental, dental care, dallas, tx, services, clinic',
                    'favicon' => 'https://cdn-icons-png.flaticon.com/512/8718/8718971.png',
                ],
                'hero' => [
                    'heading' => 'Acme Dental Care',
                    'subheading' => 'Your friendly Dallas,TX neighborhood dental clinic offering Preventative, cosmetic and emergency dentistry exceptional dental care in a very comfortable, relaxing stress-free environment at affordable prices.',
                    'badge' => 'Verified Business',
                    'image' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=1600&q=80&auto=format&fit=crop',
                    'rating' => ['value' => 4.8, 'count' => 238],
                    'ctas'   => [ ['text'=>'Request Info','url'=>'#request-info'], ['text'=>'Contact Us','url'=>'#contact'] ],
                ],
                'about' => [ 'html' => '<p>Acme Dental delivers modern, patient-first dental care backed by advanced imaging and minimally invasive techniques.</p><p>From routine cleanings to implants and cosmetic treatments, our Dallas team creates a calm experience with lasting results.</p><p>We welcome families and offer flexible scheduling and transparent pricing.</p><br/><p>Understanding the dental insurance plans, the different levels of participation and payout options you chose could be a daunting task for many patients. Ask our expertly trained dental professionals at Cedar Park office how you could utilize the plan benefits to the maximum and avoid paying any unnecessary out of pocket charges.</p>' ],
                'whyUs' => [ 'title' => 'Why Choose Us?', 'html' => '<p>The patient coordinators at Aviva Dental Care of Cedar Park ensure that you have plenty of time for each appointment. The additional time will allow us to know you and your dental priorities better. You will also have ample time to discuss any of your questions, concerns about oral health. We seldom run behind, so you should not have to wait for us after your scheduled appointment time.</p><br/><p>Whether you need a routine exam or experiencing a dental emergency, we will thoroughly answer all your questions or concerns in a relaxed, pressure-free environment. Finally, you will never feel rushed or forced to take a treatment plan that you don\'t need. So, you won\'t have to worry about surprise costs or how to fit your dental goals into your budget.</p>','image'=>'https://images.unsplash.com/photo-1704455306251-b4634215d98f?auto=format&fit=crop&w=1600' ],
                'services' => [
                    'title' => 'Products & Services',
                    'listing' => [
                        [
                            'title'=>'General Dentistry',
                            'image'=>'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?q=80&w=800&auto=format&fit=crop',
                            'description'=>'A semi-annual visit to our dentist enables early detection of various oral complications and provide treatment early in the disease process.',
                            'price'=>'$199',
                            'icon' => 'fa-circle-info',
                            'cta'=>'More info',
                            'url'=>'#request-info'
                        ],
                        [
                            'title'=>'Teeth Whitening',
                            'image'=>'https://images.unsplash.com/photo-1489278353717-f64c6ee8a4d2?q=80&w=800&auto=format&fit=crop',
                            'description'=>'Whitening is a painless procedure brightens your teeth by several within an hour. The teeth whitening is effective on natural tooth enamel but not on restorations.',
                            'price'=>'$499',
                            'icon'=>'fa-circle-info',
                            'cta'=>'Contact Us',
                            'url'=>'#contact'
                        ],
                        [
                            'title'=>'Dentures & Crowns',
                            'image'=>'https://images.unsplash.com/photo-1654373535457-383a0a4d00f9?q=80&w=1200&auto=format&fit=crop',
                            'description'=>'The dental crowns are durable and natural-looking tooth-shaped caps fitted over any remaining healthy tooth structure or a dental implant to restore the tooth\'s health.',
                            'price'=>'Call for pricing',
                            'icon'=>'fa-crown',
                            'cta'=>'Call Us',
                            'url'=>'#request-info'
                        ],
                        [
                            'title'=>'Invisalign',
                            'image'=>'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?q=80&w=800&auto=format&fit=crop',
                            'description'=>'Show off your smile as people won\'t even notice you\'re wearing transparent Invisalign aligners when compared to the traditional metal braces options. It\'s easy to remove aligners and eat whatever you like. There are no wires to trap food or get in the way when you floss.',
                            'price'=>'$3,999',
                            'icon' => 'fa-bolt',
                            'cta'=>'Enquire',
                            'url'=>'#request-info'
                        ],
                        [
                            'title'=>'Dental Implants',
                            'image'=>'https://images.unsplash.com/photo-1593022356769-11f762e25ed9?q=80&w=1200&auto=format&fit=crop',
                            'description'=>'A dental implant is a metal post that functions as a replacement tooth root. Once your dentist places the implant in the bone of the jaw, new bone forms around the implant to firmly hold it in place. Implants can also support dental restorations like crowns and bridges, just like regular teeth. They can even support full or partial dentures and give them more stability in the mouth.',
                            'price'=>'Call for pricing',
                            'icon' => 'fa-circle-info',
                            'cta'=>'Enquire',
                            'url'=>'#request-info'
                        ],
                    ]
                ],
                'contact' => [
                    'phone'=>['text'=>'+1 (214) 555-0123', 'link'=>'+12145550123'],
                    'whatsapp'=>['text'=>'+1 (214) 555-0123', 'link'=>'12145550123'],
                    'email'=>'care@acmedental.us',
                    'website'=>['text'=>'acmedental.us', 'link'=>'https://acmedental.us'],
                    'address'=>'123 Main St, Dallas, TX 75201',
                    'address_line1'=>'123 Main St',
                    'address_line2'=>'Suite 200',
                    'address_line3'=>'','address_line4'=>'',
                    'hours'=>[
                        'Monday'=>['open'=>'08:00','close'=>'17:00','closed'=>false],
                        'Tuesday'=>['open'=>'08:00','close'=>'17:00','closed'=>false],
                        'Wednesday'=>['open'=>'08:00','close'=>'17:00','closed'=>false],
                        'Thursday'=>['open'=>'08:00','close'=>'17:00','closed'=>false],
                        'Friday'=>['open'=>'08:00','close'=>'15:00','closed'=>false],
                        'Saturday'=>['open'=>'09:00','close'=>'13:00','closed'=>false],
                        'Sunday'=>['open'=>'','close'=>'','closed'=>true]
                    ],
                    'plusCode'=>'77CM+4R Dallas','plusCodeUrl'=>'https://maps.google.com/?q=77CM+4R+Dallas'
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1670250492416-570b5b7343b1?q=80&w=1600&auto=format&fit=crop','alt'=>'Dental checkup visit'],
                    ['src'=>'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?q=80&w=1600&auto=format&fit=crop','alt'=>'Cleaning and tar-tar removal'],
                    ['src'=>'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?q=80&w=800&auto=format&fit=crop','alt'=>'Clear aligners'],
                    ['src'=>'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?q=80&w=800&auto=format&fit=crop','alt'=>'Dental hygiene'],
                    ['src'=>'https://images.unsplash.com/photo-1489278353717-f64c6ee8a4d2?q=80&w=800&auto=format&fit=crop','alt'=>'Teeth whitening'],
                    ['src'=>'https://images.unsplash.com/photo-1564420228450-d9a5bc8d6565?q=80&w=800&auto=format&fit=crop','alt'=>'Dental products'],
                    ['src'=>'https://images.unsplash.com/photo-1660737217837-95f00b9eae53?q=80&w=800&auto=format&fit=crop','alt'=>'Porcelain veneers'],
                    
                ],
                'social'=>[
                    'facebook'=>'https://facebook.com/acmedental.dallas',
                    'instagram'=>'https://instagram.com/acmedental.dallas',
                    'x'=>'https://x.com/acmedental.dallas',
                    'linkedin'=>'https://linkedin.com/acmedental.dallas',
                    'youtube'=>'https://youtube.com/acmedental.dallas',
                    'tiktok'=>'https://tiktok.com/acmedental.dallas',
                ],
            ]),
            'search_terms'   => 'acme dental dentist dallas tx services clinic',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $acme, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%d','%d','%d'
        ]);
        $acmeId = $acme['id']; // Use our custom ID

        // Set POINT (lng, lat) with SRID 4326 if available (MySQL 8)
        if ($acmeId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                -96.7970, 32.7767, $acmeId
            ));
        }

        // Insert second profile: Lotus Textiles (Mumbai, IN)
        $lotusId = bin2hex(random_bytes(16));
        $lotus = [
            'id'             => $lotusId,
            'business_slug'  => 'lotus-textiles',
            'location_slug'  => 'mumbai',
            'title'          => 'Lotus Textiles — Mumbai',
            'name'           => 'Lotus Textiles',
            'city'           => 'Mumbai',
            'region'         => 'MH',
            'country_code'   => 'IN',
            'postal_code'    => '400001',
            'site_template'  => 'v2025',
            'palette'        => 'rose',
            'industry'       => 'textile',
            'default_locale' => 'en-IN',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name'=>'Lotus Textiles','city'=>'Mumbai',
                'brand'=>[
                    'palette'=>'rose','industry'=>'textile',
                    'logo'=>'https://cdn-icons-png.flaticon.com/512/6010/6010534.png'
                ],
                'seo'=>[
                    'title'=>'Lotus Textiles — Mumbai',
                    'description'=>'Fine silks, linens, and custom tailoring in Colaba. Contemporary designs rooted in Indian handloom traditions.',
                    'keywords'=>'lotus textiles, fabrics, handloom, mumbai, tailoring',
                    'favicon'=>'https://cdn-icons-png.flaticon.com/512/6010/6010534.png'
                ],
                'hero'=>[
                    'heading'=>'Lotus Textiles',
                    'subheading'=>'Your Colaba, Mumbai fabric house offering premium silks, breathable linens, and bespoke tailoring with attentive fittings in a relaxed studio at fair, transparent prices.',
                    'badge'=>'Trusted Supplier',
                    'image'=>'https://images.unsplash.com/photo-1542044801-30d3e45ae49a?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.7,'count'=>412],
                    'ctas'=>[ ['text'=>'Browse Collection','url'=>'#products'], ['text'=>'Contact','url'=>'#contact'] ],
                ],
                'about'=>['html'=>'<p>At Lotus Textiles, we curate fine silks, linens, and cottons from across India. Our artisans and suppliers are carefully selected for quality and ethical practices.</p><br/><p>Our in-house designers craft contemporary styles while preserving handloom traditions. Whether you need luxury Banarasi silk or breathable linen, our team will help you match fabric to occasion and fit.</p><br/><p>We supply boutiques and provide made-to-measure services with transparent pricing and turnaround times.</p>'],
                'whyUs'=>['title'=>'Why Choose Us?','html'=>'<p>We combine premium materials with tailored service. Our consultants guide you through fabric selection, lining, and care so your garments last for years.</p><br/><p>Alterations and bespoke stitching are done in-house for consistency and speed. Enjoy convenient fittings and delivery across Mumbai.</p>', 'image'=>'https://images.unsplash.com/photo-1524292332709-b33366a7f165?auto=format&fit=crop&w=1600'],
                'services'=>[
                    'title'=>'Products & Services',
                    'listing'=>[
                        ['title'=>'Handloom Silks','image'=>'https://images.unsplash.com/photo-1534639077088-d702bcf685e7?q=80&w=800&auto=format&fit=crop','description'=>'Banarasi, Kanchipuram, and more. Rich textures and timeless patterns.','price'=>'From ₹3,999','icon'=>'fa-swatchbook','cta'=>'Enquire','url'=>'#request-info'],
                        ['title'=>'Linen Collections','image'=>'https://images.unsplash.com/photo-1632421377986-0b1f70773812?q=80&w=800&auto=format&fit=crop','description'=>'Lightweight linens ideal for Mumbai summers. Multiple shades and weights.','price'=>'From ₹1,999','icon'=>'fa-shirt','cta'=>'Enquire','url'=>'#request-info'],
                        ['title'=>'Tailoring','image'=>'https://images.unsplash.com/photo-1534126511673-b6899657816a?q=80&w=800&auto=format&fit=crop','description'=>'Custom stitching & fittings for suits, blouses, and kurtas.','price'=>'Quoted','icon'=>'fa-scissors','cta'=>'Book','url'=>'tel:+912266601234'],
                        ['title'=>'Embroidery','image'=>'https://images.unsplash.com/photo-1623605004748-3af12342204f?q=80&w=800&auto=format&fit=crop','description'=>'Hand and machine embroidery with zari and threadwork to match your design.','price'=>'Quoted','icon'=>'fa-needle','cta'=>'Enquire','url'=>'#request-info'],
                        ['title'=>'Dyeing & Finishing','image'=>'https://images.unsplash.com/photo-1506034844286-f98ed954e516?q=80&w=800&auto=format&fit=crop','description'=>'Custom dye shades and fabric finishing for drape and handfeel.','price'=>'Quoted','icon'=>'fa-flask','cta'=>'Enquire','url'=>'#request-info']
                    ]
                ],
                'contact'=>[
                    'phone'=>['text'=>'+91 22 6660 1234','link'=>'+912266601234'],
                    'whatsapp'=>['text'=>'+91 22 6660 1234','link'=>'912266601234'],
                    'email'=>'hello@lotustextiles.in',
                    'website'=>['text'=>'lotustextiles.in','link'=>'https://lotustextiles.in'],
                    'address'=>'12 Colaba Causeway, Mumbai 400001',
                    'address_line1'=>'12 Colaba Causeway','address_line2'=>'2nd Floor','address_line3'=>'','address_line4'=>'',
                    'hours'=>[
                        'Monday'=>['open'=>'','close'=>'','closed'=>true],
                        'Tuesday'=>['open'=>'11:00','close'=>'19:00','closed'=>false],
                        'Wednesday'=>['open'=>'11:00','close'=>'19:00','closed'=>false],
                        'Thursday'=>['open'=>'11:00','close'=>'19:00','closed'=>false],
                        'Friday'=>['open'=>'11:00','close'=>'19:00','closed'=>false],
                        'Saturday'=>['open'=>'10:00','close'=>'16:00','closed'=>false],
                        'Sunday'=>['open'=>'11:00','close'=>'19:00','closed'=>false]
                    ],
                    'plusCode'=>'2RQP+6V Mumbai','plusCodeUrl'=>'https://maps.google.com/?q=2RQP+6V+Mumbai'
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1534639077088-d702bcf685e7?q=80&w=800&auto=format&fit=crop','alt'=>'Fabric rolls'],
                    ['src'=>'https://images.unsplash.com/photo-1632421377986-0b1f70773812?q=80&w=800&auto=format&fit=crop','alt'=>'Linen collection'],
                    ['src'=>'https://images.unsplash.com/photo-1534126511673-b6899657816a?q=80&w=800&auto=format&fit=crop','alt'=>'Tailoring'],
                    ['src'=>'https://images.unsplash.com/photo-1623605004748-3af12342204f?q=80&w=800&auto=format&fit=crop','alt'=>'Embroidery'],
                    ['src'=>'https://images.unsplash.com/photo-1506034844286-f98ed954e516?q=80&w=800&auto=format&fit=crop','alt'=>'Dyeing & Finishing']
                ],
                'social'=>[
                    'facebook'=>'https://facebook.com/lotus.textiles',
                    'instagram'=>'https://instagram.com/lotus.textiles',
                    'x'=>'https://x.com/lotus.textiles',
                    'youtube'=>'https://youtube.com/lotus.textiles',
                ],
            ]),
            'search_terms'   => 'lotus textiles fabric mumbai india showroom boutique',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $lotus, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%d','%d','%d'
        ]);
        $lotusId = $lotus['id']; // Use our custom ID

        if ($lotusId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                72.8777, 19.0760, $lotusId
            ));
        }

        // Insert third profile: Green Bites (London, GB)
        $greenId = bin2hex(random_bytes(16));
        $green = [
            'id'             => $greenId,
            'business_slug'  => 'green-bites',
            'location_slug'  => 'london',
            'title'          => 'Green Bites — London',
            'name'           => 'Green Bites',
            'city'           => 'London',
            'region'         => 'London',
            'country_code'   => 'GB',
            'postal_code'    => 'EC1A 1AA',
            'site_template'  => 'v2025',
            'palette'        => 'amber',
            'industry'       => 'restaurant',
            'default_locale' => 'en-GB',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name'=>'Green Bites','city'=>'London',
                'brand'=>[
                    'palette'=>'amber','industry'=>'restaurant',
                    'logo'=>'https://cdn-icons-png.flaticon.com/512/3170/3170733.png'
                ],
                'seo'=>[
                    'title'=>'Green Bites — Plant-forward Kitchen',
                    'description'=>'Seasonal bowls, sourdough, and specialty coffee made with British produce. Walk-ins welcome; bookings recommended for weekends.',
                    'keywords'=>'green bites, lunch bowls, sourdough, coffee, london',
                    'favicon'=>'https://cdn-icons-png.flaticon.com/512/3170/3170733.png'
                ],
                'hero'=>[
                    'heading'=>'Green Bites',
                    'subheading'=>'Your London café for seasonal bowls, sourdough bakes, and specialty coffee served warm and fast with plenty of vegetarian and vegan options at everyday prices.',
                    'badge'=>'Local Favourite',
                    'image'=>'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.6,'count'=>320],
                    'ctas'=>[ ['text'=>'View Menu','url'=>'#products'], ['text'=>'Book Table','url'=>'#contact'] ],
                ],
                'about'=>['html'=>'<p>We cook with British seasonal produce and whole grains, pairing vibrant vegetables with house ferments and dressings.</p><br/><p>Our menu rotates weekly with vegetarian and vegan options. Sourdough is baked on site each morning and our coffee is sourced from independent UK roasters.</p><br/><p>Walk-ins welcome; bookings recommended for weekends.</p>'],
                'whyUs'=>['title'=>'Why Choose Us?','html'=>'<p>We keep ingredients simple and traceable. Our kitchen minimizes waste by pickling and fermenting trimmings, and we donate surplus through local partners.</p><br/><p>Friendly service, fair pricing, and quick counter ordering get you back to your day fast.</p>','image'=>'https://images.unsplash.com/photo-1551218808-94e220e084d2?auto=format&fit=crop&w=1600'],
                'services'=>[
                    'title'=>'Menu Highlights',
                    'listing'=>[
                        ['title'=>'Lunch Bowls','image'=>'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?q=80&w=800&auto=format&fit=crop','description'=>'Seasonal veg + grains with herb dressings.','price'=>'£9.50','icon'=>'fa-bowl-food','cta'=>'Order','url'=>'#request-info'],
                        ['title'=>'Sourdough Sandwiches','image'=>'https://images.unsplash.com/photo-1555507036-ab1f4038808a?q=80&w=800&auto=format&fit=crop','description'=>'Slow-risen bread with fresh fillings.','price'=>'£7.80','icon'=>'fa-bread-slice','cta'=>'Order','url'=>'#request-info'],
                        ['title'=>'Specialty Coffee','image'=>'https://plus.unsplash.com/premium_photo-1674327105074-46dd8319164b?q=80&w=800&auto=format&fit=crop','description'=>'Single-origin roasts with alternative milks.','price'=>'£3.20','icon'=>'fa-mug-hot','cta'=>'Order','url'=>'#request-info'],
                        ['title'=>'Breakfast Plates','image'=>'https://images.unsplash.com/photo-1498654200943-1088dd4438ae?q=80&w=800&auto=format&fit=crop','description'=>'Eggs, greens, and sourdough toast.','price'=>'£8.90','icon'=>'fa-egg','cta'=>'Order','url'=>'#request-info'],
                        ['title'=>'Bakes & Pastries','image'=>'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?q=80&w=800&auto=format&fit=crop','description'=>'Morning buns, cookies, and seasonal cakes.','price'=>'£2.50+','icon'=>'fa-cookie-bite','cta'=>'Order','url'=>'#request-info']
                    ]
                ],
                'contact'=>[
                    'phone'=>['text'=>'+44 20 7946 0958','link'=>'+442079460958'],
                    'whatsapp'=>['text'=>'+44 20 7946 0958','link'=>'442079460958'],
                    'email'=>'hello@greenbites.co.uk',
                    'website'=>['text'=>'greenbites.co.uk','link'=>'https://greenbites.co.uk'],
                    'address'=>'10 Fleet St, London EC4Y 1AA',
                    'address_line1'=>'10 Fleet Street','address_line2'=>'','address_line3'=>'','address_line4'=>'',
                    'hours'=>[
                        'Monday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Tuesday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Wednesday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Thursday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Friday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Saturday'=>['open'=>'06:00','close'=>'15:00','closed'=>false],
                        'Sunday'=>['open'=>'06:00','close'=>'15:00','closed'=>false]
                    ],
                    'plusCode'=>'GV5C+3W London','plusCodeUrl'=>'https://maps.google.com/?q=GV5C+3W+London'
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=1600&q=80&auto=format&fit=crop','alt'=>'Lunch bowl'],
                    ['src'=>'https://images.unsplash.com/photo-1498654200943-1088dd4438ae?w=1600&q=80&auto=format&fit=crop','alt'=>'Healthy food'],
                    ['src'=>'https://images.unsplash.com/photo-1555507036-ab1f4038808a?q=80&w=800&auto=format&fit=crop','alt'=>'Bread'],
                    ['src'=>'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?q=80&w=800&auto=format&fit=crop','alt'=>'Bakes & Pastries'],
                    ['src'=>'https://plus.unsplash.com/premium_photo-1674327105074-46dd8319164b?w=1600&q=80&auto=format&fit=crop','alt'=>'Coffee']
                ],
                'social'=>[
                    'instagram'=>'https://instagram.com/greenbites.london',
                    'facebook'=>'https://facebook.com/greenbites.london',
                    'youtube'=>'https://youtube.com/greenbites.london',
                    'tiktok'=>'https://tiktok.com/greenbites.london',
                ],
            ]),
            'search_terms'   => 'green bites lunch bowls london cafe vegan coffee',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $green, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%d','%d','%d'
        ]);
        $greenId = $green['id']; // Use our custom ID
        if ($greenId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                -0.118092, 51.509865, $greenId
            ));
        }

        // Insert fourth profile: Swift Transit (Sydney, AU)
        $swiftId = bin2hex(random_bytes(16));
        $swift = [
            'id'             => $swiftId,
            'business_slug'  => 'swift-transit',
            'location_slug'  => 'sydney',
            'title'          => 'Swift Transit — Sydney',
            'name'           => 'Swift Transit',
            'city'           => 'Sydney',
            'region'         => 'NSW',
            'country_code'   => 'AU',
            'postal_code'    => '2000',
            'site_template'  => 'v2025',
            'palette'        => 'teal',
            'industry'       => 'transport',
            'default_locale' => 'en-AU',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name'=>'Swift Transit','city'=>'Sydney',
                'brand'=>[
                    'palette'=>'teal','industry'=>'transport',
                    'logo'=>'https://cdn-icons-png.flaticon.com/512/995/995260.png'
                ],
                'seo'=>[
                    'title'=>'Swift Transit — Courier & Logistics',
                    'description'=>'Reliable same‑day courier and scheduled logistics across NSW with real-time tracking and friendly support.',
                    'keywords'=>'swift transit, courier, logistics, sydney, same day delivery',
                    'favicon'=>'https://cdn-icons-png.flaticon.com/512/995/995260.png'
                ],
                'hero'=>[
                    'heading'=>'Swift Transit',
                    'subheading'=>'Your Sydney, NSW courier partner for same‑day and scheduled deliveries, last‑mile logistics, and careful handling with real‑time tracking, friendly support, and on‑time, transparent pricing.',
                    'badge'=>'On-Time Delivery',
                    'image'=>'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.9,'count'=>189],
                    'ctas'=>[ ['text'=>'Get Quote','url'=>'#request-info'], ['text'=>'Call','url'=>'tel:+61255501234'] ],
                ],
                'about'=>['html'=>'<p>Swift Transit provides same‑day courier and scheduled deliveries for businesses across Sydney. Our fleet includes vans, bikes, and EVs to fit every job size.</p><br/><p>We\'re trusted by retailers, clinics, and agencies to handle time-critical consignments with care and visibility.</p><br/><p>Real-time tracking and dedicated support keep your operations moving.</p>'],
                'whyUs'=>['title'=>'Why Choose Us?','html'=>'<p>On-time performance, transparent pricing, and proactive communication are at the core of our service. Our drivers are trained to handle fragile and confidential items.</p><br/><p>We integrate with your workflows and provide proof-of-delivery instantly.</p>','image'=>'https://images.unsplash.com/photo-1498084393753-b411b2d26b34?auto=format&fit=crop&w=1600'],
                'services'=>[
                    'title'=>'Services',
                    'listing'=>[
                        ['title'=>'Same‑Day Courier','image'=>'https://plus.unsplash.com/premium_photo-1757583509874-59d774474cf3?q=80&w=800&auto=format&fit=crop','description'=>'Intra-city urgent deliveries with live tracking.','price'=>'From A$29','icon'=>'fa-truck-fast','cta'=>'Get Quote','url'=>'#request-info'],
                        ['title'=>'Scheduled Routes','image'=>'https://plus.unsplash.com/premium_photo-1723651354432-7796fb4ecebc?q=80&w=800&auto=format&fit=crop','description'=>'Daily and weekly pickups tailored to your timetable.','price'=>'Custom','icon'=>'fa-route','cta'=>'Contact','url'=>'#contact'],
                        ['title'=>'Warehouse Transfer','image'=>'https://plus.unsplash.com/premium_photo-1749423089108-9ab9871fb9e2?q=80&w=800&auto=format&fit=crop','description'=>'Pallet & bulk moves with pallet-jack ready vans.','price'=>'Custom','icon'=>'fa-dolly','cta'=>'Call','url'=>'tel:+61255501234'],
                        ['title'=>'Medical Courier','image'=>'https://images.unsplash.com/photo-1659353888323-1f5c85ea3952?q=80&w=800&auto=format&fit=crop','description'=>'Specimens and documents with chain-of-custody.','price'=>'Custom','icon'=>'fa-briefcase-medical','cta'=>'Get Quote','url'=>'#request-info'],
                        ['title'=>'After-hours Support','image'=>'https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?q=80&w=800&auto=format&fit=crop','description'=>'Evening and weekend coverage on request.','price'=>'Custom','icon'=>'fa-clock','cta'=>'Contact','url'=>'#contact']
                    ]
                ],
                'contact'=>[
                    'phone'=>['text'=>'+61 2 5550 1234','link'=>'+61255501234'],
                    'whatsapp'=>['text'=>'+61 2 5550 1234','link'=>'61255501234'],
                    'email'=>'ops@swifttransit.au',
                    'website'=>['text'=>'swifttransit.au','link'=>'https://swifttransit.au'],
                    'address'=>'200 George St, Sydney NSW 2000',
                    'address_line1'=>'200 George St','address_line2'=>'Level 20','address_line3'=>'','address_line4'=>'',
                    'hours'=>[
                        'Monday'=>['open'=>'07:00','close'=>'16:00','closed'=>false],
                        'Tuesday'=>['open'=>'07:00','close'=>'16:00','closed'=>false],
                        'Wednesday'=>['open'=>'07:00','close'=>'16:00','closed'=>false],
                        'Thursday'=>['open'=>'07:00','close'=>'16:00','closed'=>false],
                        'Friday'=>['open'=>'07:00','close'=>'16:00','closed'=>false],
                        'Saturday'=>['open'=>'09:00','close'=>'14:00','closed'=>false],
                        'Sunday'=>['open'=>'','close'=>'','closed'=>true]
                    ],
                    'plusCode'=>'46R6+XM Sydney','plusCodeUrl'=>'https://maps.google.com/?q=46R6+XM+Sydney'
                ],
                'gallery'=>[
                    ['src'=>'https://plus.unsplash.com/premium_photo-1757583509874-59d774474cf3?q=80&w=800&auto=format&fit=crop','alt'=>'Same-day delivery'],
                    ['src'=>'https://plus.unsplash.com/premium_photo-1723651354432-7796fb4ecebc?q=80&w=800&auto=format&fit=crop','alt'=>'Route '],
                    ['src'=>'https://plus.unsplash.com/premium_photo-1749423089108-9ab9871fb9e2?q=80&w=800&auto=format&fit=crop','alt'=>'Warehouse pickup'],
                    ['src'=>'https://images.unsplash.com/photo-1659353888323-1f5c85ea3952?q=80&w=800&auto=format&fit=crop','alt'=>'Medical Courier'],
                    ['src'=>'https://plus.unsplash.com/premium_photo-1661963219843-f1a50a6cfcd3?q=80&w=800&auto=format&fit=crop','alt'=>'Semi-truck delivering goods'],
                    ['src'=>'https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?q=80&w=800&auto=format&fit=crop','alt'=>'24x7 Support']
                ],
                'social'=>[
                    'facebook'=>'https://facebook.com/swifttransit.au',
                    'instagram'=>'https://instagram.com/swifttransit.au',
                    'youtube'=>'https://youtube.com/swifttransit.au',
                    'x'=>'https://x.com/swifttransit.au',
                    'linkedin'=>'https://linkedin.com/swifttransit.au',
                ],
            ]),
            'search_terms'   => 'swift transit courier sydney logistics same day delivery',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $swift, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%s','%s',
            '%s','%s','%d','%d','%d'
        ]);
        $swiftId = $swift['id']; // Use our custom ID
        if ($swiftId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %s",
                151.2093, -33.8688, $swiftId
            ));
        }

        // ——— Versions for each profile (version 1 as published) ———
        $versionsT = $wpdb->prefix . 'minisite_versions';
        $nowUser = get_current_user_id() ?: null;
        foreach ([ $acmeId => 'US', $lotusId => 'IN', $greenId => 'GB', $swiftId => 'AU' ] as $pid => $cc) {
            if (!$pid) { continue; }

            // Get the profile data for the initial version
            $profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$profilesT} WHERE id = %s", $pid), ARRAY_A);
            $siteJson = $profile ? $profile['site_json'] : wp_json_encode(['note'=>'initial version','country'=>$cc]);
            
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
                'business_slug'    => $profile['business_slug'] ?? null,
                'location_slug'    => $profile['location_slug'] ?? null,
                'title'            => $profile['title'] ?? null,
                'name'             => $profile['name'] ?? null,
                'city'             => $profile['city'] ?? null,
                'region'           => $profile['region'] ?? null,
                'country_code'     => $profile['country_code'] ?? null,
                'postal_code'      => $profile['postal_code'] ?? null,
                'location_point'   => null, // Will be set separately if needed
                'site_template'    => $profile['site_template'] ?? null,
                'palette'          => $profile['palette'] ?? null,
                'industry'         => $profile['industry'] ?? null,
                'default_locale'   => $profile['default_locale'] ?? null,
                'schema_version'   => $profile['schema_version'] ?? null,
                'site_version'     => $profile['site_version'] ?? null,
                'site_json'        => $siteJson,
                'search_terms'     => $profile['search_terms'] ?? null,
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
                    "UPDATE {$versionsT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                    $lng, $lat, $versionId
                ));
            }

            // Update profile with current version ID
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET _minisite_current_version_id = %d WHERE id = %s",
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
        $profilesT  = $wpdb->prefix . 'minisites';
        $versionsT  = $wpdb->prefix . 'minisite_versions';
        $reviewsT   = $wpdb->prefix . 'minisite_reviews';

        // Check if the main profiles table exists before trying to query it
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            $wpdb->dbname,
            $profilesT
        ));

        if (!$tableExists) {
            // Tables don't exist yet, nothing to clear
            return;
        }

        // Get IDs for our seeded slugs
        $rows = $wpdb->get_results("
            SELECT id FROM {$profilesT}
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
        $wpdb->query($wpdb->prepare("DELETE FROM {$profilesT}  WHERE id IN ($in)", ...$ids));
    }
}
