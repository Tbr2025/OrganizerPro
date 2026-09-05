{{-- Standalone: the blog is not tournament-scoped, so it cannot reuse
     public.tournament.layouts.app, which requires a $tournament. --}}
@php
    $blogSettings = app(\App\Services\Blog\BlogSettings::class);
    $sidebarPost = $sidebarPost ?? null;
    $sidebarPosition = $blogSettings->sidebarPosition($sidebarPost);
    $hasSidebar = $sidebarPosition !== 'none';

    $recent = $hasSidebar && $blogSettings->showsRecentPosts()
        ? \App\Models\Post::query()->where('post_type', 'post')->publiclyVisible()
            ->when($sidebarPost, fn ($q) => $q->whereKeyNot($sidebarPost->getKey()))
            ->orderByDesc('published_at')->orderByDesc('created_at')->limit(5)->get()
        : collect();
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name') . ' Blog')</title>
    <meta name="description" content="@yield('meta_description', 'Match reports and news')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', config('app.name') . ' Blog')">
    <meta property="og:description" content="@yield('meta_description', 'Match reports and news')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif

    {{-- Applied before anything paints. Reading localStorage after the body renders means the
         page flashes the system theme and then corrects itself, which is worse than not having
         a toggle at all. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('blog-theme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-theme', saved);
                }
            } catch (e) {
                // Private mode, or storage blocked. The system preference still applies.
            }
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap" rel="stylesheet">
    <style>
        /* Light is the base. The dark values are declared twice on purpose: once for readers
           who have chosen nothing and whose system says dark, and once for readers who picked
           dark here. The media query is guarded so an explicit LIGHT choice still wins on a
           system set to dark — without that guard the toggle only works one way. */
        :root {
            --ink: #16141f; --paper: #ffffff; --muted: #5b6474; --rule: #e6e8ee;
            --accent: #e11d48; --soft: #f7f8fa;
            --watermark: url('{{ config('settings.site_icon') ?: (config('settings.site_logo_lite') ?: '') }}');
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --ink: #e8eaf0; --paper: #12101b; --muted: #98a1b3; --rule: rgba(255,255,255,.09);
                --soft: rgba(255,255,255,.035);
                color-scheme: dark;
            }
        }
        :root[data-theme="dark"] {
            --ink: #e8eaf0; --paper: #12101b; --muted: #98a1b3; --rule: rgba(255,255,255,.09);
            --soft: rgba(255,255,255,.035);
            color-scheme: dark;
        }

        /* Only one of the two icons is ever shown, and which one is a pure CSS consequence of
           the resolved theme — so it is right on first paint with no JavaScript involved. */
        .theme-toggle .icon-moon { display: none; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .theme-toggle .icon-sun { display: none; }
            :root:not([data-theme="light"]) .theme-toggle .icon-moon { display: block; }
        }
        :root[data-theme="dark"] .theme-toggle .icon-sun { display: none; }
        :root[data-theme="dark"] .theme-toggle .icon-moon { display: block; }
        :root[data-theme="light"] .theme-toggle .icon-sun { display: block; }
        :root[data-theme="light"] .theme-toggle .icon-moon { display: none; }

        /* Swapping between two supplied variants. Only rendered when they actually differ. */
        .site-logo-dark { display: none; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .site-logo-lite:not(.invert-on-dark) { display: none; }
            :root:not([data-theme="light"]) .site-logo-dark { display: block; }
        }
        :root[data-theme="dark"] .site-logo-lite:not(.invert-on-dark) { display: none; }
        :root[data-theme="dark"] .site-logo-dark { display: block; }
        :root[data-theme="light"] .site-logo-lite { display: block; }
        :root[data-theme="light"] .site-logo-dark { display: none; }

        /* brightness(0) flattens the artwork to black whatever colour it was, then invert(1)
           makes it white — so any dark mark comes out legible, not just a pure-black one. */
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) .invert-on-dark { filter: brightness(0) invert(1); }
            :root:not([data-theme="light"]) .thumb-fallback { filter: invert(1) hue-rotate(180deg); }
        }
        :root[data-theme="dark"] .invert-on-dark { filter: brightness(0) invert(1); }
        :root[data-theme="dark"] .thumb-fallback { filter: invert(1) hue-rotate(180deg); }
        :root[data-theme="light"] .invert-on-dark { filter: none; }
        :root[data-theme="light"] .thumb-fallback { filter: none; }

        /* Stand-in for a post with no featured image: the site mark, faint, on the page's own
           background — so a card without a picture still has the shape of one, instead of a
           ragged grid of mismatched heights. */
        .thumb-fallback {
            background-color: var(--soft);
            background-image: var(--watermark);
            background-repeat: no-repeat;
            background-position: center;
            background-size: 42% auto;
            opacity: .85;
        }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: var(--paper); color: var(--ink); }
        a { color: inherit; }

        /* The article body is model-written HTML restricted to a small tag set, so the styles it
           can need are equally small. Serif at a generous measure because this is long-form
           reading, not UI. */
        .article { font-family: 'Source Serif 4', Georgia, serif; font-size: 1.125rem; line-height: 1.75; }
        .article > * + * { margin-top: 1.35rem; }
        .article h2 { font-family: 'Inter', sans-serif; font-size: 1.5rem; font-weight: 700; line-height: 1.3;
                      margin-top: 2.75rem; letter-spacing: -.01em; }
        .article h3 { font-family: 'Inter', sans-serif; font-size: 1.175rem; font-weight: 600; margin-top: 2rem; }
        .article p { color: var(--ink); opacity: .92; }
        .article ul, .article ol { padding-left: 1.35rem; }
        .article ul { list-style: disc; } .article ol { list-style: decimal; }
        .article li + li { margin-top: .4rem; }
        .article strong { font-weight: 600; }
        .article em { font-style: italic; opacity: .85; }
        .article blockquote { border-left: 3px solid var(--accent); padding-left: 1.15rem; font-style: italic; color: var(--muted); }
        .article a { color: var(--accent); text-decoration: underline; text-underline-offset: 2px; }

        /* Figures are inserted after sanitising, so the markup is fixed: one <img>, one caption.
           A crest is far smaller than a poster, so the cap is on height as well as width — a
           200px logo stretched across the column looks like a mistake. */
        .article figure { margin: 2.25rem 0; text-align: center; }
        .article figure img { max-width: 100%; max-height: 30rem; width: auto; height: auto;
                              border-radius: .875rem; border: 1px solid var(--rule); }
        .article figcaption { margin-top: .6rem; font-family: 'Inter', sans-serif; font-size: .8rem; color: var(--muted); }

        .rule { border-color: var(--rule); }
        .muted { color: var(--muted); }
        .ad-slot { margin: 2rem 0; }
        .ad-slot:empty { display: none; }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen antialiased">
    <header class="border-b rule sticky top-0 z-30 backdrop-blur" style="background: color-mix(in srgb, var(--paper) 88%, transparent);">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            @php
                $logoLite = config('settings.site_logo_lite');
                $logoDark = config('settings.site_logo_dark');

                /*
                 * On this install both settings point at the same file — a dark wordmark, which
                 * is invisible on a dark header. When no genuinely different dark artwork has
                 * been supplied, the light mark is flipped to white in CSS instead. That is
                 * right for a monochrome wordmark and costs nothing; a site that uploads a real
                 * dark logo gets it used untouched.
                 */
                $hasDistinctDarkLogo = $logoDark && $logoDark !== $logoLite;
            @endphp
            <a href="{{ route('public.blog.index') }}" class="flex items-center gap-2.5 min-w-0">
                @if($logoLite)
                    <img src="{{ $logoLite }}" alt="{{ config('app.name') }}"
                         class="site-logo site-logo-lite h-7 w-auto object-contain @unless($hasDistinctDarkLogo) invert-on-dark @endunless">
                    @if($hasDistinctDarkLogo)
                        <img src="{{ $logoDark }}" alt="{{ config('app.name') }}" class="site-logo site-logo-dark h-7 w-auto object-contain">
                    @endif
                @else
                    <span class="text-lg font-extrabold tracking-tight">{{ config('app.name') }}</span>
                @endif
                <span class="muted font-semibold text-lg">{{ __('Blog') }}</span>
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ url('/') }}" class="text-sm muted hover:opacity-70 transition px-2">{{ __('Home') }} &rarr;</a>

                <button type="button" onclick="toggleBlogTheme()" class="theme-toggle p-2 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition"
                        aria-label="{{ __('Switch between light and dark') }}" title="{{ __('Switch between light and dark') }}">
                    <svg class="icon-sun w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
                    </svg>
                    <svg class="icon-moon w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-10 md:py-14">
        {!! $blogSettings->ad('top', $sidebarPost) !!}

        <div class="@if($hasSidebar) grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 @endif">
            {{-- order-* is what makes a left sidebar left on desktop while staying BELOW the
                 article on a phone, where a column of links above the story is just an obstacle. --}}
            <div class="@if($hasSidebar) lg:col-span-8 {{ $sidebarPosition === 'left' ? 'lg:order-2' : '' }} @endif min-w-0">
                @yield('content')
            </div>

            @if($hasSidebar)
                <aside class="lg:col-span-4 {{ $sidebarPosition === 'left' ? 'lg:order-1' : '' }} space-y-8">
                    @if($blogSettings->sidebarAbout())
                        <section>
                            <h2 class="text-xs font-bold uppercase tracking-wider muted mb-3">{{ __('About') }}</h2>
                            <p class="text-sm leading-relaxed muted">{{ $blogSettings->sidebarAbout() }}</p>
                        </section>
                    @endif

                    @if($recent->isNotEmpty())
                        <section>
                            <h2 class="text-xs font-bold uppercase tracking-wider muted mb-3">{{ $blogSettings->sidebarHeading() }}</h2>
                            <ul class="space-y-4">
                                @foreach($recent as $item)
                                    <li>
                                        <a href="{{ route('public.blog.show', $item->slug) }}" class="group block">
                                            <p class="text-sm font-semibold leading-snug group-hover:opacity-70 transition">{{ $item->title }}</p>
                                            <p class="text-xs muted mt-1">{{ optional($item->published_at ?? $item->created_at)->format('j M Y') }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <div class="ad-slot">{!! $blogSettings->ad('sidebar', $sidebarPost) !!}</div>
                </aside>
            @endif
        </div>

        <div class="ad-slot">{!! $blogSettings->ad('bottom', $sidebarPost) !!}</div>
    </main>

    <footer class="border-t rule mt-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 text-xs muted flex flex-wrap gap-x-4 gap-y-2 justify-between">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
            <a href="{{ route('public.blog.index') }}" class="hover:opacity-70">{{ __('All posts') }}</a>
        </div>
    </footer>
    <script>
        function toggleBlogTheme() {
            var root = document.documentElement;
            var current = root.getAttribute('data-theme');

            // Nothing chosen yet: flip away from whatever the system is showing, so the first
            // click always visibly changes something.
            if (!current) {
                current = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            var next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);

            try {
                localStorage.setItem('blog-theme', next);
            } catch (e) {
                // The choice still applies to this page; it just will not be remembered.
            }
        }
    </script>
</body>
</html>
