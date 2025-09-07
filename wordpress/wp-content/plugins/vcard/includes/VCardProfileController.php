<?php
/**
 * VCard Profile Controller Class
 * 
 * Handles the display logic for vCard profiles
 * 
 * @package VCard
 * @version 1.0.0
 */

namespace VCard;

class VCardProfileController {
    
    private $template_renderer;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->template_renderer = new VCardTemplateRenderer();
    }
    
    /**
     * Display vCard profile (for template use)
     * 
     * @param int $profile_id
     */
    public function display_profile($profile_id) {
        // Get profile data
        $profile_data = VCardDatabaseHelper::getBusinessProfileData($profile_id);
        
        if (empty($profile_data)) {
            return $this->renderNotFound();
        }
        
        // Track profile view
        VCardDatabaseHelper::incrementProfileViews($profile_id);
        
        // Render template
        return $this->template_renderer->renderProfile($profile_data);
    }
    
    /**
     * Display vCard profile (legacy method for backward compatibility)
     * 
     * @param int $profile_id
     * @return string
     */
    public function displayProfile($profile_id) {
        // Get profile data
        $profile_data = VCardDatabaseHelper::getBusinessProfileData($profile_id);
        
        if (empty($profile_data)) {
            return $this->renderNotFound();
        }
        
        // Track profile view
        VCardDatabaseHelper::incrementProfileViews($profile_id);
        
        // Render template
        return $this->template_renderer->renderProfile($profile_data);
    }
    
    /**
     * Handle profile not found
     * 
     * @return string
     */
    private function renderNotFound() {
        return '<div class="vcard-not-found">
            <h1>' . __('Profile Not Found', 'vcard') . '</h1>
            <p>' . __('The requested vCard profile could not be found.', 'vcard') . '</p>
        </div>';
    }
    
    /**
     * Get profile data for template rendering
     * 
     * @param int $profile_id
     * @return array|null
     */
    public function get_profile_data($profile_id) {
        return VCardDatabaseHelper::getBusinessProfileData($profile_id);
    }
    
    /**
     * Get profile data for AJAX requests
     * 
     * @param int $profile_id
     * @return array|false
     */
    public function getProfileDataForAjax($profile_id) {
        $profile_data = VCardDatabaseHelper::getBusinessProfileData($profile_id);
        
        if (empty($profile_data)) {
            return false;
        }
        
        // Return only necessary data for AJAX
        return [
            'id' => $profile_data['id'],
            'basic_info' => $profile_data['basic_info'],
            'contact_info' => $profile_data['contact_info'],
            'is_business' => VCardDatabaseHelper::isBusinessProfile($profile_data),
            'analytics' => $profile_data['analytics']
        ];
    }
    
    /**
     * Generate vCard export data
     * 
     * @param int $profile_id
     * @return array|false
     */
    public function generateVCardExportData($profile_id) {
        $profile_data = VCardDatabaseHelper::getBusinessProfileData($profile_id);
        
        if (empty($profile_data)) {
            return false;
        }
        
        $basic_info = $profile_data['basic_info'];
        $contact_info = $profile_data['contact_info'];
        $address = $profile_data['address'];
        $is_business = VCardDatabaseHelper::isBusinessProfile($profile_data);
        
        // Prepare vCard data
        $vcard_data = [
            'fn' => $is_business ? 
                $basic_info['business_name'] : 
                trim($basic_info['first_name'] . ' ' . $basic_info['last_name']),
            'org' => $basic_info['company'] ?: $basic_info['business_name'],
            'title' => $basic_info['job_title'],
            'tel_work' => $contact_info['phone'],
            'tel_cell' => $contact_info['secondary_phone'],
            'email' => $contact_info['email'],
            'url' => $contact_info['website'],
            'note' => $basic_info['business_description'],
        ];
        
        // Format address
        if (!empty(array_filter($address))) {
            $vcard_data['adr'] = [
                $address['address'],
                $address['city'],
                $address['state'],
                $address['zip_code'],
                $address['country']
            ];
        }
        
        // Add social media
        $social_media = array_filter($profile_data['social_media']);
        if (!empty($social_media)) {
            $vcard_data['social_media'] = $social_media;
        }
        
        return $vcard_data;
    }
}