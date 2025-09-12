<?php
namespace Minisite\Application\Http;

final class RewriteRegistrar
{
    public function register(): void
    {
        add_rewrite_tag('%minisite%', '([0-1])');
        add_rewrite_tag('%minisite_biz%', '([^&]+)');
        add_rewrite_tag('%minisite_loc%', '([^&]+)');
        add_rewrite_rule('^b/([^/]+)/([^/]+)/?$', 'index.php?minisite=1&minisite_biz=$matches[1]&minisite_loc=$matches[2]', 'top');
    }
}

