<?php
namespace Minisite\Application\Rendering;

use Minisite\Domain\Entities\Profile;

final class TimberRenderer
{
    public function __construct(private string $variant = 'v2025') {}

    public function render(Profile $profile): void
    {
        if (!class_exists('Timber\\Timber')) {
            // Fallback: minimal echo if Timber is not present
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8">';
            echo '<title>' . htmlspecialchars($profile->title) . '</title>';
            echo '<h1>' . htmlspecialchars($profile->name) . '</h1>';
            return;
        }

        // Register plugin templates location while allowing theme overrides
        $base = trailingslashit(\MINISITE_PLUGIN_DIR) . 'templates/timber';
        \Timber\Timber::$locations = array_values(array_unique(array_merge(\Timber\Timber::$locations ?? [], [$base])));

        // Pass the entity directly; use properties in Twig (no additional mapping)
        $context = [
            'profile' => $profile,
        ];

        \Timber\Timber::render([
            $this->variant . '/profile.twig',
            'default/profile.twig',
        ], $context);
    }
}

