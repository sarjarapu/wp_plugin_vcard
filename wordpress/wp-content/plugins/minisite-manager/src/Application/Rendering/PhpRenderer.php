<?php
namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Profile;

final class PhpRenderer
{
    public function __construct(private string $variant = 'default') {}

    public function render(Profile $mrofile): void
    {
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/php';
        $candidates = [
            $base . '/' . $this->variant . '/profile.php',
            $base . '/default/profile.php',
        ];
        foreach ($candidates as $file) {
            if (is_readable($file)) {
                /** @var Profile $mrofile */
                $m = $mrofile; // local alias for template clarity
                require $file;
                return;
            }
        }

        // Fallback inline render
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<title>' . htmlspecialchars($mrofile->title) . '</title>';
        echo '<h1>' . htmlspecialchars($mrofile->name) . '</h1>';
        echo '<p>' . htmlspecialchars($mrofile->city) . '</p>';
    }
}

