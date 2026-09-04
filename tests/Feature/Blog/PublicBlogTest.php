<?php

declare(strict_types=1);

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /blog and /blog/{slug}.
 *
 * A generated match report lands as a draft, so the thing that matters most here is that a draft
 * is invisible to the public even when its slug is known.
 */
class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'post_type' => 'post',
            'title' => 'Blake blitz',
            'slug' => 'royal-strikers-vs-thunder-kings-2026-09-04',
            'excerpt' => 'An 88 off 44.',
            'content' => '<h2>A one-sided chase</h2><p>Royal Strikers made 185/4.</p>',
            'status' => 'publish',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    #[Test]
    public function a_published_post_is_served_at_its_slug(): void
    {
        $post = $this->makePost();

        $this->get('/blog/' . $post->slug)
            ->assertOk()
            ->assertSee('Blake blitz')
            ->assertSee('A one-sided chase', false);

        $this->get('/blog')->assertOk()->assertSee('Blake blitz');
    }

    #[Test]
    public function a_draft_is_invisible_even_to_someone_who_knows_the_slug(): void
    {
        $post = $this->makePost(['status' => 'draft', 'published_at' => null]);

        $this->get('/blog/' . $post->slug)->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee('Blake blitz');
    }

    #[Test]
    public function a_post_dated_in_the_future_waits(): void
    {
        $post = $this->makePost(['published_at' => now()->addWeek()]);

        $this->get('/blog/' . $post->slug)->assertNotFound();
    }

    #[Test]
    public function a_page_is_not_a_blog_post(): void
    {
        $post = $this->makePost(['post_type' => 'page', 'slug' => 'about-us']);

        $this->get('/blog/' . $post->slug)->assertNotFound();
    }

    #[Test]
    public function the_article_body_renders_as_html_not_as_escaped_text(): void
    {
        $post = $this->makePost();

        // The body is sanitised on the way in, which is what lets it be echoed unescaped.
        $this->get('/blog/' . $post->slug)
            ->assertOk()
            ->assertSee('<h2>A one-sided chase</h2>', false);
    }
}
