<?php
namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Minisite;

final class PhpRenderer
{
    public function __construct(private string $variant = 'default') {}

    public function render(Minisite $minisite): void
    {
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/php';
        $candidates = [
            $base . '/' . $this->variant . '/minisite.php',
            $base . '/default/minisite.php',
        ];
        foreach ($candidates as $file) {
            if (is_readable($file)) {
                /** @var Minisite $minisite */
                $m = $minisite; // local alias for template clarity
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

