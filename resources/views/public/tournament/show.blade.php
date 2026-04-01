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
    .hero-section {
        background: linear-gradient(160deg, #0a0e1a 0%, #111827 40%, #0f172a 100%);
        position: relative;
        overflow: hidden;
    }
    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 70%;
        height: 200%;
        background: radial-gradient(ellipse, rgba(251,191,36,0.06) 0%, transparent 60%);
        pointer-events: none;
    }
    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -30%;
        right: -10%;
        width: 50%;
        height: 150%;
        background: radial-gradient(ellipse, rgba(59,130,246,0.04) 0%, transparent 60%);
        pointer-events: none;
    }
    .gradient-text {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 40%, #fbbf24 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    .glass-card:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(251, 191, 36, 0.2);
    }
    .stat-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        border-color: rgba(251, 191, 36, 0.3);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.3);
    }
    .match-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.06);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .match-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border-color: rgba(251, 191, 36, 0.25);
    }
    .team-logo-box {
        background: rgba(255, 255, 255, 0.05);
    }
    .score-text {
        font-variant-numeric: tabular-nums;
    }
    .live-indicator {
        animation: live-pulse 1.5s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .live-glow {
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.15);
    }
    .nav-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.3s ease;
    }
    .nav-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .section-title {
        position: relative;
        display: inline-block;
    }
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 48px;
        height: 3px;
        background: linear-gradient(90deg, #fbbf24, transparent);
        border-radius: 2px;
    }
    .section-title.center::after {
        left: 50%;
        transform: translateX(-50%);
    }
    /* Mobile team stacking */
    @media (max-width: 640px) {
        .result-teams-row {
            flex-direction: column !important;
            gap: 0.5rem !important;
        }
        .result-teams-row .team-block {
            flex-direction: row !important;
            text-align: left !important;
            width: 100%;
            justify-content: space-between;
        }
        .result-teams-row .team-block.team-b {
            flex-direction: row !important;
        }
        .result-teams-row .vs-block {
            display: none;
        }
    }
</style>
@endpush

