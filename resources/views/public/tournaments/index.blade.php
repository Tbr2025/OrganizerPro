<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments | {{ config('app.name') }}</title>
    <meta name="description" content="Browse all cricket tournaments - open for registration, ongoing, and completed">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-gradient {
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 25%, #0d1b2a 50%, #1b263b 75%, #0f0f23 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .tournament-card {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            transition: all 0.3s ease;
        }
        .tournament-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
            border-color: rgba(251, 191, 36, 0.3);
        }
        .status-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body class="hero-gradient min-h-screen text-white">
    {{-- Header --}}
    <header class="py-6 border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-trophy text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>
                        <p class="text-gray-400 text-sm">Cricket Tournaments</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Page Title --}}
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold mb-4">Browse Tournaments</h2>
            <p class="text-gray-400 text-lg">Find tournaments to register, follow ongoing matches, or view past results</p>
        </div>

        {{-- Registration Open Section --}}
        @if($registrationOpen->count() > 0)
            <section class="mb-16">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-3 h-3 bg-green-500 rounded-full status-badge"></span>
                    <h3 class="text-2xl font-bold text-green-400">Registration Open</h3>
                    <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm">{{ $registrationOpen->count() }} Tournament(s)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($registrationOpen as $tournament)
                        @include('public.tournaments.partials.tournament-card', ['tournament' => $tournament, 'showRegister' => true])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Ongoing Section --}}
        @if($ongoing->count() > 0)
            <section class="mb-16">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-3 h-3 bg-yellow-500 rounded-full status-badge"></span>
                    <h3 class="text-2xl font-bold text-yellow-400">Ongoing Tournaments</h3>
                    <span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-sm">{{ $ongoing->count() }} Tournament(s)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($ongoing as $tournament)
                        @include('public.tournaments.partials.tournament-card', ['tournament' => $tournament, 'showRegister' => false])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Completed Section --}}
        @if($completed->count() > 0)
            <section class="mb-16">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-3 h-3 bg-gray-500 rounded-full"></span>
                    <h3 class="text-2xl font-bold text-gray-400">Completed Tournaments</h3>
                    <span class="bg-gray-500/20 text-gray-400 px-3 py-1 rounded-full text-sm">{{ $completed->count() }} Tournament(s)</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($completed as $tournament)
                        @include('public.tournaments.partials.tournament-card', ['tournament' => $tournament, 'showRegister' => false])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- No Tournaments --}}
        @if($registrationOpen->count() === 0 && $ongoing->count() === 0 && $completed->count() === 0)
            <div class="text-center py-20">
                <div class="w-24 h-24 bg-gray-700/50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-trophy text-gray-500 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-400 mb-2">No Tournaments Yet</h3>
                <p class="text-gray-500">Check back later for upcoming tournaments</p>
            </div>
        @endif
    </main>

    {{-- Footer --}}
    <footer class="border-t border-white/10 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-500 text-sm">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
