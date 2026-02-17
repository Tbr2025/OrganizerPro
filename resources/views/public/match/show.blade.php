@extends('public.tournament.layouts.app')

@section('title', ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA') . ' - ' . $tournament->name)

@section('meta')
    <meta name="description" content="{{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }} - {{ $tournament->name }}">
    <meta property="og:title" content="{{ $match->teamA?->short_name ?? 'TBA' }} vs {{ $match->teamB?->short_name ?? 'TBA' }}" />
    <meta property="og:description" content="Match #{{ $match->match_number }} - {{ $tournament->name }}" />
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
    .team-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .team-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .team-card.winner {
        background: linear-gradient(145deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
        border: 2px solid rgba(34, 197, 94, 0.5);
        box-shadow: 0 0 60px rgba(34, 197, 94, 0.3);
    }
    .score-display {
        font-size: clamp(2.5rem, 6vw, 4rem);
        font-weight: 800;
        line-height: 1;
    }
    .vs-badge {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        box-shadow: 0 10px 40px rgba(251, 191, 36, 0.4);
    }
    .live-pulse {
        animation: live-pulse 1.5s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        50% { opacity: 0.8; box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
    }
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-15px); }
    }
    .gradient-text {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #fbbf24 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .info-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: all 0.3s ease;
    }
    .info-card:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.15);
    }
    .award-card {
        background: linear-gradient(145deg, rgba(251, 191, 36, 0.1) 0%, rgba(251, 191, 36, 0.02) 100%);
        border: 1px solid rgba(251, 191, 36, 0.2);
        transition: all 0.3s ease;
    }
    .award-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(251, 191, 36, 0.15);
        border-color: rgba(251, 191, 36, 0.4);
    }
    .action-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .action-btn:hover {
        transform: translateY(-3px);
    }
    .match-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        transition: all 0.3s ease;
    }
    .match-card:hover {
        transform: scale(1.03);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .gallery-item {
        transition: all 0.3s ease;
    }
    .gallery-item:hover {
        transform: scale(1.05);
        z-index: 10;
    }
    .share-btn {
        transition: all 0.3s ease;
    }
    .share-btn:hover {
        transform: translateY(-3px);
    }
    .countdown-box {
        background: linear-gradient(145deg, rgba(251, 191, 36, 0.15) 0%, rgba(251, 191, 36, 0.05) 100%);
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
</style>
@endpush

@section('content')
    {{-- Hero Section --}}
    <section class="relative hero-gradient hero-pattern overflow-hidden">
        {{-- Background Decorations --}}
        <div class="absolute top-10 left-5 w-64 h-64 bg-yellow-500/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-10 right-5 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        @if($match->status === 'completed' && $match->winner_team_id)
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-green-500/5 rounded-full blur-3xl"></div>
        @endif

        <div class="relative z-10 max-w-5xl mx-auto px-4 py-10 md:py-16">
            {{-- Tournament Badge --}}
            <div class="text-center mb-6">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="inline-flex items-center gap-3 px-5 py-2 glass-card rounded-full hover:bg-white/10 transition">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="w-8 h-8 rounded-full object-cover">
                    @endif
                    <span class="text-gray-300 font-medium">{{ $tournament->name }}</span>
                </a>
            </div>

            {{-- Match Status & Info Badges --}}
            <div class="flex flex-wrap items-center justify-center gap-3 mb-8">
                {{-- Match Status Badge --}}
                @if($match->status === 'live')
                    <span class="inline-flex items-center px-5 py-2 rounded-full text-sm font-bold bg-red-600 text-white live-pulse">
                        <span class="w-2 h-2 bg-white rounded-full mr-2 animate-ping"></span>
                        LIVE NOW
                    </span>
                @elseif($match->status === 'completed')
                    <span class="inline-flex items-center px-5 py-2 rounded-full text-sm font-bold bg-gradient-to-r from-green-500 to-emerald-600 text-white">
                        <i class="fas fa-check-circle mr-2"></i>
                        COMPLETED
                    </span>
                @else
                    <span class="inline-flex items-center px-5 py-2 rounded-full text-sm font-bold bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                        <i class="far fa-clock mr-2"></i>
                        UPCOMING
                    </span>
                @endif

                {{-- Match Stage Badge --}}
                @if($match->stage)
                    @php
                        $stageColors = [
                            'final' => 'from-yellow-400 to-amber-500 text-gray-900',
                            'semi_final' => 'from-gray-300 to-gray-400 text-gray-900',
                            'qualifier' => 'from-purple-400 to-purple-500 text-white',
                            'eliminator' => 'from-red-400 to-red-500 text-white',
                            'group' => 'from-blue-400 to-blue-500 text-white',
                        ];
                        $stageClass = $stageColors[$match->stage] ?? 'from-gray-400 to-gray-500 text-white';
                    @endphp
                    <span class="inline-flex items-center px-5 py-2 rounded-full text-sm font-bold uppercase tracking-wider bg-gradient-to-r {{ $stageClass }}">
                        @if($match->stage === 'final')
                            <i class="fas fa-crown mr-2"></i>
                        @endif
                        {{ $match->stage_display }}
                    </span>
                @endif

                {{-- Match Number --}}
                @if($match->match_number)
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium glass-card text-gray-300">
                        Match #{{ $match->match_number }}
                    </span>
                @endif

                {{-- Group Badge --}}
                @if($match->group)
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                        {{ $match->group->name }}
                    </span>
                @endif
            </div>

            {{-- Teams Display --}}
            <div class="flex flex-col lg:flex-row items-center justify-center gap-6 lg:gap-8 mb-10">
                {{-- Team A --}}
                <div class="team-card {{ $match->winner_team_id === $match->team_a_id ? 'winner' : '' }} rounded-3xl p-6 md:p-8 w-full lg:w-72 text-center">
                    {{-- Team Logo --}}
                    <div class="relative inline-block mb-5">
                        <div class="w-28 h-28 md:w-32 md:h-32 mx-auto rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-1 {{ $match->winner_team_id === $match->team_a_id ? 'ring-4 ring-green-400 ring-offset-4 ring-offset-transparent' : '' }}">
                            @if($match->teamA?->team_logo)
                                <img src="{{ Storage::url($match->teamA->team_logo) }}"
                                     alt="{{ $match->teamA->name }}"
                                     class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                    <span class="text-3xl font-bold text-gray-400">{{ substr($match->teamA?->name ?? 'A', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($match->winner_team_id === $match->team_a_id)
                            <div class="absolute -top-1 -right-1 w-10 h-10 bg-gradient-to-br from-yellow-400 to-amber-500 rounded-full flex items-center justify-center shadow-lg">
                                <i class="fas fa-trophy text-gray-900"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Team Name --}}
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-3">{{ $match->teamA?->name ?? 'TBA' }}</h2>

                    {{-- Score (if completed) --}}
                    @if($match->result)
                        <div class="score-display {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->team_a_score }}<span class="text-2xl text-gray-400">/{{ $match->result->team_a_wickets }}</span>
                        </div>
                        <p class="text-gray-400 mt-1">({{ number_format($match->result->team_a_overs, 1) }} overs)</p>
                    @endif

                    {{-- Winner Tag --}}
                    @if($match->winner_team_id === $match->team_a_id)
                        <div class="mt-4">
                            <span class="inline-flex items-center px-4 py-1.5 bg-green-500/20 text-green-400 text-sm font-bold rounded-full border border-green-500/30">
                                <i class="fas fa-check mr-1"></i> WINNER
                            </span>
                        </div>
                    @endif
                </div>

                {{-- VS Badge --}}
                <div class="flex-shrink-0 z-20">
                    <div class="vs-badge w-16 h-16 md:w-20 md:h-20 rounded-full flex items-center justify-center animate-float">
                        <span class="text-xl md:text-2xl font-black text-gray-900">VS</span>
                    </div>
                </div>

                {{-- Team B --}}
                <div class="team-card {{ $match->winner_team_id === $match->team_b_id ? 'winner' : '' }} rounded-3xl p-6 md:p-8 w-full lg:w-72 text-center">
                    {{-- Team Logo --}}
                    <div class="relative inline-block mb-5">
                        <div class="w-28 h-28 md:w-32 md:h-32 mx-auto rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-1 {{ $match->winner_team_id === $match->team_b_id ? 'ring-4 ring-green-400 ring-offset-4 ring-offset-transparent' : '' }}">
                            @if($match->teamB?->team_logo)
                                <img src="{{ Storage::url($match->teamB->team_logo) }}"
                                     alt="{{ $match->teamB->name }}"
                                     class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                    <span class="text-3xl font-bold text-gray-400">{{ substr($match->teamB?->name ?? 'B', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($match->winner_team_id === $match->team_b_id)
                            <div class="absolute -top-1 -right-1 w-10 h-10 bg-gradient-to-br from-yellow-400 to-amber-500 rounded-full flex items-center justify-center shadow-lg">
                                <i class="fas fa-trophy text-gray-900"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Team Name --}}
                    <h2 class="text-xl md:text-2xl font-bold text-white mb-3">{{ $match->teamB?->name ?? 'TBA' }}</h2>

                    {{-- Score (if completed) --}}
                    @if($match->result)
                        <div class="score-display {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->team_b_score }}<span class="text-2xl text-gray-400">/{{ $match->result->team_b_wickets }}</span>
                        </div>
                        <p class="text-gray-400 mt-1">({{ number_format($match->result->team_b_overs, 1) }} overs)</p>
                    @endif

                    {{-- Winner Tag --}}
                    @if($match->winner_team_id === $match->team_b_id)
                        <div class="mt-4">
                            <span class="inline-flex items-center px-4 py-1.5 bg-green-500/20 text-green-400 text-sm font-bold rounded-full border border-green-500/30">
                                <i class="fas fa-check mr-1"></i> WINNER
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Result Summary (if completed) --}}
            @if($match->result?->result_summary)
                <div class="text-center mb-8">
                    <div class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-gradient-to-r from-yellow-500/20 to-amber-500/10 border border-yellow-500/30">
                        <i class="fas fa-star text-yellow-400"></i>
                        <p class="text-xl md:text-2xl font-bold text-yellow-400">{{ $match->result->result_summary }}</p>
                        <i class="fas fa-star text-yellow-400"></i>
                    </div>
                </div>
            @endif

            {{-- Upcoming Match Countdown (if not completed) --}}
            @if($match->status === 'scheduled' && $match->match_date)
                <div class="text-center mb-8">
                    <div class="countdown-box inline-flex items-center gap-6 px-8 py-5 rounded-2xl">
                        <div class="text-center">
                            <div class="text-3xl md:text-4xl font-bold text-yellow-400" id="countdown-days">--</div>
                            <div class="text-xs text-gray-400 uppercase tracking-wider">Days</div>
                        </div>
                        <div class="text-2xl text-gray-600">:</div>
                        <div class="text-center">
                            <div class="text-3xl md:text-4xl font-bold text-yellow-400" id="countdown-hours">--</div>
                            <div class="text-xs text-gray-400 uppercase tracking-wider">Hours</div>
                        </div>
                        <div class="text-2xl text-gray-600">:</div>
                        <div class="text-center">
                            <div class="text-3xl md:text-4xl font-bold text-yellow-400" id="countdown-mins">--</div>
                            <div class="text-xs text-gray-400 uppercase tracking-wider">Mins</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Match Details Section --}}
    <section class="py-12 bg-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                {{-- Date --}}
                @if($match->match_date)
                    <div class="info-card rounded-2xl p-5 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                            <i class="far fa-calendar-alt text-white text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Date</p>
                        <p class="text-white font-semibold">{{ $match->match_date->format('D, M d') }}</p>
                        <p class="text-gray-400 text-sm">{{ $match->match_date->format('Y') }}</p>
                    </div>
                @endif

                {{-- Time --}}
                @if($match->match_time)
                    <div class="info-card rounded-2xl p-5 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
                            <i class="far fa-clock text-white text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Time</p>
                        <p class="text-white font-semibold">{{ $match->match_time->format('h:i A') }}</p>
                    </div>
                @endif

                {{-- Venue --}}
                @if($match->venue)
                    <div class="info-card rounded-2xl p-5 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-white text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Venue</p>
                        <p class="text-white font-semibold text-sm">{{ $match->venue }}</p>
                    </div>
                @endif

                {{-- Format --}}
                @if($tournament->settings?->overs_per_match)
                    <div class="info-card rounded-2xl p-5 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center">
                            <i class="fas fa-baseball-ball text-white text-lg"></i>
                        </div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Format</p>
                        <p class="text-white font-semibold">{{ $tournament->settings->overs_per_match }} Overs</p>
                    </div>
                @endif
            </div>

            {{-- Toss Info --}}
            @if($match->result?->toss_winner_id)
                <div class="mt-6 text-center">
                    <div class="inline-flex items-center gap-3 px-6 py-3 glass-card rounded-full">
                        <i class="fas fa-coins text-yellow-400"></i>
                        <span class="text-gray-300">
                            <strong class="text-white">{{ $match->result->toss_winner_id === $match->team_a_id ? $match->teamA?->name : $match->teamB?->name }}</strong>
                            won the toss and elected to <strong class="text-yellow-400">{{ $match->result->toss_decision }}</strong>
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Match Awards Section --}}
    @if($match->matchAwards->count() > 0)
        <section class="py-16 bg-gradient-to-b from-gray-900 to-gray-800">
            <div class="max-w-5xl mx-auto px-4">
                <div class="text-center mb-10">
                    <span class="inline-flex items-center px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-trophy mr-2"></i>Recognition
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-white">Match Awards</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($match->matchAwards as $award)
                        <div class="award-card rounded-2xl p-6">
                            <div class="flex items-center gap-4">
                                {{-- Player Image --}}
                                <div class="flex-shrink-0">
                                    <div class="w-20 h-20 rounded-full bg-gray-800 overflow-hidden border-3 border-yellow-500/50">
                                        @if($award->player?->image_path)
                                            <img src="{{ Storage::url($award->player->image_path) }}" alt="{{ $award->player->name }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-500">
                                                {{ substr($award->player?->name ?? '?', 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Award Info --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-2xl">{{ $award->tournamentAward?->icon ?? '🏆' }}</span>
                                        <span class="text-sm text-yellow-400 font-semibold uppercase tracking-wider">{{ $award->tournamentAward?->name ?? 'Award' }}</span>
                                    </div>
                                    <h4 class="text-xl font-bold text-white">{{ $award->player?->name ?? 'Unknown' }}</h4>
                                    @if($award->remarks)
                                        <p class="text-sm text-gray-400 mt-1">{{ $award->remarks }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Action Buttons --}}
    <section class="py-16 bg-gradient-to-b from-gray-800 to-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            <div class="flex flex-wrap justify-center gap-4 mb-12">
                @if($match->result)
                    <a href="{{ route('public.match.scorecard', $match->slug) }}"
                       class="action-btn px-8 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-gray-900 font-bold rounded-xl shadow-lg hover:shadow-yellow-500/25 flex items-center gap-3">
                        <i class="fas fa-list-ol"></i>
                        View Scorecard
                    </a>
                    <a href="{{ route('public.match.summary', $match->slug) }}"
                       class="action-btn px-8 py-4 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold rounded-xl shadow-lg hover:shadow-purple-500/25 flex items-center gap-3">
                        <i class="fas fa-chart-pie"></i>
                        Match Summary
                    </a>
                @endif
                <a href="{{ route('public.match.poster', $match->slug) }}"
                   class="action-btn px-8 py-4 glass-card hover:bg-white/10 text-white font-semibold rounded-xl flex items-center gap-3">
                    <i class="fas fa-image"></i>
                    Match Poster
                </a>
            </div>

            {{-- Share Section --}}
            <div class="text-center">
                <h3 class="text-gray-400 text-sm uppercase tracking-wider mb-6">Share This Match</h3>
                <div class="flex flex-wrap justify-center gap-4">
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareUrl = route('public.match.show', $match->slug);
                        $shareText = ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA') . ' - ' . $tournament->name;
                    @endphp

                    {{-- WhatsApp --}}
                    <a href="{{ $whatsappService->getMatchShareLink($match) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white shadow-lg hover:shadow-green-500/30">
                        <i class="fab fa-whatsapp text-2xl"></i>
                    </a>

                    {{-- Twitter --}}
                    <a href="https://twitter.com/intent/tweet?text={{ urlencode($shareText) }}&url={{ urlencode($shareUrl) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-blue-400 to-blue-500 flex items-center justify-center text-white shadow-lg hover:shadow-blue-500/30">
                        <i class="fab fa-twitter text-2xl"></i>
                    </a>

                    {{-- Facebook --}}
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center text-white shadow-lg hover:shadow-blue-600/30">
                        <i class="fab fa-facebook-f text-2xl"></i>
                    </a>

                    {{-- Copy Link --}}
                    <button onclick="copyToClipboard('{{ $shareUrl }}')"
                            class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center text-white shadow-lg hover:shadow-gray-500/30">
                        <i class="fas fa-link text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Other Matches Section --}}
    @if($otherMatches->count() > 0)
        <section class="py-16 bg-gray-900">
            <div class="max-w-5xl mx-auto px-4">
                <div class="text-center mb-10">
                    <span class="inline-flex items-center px-4 py-2 bg-blue-500/20 text-blue-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-calendar-alt mr-2"></i>More Matches
                    </span>
                    <h2 class="text-3xl font-bold text-white">Other Matches</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($otherMatches as $otherMatch)
                        <a href="{{ route('public.match.show', $otherMatch->slug) }}"
                           class="match-card rounded-2xl overflow-hidden border border-gray-700/50">
                            {{-- Match Header --}}
                            <div class="bg-gray-800/50 px-4 py-2 flex items-center justify-between text-xs">
                                <span class="text-gray-400">
                                    {{ $otherMatch->match_date->format('M d') }}
                                </span>
                                @if($otherMatch->status === 'completed')
                                    <span class="text-green-400 font-medium">Completed</span>
                                @elseif($otherMatch->status === 'live')
                                    <span class="text-red-400 font-medium animate-pulse">LIVE</span>
                                @else
                                    <span class="text-gray-500">{{ $otherMatch->match_time?->format('h:i A') ?? 'TBA' }}</span>
                                @endif
                            </div>

                            {{-- Teams --}}
                            <div class="p-5">
                                <div class="flex items-center justify-between gap-3">
                                    {{-- Team A --}}
                                    <div class="flex items-center gap-3 flex-1">
                                        <div class="w-10 h-10 rounded-full bg-gray-700 overflow-hidden flex-shrink-0">
                                            @if($otherMatch->teamA?->team_logo)
                                                <img src="{{ Storage::url($otherMatch->teamA->team_logo) }}" alt="{{ $otherMatch->teamA->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-400">
                                                    {{ substr($otherMatch->teamA?->short_name ?? 'A', 0, 2) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-semibold text-white text-sm {{ $otherMatch->winner_team_id === $otherMatch->team_a_id ? 'text-green-400' : '' }}">
                                                {{ $otherMatch->teamA?->short_name ?? 'TBA' }}
                                            </p>
                                            @if($otherMatch->result)
                                                <p class="text-xs text-gray-400">{{ $otherMatch->result->team_a_score }}/{{ $otherMatch->result->team_a_wickets }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <span class="text-gray-600 text-xs font-bold">vs</span>

                                    {{-- Team B --}}
                                    <div class="flex items-center gap-3 flex-1 justify-end text-right">
                                        <div>
                                            <p class="font-semibold text-white text-sm {{ $otherMatch->winner_team_id === $otherMatch->team_b_id ? 'text-green-400' : '' }}">
                                                {{ $otherMatch->teamB?->short_name ?? 'TBA' }}
                                            </p>
                                            @if($otherMatch->result)
                                                <p class="text-xs text-gray-400">{{ $otherMatch->result->team_b_score }}/{{ $otherMatch->result->team_b_wickets }}</p>
                                            @endif
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-gray-700 overflow-hidden flex-shrink-0">
                                            @if($otherMatch->teamB?->team_logo)
                                                <img src="{{ Storage::url($otherMatch->teamB->team_logo) }}" alt="{{ $otherMatch->teamB->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-xs font-bold text-gray-400">
                                                    {{ substr($otherMatch->teamB?->short_name ?? 'B', 0, 2) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="text-center mt-10">
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="inline-flex items-center px-8 py-4 glass-card hover:bg-white/10 text-white font-semibold rounded-xl transition">
                        View All Fixtures
                        <i class="fas fa-arrow-right ml-3"></i>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Quick Navigation --}}
    <section class="py-16 bg-gradient-to-b from-gray-900 to-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-6 text-center hover:bg-white/10 transition-all group">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-calendar-alt text-xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white">Fixtures</h3>
                </a>

                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-6 text-center hover:bg-white/10 transition-all group">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-table text-xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white">Points Table</h3>
                </a>

                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-6 text-center hover:bg-white/10 transition-all group">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-chart-bar text-xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white">Statistics</h3>
                </a>

                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-6 text-center hover:bg-white/10 transition-all group">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-users text-xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white">Teams</h3>
                </a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-full shadow-xl z-50 flex items-center gap-2';
        toast.innerHTML = '<i class="fas fa-check-circle"></i><span>Link copied!</span>';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}

@if($match->status === 'scheduled' && $match->match_date)
// Countdown Timer
const matchDate = new Date('{{ $match->match_date->format("Y-m-d") }}T{{ $match->match_time ? $match->match_time->format("H:i:s") : "00:00:00" }}');

function updateCountdown() {
    const now = new Date();
    const diff = matchDate - now;

    if (diff <= 0) {
        document.getElementById('countdown-days').textContent = '0';
        document.getElementById('countdown-hours').textContent = '0';
        document.getElementById('countdown-mins').textContent = '0';
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    document.getElementById('countdown-days').textContent = days;
    document.getElementById('countdown-hours').textContent = hours;
    document.getElementById('countdown-mins').textContent = mins;
}

updateCountdown();
setInterval(updateCountdown, 60000);
@endif
</script>
@endpush
