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
            $reviews= $a['reviews']?? [];
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
                'reviews'  => $reviews,
                'gallery'  => $gallery,
                'social'   => $social,
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
                'whyUs' => [ 'title' => 'Why Choose Us?', 'html' => '<p>The patient coordinators at Aviva Dental Care of Cedar Park ensure that you have plenty of time for each appointment. The additional time will allow us to know you and your dental priorities better. You will also have ample time to discuss any of your questions, concerns about oral health. We seldom run behind, so you should not have to wait for us after your scheduled appointment time.</p><br/><p>Whether you need a routine exam or experiencing a dental emergency, we will thoroughly answer all your questions or concerns in a relaxed, pressure-free environment. Finally, you will never feel rushed or forced to take a treatment plan that you don\'t need. So, you won\'t have to worry about surprise costs or how to fit your dental goals into your budget.</p>' ],
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
                        ['day'=>'Mon','open'=>'08:00','close'=>'17:00'],
                        ['day'=>'Tue','open'=>'08:00','close'=>'17:00'],
                        ['day'=>'Wed','open'=>'08:00','close'=>'17:00'],
                        ['day'=>'Thu','open'=>'08:00','close'=>'17:00'],
                        ['day'=>'Fri','open'=>'08:00','close'=>'15:00'],
                        ['day'=>'Sat'],['day'=>'Sun']
                    ],
                    'plusCode'=>'77CM+4R Dallas','plusCodeUrl'=>'https://maps.google.com/?q=77CM+4R+Dallas'
                ],
                'reviews'=>[
                    [
                        'author'=>'Jane Doe','rating'=>5,'date'=>'2025-04-01',
                        'text'=>'The hygienist was incredibly gentle and explained every step before she started. The clinic is spotless and the equipment looks brand new. I left feeling well cared for and finally not dreading my next visit.'
                    ],
                    [
                        'author'=>'Mark T.','rating'=>4.5,'date'=>'2025-03-20',
                        'text'=>'Booked a last‑minute appointment for a chipped tooth and they fit me in the same day. The repair was quick and painless, and the billing was clear. Parking was easy which is a bonus in Dallas.'
                    ],
                    [
                        'author'=>'Priya S.','rating'=>4.8,'date'=>'2025-02-28',
                        'text'=>'I had whitening done here and the results were immediate. The dentist checked sensitivity throughout and gave me clear aftercare instructions. Front desk followed up the next day to see how I was doing.'
                    ],
                    [
                        'author'=>'Daniel K.','rating'=>4.9,'date'=>'2025-01-15',
                        'text'=>'Super organized practice with on‑time appointments. They walked me through options for a crown and never pushed extras. Waiting area is calm and the coffee machine is a nice touch.'
                    ],
                    [
                        'author'=>'Alicia M.','rating'=>5,'date'=>'2024-12-05',
                        'text'=>'Brought my teen for Invisalign and the consultation was thorough without being overwhelming. Clear timeline, fair pricing, and they answered all our questions. We feel confident continuing care here.'
                    ],
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1670250492416-570b5b7343b1?q=80&w=1600&auto=format&fit=crop','alt'=>'Reception'],
                    ['src'=>'https://images.unsplash.com/photo-1670250492416-570b5b7343b1?q=80&w=1600&auto=format&fit=crop','alt'=>'Reception'],
                    ['src'=>'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?q=80&w=800&auto=format&fit=crop','alt'=>'Reception'],
                    ['src'=>'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?q=80&w=800&auto=format&fit=crop','alt'=>'Reception'],
                    ['src'=>'https://images.unsplash.com/photo-1489278353717-f64c6ee8a4d2?q=80&w=800&auto=format&fit=crop','alt'=>'Reception'],
                    ['src'=>'https://images.unsplash.com/photo-1564420228450-d9a5bc8d6565?q=80&w=800&auto=format&fit=crop','alt'=>'Chair'],['src'=>'https://images.unsplash.com/photo-1670250492416-570b5b7343b1?q=80&w=1600&auto=format&fit=crop','alt'=>'Reception'],
                    
                ],
                'social'=>[
                    ['network'=>'facebook','url'=>'https://facebook.com/acmedental.dallas'],
                    ['network'=>'instagram','url'=>'https://instagram.com/acmedental.dallas'],
                    ['network'=>'x','url'=>'https://x.com/acmedental.dallas'],
                    ['network'=>'linkedin','url'=>'https://linkedin.com/acmedental.dallas'],
                    ['network'=>'youtube','url'=>'https://youtube.com/acmedental.dallas'],
                    ['network'=>'tiktok','url'=>'https://tiktok.com/acmedental.dallas'],
                ],
            ]),
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
            'site_json'      => $buildJson([
                'name'=>'Lotus Textiles','city'=>'Mumbai',
                'brand'=>['palette'=>'rose','industry'=>'textile'],
                'hero'=>[
                    'heading'=>'Lotus Textiles — Mumbai','subheading'=>'Premium fabrics and bespoke tailoring since 1985.',
                    'badge'=>'Trusted Supplier','image'=>'https://images.unsplash.com/photo-1504089879190-820eb03ca34e?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.7,'count'=>412],
                    'ctas'=>[ ['text'=>'Browse Collection','url'=>'#products'], ['text'=>'Contact','url'=>'#contact'] ],
                ],
                'about'=>['html'=>'<p>At Lotus Textiles, we curate fine silks, linens, and cottons from across India.</p><p>Our in-house designers craft contemporary styles while preserving handloom traditions.</p><p>We supply boutiques and provide made-to-measure services.</p>'],
                'services'=>[
                    ['title'=>'Handloom Silks','description'=>'Banarasi, Kanchipuram','price'=>'From ₹3,999','cta'=>'Enquire','url'=>'#request-info'],
                    ['title'=>'Linen Collections','description'=>'Breathable summer linens','price'=>'From ₹1,999','cta'=>'Enquire','url'=>'#request-info'],
                    ['title'=>'Tailoring','description'=>'Custom stitching & fittings','price'=>'Quoted','cta'=>'Book','url'=>'tel:+912266601234'],
                ],
                'contact'=>[
                    'phone'=>'+91 22 6660 1234','email'=>'hello@lotustextiles.in','website'=>'https://lotustextiles.in',
                    'address'=>'12 Colaba Causeway, Mumbai 400001','address_line1'=>'12 Colaba Causeway','address_line2'=>'2nd Floor',
                    'hours'=>[
                        ['day'=>'Mon–Sat','open'=>'10:00','close'=>'19:00'],['day'=>'Sun']
                    ],
                    'plusCode'=>'2RQP+6V Mumbai','plusCodeUrl'=>'https://maps.google.com/?q=2RQP+6V+Mumbai'
                ],
                'reviews'=>[
                    ['author'=>'Asha P.','rating'=>5,'date'=>'2025-02-10','text'=>'Beautiful fabrics and helpful staff.'],
                    ['author'=>'Rohit K.','rating'=>4.6,'date'=>'2025-03-01','text'=>'Great pricing and quality.'],
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1520975916090-3105956dac38?w=1600&q=80&auto=format&fit=crop','alt'=>'Fabric rolls'],
                ],
                'social'=>[
                    ['network'=>'instagram','url'=>'https://instagram.com/lotus.textiles'],
                ],
            ]),
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

        // Insert third profile: Green Bites (London, GB)
        $green = [
            'business_slug'  => 'green-bites',
            'location_slug'  => 'london',
            'title'          => 'Green Bites — London',
            'name'           => 'Green Bites',
            'city'           => 'London',
            'region'         => 'London',
            'country_code'   => 'GB',
            'postal_code'    => 'EC1A 1AA',
            'lat'            => 51.509865,
            'lng'            => -0.118092,
            'site_template'  => 'v2025',
            'palette'        => 'amber',
            'industry'       => 'restaurant',
            'default_locale' => 'en-GB',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name'=>'Green Bites','city'=>'London',
                'brand'=>['palette'=>'amber','industry'=>'restaurant'],
                'hero'=>[
                    'heading'=>'Green Bites — Plant-forward Kitchen','subheading'=>'Seasonal bowls, sourdough, and specialty coffee.',
                    'badge'=>'Local Favourite','image'=>'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.6,'count'=>320],
                    'ctas'=>[ ['text'=>'View Menu','url'=>'#products'], ['text'=>'Book Table','url'=>'#contact'] ],
                ],
                'about'=>['html'=>'<p>We cook with British seasonal produce and whole grains.</p><p>Our menu rotates weekly with vegetarian and vegan options.</p><p>Walk-ins welcome; bookings recommended for weekends.</p>'],
                'services'=>[
                    ['title'=>'Lunch Bowls','description'=>'Seasonal veg + grains','price'=>'£9.50','cta'=>'Order','url'=>'#request-info'],
                    ['title'=>'Sourdough Sandwiches','description'=>'House-baked bread','price'=>'£7.80','cta'=>'Order','url'=>'#request-info'],
                    ['title'=>'Specialty Coffee','description'=>'Single-origin roasts','price'=>'£3.20','cta'=>'Order','url'=>'#request-info'],
                ],
                'contact'=>[
                    'phone'=>'+44 20 7946 0958','email'=>'hello@greenbites.co.uk','website'=>'https://greenbites.co.uk',
                    'address'=>'10 Fleet St, London EC4Y 1AA','address_line1'=>'10 Fleet Street','address_line2'=>'',
                    'hours'=>[
                        ['day'=>'Mon–Fri','open'=>'08:00','close'=>'18:00'],['day'=>'Sat','open'=>'09:00','close'=>'16:00'],['day'=>'Sun']
                    ],
                    'plusCode'=>'GV5C+3W London','plusCodeUrl'=>'https://maps.google.com/?q=GV5C+3W+London'
                ],
                'reviews'=>[
                    ['author'=>'Alex P.','rating'=>5,'date'=>'2025-02-12','text'=>'Best sourdough in the City!'],
                    ['author'=>'Maria G.','rating'=>4.7,'date'=>'2025-03-18','text'=>'Delicious bowls and friendly staff.'],
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=1600&q=80&auto=format&fit=crop','alt'=>'Bowl'],
                ],
                'social'=>[
                    ['network'=>'instagram','url'=>'https://instagram.com/greenbites.london'],
                ],
            ]),
            'search_terms'   => 'green bites lunch bowls london cafe vegan coffee',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $green, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%f','%f','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%d','%d'
        ]);
        $greenId = (int) $wpdb->insert_id;
        if ($greenId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                $green['lng'], $green['lat'], $greenId
            ));
        }

        // Insert fourth profile: Swift Transit (Sydney, AU)
        $swift = [
            'business_slug'  => 'swift-transit',
            'location_slug'  => 'sydney',
            'title'          => 'Swift Transit — Sydney',
            'name'           => 'Swift Transit',
            'city'           => 'Sydney',
            'region'         => 'NSW',
            'country_code'   => 'AU',
            'postal_code'    => '2000',
            'lat'            => -33.8688,
            'lng'            => 151.2093,
            'site_template'  => 'v2025',
            'palette'        => 'teal',
            'industry'       => 'transport',
            'default_locale' => 'en-AU',
            'schema_version' => 1,
            'site_version'   => 1,
            'site_json'      => $buildJson([
                'name'=>'Swift Transit','city'=>'Sydney',
                'brand'=>['palette'=>'teal','industry'=>'transport'],
                'hero'=>[
                    'heading'=>'Swift Transit — Australia','subheading'=>'Reliable courier and last‑mile logistics across NSW.',
                    'badge'=>'On-Time Delivery','image'=>'https://images.unsplash.com/photo-1498084393753-b411b2d26b34?w=1600&q=80&auto=format&fit=crop',
                    'rating'=>['value'=>4.9,'count'=>189],
                    'ctas'=>[ ['text'=>'Get Quote','url'=>'#request-info'], ['text'=>'Call','url'=>'tel:+61255501234'] ],
                ],
                'about'=>['html'=>'<p>Swift Transit provides same‑day courier and scheduled deliveries for businesses across Sydney.</p><p>Our fleet includes vans, bikes, and EVs to fit every job size.</p><p>Real-time tracking and friendly support keep your operations moving.</p>'],
                'services'=>[
                    ['title'=>'Same‑Day Courier','description'=>'Intra-city urgent deliveries','price'=>'From A$29','cta'=>'Get Quote','url'=>'#request-info'],
                    ['title'=>'Scheduled Routes','description'=>'Daily/weekly pickups','price'=>'Custom','cta'=>'Contact','url'=>'#contact'],
                    ['title'=>'Warehouse Transfer','description'=>'Pallet & bulk moves','price'=>'Custom','cta'=>'Call','url'=>'tel:+61255501234'],
                ],
                'contact'=>[
                    'phone'=>'+61 2 5550 1234','email'=>'ops@swifttransit.au','website'=>'https://swifttransit.au',
                    'address'=>'200 George St, Sydney NSW 2000','address_line1'=>'200 George St','address_line2'=>'Level 20',
                    'hours'=>[
                        ['day'=>'Mon–Fri','open'=>'07:00','close'=>'19:00'],['day'=>'Sat','open'=>'08:00','close'=>'14:00'],['day'=>'Sun']
                    ],
                    'plusCode'=>'46R6+XM Sydney','plusCodeUrl'=>'https://maps.google.com/?q=46R6+XM+Sydney'
                ],
                'reviews'=>[
                    ['author'=>'Zoe L.','rating'=>5,'date'=>'2025-01-22','text'=>'Super fast and careful with fragile items.'],
                    ['author'=>'Nick R.','rating'=>4.8,'date'=>'2025-03-02','text'=>'Great communication and tracking.'],
                ],
                'gallery'=>[
                    ['src'=>'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?w=1600&q=80&auto=format&fit=crop','alt'=>'Van'],
                ],
                'social'=>[
                    ['network'=>'facebook','url'=>'https://facebook.com/swifttransit.au'],
                ],
            ]),
            'search_terms'   => 'swift transit courier sydney logistics same day delivery',
            'status'         => 'published',
            'created_by'     => get_current_user_id() ?: null,
            'updated_by'     => get_current_user_id() ?: null,
        ];
        $wpdb->insert($profilesT, $swift, [
            '%s','%s','%s','%s','%s','%s','%s','%s',
            '%f','%f','%s','%s','%s','%s',
            '%d','%d','%s','%s','%s','%s','%d','%d'
        ]);
        $swiftId = (int) $wpdb->insert_id;
        if ($swiftId) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$profilesT} SET location_point = ST_SRID(POINT(%f, %f), 4326) WHERE id = %d",
                $swift['lng'], $swift['lat'], $swiftId
            ));
        }

        // ——— Revisions for each profile (rev 1 draft, rev 2 published snapshot) ———
        $nowUser = get_current_user_id() ?: null;
        foreach ([ $acmeId => 'US', $lotusId => 'IN', $greenId => 'GB', $swiftId => 'AU' ] as $pid => $cc) {
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
        if ($greenId) {
            $insertReview($greenId, 'Clara W.', 4.9, 'Delicious bowls, lovely staff.', 'en-GB');
            $insertReview($greenId, 'Owen T.', 4.5, 'Great coffee and sourdough.', 'en-GB');
        }
        if ($swiftId) {
            $insertReview($swiftId, 'Zoe L.', 5.0, 'Super fast and careful with fragile items.', 'en-AU');
            $insertReview($swiftId, 'Nick R.', 4.8, 'Great communication and tracking.', 'en-AU');
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
