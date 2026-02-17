<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $tournament->name ?? 'Tournament')</title>

    @hasSection('meta')
        @yield('meta')
    @else
        <meta name="description" content="{{ $tournament->description ?? 'Cricket Tournament' }}">
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $tournament->name ?? 'Tournament' }}" />
        <meta property="og:description" content="{{ $tournament->description ?? 'Cricket Tournament' }}" />
        @if(isset($tournament) && $tournament->settings?->logo)
            <meta property="og:image" content="{{ Storage::url($tournament->settings->logo) }}" />
        @endif
    @endif

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        h1, h2, h3 {
            font-family: 'Oswald', sans-serif;
        }
        .tournament-primary {
            background-color: {{ $tournament->settings?->primary_color ?? '#1f2937' }};
        }
        .tournament-secondary {
            background-color: {{ $tournament->settings?->secondary_color ?? '#374151' }};
        }
        .tournament-accent {
            color: {{ $tournament->settings?->accent_color ?? '#fbbf24' }};
        }
        .tournament-accent-bg {
            background-color: {{ $tournament->settings?->accent_color ?? '#fbbf24' }};
        }
        .gradient-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), rgba(0,0,0,0.9));
        }

        /* Navigation Enhancements */
        .nav-wrapper {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
        }
        .nav-link.active {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.15);
        }
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
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
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            padding-left: 2rem;
        }
        .mobile-nav-link.active {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.15);
            border-left: 4px solid #fbbf24;
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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .footer-link {
            color: #9ca3af;
            transition: all 0.3s ease;
        }
        .footer-link:hover {
            color: #fbbf24;
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
    </style>
    @stack('styles')
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    {{-- Navigation --}}
    <nav class="nav-wrapper shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                {{-- Logo/Title --}}
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="logo-container">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="logo-image">
                    @else
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
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
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="h-8 w-8 object-contain rounded">
                    @else
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center">
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
                    Powered by <span class="text-yellow-400">OrganizerPro</span>
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
    @stack('scripts')
</body>

</html>
