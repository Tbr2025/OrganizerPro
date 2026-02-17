@extends('public.tournament.layouts.app')

@section('title', 'Scorecard - ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'))

@section('meta')
    <meta name="description" content="Full Scorecard - {{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }} - {{ $tournament->name }}">
    <meta property="og:title" content="Scorecard: {{ $match->teamA?->short_name ?? 'TBA' }} vs {{ $match->teamB?->short_name ?? 'TBA' }}" />
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
    .gradient-text {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #fbbf24 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .innings-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .innings-header {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.15) 0%, rgba(251, 191, 36, 0.05) 100%);
        border-bottom: 1px solid rgba(251, 191, 36, 0.2);
    }
    .innings-header.team-b {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%);
        border-bottom: 1px solid rgba(59, 130, 246, 0.2);
    }
    .table-header {
        background: rgba(255, 255, 255, 0.03);
    }
    .table-row {
        transition: all 0.2s ease;
    }
    .table-row:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    .top-scorer {
        background: linear-gradient(90deg, rgba(34, 197, 94, 0.1) 0%, transparent 100%);
    }
    .top-wicket {
        background: linear-gradient(90deg, rgba(168, 85, 247, 0.1) 0%, transparent 100%);
    }
    .stat-badge {
        background: rgba(251, 191, 36, 0.15);
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
    .bowling-section {
        background: rgba(0, 0, 0, 0.2);
    }
    .fow-card {
        background: linear-gradient(145deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.02) 100%);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    .extras-row {
        background: rgba(251, 191, 36, 0.05);
    }
    .total-row {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.15) 0%, rgba(251, 191, 36, 0.05) 100%);
    }
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    .share-btn {
        transition: all 0.3s ease;
    }
    .share-btn:hover {
        transform: translateY(-3px);
    }
    .tab-btn {
        transition: all 0.3s ease;
    }
    .tab-btn.active {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #111827;
    }
    .tab-btn:not(.active):hover {
        background: rgba(255, 255, 255, 0.1);
    }
</style>
@endpush

