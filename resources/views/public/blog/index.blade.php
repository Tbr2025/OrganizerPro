@extends('public.blog.layout')

@section('title', config('app.name') . ' Blog')
@section('meta_description', 'Match reports, results and news.')

@section('content')
    @php
        /*
         * The newest post on page one leads, full width with a large image; everything else is
         * an even grid. Split up front rather than opening a <div> mid-loop — that version
         * silently left page two with no grid at all, because the opening tag lived inside the
         * branch that only ran for the lead.
         */
        $items = $posts->getCollection();
        $lead = $posts->currentPage() === 1 ? $items->first() : null;
        $rest = $lead ? $items->slice(1) : $items;
    @endphp

    <header class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">{{ __('Latest posts') }}</h1>
        <p class="mt-2 muted">{{ __('Match reports, results and news.') }}</p>
    </header>

    @if($lead)
        <article class="mb-10">
            <a href="{{ route('public.blog.show', $lead->slug) }}" class="group block">
                <div class="aspect-[16/9] w-full rounded-2xl overflow-hidden border rule {{ $lead->featuredImageUrl() ? '' : 'thumb-fallback' }}">
                    @if($lead->featuredImageUrl())
                        <img src="{{ $lead->featuredImageUrl() }}" alt="" loading="eager"
                             class="w-full h-full object-cover group-hover:scale-[1.02] transition duration-500">
                    @endif
                </div>
                <p class="text-xs muted mt-4">{{ optional($lead->published_at ?? $lead->created_at)->format('j M Y') }}</p>
                <h2 class="mt-1.5 text-2xl sm:text-3xl font-bold leading-snug tracking-tight group-hover:opacity-70 transition">
                    {{ $lead->title }}
                </h2>
                @if($lead->excerpt)
                    <p class="mt-2.5 leading-relaxed muted">{{ $lead->excerpt }}</p>
                @endif
            </a>
        </article>

        @if($rest->isNotEmpty())
            <div class="border-t rule mb-8"></div>
        @endif
    @endif

    @if($rest->isNotEmpty())
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-7 gap-y-10">
            @foreach($rest as $post)
                @php $image = $post->featuredImageUrl(); @endphp
                <article>
                    <a href="{{ route('public.blog.show', $post->slug) }}" class="group block h-full">
                        {{-- A fixed aspect box either way: a card with no picture still has the
                             shape of one, instead of a ragged grid of mismatched heights. --}}
                        <div class="aspect-[16/10] w-full rounded-xl overflow-hidden border rule {{ $image ? '' : 'thumb-fallback' }}">
                            @if($image)
                                <img src="{{ $image }}" alt="" loading="lazy"
                                     class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-500">
                            @endif
                        </div>
                        <p class="text-xs muted mt-3">{{ optional($post->published_at ?? $post->created_at)->format('j M Y') }}</p>
                        <h2 class="mt-1 text-lg font-bold leading-snug tracking-tight group-hover:opacity-70 transition">
                            {{ $post->title }}
                        </h2>
                        @if($post->excerpt)
                            <p class="mt-1.5 text-sm leading-relaxed muted">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
                        @endif
                    </a>
                </article>
            @endforeach
        </div>
    @endif

    @if(! $lead && $rest->isEmpty())
        <p class="muted">{{ __('Nothing published yet.') }}</p>
    @endif

    @if($posts->hasPages())
        <div class="mt-12 pt-8 border-t rule">{{ $posts->links() }}</div>
    @endif
@endsection
