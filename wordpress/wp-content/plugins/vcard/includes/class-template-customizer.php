<?php
/**
 * Template Customizer Class
 * 
 * Handles template customization interface with curated color schemes,
 * industry-specific palettes, and real-time preview functionality.
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Template_Customizer {
    
    /**
     * Industry-specific color palettes
     * @var array
     */
    private $industry_palettes = array();
    
    /**
     * Template recommendations
     * @var array
     */
    private $template_recommendations = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_industry_palettes();
        $this->init_template_recommendations();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_template_customization_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_customizer_scripts'));
        add_action('wp_ajax_vcard_preview_template', array($this, 'handle_template_preview'));
        add_action('wp_ajax_vcard_get_color_schemes', array($this, 'handle_get_color_schemes'));
        add_action('wp_ajax_vcard_get_template_recommendations', array($this, 'handle_get_template_recommendations'));
    }
    
    /**
     * Initialize industry-specific color palettes
     */
    private function init_industry_palettes() {
        $this->industry_palettes = array(
            'professional' => array(
                'name' => __('Professional', 'vcard'),
                'description' => __('Classic business colors for corporate professionals', 'vcard'),
                'schemes' => array(
                    'corporate_blue' => array(
                        'name' => __('Corporate Blue', 'vcard'),
                        'primary' => '#1e40af',
                        'secondary' => '#374151',
                        'accent' => '#f8fafc',
                        'text' => '#1e293b',
                        'text_light' => '#64748b',
                        'background' => '#ffffff',
                        'card_bg' => '#f8fafc',
                        'border' => '#e2e8f0'
                    ),
                    'executive_navy' => array(
                        'name' => __('Executive Navy', 'vcard'),
                        'primary' => '#0f172a',
                        'secondary' => '#475569',
                        'accent' => '#f1f5f9',
                        'text' => '#0f172a',
                        'text_light' => '#475569',
                        'background' => '#ffffff',
                        'card_bg' => '#f8fafc',
                        'border' => '#cbd5e1'
                    ),
                    'business_gray' => array(
                        'name' => __('Business Gray', 'vcard'),
                        'primary' => '#374151',
                        'secondary' => '#6b7280',
                        'accent' => '#f9fafb',
                        'text' => '#111827',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f3f4f6',
                        'border' => '#d1d5db'
                    )
                )
            ),
            'healthcare' => array(
                'name' => __('Healthcare', 'vcard'),
                'description' => __('Clean and trustworthy colors for medical professionals', 'vcard'),
                'schemes' => array(
                    'medical_green' => array(
                        'name' => __('Medical Green', 'vcard'),
                        'primary' => '#059669',
                        'secondary' => '#047857',
                        'accent' => '#f0fdf4',
                        'text' => '#111827',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f9fafb',
                        'border' => '#d1d5db'
                    ),
                    'healthcare_blue' => array(
                        'name' => __('Healthcare Blue', 'vcard'),
                        'primary' => '#0284c7',
                        'secondary' => '#0369a1',
                        'accent' => '#f0f9ff',
                        'text' => '#0c4a6e',
                        'text_light' => '#64748b',
                        'background' => '#ffffff',
                        'card_bg' => '#f8fafc',
                        'border' => '#e2e8f0'
                    ),
                    'wellness_teal' => array(
                        'name' => __('Wellness Teal', 'vcard'),
                        'primary' => '#0d9488',
                        'secondary' => '#0f766e',
                        'accent' => '#f0fdfa',
                        'text' => '#134e4a',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f7fffe',
                        'border' => '#ccfbf1'
                    )
                )
            ),
            'creative' => array(
                'name' => __('Creative', 'vcard'),
                'description' => __('Vibrant and artistic colors for creative professionals', 'vcard'),
                'schemes' => array(
                    'creative_purple' => array(
                        'name' => __('Creative Purple', 'vcard'),
                        'primary' => '#7c3aed',
                        'secondary' => '#a855f7',
                        'accent' => '#faf5ff',
                        'text' => '#1f2937',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f9fafb',
                        'border' => '#e5e7eb'
                    ),
                    'artistic_pink' => array(
                        'name' => __('Artistic Pink', 'vcard'),
                        'primary' => '#ec4899',
                        'secondary' => '#f472b6',
                        'accent' => '#fdf2f8',
                        'text' => '#831843',
                        'text_light' => '#9f1239',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfd',
                        'border' => '#fce7f3'
                    ),
                    'vibrant_orange' => array(
                        'name' => __('Vibrant Orange', 'vcard'),
                        'primary' => '#ea580c',
                        'secondary' => '#fb923c',
                        'accent' => '#fff7ed',
                        'text' => '#9a3412',
                        'text_light' => '#c2410c',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfb',
                        'border' => '#fed7aa'
                    )
                )
            ),
            'finance' => array(
                'name' => __('Finance', 'vcard'),
                'description' => __('Trustworthy and stable colors for financial services', 'vcard'),
                'schemes' => array(
                    'finance_navy' => array(
                        'name' => __('Finance Navy', 'vcard'),
                        'primary' => '#1e40af',
                        'secondary' => '#374151',
                        'accent' => '#f9fafb',
                        'text' => '#111827',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f3f4f6',
                        'border' => '#d1d5db'
                    ),
                    'investment_green' => array(
                        'name' => __('Investment Green', 'vcard'),
                        'primary' => '#16a34a',
                        'secondary' => '#15803d',
                        'accent' => '#f7fee7',
                        'text' => '#14532d',
                        'text_light' => '#374151',
                        'background' => '#ffffff',
                        'card_bg' => '#f9fafb',
                        'border' => '#d1d5db'
                    ),
                    'wealth_gold' => array(
                        'name' => __('Wealth Gold', 'vcard'),
                        'primary' => '#d97706',
                        'secondary' => '#92400e',
                        'accent' => '#fffbeb',
                        'text' => '#78350f',
                        'text_light' => '#a16207',
                        'background' => '#ffffff',
                        'card_bg' => '#fefdfb',
                        'border' => '#fde68a'
                    )
                )
            ),
            'hospitality' => array(
                'name' => __('Hospitality', 'vcard'),
                'description' => __('Warm and welcoming colors for restaurants and hospitality', 'vcard'),
                'schemes' => array(
                    'warm_orange' => array(
                        'name' => __('Warm Orange', 'vcard'),
                        'primary' => '#ea580c',
                        'secondary' => '#92400e',
                        'accent' => '#fff7ed',
                        'text' => '#1c1917',
                        'text_light' => '#78716c',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfb',
                        'border' => '#e7e5e4'
                    ),
                    'cozy_brown' => array(
                        'name' => __('Cozy Brown', 'vcard'),
                        'primary' => '#92400e',
                        'secondary' => '#78350f',
                        'accent' => '#fef7f0',
                        'text' => '#451a03',
                        'text_light' => '#78350f',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfb',
                        'border' => '#fed7aa'
                    ),
                    'restaurant_red' => array(
                        'name' => __('Restaurant Red', 'vcard'),
                        'primary' => '#dc2626',
                        'secondary' => '#991b1b',
                        'accent' => '#fef2f2',
                        'text' => '#7f1d1d',
                        'text_light' => '#991b1b',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfc',
                        'border' => '#fecaca'
                    )
                )
            ),
            'fitness' => array(
                'name' => __('Fitness', 'vcard'),
                'description' => __('Energetic and dynamic colors for fitness and wellness', 'vcard'),
                'schemes' => array(
                    'energetic_red' => array(
                        'name' => __('Energetic Red', 'vcard'),
                        'primary' => '#dc2626',
                        'secondary' => '#991b1b',
                        'accent' => '#fef2f2',
                        'text' => '#1f2937',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfc',
                        'border' => '#f3f4f6'
                    ),
                    'active_blue' => array(
                        'name' => __('Active Blue', 'vcard'),
                        'primary' => '#2563eb',
                        'secondary' => '#1d4ed8',
                        'accent' => '#eff6ff',
                        'text' => '#1e3a8a',
                        'text_light' => '#3730a3',
                        'background' => '#ffffff',
                        'card_bg' => '#f8fafc',
                        'border' => '#dbeafe'
                    ),
                    'power_green' => array(
                        'name' => __('Power Green', 'vcard'),
                        'primary' => '#16a34a',
                        'secondary' => '#15803d',
                        'accent' => '#f7fee7',
                        'text' => '#14532d',
                        'text_light' => '#166534',
                        'background' => '#ffffff',
                        'card_bg' => '#f9fafb',
                        'border' => '#bbf7d0'
                    )
                )
            ),
            'construction' => array(
                'name' => __('Construction', 'vcard'),
                'description' => __('Strong and reliable colors for construction and trades', 'vcard'),
                'schemes' => array(
                    'industrial_gray' => array(
                        'name' => __('Industrial Gray', 'vcard'),
                        'primary' => '#374151',
                        'secondary' => '#6b7280',
                        'accent' => '#f9fafb',
                        'text' => '#111827',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f3f4f6',
                        'border' => '#d1d5db'
                    ),
                    'construction_orange' => array(
                        'name' => __('Construction Orange', 'vcard'),
                        'primary' => '#ea580c',
                        'secondary' => '#c2410c',
                        'accent' => '#fff7ed',
                        'text' => '#9a3412',
                        'text_light' => '#c2410c',
                        'background' => '#ffffff',
                        'card_bg' => '#fefcfb',
                        'border' => '#fed7aa'
                    ),
                    'steel_blue' => array(
                        'name' => __('Steel Blue', 'vcard'),
                        'primary' => '#0f766e',
                        'secondary' => '#0d9488',
                        'accent' => '#f0fdfa',
                        'text' => '#134e4a',
                        'text_light' => '#0f766e',
                        'background' => '#ffffff',
                        'card_bg' => '#f7fffe',
                        'border' => '#99f6e4'
                    )
                )
            ),
            'luxury' => array(
                'name' => __('Luxury', 'vcard'),
                'description' => __('Premium and elegant colors for luxury services', 'vcard'),
                'schemes' => array(
                    'luxury_gold' => array(
                        'name' => __('Luxury Gold', 'vcard'),
                        'primary' => '#d97706',
                        'secondary' => '#92400e',
                        'accent' => '#fffbeb',
                        'text' => '#1c1917',
                        'text_light' => '#78716c',
                        'background' => '#ffffff',
                        'card_bg' => '#fefdfb',
                        'border' => '#f3f4f6'
                    ),
                    'premium_black' => array(
                        'name' => __('Premium Black', 'vcard'),
                        'primary' => '#111827',
                        'secondary' => '#374151',
                        'accent' => '#f9fafb',
                        'text' => '#111827',
                        'text_light' => '#6b7280',
                        'background' => '#ffffff',
                        'card_bg' => '#f3f4f6',
                        'border' => '#e5e7eb'
                    ),
                    'elegant_purple' => array(
                        'name' => __('Elegant Purple', 'vcard'),
                        'primary' => '#581c87',
                        'secondary' => '#7c3aed',
                        'accent' => '#faf5ff',
                        'text' => '#3b0764',
                        'text_light' => '#581c87',
                        'background' => '#ffffff',
                        'card_bg' => '#faf5ff',
                        'border' => '#e9d5ff'
                    )
                )
            )
        );
    }
    
    /**
     * Initialize template recommendations based on industry
     */
    private function init_template_recommendations() {
        $this->template_recommendations = array(
            'business' => array(
                'templates' => array('ceo', 'freelancer'),
                'palettes' => array('professional', 'finance')
            ),
            'healthcare' => array(
                'templates' => array('healthcare'),
                'palettes' => array('healthcare')
            ),
            'creative' => array(
                'templates' => array('freelancer'),
                'palettes' => array('creative')
            ),
            'restaurant' => array(
                'templates' => array('restaurant'),
                'palettes' => array('hospitality')
            ),
            'fitness' => array(
                'templates' => array('fitness'),
                'palettes' => array('fitness')
            ),
            'construction' => array(
                'templates' => array('construction', 'handyman'),
                'palettes' => array('construction')
            ),
            'finance' => array(
                'templates' => array('ceo'),
                'palettes' => array('finance', 'professional')
            ),
            'education' => array(
                'templates' => array('education'),
                'palettes' => array('professional', 'healthcare')
            ),
            'luxury' => array(
                'templates' => array('ceo'),
                'palettes' => array('luxury')
            )
        );
    }
    
    /**
     * Add template customization meta box
     */
    public function add_template_customization_meta_box() {
        add_meta_box(
            'vcard_template_customization',
            __('Template & Design', 'vcard'),
            array($this, 'render_template_customization_meta_box'),
            'vcard_profile',
            'normal',
            'high'
        );
    }
    
    /**
     * Render template customization meta box
     */
    public function render_template_customization_meta_box($post) {
        // Get current values
        $current_template = get_post_meta($post->ID, '_vcard_template', true) ?: 'ceo';
        $current_color_scheme = get_post_meta($post->ID, '_vcard_color_scheme', true) ?: 'corporate_blue';
        $current_industry = get_post_meta($post->ID, '_vcard_industry', true) ?: 'business';
        
        // Get template engine for available templates
        $template_engine = new VCard_Template_Engine();
        $available_templates = $template_engine->get_available_templates();
        
        wp_nonce_field('vcard_template_customization', 'vcard_template_customization_nonce');
        ?>
        
        <div id="vcard-template-customizer" class="vcard-customizer-container">
            
            <!-- Industry Selection -->
            <div class="customizer-section">
                <h4><?php _e('Business Industry', 'vcard'); ?></h4>
                <p class="description"><?php _e('Select your industry to get personalized template and color recommendations.', 'vcard'); ?></p>
                
                <select id="vcard_industry" name="vcard_industry" class="regular-text">
                    <?php foreach ($this->industry_palettes as $industry_key => $industry_data): ?>
                        <option value="<?php echo esc_attr($industry_key); ?>" <?php selected($current_industry, $industry_key); ?>>
                            <?php echo esc_html($industry_data['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Template Selection -->
            <div class="customizer-section">
                <h4><?php _e('Template Selection', 'vcard'); ?></h4>
                <p class="description"><?php _e('Choose a template that best fits your business style.', 'vcard'); ?></p>
                
                <div class="template-grid">
                    <?php foreach ($available_templates as $template_key => $template_data): ?>
                        <div class="template-option <?php echo $current_template === $template_key ? 'selected' : ''; ?>" 
                             data-template="<?php echo esc_attr($template_key); ?>">
                            <input type="radio" 
                                   id="template_<?php echo esc_attr($template_key); ?>" 
                                   name="vcard_template" 
                                   value="<?php echo esc_attr($template_key); ?>" 
                                   <?php checked($current_template, $template_key); ?>>
                            <label for="template_<?php echo esc_attr($template_key); ?>">
                                <div class="template-preview">
                                    <div class="template-thumbnail">
                                        <img src="<?php echo VCARD_ASSETS_URL; ?>images/templates/<?php echo esc_attr($template_key); ?>-thumb.svg" 
                                             alt="<?php echo esc_attr($template_data['name']); ?>" 
                                             onerror="this.src='<?php echo VCARD_ASSETS_URL; ?>images/templates/default-thumb.svg'">
                                    </div>
                                    <div class="template-info">
                                        <h5><?php echo esc_html($template_data['name']); ?></h5>
                                        <p><?php echo esc_html($template_data['description']); ?></p>
                                        <div class="template-features">
                                            <?php if (!empty($template_data['features'])): ?>
                                                <?php foreach (array_slice($template_data['features'], 0, 3) as $feature): ?>
                                                    <span class="feature-tag"><?php echo esc_html(str_replace('_', ' ', $feature)); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Color Scheme Selection -->
            <div class="customizer-section">
                <h4><?php _e('Color Scheme', 'vcard'); ?></h4>
                <p class="description"><?php _e('Select colors that match your brand and industry.', 'vcard'); ?></p>
                
                <div class="color-scheme-tabs">
                    <div class="scheme-tab-nav">
                        <?php foreach ($this->industry_palettes as $industry_key => $industry_data): ?>
                            <button type="button" 
                                    class="scheme-tab <?php echo $current_industry === $industry_key ? 'active' : ''; ?>" 
                                    data-industry="<?php echo esc_attr($industry_key); ?>">
                                <?php echo esc_html($industry_data['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="scheme-tab-content">
                        <?php foreach ($this->industry_palettes as $industry_key => $industry_data): ?>
                            <div class="scheme-panel <?php echo $current_industry === $industry_key ? 'active' : ''; ?>" 
                                 data-industry="<?php echo esc_attr($industry_key); ?>">
                                <p class="industry-description"><?php echo esc_html($industry_data['description']); ?></p>
                                
                                <div class="color-scheme-grid">
                                    <?php foreach ($industry_data['schemes'] as $scheme_key => $scheme_data): ?>
                                        <div class="color-scheme-option <?php echo $current_color_scheme === $scheme_key ? 'selected' : ''; ?>" 
                                             data-scheme="<?php echo esc_attr($scheme_key); ?>">
                                            <input type="radio" 
                                                   id="scheme_<?php echo esc_attr($scheme_key); ?>" 
                                                   name="vcard_color_scheme" 
                                                   value="<?php echo esc_attr($scheme_key); ?>" 
                                                   <?php checked($current_color_scheme, $scheme_key); ?>>
                                            <label for="scheme_<?php echo esc_attr($scheme_key); ?>">
                                                <div class="color-palette">
                                                    <div class="color-swatch primary" style="background-color: <?php echo esc_attr($scheme_data['primary']); ?>"></div>
                                                    <div class="color-swatch secondary" style="background-color: <?php echo esc_attr($scheme_data['secondary']); ?>"></div>
                                                    <div class="color-swatch accent" style="background-color: <?php echo esc_attr($scheme_data['accent']); ?>"></div>
                                                    <div class="color-swatch text" style="background-color: <?php echo esc_attr($scheme_data['text']); ?>"></div>
                                                </div>
                                                <div class="scheme-info">
                                                    <h6><?php echo esc_html($scheme_data['name']); ?></h6>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Live Preview -->
            <div class="customizer-section">
                <h4><?php _e('Live Preview', 'vcard'); ?></h4>
                <p class="description"><?php _e('See how your template and colors will look together.', 'vcard'); ?></p>
                
                <div class="preview-container">
                    <div class="preview-controls">
                        <button type="button" id="refresh-preview" class="button">
                            <?php _e('Refresh Preview', 'vcard'); ?>
                        </button>
                        <div class="preview-device-toggle">
                            <button type="button" class="device-toggle active" data-device="desktop">
                                <span class="dashicons dashicons-desktop"></span>
                            </button>
                            <button type="button" class="device-toggle" data-device="tablet">
                                <span class="dashicons dashicons-tablet"></span>
                            </button>
                            <button type="button" class="device-toggle" data-device="mobile">
                                <span class="dashicons dashicons-smartphone"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="preview-frame-container">
                        <iframe id="template-preview-frame" 
                                class="preview-frame desktop" 
                                src="about:blank" 
                                frameborder="0">
                        </iframe>
                        <div class="preview-loading">
                            <span class="spinner is-active"></span>
                            <p><?php _e('Loading preview...', 'vcard'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recommendations -->
            <div class="customizer-section recommendations-section" style="display: none;">
                <h4><?php _e('Recommendations', 'vcard'); ?></h4>
                <div id="template-recommendations"></div>
            </div>
            
        </div>
        
        <?php
    }
    
    /**
     * Enqueue customizer scripts and styles
     */
    public function enqueue_customizer_scripts($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'vcard_profile') {
            wp_enqueue_script(
                'vcard-template-customizer',
                VCARD_ASSETS_URL . 'js/template-customizer.js',
                array('jquery', 'wp-util'),
                VCARD_VERSION,
                true
            );
            
            wp_enqueue_style(
                'vcard-template-customizer',
                VCARD_ASSETS_URL . 'css/template-customizer.css',
                array(),
                VCARD_VERSION
            );
            
            // Localize script with data
            wp_localize_script('vcard-template-customizer', 'vcardCustomizer', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vcard_customizer_nonce'),
                'postId' => get_the_ID(),
                'assetsUrl' => VCARD_ASSETS_URL,
                'strings' => array(
                    'loadingPreview' => __('Loading preview...', 'vcard'),
                    'previewError' => __('Error loading preview. Please try again.', 'vcard'),
                    'recommendedForYou' => __('Recommended for you', 'vcard'),
                    'popularChoice' => __('Popular choice', 'vcard'),
                    'newTemplate' => __('New template', 'vcard')
                ),
                'industryPalettes' => $this->industry_palettes,
                'templateRecommendations' => $this->template_recommendations
            ));
        }
    }
    
    /**
     * Handle AJAX template preview request
     */
    public function handle_template_preview() {
        check_ajax_referer('vcard_customizer_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $template_key = sanitize_text_field($_POST['template']);
        $color_scheme_key = sanitize_text_field($_POST['color_scheme']);
        
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die(__('Insufficient permissions.', 'vcard'));
        }
        
        // Get post data
        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found.', 'vcard'));
        }
        
        // Create business profile instance
        $profile = new VCard_Business_Profile();
        $profile->load_from_post($post_id);
        
        // Get template engine
        $template_engine = new VCard_Template_Engine();
        
        // Generate preview HTML
        $preview_html = $template_engine->render_template($template_key, $profile->get_all_data(), $color_scheme_key);
        
        // Wrap in preview container
        $preview_html = $this->wrap_preview_html($preview_html, $template_key, $color_scheme_key);
        
        wp_send_json_success(array(
            'html' => $preview_html,
            'template' => $template_key,
            'color_scheme' => $color_scheme_key
        ));
    }
    
    /**
     * Handle AJAX get color schemes request
     */
    public function handle_get_color_schemes() {
        check_ajax_referer('vcard_customizer_nonce', 'nonce');
        
        $industry = sanitize_text_field($_POST['industry']);
        
        if (isset($this->industry_palettes[$industry])) {
            wp_send_json_success($this->industry_palettes[$industry]);
        } else {
            wp_send_json_error(__('Industry not found.', 'vcard'));
        }
    }
    
    /**
     * Handle AJAX get template recommendations request
     */
    public function handle_get_template_recommendations() {
        check_ajax_referer('vcard_customizer_nonce', 'nonce');
        
        $industry = sanitize_text_field($_POST['industry']);
        $current_template = sanitize_text_field($_POST['current_template']);
        
        $recommendations = array();
        
        if (isset($this->template_recommendations[$industry])) {
            $industry_rec = $this->template_recommendations[$industry];
            
            // Get template engine for template data
            $template_engine = new VCard_Template_Engine();
            $available_templates = $template_engine->get_available_templates();
            
            foreach ($industry_rec['templates'] as $template_key) {
                if (isset($available_templates[$template_key])) {
                    $template_data = $available_templates[$template_key];
                    $template_data['key'] = $template_key;
                    $template_data['is_current'] = ($template_key === $current_template);
                    $template_data['recommended_schemes'] = array();
                    
                    // Add recommended color schemes for this template
                    foreach ($industry_rec['palettes'] as $palette_key) {
                        if (isset($this->industry_palettes[$palette_key])) {
                            $template_data['recommended_schemes'][$palette_key] = $this->industry_palettes[$palette_key];
                        }
                    }
                    
                    $recommendations[] = $template_data;
                }
            }
        }
        
        wp_send_json_success($recommendations);
    }
    
    /**
     * Wrap preview HTML with necessary styles and scripts
     */
    private function wrap_preview_html($html, $template_key, $color_scheme_key) {
        $preview_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Preview</title>
    <style>
        body { 
            margin: 0; 
            padding: 20px; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
        }
        .preview-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .preview-notice {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            text-align: center;
        }
        .preview-content {
            padding: 0;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .preview-container { border-radius: 4px; }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-notice">
            ' . __('Live Preview', 'vcard') . ' - ' . esc_html($template_key) . ' + ' . esc_html($color_scheme_key) . '
        </div>
        <div class="preview-content">
            ' . $html . '
        </div>
    </div>
</body>
</html>';
        
        return $preview_html;
    }
    
    /**
     * Get industry palettes
     */
    public function get_industry_palettes() {
        return $this->industry_palettes;
    }
    
    /**
     * Get template recommendations
     */
    public function get_template_recommendations() {
        return $this->template_recommendations;
    }
    
    /**
     * Get color scheme by key
     */
    public function get_color_scheme($scheme_key) {
        foreach ($this->industry_palettes as $industry_data) {
            if (isset($industry_data['schemes'][$scheme_key])) {
                return $industry_data['schemes'][$scheme_key];
            }
        }
        return null;
    }
    
    /**
     * Generate CSS for color scheme
     */
    public function generate_color_scheme_css($scheme_key, $template_key = null) {
        $scheme = $this->get_color_scheme($scheme_key);
        if (!$scheme) {
            return '';
        }
        
        $selector = $template_key ? ".vcard-template.template-{$template_key}" : '.vcard-template';
        
        $css = "
        {$selector} {
            --primary-color: {$scheme['primary']};
            --secondary-color: {$scheme['secondary']};
            --accent-color: {$scheme['accent']};
            --text-color: {$scheme['text']};
            --text-light-color: {$scheme['text_light']};
            --background-color: {$scheme['background']};
            --card-bg-color: {$scheme['card_bg']};
            --border-color: {$scheme['border']};
        }
        
        {$selector} .primary-bg { background-color: var(--primary-color); }
        {$selector} .secondary-bg { background-color: var(--secondary-color); }
        {$selector} .accent-bg { background-color: var(--accent-color); }
        {$selector} .primary-text { color: var(--primary-color); }
        {$selector} .secondary-text { color: var(--secondary-color); }
        {$selector} .text-color { color: var(--text-color); }
        {$selector} .text-light { color: var(--text-light-color); }
        {$selector} .border-color { border-color: var(--border-color); }
        
        {$selector} .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        {$selector} .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
        
        {$selector} .card {
            background-color: var(--card-bg-color);
            border-color: var(--border-color);
        }
        
        {$selector} a {
            color: var(--primary-color);
        }
        
        {$selector} a:hover {
            color: var(--secondary-color);
        }
        ";
        
        return $css;
    }
}