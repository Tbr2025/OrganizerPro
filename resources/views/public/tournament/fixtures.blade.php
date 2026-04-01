@extends('public.tournament.layouts.app')

@section('title', 'Fixtures - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d1b2a 100%);
    }
    .match-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    .match-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .match-card.completed-card {
        border-left: 3px solid #22c55e;
    }
    .match-card.live-card {
        border-left: 3px solid #ef4444;
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.15);
    }
    .match-card.upcoming-card {
        border-left: 3px solid #3b82f6;
    }
    .date-header {
        position: relative;
    }
    .date-header::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 60%;
        background: linear-gradient(180deg, #fbbf24, #f59e0b);
        border-radius: 2px;
    }
    .team-logo-box {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(4px);
    }
    .score-text {
        font-variant-numeric: tabular-nums;
    }
    .vs-circle {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    .live-pulse {
        animation: livePulse 2s ease-in-out infinite;
    }
    @keyframes livePulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
        50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    }
    .filter-pill {
        transition: all 0.2s ease;
    }
    .filter-pill:hover {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
    }
    .filter-pill.active {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #111827;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
    }
    .winner-highlight {
        position: relative;
    }
    .winner-highlight::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #22c55e, transparent);
        border-radius: 1px;
    }
    .stat-chip {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(4px);
    }
    /* Mobile responsive score layout */
    @media (max-width: 640px) {
        .match-teams-row {
            flex-direction: column !important;
            gap: 0.75rem !important;
        }
        .match-teams-row .team-section {
            flex-direction: row !important;
            text-align: left !important;
            width: 100%;
            justify-content: space-between;
        }
        .match-teams-row .team-section.team-b {
            flex-direction: row !important;
        }
        .match-teams-row .vs-section {
            display: none;
        }
        .mobile-vs {
            display: flex !important;
        }
        .match-teams-row .team-info {
            text-align: left !important;
        }
        .match-teams-row .score-block {
            text-align: right !important;
        }
    }
</style>
@endpush

