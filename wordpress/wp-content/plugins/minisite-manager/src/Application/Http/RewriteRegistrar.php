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
        
        // Account routes: /account/login, /account/register, /account/dashboard, /account/logout, /account/forgot
        add_rewrite_rule('^account/(login|register|dashboard|logout|forgot)/?$', 'index.php?minisite_account=1&minisite_account_action=$matches[1]', 'top');
    }
}

