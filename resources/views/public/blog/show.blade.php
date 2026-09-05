@extends('public.blog.layout', ['sidebarPost' => $post])

@section('title', $post->title . ' | ' . config('app.name'))
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 155))
@section('og_type', 'article')
@if($post->featured_image)
    @section('og_image', \Illuminate\Support\Facades\Storage::url($post->featured_image))
@endif

@section('content')
    @php
        // ~200 words a minute, rounded up — a rough courtesy, not a measurement.
        $readingMinutes = max(1, (int) ceil(str_word_count(strip_tags($post->content)) / 200));
        $blogSettings = app(\App\Services\Blog\BlogSettings::class);
    @endphp

    <article>
        <nav class="mb-6">
            <a href="{{ route('public.blog.index') }}" class="text-sm muted hover:opacity-70 transition">&larr; {{ __('All posts') }}</a>
        </nav>

        <header class="mb-8">
            <h1 class="text-3xl sm:text-4xl lg:text-[2.75rem] font-extrabold leading-[1.15] tracking-tight">{{ $post->title }}</h1>

            @if($post->excerpt)
                <p class="mt-4 text-lg leading-relaxed muted">{{ $post->excerpt }}</p>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm muted">
                <time datetime="{{ optional($post->published_at ?? $post->created_at)->toDateString() }}">
                    {{ optional($post->published_at ?? $post->created_at)->format('j F Y') }}
                </time>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $readingMinutes }} {{ __('min read') }}</span>
                @if($post->user?->name)
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ $post->user->name }}</span>
                @endif
            </div>
        </header>

        @if($post->featured_image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($post->featured_image) }}" alt="{{ $post->title }}"
                 class="w-full rounded-2xl border rule mb-10">
        @endif

        {{-- The body is HTML written by the model and stripped to a small tag set on the way in
             (BlogGenerationService::sanitiseHtml), which is what lets it be echoed unescaped. --}}
        <div class="article">
            {!! $post->content !!}
        </div>

        <div class="ad-slot">{!! $blogSettings->ad('in_content', $post) !!}</div>

        <footer class="mt-12 pt-8 border-t rule">
            <a href="{{ route('public.blog.index') }}" class="text-sm font-semibold hover:opacity-70 transition" style="color: var(--accent)">
                &larr; {{ __('Back to all posts') }}
            </a>
        </footer>
    </article>
@endsection