@section('content')
    {{-- Page Header --}}
    <section class="page-header py-12 md:py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute top-0 left-1/4 w-72 h-72 bg-yellow-500/30 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-72 h-72 bg-blue-500/30 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-5xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="flex items-center gap-5">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}"
                             class="w-16 h-16 md:w-20 md:h-20 rounded-xl object-contain bg-white/10 p-2">
                    @endif
                    <div>
                        <p class="text-yellow-400 text-sm font-semibold tracking-wide uppercase mb-1">{{ $tournament->name }}</p>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-white">Fixtures & Results</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @php
                        $completedCount = $matches->where('status', 'completed')->count();
                        $liveCount = $matches->where('status', 'live')->count();
                        $upcomingCount = $matches->whereNotIn('status', ['completed', 'live'])->count();
                    @endphp
                    @if($liveCount > 0)
                        <span class="stat-chip inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-red-400 border border-red-500/30">
                            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                            {{ $liveCount }} Live
                        </span>
                    @endif
                    <span class="stat-chip inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm text-green-400 border border-green-500/20">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        {{ $completedCount }} Done
                    </span>
                    <span class="stat-chip inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm text-blue-400 border border-blue-500/20">
                        <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                        {{ $upcomingCount }} Upcoming
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- Filters --}}
    @php
        $stages = $matches->pluck('stage')->unique()->filter()->values();
    @endphp
    @if($stages->count() > 1 || $groups->count() > 0)
    <section class="py-4 bg-gray-900/95 sticky top-16 z-40 border-b border-gray-800 backdrop-blur-sm">
        <div class="max-w-5xl mx-auto px-4">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Stage Pills --}}
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <a href="{{ route('public.tournament.fixtures', ['tournament' => $tournament->slug, 'group_id' => $selectedGroupId]) }}"
                       class="filter-pill px-4 py-2 rounded-full text-sm whitespace-nowrap {{ !$selectedStage ? 'active' : 'text-gray-400 bg-gray-800' }}">
                        All Matches
                    </a>
                    @foreach($stages as $stage)
                        <a href="{{ route('public.tournament.fixtures', ['tournament' => $tournament->slug, 'stage' => $stage, 'group_id' => $selectedGroupId]) }}"
                           class="filter-pill px-4 py-2 rounded-full text-sm whitespace-nowrap {{ $selectedStage === $stage ? 'active' : 'text-gray-400 bg-gray-800' }}">
                            {{ ucwords(str_replace('_', ' ', $stage)) }}
                        </a>
                    @endforeach
                </div>

                {{-- Group Filter --}}
                @if($groups->count() > 0)
                    <div class="h-6 w-px bg-gray-700 hidden md:block"></div>
                    <div class="flex items-center gap-2 overflow-x-auto pb-1">
                        <a href="{{ route('public.tournament.fixtures', ['tournament' => $tournament->slug, 'stage' => $selectedStage]) }}"
                           class="filter-pill px-4 py-2 rounded-full text-sm whitespace-nowrap {{ !$selectedGroupId ? 'active' : 'text-gray-400 bg-gray-800' }}">
                            All Groups
                        </a>
                        @foreach($groups as $group)
                            <a href="{{ route('public.tournament.fixtures', ['tournament' => $tournament->slug, 'stage' => $selectedStage, 'group_id' => $group->id]) }}"
                               class="filter-pill px-4 py-2 rounded-full text-sm whitespace-nowrap {{ $selectedGroupId == $group->id ? 'active' : 'text-gray-400 bg-gray-800' }}">
                                {{ $group->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    {{-- Matches by Date --}}
    <section class="py-10 bg-gray-900 min-h-screen">
        <div class="max-w-5xl mx-auto px-4">
            @forelse($matchesByDate as $date => $dayMatches)
                {{-- Date Header --}}
                <div class="date-header pl-5 mb-5 {{ !$loop->first ? 'mt-10' : '' }}">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg md:text-xl font-bold text-white">
                            {{ \Carbon\Carbon::parse($date)->format('D, d M Y') }}
                        </h2>
                        <span class="text-xs text-gray-500 bg-gray-800 px-2.5 py-1 rounded-full">
                            {{ $dayMatches->count() }} {{ Str::plural('match', $dayMatches->count()) }}
                        </span>
                        @if(\Carbon\Carbon::parse($date)->isToday())
                            <span class="text-xs font-bold text-yellow-400 bg-yellow-500/20 px-2.5 py-1 rounded-full">
                                TODAY
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Match Cards --}}
                <div class="space-y-3 mb-6">
                    @foreach($dayMatches as $match)
                        @php
                            $isCompleted = $match->status === 'completed';
                            $isLive = $match->status === 'live';
                            $cardClass = $isLive ? 'live-card' : ($isCompleted ? 'completed-card' : 'upcoming-card');
                            $teamAWon = $match->winner_team_id === $match->team_a_id;
                            $teamBWon = $match->winner_team_id === $match->team_b_id;
                        @endphp
                        <a href="{{ route('public.match.show', $match->slug) }}" class="block match-card {{ $cardClass }} rounded-xl">
                            {{-- Top bar: match info --}}
                            <div class="px-4 md:px-5 py-2.5 flex flex-wrap items-center gap-2 border-b border-white/5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($match->match_number)
                                        <span class="text-xs font-medium text-gray-400">
                                            Match {{ $match->match_number }}
                                        </span>
                                    @endif
                                    @if($match->group)
                                        <span class="text-xs text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded">
                                            {{ $match->group->name }}
                                        </span>
                                    @endif
                                    @if($match->stage && !in_array($match->stage, ['group', 'league']))
                                        <span class="text-xs text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded uppercase">
                                            {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="ml-auto flex items-center gap-2">
                                    @if($match->ground)
                                        <span class="text-xs text-gray-500 hidden md:inline">
                                            <i class="fas fa-map-marker-alt mr-1"></i>{{ $match->ground->name }}
                                        </span>
                                    @endif
                                    @if($isLive)
                                        <span class="live-pulse bg-red-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-full flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 bg-white rounded-full"></span>
                                            LIVE
                                        </span>
                                    @elseif($isCompleted)
                                        <span class="text-[10px] font-semibold text-green-400 bg-green-500/10 px-2.5 py-1 rounded-full">
                                            COMPLETED
                                        </span>
                                    @elseif($match->start_time)
                                        <span class="text-xs font-semibold text-yellow-400">
                                            {{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Teams & Scores --}}
                            <div class="px-4 md:px-5 py-4">
                                {{-- Desktop: Side-by-side --}}
                                <div class="match-teams-row flex items-center justify-between gap-4">
                                    {{-- Team A --}}
                                    <div class="team-section flex items-center gap-3 flex-1 min-w-0">
                                        <div class="team-logo-box w-12 h-12 md:w-14 md:h-14 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamA?->team_logo)
                                                <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="{{ $match->teamA->name }}"
                                                     class="w-9 h-9 md:w-10 md:h-10 object-contain">
                                            @else
                                                <span class="text-base font-bold text-gray-500">{{ substr($match->teamA?->display_name ?? 'TBA', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <div class="team-info min-w-0">
                                            <p class="font-bold text-sm md:text-base truncate {{ $teamAWon ? 'text-green-400' : 'text-white' }}">
                                                {{ $match->teamA?->name ?? 'TBA' }}
                                                @if($teamAWon)
                                                    <i class="fas fa-trophy text-yellow-400 text-xs ml-1"></i>
                                                @endif
                                            </p>
                                            @if($match->result)
                                                <p class="score-text text-lg md:text-xl font-black {{ $teamAWon ? 'text-white' : 'text-gray-300' }} mt-0.5">
                                                    {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                                    <span class="text-xs font-normal text-gray-500">({{ $match->result->team_a_overs }} ov)</span>
                                                </p>
                                            @elseif($match->teamA?->short_name)
                                                <p class="text-xs text-gray-500">{{ $match->teamA->short_name }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- VS Badge (desktop) --}}
                                    <div class="vs-section flex-shrink-0">
                                        @if($isCompleted)
                                            <span class="text-gray-600 text-xs font-bold">VS</span>
                                        @elseif($isLive)
                                            <div class="vs-circle w-10 h-10 rounded-full flex items-center justify-center">
                                                <span class="text-[10px] font-black text-gray-900">LIVE</span>
                                            </div>
                                        @else
                                            <div class="vs-circle w-10 h-10 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-black text-gray-900">VS</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Mobile VS divider --}}
                                    <div class="mobile-vs hidden items-center gap-3 w-full">
                                        <div class="flex-1 h-px bg-gray-700"></div>
                                        <span class="text-xs font-bold text-gray-500">VS</span>
                                        <div class="flex-1 h-px bg-gray-700"></div>
                                    </div>

                                    {{-- Team B --}}
                                    <div class="team-section team-b flex items-center gap-3 flex-1 flex-row-reverse text-right min-w-0">
                                        <div class="team-logo-box w-12 h-12 md:w-14 md:h-14 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($match->teamB?->team_logo)
                                                <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="{{ $match->teamB->name }}"
                                                     class="w-9 h-9 md:w-10 md:h-10 object-contain">
                                            @else
                                                <span class="text-base font-bold text-gray-500">{{ substr($match->teamB?->display_name ?? 'TBA', 0, 3) }}</span>
                                            @endif
                                        </div>
                                        <div class="team-info min-w-0">
                                            <p class="font-bold text-sm md:text-base truncate {{ $teamBWon ? 'text-green-400' : 'text-white' }}">
                                                {{ $match->teamB?->name ?? 'TBA' }}
                                                @if($teamBWon)
                                                    <i class="fas fa-trophy text-yellow-400 text-xs ml-1"></i>
                                                @endif
                                            </p>
                                            @if($match->result)
                                                <p class="score-text text-lg md:text-xl font-black {{ $teamBWon ? 'text-white' : 'text-gray-300' }} mt-0.5">
                                                    {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                                    <span class="text-xs font-normal text-gray-500">({{ $match->result->team_b_overs }} ov)</span>
                                                </p>
                                            @elseif($match->teamB?->short_name)
                                                <p class="text-xs text-gray-500">{{ $match->teamB->short_name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Result Summary --}}
                                @if($match->result?->result_summary)
                                    <div class="mt-3 pt-3 border-t border-white/5">
                                        <p class="text-xs md:text-sm text-yellow-400/90 font-medium">
                                            <i class="fas fa-star text-yellow-500/60 mr-1.5"></i>
                                            {{ $match->result->result_summary }}
                                        </p>
                                    </div>
                                @endif

                                {{-- Venue (mobile only) --}}
                                @if($match->ground)
                                    <div class="mt-2 md:hidden">
                                        <span class="text-[11px] text-gray-500">
                                            <i class="fas fa-map-marker-alt mr-1"></i>{{ $match->ground->name }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @empty
                <div class="text-center py-24">
                    <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-calendar-times text-3xl text-gray-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">No Fixtures Yet</h3>
                    <p class="text-gray-500 text-sm">Match fixtures will be available soon.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Share FAB --}}
    <div class="fixed bottom-6 right-6 z-50">
        @php
            $shareMessage = "Fixtures & Results - {$tournament->name}\n" . request()->url();
        @endphp
        <button onclick="shareFixtures()" class="w-14 h-14 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-full shadow-lg hover:shadow-xl transition-all flex items-center justify-center text-gray-900 hover:scale-110">
            <i class="fas fa-share-alt text-lg"></i>
        </button>
    </div>

    @push('scripts')
    <script>
        function shareFixtures() {
            const title = '{{ $tournament->name }} - Fixtures & Results';
            const text = @json($shareMessage);
            const url = window.location.href;

            if (navigator.share) {
                navigator.share({ title, text, url });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    // Brief toast
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-24 right-6 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-lg z-50';
                    toast.textContent = 'Link copied!';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 2000);
                });
            }
        }
    </script>
    @endpush
@endsection
