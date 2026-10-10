<?php

declare(strict_types=1);

namespace App\Services\Poster\Presets;

use Illuminate\Support\Facades\Storage;

/**
 * The colour themes every preset can be applied in, each with a ready-made
 * background for every canvas shape.
 *
 * Backgrounds ship with the code (resources/preset-backgrounds, built by
 * `php artisan presets:backgrounds`) so a fresh server has them without any
 * upload. They are copied to the public disk when used — a template gets its
 * OWN copy, because replacing a template's background deletes the old file.
 */
class PresetThemes
{
    /** Canvas shapes a background exists for: key => [width, height]. */
    public const ASPECTS = [
        'portrait' => [1080, 1350],
        'square' => [1080, 1080],
        'landscape' => [1600, 910],
        'wide' => [1920, 1080],
    ];

    /** @return array<string, array{key:string,name:string,from:string,to:string,glow:string,ink:string,accent:string,muted:string,panel:string,panelInk:string,light:bool,rowA:string,rowB:string,onAccent:string}> */
    public static function all(): array
    {
        $themes = [
            ['key' => 'midnight', 'name' => 'Midnight Gold', 'from' => '#070d24', 'to' => '#1e3a8a', 'glow' => '#3b82f6', 'ink' => '#ffffff', 'accent' => '#f5b301', 'muted' => '#b6c3e3', 'panel' => 'rgba(255,255,255,0.10)', 'panelInk' => '#ffffff', 'rowA' => '#0f1d45', 'rowB' => '#162a5e', 'onAccent' => '#0b1530', 'light' => false],
            ['key' => 'stadium', 'name' => 'Stadium Green', 'from' => '#03130b', 'to' => '#14532d', 'glow' => '#22c55e', 'ink' => '#ffffff', 'accent' => '#a3e635', 'muted' => '#bbd8c6', 'panel' => 'rgba(255,255,255,0.10)', 'panelInk' => '#ffffff', 'rowA' => '#0a2a18', 'rowB' => '#103a22', 'onAccent' => '#052012', 'light' => false],
            ['key' => 'sunset', 'name' => 'Sunset Fire', 'from' => '#1c0603', 'to' => '#c2410c', 'glow' => '#fb923c', 'ink' => '#ffffff', 'accent' => '#fde047', 'muted' => '#f5c9b0', 'panel' => 'rgba(0,0,0,0.28)', 'panelInk' => '#ffffff', 'rowA' => '#4a1407', 'rowB' => '#5e1c0a', 'onAccent' => '#2a0a03', 'light' => false],
            ['key' => 'royal', 'name' => 'Royal Purple', 'from' => '#12061f', 'to' => '#5b21b6', 'glow' => '#a855f7', 'ink' => '#ffffff', 'accent' => '#f9a8d4', 'muted' => '#d6c4f0', 'panel' => 'rgba(255,255,255,0.10)', 'panelInk' => '#ffffff', 'rowA' => '#22103d', 'rowB' => '#2d1650', 'onAccent' => '#14061f', 'light' => false],
            ['key' => 'crimson', 'name' => 'Crimson Night', 'from' => '#0e0306', 'to' => '#9f1239', 'glow' => '#f43f5e', 'ink' => '#ffffff', 'accent' => '#fbbf24', 'muted' => '#efc1cc', 'panel' => 'rgba(255,255,255,0.10)', 'panelInk' => '#ffffff', 'rowA' => '#2a0812', 'rowB' => '#3a0c19', 'onAccent' => '#140306', 'light' => false],
            ['key' => 'classic', 'name' => 'Classic Light', 'from' => '#f8fafc', 'to' => '#dbeafe', 'glow' => '#93c5fd', 'ink' => '#0f172a', 'accent' => '#1d4ed8', 'muted' => '#475569', 'panel' => 'rgba(15,23,42,0.06)', 'panelInk' => '#0f172a', 'rowA' => '#ffffff', 'rowB' => '#eef4ff', 'onAccent' => '#ffffff', 'light' => true],
        ];

        return collect($themes)->keyBy('key')->all();
    }

    public static function find(?string $key): ?array
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }

    /** The shape whose background suits a canvas of this size. */
    public static function aspectFor(int $width, int $height): string
    {
        $ratio = $width / max(1, $height);

        return match (true) {
            $ratio >= 1.7 => 'wide',
            $ratio >= 1.4 => 'landscape',
            $ratio >= 0.95 => 'square',
            default => 'portrait',
        };
    }

    /** The shipped file for a theme + shape (absolute path), or null. */
    public static function sourcePath(string $theme, string $aspect): ?string
    {
        $path = resource_path("preset-backgrounds/{$theme}-{$aspect}.jpg");

        return is_file($path) ? $path : null;
    }

    /** A NEW public-disk copy for one template (path on the public disk), or null. */
    public static function copyForTemplate(string $theme, int $width, int $height): ?string
    {
        $source = self::sourcePath($theme, self::aspectFor($width, $height));
        if (! $source) {
            return null;
        }

        $path = 'tournament_templates/' . uniqid('preset_bg_') . '.jpg';
        Storage::disk('public')->put($path, file_get_contents($source));

        return $path;
    }

    /** A shared, read-only public URL for screens (the LED wall), copied once. */
    public static function publicUrl(string $theme, string $aspect): ?string
    {
        $source = self::sourcePath($theme, $aspect);
        if (! $source) {
            return null;
        }

        $path = "preset-backgrounds/{$theme}-{$aspect}-" . substr(md5_file($source), 0, 8) . '.jpg';
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, file_get_contents($source));
        }

        return Storage::url($path);
    }
}
