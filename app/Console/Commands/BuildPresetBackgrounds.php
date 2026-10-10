<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Poster\Presets\PresetBackgroundGenerator;
use App\Services\Poster\Presets\PresetThemes;
use Illuminate\Console\Command;

/**
 * Regenerate the shipped theme backgrounds (resources/preset-backgrounds).
 * Run after changing a theme's colours; the results are committed.
 */
class BuildPresetBackgrounds extends Command
{
    protected $signature = 'presets:backgrounds {--theme= : Only this theme}';

    protected $description = 'Build the preset theme backgrounds into resources/preset-backgrounds';

    public function handle(PresetBackgroundGenerator $generator): int
    {
        $dir = resource_path('preset-backgrounds');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (PresetThemes::all() as $key => $theme) {
            if ($this->option('theme') && $this->option('theme') !== $key) {
                continue;
            }
            foreach (PresetThemes::ASPECTS as $aspect => [$w, $h]) {
                $img = $generator->render($theme, $w, $h);
                imagejpeg($img, "{$dir}/{$key}-{$aspect}.jpg", 86);
                imagedestroy($img);
                $this->line("{$key}-{$aspect}.jpg");
            }
        }

        return self::SUCCESS;
    }
}
