<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public blog at /blog and /blog/{slug}.
 *
 * Only published posts are served. A draft is visible to nobody, including by guessing its slug —
 * a generated match report sits in draft until an editor has read it.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->where('post_type', 'post')
            ->publiclyVisible()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('public.blog.index', ['posts' => $posts]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->where('post_type', 'post')
            ->where('slug', $slug)
            ->publiclyVisible()
            ->firstOrFail();

        return view('public.blog.show', ['post' => $post]);
    }
}
