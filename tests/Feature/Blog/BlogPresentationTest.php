<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use App\Services\Blog\BlogSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Blog layout and advertising: site-wide defaults, and per-post overrides.
 */
class BlogPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'post_type' => 'post',
            'title' => 'Blake blitz',
            'slug' => 'blake-blitz',
            'excerpt' => 'An 88 off 44.',
            'content' => '<h2>Chase</h2><p>Royal Strikers made 185/4.</p>',
            'status' => 'publish',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    #[Test]
    public function the_sidebar_defaults_to_the_right_and_can_be_moved_or_switched_off(): void
    {
        $post = $this->makePost();
        $settings = app(BlogSettings::class);

        $this->assertSame('right', $settings->sidebarPosition());

        add_setting('blog_sidebar_position', 'left');
        $this->assertSame('left', app(BlogSettings::class)->sidebarPosition());
        // order-2 on the article is what puts the sidebar on the left at desktop widths.
        $this->get('/blog/' . $post->slug)->assertOk()->assertSee('lg:order-2', false);

        add_setting('blog_sidebar_position', 'none');
        $this->get('/blog/' . $post->slug)->assertOk()->assertDontSee('<aside', false);
    }

    #[Test]
    public function a_post_overrides_the_site_sidebar_setting(): void
    {
        add_setting('blog_sidebar_position', 'right');
        $post = $this->makePost(['meta' => ['blog' => ['sidebar' => 'none']]]);

        $this->assertSame('none', app(BlogSettings::class)->sidebarPosition($post));
        $this->get('/blog/' . $post->slug)->assertOk()->assertDontSee('<aside', false);

        // The rest of the blog is untouched.
        $this->assertSame('right', app(BlogSettings::class)->sidebarPosition());
    }

    #[Test]
    public function ads_are_off_until_switched_on_and_then_render_in_their_slots(): void
    {
        $post = $this->makePost();
        add_setting('blog_ad_top', '<div id="ad-top">BANNER</div>');

        // The code being present is not consent to show it.
        $this->get('/blog/' . $post->slug)->assertOk()->assertDontSee('ad-top', false);

        add_setting('blog_ads_enabled', '1');
        $this->get('/blog/' . $post->slug)->assertOk()->assertSee('<div id="ad-top">BANNER</div>', false);
    }

    #[Test]
    public function a_post_can_hide_or_replace_the_advertising(): void
    {
        add_setting('blog_ads_enabled', '1');
        add_setting('blog_ad_top', '<div>SITE BANNER</div>');

        $hidden = $this->makePost(['slug' => 'quiet', 'meta' => ['blog' => ['ads' => 'off']]]);
        $this->get('/blog/' . $hidden->slug)->assertOk()->assertDontSee('SITE BANNER', false);

        $sponsored = $this->makePost(['slug' => 'sponsored', 'meta' => ['blog' => ['ad_top' => '<div>THIS POST ONLY</div>']]]);
        $this->get('/blog/' . $sponsored->slug)->assertOk()
            ->assertSee('THIS POST ONLY', false)
            ->assertDontSee('SITE BANNER', false);
    }

    #[Test]
    public function inherit_is_stored_as_absence_so_the_post_follows_later_changes(): void
    {
        $post = $this->makePost(['meta' => ['blog' => ['sidebar' => 'left']]]);
        $settings = app(BlogSettings::class);

        $settings->applyPostOverrides($post, ['sidebar' => 'inherit', 'ads' => 'inherit']);
        $post->save();

        /*
         * Storing the word "inherit" would freeze the post against future changes to the global
         * setting — absence is what inheritance actually means.
         */
        $this->assertSame([], $post->fresh()->meta['blog']);

        add_setting('blog_sidebar_position', 'none');
        $this->assertSame('none', app(BlogSettings::class)->sidebarPosition($post->fresh()));
    }

    #[Test]
    public function the_sidebar_lists_other_posts_but_never_the_one_being_read(): void
    {
        $this->makePost(['slug' => 'older', 'title' => 'An older match', 'published_at' => now()->subWeek()]);
        $current = $this->makePost(['slug' => 'current', 'title' => 'Todays match']);

        $html = $this->get('/blog/' . $current->slug)->assertOk()->getContent();

        /*
         * Scoped to the <aside>, deliberately. The current post's own URL is all over the page
         * — canonical, og:url — so asserting against the whole document would be testing the
         * head, not the sidebar.
         */
        preg_match('#<aside\b.*?</aside>#s', $html, $aside);
        $this->assertNotEmpty($aside, 'The sidebar did not render.');

        $this->assertStringContainsString('An older match', $aside[0]);
        $this->assertStringNotContainsString('Todays match', $aside[0], 'The post being read must not be offered as somewhere else to go.');
    }

    #[Test]
    public function a_dark_logo_is_inverted_for_dark_mode_and_a_light_one_is_left_alone(): void
    {
        $settings = app(BlogSettings::class);

        /*
         * The reason this is measured rather than inferred from the file names: this install's
         * site_logo_dark is a differently-named but byte-identical copy of the light one, so a
         * name check concludes a dark variant exists and then renders a black wordmark on a
         * black header.
         */
        $dark = $this->writeLogo('dark-mark.png', 0, 0, 0);
        $light = $this->writeLogo('light-mark.png', 255, 255, 255);

        $this->assertTrue($settings->isDarkArtwork($dark), 'A black wordmark must be flipped to be seen on a dark header.');
        $this->assertFalse($settings->isDarkArtwork($light), 'A logo already made for dark backgrounds must be left alone.');

        // Missing artwork fails safe: a light mark inverted looks wrong, a dark one left alone
        // is invisible, so the unknown case assumes dark ink.
        $this->assertTrue($settings->isDarkArtwork('/images/logo/does-not-exist.png'));
    }

    #[Test]
    public function the_header_flips_the_logo_only_when_the_artwork_needs_it(): void
    {
        $post = $this->makePost();

        // Scoped to the <img>, not the document: the class name also appears in the <style>
        // block that defines it, so asserting against the whole page always matches.
        $darkLogoTag = function () use ($post) {
            $html = $this->get('/blog/' . $post->slug)->assertOk()->getContent();
            preg_match('#<img[^>]*site-logo-dark[^>]*>#', $html, $m);
            $this->assertNotEmpty($m, 'No dark-mode logo was rendered.');

            return $m[0];
        };

        config(['settings.site_logo_lite' => $this->writeLogo('lite.png', 10, 10, 10)]);
        config(['settings.site_logo_dark' => null]);
        $this->assertStringContainsString('invert-on-dark', $darkLogoTag());

        config(['settings.site_logo_dark' => $this->writeLogo('proper-dark.png', 250, 250, 250)]);
        $this->assertStringNotContainsString('invert-on-dark', $darkLogoTag());
    }

    /** @var array<int, string> Absolute paths written by writeLogo(), removed in tearDown. */
    private array $writtenLogos = [];

    /*
     * These have to be real files under public/ — the detector reads pixels, so Storage::fake()
     * cannot stand in for them. Cleaning up is therefore the test's own job; left behind they
     * show up as untracked files in the repository.
     */
    protected function tearDown(): void
    {
        foreach ($this->writtenLogos as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->writtenLogos = [];

        parent::tearDown();
    }

    /** A one-colour PNG under public/, returned as the root-relative path the settings hold. */
    private function writeLogo(string $name, int $r, int $g, int $b): string
    {
        $dir = public_path('images/logo');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        imagepng($image, $dir . '/' . $name);
        imagedestroy($image);

        $this->writtenLogos[] = $dir . '/' . $name;

        return '/images/logo/' . $name;
    }

    #[Test]
    public function the_theme_toggle_is_present_and_applies_before_the_page_paints(): void
    {
        $post = $this->makePost();
        $html = $this->get('/blog/' . $post->slug)->assertOk()->getContent();

        $this->assertStringContainsString('toggleBlogTheme()', $html);
        $this->assertStringContainsString('theme-toggle', $html);

        /*
         * The stored choice has to be applied in <head>, before the body renders. Read after
         * paint, the page shows the system theme and then corrects itself — a flash that is
         * worse than having no toggle.
         */
        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertStringContainsString("localStorage.getItem('blog-theme')", $head);
        $this->assertStringContainsString("setAttribute('data-theme'", $head);

        /*
         * The dark tokens must exist under an explicit [data-theme="dark"] as well as under the
         * media query, and the media query must be guarded — otherwise choosing LIGHT on a
         * system set to dark changes nothing and the toggle only works one way.
         */
        $this->assertStringContainsString(':root[data-theme="dark"]', $html);
        $this->assertStringContainsString(':root:not([data-theme="light"])', $html);
    }

    #[Test]
    public function a_draft_never_appears_in_the_sidebar(): void
    {
        $this->makePost(['slug' => 'secret', 'title' => 'Unreleased scoop', 'status' => 'draft', 'published_at' => null]);
        $current = $this->makePost(['slug' => 'current']);

        $this->get('/blog/' . $current->slug)->assertOk()->assertDontSee('Unreleased scoop');
    }
}
