@extends('backend.layouts.app')

@section('title', 'Live Scoring | ' . ($match->name ?? 'Match'))

@push('styles')
<style>
    .score-card {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    }
    .run-btn {
        transition: all 0.2s ease;
    }
    .run-btn:hover {
        transform: scale(1.05);
    }
    .run-btn:active {
        transform: scale(0.95);
    }
    .ball-indicator {
        transition: all 0.3s ease;
    }
    .ball-dot { @apply bg-gray-400; }
    .ball-1 { @apply bg-blue-500; }
    .ball-2 { @apply bg-green-500; }
    .ball-3 { @apply bg-purple-500; }
    .ball-4 { @apply bg-yellow-500; }
    .ball-6 { @apply bg-red-500; }
    .ball-w { @apply bg-red-700; }
    .ball-wd { @apply bg-orange-400; }
    .ball-nb { @apply bg-pink-500; }
    .current-over {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => 'Live Scoring']
]" />

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Scoring Area -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Live Score Card -->
        <div class="score-card rounded-2xl p-6 text-white relative overflow-hidden">
            <!-- Status Badge & Innings -->
            <div class="absolute top-4 right-4 flex items-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase bg-indigo-500">
                    {{ \App\Http\Controllers\Backend\MatchesController::ordinal((int) ($currentInnings ?? 1)) }} Inn{{ ($totalInnings ?? 2) > 2 ? ' of ' . $totalInnings : '' }}
                </span>
                <span id="powerplayBadge" class="{{ ($inPowerplay ?? false) ? '' : 'hidden' }} inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-400 text-black">
                    Powerplay
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase
                    {{ $match->status === 'live' ? 'bg-red-500 animate-pulse' : ($match->status === 'completed' ? 'bg-green-500' : 'bg-yellow-500') }}">
                    @if($match->status === 'live')
                        <span class="w-2 h-2 bg-white rounded-full mr-2 animate-ping"></span>
                    @endif
                    {{ ucfirst($match->status ?? 'Scheduled') }}
                </span>
            </div>

            <!-- Tournament & Match Info -->
            <div class="text-center mb-4">
                <p class="text-gray-400 text-sm">{{ $match->tournament->name ?? 'Tournament' }}</p>
                <h2 class="text-xl font-bold mt-1">{{ $match->name ?? 'Match' }}</h2>
                @can('ledwall.operate')
                <a href="{{ route('admin.matches.led', $match) }}" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/20 text-xs font-semibold">
                    <iconify-icon icon="lucide:monitor" width="14"></iconify-icon> LED wall console
                </a>
                @endcan

                <!-- Toss Section -->
                <div id="toss-section" class="mt-3">
                    @if($match->toss_winner_team_id)
                        <!-- Toss Result Display -->
                        <div id="toss-result" class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-500/20 rounded-full">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 110-12 6 6 0 010 12z"/>
                            </svg>
                            <span class="text-yellow-400 text-sm">
                                <strong>{{ $match->tossWinner?->name }}</strong> won toss, elected to <strong>{{ $match->toss_decision === 'bat' ? 'BAT' : 'BOWL' }}</strong>
                            </span>
                            <button onclick="showTossSelector()" class="ml-2 text-yellow-400/70 hover:text-yellow-400 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                        </div>
                    @elsecan('match.edit')
                        <!-- Toss Not Set - Show Button. Setting the toss changes the match. -->
                        <button id="toss-btn" onclick="showTossSelector()"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-black font-semibold rounded-full transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 110-12 6 6 0 010 12z"/>
                            </svg>
                            Set Toss
                        </button>
                    @endif

                    <!-- Toss Selector (Hidden by default) -->
                    <div id="toss-selector" class="hidden mt-4 p-4 bg-gray-800/80 rounded-xl backdrop-blur max-w-md mx-auto">
                        <h4 class="text-white font-semibold mb-3">Who won the toss?</h4>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <button type="button" onclick="selectTossWinner({{ $match->team_a_id }}, '{{ $match->teamA?->name }}')"
                                    class="toss-team-btn p-3 rounded-lg border-2 border-transparent bg-gray-700 hover:border-blue-500 transition text-center"
                                    data-team="{{ $match->team_a_id }}">
                                @if($match->teamA?->logo)
                                    <img src="{{ asset('storage/' . $match->teamA->logo) }}" class="w-10 h-10 mx-auto rounded-full mb-1">
                                @endif
                                <span class="text-white text-sm font-medium">{{ $match->teamA?->name }}</span>
                            </button>
                            <button type="button" onclick="selectTossWinner({{ $match->team_b_id }}, '{{ $match->teamB?->name }}')"
                                    class="toss-team-btn p-3 rounded-lg border-2 border-transparent bg-gray-700 hover:border-green-500 transition text-center"
                                    data-team="{{ $match->team_b_id }}">
                                @if($match->teamB?->logo)
                                    <img src="{{ asset('storage/' . $match->teamB->logo) }}" class="w-10 h-10 mx-auto rounded-full mb-1">
                                @endif
                                <span class="text-white text-sm font-medium">{{ $match->teamB?->name }}</span>
                            </button>
                        </div>

                        <div id="toss-decision-section" class="hidden">
                            <h4 class="text-white font-semibold mb-3"><span id="toss-winner-name"></span> elected to:</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" onclick="saveToss('bat')"
                                        class="p-3 rounded-lg bg-blue-600 hover:bg-blue-700 transition text-white font-bold flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    BAT
                                </button>
                                <button type="button" onclick="saveToss('bowl')"
                                        class="p-3 rounded-lg bg-green-600 hover:bg-green-700 transition text-white font-bold flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    BOWL
                                </button>
                            </div>
                        </div>

                        <button onclick="hideTossSelector()" class="mt-4 text-gray-400 hover:text-white text-sm transition">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            @php
                // Determine batting team based on toss
                // If toss winner elected to BAT, they bat first (innings 1)
                // If toss winner elected to BOWL, the other team bats first
                $firstBattingTeamId = $match->team_a_id; // Default: Team A bats first

                if ($match->batting_first_team_id) {
                    // Fixed from the toss when scoring started (BallRecorder::applyToss).
                    $firstBattingTeamId = $match->batting_first_team_id;
                } elseif ($match->toss_winner_team_id && $match->toss_decision) {
                    if ($match->toss_decision === 'bat') {
                        $firstBattingTeamId = $match->toss_winner_team_id;
                    } else {
                        // Toss winner chose to bowl, so other team bats first
                        $firstBattingTeamId = ($match->toss_winner_team_id == $match->team_a_id)
                            ? $match->team_b_id
                            : $match->team_a_id;
                    }
                }

                // Check if Team A bats first
                $teamABatsFirst = $firstBattingTeamId == $match->team_a_id;

                // Each side's score from MatchStateService: its innings added up.
                $teamAStats = $teamAStats ?? [];
                $teamBStats = $teamBStats ?? [];

                // Fallback to match_results when no ball data exists
                $hasBallData = ($teamAStats['runs'] ?? 0) > 0 || ($teamBStats['runs'] ?? 0) > 0;
                $scoreSource = 'live'; // default: ball-by-ball
                if (!$hasBallData && $match->result) {
                    // Use result's batting order if available
                    if ($match->result->team_a_batting_first !== null) {
                        $teamABatsFirst = (bool) $match->result->team_a_batting_first;
                    }
                    $teamAStats = [
                        'runs' => $match->result->team_a_score ?? 0,
                        'wickets' => $match->result->team_a_wickets ?? 0,
                        'overs' => $match->result->team_a_overs ?? '0.0',
                    ];
                    $teamBStats = [
                        'runs' => $match->result->team_b_score ?? 0,
                        'wickets' => $match->result->team_b_wickets ?? 0,
                        'overs' => $match->result->team_b_overs ?? '0.0',
                    ];
                    $scoreSource = $match->result->scorecard_data ? 'cricheroes' : 'manual';
                }

                // Build display order: first batting team on LEFT, second on RIGHT
                $leftTeam = $teamABatsFirst ? $match->teamA : $match->teamB;
                $rightTeam = $teamABatsFirst ? $match->teamB : $match->teamA;
                $leftStats = $teamABatsFirst ? $teamAStats : $teamBStats;
                $rightStats = $teamABatsFirst ? $teamBStats : $teamAStats;
                $leftId = $teamABatsFirst ? 'teamA' : 'teamB';
                $rightId = $teamABatsFirst ? 'teamB' : 'teamA';

                // Determine which team is currently batting (for live scoring highlight)
                $currentBattingTeamId = $battingTeam?->id ?? $firstBattingTeamId;

                $isLeftBatting = $currentBattingTeamId == $leftTeam?->id;
                $isRightBatting = $currentBattingTeamId == $rightTeam?->id;

                // Check if team has batted yet
                if ($hasBallData) {
                    $leftHasBatted = true;
                    $rightHasBatted = ($rightStats['batted'] ?? false) || ($currentInnings ?? 1) >= 2;
                } else {
                    $leftHasBatted = ($leftStats['runs'] ?? 0) > 0 || ($leftStats['wickets'] ?? 0) > 0;
                    $rightHasBatted = ($rightStats['runs'] ?? 0) > 0 || ($rightStats['wickets'] ?? 0) > 0;
                }
            @endphp

            <!-- Teams & Score (First Batting on Left) -->
            <div class="flex items-center justify-between mt-6">
                <!-- First Batting Team -->
                <div class="flex-1 text-center {{ $isLeftBatting ? 'ring-2 ring-green-400 rounded-xl p-2' : '' }}">
                    @if($leftTeam?->team_logo)
                        <img src="{{ Storage::url($leftTeam->team_logo) }}" alt=""
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 {{ $isLeftBatting ? 'border-green-400' : 'border-white/30' }} mb-2">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-gray-600 flex items-center justify-center text-xl font-bold mb-2">
                            {{ substr($leftTeam?->name ?? 'A', 0, 2) }}
                        </div>
                    @endif
                    <h3 class="font-bold">{{ $leftTeam?->short_name ?? $leftTeam?->name ?? 'Team A' }}</h3>
                    @if($isLeftBatting)
                        <span class="text-xs text-green-400">BATTING</span>
                    @elseif(!$leftHasBatted)
                        <span class="text-xs text-gray-500 italic">Yet to bat</span>
                    @else
                        <span class="text-xs text-yellow-400/70">{{ count($leftStats['innings'] ?? []) > 1 ? 'Total ' . ($leftStats['runs'] ?? 0) : '1st Innings' }}</span>
                    @endif
                    <div class="text-3xl font-black mt-2" id="{{ $leftId }}Score">
                        @if($leftHasBatted || ($leftStats['runs'] ?? 0) > 0)
                            {{ count($leftStats['innings'] ?? []) > 1 ? $leftStats['display'] : ($leftStats['runs'] ?? 0) . '/' . ($leftStats['wickets'] ?? 0) }}
                        @else
                            <span class="text-gray-500">-</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-400" id="{{ $leftId }}Overs">
                        @if($leftHasBatted || ($leftStats['runs'] ?? 0) > 0)
                            ({{ $leftStats['overs'] ?? '0.0' }} ov)
                        @endif
                    </div>
                </div>

                <!-- VS -->
                <div class="flex-shrink-0 px-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                        <span class="text-white font-bold text-sm">VS</span>
                    </div>
                </div>

                <!-- Second Batting Team -->
                <div class="flex-1 text-center {{ $isRightBatting ? 'ring-2 ring-green-400 rounded-xl p-2' : '' }}">
                    @if($rightTeam?->team_logo)
                        <img src="{{ Storage::url($rightTeam->team_logo) }}" alt=""
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 {{ $isRightBatting ? 'border-green-400' : 'border-white/30' }} mb-2">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-gray-600 flex items-center justify-center text-xl font-bold mb-2">
                            {{ substr($rightTeam?->name ?? 'B', 0, 2) }}
                        </div>
                    @endif
                    <h3 class="font-bold">{{ $rightTeam?->short_name ?? $rightTeam?->name ?? 'Team B' }}</h3>
                    @if($isRightBatting)
                        <span class="text-xs text-green-400">BATTING</span>
                    @elseif(!$rightHasBatted)
                        <span class="text-xs text-gray-500 italic">Yet to bat</span>
                    @else
                        <span class="text-xs text-blue-400/70">{{ count($rightStats['innings'] ?? []) > 1 ? 'Total ' . ($rightStats['runs'] ?? 0) : '2nd Innings' }}</span>
                    @endif
                    <div class="text-3xl font-black mt-2" id="{{ $rightId }}Score">
                        @if($rightHasBatted || ($rightStats['runs'] ?? 0) > 0)
                            {{ count($rightStats['innings'] ?? []) > 1 ? $rightStats['display'] : ($rightStats['runs'] ?? 0) . '/' . ($rightStats['wickets'] ?? 0) }}
                        @else
                            <span class="text-gray-500">-</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-400" id="{{ $rightId }}Overs">
                        @if($rightHasBatted || ($rightStats['runs'] ?? 0) > 0)
                            ({{ $rightStats['overs'] ?? '0.0' }} ov)
                        @endif
                    </div>
                </div>
            </div>

            {{-- Result Summary --}}
            @if($match->result?->result_summary && $match->status === 'completed')
                <div class="mt-4 pt-3 border-t border-white/10 text-center">
                    <p class="text-yellow-400 font-semibold text-sm">
                        {{ $match->result->result_summary }}
                    </p>
                </div>
            @endif

            {{-- Score Source Badge --}}
            @if(isset($scoreSource) && $scoreSource !== 'live')
                <div class="mt-3 text-center">
                    @if($scoreSource === 'cricheroes')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-teal-500/20 text-teal-300 border border-teal-500/30">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Synced from CricHeroes
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-300 border border-blue-500/30">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Manually Entered
                        </span>
                    @endif
                </div>
            @endif

            <!-- Target Info (2nd innings) -->
            @if(!empty($chase))
                <div class="mt-4 pt-4 border-t border-white/10 text-center" id="chaseLine">
                    <p class="text-yellow-400 font-semibold">
                        Target: {{ $chase['target'] }} |
                        Need {{ $chase['runs_needed'] }} off {{ $chase['balls_left'] }} balls
                        @if($chase['required_run_rate'] !== null)
                            | RRR: {{ number_format($chase['required_run_rate'], 2) }}
                        @endif
                    </p>
                </div>
            @endif

            {{-- At the crease: the two batters, the bowler, the stand. Filled by refreshMatchState(). --}}
            <div id="creasePanel" class="mt-6 pt-4 border-t border-white/10 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm {{ ($crease['striker'] ?? null) || ($crease['nonStriker'] ?? null) ? '' : 'hidden' }}">
                <div class="rounded-xl bg-white/5 p-3 space-y-1.5">
                    <p class="text-[11px] uppercase tracking-wider text-gray-400">Batting</p>
                    <div class="flex justify-between gap-2"><span id="creaseStrikerName" class="font-semibold truncate"></span><span id="creaseStrikerLine" class="tabular-nums text-gray-200"></span></div>
                    <div class="flex justify-between gap-2"><span id="creaseNonStrikerName" class="truncate text-gray-300"></span><span id="creaseNonStrikerLine" class="tabular-nums text-gray-400"></span></div>
                    <p class="text-xs text-gray-400 pt-1">Partnership <span id="creasePartnership" class="text-gray-200 tabular-nums"></span></p>
                </div>
                <div class="rounded-xl bg-white/5 p-3 space-y-1.5">
                    <p class="text-[11px] uppercase tracking-wider text-gray-400">Bowling <span class="normal-case tracking-normal">· max {{ $bowlerQuota }} ov each</span></p>
                    <div class="flex justify-between gap-2"><span id="creaseBowlerName" class="font-semibold truncate">—</span><span id="creaseBowlerLine" class="tabular-nums text-gray-200"></span></div>
                    <p class="text-xs text-gray-400 pt-1">Last wicket <span id="creaseLastWicket" class="text-gray-200"></span></p>
                </div>
            </div>

            <!-- Current Over Display -->
            <div class="mt-6 pt-4 border-t border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400 flex items-center gap-2">This over
                        <span id="freeHitBadge" class="{{ ($freeHit ?? false) ? '' : 'hidden' }} px-2 py-0.5 rounded-full bg-amber-400 text-black text-[11px] font-bold uppercase tracking-wide animate-pulse">Free hit</span>
                    </span>
                    <span class="text-sm text-gray-400">Run Rate: <span class="text-white font-bold" id="runRate">{{ number_format($runRate ?? 0, 2) }}</span></span>
                </div>
                <div class="flex items-center gap-2 mt-2 flex-wrap" id="currentOverBalls">
                    @php
                        $lastOver = collect($summary)->last();
                        $currentBalls = $lastOver['balls'] ?? [];
                        // Count legal deliveries (exclude wides and no-balls)
                        $legalBalls = collect($currentBalls)->filter(fn($b) => !str_contains($b, 'wd') && !str_contains($b, 'nb'))->count();
                        $remainingBalls = max(0, 6 - $legalBalls);
                    @endphp
                    {{-- Show all bowled balls --}}
                    @foreach($currentBalls as $ball)
                        @php
                            $ballClass = 'bg-gray-700';
                            if ($ball === 'W') $ballClass = 'bg-red-600';
                            elseif (str_contains($ball, 'wd')) $ballClass = 'bg-orange-500';
                            elseif (str_contains($ball, 'nb')) $ballClass = 'bg-pink-500';
                            elseif (str_contains($ball, 'lb')) $ballClass = 'bg-purple-500';
                            elseif (str_contains($ball, 'b') && !str_contains($ball, 'nb')) $ballClass = 'bg-blue-400';
                            elseif ($ball === '4') $ballClass = 'bg-yellow-500';
                            elseif ($ball === '6') $ballClass = 'bg-green-500';
                            elseif ($ball === '0') $ballClass = 'bg-gray-500';
                            else $ballClass = 'bg-blue-500';
                        @endphp
                        <div class="w-10 h-10 rounded-full {{ $ballClass }} flex items-center justify-center text-white font-bold text-sm">
                            {{ $ball }}
                        </div>
                    @endforeach
                    {{-- Show remaining empty slots for legal deliveries --}}
                    @for($i = 0; $i < $remainingBalls; $i++)
                        <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-white font-bold text-sm">-</div>
                    @endfor
                </div>
            </div>
        </div>

        <!-- Quick Scoring Buttons -->
        @can('match.edit')
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Quick Score Entry
                </h3>
            </div>
            <div class="p-6">
                @include('backend.pages.matches.partials.format-panel')

                <!-- Select Batsmen & Bowler -->
                <div id="overAlert" class="mb-4 p-3 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 rounded-xl {{ ($overComplete ?? false) ? '' : 'hidden' }}">
                    <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">Over complete — choose the next bowler. The bowler who just bowled cannot bowl this over.</p>
                </div>
                <div id="wicketAlert" class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded-xl {{ ($needsNewBatsman ?? false) ? '' : 'hidden' }}">
                    <p class="text-yellow-800 dark:text-yellow-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Wicket! Please select the new batsman.
                    </p>
                </div>

                @php
                    // From MatchStateService: overs bowled, all out (by the
                    // side's size), or — in a chase — the target reached.
                    $allWicketsDown = $isAllOut ?? false;
                    $inningsComplete = $inningsComplete ?? false;
                @endphp

                @php
                    // Innings in this match (2 × innings each) and where we are in them.
                    $ord = fn (int $n) => \App\Http\Controllers\Backend\MatchesController::ordinal($n);
                    $inn = (int) ($currentInnings ?? 1);
                    $lastInnings = (int) ($totalInnings ?? 2);
                    $isLastInnings = $inn >= $lastInnings;
                    $ended = (bool) $match->scoring_ended_at;
                @endphp

                @if($inningsComplete && ! $isLastInnings && ! ($matchComplete ?? false))
                <!-- An innings complete - start the next -->
                <div class="mb-4 p-4 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 rounded-xl">
                    <p class="text-blue-800 dark:text-blue-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $ord($inn) }} Innings Complete{{ $allWicketsDown ? ' (All Out)' : '' }}! {{ $battingTeam?->name }} scored {{ $totalRuns }}/{{ $totalWickets }} ({{ $totalOvers }} ov)
                    </p>
                    <form action="{{ route('admin.matches.switchInnings', $match) }}" method="POST" class="mt-3">
                        @csrf <input type="hidden" name="to" value="{{ $inn + 1 }}">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Start {{ $ord($inn + 1) }} Innings ({{ $bowlingTeam?->name }} batting)
                        </button>
                    </form>
                </div>
                @elseif($inningsComplete && ($isLastInnings || ($matchComplete ?? false)))
                <!-- Last innings complete (or won by an innings) - Match Over -->
                <div id="inningsCompleteAlert" class="mb-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 rounded-xl">
                    <p class="text-green-800 dark:text-green-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Match Complete{{ $allWicketsDown ? ' (All Out)' : '' }}! {{ $battingTeam?->name }} scored {{ $totalRuns }}/{{ $totalWickets }} ({{ $totalOvers }} ov)
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('admin.matches.summary.edit', $match) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            View Summary & Poster
                        </a>
                        @can('match.edit')
                        <a href="{{ route('admin.matches.result.edit', $match) }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Result
                        </a>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Innings and match controls. Explicit targets (to=n): a double
                     click lands on the same innings instead of flipping back. --}}
                @if($ended)
                    <div class="mb-4 p-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Match ended — {{ $match->end_reason }}</p>
                        <p class="text-xs text-gray-500 mt-1">No more balls can be scored. Reopen scoring if it was ended by mistake.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ route('admin.matches.summary.edit', $match) }}" class="px-3 py-2 text-sm rounded-lg bg-purple-600 text-white">View Summary &amp; Poster</a>
                            <form method="POST" action="{{ route('admin.matches.reopenScoring', $match) }}" onsubmit="return confirm('Reopen scoring for this match?')">@csrf
                                <button class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600">Reopen scoring</button>
                            </form>
                        </div>
                    </div>
                @else
                    @if($inningsBreak ?? false)
                        <div class="mb-4 p-4 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700">
                            @php
                                // Before the last innings: the target. Before any other: the lead.
                                $batAgg = (int) (($battingTeam?->id === $match->team_a_id ? $teamAStats : $teamBStats)['runs'] ?? 0);
                                $fieldAgg = (int) (($battingTeam?->id === $match->team_a_id ? $teamBStats : $teamAStats)['runs'] ?? 0);
                                $standing = $isLastInnings
                                    ? ($target ? "need {$target} to win" : '')
                                    : ($batAgg === $fieldAgg ? 'scores level' : ($batAgg > $fieldAgg ? 'lead by ' . ($batAgg - $fieldAgg) : 'trail by ' . ($fieldAgg - $batAgg)));
                            @endphp
                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Innings break — {{ $battingTeam?->name }} {{ $standing }}.</p>
                            <p class="text-xs text-amber-800 dark:text-amber-200 mt-1">The LED wall is showing the innings summary and top performers. Pick the openers and bowler below, then score the first ball to start the {{ $ord($inn) }} innings.</p>
                        </div>
                    @endif
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            {{ $ord($inn) }} innings{{ $lastInnings > 2 ? ' of ' . $lastInnings : '' }}: <strong class="text-indigo-600">{{ $battingTeam?->name ?? 'Team' }}</strong> batting
                        </span>
                        <div class="flex flex-wrap gap-2">
                            @if(($inningsBreak ?? false) && $inn > 1)
                                <form action="{{ route('admin.matches.switchInnings', $match) }}" method="POST"
                                      onsubmit="return confirm('Go back to the {{ $ord($inn - 1) }} innings? Use this only if it was ended by mistake.')">
                                    @csrf <input type="hidden" name="to" value="{{ $inn - 1 }}">
                                    <button type="submit" class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600">Back to {{ $ord($inn - 1) }} innings</button>
                                </form>
                            @elseif(! $isLastInnings)
                                <form action="{{ route('admin.matches.switchInnings', $match) }}" method="POST"
                                      onsubmit="return confirm('End the {{ $ord($inn) }} innings now and start the innings break?')">
                                    @csrf <input type="hidden" name="to" value="{{ $inn + 1 }}">
                                    <button type="submit" class="text-sm px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700">{{ $inn % 2 === 0 || $lastInnings === 2 ? 'End ' . $ord($inn) . ' innings' : 'End / declare ' . $ord($inn) . ' innings' }}</button>
                                </form>
                            @endif
                            <button type="button" onclick="openEndMatch()" class="text-sm px-3 py-1.5 rounded-lg bg-gray-800 text-white hover:bg-gray-900 dark:bg-gray-700">End match</button>
                            <button type="button" onclick="openEndMatch('abandoned')" class="text-sm px-3 py-1.5 rounded-lg border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300">Abandon (rain etc.)</button>
                        </div>
                    </div>

                    {{-- End match --}}
                    <div id="endMatchModal" class="hidden fixed inset-0 z-99999 flex items-center justify-center bg-black/50 p-4" onclick="if (event.target === this) closeEndMatch()">
                        <form method="POST" action="{{ route('admin.matches.endMatch', $match) }}" class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 space-y-3 text-sm">
                            @csrf
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">End match</h3>
                            <p class="text-xs text-gray-500">Saves the result, updates the points table and shows the result on the LED wall. You can reopen scoring afterwards.</p>
                            @if($naturalResult ?? false)
                                <label class="flex items-center gap-2"><input type="radio" name="outcome" value="auto" checked> Use the scored result</label>
                            @endif
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="team_a" @checked(!($naturalResult ?? false))> {{ $match->teamA?->name }} won</label>
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="team_b"> {{ $match->teamB?->name }} won</label>
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="tie"> Tie</label>
                            @if(($totalInnings ?? 2) > 2)
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="draw"> Draw (time ran out)</label>
                            @endif
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="no_result"> No result</label>
                            <label class="flex items-center gap-2"><input type="radio" name="outcome" value="abandoned"> Abandoned</label>
                            <label id="abandonReason" class="hidden flex-col gap-1">Reason
                                <select name="reason" class="form-control">
                                    @foreach(\App\Services\Scoring\MatchFinisher::ABANDON_REASONS as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" onclick="closeEndMatch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600">Cancel</button>
                                <button type="submit" class="px-3 py-2 rounded-lg bg-gray-900 text-white dark:bg-indigo-600">End match</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <span class="inline-flex items-center">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                Striker
                            </span>
                            <span class="text-xs text-gray-500 ml-1">({{ count($outBatsmenIds ?? []) }} out)</span>
                            @can('scoring.quick_add_player')
                            <button type="button" onclick="openQuickPlayer('batting', 'batsmanSelect')" class="ml-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" title="Add a player who is not in the squad">+ Add</button>
                            @endcan
                        </label>
                        <select id="batsmanSelect" class="form-control {{ !isset($currentStriker) && count($summary ?? []) > 0 ? 'ring-2 ring-yellow-400' : '' }}">
                            <option value="">Select Striker</option>
                            @foreach($battingPlayers as $player)
                                @if($player->player)
                                    @php
                                        $isOut = in_array($player->id, $outBatsmenIds ?? []);
                                        $isStriker = isset($currentStriker) && $player->id == $currentStriker;
                                        $isNonStriker = isset($currentNonStriker) && $player->id == $currentNonStriker;
                                    @endphp
                                    <option value="{{ $player->id }}"
                                            {{ $isOut ? 'disabled' : '' }}
                                            {{ $isStriker ? 'selected' : '' }}
                                            {{ $isNonStriker && !$isStriker ? 'hidden' : '' }}
                                            {{ $isOut ? 'data-is-out=true' : '' }}
                                            class="{{ $isOut ? 'text-red-500 line-through' : '' }}">
                                        {{ $player->player->name }}{{ $isOut ? ' (OUT)' : '' }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <span class="inline-flex items-center">
                                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                Non-Striker
                            </span>
                            @can('scoring.quick_add_player')
                            <button type="button" onclick="openQuickPlayer('batting', 'nonStrikerSelect')" class="ml-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" title="Add a player who is not in the squad">+ Add</button>
                            @endcan
                        </label>
                        <select id="nonStrikerSelect" class="form-control">
                            <option value="">Select Non-Striker</option>
                            @foreach($battingPlayers as $player)
                                @if($player->player)
                                    @php
                                        $isOut = in_array($player->id, $outBatsmenIds ?? []);
                                        $isStriker = isset($currentStriker) && $player->id == $currentStriker;
                                        $isNonStriker = isset($currentNonStriker) && $player->id == $currentNonStriker;
                                    @endphp
                                    <option value="{{ $player->id }}"
                                            {{ $isOut ? 'disabled' : '' }}
                                            {{ $isNonStriker ? 'selected' : '' }}
                                            {{ $isStriker && !$isNonStriker ? 'hidden' : '' }}
                                            {{ $isOut ? 'data-is-out=true' : '' }}
                                            class="{{ $isOut ? 'text-red-500 line-through' : '' }}">
                                        {{ $player->player->name }}{{ $isOut ? ' (OUT)' : '' }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <span class="inline-flex items-center">
                                <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                Bowler
                            </span>
                            @can('scoring.quick_add_player')
                            <button type="button" onclick="openQuickPlayer('bowling', 'bowlerSelect')" class="ml-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" title="Add a player who is not in the squad">+ Add</button>
                            @endcan
                        </label>
                        <select id="bowlerSelect" class="form-control">
                            <option value="">Select Bowler</option>
                            @foreach($bowlingPlayers as $player)
                                @if($player->player)
                                    @php
                                        $isBowler = isset($currentBowler) && $player->id == $currentBowler;
                                    @endphp
                                    <option value="{{ $player->id }}" {{ $isBowler ? 'selected' : '' }}>
                                        {{ $player->player->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Swap Batsmen Button -->
                <div class="mb-4">
                    <button type="button" onclick="swapBatsmen()"
                            class="w-full py-2 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl flex items-center justify-center transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Swap Striker / Non-Striker
                    </button>
                </div>

                <!-- Run Buttons -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Runs</label>
                    <div class="grid grid-cols-7 gap-2">
                        @foreach([0, 1, 2, 3, 4, 5, 6] as $run)
                            <button type="button" onclick="recordBall({{ $run }})"
                                    class="run-btn h-14 rounded-xl font-bold text-xl
                                    {{ $run === 4 ? 'bg-yellow-500 hover:bg-yellow-600 text-white' :
                                       ($run === 6 ? 'bg-green-500 hover:bg-green-600 text-white' :
                                       'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600') }}">
                                {{ $run }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Extras -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Extras</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" onclick="showExtraModal('wide')"
                                class="run-btn h-12 rounded-xl font-semibold bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 hover:bg-orange-200">
                            Wide
                        </button>
                        <button type="button" onclick="showExtraModal('no_ball')"
                                class="run-btn h-12 rounded-xl font-semibold bg-pink-100 dark:bg-pink-900/30 text-pink-600 dark:text-pink-400 hover:bg-pink-200">
                            No Ball
                        </button>
                        <button type="button" onclick="showExtraModal('bye')"
                                class="run-btn h-12 rounded-xl font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200">
                            Bye
                        </button>
                        <button type="button" onclick="showExtraModal('leg_bye')"
                                class="run-btn h-12 rounded-xl font-semibold bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 hover:bg-purple-200">
                            Leg Bye
                        </button>
                    </div>
                </div>

                <!-- Wicket -->
                <div>
                    <button type="button" onclick="recordWicket()"
                            class="run-btn w-full h-14 rounded-xl font-bold text-lg bg-red-500 hover:bg-red-600 text-white flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        WICKET
                    </button>
                </div>

                <!-- Undo Last Ball -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="undoLastBall()"
                            class="w-full py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-red-500 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Undo Last Ball
                    </button>
                </div>
            </div>
        </div>
        @endcan

        <!-- Over-by-Over Summary -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4">
                <h3 class="text-white font-bold text-lg">Over Summary</h3>
            </div>
            <div class="p-6 max-h-96 overflow-y-auto" id="overSummary">
                @forelse ($summary as $over)
                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl {{ $loop->last ? 'current-over ring-2 ring-indigo-500' : '' }}">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-gray-700 dark:text-gray-300">Over {{ $over['over'] }}</span>
                            <span class="text-sm">
                                <span class="font-semibold text-blue-600">{{ $over['runs'] }}</span> runs,
                                <span class="font-semibold text-red-600">{{ $over['wickets'] }}</span> wkts
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($over['balls'] as $ball)
                                @php
                                    $ballClass = 'bg-gray-300 dark:bg-gray-600';
                                    if ($ball === 'W') $ballClass = 'bg-red-500 text-white';
                                    elseif (str_contains($ball, 'wd')) $ballClass = 'bg-orange-400 text-white';
                                    elseif (str_contains($ball, 'nb')) $ballClass = 'bg-pink-500 text-white';
                                    elseif (str_contains($ball, 'lb')) $ballClass = 'bg-purple-500 text-white';
                                    elseif (str_contains($ball, 'b') && !str_contains($ball, 'nb')) $ballClass = 'bg-blue-400 text-white';
                                    elseif ($ball === '4') $ballClass = 'bg-yellow-500 text-white';
                                    elseif ($ball === '6') $ballClass = 'bg-green-500 text-white';
                                    elseif ($ball === '0') $ballClass = 'bg-gray-400 text-white';
                                    else $ballClass = 'bg-blue-500 text-white';
                                @endphp
                                <span class="w-8 h-8 rounded-full {{ $ballClass }} flex items-center justify-center text-xs font-bold">
                                    {{ $ball }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <p>No balls recorded yet. Start scoring!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="lg:col-span-1 space-y-6">

        <!-- Match Info -->
        <div class="card rounded-2xl p-6">
            <h3 class="font-bold text-lg mb-4">Match Info</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Date</span>
                    <span class="font-medium">{{ $match->match_date ? \Carbon\Carbon::parse($match->match_date)->format('M d, Y') : '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Time</span>
                    <span class="font-medium">{{ $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Venue</span>
                    <span class="font-medium">{{ $match->venue ?? $match->location ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Overs</span>
                    <span class="font-medium">{{ $match->overs ?? '20' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Innings per team</span>
                    <span class="font-medium">{{ $match->innings_per_team ?: 1 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Players per team</span>
                    <span class="font-medium">{{ $match->players_per_team ?: '11 (standard)' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Max overs per bowler</span>
                    <span class="font-medium">{{ $bowlerQuota }}{{ $match->bowler_overs_limit ? '' : ' (standard)' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Powerplay</span>
                    <span class="font-medium">{{ collect($match->powerplays ?? [])->map(fn ($p) => 'overs ' . $p['from'] . '–' . $p['to'])->implode(', ') ?: 'None' }}</span>
                </div>
                @can('match.edit')
                <p class="text-xs text-gray-400">Change these under <a href="#matchFormat" onclick="document.getElementById('matchFormat').open = true" class="underline">Match format</a> in the scoring panel.</p>
                @endcan
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card rounded-2xl p-6">
            <h3 class="font-bold text-lg mb-4">Actions</h3>
            <div class="space-y-2">
        {{-- Only someone who may change the match is offered the controls that change it.

             Team managers reach this page deliberately: they hold match.view and
             RedirectTeamManager allowlists admin/matches*. They were being shown Record Final
             Result, Match Summary, Edit Match, Set Toss and the whole scoring panel. The
             controllers refuse them now; this stops offering them. Scorers hold match.edit, so
             their tools are untouched. --}}
                @can('match.edit')
                <a href="{{ route('admin.matches.result.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Record Final Result
                </a>
                @if($match->cricheroes_match_url)
                <button type="button" onclick="showSyncConfirmation()" id="syncScoreBtn"
                    class="w-full flex items-center justify-center px-4 py-3 bg-teal-500 hover:bg-teal-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sync Score Now
                </button>
                @endif
                @can('ledwall.operate')
                <a href="{{ route('admin.matches.led', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-gray-900 hover:bg-black text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    LED Wall Console
                </a>
                @endcan
                <a href="{{ route('admin.matches.summary.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Match Summary & Poster
                </a>
                <a href="{{ route('admin.tournaments.templates.generate', $match->tournament) }}?type=award_poster"
                   class="w-full flex items-center justify-center px-4 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    Generate Award Poster
                </a>
                <a href="{{ route('admin.matches.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Match
                </a>
                @endcan
                @if($match->slug)
                <a href="{{ route('public.match.show', $match) }}" target="_blank"
                   class="w-full flex items-center justify-center px-4 py-3 bg-teal-500 hover:bg-teal-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    View Public Fixture
                </a>
                @endif
                @can('match.edit')
                {{-- Poster Template Selector --}}
                <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-3 space-y-2">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Generate Poster</label>
                    <select id="posterTemplateSelect" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">Select Template</option>
                        @php
                            $matchTemplates = $match->tournament->templates()
                                ->where('type', 'match_poster')
                                ->where('is_active', true)
                                ->orderByDesc('is_default')
                                ->get();
                            $summaryTemplates = $match->status === 'completed'
                                ? $match->tournament->templates()
                                    ->where('type', 'match_summary')
                                    ->where('is_active', true)
                                    ->orderByDesc('is_default')
                                    ->get()
                                : collect();
                        @endphp
                        @if($matchTemplates->count() > 0)
                            <optgroup label="Match Poster">
                                @foreach($matchTemplates as $template)
                                    <option value="{{ $template->id }}" {{ $template->is_default ? 'selected' : '' }}>
                                        {{ $template->name }} {{ $template->is_default ? '(Default)' : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($summaryTemplates->count() > 0)
                            <optgroup label="Match Summary">
                                @foreach($summaryTemplates as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($matchTemplates->isEmpty() && $summaryTemplates->isEmpty())
                            <option value="enhanced" selected>Enhanced Poster (Built-in)</option>
                        @else
                            <option value="enhanced">Enhanced Poster (Built-in)</option>
                        @endif
                    </select>
                    <button type="button" onclick="generatePoster()" id="generatePosterBtn"
                       class="w-full flex items-center justify-center px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white font-semibold rounded-lg transition text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Generate & Download
                    </button>
                </div>
                @endcan
            </div>
        </div>

        @include('backend.pages.matches.partials.match-blog')

        <!-- Match Awards -->
        @if($match->matchAwards->count() > 0)
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-yellow-500 to-amber-500 px-4 py-3">
                <h3 class="text-white font-bold">Match Awards</h3>
            </div>
            <div class="p-4 space-y-3">
                @foreach($match->matchAwards as $award)
                    <div class="flex items-center gap-3">
                        @if($award->player?->image_path)
                            <img src="{{ asset('storage/' . $award->player->image_path) }}" class="w-10 h-10 rounded-full object-cover">
                        @else
                            <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        @endif
                        <div>
                            <p class="text-xs font-bold text-yellow-600 uppercase">{{ $award->tournamentAward->name ?? 'Award' }}</p>
                            <p class="text-sm font-medium">{{ $award->player->name ?? 'Unknown' }}
                                @if($award->player?->actualTeam) <span class="text-gray-500">({{ $award->player->actualTeam->name }})</span> @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Batting Team Players -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-4 py-3">
                <h3 class="text-white font-bold">{{ $battingTeam?->name ?? 'Batting Team' }} - Batting</h3>
            </div>
            <div class="p-4 max-h-60 overflow-y-auto">
                <ul class="space-y-2">
                    @forelse($battingPlayers as $player)
                        @if($player->player)
                            @php
                                $isOut = in_array($player->id, $outBatsmenIds ?? []);
                            @endphp
                            <li class="flex items-center justify-between text-sm py-1 {{ $isOut ? 'opacity-50' : '' }}">
                                <span class="{{ $isOut ? 'line-through text-red-500' : '' }}">
                                    {{ $player->player->name }}
                                </span>
                                @if($isOut)
                                    <span class="text-red-500 font-bold">OUT</span>
                                @endif
                            </li>
                        @endif
                    @empty
                        <li class="text-gray-500 text-sm">No players</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Bowling Team Players -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-orange-500 to-red-500 px-4 py-3">
                <h3 class="text-white font-bold">{{ $bowlingTeam?->name ?? 'Bowling Team' }} - Bowling</h3>
            </div>
            <div class="p-4 max-h-60 overflow-y-auto">
                <ul class="space-y-2">
                    @forelse($bowlingPlayers as $player)
                        @if($player->player)
                            <li class="flex items-center justify-between text-sm py-1">
                                <span>{{ $player->player->name }}</span>
                                <span class="text-gray-500">{{ $player->player->playerType?->type ?? '-' }}</span>
                            </li>
                        @endif
                    @empty
                        <li class="text-gray-500 text-sm">No players</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Wicket Modal -->
<div id="wicketModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-4">Record Wicket</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Dismissal Type</label>
                <select id="dismissalType" class="form-control">
                    <option value="bowled">Bowled</option>
                    <option value="caught">Caught</option>
                    <option value="lbw">LBW</option>
                    <option value="run_out">Run Out</option>
                    <option value="stumped">Stumped</option>
                    <option value="hit_wicket">Hit Wicket</option>
                </select>
            </div>
            <div id="freeHitNote" class="hidden p-2.5 rounded-lg bg-amber-50 text-amber-800 text-sm dark:bg-amber-900/30 dark:text-amber-200">
                Free hit — the batter can only be out run out.
            </div>
            <!-- Off a wide or no-ball: only the dismissals the Laws allow -->
            <div id="deliverySection">
                <label for="wicketDelivery" class="block text-sm font-medium mb-1">Delivery</label>
                <select id="wicketDelivery" class="form-control">
                    <option value="legal">Legal ball</option>
                    <option value="wide">Wide (+1 wide)</option>
                    <option value="no_ball">No-ball (+1 no-ball)</option>
                </select>
            </div>
            <!-- Run out: either batter can be out, and runs completed still count -->
            <div id="runOutSection" class="grid grid-cols-2 gap-3" style="display:none">
                <div>
                    <label for="runOutBatter" class="block text-sm font-medium mb-1">Who is out</label>
                    <select id="runOutBatter" class="form-control">
                        <option value="striker">Striker</option>
                        <option value="non_striker">Non-striker</option>
                    </select>
                </div>
                <div>
                    <label for="runOutRuns" class="block text-sm font-medium mb-1">Runs completed</label>
                    <select id="runOutRuns" class="form-control">
                        @foreach([0, 1, 2, 3] as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="fielderSection">
                <label class="block text-sm font-medium mb-1">Fielder / keeper
                    @can('scoring.quick_add_player')
                            <button type="button" onclick="openQuickPlayer('bowling', 'fielderSelect')" class="ml-2 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" title="Add a player who is not in the squad">+ Add</button>
                            @endcan
                </label>
                <select id="fielderSelect" class="form-control">
                    <option value="">Select Fielder</option>
                    @foreach($bowlingPlayers as $player)
                        @if($player->player)
                            <option value="{{ $player->id }}">{{ $player->player->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="closeWicketModal()" class="flex-1 py-2 border border-gray-300 rounded-xl hover:bg-gray-50">Cancel</button>
            <button onclick="confirmWicket()" class="flex-1 py-2 bg-red-500 text-white rounded-xl hover:bg-red-600">Confirm Wicket</button>
        </div>
    </div>
</div>

<!-- Extras Modal -->
<div id="extraModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-4" id="extraModalTitle">Select Runs</h3>
        <input type="hidden" id="extraType" value="">
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400" id="extraDescription">How many runs?</p>
            <!-- No-ball: runs off the bat; the no-ball's own run is added automatically -->
            <div class="grid grid-cols-6 gap-2 hidden" id="noBallRunsGrid">
                @foreach([0, 1, 2, 3, 4, 6] as $run)
                    <button type="button" onclick="confirmNoBall({{ $run }})"
                            class="run-btn h-14 rounded-xl font-bold text-xl
                            {{ $run === 4 ? 'bg-yellow-500 hover:bg-yellow-600 text-white' : ($run === 6 ? 'bg-purple-600 hover:bg-purple-700 text-white' :
                               'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600') }}">
                        {{ $run }}
                    </button>
                @endforeach
            </div>
            <!-- Wide: the penalty run plus anything run or a boundary (wd+4 = 5 wides) -->
            <div class="grid grid-cols-5 gap-2 hidden" id="wideRunsGrid">
                @foreach([0, 1, 2, 3, 4] as $run)
                    <button type="button" onclick="confirmExtra({{ 1 + $run }})"
                            class="run-btn h-14 rounded-xl font-bold text-base
                            {{ $run === 4 ? 'bg-yellow-500 hover:bg-yellow-600 text-white' :
                               'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ $run === 0 ? 'Wd' : 'Wd+' . $run }}
                    </button>
                @endforeach
            </div>
            <!-- Byes / leg byes: runs taken (4 = to the boundary) -->
            <div class="grid grid-cols-5 gap-2" id="extraRunsGrid">
                @foreach([1, 2, 3, 4, 5] as $run)
                    <button type="button" onclick="confirmExtra({{ $run }})"
                            class="run-btn h-14 rounded-xl font-bold text-xl
                            {{ $run === 4 ? 'bg-yellow-500 hover:bg-yellow-600 text-white' :
                               'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                        {{ $run }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="closeExtraModal()" class="flex-1 py-2 border border-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
        </div>
    </div>
</div>

@if($match->cricheroes_match_url)
<!-- Sync CricHeroes Confirmation Modal -->
<div id="syncConfirmModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-2">Sync Score from CricHeroes?</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            This will fetch the latest scores and update the match result. Existing scores will be overwritten.
        </p>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mb-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">CricHeroes URL</p>
            <a href="{{ $match->cricheroes_match_url }}" target="_blank"
               class="text-sm text-teal-600 dark:text-teal-400 hover:underline break-all">
                {{ Str::limit($match->cricheroes_match_url, 60) }}
            </a>
        </div>
        <div class="flex gap-3">
            <button onclick="hideSyncConfirmation()" class="flex-1 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">Cancel</button>
            <button onclick="syncScoreNow()" id="syncNowBtn" class="flex-1 py-2.5 bg-teal-500 hover:bg-teal-600 text-white rounded-xl font-semibold transition">Sync Now</button>
        </div>
    </div>
</div>
@endif


@can('scoring.quick_add_player')
@php
    $qpCountry = $match->tournament?->settings?->default_phone_country
        ?: ($match->tournament?->settings?->default_country ?: config('settings.default_country', 'IN'));
    $qpDial = config('countries.dial_codes.' . $qpCountry, '+91');
    $qpDialCodes = collect(config('countries.dial_codes', []))->unique()->sort()->values();
@endphp
<!-- Quick-add player: a walk-in player by name + phone, without leaving the match -->
<div id="quickPlayerModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[60]">
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold mb-1">Add player</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            To <strong id="qpTeamName"></strong>. They are added to the squad and can play straight away.
        </p>
        <form id="quickPlayerForm" class="space-y-3" onsubmit="submitQuickPlayer(event)">
            <div>
                <label for="qpName" class="block text-sm font-medium mb-1">Name <span class="text-red-500">*</span></label>
                <input id="qpName" type="text" class="form-control" maxlength="100" required autocomplete="off">
            </div>
            <div>
                <label for="qpPhone" class="block text-sm font-medium mb-1">Phone <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select id="qpCode" class="form-control" style="max-width:7rem">
                        @foreach($qpDialCodes as $dial)
                            <option value="{{ $dial }}" {{ $dial === $qpDial ? 'selected' : '' }}>{{ $dial }}</option>
                        @endforeach
                    </select>
                    <input id="qpPhone" type="tel" inputmode="tel" class="form-control flex-1" pattern="[0-9\s\-()]{6,20}" required autocomplete="off">
                </div>
            </div>
            <p id="qpError" class="hidden text-sm text-red-600"></p>
            <div id="qpCandidates" class="hidden rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 text-sm">
                <p class="font-medium mb-2">A player with this phone already exists. Is it one of them?</p>
                <div id="qpCandidateList" class="space-y-1"></div>
                <button type="button" onclick="submitQuickPlayer(null, {force_new: true})" class="mt-2 text-xs underline text-gray-600 dark:text-gray-300">No — add as a new player</button>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeQuickPlayer()" class="flex-1 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">Cancel</button>
                <button type="submit" id="qpSubmit" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-semibold transition">Add player</button>
            </div>
        </form>
    </div>
</div>
@endcan

@push('scripts')
<script>
const matchId = {{ $match->id }};
const csrfToken = '{{ csrf_token() }}';
const matchOversLimit = {{ $match->overs ?? 20 }};
const currentInnings = {{ $currentInnings ?? 1 }};
let currentCompletedOvers = {{ $totalOvers ?? 0 }};
let isInningsComplete = {{ ($inningsComplete ?? false) ? 'true' : 'false' }};
// Cricket rules the page needs between refreshes.
let freeHit = @json((bool) ($freeHit ?? false));
const bowlerQuota = {{ (int) ($bowlerQuota ?? 4) }};
let bowlerStats = @json($crease['bowlers'] ?? []);
document.addEventListener('DOMContentLoaded', () => {
    renderCrease({
        strikerDetails: @json($crease['striker'] ?? null),
        nonStrikerDetails: @json($crease['nonStriker'] ?? null),
        partnership: @json($crease['partnership'] ?? null),
        lastWicket: @json($crease['lastWicket'] ?? null),
    });
    applyOverEnd(@json((bool) ($overComplete ?? false)), @json($lastOverBowler ?? null));
    document.getElementById('bowlerSelect')?.addEventListener('change', () => {
        renderBowlerLine();
        document.getElementById('overAlert')?.classList.add('hidden');
        document.getElementById('bowlerSelect').classList.remove('ring-2', 'ring-blue-400');
    });
});
// The score this page is showing. Sent with each ball so a second scorer's
// change is caught (409) instead of silently stacked on.
let scoreVersion = {{ (int) ($scoreVersion ?? 0) }};

function newEventId() {
    if (window.crypto?.randomUUID) return crypto.randomUUID();
    // RFC4122 v4 from getRandomValues for older browsers.
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c =>
        (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
}

@can('scoring.quick_add_player')
// ============ QUICK-ADD PLAYER ============
// Batting side feeds striker + non-striker; bowling side feeds bowler + fielder.
const quickPlayerTeams = {
    batting: { id: {{ (int) ($battingTeam?->id ?? 0) }}, name: @json($battingTeam?->name ?? 'batting team'), selects: ['batsmanSelect', 'nonStrikerSelect'] },
    bowling: { id: {{ (int) ($bowlingTeam?->id ?? 0) }}, name: @json($bowlingTeam?->name ?? 'bowling team'), selects: ['bowlerSelect', 'fielderSelect'] },
};
let quickPlayerSide = null;
let quickPlayerTarget = null;

function openQuickPlayer(side, targetSelectId) {
    quickPlayerSide = side;
    quickPlayerTarget = targetSelectId;
    document.getElementById('qpTeamName').textContent = quickPlayerTeams[side].name;
    document.getElementById('quickPlayerForm').reset();
    document.getElementById('qpError').classList.add('hidden');
    document.getElementById('qpCandidates').classList.add('hidden');
    const modal = document.getElementById('quickPlayerModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('qpName').focus();
}

function closeQuickPlayer() {
    const modal = document.getElementById('quickPlayerModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function submitQuickPlayer(event, extra = {}) {
    event?.preventDefault();
    const errorEl = document.getElementById('qpError');
    const submitBtn = document.getElementById('qpSubmit');
    errorEl.classList.add('hidden');
    submitBtn.disabled = true;

    try {
        const response = await fetch(`/admin/matches/${matchId}/quick-player`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({
                actual_team_id: quickPlayerTeams[quickPlayerSide].id,
                name: document.getElementById('qpName').value,
                mobile_country_code: document.getElementById('qpCode').value,
                mobile_national_number: document.getElementById('qpPhone').value,
                ...extra,
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
            throw new Error(firstError || data.message || 'Could not add the player.');
        }

        if (data.status === 'match') {
            const list = document.getElementById('qpCandidateList');
            list.innerHTML = '';
            data.candidates.forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'block w-full text-left px-3 py-1.5 rounded-lg bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-gray-600';
                b.textContent = `Use ${c.name}`;
                b.onclick = () => submitQuickPlayer(null, { use_player_id: c.id });
                list.appendChild(b);
            });
            document.getElementById('qpCandidates').classList.remove('hidden');
            return;
        }

        // Added: make them selectable everywhere their side appears, and
        // pick them in the dropdown the scorer opened this from.
        quickPlayerTeams[quickPlayerSide].selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (!select || select.querySelector(`option[value="${data.option.id}"]`)) return;
            select.add(new Option(data.option.name, data.option.id));
        });
        const target = document.getElementById(quickPlayerTarget);
        if (target) {
            target.value = data.option.id;
            target.dispatchEvent(new Event('change'));
        }
        closeQuickPlayer();
    } catch (e) {
        errorEl.textContent = e.message;
        errorEl.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
    }
}
@endcan

// ============ TOSS FUNCTIONS ============
let selectedTossWinner = null;
let selectedTossWinnerName = '';

function showTossSelector() {
    document.getElementById('toss-selector').classList.remove('hidden');
    document.getElementById('toss-result')?.classList.add('hidden');
    document.getElementById('toss-btn')?.classList.add('hidden');
}

function hideTossSelector() {
    document.getElementById('toss-selector').classList.add('hidden');
    document.getElementById('toss-decision-section').classList.add('hidden');
    document.getElementById('toss-result')?.classList.remove('hidden');
    document.getElementById('toss-btn')?.classList.remove('hidden');
    selectedTossWinner = null;
    // Reset team button styles
    document.querySelectorAll('.toss-team-btn').forEach(btn => {
        btn.classList.remove('border-yellow-500', 'bg-yellow-500/20');
        btn.classList.add('border-transparent');
    });
}

function selectTossWinner(teamId, teamName) {
    selectedTossWinner = teamId;
    selectedTossWinnerName = teamName;

    // Highlight selected team
    document.querySelectorAll('.toss-team-btn').forEach(btn => {
        if (btn.dataset.team == teamId) {
            btn.classList.add('border-yellow-500', 'bg-yellow-500/20');
            btn.classList.remove('border-transparent');
        } else {
            btn.classList.remove('border-yellow-500', 'bg-yellow-500/20');
            btn.classList.add('border-transparent');
        }
    });

    // Show decision section
    document.getElementById('toss-winner-name').textContent = teamName;
    document.getElementById('toss-decision-section').classList.remove('hidden');
}

async function saveToss(decision) {
    if (!selectedTossWinner) {
        alert('Please select the team that won the toss');
        return;
    }

    try {
        const response = await fetch(`/admin/matches/${matchId}/toss`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                toss_winner_team_id: selectedTossWinner,
                toss_decision: decision
            })
        });

        const data = await response.json();

        if (data.success) {
            // Update the UI
            const tossSection = document.getElementById('toss-section');
            const decisionText = decision === 'bat' ? 'BAT' : 'BOWL';

            tossSection.innerHTML = `
                <div id="toss-result" class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-500/20 rounded-full">
                    <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 110-12 6 6 0 010 12z"/>
                    </svg>
                    <span class="text-yellow-400 text-sm">
                        <strong>${selectedTossWinnerName}</strong> won toss, elected to <strong>${decisionText}</strong>
                    </span>
                    <button onclick="location.reload()" class="ml-2 text-yellow-400/70 hover:text-yellow-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </button>
                </div>
            `;
        } else {
            alert(data.message || 'Failed to save toss');
        }
    } catch (error) {
        console.error('Error saving toss:', error);
        alert('Failed to save toss. Please try again.');
    }
}
// ============ END TOSS FUNCTIONS ============

// Check if innings is complete
// Update dropdown options - hide selected player from the other dropdown
function updateBatsmenDropdowns() {
    const strikerSelect = document.getElementById('batsmanSelect');
    const nonStrikerSelect = document.getElementById('nonStrikerSelect');

    const strikerValue = strikerSelect.value;
    const nonStrikerValue = nonStrikerSelect.value;

    // Update non-striker options - hide the selected striker
    Array.from(nonStrikerSelect.options).forEach(option => {
        if (option.value && option.value === strikerValue) {
            option.hidden = true;
            option.disabled = true;
        } else if (!option.dataset.isOut) {
            option.hidden = false;
            option.disabled = false;
        }
    });

    // Update striker options - hide the selected non-striker
    Array.from(strikerSelect.options).forEach(option => {
        if (option.value && option.value === nonStrikerValue) {
            option.hidden = true;
            option.disabled = true;
        } else if (!option.dataset.isOut) {
            option.hidden = false;
            option.disabled = false;
        }
    });
}

// Swap striker and non-striker
function swapBatsmen() {
    const strikerSelect = document.getElementById('batsmanSelect');
    const nonStrikerSelect = document.getElementById('nonStrikerSelect');

    const strikerValue = strikerSelect.value;
    const nonStrikerValue = nonStrikerSelect.value;

    // Temporarily unhide all options for swap
    Array.from(strikerSelect.options).forEach(opt => { if (!opt.dataset.isOut) { opt.hidden = false; opt.disabled = false; }});
    Array.from(nonStrikerSelect.options).forEach(opt => { if (!opt.dataset.isOut) { opt.hidden = false; opt.disabled = false; }});

    strikerSelect.value = nonStrikerValue;
    nonStrikerSelect.value = strikerValue;

    // Re-apply filtering
    updateBatsmenDropdowns();
}

// Listen for changes on both dropdowns
document.getElementById('batsmanSelect')?.addEventListener('change', updateBatsmenDropdowns);
document.getElementById('nonStrikerSelect')?.addEventListener('change', updateBatsmenDropdowns);

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateBatsmenDropdowns);

// AJAX refresh - update UI without page reload
function refreshMatchState() {
    fetch(`/admin/matches/${matchId}/state`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        scoreVersion = data.scoreVersion ?? scoreVersion;

        // Header scores, by TEAM (teamAScore is always team A's box, whoever batted first)
        [['teamA', data.teamAStats], ['teamB', data.teamBStats]].forEach(([key, stats]) => {
            const scoreEl = document.getElementById(`${key}Score`);
            const oversEl = document.getElementById(`${key}Overs`);
            if (!stats || !scoreEl) return;
            if (stats.batted) {
                // Two innings each: "245/10 & 180/6".
                scoreEl.textContent = (stats.innings || []).length > 1 ? stats.display : `${stats.runs}/${stats.wickets}`;
                if (oversEl) oversEl.textContent = `(${stats.overs} ov)`;
            } else {
                scoreEl.innerHTML = '<span class="text-gray-500">Yet to bat</span>';
                if (oversEl) oversEl.textContent = '';
            }
        });

        // Chase line
        const chaseLine = document.getElementById('chaseLine');
        if (chaseLine && data.chase) {
            const rrr = data.chase.required_run_rate !== null ? ` | RRR: ${Number(data.chase.required_run_rate).toFixed(2)}` : '';
            chaseLine.querySelector('p').textContent = `Target: ${data.chase.target} | Need ${data.chase.runs_needed} off ${data.chase.balls_left} balls${rrr}`;
        }

        // Update run rate
        document.getElementById('runRate').textContent = Number(data.runRate).toFixed(2);

        // Update current over balls
        updateCurrentOverDisplay(data.currentOverBalls);

        // Update over summary
        updateOverSummary(data.summary);

        // Update out batsmen (disable in dropdowns)
        updateOutBatsmen(data.outBatsmenIds);

        // Update selected players
        if (data.currentStriker) {
            document.getElementById('batsmanSelect').value = data.currentStriker;
        } else if (data.needsNewBatsman) {
            document.getElementById('batsmanSelect').value = '';
        }

        if (data.currentNonStriker) {
            document.getElementById('nonStrikerSelect').value = data.currentNonStriker;
        }

        if (data.currentBowler) {
            document.getElementById('bowlerSelect').value = data.currentBowler;
        }
        freeHit = !!data.freeHit;
        document.getElementById('freeHitBadge')?.classList.toggle('hidden', !freeHit);
        document.getElementById('powerplayBadge')?.classList.toggle('hidden', !data.inPowerplay);
        bowlerStats = data.bowlerStats || [];
        renderCrease(data);
        applyOverEnd(!!data.overComplete, data.lastOverBowler);

        // Show/hide wicket alert
        const wicketAlert = document.getElementById('wicketAlert');
        if (data.needsNewBatsman) {
            wicketAlert.classList.remove('hidden');
            document.getElementById('batsmanSelect').classList.add('ring-2', 'ring-yellow-400');
        } else {
            wicketAlert.classList.add('hidden');
            document.getElementById('batsmanSelect').classList.remove('ring-2', 'ring-yellow-400');
        }

        // Overs done, all out, or target reached — decided on the server.
        const wasComplete = isInningsComplete;
        isInningsComplete = !!data.inningsComplete;
        currentCompletedOvers = data.totalOvers;

        // The innings just ended: the panel changes (next-innings / result
        // prompt), so reload once on that transition only.
        if (isInningsComplete && !wasComplete) {
            location.reload();
            return;
        }

        const inningsCompleteAlert = document.getElementById('inningsCompleteAlert');
        if (isInningsComplete) {
            inningsCompleteAlert?.classList.remove('hidden');
            // Disable all scoring buttons
            document.querySelectorAll('.run-btn').forEach(btn => btn.disabled = true);
        } else {
            inningsCompleteAlert?.classList.add('hidden');
            // Re-enable buttons
            document.querySelectorAll('.run-btn').forEach(btn => btn.disabled = false);
        }

        // Re-apply dropdown filtering
        updateBatsmenDropdowns();
    })
    .catch(error => {
        console.error('Error refreshing state:', error);
    });
}

function updateCurrentOverDisplay(balls) {
    const container = document.getElementById('currentOverBalls');
    if (!container) return;

    // Count legal deliveries (exclude wides and no-balls)
    const legalBalls = balls.filter(b => !b.includes('wd') && !b.includes('nb')).length;
    const remainingBalls = Math.max(0, 6 - legalBalls);

    let html = '';

    // Show all bowled balls
    balls.forEach(ball => {
        let ballClass = 'bg-gray-700';
        if (ball === 'W') ballClass = 'bg-red-600';
        else if (ball.includes('wd')) ballClass = 'bg-orange-500';
        else if (ball.includes('nb')) ballClass = 'bg-pink-500';
        else if (ball.includes('lb')) ballClass = 'bg-purple-500';
        else if (ball.includes('b') && !ball.includes('nb')) ballClass = 'bg-blue-400';
        else if (ball === '4') ballClass = 'bg-yellow-500';
        else if (ball === '6') ballClass = 'bg-green-500';
        else if (ball === '0') ballClass = 'bg-gray-500';
        else ballClass = 'bg-blue-500';
        html += `<div class="w-10 h-10 rounded-full ${ballClass} flex items-center justify-center text-white font-bold text-sm">${ball}</div>`;
    });

    // Show remaining empty slots for legal deliveries
    for (let i = 0; i < remainingBalls; i++) {
        html += `<div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-white font-bold text-sm">-</div>`;
    }

    container.innerHTML = html;
}

function updateOverSummary(summary) {
    const container = document.getElementById('overSummary');
    if (!container) return;

    if (summary.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <p>No balls recorded yet. Start scoring!</p>
            </div>`;
        return;
    }

    let html = '';
    summary.forEach((over, index) => {
        const isLast = index === summary.length - 1;
        html += `
            <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl ${isLast ? 'current-over ring-2 ring-indigo-500' : ''}">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-gray-700 dark:text-gray-300">Over ${over.over}</span>
                    <span class="text-sm">
                        <span class="font-semibold text-blue-600">${over.runs}</span> runs,
                        <span class="font-semibold text-red-600">${over.wickets}</span> wkts
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${over.balls.map(ball => {
                        let ballClass = 'bg-gray-300 dark:bg-gray-600';
                        if (ball === 'W') ballClass = 'bg-red-500 text-white';
                        else if (ball.includes('wd')) ballClass = 'bg-orange-400 text-white';
                        else if (ball.includes('nb')) ballClass = 'bg-pink-500 text-white';
                        else if (ball.includes('lb')) ballClass = 'bg-purple-500 text-white';
                        else if (ball.includes('b') && !ball.includes('nb')) ballClass = 'bg-blue-400 text-white';
                        else if (ball === '4') ballClass = 'bg-yellow-500 text-white';
                        else if (ball === '6') ballClass = 'bg-green-500 text-white';
                        else if (ball === '0') ballClass = 'bg-gray-400 text-white';
                        else ballClass = 'bg-blue-500 text-white';
                        return `<span class="w-8 h-8 rounded-full ${ballClass} flex items-center justify-center text-xs font-bold">${ball}</span>`;
                    }).join('')}
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

function updateOutBatsmen(outIds) {
    const strikerSelect = document.getElementById('batsmanSelect');
    const nonStrikerSelect = document.getElementById('nonStrikerSelect');

    [strikerSelect, nonStrikerSelect].forEach(select => {
        Array.from(select.options).forEach(option => {
            if (option.value && outIds.includes(parseInt(option.value))) {
                option.disabled = true;
                option.dataset.isOut = 'true';
                if (!option.textContent.includes('(OUT)')) {
                    option.textContent += ' (OUT)';
                }
            }
        });
    });
}

/**
 * The bowler list. Each name carries its figures (O-M-R-W). At the end of an
 * over the next bowler is the scorer's choice: the one who just bowled is
 * barred (no two overs in a row) and so is anyone who has bowled their
 * quota. Mid-over the selection is left alone, so a refresh never overwrites
 * what the scorer picked.
 */
function applyOverEnd(overDone, lastBowler) {
    const select = document.getElementById('bowlerSelect');
    if (!select) return;
    const figures = Object.fromEntries((bowlerStats || []).map(b => [String(b.id), b]));
    Array.from(select.options).forEach(o => {
        if (!o.value) return;
        o.dataset.name ??= o.textContent.trim();
        const f = figures[o.value];
        const atQuota = f && f.oversCount >= bowlerQuota;
        const bowledLast = lastBowler && parseInt(o.value) === parseInt(lastBowler);
        let label = o.dataset.name + (f ? `  ·  ${f.overs}-${f.maidens}-${f.runs}-${f.wickets}` : '');
        if (overDone && bowledLast) label += '  (bowled last over)';
        else if (overDone && atQuota) label += `  (${bowlerQuota} overs done)`;
        o.textContent = label;
        o.disabled = !!(overDone && (bowledLast || atQuota));
    });
    if (overDone && (!select.value || select.selectedOptions[0]?.disabled)) {
        select.value = '';
    }
    const needsBowler = overDone && !select.value;
    document.getElementById('overAlert')?.classList.toggle('hidden', !needsBowler);
    select.classList.toggle('ring-2', needsBowler);
    select.classList.toggle('ring-blue-400', needsBowler);
    renderBowlerLine();
}

/** Batters, partnership and last wicket under the score. */
function renderCrease(data) {
    const panel = document.getElementById('creasePanel');
    if (!panel) return;
    const s = data.strikerDetails, n = data.nonStrikerDetails;
    panel.classList.toggle('hidden', !s && !n);
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    const line = b => b ? `${b.runs} (${b.balls})` + (b.fours || b.sixes ? `  ${b.fours}×4 ${b.sixes}×6` : '') : '';
    set('creaseStrikerName', s ? `${s.name} *` : '—');
    set('creaseStrikerLine', line(s));
    set('creaseNonStrikerName', n ? n.name : '—');
    set('creaseNonStrikerLine', line(n));
    const p = data.partnership;
    set('creasePartnership', p ? `${p.runs} (${p.balls})` : '—');
    const w = data.lastWicket;
    set('creaseLastWicket', w ? `${w.name} ${w.runs} (${w.balls}) — ${w.score}/${w.wicket}` : '—');
}

/** The selected bowler's figures, so the scorer sees them while picking. */
function renderBowlerLine() {
    const id = document.getElementById('bowlerSelect')?.value;
    const f = (bowlerStats || []).find(b => String(b.id) === String(id));
    const opt = document.getElementById('bowlerSelect')?.selectedOptions[0];
    const nameEl = document.getElementById('creaseBowlerName');
    const lineEl = document.getElementById('creaseBowlerLine');
    if (!nameEl || !lineEl) return;
    nameEl.textContent = id ? (opt?.dataset.name || opt?.textContent || '—') : '—';
    lineEl.textContent = f ? `${f.overs}-${f.maidens}-${f.runs}-${f.wickets}  ·  Econ ${Number(f.economy).toFixed(2)}` : (id ? 'yet to bowl' : '');
}

function openEndMatch(outcome) {
    const modal = document.getElementById('endMatchModal');
    if (!modal) return;
    if (outcome) {
        const r = modal.querySelector(`input[name="outcome"][value="${outcome}"]`);
        if (r) r.checked = true;
    }
    syncAbandonReason();
    modal.classList.remove('hidden');
}
function closeEndMatch() { document.getElementById('endMatchModal')?.classList.add('hidden'); }
function syncAbandonReason() {
    const modal = document.getElementById('endMatchModal');
    const abandoned = modal?.querySelector('input[name="outcome"]:checked')?.value === 'abandoned';
    const box = document.getElementById('abandonReason');
    box?.classList.toggle('hidden', !abandoned);
    box?.classList.toggle('flex', abandoned);
}
document.addEventListener('change', e => { if (e.target.name === 'outcome') syncAbandonReason(); });

function recordBall(runs, extraType = null, extraRuns = 0, isWicket = false, dismissalType = null, fielderId = null, outBatsman = null) {
    // Check if innings is complete
    if (isInningsComplete) {
        alert('This innings is complete. Start the next innings to keep scoring.');
        return;
    }

    const batsmanId = document.getElementById('batsmanSelect').value;
    const nonStrikerId = document.getElementById('nonStrikerSelect').value;
    const bowlerId = document.getElementById('bowlerSelect').value;

    if (!batsmanId) {
        alert('Please select the Striker (Batsman)');
        document.getElementById('batsmanSelect').focus();
        return;
    }

    if (!nonStrikerId) {
        alert('Please select the Non-Striker');
        document.getElementById('nonStrikerSelect').focus();
        return;
    }

    if (!bowlerId) {
        alert('Please select the Bowler');
        document.getElementById('bowlerSelect').focus();
        return;
    }

    if (batsmanId === nonStrikerId) {
        alert('Striker and Non-Striker cannot be the same player');
        return;
    }

    // Disable buttons during submission
    document.querySelectorAll('.run-btn').forEach(btn => btn.disabled = true);

    const data = {
        match_id: matchId,
        batsman_id: batsmanId,
        bowler_id: bowlerId,
        runs: runs,
        extra_type: extraType,
        extra_runs: extraRuns,
        is_wicket: isWicket ? 1 : 0,
        dismissal_type: dismissalType,
        fielder_id: fielderId,
        out_batsman: outBatsman,
        non_striker_id: nonStrikerId,
        event_uuid: newEventId(),
        expected_version: scoreVersion,
    };

    fetch(`/admin/matches/${matchId}/balls/ajax-store`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json().then(result => ({ status: response.status, result })))
    .then(({ status, result }) => {
        if (result.success) {
            scoreVersion = result.score_version ?? scoreVersion;
            // Refresh UI without page reload
            refreshMatchState();
        } else {
            alert(result.message || 'Failed to record ball');
            console.error(result.errors);
            // 409: somebody else scored — show their score before trying again.
            if (status === 409) refreshMatchState();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        // Re-enable buttons
        document.querySelectorAll('.run-btn').forEach(btn => btn.disabled = false);
    });
}

function recordExtra(type, runs = 1) {
    // For wide/no_ball: runs go to extras, batsman can also run
    // For bye/leg_bye: all runs are extras (ball counts as legal)
    if (type === 'wide' || type === 'no_ball') {
        // Wide/No ball: 1 extra + any runs taken
        recordBall(0, type, runs);
    } else {
        // Bye/Leg bye: runs are extras, no bat runs
        recordBall(0, type, runs);
    }
}

// Extra modal functions
function showExtraModal(type) {
    const modal = document.getElementById('extraModal');
    const title = document.getElementById('extraModalTitle');
    const desc = document.getElementById('extraDescription');
    document.getElementById('extraType').value = type;

    const labels = {
        'wide': { title: 'Wide', desc: 'One wide is added. Pick extra runs taken (Wd+4 = wide to the boundary, 5 wides).' },
        'no_ball': { title: 'No Ball', desc: 'Runs off the bat? One no-ball run is added, and the next ball is a free hit.' },
        'bye': { title: 'Bye', desc: 'How many bye runs?' },
        'leg_bye': { title: 'Leg Bye', desc: 'How many leg bye runs?' }
    };

    title.textContent = labels[type]?.title || 'Extra';
    desc.textContent = labels[type]?.desc || 'How many runs?';

    // A no-ball asks for runs off the BAT (credited to the batter); the
    // no-ball's own run is added on top. Others ask for the extra itself.
    const isNoBall = type === 'no_ball';
    const isWide = type === 'wide';
    document.getElementById('noBallRunsGrid').classList.toggle('hidden', !isNoBall);
    document.getElementById('wideRunsGrid').classList.toggle('hidden', !isWide);
    document.getElementById('extraRunsGrid').classList.toggle('hidden', isNoBall || isWide);

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeExtraModal() {
    document.getElementById('extraModal').classList.add('hidden');
    document.getElementById('extraModal').classList.remove('flex');
}

function confirmNoBall(batRuns) {
    recordBall(batRuns, 'no_ball', 1);
    closeExtraModal();
}

function confirmExtra(runs) {
    const type = document.getElementById('extraType').value;
    recordExtra(type, runs);
    closeExtraModal();
}

function recordWicket() {
    document.getElementById('wicketModal').classList.remove('hidden');
    document.getElementById('wicketModal').classList.add('flex');
    document.getElementById('wicketDelivery').value = 'legal';
    document.getElementById('fielderSelect').value = '';
    syncWicketForm();
}

function closeWicketModal() {
    document.getElementById('wicketModal').classList.add('hidden');
    document.getElementById('wicketModal').classList.remove('flex');
}

function confirmWicket() {
    const dismissalType = document.getElementById('dismissalType').value;
    const delivery = document.getElementById('wicketDelivery').value;
    const fielderId = document.getElementById('fielderSelect').value || null;
    if (['caught', 'stumped'].includes(dismissalType) && !fielderId) {
        alert(dismissalType === 'caught' ? 'Choose who took the catch.' : 'Choose the wicket-keeper.');
        return;
    }
    const runs = dismissalType === 'run_out' ? (parseInt(document.getElementById('runOutRuns').value, 10) || 0) : 0;
    const who = dismissalType === 'run_out' ? document.getElementById('runOutBatter').value : null;
    if (delivery === 'wide') {
        // Runs run on a wide are wides, not the batter's.
        recordBall(0, 'wide', 1 + runs, true, dismissalType, fielderId, who);
    } else if (delivery === 'no_ball') {
        recordBall(runs, 'no_ball', 1, true, dismissalType, fielderId, who);
    } else {
        recordBall(runs, null, 0, true, dismissalType, fielderId, who);
    }
    closeWicketModal();
}

/**
 * Keep the wicket form to what can actually happen: on a free hit only a
 * run-out; off a no-ball only a run-out; off a wide run out, stumped or hit
 * wicket.
 */
const deliveriesFor = {
    bowled: ['legal'], caught: ['legal'], lbw: ['legal'],
    stumped: ['legal', 'wide'], hit_wicket: ['legal', 'wide'],
    run_out: ['legal', 'wide', 'no_ball'],
};
function syncWicketForm() {
    const type = document.getElementById('dismissalType');
    Array.from(type.options).forEach(o => { o.disabled = freeHit && o.value !== 'run_out'; });
    if (type.selectedOptions[0]?.disabled) type.value = 'run_out';
    document.getElementById('freeHitNote').classList.toggle('hidden', !freeHit);

    const allowed = deliveriesFor[type.value] || ['legal'];
    const delivery = document.getElementById('wicketDelivery');
    Array.from(delivery.options).forEach(o => { o.hidden = o.disabled = !allowed.includes(o.value); });
    if (delivery.selectedOptions[0]?.disabled) delivery.value = 'legal';
    document.getElementById('deliverySection').classList.toggle('hidden', allowed.length < 2);

    document.getElementById('fielderSection').style.display = ['caught', 'run_out', 'stumped'].includes(type.value) ? 'block' : 'none';
    document.getElementById('runOutSection').style.display = type.value === 'run_out' ? 'grid' : 'none';
}

function undoLastBall() {
    if (!confirm('Are you sure you want to undo the last ball?')) return;

    // First get the last ball ID
    fetch(`/admin/matches/${matchId}/balls/last`, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert(data.message || 'No balls to undo.');
            return;
        }

        const ballId = data.ball.id;
        const ballInfo = `Over ${data.ball.over}.${data.ball.ball_in_over} - ${data.ball.runs} runs${data.ball.is_wicket ? ' (Wicket)' : ''}`;

        // Now delete the ball
        fetch(`/admin/matches/${matchId}/balls/${ballId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ expected_version: scoreVersion }),
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                scoreVersion = result.score_version ?? scoreVersion;
                // Undoing out of a finished innings reopens it.
                if (isInningsComplete) { location.reload(); return; }
                // Refresh UI without page reload
                refreshMatchState();
            } else {
                alert(result.error || 'Failed to delete ball.');
            }
        })
        .catch(error => {
            console.error('Error deleting ball:', error);
            alert('An error occurred while deleting the ball.');
        });
    })
    .catch(error => {
        console.error('Error fetching last ball:', error);
        alert('An error occurred. Please try again.');
    });
}

document.getElementById('dismissalType')?.addEventListener('change', syncWicketForm);

// Generate poster with selected template
function generatePoster() {
    const templateSelect = document.getElementById('posterTemplateSelect');
    const templateId = templateSelect.value;
    const btn = document.getElementById('generatePosterBtn');

    if (!templateId) {
        alert('Please select a template');
        return;
    }

    // Show loading state
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Generating...';
    btn.disabled = true;

    // Redirect to download with template and innings parameter
    const url = `/admin/matches/${matchId}/generate-poster?template=${templateId}&innings=${currentInnings}`;

    // Create a temporary link to trigger download
    fetch(url, {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        return response.json().then(data => {
            throw new Error(data.message || 'Failed to generate poster');
        });
    })
    .then(blob => {
        // Create download link
        const downloadUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = downloadUrl;
        a.download = `{{ config('settings.app_name') ?: config('app.name') }}-match-poster-${matchId}.png`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(downloadUrl);
        a.remove();

        // Reset button
        btn.innerHTML = originalContent;
        btn.disabled = false;
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message || 'Failed to generate poster');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    });
}

// ============ CRICHEROES SYNC ============
function showSyncConfirmation() {
    const modal = document.getElementById('syncConfirmModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function hideSyncConfirmation() {
    const modal = document.getElementById('syncConfirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function syncScoreNow() {
    const btn = document.getElementById('syncNowBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>`;

    try {
        const response = await fetch(`/admin/matches/${matchId}/sync-cricheroes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            hideSyncConfirmation();
            alert(data.message + '\n\n' + (data.data.result_summary || data.data.team_a_score + ' vs ' + data.data.team_b_score));
            location.reload();
        } else {
            alert('Sync failed: ' + (data.message || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Sync error:', error);
        alert('Failed to sync scores. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>
@endpush
@endsection
