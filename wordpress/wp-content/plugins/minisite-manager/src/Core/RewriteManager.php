<?php

namespace Minisite\Core;

/**
 * Rewrite Manager
 * 
 * SINGLE RESPONSIBILITY: Manage WordPress rewrite rules
 * - Handles rewrite rule registration
 * - Manages query variables
 * - Coordinates with RewriteRegistrar
 */
final class RewriteManager
{
    public static function initialize(): void
    {
        add_action('init', [self::class, 'registerRewriteRules'], 5);
        add_filter('query_vars', [self::class, 'addQueryVars']);
        
        // One-time flush after activation
        if (get_option('minisite_flush_rewrites')) {
            flush_rewrite_rules();
            delete_option('minisite_flush_rewrites');
        }
    }
    
    public static function registerRewriteRules(): void
    {
        if (class_exists(\Minisite\Application\Http\RewriteRegistrar::class)) {
            $rewriteRegistrar = new \Minisite\Application\Http\RewriteRegistrar();
            $rewriteRegistrar->register();
        }
    }
    
    public static function addQueryVars(array $vars): array
    {
        $vars[] = 'minisite';
        $vars[] = 'minisite_biz';
        $vars[] = 'minisite_loc';
        $vars[] = 'minisite_account';
        $vars[] = 'minisite_account_action';
        $vars[] = 'minisite_site_id';
        $vars[] = 'minisite_version_id';
        return $vars;
    }
}
