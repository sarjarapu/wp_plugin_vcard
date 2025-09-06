<?php
/**
 * Business Client Dashboard Template
 * 
 * Dashboard interface for business clients to manage their profiles
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get dashboard statistics
$total_profiles = count($profiles);
$published_profiles = count(array_filter($profiles, function($p) { return $p->post_status === 'publish'; }));
$draft_profiles = count(array_filter($profiles, function($p) { return $p->post_status === 'draft'; }));

// Get analytics data
$total_views = 0;
$total_downloads = 0;
foreach ($profiles as $profile) {
    $total_views += intval(get_post_meta($profile->ID, '_vcard_profile_views', true));
    $total_downloads += intval(get_post_meta($profile->ID, '_vcard_vcard_downloads', true));
}

// Get subscription info
$subscription_status = get_user_meta($user_id, '_vcard_subscription_status', true) ?: 'active';
$subscription_expires = get_user_meta($user_id, '_vcard_subscription_expires', true);

// Profile creation limits
$auth = new VCard_Dashboard_Auth();
$can_create_more = $auth->check_profile_creation_limits($user_id);
?>

<div class="wrap vcard-client-dashboard">
    <h1><?php _e('My vCard Dashboard', 'vcard'); ?></h1>
    
    <!-- Welcome Section -->
    <div class="vcard-welcome-section">
        <div class="welcome-message">
            <h2><?php printf(__('Welcome back, %s!', 'vcard'), esc_html($current_user->display_name)); ?></h2>
            <p><?php _e('Manage your business profiles and track their performance from this dashboard.', 'vcard'); ?></p>
        </div>
        
        <div class="quick-actions">
            <?php if ($can_create_more): ?>
                <a href="<?php echo admin_url('post-new.php?post_type=vcard_profile'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Create New Profile', 'vcard'); ?>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($profiles)): ?>
                <a href="<?php echo admin_url('edit.php?post_type=vcard_profile'); ?>" class="button">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Manage Profiles', 'vcard'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="vcard-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-id-alt"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_profiles; ?></h3>
                <p><?php _e('Total Profiles', 'vcard'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-visibility"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_views); ?></h3>
                <p><?php _e('Profile Views', 'vcard'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-download"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_downloads); ?></h3>
                <p><?php _e('vCard Downloads', 'vcard'); ?></p>
            </div>
        </div>
        
        <div class="stat-card subscription-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="stat-content">
                <h3><?php echo ucfirst($subscription_plan); ?></h3>
                <p><?php _e('Subscription Plan', 'vcard'); ?></p>
                <?php if ($subscription_plan === 'free'): ?>
                    <a href="#" class="upgrade-link"><?php _e('Upgrade', 'vcard'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Profile Status Overview -->
    <div class="vcard-dashboard-section">
        <h2><?php _e('Profile Status', 'vcard'); ?></h2>
        
        <div class="profile-status-grid">
            <div class="status-item published">
                <span class="status-count"><?php echo $published_profiles; ?></span>
                <span class="status-label"><?php _e('Published', 'vcard'); ?></span>
            </div>
            
            <div class="status-item draft">
                <span class="status-count"><?php echo $draft_profiles; ?></span>
                <span class="status-label"><?php _e('Drafts', 'vcard'); ?></span>
            </div>
            
            <?php if (!$can_create_more): ?>
                <div class="status-item limit-reached">
                    <span class="status-icon dashicons dashicons-warning"></span>
                    <span class="status-label"><?php _e('Profile Limit Reached', 'vcard'); ?></span>
                    <a href="#" class="upgrade-link"><?php _e('Upgrade Plan', 'vcard'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Profiles -->
    <?php if (!empty($profiles)): ?>
    <div class="vcard-dashboard-section">
        <div class="section-header">
            <h2><?php _e('Your Profiles', 'vcard'); ?></h2>
            <a href="<?php echo admin_url('edit.php?post_type=vcard_profile'); ?>" class="view-all-link">
                <?php _e('View All', 'vcard'); ?>
            </a>
        </div>
        
        <div class="profiles-grid">
            <?php foreach (array_slice($profiles, 0, 6) as $profile): 
                $profile_views = intval(get_post_meta($profile->ID, '_vcard_profile_views', true));
                $profile_downloads = intval(get_post_meta($profile->ID, '_vcard_vcard_downloads', true));
                $template_name = get_post_meta($profile->ID, '_vcard_template_name', true) ?: 'ceo';
                $business_name = get_post_meta($profile->ID, '_vcard_business_name', true) ?: $profile->post_title;
                $business_logo = get_post_meta($profile->ID, '_vcard_business_logo', true);
                $logo_url = $business_logo ? wp_get_attachment_image_url($business_logo, 'thumbnail') : '';
            ?>
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-logo">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($business_name); ?>">
                        <?php else: ?>
                            <span class="default-logo dashicons dashicons-building"></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-info">
                        <h3><?php echo esc_html($business_name); ?></h3>
                        <span class="profile-status status-<?php echo $profile->post_status; ?>">
                            <?php echo ucfirst($profile->post_status); ?>
                        </span>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat">
                        <span class="dashicons dashicons-visibility"></span>
                        <span><?php echo number_format($profile_views); ?> <?php _e('views', 'vcard'); ?></span>
                    </div>
                    <div class="stat">
                        <span class="dashicons dashicons-download"></span>
                        <span><?php echo number_format($profile_downloads); ?> <?php _e('downloads', 'vcard'); ?></span>
                    </div>
                </div>
                
                <div class="profile-template">
                    <span class="template-badge"><?php echo ucfirst(str_replace('-', ' ', $template_name)); ?></span>
                </div>
                
                <div class="profile-actions">
                    <a href="<?php echo get_edit_post_link($profile->ID); ?>" class="button button-small">
                        <?php _e('Edit', 'vcard'); ?>
                    </a>
                    
                    <?php if ($profile->post_status === 'publish'): ?>
                        <a href="<?php echo get_permalink($profile->ID); ?>" class="button button-small" target="_blank">
                            <?php _e('View', 'vcard'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- No Profiles State -->
    <div class="vcard-dashboard-section no-profiles">
        <div class="empty-state">
            <div class="empty-icon">
                <span class="dashicons dashicons-id-alt"></span>
            </div>
            <h3><?php _e('No Profiles Yet', 'vcard'); ?></h3>
            <p><?php _e('Create your first business profile to get started with vCard.', 'vcard'); ?></p>
            
            <?php if ($can_create_more): ?>
                <a href="<?php echo admin_url('post-new.php?post_type=vcard_profile'); ?>" class="button button-primary button-large">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Create Your First Profile', 'vcard'); ?>
                </a>
            <?php else: ?>
                <p class="limit-message">
                    <?php _e('You have reached your profile creation limit.', 'vcard'); ?>
                    <a href="#" class="upgrade-link"><?php _e('Upgrade your plan', 'vcard'); ?></a>
                    <?php _e('to create more profiles.', 'vcard'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Help Section -->
    <div class="vcard-dashboard-section help-section">
        <h2><?php _e('Need Help?', 'vcard'); ?></h2>
        
        <div class="help-grid">
            <div class="help-item">
                <div class="help-icon">
                    <span class="dashicons dashicons-book-alt"></span>
                </div>
                <div class="help-content">
                    <h4><?php _e('Getting Started Guide', 'vcard'); ?></h4>
                    <p><?php _e('Learn how to create and customize your business profile.', 'vcard'); ?></p>
                    <a href="#" class="help-link"><?php _e('Read Guide', 'vcard'); ?></a>
                </div>
            </div>
            
            <div class="help-item">
                <div class="help-icon">
                    <span class="dashicons dashicons-video-alt3"></span>
                </div>
                <div class="help-content">
                    <h4><?php _e('Video Tutorials', 'vcard'); ?></h4>
                    <p><?php _e('Watch step-by-step tutorials for common tasks.', 'vcard'); ?></p>
                    <a href="#" class="help-link"><?php _e('Watch Videos', 'vcard'); ?></a>
                </div>
            </div>
            
            <div class="help-item">
                <div class="help-icon">
                    <span class="dashicons dashicons-sos"></span>
                </div>
                <div class="help-content">
                    <h4><?php _e('Support Center', 'vcard'); ?></h4>
                    <p><?php _e('Get help from our support team.', 'vcard'); ?></p>
                    <a href="#" class="help-link"><?php _e('Contact Support', 'vcard'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.vcard-client-dashboard {
    max-width: 1200px;
}

.vcard-welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
}

.welcome-message h2 {
    margin: 0 0 8px 0;
    color: #1d2327;
}

.welcome-message p {
    margin: 0;
    color: #646970;
}

.quick-actions {
    display: flex;
    gap: 10px;
}

.vcard-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: #f0f6fc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon .dashicons {
    font-size: 24px;
    color: #0073aa;
}

.stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.stat-content p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.subscription-card .stat-icon {
    background: #fff3cd;
}

.subscription-card .stat-icon .dashicons {
    color: #856404;
}

.upgrade-link {
    color: #0073aa;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
}

.upgrade-link:hover {
    text-decoration: underline;
}

.vcard-dashboard-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
}

.vcard-dashboard-section h2 {
    margin: 0 0 20px 0;
    color: #1d2327;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    margin: 0;
}

.view-all-link {
    color: #0073aa;
    text-decoration: none;
    font-weight: 500;
}

.view-all-link:hover {
    text-decoration: underline;
}

.profile-status-grid {
    display: flex;
    gap: 30px;
    align-items: center;
}

.status-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.status-count {
    font-size: 32px;
    font-weight: 600;
    color: #1d2327;
}

.status-label {
    font-size: 14px;
    color: #646970;
}

.status-item.published .status-count {
    color: #00a32a;
}

.status-item.draft .status-count {
    color: #dba617;
}

.status-item.limit-reached {
    flex-direction: row;
    gap: 10px;
    padding: 10px 15px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}

.status-item.limit-reached .status-icon {
    color: #856404;
}

.profiles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.profile-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    background: #fafafa;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.profile-logo {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-logo {
    font-size: 24px;
    color: #646970;
}

.profile-info h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: #1d2327;
}

.profile-status {
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.status-publish {
    background: #d1e7dd;
    color: #0f5132;
}

.status-draft {
    background: #fff3cd;
    color: #664d03;
}

.profile-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.profile-stats .stat {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #646970;
}

.profile-stats .dashicons {
    font-size: 14px;
}

.template-badge {
    display: inline-block;
    background: #e7f3ff;
    color: #0073aa;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 15px;
}

.profile-actions {
    display: flex;
    gap: 8px;
}

.no-profiles {
    text-align: center;
    padding: 60px 20px;
}

.empty-state .empty-icon {
    width: 80px;
    height: 80px;
    background: #f0f6fc;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.empty-state .empty-icon .dashicons {
    font-size: 40px;
    color: #0073aa;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #1d2327;
}

.empty-state p {
    margin: 0 0 20px 0;
    color: #646970;
}

.limit-message {
    color: #856404;
    background: #fff3cd;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #ffeaa7;
}

.help-section {
    border: none;
    background: #f8f9fa;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.help-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.help-icon {
    width: 40px;
    height: 40px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.help-icon .dashicons {
    font-size: 18px;
    color: #0073aa;
}

.help-content h4 {
    margin: 0 0 8px 0;
    color: #1d2327;
    font-size: 14px;
}

.help-content p {
    margin: 0 0 8px 0;
    color: #646970;
    font-size: 13px;
}

.help-link {
    color: #0073aa;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
}

.help-link:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .vcard-welcome-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .vcard-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .profile-status-grid {
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .profiles-grid {
        grid-template-columns: 1fr;
    }
    
    .help-grid {
        grid-template-columns: 1fr;
    }
}
</style>