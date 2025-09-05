-- BizCard Pro Database Schema
-- This shows the actual MySQL tables we'll create

-- 1. CORE BUSINESS PROFILES TABLE
-- This is the heart of the system - stores all business information
CREATE TABLE wp_bizcard_profiles (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,                    -- Links to WordPress user
    business_name varchar(255) NOT NULL,
    business_tagline varchar(500),
    owner_name varchar(255),
    business_description text,
    business_logo varchar(500),                     -- URL to logo image
    cover_image varchar(500),                       -- URL to cover image
    
    -- Contact Information (JSON format for flexibility)
    contact_info json,                              -- {"phone": "+1234567890", "email": "test@example.com", "website": "https://example.com"}
    business_hours json,                            -- {"monday": {"open": "09:00", "close": "17:00", "closed": false}}
    social_media json,                              -- {"facebook": "https://facebook.com/business", "instagram": "@business"}
    
    -- Business Details
    established_year int(4),
    team_size varchar(50),
    years_experience int(3),
    service_areas json,                             -- ["Downtown", "Suburbs", "Metro Area"]
    business_license varchar(100),
    payment_methods json,                           -- ["Cash", "Card", "Digital"]
    
    -- Address Information
    address_street varchar(255),
    address_city varchar(100),
    address_state varchar(100),
    address_zip varchar(20),
    address_country varchar(100),
    latitude decimal(10, 8),
    longitude decimal(11, 8),
    
    -- SEO and Marketing
    meta_title varchar(255),
    meta_description text,
    keywords json,                                  -- ["keyword1", "keyword2"]
    
    -- Profile Status and Settings
    is_public tinyint(1) DEFAULT 1,
    allow_reviews tinyint(1) DEFAULT 1,
    show_contact_form tinyint(1) DEFAULT 1,
    profile_status enum('draft', 'published', 'suspended') DEFAULT 'draft',
    
    -- Timestamps
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at datetime,
    
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY business_name (business_name),
    KEY profile_status (profile_status),
    KEY is_public (is_public)
);

-- 2. SERVICES TABLE
-- Each business can have multiple services
CREATE TABLE wp_bizcard_services (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) NOT NULL,                 -- Links to wp_bizcard_profiles
    name varchar(255) NOT NULL,
    description text,
    price decimal(10, 2),
    price_type enum('fixed', 'starting_from', 'hourly', 'custom') DEFAULT 'fixed',
    category varchar(100),
    images json,                                    -- ["image1.jpg", "image2.jpg", "image3.jpg"]
    featured_image varchar(500),                    -- Main image to display in listings
    features json,                                  -- ["Feature 1", "Feature 2"]
    duration varchar(100),                          -- "2 hours", "1 day", etc.
    display_order int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY profile_id (profile_id),
    KEY category (category),
    KEY is_active (is_active),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 3. PRODUCTS TABLE
-- Each business can sell products
CREATE TABLE wp_bizcard_products (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) NOT NULL,
    name varchar(255) NOT NULL,
    description text,
    price decimal(10, 2),
    sale_price decimal(10, 2),
    category varchar(100),
    images json,                                    -- ["image1.jpg", "image2.jpg", "image3.jpg"]
    featured_image varchar(500),                    -- Main image to display in listings
    in_stock tinyint(1) DEFAULT 1,
    sku varchar(100),
    specifications json,                            -- {"weight": "1kg", "color": "blue"}
    display_order int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY profile_id (profile_id),
    KEY category (category),
    KEY in_stock (in_stock),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 4. GALLERY TABLE