@section('content')
    @php
        $regActuallyOpen = $tournament->status === 'registration' && ($settings?->isRegistrationOpen() ?? false);
        $isLive = in_array($tournament->status, ['active', 'ongoing']) || ($tournament->status === 'registration' && !$regActuallyOpen);
        $totalMatches = $tournament->matches()->where('is_cancelled', false)->count();
        $completedMatches = $tournament->matches()->where('status', 'completed')->count();
        $teamCount = $tournament->actualTeams()->count();
    @endphp

    {{-- Hero Section --}}
    <section class="hero-section pt-16 pb-20 md:pt-24 md:pb-28">
        <div class="relative z-10 max-w-5xl mx-auto px-4">
            <div class="text-center">
                {{-- Logo --}}
                @if($settings?->logo)
                    <div class="mb-6">
                        <div class="inline-block p-1.5 rounded-2xl bg-gradient-to-br from-yellow-400/20 to-orange-500/10 shadow-lg shadow-yellow-500/10">
                            <img src="{{ Storage::url($settings->logo) }}"
                                 alt="{{ $tournament->name }}"
                                 class="h-24 md:h-32 w-auto object-contain rounded-xl">
                        </div>
                    </div>
                @endif

                {{-- Tournament Name --}}
                <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold text-white mb-4 tracking-tight leading-tight">
                    <span class="gradient-text">{{ $tournament->name }}</span>
                </h1>

                {{-- Description --}}
                @if($tournament->description)
                    <p class="text-lg md:text-xl text-gray-400 max-w-2xl mx-auto mb-6 leading-relaxed">
                        {{ $tournament->description }}
                    </p>
                @endif

                {{-- Status Badge --}}
                <div class="mb-8 flex items-center justify-center gap-3 flex-wrap">
                    @if($regActuallyOpen)
                        <span class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-bold bg-green-500/20 text-green-400 border border-green-500/30">
                            <span class="w-2.5 h-2.5 bg-green-400 rounded-full mr-2.5 animate-pulse"></span>
                            Registration Open
                        </span>
                    @elseif($isLive)
                        <span class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-bold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                            <span class="w-2.5 h-2.5 bg-red-500 rounded-full mr-2.5 live-indicator"></span>
                            Tournament Live
                        </span>
                    @elseif($tournament->status === 'completed')
                        <span class="inline-flex items-center px-5 py-2.5 rounded-full text-sm font-bold bg-gray-500/20 text-gray-300 border border-gray-500/30">
                            <i class="fas fa-trophy text-yellow-400 mr-2"></i>
                            Completed
                        </span>
                    @endif

                    {{-- Quick stats inline --}}
                    @if($totalMatches > 0)
                        <span class="text-sm text-gray-500">
                            {{ $completedMatches }}/{{ $totalMatches }} matches played
                        </span>
                    @endif
                </div>

                {{-- Register / Share Row --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    @if($regActuallyOpen)
                        @php
                            $playerOpen = $settings?->player_registration_open ?? false;
                            $teamOpen = $settings?->team_registration_open ?? false;
                        @endphp
                        <div x-data="{ open: false }" class="relative">
                            @if($playerOpen && $teamOpen)
                                <button @click="open = !open"
                                        class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold text-lg rounded-xl transition-all hover:shadow-xl hover:shadow-green-500/20 flex items-center gap-2">
                                    <i class="fas fa-clipboard-check"></i>
                                    Register Now
                                    <i class="fas fa-chevron-down text-sm transition-transform" :class="{ 'rotate-180': open }"></i>
                                </button>
                                <div x-show="open" x-transition @click.away="open = false"
                                     class="absolute left-1/2 -translate-x-1/2 mt-3 w-64 bg-gray-800 rounded-xl shadow-2xl border border-gray-700 overflow-hidden z-50">
                                    <a href="{{ route('public.tournament.registration.player', $tournament->slug) }}"
                                       class="flex items-center gap-3 px-5 py-4 hover:bg-gray-700 transition-colors border-b border-gray-700">
                                        <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-400"></i>
                                        </div>
                                        <div class="text-left">
                                            <p class="font-semibold text-white text-sm">As Player</p>
                                            <p class="text-xs text-gray-400">Join individually</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('public.tournament.registration.team', $tournament->slug) }}"
                                       class="flex items-center gap-3 px-5 py-4 hover:bg-gray-700 transition-colors">
                                        <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                                            <i class="fas fa-users text-purple-400"></i>
                                        </div>
                                        <div class="text-left">
                                            <p class="font-semibold text-white text-sm">As Team</p>
                                            <p class="text-xs text-gray-400">Register your squad</p>
                                        </div>
                                    </a>
                                </div>
                            @elseif($teamOpen)
                                <a href="{{ route('public.tournament.registration.team', $tournament->slug) }}"
                                   class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold text-lg rounded-xl transition-all hover:shadow-xl hover:shadow-green-500/20 inline-flex items-center gap-2">
                                    <i class="fas fa-users"></i> Register Your Team
                                </a>
                            @elseif($playerOpen)
                                <a href="{{ route('public.tournament.registration.player', $tournament->slug) }}"
                                   class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold text-lg rounded-xl transition-all hover:shadow-xl hover:shadow-green-500/20 inline-flex items-center gap-2">
                                    <i class="fas fa-user"></i> Register as Player
                                </a>
                            @endif
                        </div>
                    @endif

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
        </div>
    </section>

    {{-- Quick Stats Strip --}}
    <section class="py-1 bg-gray-900 border-t border-b border-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-gray-800">
                <div class="py-6 text-center">
                    <p class="text-2xl md:text-3xl font-extrabold text-white">{{ $teamCount }}</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Teams</p>
                </div>
                <div class="py-6 text-center">
                    <p class="text-2xl md:text-3xl font-extrabold text-white">{{ $totalMatches }}</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Matches</p>
                </div>
                <div class="py-6 text-center">
                    <p class="text-2xl md:text-3xl font-extrabold text-white">{{ $tournament->groups->count() ?: '-' }}</p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Groups</p>
                </div>
                <div class="py-6 text-center">
                    <p class="text-2xl md:text-3xl font-extrabold text-white">
                        @if($settings?->overs_per_match)
                            {{ $settings->overs_per_match }}
                        @else
                            20
                        @endif
                    </p>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mt-1">Overs</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Champion Section --}}
    @if($tournament->status === 'completed' && $tournament->champion)
        <section class="py-16 bg-gradient-to-r from-yellow-600/90 via-amber-500/90 to-yellow-600/90 relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cg%20fill%3D%22%23000%22%20fill-opacity%3D%220.05%22%3E%3Ccircle%20cx%3D%2210%22%20cy%3D%2210%22%20r%3D%221%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E')] opacity-50"></div>
            <div class="relative max-w-4xl mx-auto px-4 text-center">
                <p class="text-yellow-900/60 font-bold text-sm uppercase tracking-widest mb-3">Champions</p>
                <div class="inline-flex items-center gap-5 bg-white/95 rounded-2xl px-8 py-6 shadow-2xl">
                    @if($tournament->champion->logo)
                        <img src="{{ Storage::url($tournament->champion->logo) }}"
                             alt="{{ $tournament->champion->name }}"
                             class="h-16 w-16 object-contain rounded-full border-2 border-yellow-400">
                    @else
                        <div class="h-16 w-16 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-trophy text-2xl text-white"></i>
                        </div>
                    @endif
                    <div class="text-left">
                        <h3 class="text-2xl md:text-3xl font-extrabold text-gray-900">{{ $tournament->champion->name }}</h3>
                        @if($tournament->runnerUp)
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-medal text-gray-400 mr-1"></i>
                                Runner-up: {{ $tournament->runnerUp->name }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Upcoming Matches --}}
    @if($upcomingMatches->count() > 0)
        <section class="py-14 bg-gray-900">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="section-title text-2xl font-bold text-white">Upcoming</h2>
                    </div>
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="text-sm text-yellow-400 hover:text-yellow-300 font-medium transition">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($upcomingMatches->take(4) as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}" class="block match-card rounded-xl overflow-hidden">
                            {{-- Header --}}
                            <div class="px-4 py-2.5 flex items-center justify-between border-b border-white/5 bg-white/[0.02]">
                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                    @if($match->match_number)
                                        <span>Match {{ $match->match_number }}</span>
                                    @endif
                                    @if($match->group)
                                        <span class="text-blue-400 bg-blue-500/10 px-1.5 py-0.5 rounded">{{ $match->group->name }}</span>
                                    @endif
                                </div>
                                @if($match->start_time)
                                    <span class="text-xs font-semibold text-yellow-400">
                                        {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                                    </span>
                                @endif
                            </div>

                            {{-- Teams --}}
                            <div class="p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="team-logo-box w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamA?->team_logo)
                                                <img src="{{ Storage::url($match->teamA->team_logo) }}" class="w-8 h-8 object-contain">
                                            @else
                                                <span class="text-xs font-bold text-gray-500">{{ substr($match->teamA?->display_name ?? 'TBA', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <p class="font-semibold text-white text-sm truncate">{{ $match->teamA?->name ?? 'TBA' }}</p>
                                    </div>

                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                                        <span class="text-[9px] font-black text-gray-900">VS</span>
                                    </div>

                                    <div class="flex items-center gap-3 flex-1 flex-row-reverse min-w-0">
                                        <div class="team-logo-box w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamB?->team_logo)
                                                <img src="{{ Storage::url($match->teamB->team_logo) }}" class="w-8 h-8 object-contain">
                                            @else
                                                <span class="text-xs font-bold text-gray-500">{{ substr($match->teamB?->display_name ?? 'TBA', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <p class="font-semibold text-white text-sm truncate text-right">{{ $match->teamB?->name ?? 'TBA' }}</p>
                                    </div>
                                </div>

                                {{-- Date & Venue --}}
                                <div class="mt-3 pt-3 border-t border-white/5 flex items-center justify-between text-xs text-gray-500">
                                    <span><i class="far fa-calendar mr-1"></i>{{ $match->match_date->format('D, d M') }}</span>
                                    @if($match->ground)
                                        <span><i class="fas fa-map-marker-alt mr-1 text-red-400/50"></i>{{ $match->ground->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Recent Results --}}
    @if($recentResults->count() > 0)
        <section class="py-14 bg-gray-900 {{ $upcomingMatches->count() > 0 ? 'border-t border-gray-800' : '' }}">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="section-title text-2xl font-bold text-white">Recent Results</h2>
                    </div>
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="text-sm text-yellow-400 hover:text-yellow-300 font-medium transition">
                        All Results <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="space-y-3">
                    @foreach($recentResults as $match)
                        @php
                            $teamAWon = $match->winner_team_id === $match->team_a_id;
                            $teamBWon = $match->winner_team_id === $match->team_b_id;
                        @endphp
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="block match-card rounded-xl overflow-hidden border-l-3 border-l-green-500">
                            <div class="p-4 md:p-5">
                                <div class="result-teams-row flex items-center justify-between gap-4">
                                    {{-- Team A --}}
                                    <div class="team-block flex items-center gap-3 flex-1 min-w-0">
                                        <div class="team-logo-box w-11 h-11 md:w-12 md:h-12 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamA?->team_logo)
                                                <img src="{{ Storage::url($match->teamA->team_logo) }}" class="w-8 h-8 md:w-9 md:h-9 object-contain">
                                            @else
                                                <span class="text-xs font-bold text-gray-500">{{ substr($match->teamA?->display_name ?? 'A', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-sm truncate {{ $teamAWon ? 'text-green-400' : 'text-white' }}">
                                                {{ $match->teamA?->name ?? 'TBA' }}
                                                @if($teamAWon) <i class="fas fa-trophy text-yellow-400 text-[10px] ml-1"></i> @endif
                                            </p>
                                            @if($match->result)
                                                <p class="score-text text-base md:text-lg font-black {{ $teamAWon ? 'text-white' : 'text-gray-400' }}">
                                                    {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                                    <span class="text-[10px] font-normal text-gray-500">({{ $match->result->team_a_overs }} ov)</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- VS --}}
                                    <div class="vs-block flex-shrink-0 px-3">
                                        <span class="text-gray-600 text-xs font-semibold">VS</span>
                                    </div>

                                    {{-- Team B --}}
                                    <div class="team-block team-b flex items-center gap-3 flex-1 flex-row-reverse text-right min-w-0">
                                        <div class="team-logo-box w-11 h-11 md:w-12 md:h-12 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamB?->team_logo)
                                                <img src="{{ Storage::url($match->teamB->team_logo) }}" class="w-8 h-8 md:w-9 md:h-9 object-contain">
                                            @else
                                                <span class="text-xs font-bold text-gray-500">{{ substr($match->teamB?->display_name ?? 'B', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-sm truncate {{ $teamBWon ? 'text-green-400' : 'text-white' }}">
                                                {{ $match->teamB?->name ?? 'TBA' }}
                                                @if($teamBWon) <i class="fas fa-trophy text-yellow-400 text-[10px] ml-1"></i> @endif
                                            </p>
                                            @if($match->result)
                                                <p class="score-text text-base md:text-lg font-black {{ $teamBWon ? 'text-white' : 'text-gray-400' }}">
                                                    {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                                    <span class="text-[10px] font-normal text-gray-500">({{ $match->result->team_b_overs }} ov)</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Result Summary --}}
                                @if($match->result?->result_summary)
                                    <div class="mt-3 pt-3 border-t border-white/5">
                                        <p class="text-xs text-yellow-400/80 font-medium">
                                            <i class="fas fa-star text-yellow-500/50 mr-1"></i>
                                            {{ $match->result->result_summary }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Explore Tournament Navigation --}}
    <section class="py-14 bg-gray-900 border-t border-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="text-center mb-10">
                <h2 class="text-2xl font-bold text-white">Explore</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="nav-card rounded-xl p-6 text-center group hover:border-blue-500/30 hover:bg-blue-500/5">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-calendar-alt text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm">Fixtures</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Schedule & Results</p>
                </a>

                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="nav-card rounded-xl p-6 text-center group hover:border-green-500/30 hover:bg-green-500/5">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-table text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm">Points Table</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Team Standings</p>
                </a>

                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="nav-card rounded-xl p-6 text-center group hover:border-purple-500/30 hover:bg-purple-500/5">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-bar text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm">Statistics</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Player Stats</p>
                </a>

                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="nav-card rounded-xl p-6 text-center group hover:border-orange-500/30 hover:bg-orange-500/5">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-users text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm">Teams</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">All Squads</p>
                </a>
            </div>
        </div>
    </section>

    {{-- Tournament Info Footer --}}
    @if($tournament->start_date || $tournament->location)
        <section class="py-10 bg-gray-900 border-t border-gray-800">
            <div class="max-w-5xl mx-auto px-4">
                <div class="flex flex-wrap items-center justify-center gap-6 text-sm text-gray-500">
                    @if($tournament->start_date)
                        <span class="flex items-center gap-2">
                            <i class="far fa-calendar text-gray-600"></i>
                            {{ $tournament->start_date->format('M d, Y') }}
                            @if($tournament->end_date)
                                — {{ $tournament->end_date->format('M d, Y') }}
                            @endif
                        </span>
                    @endif
                    @if($tournament->location)
                        <span class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-gray-600"></i>
                            {{ $tournament->location }}
                        </span>
                    @endif
                    @if($settings?->overs_per_match)
                        <span class="flex items-center gap-2">
                            <i class="fas fa-cricket-bat-ball text-gray-600"></i>
                            {{ $settings->overs_per_match }} Overs Format
                        </span>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection
