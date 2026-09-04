@extends('public.blog.layout')

@section('title', 'Blog | ' . config('app.name'))
@section('meta_description', 'Match reports, results and news.')

@section('content')
    <h1 class="text-3xl font-extrabold text-white mb-8">Latest posts</h1>

    @forelse($posts as $post)
        <article class="mb-8 pb-8 border-b border-white/10 last:border-0">
            <a href="{{ route('public.blog.show', $post->slug) }}" class="group block">
                <h2 class="text-xl font-bold text-white group-hover:text-red-400 transition">{{ $post->title }}</h2>
                <p class="text-xs text-gray-500 mt-1">
                    {{ optional($post->published_at ?? $post->created_at)->format('j M Y') }}
                </p>
                @if($post->excerpt)
                    <p class="text-gray-400 mt-3 leading-relaxed">{{ $post->excerpt }}</p>
                @endif
                <span class="inline-block mt-3 text-sm font-medium text-red-400">Read more &rarr;</span>
            </a>
        </article>
    @empty
        <p class="text-gray-400">Nothing published yet.</p>
    @endforelse

    <div class="mt-8">
        {{ $posts->links() }}
    </div>
@endsection
