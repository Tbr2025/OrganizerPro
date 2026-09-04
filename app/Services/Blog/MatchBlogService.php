<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Models\MatchReport;
use App\Models\Matches;
use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Turning a generated draft into the blog post at /blog/{match-name}-{date}.
 *
 * The post is an ordinary `post` record, not a type of its own, so the blog list and the post
 * editor that already exist can edit it with no extra UI. What ties it back to the match is the
 * match_reports row plus a `meta` stamp, which is also how a regenerate finds the post to
 * overwrite instead of leaving a second copy at a -2 slug.
 */
class MatchBlogService
{
    /**
     * The public slug: the match, then the date it was played.
     *
     * Two sides can meet more than once in a season, so the date is what makes this unique and
     * readable; falling back to the created date keeps an unscheduled match from colliding with
     * every other unscheduled one.
     */
    public function slugFor(Matches $match): string
    {
        $name = trim((string) $match->name) !== ''
            ? $match->name
            : trim(($match->teamA?->name ?? 'team-a') . ' vs ' . ($match->teamB?->name ?? 'team-b'));

        $date = $match->match_date?->format('Y-m-d') ?? $match->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        return Str::slug($name . '-' . $date);
    }

    /**
     * Create or overwrite the post for a report.
     *
     * @param  array{title: string, excerpt: string, content: string, model: string}  $draft
     */
    public function publish(MatchReport $report, array $draft, string $status = 'draft'): Post
    {
        $match = $report->match;
        $post = $report->post;

        $attributes = [
            'post_type' => 'post',
            'title' => $draft['title'] !== '' ? $draft['title'] : $this->slugFor($match),
            'excerpt' => $draft['excerpt'],
            'content' => $draft['content'],
            'status' => $status,
            'meta' => array_merge(is_array($post?->meta) ? $post->meta : [], [
                'match_id' => $match?->id,
                'tournament_id' => $match?->tournament_id,
                'source' => 'cricheroes_pdf',
                'ai_model' => $draft['model'],
                'ai_generated_at' => now()->toIso8601String(),
            ]),
        ];

        if ($post) {
            // An editor may have published it and fixed the wording; regenerating replaces the
            // body they asked to have replaced, but never silently unpublishes the page.
            unset($attributes['status']);
            $post->fill($attributes);
            $post->save();
        } else {
            $attributes['slug'] = $this->uniqueSlug($this->slugFor($match));
            $attributes['user_id'] = auth()->id();
            $attributes['published_at'] = $status === 'publish' ? now() : null;
            $post = Post::create($attributes);

            $report->post()->associate($post);
        }

        $report->model = $draft['model'];
        $report->generated_at = now();
        $report->save();

        return $post;
    }

    /** Never steal a slug that already belongs to another post. */
    private function uniqueSlug(string $slug): string
    {
        $base = $slug !== '' ? $slug : 'match-report';
        $candidate = $base;
        $suffix = 2;

        while (Post::where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
