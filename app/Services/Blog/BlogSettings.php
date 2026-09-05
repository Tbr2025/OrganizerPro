<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\Post;

/**
 * How a blog page is laid out, and what advertising sits around it.
 *
 * Two levels: a site-wide default, and a per-post override stored in `posts.meta`. A post says
 * "inherit" unless somebody deliberately changed it, so altering the global setting moves every
 * page that has not been given an opinion of its own — which is the point of having a global
 * setting at all.
 *
 * Ad slots hold raw HTML because that is what an ad network hands you. Only a Superadmin may
 * save them (enforced in SettingsController): anyone who can write arbitrary HTML into a public
 * page can run script on every visitor, so this is a narrower gate than settings.edit.
 */
class BlogSettings
{
    public const SIDEBAR_POSITIONS = [
        'right' => 'Right',
        'left' => 'Left',
        'none' => 'No sidebar (full width)',
    ];

    /** Where an ad can appear. Kept as a list so the settings page and the view cannot drift. */
    public const AD_SLOTS = [
        'top' => 'Above the article',
        'bottom' => 'Below the article',
        'in_content' => 'Inside the article',
        'sidebar' => 'In the sidebar',
    ];

    /** Fields that hold raw HTML and are therefore Superadmin-only to save. */
    public const HTML_FIELDS = [
        'blog_ad_top',
        'blog_ad_bottom',
        'blog_ad_in_content',
        'blog_ad_sidebar',
    ];

    // -----------------------------------------------------------------------
    // Layout
    // -----------------------------------------------------------------------

    public function sidebarPosition(?Post $post = null): string
    {
        $override = $this->postMeta($post, 'sidebar');

        if ($override && $override !== 'inherit' && array_key_exists($override, self::SIDEBAR_POSITIONS)) {
            return $override;
        }

        $global = (string) (get_setting('blog_sidebar_position') ?: 'right');

        return array_key_exists($global, self::SIDEBAR_POSITIONS) ? $global : 'right';
    }

    public function showsSidebar(?Post $post = null): bool
    {
        return $this->sidebarPosition($post) !== 'none';
    }

    public function showsRecentPosts(): bool
    {
        return $this->flag('blog_sidebar_recent', true);
    }

    public function sidebarHeading(): string
    {
        return (string) (get_setting('blog_sidebar_heading') ?: 'Latest posts');
    }

    public function sidebarAbout(): string
    {
        return trim((string) get_setting('blog_sidebar_about'));
    }

    // -----------------------------------------------------------------------
    // Advertising
    // -----------------------------------------------------------------------

    public function adsEnabled(?Post $post = null): bool
    {
        $override = $this->postMeta($post, 'ads');

        if ($override === 'off') {
            return false;
        }
        if ($override === 'on') {
            return true;
        }

        return $this->flag('blog_ads_enabled', false);
    }

    /**
     * The markup for one slot, or '' when nothing should render there.
     *
     * A per-post value wins over the global one, so a sponsored article can carry its own
     * banner without disturbing the rest of the blog.
     */
    public function ad(string $slot, ?Post $post = null): string
    {
        if (! array_key_exists($slot, self::AD_SLOTS) || ! $this->adsEnabled($post)) {
            return '';
        }

        $override = trim((string) $this->postMeta($post, 'ad_' . $slot));

        return $override !== '' ? $override : trim((string) get_setting('blog_ad_' . $slot));
    }

