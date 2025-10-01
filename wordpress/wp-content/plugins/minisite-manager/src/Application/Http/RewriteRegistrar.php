<?php

namespace Minisite\Application\Http;

final class RewriteRegistrar
{
    public function register(): void
    {
        // Minisite profile routes
        add_rewrite_tag('%minisite%', '([0-1])');
        add_rewrite_tag('%minisite_biz%', '([^&]+)');
        add_rewrite_tag('%minisite_loc%', '([^&]+)');
        add_rewrite_rule('^b/([^/]+)/([^/]+)/?$', 'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', 'top');

        // Account authentication routes
        add_rewrite_tag('%minisite_account%', '([0-1])');
        add_rewrite_tag('%minisite_account_action%', '([^&]+)');

        // Account routes: /account/login, /account/register, /account/dashboard, /account/logout, /account/forgot, /account/sites
        add_rewrite_rule('^account/(login|register|dashboard|logout|forgot|sites)/?$', 'index.php?minisite_account=1&minisite_account_action=$matches[1]', 'top');

        // Account sites new route: /account/sites/new
        add_rewrite_rule('^account/sites/new/?$', 'index.php?minisite_account=1&minisite_account_action=new', 'top');

        // Account sites publish route: /account/sites/publish
        add_rewrite_rule('^account/sites/publish/?$', 'index.php?minisite_account=1&minisite_account_action=publish', 'top');

        // Account sites management routes: /account/sites/{id}/edit, /account/sites/{id}/edit/{version_id}, /account/sites/{id}/preview/{version_id}, /account/sites/{id}/versions
        add_rewrite_tag('%minisite_site_id%', '([a-f0-9]{24,32})');
        add_rewrite_tag('%minisite_version_id%', '([0-9]+|current|latest)');
        add_rewrite_rule('^account/sites/([a-f0-9]{24,32})/edit/([0-9]+|latest)/?$', 'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]&minisite_version_id=$matches[2]', 'top');
        add_rewrite_rule('^account/sites/([a-f0-9]{24,32})/edit/?$', 'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]', 'top');
        add_rewrite_rule('^account/sites/([a-f0-9]{24,32})/preview/([0-9]+|current)/?$', 'index.php?minisite_account=1&minisite_account_action=preview&minisite_site_id=$matches[1]&minisite_version_id=$matches[2]', 'top');
        add_rewrite_rule('^account/sites/([a-f0-9]{24,32})/versions/?$', 'index.php?minisite_account=1&minisite_account_action=versions&minisite_site_id=$matches[1]', 'top');
        add_rewrite_rule('^account/sites/([a-f0-9]{24,32})/?$', 'index.php?minisite_account=1&minisite_account_action=edit&minisite_site_id=$matches[1]', 'top');
    }
}