-- Images for each business profile
CREATE TABLE wp_bizcard_gallery (
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
    KEY category (category),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 5. STYLING/CUSTOMIZATION TABLE
-- How each business profile looks
CREATE TABLE wp_bizcard_styling (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) NOT NULL,
    style_theme enum('professional', 'modern', 'elegant', 'vibrant', 'minimal', 'classic', 'bold', 'warm', 'cool', 'gradient') DEFAULT 'professional',
    primary_color varchar(7) DEFAULT '#667eea',     -- Hex color code
    secondary_color varchar(7) DEFAULT '#764ba2',
    background_style enum('solid', 'gradient', 'pattern', 'image') DEFAULT 'solid',
    background_image varchar(500),
    font_style enum('modern', 'classic', 'elegant', 'bold') DEFAULT 'modern',
    button_style enum('rounded', 'square', 'pill', 'minimal') DEFAULT 'rounded',
    card_style enum('flat', 'shadow', 'border', 'gradient') DEFAULT 'shadow',
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY profile_id (profile_id),             -- One styling per profile
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 6. SUBSCRIPTIONS TABLE
-- Billing and subscription management
CREATE TABLE wp_bizcard_subscriptions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,                    -- WordPress user ID
    profile_id bigint(20) NOT NULL,
    plan enum('trial', 'basic', 'professional', 'enterprise') DEFAULT 'trial',
    status enum('active', 'expired', 'suspended', 'cancelled') DEFAULT 'active',
    stripe_subscription_id varchar(255),            -- Stripe subscription ID
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
    KEY current_period_end (current_period_end),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 7. ANALYTICS TABLE
-- Track profile views and engagement
CREATE TABLE wp_bizcard_analytics (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) NOT NULL,
    event_type enum('view', 'contact_click', 'phone_click', 'email_click', 'website_click', 'share', 'download') NOT NULL,
    visitor_ip varchar(45),                         -- IPv4 or IPv6
    user_agent text,
    referrer varchar(500),
    event_data json,                                -- Additional event-specific data
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY profile_id (profile_id),
    KEY event_type (event_type),
    KEY created_at (created_at),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 8. SAVED CONTACTS TABLE
-- End users saving business contacts
CREATE TABLE wp_bizcard_saved_contacts (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20),                             -- NULL for anonymous users (local storage)
    profile_id bigint(20) NOT NULL,
    local_storage_key varchar(255),                 -- For anonymous users
    notes text,
    tags json,                                      -- ["important", "follow-up"]
    saved_at datetime DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY profile_id (profile_id),
    KEY local_storage_key (local_storage_key),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 9. REVIEWS TABLE
-- Customer reviews for businesses
CREATE TABLE wp_bizcard_reviews (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    profile_id bigint(20) NOT NULL,
    reviewer_name varchar(255) NOT NULL,
    reviewer_email varchar(255),
    rating tinyint(1) NOT NULL,                     -- 1-5 stars
    review_text text,
    reviewer_avatar varchar(500),
    is_verified tinyint(1) DEFAULT 0,
    is_approved tinyint(1) DEFAULT 0,               -- Admin moderation
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    KEY profile_id (profile_id),
    KEY rating (rating),
    KEY is_approved (is_approved),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- 10. CERTIFICATIONS TABLE
-- Business certifications and awards
CREATE TABLE wp_bizcard_certifications (
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
    KEY profile_id (profile_id),
    FOREIGN KEY (profile_id) REFERENCES wp_bizcard_profiles(id) ON DELETE CASCADE
);

-- EXAMPLE DATA: How multiple images work for services and products

-- Example: Photography Service with multiple portfolio images
INSERT INTO wp_bizcard_services (
    profile_id, 
    name, 
    description, 
    price, 
    price_type, 
    category,
    images,
    featured_image,
    features,
    duration
) VALUES (
    1,
    'Wedding Photography',
    'Professional wedding photography with full day coverage',
    1500.00,
    'starting_from',
    'Photography',
    '["wedding1.jpg", "wedding2.jpg", "wedding3.jpg", "wedding4.jpg", "wedding5.jpg"]',
    'wedding1.jpg',
    '["Full day coverage", "200+ edited photos", "Online gallery", "Print release"]',
    '8-10 hours'
);

-- Example: Restaurant Product with multiple food images
INSERT INTO wp_bizcard_products (
    profile_id,
    name,
    description,
    price,
    category,
    images,
    featured_image,
    in_stock,
    sku
) VALUES (
    2,
    'Margherita Pizza',
    'Classic pizza with fresh mozzarella, tomato sauce, and basil',
    18.99,
    'Pizza',
    '["margherita_top.jpg", "margherita_slice.jpg", "margherita_ingredients.jpg"]',
    'margherita_top.jpg',
    1,
    'PIZZA-MARG-001'
);

-- SAMPLE QUERIES: How to work with multiple images

-- Get service with all its images
SELECT 
    name,
    featured_image,
    JSON_EXTRACT(images, '$') as all_images,
    JSON_LENGTH(images) as image_count
FROM wp_bizcard_services 
WHERE id = 1;

-- Get first 3 images from a product
SELECT 
    name,
    JSON_EXTRACT(images, '$[0]') as image1,
    JSON_EXTRACT(images, '$[1]') as image2,
    JSON_EXTRACT(images, '$[2]') as image3
FROM wp_bizcard_products 
WHERE id = 1;

-- Find services that have more than 3 images
SELECT name, JSON_LENGTH(images) as image_count
FROM wp_bizcard_services 
WHERE JSON_LENGTH(images) > 3;