    public function hasAnyAd(?Post $post = null): bool
    {
        foreach (array_keys(self::AD_SLOTS) as $slot) {
            if ($this->ad($slot, $post) !== '') {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Per-post storage
    // -----------------------------------------------------------------------

    /**
     * Merge display choices into a post's meta, dropping the ones set to "inherit".
     *
     * Storing "inherit" as a value would freeze the post against future changes to the global
     * setting; absence is what inheritance actually means.
     *
     * @param  array<string, mixed>  $input
     */
    public function applyPostOverrides(Post $post, array $input): void
    {
        $meta = is_array($post->meta) ? $post->meta : [];
        $blog = is_array($meta['blog'] ?? null) ? $meta['blog'] : [];

        foreach (['sidebar', 'ads'] as $key) {
            $value = trim((string) ($input[$key] ?? 'inherit'));
            if ($value === '' || $value === 'inherit') {
                unset($blog[$key]);
            } else {
                $blog[$key] = $value;
            }
        }

        foreach (array_keys(self::AD_SLOTS) as $slot) {
            $value = trim((string) ($input['ad_' . $slot] ?? ''));
            if ($value === '') {
                unset($blog['ad_' . $slot]);
            } else {
                $blog['ad_' . $slot] = $value;
            }
        }

        $meta['blog'] = $blog;
        $post->meta = $meta;
    }

    public function postMeta(?Post $post, string $key): ?string
    {
        if (! $post || ! is_array($post->meta)) {
            return null;
        }

        $blog = $post->meta['blog'] ?? null;

        return is_array($blog) && isset($blog[$key]) ? (string) $blog[$key] : null;
    }

    /**
     * The logo to show on a dark background, and whether it has to be flipped to be seen.
     *
     * Comparing the two configured PATHS is not enough: this install has site_logo_dark pointing
     * at a differently-named but byte-identical copy of the light one, so a name check happily
     * concludes a dark variant exists and then renders a black wordmark on a black header.
     *
     * So the artwork itself is measured. If what would be shown is dark, it is inverted in CSS;
     * if somebody has uploaded a genuinely light logo for dark mode, it is left alone.
     *
     * @return array{url: string, invert: bool}|null
     */
    public function darkModeLogo(): ?array
    {
        $lite = (string) (config('settings.site_logo_lite') ?: '');
        $dark = (string) (config('settings.site_logo_dark') ?: '');
        $chosen = $dark !== '' ? $dark : $lite;

        if ($chosen === '') {
            return null;
        }

        return ['url' => $chosen, 'invert' => $this->isDarkArtwork($chosen)];
    }

    /**
     * Is this image mostly dark ink?
     *
     * Averaged over the visible pixels only — a wordmark is mostly transparent, and counting the
     * empty space would call every logo "light". Cached against the file's mtime so a re-upload
     * is picked up but the pixels are not walked on every request.
     */
    public function isDarkArtwork(string $publicPath): bool
    {
        $file = public_path(ltrim(parse_url($publicPath, PHP_URL_PATH) ?? '', '/'));

        if (! is_file($file) || ! function_exists('imagecreatefromstring')) {
            // Unknowable: assume dark ink, which is the overwhelmingly common case for a logo
            // and the assumption that fails safe — a light mark inverted looks wrong, but a dark
            // one left alone is invisible.
            return true;
        }

        return (bool) cache()->remember(
            'blog:logo-dark:' . md5($file . '|' . filemtime($file)),
            now()->addDay(),
            function () use ($file) {
                $image = @imagecreatefromstring((string) file_get_contents($file));
                if (! $image) {
                    return true;
                }

                $width = imagesx($image);
                $height = imagesy($image);
                $stepX = max(1, (int) ($width / 40));
                $stepY = max(1, (int) ($height / 40));

                $total = 0.0;
                $counted = 0;

                for ($x = 0; $x < $width; $x += $stepX) {
                    for ($y = 0; $y < $height; $y += $stepY) {
                        $rgba = imagecolorat($image, $x, $y);
                        $alpha = ($rgba >> 24) & 0x7F;

                        // 127 is fully transparent; anything near it is not part of the mark.
                        if ($alpha > 100) {
                            continue;
                        }

                        $total += 0.2126 * (($rgba >> 16) & 0xFF)
                            + 0.7152 * (($rgba >> 8) & 0xFF)
                            + 0.0722 * ($rgba & 0xFF);
                        $counted++;
                    }
                }

                imagedestroy($image);

                // No visible pixels at all says nothing; fail safe.
                return $counted === 0 ? true : ($total / $counted) < 128;
            }
        );
    }

    private function flag(string $name, bool $default): bool
    {
        $value = get_setting($name);

        return $value === null || $value === '' ? $default : (bool) $value;
    }
}
