<?php

namespace Minisite\Core;

/**
 * Role Manager
 * 
 * SINGLE RESPONSIBILITY: Manage WordPress roles and capabilities
 * - Defines minisite-specific roles and capabilities
 * - Handles role registration and updates
 * - Manages capability assignments
 */
final class RoleManager
{
    public static function initialize(): void
    {
        add_action('init', [self::class, 'syncRolesAndCapabilities'], 20);
    }
    
    public static function syncRolesAndCapabilities(): void
    {
        // Define capabilities
        $capabilities = self::getCapabilities();
        
        // Define roles
        $roles = self::getRoles();
        
        // Register roles
        foreach ($roles as $roleSlug => $roleData) {
            self::addOrUpdateRole($roleSlug, $roleData['name'], $roleData['capabilities']);
        }
        
        // Ensure WordPress Administrator has all minisite capabilities
        self::grantAdminCapabilities($capabilities);
    }
    
    private static function getCapabilities(): array
    {
        return [
            'minisite_read',
            'minisite_create',
            'minisite_publish',
            'minisite_edit_own',
            'minisite_delete_own',
            'minisite_edit_assigned',
            'minisite_edit_any',
            'minisite_delete_any',
            'minisite_read_private',
            'minisite_view_contact_reports_own',
            'minisite_view_contact_reports_all',
            'minisite_view_revenue_reports',
            'minisite_generate_discounts',
            'minisite_apply_discounts',
            'minisite_manage_referrals',
            'minisite_save_contact',
            'minisite_view_saved_contacts',
            'minisite_view_billing',
            'minisite_manage_plugin',
        ];
    }
    
    private static function getRoles(): array
    {
        return [
            'minisite_user' => [
                'name' => 'Minisite User',
                'capabilities' => [
                    'read' => true,
                    'minisite_read' => true,
                    'minisite_create' => true,
                    'minisite_edit_own' => true,
                    'minisite_delete_own' => true,
                    'minisite_save_contact' => true,
                    'minisite_view_saved_contacts' => true,
                    'minisite_apply_discounts' => true,
                ]
            ],
            'minisite_member' => [
                'name' => 'Minisite Member',
                'capabilities' => [
                    'read' => true,
                    'minisite_read' => true,
                    'minisite_create' => true,
                    'minisite_edit_own' => true,
                    'minisite_delete_own' => true,
                    'minisite_publish' => true,
                    'minisite_read_private' => true,
                    'minisite_view_contact_reports_own' => true,
                    'minisite_manage_referrals' => true,
                    'minisite_save_contact' => true,
                    'minisite_view_saved_contacts' => true,
                    'minisite_apply_discounts' => true,
                    'edit_posts' => true,
                    'upload_files' => true,
                ]
            ],
            'minisite_power' => [
                'name' => 'Minisite Power',
                'capabilities' => [
                    'read' => true,
                    'minisite_read' => true,
                    'minisite_create' => true,
                    'minisite_edit_own' => true,
                    'minisite_delete_own' => true,
                    'minisite_edit_assigned' => true,
                    'minisite_edit_any' => true,
                    'minisite_delete_any' => true,
                    'minisite_publish' => true,
                    'minisite_read_private' => true,
                    'minisite_view_contact_reports_own' => true,
                    'minisite_view_contact_reports_all' => true,
                    'minisite_view_revenue_reports' => true,
                    'minisite_generate_discounts' => true,
                    'minisite_manage_referrals' => true,
                    'minisite_save_contact' => true,
                    'minisite_view_saved_contacts' => true,
                    'minisite_view_billing' => true,
                    'edit_posts' => true,
                    'upload_files' => true,
                ]
            ],
            'minisite_admin' => [
                'name' => 'Minisite Admin',
                'capabilities' => [
                    'read' => true,
                    'minisite_read' => true,
                    'minisite_create' => true,
                    'minisite_edit_own' => true,
                    'minisite_delete_own' => true,
                    'minisite_edit_assigned' => true,
                    'minisite_edit_any' => true,
                    'minisite_delete_any' => true,
                    'minisite_publish' => true,
                    'minisite_read_private' => true,
                    'minisite_view_contact_reports_own' => true,
                    'minisite_view_contact_reports_all' => true,
                    'minisite_view_revenue_reports' => true,
                    'minisite_generate_discounts' => true,
                    'minisite_manage_referrals' => true,
                    'minisite_save_contact' => true,
                    'minisite_view_saved_contacts' => true,
                    'minisite_view_billing' => true,
                    'minisite_manage_plugin' => true,
                    'edit_posts' => true,
                    'upload_files' => true,
                ]
            ],
        ];
    }
    
    private static function addOrUpdateRole(string $slug, string $name, array $caps): void
    {
        $role = get_role($slug);
        if (!$role) {
            add_role($slug, $name, $caps);
            $role = get_role($slug);
        }
        if ($role) {
            foreach ($caps as $cap => $grant) {
                if ($grant) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    private static function grantAdminCapabilities(array $capabilities): void
    {
        if ($wpAdmin = get_role('administrator')) {
            foreach ($capabilities as $cap) {
                $wpAdmin->add_cap($cap);
            }
        }
    }
}
