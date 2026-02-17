@extends('public.tournament.layouts.app')

@section('title', $tournament->name)

@section('meta')
    <meta name="description" content="{{ $tournament->description ?? 'Cricket Tournament - ' . $tournament->name }}">
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('public.tournament.show', $tournament->slug) }}" />
    <meta property="og:title" content="{{ $tournament->name }}" />
    <meta property="og:description" content="{{ $tournament->description ?? 'Cricket Tournament' }}" />
    @if($settings?->logo)
        <meta property="og:image" content="{{ Storage::url($settings->logo) }}" />
    @endif
@endsection

@push('styles')
<style>
    .hero-gradient {
        background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 25%, #0d1b2a 50%, #1b263b 75%, #0f0f23 100%);
    }
    .hero-pattern {
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23fbbf24' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .glow-yellow {
        box-shadow: 0 0 40px rgba(251, 191, 36, 0.3);
    }
    .glow-green {
        box-shadow: 0 0 30px rgba(34, 197, 94, 0.4);
    }
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    .animate-float-delayed {
        animation: float 6s ease-in-out infinite 2s;
    }
    .animate-pulse-slow {
        animation: pulse 3s ease-in-out infinite;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
    .stat-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .match-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        transition: all 0.3s ease;
    }
    .match-card:hover {
        transform: scale(1.02);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
    }
    .team-logo-container {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
    }
    .countdown-digit {
        background: linear-gradient(145deg, #fbbf24 0%, #f59e0b 100%);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .nav-link-active::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #fbbf24, #f59e0b);
        border-radius: 2px;
    }
    .scroll-indicator {
        animation: bounce 2s infinite;
    }
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-10px); }
        60% { transform: translateY(-5px); }
    }
    .gradient-text {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #fbbf24 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .live-indicator {
        animation: live-pulse 1.5s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
</style>
@endpush

@section('content')
    {{-- Hero Section --}}
    <section class="relative min-h-screen hero-gradient hero-pattern overflow-hidden">
        {{-- Background Elements --}}
        @if($settings?->background_image)
            <div class="absolute inset-0 bg-cover bg-center opacity-30" style="background-image: url('{{ Storage::url($settings->background_image) }}');"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-b from-transparent via-gray-900/50 to-gray-900"></div>

        {{-- Decorative Elements --}}
        <div class="absolute top-20 left-10 w-72 h-72 bg-yellow-500/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-float-delayed"></div>

        {{-- Content --}}
        <div class="relative z-10 max-w-7xl mx-auto px-4 pt-20 pb-32 min-h-screen flex flex-col justify-center">
            <div class="text-center">
                {{-- Tournament Logo --}}
                @if($settings?->logo)
                    <div class="mb-8 animate-float">
                        <div class="inline-block p-2 rounded-full bg-gradient-to-r from-yellow-400/20 to-orange-500/20 glow-yellow">
                            <img src="{{ Storage::url($settings->logo) }}"
                                 alt="{{ $tournament->name }}"
                                 class="h-32 md:h-40 w-auto object-contain rounded-full">
                        </div>
                    </div>
                @endif

                {{-- Tournament Name --}}
                <h1 class="text-5xl md:text-7xl lg:text-8xl font-bold text-white mb-6 tracking-tight">
                    <span class="gradient-text">{{ $tournament->name }}</span>
                </h1>

                {{-- Description --}}
                @if($tournament->description)
                    <p class="text-xl md:text-2xl text-gray-300 max-w-3xl mx-auto mb-8 leading-relaxed">
                        {{ $tournament->description }}
                    </p>
                @endif

                {{-- Status Badge --}}
                <div class="mb-10">
                    @if($tournament->status === 'registration')
                        <span class="inline-flex items-center px-6 py-3 rounded-full text-lg font-bold bg-gradient-to-r from-green-500 to-emerald-600 text-white shadow-lg glow-green">
                            <span class="w-3 h-3 bg-white rounded-full mr-3 animate-pulse"></span>
                            Registration Open
                        </span>
                    @elseif($tournament->status === 'active')
                        <span class="inline-flex items-center px-6 py-3 rounded-full text-lg font-bold bg-gradient-to-r from-yellow-500 to-orange-500 text-gray-900 shadow-lg glow-yellow">
                            <span class="w-3 h-3 bg-red-600 rounded-full mr-3 live-indicator"></span>
                            Tournament Live
                        </span>
                    @elseif($tournament->status === 'completed')
                        <span class="inline-flex items-center px-6 py-3 rounded-full text-lg font-bold bg-gradient-to-r from-gray-600 to-gray-700 text-white shadow-lg">
                            <i class="fas fa-trophy mr-2"></i>
                            Completed
                        </span>
                    @endif
                </div>

                {{-- Registration Buttons --}}
                @if($tournament->status === 'registration')
                    <div class="flex flex-wrap gap-4 justify-center mb-10">
                        @if($settings?->player_registration_enabled)
                            <a href="{{ route('public.tournament.register.player', $tournament->slug) }}"
                               class="group px-8 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 font-bold text-lg rounded-xl transition-all transform hover:scale-105 hover:shadow-2xl flex items-center">
                                <i class="fas fa-user-plus mr-3 group-hover:animate-bounce"></i>
                                Register as Player
                            </a>
                        @endif
                        @if($settings?->team_registration_enabled)
                            <a href="{{ route('public.tournament.register.team', $tournament->slug) }}"
                               class="group px-8 py-4 bg-white/10 backdrop-blur border-2 border-white/30 text-white font-bold text-lg rounded-xl transition-all transform hover:scale-105 hover:bg-white/20 hover:shadow-2xl flex items-center">
                                <i class="fas fa-users mr-3 group-hover:animate-bounce"></i>
                                Register Team
                            </a>
                        @endif
                    </div>
                @endif

                {{-- Share Buttons --}}
                <div class="flex justify-center">
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareMessage = $whatsappService->getTournamentShareMessage($tournament);
                    @endphp
                    <x-share-buttons
                        :title="$tournament->name"
                        :description="$tournament->description ?? 'Cricket Tournament'"
                        :whatsappMessage="$shareMessage"
                        variant="compact"
                        :showLabel="false"
                        class="justify-center"
                    />
                </div>
            </div>

            {{-- Scroll Indicator --}}
            <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 scroll-indicator">
                <div class="flex flex-col items-center text-gray-400">
                    <span class="text-sm mb-2">Scroll Down</span>
                    <i class="fas fa-chevron-down text-xl"></i>
                </div>
            </div>
        </div>
    </section>

    {{-- Champion Section (if completed) --}}
    @if($tournament->status === 'completed' && $tournament->champion)
        <section class="py-20 bg-gradient-to-r from-yellow-600 via-amber-500 to-yellow-600 relative overflow-hidden">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute -top-20 -left-20 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-20 -right-20 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>

            <div class="relative max-w-5xl mx-auto px-4 text-center">
                <div class="mb-8">
                    <i class="fas fa-crown text-6xl text-yellow-900/60 mb-4 animate-float"></i>
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900">Champions</h2>
                </div>

                <div class="bg-white rounded-3xl p-10 shadow-2xl inline-block transform hover:scale-105 transition-all duration-300">
                    @if($tournament->champion->logo)
                        <img src="{{ Storage::url($tournament->champion->logo) }}"
                             alt="{{ $tournament->champion->name }}"
                             class="h-32 w-32 object-contain mx-auto mb-6 rounded-full border-4 border-yellow-400 shadow-lg">
                    @else
                        <div class="h-32 w-32 mx-auto mb-6 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                            <i class="fas fa-trophy text-5xl text-white"></i>
                        </div>
                    @endif
                    <h3 class="text-3xl md:text-4xl font-bold text-gray-900">{{ $tournament->champion->name }}</h3>
                </div>

                @if($tournament->runnerUp)
                    <div class="mt-10 flex items-center justify-center gap-3">
                        <i class="fas fa-medal text-2xl text-gray-700"></i>
                        <span class="text-xl text-gray-800">
                            Runner-up: <strong>{{ $tournament->runnerUp->name }}</strong>
                        </span>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Tournament Stats Bar --}}
    <section class="py-16 bg-gray-900 relative">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-900 to-gray-800"></div>
        <div class="relative max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                {{-- Dates --}}
                <div class="stat-card rounded-2xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-sm uppercase tracking-wider text-gray-400 mb-2">Tournament Dates</h3>
                    <p class="text-lg font-bold text-white">
                        @if($tournament->start_date && $tournament->end_date)
                            {{ $tournament->start_date->format('M d') }} - {{ $tournament->end_date->format('M d') }}
                        @else
                            TBA
                        @endif
                    </p>
                </div>

                {{-- Format --}}
                <div class="stat-card rounded-2xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
                        <i class="fas fa-trophy text-2xl text-white"></i>
                    </div>
                    <h3 class="text-sm uppercase tracking-wider text-gray-400 mb-2">Format</h3>
                    <p class="text-lg font-bold text-white">
                        @if($settings?->overs_per_match)
                            {{ $settings->overs_per_match }} Overs
                        @else
                            T20
                        @endif
                    </p>
                </div>

                {{-- Teams --}}
                <div class="stat-card rounded-2xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center">
                        <i class="fas fa-users text-2xl text-white"></i>
                    </div>
                    <h3 class="text-sm uppercase tracking-wider text-gray-400 mb-2">Teams</h3>
                    <p class="text-lg font-bold text-white">{{ $tournament->actualTeams()->count() }} Teams</p>
                </div>

                {{-- Groups --}}
                <div class="stat-card rounded-2xl p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center">
                        <i class="fas fa-layer-group text-2xl text-white"></i>
                    </div>
                    <h3 class="text-sm uppercase tracking-wider text-gray-400 mb-2">Groups</h3>
                    <p class="text-lg font-bold text-white">{{ $tournament->groups->count() ?: 1 }} Groups</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Upcoming Matches --}}
    @if($upcomingMatches->count() > 0)
        <section class="py-20 bg-gradient-to-b from-gray-800 to-gray-900">
            <div class="max-w-6xl mx-auto px-4">
                <div class="text-center mb-12">
                    <span class="inline-block px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-clock mr-2"></i>Coming Up
                    </span>
                    <h2 class="text-4xl md:text-5xl font-bold text-white">Upcoming Matches</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($upcomingMatches as $index => $match)
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="match-card rounded-2xl overflow-hidden border border-gray-700/50 {{ $index === 0 ? 'md:col-span-2 lg:col-span-1' : '' }}">
                            {{-- Match Header --}}
                            <div class="bg-gradient-to-r from-gray-700/50 to-gray-800/50 px-6 py-3 flex items-center justify-between">
                                <span class="text-sm text-gray-400">
                                    <i class="far fa-calendar mr-2"></i>
                                    {{ $match->match_date->format('D, M d') }}
                                </span>
                                @if($match->match_time)
                                    <span class="text-sm text-yellow-400 font-semibold">
                                        <i class="far fa-clock mr-1"></i>
                                        {{ $match->match_time->format('h:i A') }}
                                    </span>
                                @endif
                            </div>

                            {{-- Teams --}}
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    {{-- Team A --}}
                                    <div class="text-center flex-1">
                                        <div class="team-logo-container w-20 h-20 mx-auto mb-3 rounded-full flex items-center justify-center overflow-hidden">
                                            @if($match->teamA?->logo)
                                                <img src="{{ Storage::url($match->teamA->logo) }}"
                                                     alt="{{ $match->teamA->name }}"
                                                     class="w-16 h-16 object-contain">
                                            @else
                                                <span class="text-2xl font-bold text-gray-400">
                                                    {{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="font-bold text-white truncate">{{ $match->teamA?->short_name ?? 'TBA' }}</p>
                                    </div>

                                    {{-- VS Badge --}}
                                    <div class="px-4">
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                                            <span class="text-sm font-black text-gray-900">VS</span>
                                        </div>
                                    </div>

                                    {{-- Team B --}}
                                    <div class="text-center flex-1">
                                        <div class="team-logo-container w-20 h-20 mx-auto mb-3 rounded-full flex items-center justify-center overflow-hidden">
                                            @if($match->teamB?->logo)
                                                <img src="{{ Storage::url($match->teamB->logo) }}"
                                                     alt="{{ $match->teamB->name }}"
                                                     class="w-16 h-16 object-contain">
                                            @else
                                                <span class="text-2xl font-bold text-gray-400">
                                                    {{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="font-bold text-white truncate">{{ $match->teamB?->short_name ?? 'TBA' }}</p>
                                    </div>
                                </div>

                                {{-- Venue --}}
                                @if($match->ground)
                                    <div class="mt-4 pt-4 border-t border-gray-700/50 text-center">
                                        <span class="text-sm text-gray-400">
                                            <i class="fas fa-map-marker-alt mr-2 text-red-400"></i>
                                            {{ $match->ground->name }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="text-center mt-10">
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 font-bold rounded-xl hover:shadow-2xl transition-all transform hover:scale-105">
                        View All Fixtures
                        <i class="fas fa-arrow-right ml-3"></i>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Recent Results --}}
    @if($recentResults->count() > 0)
        <section class="py-20 bg-gray-900">
            <div class="max-w-6xl mx-auto px-4">
                <div class="text-center mb-12">
                    <span class="inline-block px-4 py-2 bg-green-500/20 text-green-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-check-circle mr-2"></i>Completed
                    </span>
                    <h2 class="text-4xl md:text-5xl font-bold text-white">Recent Results</h2>
                </div>

                <div class="space-y-4">
                    @foreach($recentResults as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="block bg-gradient-to-r from-gray-800 to-gray-800/50 rounded-2xl p-6 border border-gray-700/50 hover:border-yellow-500/50 transition-all hover:shadow-xl">
                            <div class="flex flex-col md:flex-row md:items-center gap-4">
                                {{-- Team A --}}
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-14 h-14 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        @if($match->teamA?->logo)
                                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="w-10 h-10 object-contain">
                                        @else
                                            <span class="text-lg font-bold">{{ substr($match->teamA?->short_name ?? 'A', 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-lg {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400' : 'text-white' }}">
                                            {{ $match->teamA?->short_name ?? 'TBA' }}
                                            @if($match->winner_team_id === $match->team_a_id)
                                                <i class="fas fa-trophy text-yellow-400 ml-2"></i>
                                            @endif
                                        </p>
                                        @if($match->result)
                                            <p class="text-2xl font-black text-white">
                                                {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                                <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_a_overs }})</span>
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                {{-- VS --}}
                                <div class="hidden md:flex items-center justify-center px-6">
                                    <span class="text-gray-500 text-lg font-semibold">vs</span>
                                </div>

                                {{-- Team B --}}
                                <div class="flex items-center gap-4 flex-1 md:flex-row-reverse md:text-right">
                                    <div class="w-14 h-14 rounded-full bg-gray-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                                        @if($match->teamB?->logo)
                                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="w-10 h-10 object-contain">
                                        @else
                                            <span class="text-lg font-bold">{{ substr($match->teamB?->short_name ?? 'B', 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-bold text-lg {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400' : 'text-white' }}">
                                            {{ $match->teamB?->short_name ?? 'TBA' }}
                                            @if($match->winner_team_id === $match->team_b_id)
                                                <i class="fas fa-trophy text-yellow-400 ml-2"></i>
                                            @endif
                                        </p>
                                        @if($match->result)
                                            <p class="text-2xl font-black text-white">
                                                {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                                <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_b_overs }})</span>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Result Summary --}}
                            @if($match->result?->result_summary)
                                <div class="mt-4 pt-4 border-t border-gray-700/50 text-center">
                                    <span class="inline-flex items-center px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-full text-sm font-semibold">
                                        <i class="fas fa-star mr-2"></i>
                                        {{ $match->result->result_summary }}
                                    </span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Quick Links / Navigation Cards --}}
    <section class="py-20 bg-gradient-to-b from-gray-900 to-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white">Explore Tournament</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="group stat-card rounded-2xl p-8 text-center hover:bg-gradient-to-br hover:from-blue-600/20 hover:to-blue-800/20">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-calendar-alt text-3xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Fixtures</h3>
                    <p class="text-sm text-gray-400">Match Schedule</p>
                </a>

                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="group stat-card rounded-2xl p-8 text-center hover:bg-gradient-to-br hover:from-green-600/20 hover:to-green-800/20">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-table text-3xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Point Table</h3>
                    <p class="text-sm text-gray-400">Team Standings</p>
                </a>

                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="group stat-card rounded-2xl p-8 text-center hover:bg-gradient-to-br hover:from-purple-600/20 hover:to-purple-800/20">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-chart-bar text-3xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Statistics</h3>
                    <p class="text-sm text-gray-400">Player Stats</p>
                </a>

                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="group stat-card rounded-2xl p-8 text-center hover:bg-gradient-to-br hover:from-orange-600/20 hover:to-orange-800/20">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-users text-3xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-1">Teams</h3>
                    <p class="text-sm text-gray-400">All Squads</p>
                </a>
            </div>
        </div>
    </section>
@endsection
