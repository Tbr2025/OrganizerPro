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
    </style>
    @stack('styles')
</head>

<body class="bg-gray-900 text-gray-100 min-h-screen">
    {{-- Navigation --}}
    <nav class="tournament-primary shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                {{-- Logo/Title --}}
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="flex items-center space-x-3">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="h-10 w-10 object-contain rounded">
                    @endif
                    <span class="font-bold text-lg truncate max-w-xs">{{ $tournament->name }}</span>
                </a>

                {{-- Desktop Menu --}}
                <div class="hidden md:flex items-center space-x-6">
                    <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                       class="hover:text-yellow-400 transition {{ request()->routeIs('public.tournament.show') ? 'text-yellow-400' : '' }}">
                        Home
                    </a>
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="hover:text-yellow-400 transition {{ request()->routeIs('public.tournament.fixtures') ? 'text-yellow-400' : '' }}">
                        Fixtures
                    </a>
                    <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                       class="hover:text-yellow-400 transition {{ request()->routeIs('public.tournament.point-table') ? 'text-yellow-400' : '' }}">
                        Point Table
                    </a>
                    <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                       class="hover:text-yellow-400 transition {{ request()->routeIs('public.tournament.statistics') ? 'text-yellow-400' : '' }}">
                        Statistics
                    </a>
                    <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                       class="hover:text-yellow-400 transition {{ request()->routeIs('public.tournament.teams') ? 'text-yellow-400' : '' }}">
                        Teams
                    </a>
                </div>

                {{-- Mobile menu button --}}
                <button type="button" class="md:hidden text-gray-300 hover:text-white" onclick="toggleMobileMenu()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            {{-- Mobile Menu --}}
            <div id="mobile-menu" class="hidden md:hidden pb-4">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="block py-2 hover:text-yellow-400">Home</a>
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}" class="block py-2 hover:text-yellow-400">Fixtures</a>
                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}" class="block py-2 hover:text-yellow-400">Point Table</a>
                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}" class="block py-2 hover:text-yellow-400">Statistics</a>
                <a href="{{ route('public.tournament.teams', $tournament->slug) }}" class="block py-2 hover:text-yellow-400">Teams</a>
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-green-600 text-white px-4 py-3 text-center">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-600 text-white px-4 py-3 text-center">
            {{ session('error') }}
        </div>
    @endif

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="tournament-primary mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-gray-400">&copy; {{ now()->year }} {{ $tournament->name }}. All rights reserved.</p>
            <p class="text-gray-500 text-sm mt-2">Powered by OrganizerPro</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }
    </script>
    @stack('scripts')
</body>

</html>
