<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $tournament->name ?? 'Tournament')</title>

    @hasSection('meta')
        @yield('meta')
    @else
        @php
            $shareImage = $tournament->settings?->share_image_url ?? null;
            $ogDesc = $tournament->settings?->description ?: ($tournament->description ?: 'Cricket Tournament - ' . $tournament->name);
        @endphp
        <meta name="description" content="{{ $ogDesc }}">
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $tournament->name ?? 'Tournament' }}" />
        <meta property="og:description" content="{{ $ogDesc }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:site_name" content="{{ config('app.name') }}" />
        @if($shareImage)
            {{-- Absolute URL so WhatsApp/Facebook/Twitter scrapers can fetch it --}}
            <meta property="og:image" content="{{ $shareImage }}" />
            <meta property="og:image:secure_url" content="{{ $shareImage }}" />
            <meta property="og:image:width" content="1200" />
            <meta property="og:image:height" content="630" />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content="{{ $tournament->name ?? 'Tournament' }}" />
            <meta name="twitter:description" content="{{ $ogDesc }}" />
            <meta name="twitter:image" content="{{ $shareImage }}" />
        @endif
    @endif

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    @php
        $accentColor = $tournament->settings?->accent_color ?? '#fbbf24';
        $primaryColor = $tournament->settings?->primary_color ?? '#1a1a2e';
        $secondaryColor = $tournament->settings?->secondary_color ?? '#16213e';
        $accentRgb = \App\Services\ThemeColorService::hexToRgb($accentColor);
        $accentPalette = \App\Services\ThemeColorService::generateColorPalette($accentColor);
        $accentDark = $accentPalette[600] ?? $accentColor;
        $accentDarkRgb = \App\Services\ThemeColorService::hexToRgb($accentDark);
        $primaryRgb = \App\Services\ThemeColorService::hexToRgb($primaryColor);
        $secondaryRgb = \App\Services\ThemeColorService::hexToRgb($secondaryColor);
    @endphp
    <style>
        :root {
            --accent: {{ $accentColor }};
            --accent-dark: {{ $accentDark }};
            --accent-rgb: {{ $accentRgb }};
            --accent-dark-rgb: {{ $accentDarkRgb }};
            --primary: {{ $primaryColor }};
            --secondary: {{ $secondaryColor }};
            --primary-rgb: {{ $primaryRgb }};
            --secondary-rgb: {{ $secondaryRgb }};
        }

        /* Utility classes */
        .text-accent { color: var(--accent) !important; }
        .bg-accent { background-color: var(--accent) !important; }
        .border-accent { border-color: var(--accent) !important; }
        .accent-link { color: var(--accent); transition: opacity 0.2s; }
        .accent-link:hover { opacity: 0.8; }

        body {
            font-family: 'Roboto', sans-serif;
        }
        h1, h2, h3 {
            font-family: 'Oswald', sans-serif;
        }
        .tournament-primary {
            background-color: var(--primary);
        }
        .tournament-secondary {
            background-color: var(--secondary);
        }
        .tournament-accent {
            color: var(--accent);
        }
        .tournament-accent-bg {
            background-color: var(--accent);
        }
        .gradient-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), rgba(0,0,0,0.9));
        }

        /* Navigation Enhancements */
        .nav-wrapper {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background: rgba(var(--accent-rgb), 0.1);
            color: var(--accent);
        }
        .nav-link.active {
            color: var(--accent);
            background: rgba(var(--accent-rgb), 0.15);
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-dark));
            border-radius: 2px;
        }
        .mobile-menu-overlay {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
        }
        .mobile-nav-link {
            display: block;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .mobile-nav-link:hover {
            background: rgba(var(--accent-rgb), 0.1);
            color: var(--accent);
            padding-left: 2rem;
        }
        .mobile-nav-link.active {
            color: var(--accent);
            background: rgba(var(--accent-rgb), 0.15);
            border-left: 4px solid var(--accent);
        }

        /* Logo Enhancement */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        .logo-container:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .logo-image {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.25rem;
        }

        /* Footer Enhancement */
        .footer-wrapper {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .footer-link {
            color: #9ca3af;
            transition: all 0.3s ease;
        }
        .footer-link:hover {
            color: var(--accent);
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Animation for mobile menu */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-slideDown {
            animation: slideDown 0.3s ease forwards;
        }

        [x-cloak] {
            display: none !important;
        }

        /* ── Scroll Reveal Animations ── */
        .reveal, .reveal-left, .reveal-right, .reveal-scale {
            opacity: 0;
            transition: opacity 0.7s ease, transform 0.7s ease;
            will-change: opacity, transform;
        }
        .reveal { transform: translateY(40px); }
        .reveal-left { transform: translateX(-40px); }
        .reveal-right { transform: translateX(40px); }
        .reveal-scale { transform: scale(0.85); }
        .revealed {
            opacity: 1 !important;
            transform: translateY(0) translateX(0) scale(1) !important;
        }

        /* Stagger children */
        .stagger-children > * {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .stagger-children.revealed > *:nth-child(1) { transition-delay: 0.05s; }
        .stagger-children.revealed > *:nth-child(2) { transition-delay: 0.12s; }
        .stagger-children.revealed > *:nth-child(3) { transition-delay: 0.19s; }
        .stagger-children.revealed > *:nth-child(4) { transition-delay: 0.26s; }
        .stagger-children.revealed > *:nth-child(5) { transition-delay: 0.33s; }
        .stagger-children.revealed > *:nth-child(6) { transition-delay: 0.40s; }
        .stagger-children.revealed > *:nth-child(7) { transition-delay: 0.47s; }
        .stagger-children.revealed > *:nth-child(8) { transition-delay: 0.54s; }
        .stagger-children.revealed > * {
            opacity: 1;
            transform: translateY(0);
        }

        /* Glassmorphism */
        .glass {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-hover:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(var(--accent-rgb), 0.25);
        }

        /* Accent gradient text */
        .gradient-gold {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* 3D Tilt card */
        .tilt-card {
            transition: transform 0.35s ease, box-shadow 0.35s ease;
            transform-style: preserve-3d;
            perspective: 800px;
        }
        .tilt-card:hover {
            transform: rotateX(2deg) rotateY(-2deg) translateY(-6px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35), 0 0 20px rgba(var(--accent-rgb), 0.08);
        }

        /* Ripple button */
        .btn-ripple {
            position: relative;
            overflow: hidden;
        }
        .btn-ripple .ripple-effect {
            position: absolute;
            border-radius: 50%;
            background: rgba(var(--accent-rgb), 0.3);
            transform: scale(0);
            animation: rippleAnim 0.6s linear;
            pointer-events: none;
        }
        @keyframes rippleAnim {
            to { transform: scale(4); opacity: 0; }
        }

        /* Count-up transition */
        .count-up {
            font-variant-numeric: tabular-nums;
        }

        /* Live border animation */
        @keyframes rotateBorder {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .live-border {
            background: linear-gradient(90deg, #ef4444, var(--accent), #ef4444);
            background-size: 200% 200%;
            animation: rotateBorder 3s ease infinite;
            padding: 2px;
            border-radius: 0.75rem;
        }
        .live-border > * {
            background: var(--primary);
            border-radius: 0.65rem;
        }

        /* Champion glow ring */
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(var(--accent-rgb), 0.3); }
            50% { box-shadow: 0 0 40px rgba(var(--accent-rgb), 0.6); }
        }
        .glow-ring {
            animation: glowPulse 2.5s ease-in-out infinite;
        }

        /* Top player gold glow */
        @keyframes goldGlow {
            0%, 100% { box-shadow: inset 0 0 20px rgba(var(--accent-rgb), 0.05); }
            50% { box-shadow: inset 0 0 30px rgba(var(--accent-rgb), 0.12); }
        }
        .gold-glow {
            animation: goldGlow 3s ease-in-out infinite;
        }

        /* Position #1 bounce */
        @keyframes scaleBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .bounce-badge {
            animation: scaleBounce 2s ease-in-out infinite;
        }
    </style>
    @stack('styles')
</head>

<body class="text-gray-100 min-h-screen flex flex-col" style="background-color: var(--primary);">
    {{-- Navigation --}}
    <nav class="nav-wrapper shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                {{-- Logo/Title --}}
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="logo-container">
                    @if(($tournament->settings?->logo ?? $tournament->logo))
                        <img src="{{ Storage::url($tournament->settings?->logo ?? $tournament->logo) }}" alt="{{ $tournament->name }}" class="logo-image">
                    @else
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, var(--accent), var(--accent-dark));">
                            <i class="fas fa-trophy text-gray-900"></i>
                        </div>
                    @endif
                    <span class="font-bold text-lg truncate max-w-xs">{{ $tournament->name }}</span>
                </a>

                {{-- Desktop Menu --}}
                <div class="hidden md:flex items-center space-x-2">
                    <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                       class="nav-link {{ request()->routeIs('public.tournament.show') ? 'active' : '' }}">
                        <i class="fas fa-home mr-2 text-sm"></i>Home
                    </a>
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="nav-link {{ request()->routeIs('public.tournament.fixtures') ? 'active' : '' }}">
                        <i class="fas fa-calendar mr-2 text-sm"></i>Fixtures
                    </a>
                    <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                       class="nav-link {{ request()->routeIs('public.tournament.point-table') ? 'active' : '' }}">
                        <i class="fas fa-table mr-2 text-sm"></i>Points
                    </a>
                    <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                       class="nav-link {{ request()->routeIs('public.tournament.statistics') ? 'active' : '' }}">
                        <i class="fas fa-chart-bar mr-2 text-sm"></i>Stats
                    </a>
                    <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                       class="nav-link {{ request()->routeIs('public.tournament.teams') ? 'active' : '' }}">
                        <i class="fas fa-users mr-2 text-sm"></i>Teams
                    </a>
                </div>

                {{-- Mobile menu button --}}
                <button type="button" class="md:hidden p-2 rounded-lg hover:bg-gray-800 transition" onclick="toggleMobileMenu()">
                    <svg id="menu-icon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="close-icon" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Mobile Menu --}}
            <div id="mobile-menu" class="hidden md:hidden mobile-menu-overlay rounded-xl mb-4 overflow-hidden animate-slideDown">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                   class="mobile-nav-link {{ request()->routeIs('public.tournament.show') ? 'active' : '' }}">
                    <i class="fas fa-home mr-3 w-5"></i>Home
                </a>
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="mobile-nav-link {{ request()->routeIs('public.tournament.fixtures') ? 'active' : '' }}">
                    <i class="fas fa-calendar mr-3 w-5"></i>Fixtures
                </a>
                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="mobile-nav-link {{ request()->routeIs('public.tournament.point-table') ? 'active' : '' }}">
                    <i class="fas fa-table mr-3 w-5"></i>Point Table
                </a>
                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="mobile-nav-link {{ request()->routeIs('public.tournament.statistics') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar mr-3 w-5"></i>Statistics
                </a>
                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="mobile-nav-link {{ request()->routeIs('public.tournament.teams') ? 'active' : '' }}">
                    <i class="fas fa-users mr-3 w-5"></i>Teams
                </a>
            </div>
        </div>
    </nav>

    {{-- Tournament Info Bar --}}
    @if($tournament->start_date || $tournament->location)
    <div class="border-b border-gray-700/50" style="background: linear-gradient(to right, var(--secondary), var(--primary), var(--secondary));">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-1 py-2 text-xs sm:text-sm text-gray-300">
                @if($tournament->start_date)
                <div class="flex items-center gap-1.5">
                    <i class="far fa-calendar-alt text-accent"></i>
                    <span>
                        {{ $tournament->start_date->format('d M Y') }}
                        @if($tournament->end_date && $tournament->end_date->ne($tournament->start_date))
                            <span class="text-gray-500 mx-0.5">-</span> {{ $tournament->end_date->format('d M Y') }}
                        @endif
                    </span>
                </div>
                @endif

                @if($tournament->location)
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-map-marker-alt text-accent"></i>
                    <span>{{ $tournament->location }}</span>
                </div>
                @endif

                <div class="flex items-center gap-1.5" x-data x-init="
                    setInterval(() => {
                        const now = new Date();
                        $el.querySelector('[data-live-time]').textContent =
                            now.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short' }) +
                            ' \u2022 ' +
                            now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    }, 1000);
                    $el.querySelector('[data-live-time]').textContent =
                        new Date().toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short' }) +
                        ' \u2022 ' +
                        new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                ">
                    <i class="far fa-clock text-accent"></i>
                    <span data-live-time class="tabular-nums"></span>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-gradient-to-r from-green-600 to-green-700 text-white px-4 py-3 text-center shadow-lg">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-4 py-3 text-center shadow-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- Main Content --}}
    <main class="flex-grow">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="footer-wrapper py-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-3">
                    @if(($tournament->settings?->logo ?? $tournament->logo))
                        <img src="{{ Storage::url($tournament->settings?->logo ?? $tournament->logo) }}" alt="{{ $tournament->name }}" class="h-8 w-8 object-contain rounded">
                    @else
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(to bottom right, var(--accent), var(--accent-dark));">
                            <i class="fas fa-trophy text-gray-900 text-sm"></i>
                        </div>
                    @endif
                    <span class="text-gray-400 text-sm">&copy; {{ now()->year }} {{ $tournament->name }}</span>
                </div>
                <div class="flex items-center gap-6">
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}" class="footer-link text-sm">Fixtures</a>
                    <a href="{{ route('public.tournament.point-table', $tournament->slug) }}" class="footer-link text-sm">Points</a>
                    <a href="{{ route('public.tournament.teams', $tournament->slug) }}" class="footer-link text-sm">Teams</a>
                </div>
                <p class="text-gray-600 text-sm">
                    Powered by <span class="text-accent">{{ config('app.name') }}</span>
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');
            const closeIcon = document.getElementById('close-icon');

            menu.classList.toggle('hidden');
            menuIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('hidden');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobile-menu');
            const nav = document.querySelector('nav');
            if (!nav.contains(event.target) && !menu.classList.contains('hidden')) {
                toggleMobileMenu();
            }
        });
    </script>
    {{-- Scroll Reveal & Counter Animation --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // IntersectionObserver for reveal animations
            const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale, .stagger-children');
            if (revealEls.length) {
                const revealObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('revealed');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });
                revealEls.forEach(function (el) { revealObserver.observe(el); });
            }

            // Count-up animation
            const counters = document.querySelectorAll('.count-up');
            if (counters.length) {
                const counterObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            const el = entry.target;
                            const target = parseInt(el.getAttribute('data-count'), 10);
                            if (isNaN(target)) return;
                            counterObserver.unobserve(el);
                            const duration = 1200;
                            const start = performance.now();
                            function tick(now) {
                                const elapsed = now - start;
                                const progress = Math.min(elapsed / duration, 1);
                                const ease = 1 - Math.pow(1 - progress, 3);
                                el.textContent = Math.round(ease * target);
                                if (progress < 1) requestAnimationFrame(tick);
                            }
                            requestAnimationFrame(tick);
                        }
                    });
                }, { threshold: 0.3 });
                counters.forEach(function (el) { counterObserver.observe(el); });
            }

            // Ripple effect for .btn-ripple
            document.querySelectorAll('.btn-ripple').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple-effect');
                    const rect = btn.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    btn.appendChild(ripple);
                    setTimeout(function () { ripple.remove(); }, 600);
                });
            });
        });
    </script>
    @stack('scripts')
</body>

</html>
