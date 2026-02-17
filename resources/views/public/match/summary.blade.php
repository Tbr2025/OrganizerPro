@extends('public.tournament.layouts.app')

@section('title', 'Match Summary - ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'))

@section('meta')
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $result->result_summary ?? 'Match Summary' }}" />
    <meta property="og:description" content="{{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }} - {{ $tournament->name }}" />
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
    .winner-glow {
        box-shadow: 0 0 60px rgba(34, 197, 94, 0.4), 0 0 100px rgba(34, 197, 94, 0.2);
    }
    .gold-glow {
        box-shadow: 0 0 40px rgba(251, 191, 36, 0.4);
    }
    .team-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .team-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
    }
    .team-card.winner {
        background: linear-gradient(145deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
        border: 2px solid rgba(34, 197, 94, 0.5);
    }
    .score-text {
        font-size: clamp(3rem, 8vw, 5rem);
        font-weight: 800;
        line-height: 1;
    }
    .motm-card {
        background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 50%, #4c1d95 100%);
        position: relative;
        overflow: hidden;
    }
    .motm-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: shimmer 3s ease-in-out infinite;
    }
    @keyframes shimmer {
        0%, 100% { transform: translate(-30%, -30%); }
        50% { transform: translate(30%, 30%); }
    }
    .award-card {
        background: linear-gradient(145deg, rgba(251, 191, 36, 0.15) 0%, rgba(251, 191, 36, 0.05) 100%);
        border: 1px solid rgba(251, 191, 36, 0.3);
        transition: all 0.3s ease;
    }
    .award-card:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 30px rgba(251, 191, 36, 0.2);
    }
    .highlight-card {
        background: linear-gradient(145deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.02) 100%);
        border-left: 4px solid #3b82f6;
    }
    .result-banner {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.1) 100%);
        border: 1px solid rgba(251, 191, 36, 0.3);
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
    .vs-badge {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        box-shadow: 0 10px 40px rgba(251, 191, 36, 0.4);
    }
    .stat-pill {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .share-btn {
        transition: all 0.3s ease;
    }
    .share-btn:hover {
        transform: translateY(-3px);
    }
</style>
@endpush

@section('content')
    {{-- Hero Section with Match Result --}}
    <section class="relative min-h-screen hero-gradient hero-pattern overflow-hidden">
        {{-- Background Decorations --}}
        <div class="absolute top-20 left-10 w-72 h-72 bg-yellow-500/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-40 right-10 w-96 h-96 bg-green-500/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-500/5 rounded-full blur-3xl"></div>

        <div class="relative z-10 max-w-5xl mx-auto px-4 py-12">
            {{-- Tournament Badge --}}
            <div class="text-center mb-8">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="inline-flex items-center gap-3 px-5 py-2 glass-card rounded-full hover:bg-white/10 transition">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="w-8 h-8 rounded-full object-cover">
                    @endif
                    <span class="text-gray-300 font-medium">{{ $tournament->name }}</span>
                </a>
            </div>

            {{-- Match Stage Badge --}}
            <div class="text-center mb-6">
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
                <span class="inline-flex items-center px-6 py-2 rounded-full text-sm font-bold uppercase tracking-wider bg-gradient-to-r {{ $stageClass }}">
                    @if($match->stage === 'final')
                        <i class="fas fa-crown mr-2"></i>
                    @endif
                    {{ $match->stage_display }}
                    @if($match->match_number)
                        <span class="ml-2 opacity-70">#{{ $match->match_number }}</span>
                    @endif
                </span>
            </div>

            {{-- Page Title --}}
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-center mb-12">
                <span class="gradient-text">Match Result</span>
            </h1>

            {{-- Teams Score Display --}}
            <div class="flex flex-col lg:flex-row items-center justify-center gap-6 lg:gap-10 mb-10">
                {{-- Team A Card --}}
                <div class="team-card {{ $match->winner_team_id === $match->team_a_id ? 'winner winner-glow' : '' }} rounded-3xl p-8 w-full lg:w-80 text-center">
                    {{-- Team Logo --}}
                    <div class="relative inline-block mb-6">
                        <div class="w-32 h-32 mx-auto rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-1 {{ $match->winner_team_id === $match->team_a_id ? 'ring-4 ring-green-400 ring-offset-4 ring-offset-gray-900' : '' }}">
                            @if($match->teamA?->team_logo)
                                <img src="{{ Storage::url($match->teamA->team_logo) }}"
                                     alt="{{ $match->teamA->name }}"
                                     class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                    <span class="text-4xl font-bold text-gray-400">{{ substr($match->teamA?->name ?? 'A', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($match->winner_team_id === $match->team_a_id)
                            <div class="absolute -top-2 -right-2 w-12 h-12 bg-gradient-to-br from-yellow-400 to-amber-500 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                <i class="fas fa-trophy text-gray-900 text-lg"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Team Name --}}
                    <h2 class="text-2xl font-bold text-white mb-4">{{ $match->teamA?->name ?? 'Team A' }}</h2>

                    {{-- Score --}}
                    <div class="score-text {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400' : 'text-white' }}">
                        {{ $result->team_a_score ?? 0 }}<span class="text-3xl text-gray-400">/{{ $result->team_a_wickets ?? 0 }}</span>
                    </div>
                    <p class="text-gray-400 text-lg mt-2">({{ number_format($result->team_a_overs ?? 0, 1) }} overs)</p>

                    {{-- Extras --}}
                    @if($result->team_a_extras)
                        <p class="text-sm text-gray-500 mt-2">Extras: {{ $result->team_a_extras }}</p>
                    @endif

                    {{-- Winner Badge --}}
                    @if($match->winner_team_id === $match->team_a_id)
                        <div class="mt-6">
                            <span class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-full shadow-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                WINNER
                            </span>
                        </div>
                    @endif
                </div>

                {{-- VS Badge --}}
                <div class="flex-shrink-0 z-20">
                    <div class="vs-badge w-20 h-20 lg:w-24 lg:h-24 rounded-full flex items-center justify-center animate-float">
                        <span class="text-2xl lg:text-3xl font-black text-gray-900">VS</span>
                    </div>
                </div>

                {{-- Team B Card --}}
                <div class="team-card {{ $match->winner_team_id === $match->team_b_id ? 'winner winner-glow' : '' }} rounded-3xl p-8 w-full lg:w-80 text-center">
                    {{-- Team Logo --}}
                    <div class="relative inline-block mb-6">
                        <div class="w-32 h-32 mx-auto rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-1 {{ $match->winner_team_id === $match->team_b_id ? 'ring-4 ring-green-400 ring-offset-4 ring-offset-gray-900' : '' }}">
                            @if($match->teamB?->team_logo)
                                <img src="{{ Storage::url($match->teamB->team_logo) }}"
                                     alt="{{ $match->teamB->name }}"
                                     class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                    <span class="text-4xl font-bold text-gray-400">{{ substr($match->teamB?->name ?? 'B', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        @if($match->winner_team_id === $match->team_b_id)
                            <div class="absolute -top-2 -right-2 w-12 h-12 bg-gradient-to-br from-yellow-400 to-amber-500 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                <i class="fas fa-trophy text-gray-900 text-lg"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Team Name --}}
                    <h2 class="text-2xl font-bold text-white mb-4">{{ $match->teamB?->name ?? 'Team B' }}</h2>

                    {{-- Score --}}
                    <div class="score-text {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400' : 'text-white' }}">
                        {{ $result->team_b_score ?? 0 }}<span class="text-3xl text-gray-400">/{{ $result->team_b_wickets ?? 0 }}</span>
                    </div>
                    <p class="text-gray-400 text-lg mt-2">({{ number_format($result->team_b_overs ?? 0, 1) }} overs)</p>

                    {{-- Extras --}}
                    @if($result->team_b_extras)
                        <p class="text-sm text-gray-500 mt-2">Extras: {{ $result->team_b_extras }}</p>
                    @endif

                    {{-- Winner Badge --}}
                    @if($match->winner_team_id === $match->team_b_id)
                        <div class="mt-6">
                            <span class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-full shadow-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                WINNER
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Result Summary Banner --}}
            @if($result->result_summary)
                <div class="result-banner rounded-2xl p-6 text-center max-w-2xl mx-auto mb-10">
                    <div class="flex items-center justify-center gap-3">
                        <i class="fas fa-star text-yellow-400 text-xl"></i>
                        <p class="text-xl md:text-2xl font-bold text-yellow-400">{{ $result->result_summary }}</p>
                        <i class="fas fa-star text-yellow-400 text-xl"></i>
                    </div>
                </div>
            @endif

            {{-- Toss Info --}}
            @if($result->toss_winner_id)
                <div class="text-center mb-10">
                    <div class="inline-flex items-center gap-2 px-5 py-2 stat-pill rounded-full text-gray-400">
                        <i class="fas fa-coins text-yellow-400"></i>
                        <span>
                            {{ $result->toss_winner_id === $match->team_a_id ? $match->teamA?->short_name : $match->teamB?->short_name }}
                            won the toss and elected to {{ $result->toss_decision }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- Match Details Pills --}}
            <div class="flex flex-wrap items-center justify-center gap-4 mb-10">
                @if($match->match_date)
                    <div class="stat-pill rounded-full px-5 py-2 flex items-center gap-2 text-gray-300">
                        <i class="far fa-calendar text-blue-400"></i>
                        <span>{{ $match->match_date->format('D, M d, Y') }}</span>
                    </div>
                @endif
                @if($match->venue)
                    <div class="stat-pill rounded-full px-5 py-2 flex items-center gap-2 text-gray-300">
                        <i class="fas fa-map-marker-alt text-red-400"></i>
                        <span>{{ $match->venue }}</span>
                    </div>
                @endif
                @if($match->match_time)
                    <div class="stat-pill rounded-full px-5 py-2 flex items-center gap-2 text-gray-300">
                        <i class="far fa-clock text-purple-400"></i>
                        <span>{{ $match->match_time }}</span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Man of the Match Section --}}
    @php
        $motmAward = $match->matchAwards->first(function($award) {
            return $award->tournamentAward && str_contains(strtolower($award->tournamentAward->name), 'man of the match');
        });
    @endphp
    @if($motmAward)
        <section class="py-16 bg-gray-900 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-gray-900 via-purple-900/20 to-gray-900"></div>
            <div class="relative max-w-4xl mx-auto px-4">
                <div class="text-center mb-8">
                    <span class="inline-flex items-center px-4 py-2 bg-purple-500/20 text-purple-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-star mr-2"></i>Star Performer
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-white">Man of the Match</h2>
                </div>

                <div class="motm-card rounded-3xl p-8 md:p-12 text-white text-center gold-glow">
                    <div class="relative inline-block mb-6">
                        <div class="w-36 h-36 md:w-44 md:h-44 mx-auto rounded-full bg-white/10 p-1 ring-4 ring-yellow-400 ring-offset-4 ring-offset-purple-900">
                            @if($motmAward->player?->image_path)
                                <img src="{{ Storage::url($motmAward->player->image_path) }}"
                                     alt="{{ $motmAward->player->name }}"
                                     class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-purple-800 flex items-center justify-center">
                                    <span class="text-5xl font-bold text-purple-300">{{ substr($motmAward->player?->name ?? 'M', 0, 1) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="absolute -bottom-3 left-1/2 transform -translate-x-1/2">
                            <span class="text-5xl">{{ $motmAward->tournamentAward->icon ?? '🏆' }}</span>
                        </div>
                    </div>

                    <h3 class="text-3xl md:text-4xl font-bold mb-2">{{ $motmAward->player?->name ?? 'Unknown Player' }}</h3>

                    @if($motmAward->remarks)
                        <p class="text-lg text-purple-200 max-w-md mx-auto mt-4">{{ $motmAward->remarks }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Other Awards Section --}}
    @php
        $otherAwards = $match->matchAwards->filter(function($award) {
            return !$award->tournamentAward || !str_contains(strtolower($award->tournamentAward->name), 'man of the match');
        });
    @endphp
    @if($otherAwards->count() > 0)
        <section class="py-16 bg-gradient-to-b from-gray-900 to-gray-800">
            <div class="max-w-5xl mx-auto px-4">
                <div class="text-center mb-10">
                    <span class="inline-flex items-center px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-trophy mr-2"></i>Recognition
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-white">Match Awards</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($otherAwards as $award)
                        <div class="award-card rounded-2xl p-6 text-center">
                            <div class="text-4xl mb-4">{{ $award->tournamentAward?->icon ?? '🏆' }}</div>
                            <p class="text-sm text-yellow-400 font-semibold uppercase tracking-wider mb-3">{{ $award->tournamentAward?->name ?? 'Award' }}</p>

                            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 overflow-hidden border-2 border-yellow-500/50">
                                @if($award->player?->image_path)
                                    <img src="{{ Storage::url($award->player->image_path) }}" alt="{{ $award->player->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-2xl font-bold text-gray-500">
                                        {{ substr($award->player?->name ?? '?', 0, 1) }}
                                    </div>
                                @endif
                            </div>

                            <h4 class="text-xl font-bold text-white">{{ $award->player?->name ?? 'Unknown' }}</h4>

                            @if($award->remarks)
                                <p class="text-sm text-gray-400 mt-2">{{ $award->remarks }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Match Highlights Section --}}
    @if($match->summary && $match->summary->hasHighlights())
        <section class="py-16 bg-gray-800">
            <div class="max-w-4xl mx-auto px-4">
                <div class="text-center mb-10">
                    <span class="inline-flex items-center px-4 py-2 bg-blue-500/20 text-blue-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-bolt mr-2"></i>Key Moments
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-white">Match Highlights</h2>
                </div>

                <div class="space-y-4">
                    @foreach($match->summary->highlights as $index => $highlight)
                        <div class="highlight-card rounded-xl p-5 flex items-start gap-4 transition-all hover:bg-blue-500/10">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold shadow-lg">
                                {{ $index + 1 }}
                            </div>
                            <p class="text-lg text-gray-200 pt-1">{{ $highlight }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Commentary Section --}}
    @if($match->summary && $match->summary->commentary)
        <section class="py-16 bg-gradient-to-b from-gray-800 to-gray-900">
            <div class="max-w-4xl mx-auto px-4">
                <div class="text-center mb-10">
                    <span class="inline-flex items-center px-4 py-2 bg-green-500/20 text-green-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-comment-dots mr-2"></i>Analysis
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-white">Match Commentary</h2>
                </div>

                <div class="glass-card rounded-2xl p-8">
                    <p class="text-lg text-gray-300 leading-relaxed whitespace-pre-line">{{ $match->summary->commentary }}</p>
                </div>
            </div>
        </section>
    @endif

    {{-- Quick Actions --}}
    <section class="py-16 bg-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            {{-- Action Buttons --}}
            <div class="flex flex-wrap justify-center gap-4 mb-12">
                <a href="{{ route('public.match.show', $match->slug) }}"
                   class="px-8 py-4 glass-card hover:bg-white/10 text-white font-semibold rounded-xl transition-all flex items-center gap-3">
                    <i class="fas fa-arrow-left"></i>
                    Back to Match
                </a>
                <a href="{{ route('public.match.scorecard', $match->slug) }}"
                   class="px-8 py-4 bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-gray-900 font-bold rounded-xl transition-all flex items-center gap-3 shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-list-ol"></i>
                    View Full Scorecard
                </a>
            </div>

            {{-- Share Section --}}
            <div class="text-center">
                <h3 class="text-gray-400 text-sm uppercase tracking-wider mb-6">Share This Result</h3>
                <div class="flex flex-wrap justify-center gap-4">
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareUrl = route('public.match.summary', $match->slug);
                        $shareText = ($result->result_summary ?? 'Match Result') . ' - ' . $tournament->name;
                    @endphp

                    {{-- WhatsApp --}}
                    <a href="{{ $whatsappService->getResultShareLink($match) }}"
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

    {{-- Related Matches --}}
    <section class="py-16 bg-gradient-to-b from-gray-900 to-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="text-center mb-10">
                <h2 class="text-2xl font-bold text-white">Explore More</h2>
            </div>

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
        // Show toast
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-green-600 text-white px-6 py-3 rounded-full shadow-xl z-50 flex items-center gap-2';
        toast.innerHTML = '<i class="fas fa-check-circle"></i><span>Link copied!</span>';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}
</script>
@endpush
