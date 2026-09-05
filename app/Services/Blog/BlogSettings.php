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

    private function flag(string $name, bool $default): bool
    {
        $value = get_setting($name);

        return $value === null || $value === '' ? $default : (bool) $value;
    }
}
