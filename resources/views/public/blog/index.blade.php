@extends('public.blog.layout')

@section('title', config('app.name') . ' Blog')
@section('meta_description', 'Match reports, results and news.')

@section('content')
    <header class="mb-10">
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">{{ __('Latest posts') }}</h1>
        <p class="mt-2 muted">{{ __('Match reports, results and news.') }}</p>
    </header>

    @forelse($posts as $index => $post)
        @php
            // The newest post leads: a bigger heading and its picture, so the page has a front
            // page rather than a uniform list.
            $isLead = $index === 0 && $posts->currentPage() === 1;
        @endphp
        <article class="py-7 {{ $loop->first ? 'pt-0' : '' }} {{ $loop->last ? '' : 'border-b rule' }}">
            <a href="{{ route('public.blog.show', $post->slug) }}" class="group block">
                <div class="@if($isLead && $post->featured_image) grid sm:grid-cols-5 gap-6 items-start @endif">
                    @if($isLead && $post->featured_image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($post->featured_image) }}" alt=""
                             class="sm:col-span-2 w-full rounded-xl border rule">
                    @endif
                    <div class="@if($isLead && $post->featured_image) sm:col-span-3 @endif">
                        <p class="text-xs muted mb-2">{{ optional($post->published_at ?? $post->created_at)->format('j M Y') }}</p>
                        <h2 class="{{ $isLead ? 'text-2xl sm:text-3xl' : 'text-xl' }} font-bold leading-snug tracking-tight group-hover:opacity-70 transition">
                            {{ $post->title }}
                        </h2>
                        @if($post->excerpt)
                            <p class="mt-2.5 leading-relaxed muted">{{ $post->excerpt }}</p>
                        @endif
                        <span class="inline-block mt-3 text-sm font-semibold" style="color: var(--accent)">{{ __('Read more') }} &rarr;</span>
                    </div>
                </div>
            </a>
        </article>
    @empty
        <p class="muted">{{ __('Nothing published yet.') }}</p>
    @endforelse

    @if($posts->hasPages())
        <div class="mt-10 pt-8 border-t rule">{{ $posts->links() }}</div>
    @endif
@endsection
