<?php
/**
 * VCard Profile Loader Class
 * 
 * Handles the loading and initialization of profile viewing functionality
 * 
 * @package VCard
 * @version 1.0.0
 */

namespace VCard;

class VCardProfileLoader {
    
    private $controller;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->controller = new VCardProfileController();
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Template loading
        add_filter('template_include', [$this, 'loadProfileTemplate']);
        
        // AJAX handlers for profile functionality
        add_action('wp_ajax_get_vcard_export_data', [$this, 'handleVCardExport']);
        add_action('wp_ajax_nopriv_get_vcard_export_data', [$this, 'handleVCardExport']);
        
        // Modern UX AJAX handlers
        add_action('wp_ajax_vcard_modern_ux_track_event', [$this, 'handleModernUXTracking']);
        add_action('wp_ajax_nopriv_vcard_modern_ux_track_event', [$this, 'handleModernUXTracking']);
    }
    
    /**
     * Load profile template
     * 
     * @param string $template
     * @return string
     */
    public function loadProfileTemplate($template) {
        if (is_singular('vcard_profile')) {
            $clean_template = VCARD_PLUGIN_PATH . 'templates/single-vcard_profile-clean.php';
            
            if (file_exists($clean_template)) {
                return $clean_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Handle vCard export AJAX request
     */
    public function handleVCardExport() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vcard_export_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $profile_id = intval($_POST['profile_id'] ?? 0);
        $format = sanitize_text_field($_POST['format'] ?? 'vcf');
        
        if (!$profile_id) {
            wp_send_json_error('Invalid profile ID');
        }
        
        // Get export data
        $export_data = $this->controller->generateVCardExportData($profile_id);
        
        if (!$export_data) {
            wp_send_json_error('Profile not found');
        }
        
        // Generate vCard content
        $vcard_content = $this->generateVCardContent($export_data);
        
        wp_send_json_success([
            'content' => $vcard_content,
            'filename' => $this->generateVCardFilename($export_data),
            'mime_type' => 'text/vcard',
            'format' => $format
        ]);
    }
    
    /**
     * Handle modern UX tracking
     */
    public function handleModernUXTracking() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vcard_public_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $event_name = sanitize_text_field($_POST['event_name'] ?? '');
        $event_data = $_POST['event_data'] ?? [];
        
        // Log the event
        error_log("vCard Event: {$event_name} - " . json_encode($event_data));
        
        // Update profile meta if profile_id is provided
        if (isset($event_data['profile_id'])) {
            $profile_id = intval($event_data['profile_id']);
            $this->updateEventMetrics($profile_id, $event_name, $event_data);
        }
        
        wp_send_json_success([
            'message' => 'Event tracked successfully',
            'event' => $event_name
        ]);
    }
    
    /**
     * Update event metrics
     * 
     * @param int $profile_id
     * @param string $event_name
     * @param array $event_data
     */
    private function updateEventMetrics($profile_id, $event_name, $event_data) {
        switch ($event_name) {
            case 'quick_action_call':
            case 'quick_action_message':
            case 'quick_action_whatsapp':
            case 'quick_action_directions':
            case 'quick_action_share_native':
            case 'quick_action_share_clipboard':
                // Track as general interaction
                $current_views = (int) get_post_meta($profile_id, '_vcard_profile_views', true);
                update_post_meta($profile_id, '_vcard_profile_views', $current_views + 1);
                break;
                
            case 'contact_save_toggle':
                // Track contact saves
                if (isset($event_data['saved']) && $event_data['saved']) {
                    $current_saves = (int) get_post_meta($profile_id, '_vcard_contact_saves', true);
                    update_post_meta($profile_id, '_vcard_contact_saves', $current_saves + 1);
                }
                break;
        }
    }
    
    /**
     * Generate vCard content
     * 
     * @param array $data
     * @return string
     */
    private function generateVCardContent($data) {
        $vcard = "BEGIN:VCARD\nVERSION:4.0\n";
        
        if (!empty($data['fn'])) {
            $vcard .= "FN:" . $this->escapeVCardValue($data['fn']) . "\n";
        }
        
        if (!empty($data['org'])) {
            $vcard .= "ORG:" . $this->escapeVCardValue($data['org']) . "\n";
        }
        
        if (!empty($data['title'])) {
            $vcard .= "TITLE:" . $this->escapeVCardValue($data['title']) . "\n";
        }
        
        if (!empty($data['tel_work'])) {
            $vcard .= "TEL;TYPE=work,voice:" . $this->escapeVCardValue($data['tel_work']) . "\n";
        }
        
        if (!empty($data['tel_cell'])) {
            $vcard .= "TEL;TYPE=work,cell:" . $this->escapeVCardValue($data['tel_cell']) . "\n";
        }
        
        if (!empty($data['email'])) {
            $vcard .= "EMAIL;TYPE=work:" . $this->escapeVCardValue($data['email']) . "\n";
        }
        
        if (!empty($data['url'])) {
            $vcard .= "URL:" . $this->escapeVCardValue($data['url']) . "\n";
        }
        
        if (!empty($data['adr'])) {
            $vcard .= "ADR;TYPE=work:;;" . implode(';', array_map([$this, 'escapeVCardValue'], $data['adr'])) . "\n";
        }
        
        if (!empty($data['note'])) {
            $vcard .= "NOTE:" . $this->escapeVCardValue($data['note']) . "\n";
        }
        
        // Add social media
        if (!empty($data['social_media'])) {
            foreach ($data['social_media'] as $platform => $url) {
                $vcard .= "X-SOCIALPROFILE;TYPE=" . strtoupper($platform) . ":" . $this->escapeVCardValue($url) . "\n";
            }
        }
        
        // Add revision timestamp
        $vcard .= "REV:" . gmdate('Ymd\THis\Z') . "\n";
        $vcard .= "END:VCARD";
        
        return $vcard;
    }
    
    /**
     * Generate vCard filename
     * 
     * @param array $data
     * @return string
     */
    private function generateVCardFilename($data) {
        $name = $data['fn'] ?? 'vcard';
        $safe_name = sanitize_file_name($name);
        return $safe_name . '.vcf';
    }
    
    /**
     * Escape vCard value
     * 
     * @param string $value
     * @return string
     */
    private function escapeVCardValue($value) {
        // Escape special characters in vCard format
        $value = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', '\\r'], $value);
        return $value;
    }
}