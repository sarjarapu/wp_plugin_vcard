<?php
/**
 * Template Thumbnail Generator
 * 
 * Generates SVG thumbnails for templates when actual images are not available
 * 
 * @package vCard
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class VCard_Template_Thumbnail_Generator {
    
    /**
     * Generate SVG thumbnail for template
     */
    public static function generate_thumbnail($template_key, $width = 280, $height = 180) {
        $template_configs = array(
            'ceo' => array(
                'bg' => '#f8fafc',
                'primary' => '#1e40af',
                'secondary' => '#374151',
                'layout' => 'header-focused'
            ),
            'freelancer' => array(
                'bg' => '#faf5ff',
                'primary' => '#7c3aed',
                'secondary' => '#a855f7',
                'layout' => 'portfolio-focused'
            ),
            'restaurant' => array(
                'bg' => '#fff7ed',
                'primary' => '#ea580c',
                'secondary' => '#92400e',
                'layout' => 'menu-focused'
            ),
            'healthcare' => array(
                'bg' => '#f0fdf4',
                'primary' => '#059669',
                'secondary' => '#047857',
                'layout' => 'service-focused'
            ),
            'construction' => array(
                'bg' => '#f9fafb',
                'primary' => '#374151',
                'secondary' => '#6b7280',
                'layout' => 'project-focused'
            ),
            'education' => array(
                'bg' => '#f0f9ff',
                'primary' => '#0284c7',
                'secondary' => '#0369a1',
                'layout' => 'content-focused'
            ),
            'fitness' => array(
                'bg' => '#fef2f2',
                'primary' => '#dc2626',
                'secondary' => '#991b1b',
                'layout' => 'action-focused'
            ),
            'coffeebar' => array(
                'bg' => '#fef7f0',
                'primary' => '#92400e',
                'secondary' => '#78350f',
                'layout' => 'cozy-focused'
            ),
            'handyman' => array(
                'bg' => '#f0fdfa',
                'primary' => '#0d9488',
                'secondary' => '#0f766e',
                'layout' => 'service-focused'
            )
        );
        
        $config = isset($template_configs[$template_key]) ? $template_configs[$template_key] : $template_configs['ceo'];
        
        $svg = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg">';
        
        // Background
        $svg .= '<rect width="100%" height="100%" fill="' . $config['bg'] . '"/>';
        
        // Header section
        $svg .= '<rect x="0" y="0" width="100%" height="60" fill="' . $config['primary'] . '"/>';
        
        // Profile area
        $svg .= '<circle cx="40" cy="30" r="20" fill="white" opacity="0.9"/>';
        $svg .= '<rect x="70" y="20" width="120" height="8" fill="white" opacity="0.9" rx="4"/>';
        $svg .= '<rect x="70" y="35" width="80" height="6" fill="white" opacity="0.7" rx="3"/>';
        
        // Content sections based on layout
        switch ($config['layout']) {
            case 'header-focused':
                // Large header with contact info
                $svg .= '<rect x="20" y="80" width="240" height="12" fill="' . $config['secondary'] . '" opacity="0.8" rx="6"/>';
                $svg .= '<rect x="20" y="100" width="180" height="8" fill="' . $config['secondary'] . '" opacity="0.6" rx="4"/>';
                $svg .= '<rect x="20" y="120" width="200" height="8" fill="' . $config['secondary'] . '" opacity="0.6" rx="4"/>';
                break;
                
            case 'portfolio-focused':
                // Gallery grid
                for ($i = 0; $i < 6; $i++) {
                    $x = 20 + ($i % 3) * 80;
                    $y = 80 + floor($i / 3) * 40;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="70" height="30" fill="' . $config['secondary'] . '" opacity="0.7" rx="4"/>';
                }
                break;
                
            case 'menu-focused':
                // Menu items
                for ($i = 0; $i < 4; $i++) {
                    $y = 80 + $i * 25;
                    $svg .= '<rect x="20" y="' . $y . '" width="150" height="8" fill="' . $config['secondary'] . '" opacity="0.8" rx="4"/>';
                    $svg .= '<rect x="20" y="' . ($y + 12) . '" width="100" height="6" fill="' . $config['secondary'] . '" opacity="0.6" rx="3"/>';
                }
                break;
                
            case 'service-focused':
                // Service list
                for ($i = 0; $i < 3; $i++) {
                    $y = 80 + $i * 30;
                    $svg .= '<circle cx="35" cy="' . ($y + 10) . '" r="8" fill="' . $config['primary'] . '" opacity="0.8"/>';
                    $svg .= '<rect x="50" y="' . ($y + 5) . '" width="120" height="8" fill="' . $config['secondary'] . '" opacity="0.8" rx="4"/>';
                    $svg .= '<rect x="50" y="' . ($y + 16) . '" width="80" height="6" fill="' . $config['secondary'] . '" opacity="0.6" rx="3"/>';
                }
                break;
                
            default:
                // Default content layout
                $svg .= '<rect x="20" y="80" width="240" height="8" fill="' . $config['secondary'] . '" opacity="0.8" rx="4"/>';
                $svg .= '<rect x="20" y="95" width="200" height="6" fill="' . $config['secondary'] . '" opacity="0.6" rx="3"/>';
                $svg .= '<rect x="20" y="110" width="180" height="6" fill="' . $config['secondary'] . '" opacity="0.6" rx="3"/>';
                break;
        }
        
        // Footer/contact section
        $svg .= '<rect x="0" y="' . ($height - 30) . '" width="100%" height="30" fill="' . $config['secondary'] . '" opacity="0.1"/>';
        $svg .= '<rect x="20" y="' . ($height - 20) . '" width="60" height="6" fill="' . $config['primary'] . '" opacity="0.8" rx="3"/>';
        $svg .= '<rect x="90" y="' . ($height - 20) . '" width="60" height="6" fill="' . $config['primary'] . '" opacity="0.8" rx="3"/>';
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Save thumbnail as PNG file
     */
    public static function save_thumbnail($template_key, $width = 280, $height = 180) {
        $svg_content = self::generate_thumbnail($template_key, $width, $height);
        $filename = $template_key . '-thumb.svg';
        $filepath = VCARD_PLUGIN_PATH . 'assets/images/templates/' . $filename;
        
        file_put_contents($filepath, $svg_content);
        
        return VCARD_ASSETS_URL . 'images/templates/' . $filename;
    }
    
    /**
     * Generate all template thumbnails
     */
    public static function generate_all_thumbnails() {
        $templates = array('ceo', 'freelancer', 'restaurant', 'healthcare', 'construction', 'education', 'fitness', 'coffeebar', 'handyman');
        
        foreach ($templates as $template) {
            self::save_thumbnail($template);
        }
        
        // Create default thumbnail
        $default_svg = self::generate_thumbnail('ceo');
        file_put_contents(VCARD_PLUGIN_PATH . 'assets/images/templates/default-thumb.svg', $default_svg);
    }
}