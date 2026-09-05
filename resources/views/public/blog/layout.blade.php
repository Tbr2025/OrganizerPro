{{-- A standalone layout: the blog is not tournament-scoped, so it cannot reuse
     public.tournament.layouts.app, which requires a $tournament. --}}
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Blog')</title>
    <meta name="description" content="@yield('meta_description', 'Match reports and news')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Blog')">
    <meta property="og:description" content="@yield('meta_description', 'Match reports and news')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #12101f; color: #e5e7eb; }
        /* The article body is model-written HTML restricted to a small tag set, so the styles
           it can need are equally small. */
        .article h2 { font-size: 1.5rem; font-weight: 700; color: #fff; margin: 2rem 0 .75rem; }
        .article h3 { font-size: 1.2rem; font-weight: 600; color: #fff; margin: 1.5rem 0 .5rem; }
        .article p { margin: 0 0 1rem; line-height: 1.75; color: #cbd5e1; }
        .article ul, .article ol { margin: 0 0 1rem 1.25rem; color: #cbd5e1; line-height: 1.75; }
        .article ul { list-style: disc; }
        .article ol { list-style: decimal; }
        .article li { margin-bottom: .35rem; }
        .article strong { color: #fff; font-weight: 600; }
        .article em { color: #cbd5e1; font-style: italic; }
        .article blockquote { border-left: 3px solid #ef4444; padding-left: 1rem; margin: 1.5rem 0; color: #94a3b8; font-style: italic; }
        /* Figures are inserted by the generator after sanitising, so the markup here is fixed:
           one <img> and one <figcaption>. A logo is far smaller than a poster, so the cap is on
           height as well as width to stop a crest being blown up across the column. */
        .article figure { margin: 1.75rem 0; text-align: center; }
        .article figure img { max-width: 100%; max-height: 32rem; width: auto; height: auto; border-radius: .75rem; border: 1px solid rgba(255,255,255,.08); }
        .article figcaption { margin-top: .5rem; font-size: .8rem; color: #94a3b8; }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen">
    <header class="border-b border-white/10">
        <div class="max-w-3xl mx-auto px-4 py-5 flex items-center justify-between">
            <a href="{{ route('public.blog.index') }}" class="text-lg font-extrabold tracking-tight text-white">
                {{ config('app.name') }} <span class="text-red-500">Blog</span>
            </a>
            <a href="{{ url('/') }}" class="text-sm text-gray-400 hover:text-white transition">Home</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10">
        @yield('content')
    </main>

    <footer class="border-t border-white/10 mt-16">
        <div class="max-w-3xl mx-auto px-4 py-6 text-xs text-gray-500">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </footer>
</body>
</html>