@section('content')
    {{-- Hero Section --}}
    <section class="relative hero-gradient hero-pattern overflow-hidden">
        {{-- Background Decorations --}}
        <div class="absolute top-10 left-5 w-48 h-48 bg-yellow-500/10 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-10 right-5 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl animate-float" style="animation-delay: 2s;"></div>

        <div class="relative z-10 max-w-5xl mx-auto px-4 py-10">
            {{-- Tournament Badge --}}
            <div class="text-center mb-6">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="inline-flex items-center gap-3 px-5 py-2 glass-card rounded-full hover:bg-white/10 transition">
                    @if($tournament->settings?->logo)
                        <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="w-8 h-8 rounded-full object-cover">
                    @endif
                    <span class="text-gray-300 font-medium">{{ $tournament->name }}</span>
                </a>
            </div>

            {{-- Page Title --}}
            <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-center mb-8">
                <span class="gradient-text">Full Scorecard</span>
            </h1>

            {{-- Match Summary Card --}}
            <div class="glass-card rounded-2xl p-6 max-w-3xl mx-auto">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    {{-- Team A --}}
                    <div class="flex items-center gap-4 flex-1">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-0.5 {{ $match->winner_team_id === $match->team_a_id ? 'ring-2 ring-green-400' : '' }}">
                            @if($match->teamA?->team_logo)
                                <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="{{ $match->teamA->name }}" class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gray-700 flex items-center justify-center">
                                    <span class="text-xl font-bold text-gray-400">{{ substr($match->teamA?->name ?? 'A', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h2 class="font-bold text-lg {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400' : 'text-white' }}">
                                {{ $match->teamA?->short_name ?? 'TBA' }}
                                @if($match->winner_team_id === $match->team_a_id)
                                    <i class="fas fa-trophy text-yellow-400 ml-1 text-sm"></i>
                                @endif
                            </h2>
                            @if($match->result)
                                <p class="text-2xl font-bold text-white">
                                    {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                    <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_a_overs }} ov)</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- VS --}}
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-lg">
                            <span class="text-sm font-black text-gray-900">VS</span>
                        </div>
                    </div>

                    {{-- Team B --}}
                    <div class="flex items-center gap-4 flex-1 md:flex-row-reverse md:text-right">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-gray-700 to-gray-800 p-0.5 {{ $match->winner_team_id === $match->team_b_id ? 'ring-2 ring-green-400' : '' }}">
                            @if($match->teamB?->team_logo)
                                <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="{{ $match->teamB->name }}" class="w-full h-full rounded-full object-cover">
                            @else
                                <div class="w-full h-full rounded-full bg-gray-700 flex items-center justify-center">
                                    <span class="text-xl font-bold text-gray-400">{{ substr($match->teamB?->name ?? 'B', 0, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h2 class="font-bold text-lg {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400' : 'text-white' }}">
                                {{ $match->teamB?->short_name ?? 'TBA' }}
                                @if($match->winner_team_id === $match->team_b_id)
                                    <i class="fas fa-trophy text-yellow-400 ml-1 text-sm"></i>
                                @endif
                            </h2>
                            @if($match->result)
                                <p class="text-2xl font-bold text-white">
                                    {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                    <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_b_overs }} ov)</span>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Result Summary --}}
                @if($match->result?->result_summary)
                    <div class="mt-6 pt-4 border-t border-white/10 text-center">
                        <p class="text-yellow-400 font-semibold flex items-center justify-center gap-2">
                            <i class="fas fa-star text-sm"></i>
                            {{ $match->result->result_summary }}
                            <i class="fas fa-star text-sm"></i>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Scorecard Content --}}
    <section class="py-10 bg-gray-900">
        <div class="max-w-5xl mx-auto px-4">
            @php
                $firstBattingTeam = $match->result?->team_a_batting_first ? $match->teamA : $match->teamB;
                $secondBattingTeam = $match->result?->team_a_batting_first ? $match->teamB : $match->teamA;
                $firstBowlingTeam = $match->result?->team_a_batting_first ? $match->teamB : $match->teamA;
                $secondBowlingTeam = $match->result?->team_a_batting_first ? $match->teamA : $match->teamB;

                $firstBattingScore = $match->result?->team_a_batting_first
                    ? "{$match->result->team_a_score}/{$match->result->team_a_wickets}"
                    : "{$match->result->team_b_score}/{$match->result->team_b_wickets}";
                $firstBattingOvers = $match->result?->team_a_batting_first
                    ? $match->result->team_a_overs
                    : $match->result->team_b_overs;

                $secondBattingScore = $match->result?->team_a_batting_first
                    ? "{$match->result->team_b_score}/{$match->result->team_b_wickets}"
                    : "{$match->result->team_a_score}/{$match->result->team_a_wickets}";
                $secondBattingOvers = $match->result?->team_a_batting_first
                    ? $match->result->team_b_overs
                    : $match->result->team_a_overs;
            @endphp

            {{-- First Innings --}}
            <div class="innings-card rounded-2xl overflow-hidden mb-8">
                {{-- Innings Header --}}
                <div class="innings-header px-6 py-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gray-800/50 overflow-hidden">
                                @if($firstBattingTeam?->team_logo)
                                    <img src="{{ Storage::url($firstBattingTeam->team_logo) }}" alt="{{ $firstBattingTeam->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-lg font-bold text-gray-400">
                                        {{ substr($firstBattingTeam?->name ?? 'A', 0, 2) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <span class="text-xs text-yellow-400 uppercase tracking-wider font-semibold">1st Innings</span>
                                <h2 class="text-xl font-bold text-white">{{ $firstBattingTeam?->name ?? 'Team A' }}</h2>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-bold text-yellow-400">{{ $firstBattingScore }}</p>
                            <p class="text-sm text-gray-400">({{ $firstBattingOvers }} overs)</p>
                        </div>
                    </div>
                </div>

                {{-- Batting Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                <th class="px-6 py-4 text-left font-semibold">Batter</th>
                                <th class="px-4 py-4 text-left font-semibold">Dismissal</th>
                                <th class="px-4 py-4 text-center font-semibold">R</th>
                                <th class="px-4 py-4 text-center font-semibold">B</th>
                                <th class="px-4 py-4 text-center font-semibold">4s</th>
                                <th class="px-4 py-4 text-center font-semibold">6s</th>
                                <th class="px-4 py-4 text-center font-semibold">SR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full bg-gray-800 flex items-center justify-center">
                                            <i class="fas fa-cricket-bat-ball text-2xl text-gray-600"></i>
                                        </div>
                                        <p class="text-gray-500">Detailed batting scorecard will be available when ball-by-ball data is entered.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Bowling Section --}}
                <div class="bowling-section">
                    <div class="px-6 py-3 border-t border-white/5">
                        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-baseball-ball text-purple-400"></i>
                            Bowling - {{ $firstBowlingTeam?->short_name ?? 'Team B' }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-6 py-3 text-left font-semibold">Bowler</th>
                                    <th class="px-4 py-3 text-center font-semibold">O</th>
                                    <th class="px-4 py-3 text-center font-semibold">M</th>
                                    <th class="px-4 py-3 text-center font-semibold">R</th>
                                    <th class="px-4 py-3 text-center font-semibold">W</th>
                                    <th class="px-4 py-3 text-center font-semibold">Econ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Bowling figures will be available when ball-by-ball data is entered.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Second Innings --}}
            <div class="innings-card rounded-2xl overflow-hidden mb-8">
                {{-- Innings Header --}}
                <div class="innings-header team-b px-6 py-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gray-800/50 overflow-hidden">
                                @if($secondBattingTeam?->team_logo)
                                    <img src="{{ Storage::url($secondBattingTeam->team_logo) }}" alt="{{ $secondBattingTeam->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-lg font-bold text-gray-400">
                                        {{ substr($secondBattingTeam?->name ?? 'B', 0, 2) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <span class="text-xs text-blue-400 uppercase tracking-wider font-semibold">2nd Innings</span>
                                <h2 class="text-xl font-bold text-white">{{ $secondBattingTeam?->name ?? 'Team B' }}</h2>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-bold text-blue-400">{{ $secondBattingScore }}</p>
                            <p class="text-sm text-gray-400">({{ $secondBattingOvers }} overs)</p>
                        </div>
                    </div>
                </div>

                {{-- Batting Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                <th class="px-6 py-4 text-left font-semibold">Batter</th>
                                <th class="px-4 py-4 text-left font-semibold">Dismissal</th>
                                <th class="px-4 py-4 text-center font-semibold">R</th>
                                <th class="px-4 py-4 text-center font-semibold">B</th>
                                <th class="px-4 py-4 text-center font-semibold">4s</th>
                                <th class="px-4 py-4 text-center font-semibold">6s</th>
                                <th class="px-4 py-4 text-center font-semibold">SR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full bg-gray-800 flex items-center justify-center">
                                            <i class="fas fa-cricket-bat-ball text-2xl text-gray-600"></i>
                                        </div>
                                        <p class="text-gray-500">Detailed batting scorecard will be available when ball-by-ball data is entered.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Bowling Section --}}
                <div class="bowling-section">
                    <div class="px-6 py-3 border-t border-white/5">
                        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-baseball-ball text-purple-400"></i>
                            Bowling - {{ $secondBowlingTeam?->short_name ?? 'Team A' }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="table-header text-xs uppercase tracking-wider text-gray-400">
                                    <th class="px-6 py-3 text-left font-semibold">Bowler</th>
                                    <th class="px-4 py-3 text-center font-semibold">O</th>
                                    <th class="px-4 py-3 text-center font-semibold">M</th>
                                    <th class="px-4 py-3 text-center font-semibold">R</th>
                                    <th class="px-4 py-3 text-center font-semibold">W</th>
                                    <th class="px-4 py-3 text-center font-semibold">Econ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Bowling figures will be available when ball-by-ball data is entered.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Fall of Wickets --}}
            <div class="fow-card rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center">
                        <i class="fas fa-arrow-down text-red-400"></i>
                    </div>
                    Fall of Wickets
                </h3>
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-layer-group text-2xl text-gray-600"></i>
                    </div>
                    <p class="text-gray-500">Fall of wickets data will be available when ball-by-ball scoring is enabled.</p>
                </div>
            </div>

            {{-- Match Info Card --}}
            <div class="glass-card rounded-2xl p-6 mb-8">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    Match Information
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @if($match->match_date)
                        <div class="text-center p-4 rounded-xl bg-white/5">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Date</p>
                            <p class="text-white font-semibold">{{ $match->match_date->format('M d, Y') }}</p>
                        </div>
                    @endif
                    @if($match->venue)
                        <div class="text-center p-4 rounded-xl bg-white/5">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Venue</p>
                            <p class="text-white font-semibold text-sm">{{ $match->venue }}</p>
                        </div>
                    @endif
                    @if($match->result?->toss_winner_id)
                        <div class="text-center p-4 rounded-xl bg-white/5">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Toss</p>
                            <p class="text-white font-semibold text-sm">
                                {{ $match->result->toss_winner_id === $match->team_a_id ? $match->teamA?->short_name : $match->teamB?->short_name }}
                                ({{ $match->result->toss_decision }})
                            </p>
                        </div>
                    @endif
                    @if($tournament->settings?->overs_per_match)
                        <div class="text-center p-4 rounded-xl bg-white/5">
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Format</p>
                            <p class="text-white font-semibold">{{ $tournament->settings->overs_per_match }} Overs</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Action Buttons --}}
    <section class="py-12 bg-gradient-to-b from-gray-900 to-gray-800">
        <div class="max-w-4xl mx-auto px-4">
            <div class="flex flex-wrap justify-center gap-4 mb-10">
                <a href="{{ route('public.match.show', $match->slug) }}"
                   class="px-8 py-4 glass-card hover:bg-white/10 text-white font-semibold rounded-xl transition-all flex items-center gap-3">
                    <i class="fas fa-arrow-left"></i>
                    Back to Match
                </a>
                <a href="{{ route('public.match.summary', $match->slug) }}"
                   class="px-8 py-4 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-bold rounded-xl shadow-lg hover:shadow-purple-500/25 transition-all flex items-center gap-3">
                    <i class="fas fa-chart-pie"></i>
                    Match Summary
                </a>
            </div>

            {{-- Share Section --}}
            <div class="text-center">
                <h3 class="text-gray-400 text-sm uppercase tracking-wider mb-6">Share Scorecard</h3>
                <div class="flex flex-wrap justify-center gap-4">
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareUrl = route('public.match.scorecard', $match->slug);
                        $shareText = 'Scorecard: ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA') . ' - ' . $tournament->name;
                    @endphp

                    <a href="https://wa.me/?text={{ urlencode($shareText . ' ' . $shareUrl) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white shadow-lg hover:shadow-green-500/30">
                        <i class="fab fa-whatsapp text-2xl"></i>
                    </a>

                    <a href="https://twitter.com/intent/tweet?text={{ urlencode($shareText) }}&url={{ urlencode($shareUrl) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-blue-400 to-blue-500 flex items-center justify-center text-white shadow-lg hover:shadow-blue-500/30">
                        <i class="fab fa-twitter text-2xl"></i>
                    </a>

                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
                       target="_blank"
                       class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center text-white shadow-lg hover:shadow-blue-600/30">
                        <i class="fab fa-facebook-f text-2xl"></i>
                    </a>

                    <button onclick="copyToClipboard('{{ $shareUrl }}')"
                            class="share-btn w-14 h-14 rounded-full bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center text-white shadow-lg hover:shadow-gray-500/30">
                        <i class="fas fa-link text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Quick Navigation --}}
    <section class="py-12 bg-gray-800">
        <div class="max-w-5xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-5 text-center hover:bg-white/10 transition-all group">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-calendar-alt text-lg text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white text-sm">Fixtures</h3>
                </a>

                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-5 text-center hover:bg-white/10 transition-all group">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-table text-lg text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white text-sm">Points Table</h3>
                </a>

                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-5 text-center hover:bg-white/10 transition-all group">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-chart-bar text-lg text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white text-sm">Statistics</h3>
                </a>

                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="glass-card rounded-2xl p-5 text-center hover:bg-white/10 transition-all group">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-users text-lg text-white"></i>
                    </div>
                    <h3 class="font-semibold text-white text-sm">Teams</h3>
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
</script>
@endpush
