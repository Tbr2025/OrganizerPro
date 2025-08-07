<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;

class PostService
{
    /**
     * Get posts with filters
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPosts(array $filters = [])
    {
        // Set default post type if not provided.
        if (! isset($filters['post_type'])) {
            $filters['post_type'] = 'post';
        }

        // Create base query with post type filter.
        $query = Post::where('post_type', $filters['post_type'])
            ->with(['user', 'terms']);

        // Handle category filter separately.
        if (isset($filters['category']) && ! empty($filters['category'])) {
            $query->filterByCategory($filters['category']);
            unset($filters['category']); // Remove to prevent double filtering
        }

        // Handle tag filter separately.
        if (isset($filters['tag']) && ! empty($filters['tag'])) {
            $query->filterByTag($filters['tag']);
            unset($filters['tag']); // Remove to prevent double filtering
        }

        $query = $query->applyFilters($filters);

        return $query->paginateData([
            'per_page' => config('settings.default_pagination') ?? 10,
        ]);
    }

    /**
     * Get a post by ID.
     */
    public function getPostById(?int $id, ?string $postType = null): ?Post
    {
        if (empty($id)) {
            return null;
        }

        $query = Post::query();

        if ($postType) {
            $query->where('post_type', $postType)
                ->with(['user', 'terms']);
        }

        return $query->findOrFail($id);
    }

    /**
     * Get paginated posts with filters
     */
    public function getPaginatedPosts(array $filters = [], int $perPage = 10)
    {
        // Set default post type if not provided.
        if (! isset($filters['post_type'])) {
            $filters['post_type'] = 'post';
        }

        // Create base query with post type filter.
        $query = Post::where('post_type', $filters['post_type'])
            ->with(['author', 'terms']);

        // Handle category filter separately.
        if (isset($filters['category']) && ! empty($filters['category'])) {
            $query->filterByCategory($filters['category']);
            unset($filters['category']);
        }

        // Handle tag filter separately.
        if (isset($filters['tag']) && ! empty($filters['tag'])) {
            $query->filterByTag($filters['tag']);
            unset($filters['tag']);
        }

        $query = $query->applyFilters($filters);

        return $query->paginate($perPage);
    }

    /**
     * Create a new post
     */
    public function createPost(array $data): Post
    {
        $post = Post::create([
            'title' => $data['title'],
            'slug' => $data['slug'] ?? str()->slug($data['title']),
            'content' => $data['content'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'featured_image' => $data['featured_image'] ?? null,
            'post_type' => $data['post_type'] ?? 'post',
            'status' => $data['status'] ?? 'draft',
            'published_at' => $data['published_at'] ?? null,
            'author_id' => $data['author_id'],
        ]);

        // Sync terms if provided
        if (isset($data['terms']) && ! empty($data['terms'])) {
            $post->terms()->sync($data['terms']);
        }

        // Handle post meta if provided
        if (isset($data['meta']) && ! empty($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $post->postMeta()->updateOrCreate(
                    ['meta_key' => $key],
                    ['meta_value' => $value]
                );
            }
        }

        return $post->load(['author', 'terms']);
    }

    /**
     * Update an existing post
     */
    public function updatePost(Post $post, array $data): Post
    {
        $updateData = [
            'title' => $data['title'] ?? $post->title,
            'slug' => $data['slug'] ?? $post->slug,
            'content' => $data['content'] ?? $post->content,
            'excerpt' => $data['excerpt'] ?? $post->excerpt,
            'featured_image' => $data['featured_image'] ?? $post->featured_image,
            'status' => $data['status'] ?? $post->status,
            'published_at' => $data['published_at'] ?? $post->published_at,
        ];

        $post->update($updateData);

        // Sync terms if provided
        if (isset($data['terms'])) {
            $post->terms()->sync($data['terms']);
        }

        // Handle post meta if provided
        if (isset($data['meta']) && ! empty($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                $post->postMeta()->updateOrCreate(
                    ['meta_key' => $key],
                    ['meta_value' => $value]
                );
            }
        }

        return $post->load(['author', 'terms']);
    }
}
