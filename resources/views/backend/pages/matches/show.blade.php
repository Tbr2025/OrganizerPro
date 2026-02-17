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
                    {{ $currentInnings ?? 1 }}{{ $currentInnings == 1 ? 'st' : 'nd' }} Inn
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
            </div>

            <!-- Teams & Score -->
            <div class="flex items-center justify-between mt-6">
                <!-- Team A (1st Innings) -->
                <div class="flex-1 text-center {{ ($currentInnings ?? 1) == 1 ? 'ring-2 ring-green-400 rounded-xl p-2' : '' }}">
                    @if($match->teamA?->team_logo)
                        <img src="{{ Storage::url($match->teamA->team_logo) }}" alt=""
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 {{ ($currentInnings ?? 1) == 1 ? 'border-green-400' : 'border-white/30' }} mb-2">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-gray-600 flex items-center justify-center text-xl font-bold mb-2">
                            {{ substr($match->teamA?->name ?? 'A', 0, 2) }}
                        </div>
                    @endif
                    <h3 class="font-bold">{{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'Team A' }}</h3>
                    @if(($currentInnings ?? 1) == 1)
                        <span class="text-xs text-green-400">BATTING</span>
                    @endif
                    <div class="text-3xl font-black mt-2" id="teamAScore">
                        {{ $innings1Stats['runs'] ?? 0 }}/{{ $innings1Stats['wickets'] ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-400" id="teamAOvers">
                        ({{ $innings1Stats['overs'] ?? '0.0' }} ov)
                    </div>
                </div>

                <!-- VS -->
                <div class="flex-shrink-0 px-6">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                        <span class="text-white font-bold text-sm">VS</span>
                    </div>
                </div>

                <!-- Team B (2nd Innings) -->
                <div class="flex-1 text-center {{ ($currentInnings ?? 1) == 2 ? 'ring-2 ring-green-400 rounded-xl p-2' : '' }}">
                    @if($match->teamB?->team_logo)
                        <img src="{{ Storage::url($match->teamB->team_logo) }}" alt=""
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 {{ ($currentInnings ?? 1) == 2 ? 'border-green-400' : 'border-white/30' }} mb-2">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-gray-600 flex items-center justify-center text-xl font-bold mb-2">
                            {{ substr($match->teamB?->name ?? 'B', 0, 2) }}
                        </div>
                    @endif
                    <h3 class="font-bold">{{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'Team B' }}</h3>
                    @if(($currentInnings ?? 1) == 2)
                        <span class="text-xs text-green-400">BATTING</span>
                    @endif
                    <div class="text-3xl font-black mt-2" id="teamBScore">
                        @if(($innings2Stats['runs'] ?? 0) > 0 || ($currentInnings ?? 1) == 2)
                            {{ $innings2Stats['runs'] ?? 0 }}/{{ $innings2Stats['wickets'] ?? 0 }}
                        @else
                            <span class="text-gray-500">Yet to bat</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-400" id="teamBOvers">
                        @if(($innings2Stats['runs'] ?? 0) > 0 || ($currentInnings ?? 1) == 2)
                            ({{ $innings2Stats['overs'] ?? '0.0' }} ov)
                        @endif
                    </div>
                </div>
            </div>

            <!-- Target Info (2nd innings) -->
            @if(($currentInnings ?? 1) == 2 && ($innings1Stats['runs'] ?? 0) > 0)
                @php
                    $target = ($innings1Stats['runs'] ?? 0) + 1;
                    $required = $target - ($innings2Stats['runs'] ?? 0);
                    $remainingOvers = ($match->overs ?? 20) - (float)str_replace('.', '', $innings2Stats['overs'] ?? '0.0') / 10;
                @endphp
                <div class="mt-4 pt-4 border-t border-white/10 text-center">
                    <p class="text-yellow-400 font-semibold">
                        Target: {{ $target }} |
                        Need {{ $required }} runs
                        @if($remainingOvers > 0)
                            | RRR: {{ number_format($required / max(0.1, $remainingOvers), 2) }}
                        @endif
                    </p>
                </div>
            @endif

            <!-- Current Over Display -->
            <div class="mt-6 pt-4 border-t border-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Current Over</span>
                    <span class="text-sm text-gray-400">Run Rate: <span class="text-white font-bold" id="runRate">{{ $totalOvers > 0 ? number_format($totalRuns / $totalOvers, 2) : '0.00' }}</span></span>
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
                <!-- Select Batsmen & Bowler -->
                <div id="wicketAlert" class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded-xl {{ ($needsNewBatsman ?? false) ? '' : 'hidden' }}">
                    <p class="text-yellow-800 dark:text-yellow-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Wicket! Please select the new batsman.
                    </p>
                </div>

                @php
                    // Check if current innings is complete
                    $lastOverData = collect($summary)->last();
                    $legalBallsInLastOver = $lastOverData ? collect($lastOverData['balls'])->filter(fn($b) => !str_contains($b, 'wd') && !str_contains($b, 'nb'))->count() : 0;
                    $currentInningsComplete = ($totalOvers ?? 0) >= ($match->overs ?? 20) && $legalBallsInLastOver >= 6;
                    $allWicketsDown = ($totalWickets ?? 0) >= 10;
                    $inningsComplete = $currentInningsComplete || $allWicketsDown;
                @endphp

                @if($inningsComplete && ($currentInnings ?? 1) == 1)
                <!-- First Innings Complete - Start 2nd Innings -->
                <div class="mb-4 p-4 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 rounded-xl">
                    <p class="text-blue-800 dark:text-blue-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        1st Innings Complete{{ $allWicketsDown ? ' (All Out)' : '' }}! {{ $match->teamA?->name }} scored {{ $innings1Stats['runs'] ?? 0 }}/{{ $innings1Stats['wickets'] ?? 0 }} ({{ $innings1Stats['overs'] ?? '0.0' }} ov)
                    </p>
                    <form action="{{ route('admin.matches.switchInnings', $match) }}" method="POST" class="mt-3">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Start 2nd Innings ({{ $match->teamB?->name }} batting)
                        </button>
                    </form>
                </div>
                @elseif($inningsComplete && ($currentInnings ?? 1) == 2)
                <!-- Second Innings Complete - Match Over -->
                <div id="inningsCompleteAlert" class="mb-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 rounded-xl">
                    <p class="text-green-800 dark:text-green-200 text-sm font-medium flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Match Complete{{ $allWicketsDown ? ' (All Out)' : '' }}! {{ $battingTeam?->name }} scored {{ $totalRuns }}/{{ $totalWickets }} ({{ $innings2Stats['overs'] ?? '0.0' }} ov)
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('admin.matches.summary.edit', $match) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            View Summary & Poster
                        </a>
                        <a href="{{ route('admin.matches.result.edit', $match) }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit Result
                        </a>
                    </div>
                </div>
                @endif

                <!-- Switch Innings Button (manual) -->
                <div class="mb-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Currently scoring: <strong class="text-indigo-600">{{ $battingTeam?->name ?? 'Team' }}</strong> batting
                    </span>
                    <form action="{{ route('admin.matches.switchInnings', $match) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                            Switch Innings
                        </button>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            <span class="inline-flex items-center">
                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                Striker
                            </span>
                            <span class="text-xs text-gray-500 ml-1">({{ count($outBatsmenIds ?? []) }} out)</span>
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
                    <span class="font-medium">{{ $match->start_time ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Venue</span>
                    <span class="font-medium">{{ $match->venue ?? $match->location ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Overs</span>
                    <span class="font-medium">{{ $match->overs ?? '20' }}</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card rounded-2xl p-6">
            <h3 class="font-bold text-lg mb-4">Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('admin.matches.result.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Record Final Result
                </a>
                <a href="{{ route('admin.matches.summary.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-purple-500 hover:bg-purple-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Match Summary & Poster
                </a>
                <a href="{{ route('admin.matches.appreciations.create', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    Add Appreciation
                </a>
                <a href="{{ route('admin.matches.edit', $match) }}"
                   class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 font-semibold rounded-xl transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Match
                </a>
            </div>
        </div>

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
                                $isOut = in_array($player->user_id, $outBatsmenIds ?? []);
                            @endphp
                            <li class="flex items-center justify-between text-sm py-1 {{ $isOut ? 'opacity-50' : '' }}">
                                <span class="{{ $isOut ? 'line-through text-red-500' : '' }}">
                                    {{ $player->player->name }}
                                </span>
                                <span class="{{ $isOut ? 'text-red-500 font-bold' : 'text-gray-500' }}">
                                    {{ $isOut ? 'OUT' : ($player->player->jersey_number ?? '-') }}
                                </span>
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
                                <span class="text-gray-500">{{ $player->player->jersey_number ?? '-' }}</span>
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
            <div id="fielderSection">
                <label class="block text-sm font-medium mb-1">Fielder (if applicable)</label>
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
            <div class="grid grid-cols-5 gap-2">
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

@push('scripts')
<script>
const matchId = {{ $match->id }};
const csrfToken = '{{ csrf_token() }}';
const matchOversLimit = {{ $match->overs ?? 20 }};
let currentCompletedOvers = {{ $totalOvers ?? 0 }};
let isInningsComplete = false;

// Check if innings is complete
function checkInningsComplete(totalOvers, summary) {
    if (!summary || summary.length === 0) return false;

    const lastOver = summary[summary.length - 1];
    // Count legal balls in last over
    const legalBalls = lastOver.balls.filter(b => !b.includes('wd') && !b.includes('nb')).length;

    // Innings complete if we've finished all overs
    if (totalOvers >= matchOversLimit && legalBalls >= 6) {
        return true;
    }
    return false;
}

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
        // Update BOTH innings scores in header
        if (data.innings1Stats) {
            document.getElementById('teamAScore').textContent = `${data.innings1Stats.runs}/${data.innings1Stats.wickets}`;
            document.getElementById('teamAOvers').textContent = `(${data.innings1Stats.overs} ov)`;
        }
        if (data.innings2Stats) {
            const teamBScore = document.getElementById('teamBScore');
            const teamBOvers = document.getElementById('teamBOvers');
            if (data.innings2Stats.runs > 0 || data.currentInnings === 2) {
                teamBScore.innerHTML = `${data.innings2Stats.runs}/${data.innings2Stats.wickets}`;
                teamBOvers.textContent = `(${data.innings2Stats.overs} ov)`;
            } else {
                teamBScore.innerHTML = '<span class="text-gray-500">Yet to bat</span>';
                teamBOvers.textContent = '';
            }
        }

        // Update run rate
        document.getElementById('runRate').textContent = data.runRate.toFixed(2);

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

        // Show/hide wicket alert
        const wicketAlert = document.getElementById('wicketAlert');
        if (data.needsNewBatsman) {
            wicketAlert.classList.remove('hidden');
            document.getElementById('batsmanSelect').classList.add('ring-2', 'ring-yellow-400');
        } else {
            wicketAlert.classList.add('hidden');
            document.getElementById('batsmanSelect').classList.remove('ring-2', 'ring-yellow-400');
        }

        // Check if innings is complete (overs done OR all out - 10 wickets)
        const oversComplete = checkInningsComplete(data.totalOvers, data.summary);
        isInningsComplete = oversComplete || data.isAllOut;
        currentCompletedOvers = data.totalOvers;

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

function recordBall(runs, extraType = null, extraRuns = 0, isWicket = false, dismissalType = null, fielderId = null) {
    // Check if innings is complete
    if (isInningsComplete) {
        alert(`Innings complete! All ${matchOversLimit} overs have been bowled.`);
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
        current_striker_user_id: batsmanId,
        current_non_striker_user_id: nonStrikerId
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
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Refresh UI without page reload
            refreshMatchState();
        } else {
            alert(result.message || 'Failed to record ball');
            console.error(result.errors);
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
        'wide': { title: 'Wide', desc: 'Wide + how many additional runs?' },
        'no_ball': { title: 'No Ball', desc: 'No Ball + how many runs scored?' },
        'bye': { title: 'Bye', desc: 'How many bye runs?' },
        'leg_bye': { title: 'Leg Bye', desc: 'How many leg bye runs?' }
    };

    title.textContent = labels[type]?.title || 'Extra';
    desc.textContent = labels[type]?.desc || 'How many runs?';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeExtraModal() {
    document.getElementById('extraModal').classList.add('hidden');
    document.getElementById('extraModal').classList.remove('flex');
}

function confirmExtra(runs) {
    const type = document.getElementById('extraType').value;
    recordExtra(type, runs);
    closeExtraModal();
}

function recordWicket() {
    document.getElementById('wicketModal').classList.remove('hidden');
    document.getElementById('wicketModal').classList.add('flex');
}

function closeWicketModal() {
    document.getElementById('wicketModal').classList.add('hidden');
    document.getElementById('wicketModal').classList.remove('flex');
}

function confirmWicket() {
    const dismissalType = document.getElementById('dismissalType').value;
    const fielderId = document.getElementById('fielderSelect').value || null;
    recordBall(0, null, 0, true, dismissalType, fielderId);
    closeWicketModal();
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
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
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

// Show/hide fielder based on dismissal type
document.getElementById('dismissalType')?.addEventListener('change', function() {
    const needsFielder = ['caught', 'run_out', 'stumped'].includes(this.value);
    document.getElementById('fielderSection').style.display = needsFielder ? 'block' : 'none';
});
</script>
@endpush
@endsection
