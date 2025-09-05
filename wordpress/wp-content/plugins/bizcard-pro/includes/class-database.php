<?php
/**
 * Database management class for BizCard Pro
 *
 * @package BizCard_Pro
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * BizCard Pro Database Class
 */
class BizCard_Pro_Database {
    
    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'check_database_version'));
    }
    
    /**
     * Check if database needs updating
     */
    public function check_database_version() {
        $installed_version = get_option('bizcard_pro_db_version', '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            $this->create_tables();
            update_option('bizcard_pro_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Create all database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        // Set charset collate
        $charset_collate = $wpdb->get_charset_collate();
        
        // Array to store all table creation SQL
        $tables = array();
        
        // 1. Business Profiles Table
        $tables['profiles'] = "CREATE TABLE {$wpdb->prefix}bizcard_profiles (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            business_name varchar(255) NOT NULL,
            business_tagline varchar(500),
            owner_name varchar(255),
            business_description text,
            business_logo varchar(500),
            cover_image varchar(500),
            contact_info json,
            business_hours json,
            social_media json,
            established_year int(4),
            team_size varchar(50),
            years_experience int(3),
            service_areas json,
            business_license varchar(100),
            payment_methods json,
            address_street varchar(255),
            address_city varchar(100),
            address_state varchar(100),
            address_zip varchar(20),
            address_country varchar(100),
            latitude decimal(10, 8),
            longitude decimal(11, 8),
            meta_title varchar(255),
            meta_description text,
            keywords json,
            is_public tinyint(1) DEFAULT 1,
            allow_reviews tinyint(1) DEFAULT 1,
            show_contact_form tinyint(1) DEFAULT 1,
            profile_status enum('draft', 'published', 'suspended') DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY business_name (business_name),
            KEY profile_status (profile_status),
            KEY is_public (is_public)
        ) $charset_collate;";
        
        // 2. Services Table
        $tables['services'] = "CREATE TABLE {$wpdb->prefix}bizcard_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10, 2),
            price_type enum('fixed', 'starting_from', 'hourly', 'custom') DEFAULT 'fixed',
            category varchar(100),
            images json,
            featured_image varchar(500),
            features json,
            duration varchar(100),
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // 3. Products Table
        $tables['products'] = "CREATE TABLE {$wpdb->prefix}bizcard_products (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10, 2),
            sale_price decimal(10, 2),
            category varchar(100),
            images json,
            featured_image varchar(500),
            in_stock tinyint(1) DEFAULT 1,
            sku varchar(100),
            specifications json,
            display_order int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY category (category),
            KEY in_stock (in_stock)
        ) $charset_collate;";
        
        // 4. Gallery Table
        $tables['gallery'] = "CREATE TABLE {$wpdb->prefix}bizcard_gallery (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            image_url varchar(500) NOT NULL,
            thumbnail_url varchar(500),
            title varchar(255),
            description text,
            category enum('projects', 'team', 'office', 'events', 'products') DEFAULT 'projects',
            display_order int(11) DEFAULT 0,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY category (category)
        ) $charset_collate;";
        
        // 5. Styling Table
        $tables['styling'] = "CREATE TABLE {$wpdb->prefix}bizcard_styling (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            style_theme enum('professional', 'modern', 'elegant', 'vibrant', 'minimal', 'classic', 'bold', 'warm', 'cool', 'gradient') DEFAULT 'professional',
            primary_color varchar(7) DEFAULT '#667eea',
            secondary_color varchar(7) DEFAULT '#764ba2',
            background_style enum('solid', 'gradient', 'pattern', 'image') DEFAULT 'solid',
            background_image varchar(500),
            font_style enum('modern', 'classic', 'elegant', 'bold') DEFAULT 'modern',
            button_style enum('rounded', 'square', 'pill', 'minimal') DEFAULT 'rounded',
            card_style enum('flat', 'shadow', 'border', 'gradient') DEFAULT 'shadow',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY profile_id (profile_id)
        ) $charset_collate;";
        
        // 6. Subscriptions Table
        $tables['subscriptions'] = "CREATE TABLE {$wpdb->prefix}bizcard_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            profile_id bigint(20) NOT NULL,
            plan enum('trial', 'basic', 'professional', 'enterprise') DEFAULT 'trial',
            status enum('active', 'expired', 'suspended', 'cancelled') DEFAULT 'active',
            stripe_subscription_id varchar(255),
            amount decimal(10, 2),
            currency varchar(3) DEFAULT 'USD',
            billing_cycle enum('monthly', 'yearly') DEFAULT 'yearly',
            trial_ends_at datetime,
            current_period_start datetime,
            current_period_end datetime,
            auto_renew tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY profile_id (profile_id),
            KEY status (status),
            KEY current_period_end (current_period_end)
        ) $charset_collate;";
        
        // 7. Analytics Table
        $tables['analytics'] = "CREATE TABLE {$wpdb->prefix}bizcard_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            event_type enum('view', 'contact_click', 'phone_click', 'email_click', 'website_click', 'share', 'download') NOT NULL,
            visitor_ip varchar(45),
            user_agent text,
            referrer varchar(500),
            event_data json,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // 8. Saved Contacts Table
        $tables['saved_contacts'] = "CREATE TABLE {$wpdb->prefix}bizcard_saved_contacts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20),
            profile_id bigint(20) NOT NULL,
            local_storage_key varchar(255),
            notes text,
            tags json,
            saved_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY profile_id (profile_id),
            KEY local_storage_key (local_storage_key)
        ) $charset_collate;";
        
        // 9. Reviews Table
        $tables['reviews'] = "CREATE TABLE {$wpdb->prefix}bizcard_reviews (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            reviewer_name varchar(255) NOT NULL,
            reviewer_email varchar(255),
            rating tinyint(1) NOT NULL,
            review_text text,
            reviewer_avatar varchar(500),
            is_verified tinyint(1) DEFAULT 0,
            is_approved tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id),
            KEY rating (rating),
            KEY is_approved (is_approved)
        ) $charset_collate;";
        
        // 10. Certifications Table
        $tables['certifications'] = "CREATE TABLE {$wpdb->prefix}bizcard_certifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            profile_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            issuer varchar(255),
            date_received date,
            expiry_date date,
            certificate_image varchar(500),
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY profile_id (profile_id)
        ) $charset_collate;";
        
        // Include WordPress database upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create each table
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            
            // Log any errors
            if ($wpdb->last_error) {
                error_log("BizCard Pro: Error creating {$table_name} table: " . $wpdb->last_error);
            }
        }
        
        // Add foreign key constraints (MySQL only)
        self::add_foreign_keys();
        
        // Insert default data
        self::insert_default_data();
    }
    
    /**
     * Add foreign key constraints
     * Note: WordPress doesn't typically use foreign keys, but we'll add them for data integrity
     */
    private static function add_foreign_keys() {
        global $wpdb;
        
        // Only add foreign keys if MySQL supports them
        $engine = $wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}bizcard_profiles'");
        
        if (strtolower($engine) === 'innodb') {
            $foreign_keys = array(
                "ALTER TABLE {$wpdb->prefix}bizcard_services ADD CONSTRAINT fk_services_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_products ADD CONSTRAINT fk_products_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_gallery ADD CONSTRAINT fk_gallery_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_styling ADD CONSTRAINT fk_styling_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_subscriptions ADD CONSTRAINT fk_subscriptions_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_analytics ADD CONSTRAINT fk_analytics_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_saved_contacts ADD CONSTRAINT fk_saved_contacts_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_reviews ADD CONSTRAINT fk_reviews_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE",
                "ALTER TABLE {$wpdb->prefix}bizcard_certifications ADD CONSTRAINT fk_certifications_profile FOREIGN KEY (profile_id) REFERENCES {$wpdb->prefix}bizcard_profiles(id) ON DELETE CASCADE"
            );
            
            foreach ($foreign_keys as $fk_sql) {
                $wpdb->query($fk_sql);
                // Ignore errors for foreign keys (they might already exist)
            }
        }
    }
    
    /**
     * Insert default data
     */
    private static function insert_default_data() {
        // This will be called during activation to set up default categories, etc.
        // For now, we'll keep it empty and add data as needed
    }
    
    /**
     * Drop all plugin tables (used during uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        // Drop tables in reverse order to handle foreign key constraints
        $tables = array(
            "{$wpdb->prefix}bizcard_certifications",
            "{$wpdb->prefix}bizcard_reviews",
            "{$wpdb->prefix}bizcard_saved_contacts",
            "{$wpdb->prefix}bizcard_analytics",
            "{$wpdb->prefix}bizcard_subscriptions",
            "{$wpdb->prefix}bizcard_styling",
            "{$wpdb->prefix}bizcard_gallery",
            "{$wpdb->prefix}bizcard_products",
            "{$wpdb->prefix}bizcard_services",
            "{$wpdb->prefix}bizcard_profiles"
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Remove database version option
        delete_option('bizcard_pro_db_version');
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'bizcard_' . $table;
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $table_name = self::get_table_name('profiles');
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        return $table_exists === $table_name;
    }
}