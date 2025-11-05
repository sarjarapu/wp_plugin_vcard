<?php

namespace Minisite\Core;

/**
 * Rewrite Coordinator
 *
 * SINGLE RESPONSIBILITY: Coordinate WordPress rewrite system lifecycle
 * - Handles rewrite system initialization
 * - Manages query variables registration
 * - Coordinates with RewriteRegistrar for rule registration
 */
final class RewriteCoordinator
{
    public static function initialize(): void
    {
        // Register rewrite rules immediately
        self::registerRewriteRules();
        add_filter('query_vars', array(self::class, 'addQueryVars'));

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
        $vars[] = 'minisite_id';
        $vars[] = 'minisite_version_id';

        return $vars;
    }
}
