<?php
/**
 * Profile Management Interface Class
 * 
 * Handles comprehensive profile editing forms, services/products management,
 * and gallery management with drag-and-drop functionality.
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Profile_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Enhanced meta box hooks
        add_action('add_meta_boxes', array($this, 'add_enhanced_meta_boxes'), 20);
        add_action('save_post', array($this, 'save_enhanced_meta_fields'), 20);
        
        // AJAX hooks for dynamic management
        add_action('wp_ajax_vcard_add_service', array($this, 'ajax_add_service'));
        add_action('wp_ajax_vcard_remove_service', array($this, 'ajax_remove_service'));
        add_action('wp_ajax_vcard_add_product', array($this, 'ajax_add_product'));
        add_action('wp_ajax_vcard_remove_product', array($this, 'ajax_remove_product'));
        add_action('wp_ajax_vcard_update_gallery', array($this, 'ajax_update_gallery'));
        add_action('wp_ajax_vcard_get_profile_stats', array($this, 'ajax_get_profile_stats'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Custom columns for profile list
        add_filter('manage_vcard_profile_posts_columns', array($this, 'add_profile_columns'));
        add_action('manage_vcard_profile_posts_custom_column', array($this, 'populate_profile_columns'), 10, 2);
    }
    
    /**
     * Add enhanced meta boxes
     */
    public function add_enhanced_meta_boxes() {
        // Profile Overview meta box
        add_meta_box(
            'vcard_profile_overview',
            __('Profile Overview & Statistics', 'vcard'),
            array($this, 'render_profile_overview_meta_box'),
            'vcard_profile',
            'side',
            'high'
        );
        
        // Services Management meta box
        add_meta_box(
            'vcard_services_manager',
            __('Services Management', 'vcard'),
            array($this, 'render_services_manager_meta_box'),
            'vcard_profile',
            'normal',
            'default'
        );
        
        // Products Management meta box
        add_meta_box(
            'vcard_products_manager',
            __('Products Management', 'vcard'),
            array($this, 'render_products_manager_meta_box'),
            'vcard_profile',
            'normal',
            'default'
        );
        
        // Gallery Management meta box
        add_meta_box(
            'vcard_gallery_manager',
            __('Gallery Management', 'vcard'),
            array($this, 'render_gallery_manager_meta_box'),
            'vcard_profile',
            'normal',
            'default'
        );
        
        // Quick Actions meta box
        add_meta_box(
            'vcard_quick_actions',
            __('Quick Actions', 'vcard'),
            array($this, 'render_quick_actions_meta_box'),
            'vcard_profile',
            'side',
            'default'
        );
    }
    
    /**
     * Render profile overview meta box
     */
    public function render_profile_overview_meta_box($post) {
        $profile_views = intval(get_post_meta($post->ID, '_vcard_profile_views', true));
        $vcard_downloads = intval(get_post_meta($post->ID, '_vcard_vcard_downloads', true));
        $qr_scans = intval(get_post_meta($post->ID, '_vcard_qr_scans', true));
        $shares = intval(get_post_meta($post->ID, '_vcard_shares', true));
        
        $template_name = get_post_meta($post->ID, '_vcard_template_name', true) ?: 'ceo';
        $subscription_plan = get_user_meta($post->post_author, '_vcard_subscription_plan', true) ?: 'free';
        
        $last_modified = get_post_modified_time('F j, Y g:i a', false, $post);
        $created_date = get_post_time('F j, Y g:i a', false, $post);
        ?>
        
        <div class="vcard-profile-overview">
            <div class="overview-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($profile_views); ?></span>
                    <span class="stat-label"><?php _e('Views', 'vcard'); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($vcard_downloads); ?></span>
                    <span class="stat-label"><?php _e('Downloads', 'vcard'); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($qr_scans); ?></span>
                    <span class="stat-label"><?php _e('QR Scans', 'vcard'); ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($shares); ?></span>
                    <span class="stat-label"><?php _e('Shares', 'vcard'); ?></span>
                </div>
            </div>
            
            <div class="overview-info">
                <div class="info-item">
                    <strong><?php _e('Template:', 'vcard'); ?></strong>
                    <span class="template-badge"><?php echo ucfirst(str_replace('-', ' ', $template_name)); ?></span>
                </div>
                
                <div class="info-item">
                    <strong><?php _e('Plan:', 'vcard'); ?></strong>
                    <span class="plan-badge plan-<?php echo $subscription_plan; ?>"><?php echo ucfirst($subscription_plan); ?></span>
                </div>
                
                <div class="info-item">
                    <strong><?php _e('Created:', 'vcard'); ?></strong>
                    <span><?php echo $created_date; ?></span>
                </div>
                
                <div class="info-item">
                    <strong><?php _e('Last Modified:', 'vcard'); ?></strong>
                    <span><?php echo $last_modified; ?></span>
                </div>
            </div>
            
            <?php if ($post->post_status === 'publish'): ?>
            <div class="profile-url">
                <strong><?php _e('Profile URL:', 'vcard'); ?></strong>
                <div class="url-container">
                    <input type="text" value="<?php echo get_permalink($post->ID); ?>" readonly class="profile-url-input">
                    <button type="button" class="button copy-url-btn" data-url="<?php echo esc_attr(get_permalink($post->ID)); ?>">
                        <?php _e('Copy', 'vcard'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .vcard-profile-overview {
            font-size: 13px;
        }
        
        .overview-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .stat-item {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .stat-number {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #0073aa;
        }
        
        .stat-label {
            font-size: 11px;
            color: #646970;
        }
        
        .overview-info {
            margin-bottom: 15px;
        }
        
        .info-item {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .template-badge, .plan-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .template-badge {
            background: #e7f3ff;
            color: #0073aa;
        }
        
        .plan-badge {
            background: #f0f0f0;
            color: #646970;
        }
        
        .plan-free {
            background: #fff3cd;
            color: #856404;
        }
        
        .plan-basic {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .plan-professional {
            background: #d4edda;
            color: #155724;
        }
        
        .profile-url {
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .url-container {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .profile-url-input {
            flex: 1;
            font-size: 11px;
            padding: 4px 6px;
        }
        
        .copy-url-btn {
            font-size: 11px;
            padding: 4px 8px;
            height: auto;
        }
        </style>
        <?php
    }
    
    /**
     * Render services manager meta box
     */
    public function render_services_manager_meta_box($post) {
        wp_nonce_field('vcard_services_manager', 'vcard_services_manager_nonce');
        
        $services = get_post_meta($post->ID, '_vcard_services', true);
        $services_data = !empty($services) ? json_decode($services, true) : array();
        
        // Check subscription limits
        $user_roles = new VCard_User_Roles();
        $can_add_service = $user_roles->check_subscription_limit($post->post_author, 'add_service', $post->ID);
        $remaining_limits = $user_roles->get_user_remaining_limits($post->post_author);
        ?>
        
        <div class="vcard-services-manager">
            <div class="services-header">
                <div class="services-info">
                    <p><?php _e('Manage your business services. Add detailed descriptions, pricing, and categorize your offerings.', 'vcard'); ?></p>
                    
                    <?php if ($remaining_limits['services'] !== -1): ?>
                    <div class="limit-info">
                        <span class="limit-text">
                            <?php printf(__('Services: %d/%d used', 'vcard'), 
                                count($services_data), 
                                $remaining_limits['services'] + count($services_data)
                            ); ?>
                        </span>
                        <?php if (!$can_add_service): ?>
                            <span class="limit-reached"><?php _e('Limit reached', 'vcard'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($can_add_service): ?>
                <button type="button" class="button button-primary add-service-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Service', 'vcard'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="services-list" id="services-list">
                <?php if (!empty($services_data) && is_array($services_data)): ?>
                    <?php foreach ($services_data as $index => $service): ?>
                        <?php $this->render_service_item($index, $service); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-services-message">
                        <p><?php _e('No services added yet. Click "Add Service" to get started.', 'vcard'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Service Template -->
        <script type="text/template" id="service-template">
            <?php $this->render_service_item('{{INDEX}}', array()); ?>
        </script>
        
        <style>
        .vcard-services-manager {
            margin: -6px -12px -12px -12px;
            padding: 15px;
        }
        
        .services-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .services-info p {
            margin: 0 0 10px 0;
            color: #646970;
        }
        
        .limit-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .limit-text {
            font-size: 12px;
            color: #646970;
        }
        
        .limit-reached {
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .services-list {
            min-height: 100px;
        }
        
        .no-services-message {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
        }
        
        .no-services-message p {
            margin: 0;
            color: #646970;
            font-style: italic;
        }
        
        .service-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .service-item:last-child {
            margin-bottom: 0;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .service-title {
            font-weight: 600;
            color: #1d2327;
        }
        
        .service-actions {
            display: flex;
            gap: 5px;
        }
        
        .remove-service-btn {
            color: #d63638;
            text-decoration: none;
            font-size: 12px;
            padding: 4px 8px;
            border: 1px solid #d63638;
            border-radius: 3px;
            background: transparent;
        }
        
        .remove-service-btn:hover {
            background: #d63638;
            color: #fff;
        }
        
        .service-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .service-field {
            display: flex;
            flex-direction: column;
        }
        
        .service-field.full-width {
            grid-column: 1 / -1;
        }
        
        .service-field label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #1d2327;
        }
        
        .service-field input,
        .service-field textarea,
        .service-field select {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .service-field textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        @media (max-width: 768px) {
            .services-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .service-fields {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render products manager meta box
     */
    public function render_products_manager_meta_box($post) {
        wp_nonce_field('vcard_products_manager', 'vcard_products_manager_nonce');
        
        $products = get_post_meta($post->ID, '_vcard_products', true);
        $products_data = !empty($products) ? json_decode($products, true) : array();
        
        // Check subscription limits
        $user_roles = new VCard_User_Roles();
        $can_add_product = $user_roles->check_subscription_limit($post->post_author, 'add_product', $post->ID);
        $remaining_limits = $user_roles->get_user_remaining_limits($post->post_author);
        ?>
        
        <div class="vcard-products-manager">
            <div class="products-header">
                <div class="products-info">
                    <p><?php _e('Showcase your products with images, descriptions, and pricing information.', 'vcard'); ?></p>
                    
                    <?php if ($remaining_limits['products'] !== -1): ?>
                    <div class="limit-info">
                        <span class="limit-text">
                            <?php printf(__('Products: %d/%d used', 'vcard'), 
                                count($products_data), 
                                $remaining_limits['products'] + count($products_data)
                            ); ?>
                        </span>
                        <?php if (!$can_add_product): ?>
                            <span class="limit-reached"><?php _e('Limit reached', 'vcard'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($can_add_product): ?>
                <button type="button" class="button button-primary add-product-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Product', 'vcard'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="products-list" id="products-list">
                <?php if (!empty($products_data) && is_array($products_data)): ?>
                    <?php foreach ($products_data as $index => $product): ?>
                        <?php $this->render_product_item($index, $product); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products-message">
                        <p><?php _e('No products added yet. Click "Add Product" to showcase your offerings.', 'vcard'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Template -->
        <script type="text/template" id="product-template">
            <?php $this->render_product_item('{{INDEX}}', array()); ?>
        </script>
        <?php
    }
    
    /**
     * Render gallery manager meta box
     */
    public function render_gallery_manager_meta_box($post) {
        wp_nonce_field('vcard_gallery_manager', 'vcard_gallery_manager_nonce');
        
        $gallery = get_post_meta($post->ID, '_vcard_gallery', true);
        $gallery_ids = !empty($gallery) ? explode(',', $gallery) : array();
        
        // Check subscription limits
        $user_roles = new VCard_User_Roles();
        $can_add_image = $user_roles->check_subscription_limit($post->post_author, 'add_gallery_image', $post->ID);
        $remaining_limits = $user_roles->get_user_remaining_limits($post->post_author);
        ?>
        
        <div class="vcard-gallery-manager">
            <div class="gallery-header">
                <div class="gallery-info">
                    <p><?php _e('Upload and organize images to showcase your business. Drag and drop to reorder images.', 'vcard'); ?></p>
                    
                    <?php if ($remaining_limits['gallery_images'] !== -1): ?>
                    <div class="limit-info">
                        <span class="limit-text">
                            <?php printf(__('Images: %d/%d used', 'vcard'), 
                                count($gallery_ids), 
                                $remaining_limits['gallery_images'] + count($gallery_ids)
                            ); ?>
                        </span>
                        <?php if (!$can_add_image): ?>
                            <span class="limit-reached"><?php _e('Limit reached', 'vcard'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($can_add_image): ?>
                <button type="button" class="button button-primary add-gallery-images-btn">
                    <span class="dashicons dashicons-camera"></span>
                    <?php _e('Add Images', 'vcard'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="gallery-container">
                <input type="hidden" name="vcard_gallery" id="vcard-gallery-input" value="<?php echo esc_attr($gallery); ?>">
                
                <div class="gallery-grid" id="gallery-grid">
                    <?php if (!empty($gallery_ids)): ?>
                        <?php foreach ($gallery_ids as $image_id): ?>
                            <?php if (!empty($image_id)): ?>
                                <?php $this->render_gallery_item($image_id); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($gallery_ids)): ?>
                <div class="no-images-message" id="no-images-message">
                    <div class="upload-placeholder">
                        <span class="dashicons dashicons-camera"></span>
                        <p><?php _e('No images in gallery yet.', 'vcard'); ?></p>
                        <?php if ($can_add_image): ?>
                            <button type="button" class="button add-first-image-btn">
                                <?php _e('Add Your First Image', 'vcard'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .vcard-gallery-manager {
            margin: -6px -12px -12px -12px;
            padding: 15px;
        }
        
        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .gallery-info p {
            margin: 0 0 10px 0;
            color: #646970;
        }
        
        .gallery-container {
            min-height: 200px;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .gallery-item {
            position: relative;
            aspect-ratio: 1;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            cursor: move;
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-item-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: flex;
            gap: 3px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .gallery-item:hover .gallery-item-actions {
            opacity: 1;
        }
        
        .gallery-action-btn {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .gallery-action-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        .remove-image-btn {
            background: rgba(214, 54, 56, 0.9);
        }
        
        .remove-image-btn:hover {
            background: rgba(214, 54, 56, 1);
        }
        
        .no-images-message {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }
        
        .upload-placeholder {
            text-align: center;
            padding: 40px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .upload-placeholder .dashicons {
            font-size: 48px;
            color: #c3c4c7;
            margin-bottom: 15px;
        }
        
        .upload-placeholder p {
            margin: 0 0 15px 0;
            color: #646970;
            font-style: italic;
        }
        
        .gallery-grid.sortable .gallery-item {
            cursor: grab;
        }
        
        .gallery-grid.sortable .gallery-item:active {
            cursor: grabbing;
        }
        
        .gallery-item.ui-sortable-helper {
            transform: rotate(5deg);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .gallery-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render quick actions meta box
     */
    public function render_quick_actions_meta_box($post) {
        ?>
        <div class="vcard-quick-actions">
            <?php if ($post->post_status === 'publish'): ?>
                <div class="action-group">
                    <h4><?php _e('View & Share', 'vcard'); ?></h4>
                    
                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" class="button button-secondary action-btn">
                        <span class="dashicons dashicons-external"></span>
                        <?php _e('View Profile', 'vcard'); ?>
                    </a>
                    
                    <button type="button" class="button action-btn" onclick="vCardManager.generateQR(<?php echo $post->ID; ?>)">
                        <span class="dashicons dashicons-smartphone"></span>
                        <?php _e('Generate QR Code', 'vcard'); ?>
                    </button>
                    
                    <button type="button" class="button action-btn" onclick="vCardManager.exportVCard(<?php echo $post->ID; ?>)">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export vCard', 'vcard'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="action-group">
                <h4><?php _e('Profile Management', 'vcard'); ?></h4>
                
                <button type="button" class="button action-btn" onclick="vCardManager.duplicateProfile(<?php echo $post->ID; ?>)">
                    <span class="dashicons dashicons-admin-page"></span>
                    <?php _e('Duplicate Profile', 'vcard'); ?>
                </button>
                
                <button type="button" class="button action-btn" onclick="vCardManager.resetAnalytics(<?php echo $post->ID; ?>)">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php _e('Reset Analytics', 'vcard'); ?>
                </button>
            </div>
            
            <div class="action-group">
                <h4><?php _e('Template & Design', 'vcard'); ?></h4>
                
                <button type="button" class="button action-btn" onclick="vCardManager.previewTemplate(<?php echo $post->ID; ?>)">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Preview Template', 'vcard'); ?>
                </button>
                
                <button type="button" class="button action-btn" onclick="vCardManager.changeTemplate(<?php echo $post->ID; ?>)">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    <?php _e('Change Template', 'vcard'); ?>
                </button>
            </div>
        </div>
        
        <style>
        .vcard-quick-actions {
            font-size: 13px;
        }
        
        .action-group {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .action-group:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .action-group h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            margin-bottom: 8px;
            padding: 8px 12px;
            text-align: left;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .action-btn:last-child {
            margin-bottom: 0;
        }
        
        .action-btn .dashicons {
            font-size: 16px;
        }
        
        .action-btn:hover {
            background: #f0f6fc;
            border-color: #0073aa;
        }
        </style>
        <?php
    }
    
    /**
     * Render individual service item
     */
    private function render_service_item($index, $service) {
        $name = isset($service['name']) ? $service['name'] : '';
        $description = isset($service['description']) ? $service['description'] : '';
        $price = isset($service['price']) ? $service['price'] : '';
        $category = isset($service['category']) ? $service['category'] : '';
        $image = isset($service['image']) ? $service['image'] : '';
        ?>
        
        <div class="service-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="service-header">
                <span class="service-title">
                    <?php echo !empty($name) ? esc_html($name) : __('New Service', 'vcard'); ?>
                </span>
                <div class="service-actions">
                    <button type="button" class="remove-service-btn" onclick="vCardManager.removeService(this)">
                        <?php _e('Remove', 'vcard'); ?>
                    </button>
                </div>
            </div>
            
            <div class="service-fields">
                <div class="service-field">
                    <label><?php _e('Service Name', 'vcard'); ?> *</label>
                    <input type="text" name="vcard_services[<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($name); ?>" 
                           placeholder="<?php _e('Enter service name', 'vcard'); ?>"
                           onchange="vCardManager.updateServiceTitle(this)">
                </div>
                
                <div class="service-field">
                    <label><?php _e('Price', 'vcard'); ?></label>
                    <input type="text" name="vcard_services[<?php echo $index; ?>][price]" 
                           value="<?php echo esc_attr($price); ?>" 
                           placeholder="<?php _e('e.g., $99 or Contact for quote', 'vcard'); ?>">
                </div>
                
                <div class="service-field">
                    <label><?php _e('Category', 'vcard'); ?></label>
                    <input type="text" name="vcard_services[<?php echo $index; ?>][category]" 
                           value="<?php echo esc_attr($category); ?>" 
                           placeholder="<?php _e('e.g., Consulting, Design', 'vcard'); ?>">
                </div>
                
                <div class="service-field">
                    <label><?php _e('Service Image', 'vcard'); ?></label>
                    <div class="image-upload-container">
                        <input type="hidden" name="vcard_services[<?php echo $index; ?>][image]" 
                               value="<?php echo esc_attr($image); ?>" class="service-image-id">
                        <div class="image-preview">
                            <?php if (!empty($image)): ?>
                                <?php $image_url = wp_get_attachment_image_url($image, 'thumbnail'); ?>
                                <?php if ($image_url): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button select-image-btn" 
                                onclick="vCardManager.selectServiceImage(this)">
                            <?php echo !empty($image) ? __('Change Image', 'vcard') : __('Select Image', 'vcard'); ?>
                        </button>
                        <?php if (!empty($image)): ?>
                            <button type="button" class="button remove-image-btn" 
                                    onclick="vCardManager.removeServiceImage(this)">
                                <?php _e('Remove', 'vcard'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="service-field full-width">
                    <label><?php _e('Description', 'vcard'); ?></label>
                    <textarea name="vcard_services[<?php echo $index; ?>][description]" 
                              rows="3" 
                              placeholder="<?php _e('Describe your service in detail...', 'vcard'); ?>"><?php echo esc_textarea($description); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render individual product item
     */
    private function render_product_item($index, $product) {
        $name = isset($product['name']) ? $product['name'] : '';
        $description = isset($product['description']) ? $product['description'] : '';
        $price = isset($product['price']) ? $product['price'] : '';
        $category = isset($product['category']) ? $product['category'] : '';
        $in_stock = isset($product['in_stock']) ? $product['in_stock'] : true;
        $images = isset($product['images']) ? $product['images'] : array();
        ?>
        
        <div class="product-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="product-header">
                <span class="product-title">
                    <?php echo !empty($name) ? esc_html($name) : __('New Product', 'vcard'); ?>
                </span>
                <div class="product-actions">
                    <button type="button" class="remove-product-btn" onclick="vCardManager.removeProduct(this)">
                        <?php _e('Remove', 'vcard'); ?>
                    </button>
                </div>
            </div>
            
            <div class="product-fields">
                <div class="product-field">
                    <label><?php _e('Product Name', 'vcard'); ?> *</label>
                    <input type="text" name="vcard_products[<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($name); ?>" 
                           placeholder="<?php _e('Enter product name', 'vcard'); ?>"
                           onchange="vCardManager.updateProductTitle(this)">
                </div>
                
                <div class="product-field">
                    <label><?php _e('Price', 'vcard'); ?></label>
                    <input type="text" name="vcard_products[<?php echo $index; ?>][price]" 
                           value="<?php echo esc_attr($price); ?>" 
                           placeholder="<?php _e('e.g., $29.99', 'vcard'); ?>">
                </div>
                
                <div class="product-field">
                    <label><?php _e('Category', 'vcard'); ?></label>
                    <input type="text" name="vcard_products[<?php echo $index; ?>][category]" 
                           value="<?php echo esc_attr($category); ?>" 
                           placeholder="<?php _e('e.g., Electronics, Clothing', 'vcard'); ?>">
                </div>
                
                <div class="product-field">
                    <label>
                        <input type="checkbox" name="vcard_products[<?php echo $index; ?>][in_stock]" 
                               value="1" <?php checked($in_stock, true); ?>>
                        <?php _e('In Stock', 'vcard'); ?>
                    </label>
                </div>
                
                <div class="product-field full-width">
                    <label><?php _e('Description', 'vcard'); ?></label>
                    <textarea name="vcard_products[<?php echo $index; ?>][description]" 
                              rows="3" 
                              placeholder="<?php _e('Describe your product...', 'vcard'); ?>"><?php echo esc_textarea($description); ?></textarea>
                </div>
                
                <div class="product-field full-width">
                    <label><?php _e('Product Images', 'vcard'); ?></label>
                    <div class="product-images-container">
                        <input type="hidden" name="vcard_products[<?php echo $index; ?>][images]" 
                               value="<?php echo esc_attr(is_array($images) ? implode(',', $images) : $images); ?>" 
                               class="product-images-input">
                        <div class="product-images-grid">
                            <?php if (!empty($images) && is_array($images)): ?>
                                <?php foreach ($images as $image_id): ?>
                                    <?php if (!empty($image_id)): ?>
                                        <?php $this->render_product_image_item($image_id); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button add-product-images-btn" 
                                onclick="vCardManager.selectProductImages(this)">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Add Images', 'vcard'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render gallery item
     */
    private function render_gallery_item($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        if (!$image_url) {
            return;
        }
        ?>
        <div class="gallery-item" data-id="<?php echo esc_attr($image_id); ?>">
            <img src="<?php echo esc_url($image_url); ?>" alt="">
            <div class="gallery-item-actions">
                <button type="button" class="gallery-action-btn remove-image-btn" 
                        onclick="vCardManager.removeGalleryImage(this)" 
                        title="<?php _e('Remove Image', 'vcard'); ?>">
                    ×
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render product image item
     */
    private function render_product_image_item($image_id) {
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        if (!$image_url) {
            return;
        }
        ?>
        <div class="product-image-item" data-id="<?php echo esc_attr($image_id); ?>">
            <img src="<?php echo esc_url($image_url); ?>" alt="">
            <button type="button" class="remove-product-image-btn" 
                    onclick="vCardManager.removeProductImage(this)" 
                    title="<?php _e('Remove Image', 'vcard'); ?>">
                ×
            </button>
        </div>
        <?php
    }
    
    /**
     * Save enhanced meta fields
     */
    public function save_enhanced_meta_fields($post_id) {
        // Skip if not vcard_profile post type
        if (get_post_type($post_id) !== 'vcard_profile') {
            return;
        }
        
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save services data
        if (isset($_POST['vcard_services_manager_nonce']) && 
            wp_verify_nonce($_POST['vcard_services_manager_nonce'], 'vcard_services_manager')) {
            
            $services = isset($_POST['vcard_services']) ? $_POST['vcard_services'] : array();
            $services_data = array();
            
            foreach ($services as $service) {
                if (!empty($service['name'])) {
                    $services_data[] = array(
                        'name' => sanitize_text_field($service['name']),
                        'description' => sanitize_textarea_field($service['description']),
                        'price' => sanitize_text_field($service['price']),
                        'category' => sanitize_text_field($service['category']),
                        'image' => intval($service['image']),
                    );
                }
            }
            
            update_post_meta($post_id, '_vcard_services', wp_json_encode($services_data));
        }
        
        // Save products data
        if (isset($_POST['vcard_products_manager_nonce']) && 
            wp_verify_nonce($_POST['vcard_products_manager_nonce'], 'vcard_products_manager')) {
            
            $products = isset($_POST['vcard_products']) ? $_POST['vcard_products'] : array();
            $products_data = array();
            
            foreach ($products as $product) {
                if (!empty($product['name'])) {
                    $images = !empty($product['images']) ? explode(',', $product['images']) : array();
                    $images = array_map('intval', array_filter($images));
                    
                    $products_data[] = array(
                        'name' => sanitize_text_field($product['name']),
                        'description' => sanitize_textarea_field($product['description']),
                        'price' => sanitize_text_field($product['price']),
                        'category' => sanitize_text_field($product['category']),
                        'in_stock' => !empty($product['in_stock']),
                        'images' => $images,
                    );
                }
            }
            
            update_post_meta($post_id, '_vcard_products', wp_json_encode($products_data));
        }
        
        // Save gallery data
        if (isset($_POST['vcard_gallery_manager_nonce']) && 
            wp_verify_nonce($_POST['vcard_gallery_manager_nonce'], 'vcard_gallery_manager')) {
            
            $gallery = sanitize_text_field($_POST['vcard_gallery']);
            update_post_meta($post_id, '_vcard_gallery', $gallery);
        }
    }
    
    /**
     * Add custom columns to profile list
     */
    public function add_profile_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            // Add custom columns after title
            if ($key === 'title') {
                $new_columns['template'] = __('Template', 'vcard');
                $new_columns['views'] = __('Views', 'vcard');
                $new_columns['downloads'] = __('Downloads', 'vcard');
                $new_columns['subscription'] = __('Plan', 'vcard');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Populate custom columns
     */
    public function populate_profile_columns($column, $post_id) {
        switch ($column) {
            case 'template':
                $template = get_post_meta($post_id, '_vcard_template_name', true) ?: 'ceo';
                echo '<span class="template-badge">' . ucfirst(str_replace('-', ' ', $template)) . '</span>';
                break;
                
            case 'views':
                $views = intval(get_post_meta($post_id, '_vcard_profile_views', true));
                echo number_format($views);
                break;
                
            case 'downloads':
                $downloads = intval(get_post_meta($post_id, '_vcard_vcard_downloads', true));
                echo number_format($downloads);
                break;
                
            case 'subscription':
                $author_id = get_post_field('post_author', $post_id);
                $plan = get_user_meta($author_id, '_vcard_subscription_plan', true) ?: 'free';
                echo '<span class="plan-badge plan-' . $plan . '">' . ucfirst($plan) . '</span>';
                break;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($post_type !== 'vcard_profile') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_script(
            'vcard-profile-manager',
            VCARD_ASSETS_URL . 'js/profile-manager.js',
            array('jquery', 'jquery-ui-sortable'),
            VCARD_VERSION,
            true
        );
        
        wp_localize_script('vcard-profile-manager', 'vCardManager', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vcard_manager_nonce'),
            'strings' => array(
                'confirmRemove' => __('Are you sure you want to remove this item?', 'vcard'),
                'urlCopied' => __('URL copied to clipboard!', 'vcard'),
                'error' => __('An error occurred. Please try again.', 'vcard'),
            ),
        ));
        
        wp_enqueue_style(
            'vcard-profile-manager',
            VCARD_ASSETS_URL . 'css/profile-manager.css',
            array(),
            VCARD_VERSION
        );
    }
    
    // AJAX handlers will be implemented in the next part due to length constraints
}