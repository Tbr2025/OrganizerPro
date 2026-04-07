@extends('backend.layouts.app')

@section('title', 'Record Match Result | ' . config('app.name'))

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => $match->match_title ?? 'Match', 'route' => route('admin.matches.show', $match)],
    ['name' => 'Record Result']
]" />

@php
    $teamABatsFirst = $result->team_a_batting_first ?? true;
    $firstTeam = $teamABatsFirst ? $match->teamA : $match->teamB;
    $secondTeam = $teamABatsFirst ? $match->teamB : $match->teamA;
@endphp

<div class="max-w-4xl mx-auto">
    <!-- Match Header -->
    <div class="card rounded-2xl overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">{{ $match->match_title ?? 'Match Result' }}</h2>
                    <p class="text-blue-200 mt-1">{{ $match->tournament->name ?? '' }} - {{ $match->stage_display ?? 'League Match' }}</p>
                </div>
                @if($match->match_date)
                    <div class="text-right">
                        <div class="text-sm text-blue-200">Match Date</div>
                        <div class="font-semibold">{{ $match->match_date->format('D, M d, Y') }}</div>
                    </div>
                @endif
            </div>

            <!-- Teams Display -->
            <div class="mt-6 flex items-center justify-center gap-8">
                <div class="text-center">
                    @if($firstTeam?->team_logo)
                        <img src="{{ Storage::url($firstTeam->team_logo) }}" alt="{{ $firstTeam->name }}"
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 border-white/30">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
                            {{ substr($firstTeam?->name ?? 'A', 0, 2) }}
                        </div>
                    @endif
                    <div class="mt-2 font-semibold">{{ $firstTeam?->name ?? 'Team A' }}</div>
                </div>
                <div class="text-2xl font-bold text-blue-200">VS</div>
                <div class="text-center">
                    @if($secondTeam?->team_logo)
                        <img src="{{ Storage::url($secondTeam->team_logo) }}" alt="{{ $secondTeam->name }}"
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 border-white/30">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
                            {{ substr($secondTeam?->name ?? 'B', 0, 2) }}
                        </div>
                    @endif
                    <div class="mt-2 font-semibold">{{ $secondTeam?->name ?? 'Team B' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(isset($ballStats) && $ballStats['hasBallData'])
        @if($ballStats['bothInningsComplete'])
        <!-- Both Innings Complete - Auto-calculated -->
        <div class="card rounded-2xl overflow-hidden mb-6 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-200 dark:border-green-800">
            <div class="p-4 flex items-center">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-500 flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-green-800 dark:text-green-200">Auto-Calculated from Live Scoring</h4>
                    <p class="text-sm text-green-600 dark:text-green-400">Both innings complete! Scores have been automatically calculated from ball-by-ball data. You can adjust if needed.</p>
                </div>
            </div>
        </div>
        @elseif($ballStats['firstInningsComplete'] && !($ballStats['secondInningsStarted'] ?? false))
        <!-- Only First Innings Complete -->
        <div class="card rounded-2xl overflow-hidden mb-6 bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 border border-yellow-200 dark:border-yellow-800">
            <div class="p-4 flex items-center">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-500 flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-yellow-800 dark:text-yellow-200">First Innings Complete - Awaiting Second Innings</h4>
                    <p class="text-sm text-yellow-600 dark:text-yellow-400">
                        {{ $match->teamA?->name ?? 'Team A' }} scored <strong>{{ $ballStats['teamA']['runs'] }}/{{ $ballStats['teamA']['wickets'] }}</strong> ({{ $ballStats['teamA']['overs'] }} ov).
                        Please complete the second innings before recording the final result.
                    </p>
                    <a href="{{ route('admin.matches.show', $match) }}" class="mt-2 inline-flex items-center text-yellow-700 dark:text-yellow-300 font-semibold hover:underline">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Continue Live Scoring (2nd Innings)
                    </a>
                </div>
            </div>
        </div>
        @elseif($ballStats['secondInningsStarted'] ?? false)
        <!-- Second Innings In Progress -->
        <div class="card rounded-2xl overflow-hidden mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800">
            <div class="p-4 flex items-center">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-blue-800 dark:text-blue-200">Second Innings In Progress</h4>
                    <p class="text-sm text-blue-600 dark:text-blue-400">
                        1st Inn: {{ $match->teamA?->name ?? 'Team A' }} - <strong>{{ $ballStats['teamA']['runs'] }}/{{ $ballStats['teamA']['wickets'] }}</strong> ({{ $ballStats['teamA']['overs'] }} ov)<br>
                        2nd Inn: {{ $match->teamB?->name ?? 'Team B' }} - <strong>{{ $ballStats['teamB']['runs'] }}/{{ $ballStats['teamB']['wickets'] }}</strong> ({{ $ballStats['teamB']['overs'] }} ov)
                    </p>
                    <a href="{{ route('admin.matches.show', $match) }}" class="mt-2 inline-flex items-center text-blue-700 dark:text-blue-300 font-semibold hover:underline">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Continue Live Scoring
                    </a>
                </div>
            </div>
        </div>
        @endif
    @endif

    <!-- CricHeroes Sync Panel -->
    <div class="card rounded-2xl overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-teal-500 to-cyan-500 px-6 py-4">
            <h3 class="text-white font-bold text-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Import from CricHeroes
            </h3>
        </div>
        <div class="p-6 space-y-4">
            <!-- Fetch from URL (primary) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CricHeroes Match URL</label>
                <div class="flex gap-2">
                    <input type="text" id="cricheroes_url"
                           value="{{ $match->cricheroes_match_url ?? '' }}"
                           class="form-control flex-1"
                           placeholder="https://cricheroes.com/scorecard/...">
                    <button type="button" id="cricheroes-fetch-btn"
                            class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-lg transition flex items-center whitespace-nowrap">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Fetch Scorecard
                    </button>
                </div>
            </div>

            <!-- Paste fallback -->
            <details class="group">
                <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Or paste scorecard text manually
                </summary>
                <div class="mt-3 space-y-2">
                    <textarea id="cricheroes_paste" rows="4"
                              class="form-control text-sm"
                              placeholder="Paste text from CricHeroes scorecard page, e.g.:&#10;ASIAN ROYALS CC 156/8 (20.0 Ov)&#10;EVEXIA ALL STARS 139/9 (20.0 Ov)&#10;Toss: Evexia All Stars opt to field&#10;Asian ROYALS CC won by 17 runs"></textarea>
                    <button type="button" id="cricheroes-parse-btn"
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg transition text-sm">
                        Parse Scorecard
                    </button>
                </div>
            </details>

            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <input type="checkbox" id="cricheroes-override" class="rounded border-gray-300" checked>
                Override existing values
            </label>

            <!-- Status message -->
            <div id="cricheroes-status" class="text-sm"></div>
        </div>
    </div>

    <!-- Result Form -->
    <form action="{{ route('admin.matches.result.update', $match) }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Toss Details -->
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-yellow-500 to-amber-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Toss Details
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Toss Won By
                        </label>
                        <select name="toss_won_by" id="toss_won_by" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="{{ $match->team_a_id }}" {{ old('toss_won_by', $result->toss_won_by) == $match->team_a_id ? 'selected' : '' }}>
                                {{ $match->teamA?->name ?? 'Team A' }}
                            </option>
                            <option value="{{ $match->team_b_id }}" {{ old('toss_won_by', $result->toss_won_by) == $match->team_b_id ? 'selected' : '' }}>
                                {{ $match->teamB?->name ?? 'Team B' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Elected To
                        </label>
                        <select name="toss_decision" id="toss_decision" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="bat" {{ old('toss_decision', $result->toss_decision) === 'bat' ? 'selected' : '' }}>Bat First</option>
                            <option value="bowl" {{ old('toss_decision', $result->toss_decision) === 'bowl' ? 'selected' : '' }}>Bowl First</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score Cards Container (order swaps dynamically based on toss) -->
        <div id="score-cards-container" class="flex flex-col">
            <!-- Team A Score Card -->
            <div id="score-card-a" class="card rounded-2xl overflow-hidden mb-6" style="order: {{ $teamABatsFirst ? 1 : 2 }};">
                <div id="score-card-a-header" class="bg-gradient-to-r {{ $teamABatsFirst ? 'from-green-500 to-emerald-500' : 'from-orange-500 to-red-500' }} px-6 py-4">
                    <h3 class="text-white font-bold text-lg flex items-center">
                        @if($match->teamA?->team_logo)
                            <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="" class="w-8 h-8 rounded-full mr-3 object-cover">
                        @endif
                        {{ $match->teamA?->name ?? 'Team A' }} - Score
                        <span id="score-card-a-innings" class="ml-2 text-xs font-normal bg-white/20 px-2 py-0.5 rounded-full">{{ $teamABatsFirst ? '1st' : '2nd' }} Innings</span>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Runs <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_a_score" id="team_a_score"
                                   value="{{ old('team_a_score', $result->team_a_score) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_a_score') border-red-500 @enderror"
                                   min="0" required placeholder="0">
                            @error('team_a_score')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Wickets <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_a_wickets" id="team_a_wickets"
                                   value="{{ old('team_a_wickets', $result->team_a_wickets) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_a_wickets') border-red-500 @enderror"
                                   min="0" max="10" required placeholder="0">
                            @error('team_a_wickets')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Overs <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_a_overs" id="team_a_overs"
                                   value="{{ old('team_a_overs', $result->team_a_overs) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_a_overs') border-red-500 @enderror"
                                   min="0" step="0.1" required placeholder="0.0">
                            @error('team_a_overs')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Extras
                            </label>
                            <input type="number" name="team_a_extras" id="team_a_extras"
                                   value="{{ old('team_a_extras', $result->team_a_extras) }}"
                                   class="form-control text-center @error('team_a_extras') border-red-500 @enderror"
                                   min="0" placeholder="0">
                            @error('team_a_extras')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team B Score Card -->
            <div id="score-card-b" class="card rounded-2xl overflow-hidden mb-6" style="order: {{ $teamABatsFirst ? 2 : 1 }};">
                <div id="score-card-b-header" class="bg-gradient-to-r {{ $teamABatsFirst ? 'from-orange-500 to-red-500' : 'from-green-500 to-emerald-500' }} px-6 py-4">
                    <h3 class="text-white font-bold text-lg flex items-center">
                        @if($match->teamB?->team_logo)
                            <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="" class="w-8 h-8 rounded-full mr-3 object-cover">
                        @endif
                        {{ $match->teamB?->name ?? 'Team B' }} - Score
                        <span id="score-card-b-innings" class="ml-2 text-xs font-normal bg-white/20 px-2 py-0.5 rounded-full">{{ $teamABatsFirst ? '2nd' : '1st' }} Innings</span>
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Runs <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_b_score" id="team_b_score"
                                   value="{{ old('team_b_score', $result->team_b_score) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_b_score') border-red-500 @enderror"
                                   min="0" required placeholder="0">
                            @error('team_b_score')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Wickets <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_b_wickets" id="team_b_wickets"
                                   value="{{ old('team_b_wickets', $result->team_b_wickets) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_b_wickets') border-red-500 @enderror"
                                   min="0" max="10" required placeholder="0">
                            @error('team_b_wickets')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Overs <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="team_b_overs" id="team_b_overs"
                                   value="{{ old('team_b_overs', $result->team_b_overs) }}"
                                   class="form-control text-2xl font-bold text-center @error('team_b_overs') border-red-500 @enderror"
                                   min="0" step="0.1" required placeholder="0.0">
                            @error('team_b_overs')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Extras
                            </label>
                            <input type="number" name="team_b_extras" id="team_b_extras"
                                   value="{{ old('team_b_extras', $result->team_b_extras) }}"
                                   class="form-control text-center @error('team_b_extras') border-red-500 @enderror"
                                   min="0" placeholder="0">
                            @error('team_b_extras')
                                <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Match Result Details -->
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    Match Result
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Winner Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Winner <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="winner_team_id" value="{{ $match->team_a_id }}"
                                   class="peer sr-only"
                                   {{ old('winner_team_id', $result->winner_team_id) == $match->team_a_id ? 'checked' : '' }}>
                            <div class="p-4 border-2 rounded-xl text-center transition-all
                                        peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/30
                                        hover:border-gray-400">
                                <div class="font-semibold text-sm">{{ $match->teamA?->name ?? 'Team A' }}</div>
                                <div class="text-xs text-gray-500 mt-1">Won</div>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="winner_team_id" value="{{ $match->team_b_id }}"
                                   class="peer sr-only"
                                   {{ old('winner_team_id', $result->winner_team_id) == $match->team_b_id ? 'checked' : '' }}>
                            <div class="p-4 border-2 rounded-xl text-center transition-all
                                        peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/30
                                        hover:border-gray-400">
                                <div class="font-semibold text-sm">{{ $match->teamB?->name ?? 'Team B' }}</div>
                                <div class="text-xs text-gray-500 mt-1">Won</div>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="winner_team_id" value=""
                                   class="peer sr-only" data-result-type="tie"
                                   {{ old('winner_team_id', $result->winner_team_id) === null && old('result_type', $result->result_type) === 'tie' ? 'checked' : '' }}>
                            <div class="p-4 border-2 rounded-xl text-center transition-all
                                        peer-checked:border-yellow-500 peer-checked:bg-yellow-50 dark:peer-checked:bg-yellow-900/30
                                        hover:border-gray-400">
                                <div class="font-semibold text-sm">Tie</div>
                                <div class="text-xs text-gray-500 mt-1">No winner</div>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="winner_team_id" value=""
                                   class="peer sr-only" data-result-type="no_result"
                                   {{ old('winner_team_id', $result->winner_team_id) === null && old('result_type', $result->result_type) === 'no_result' ? 'checked' : '' }}>
                            <div class="p-4 border-2 rounded-xl text-center transition-all
                                        peer-checked:border-gray-500 peer-checked:bg-gray-50 dark:peer-checked:bg-gray-800
                                        hover:border-gray-400">
                                <div class="font-semibold text-sm">No Result</div>
                                <div class="text-xs text-gray-500 mt-1">Abandoned</div>
                            </div>
                        </label>
                    </div>
                    @error('winner_team_id')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Result Type & Margin -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Result Type <span class="text-red-500">*</span>
                        </label>
                        <select name="result_type" id="result_type"
                                class="form-control @error('result_type') border-red-500 @enderror" required>
                            <option value="">-- Select --</option>
                            <option value="runs" {{ old('result_type', $result->result_type) === 'runs' ? 'selected' : '' }}>Won by Runs</option>
                            <option value="wickets" {{ old('result_type', $result->result_type) === 'wickets' ? 'selected' : '' }}>Won by Wickets</option>
                            <option value="tie" {{ old('result_type', $result->result_type) === 'tie' ? 'selected' : '' }}>Tie</option>
                            <option value="no_result" {{ old('result_type', $result->result_type) === 'no_result' ? 'selected' : '' }}>No Result</option>
                            <option value="super_over" {{ old('result_type', $result->result_type) === 'super_over' ? 'selected' : '' }}>Super Over</option>
                            <option value="dls" {{ old('result_type', $result->result_type) === 'dls' ? 'selected' : '' }}>DLS Method</option>
                        </select>
                        @error('result_type')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Margin (runs/wickets)
                        </label>
                        <input type="number" name="margin" id="margin"
                               value="{{ old('margin', $result->margin) }}"
                               class="form-control @error('margin') border-red-500 @enderror"
                               min="0" placeholder="e.g., 25 runs or 5 wickets">
                        @error('margin')
                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Result Summary -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Result Summary
                        <span class="text-xs text-gray-500">(Auto-generated if empty)</span>
                    </label>
                    <input type="text" name="result_summary" id="result_summary"
                           value="{{ old('result_summary', $result->result_summary) }}"
                           class="form-control @error('result_summary') border-red-500 @enderror"
                           placeholder="e.g., Team A won by 25 runs">
                    @error('result_summary')
                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Match Notes -->
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-gray-600 to-gray-700 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Match Notes (Optional)
                </h3>
            </div>
            <div class="p-6">
                <textarea name="match_notes" id="match_notes" rows="3"
                          class="form-control @error('match_notes') border-red-500 @enderror"
                          placeholder="Any additional notes about the match...">{{ old('match_notes', $result->match_notes) }}</textarea>
                @error('match_notes')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.matches.index') }}"
               class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                Cancel
            </a>
            <button type="submit"
                    class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-bold rounded-xl shadow-lg transition flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save Match Result
            </button>
        </div>
    </form>
</div>

<!-- Import Preview Modal -->
<div id="import-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" id="import-modal-backdrop"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-teal-500 to-cyan-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center" id="import-modal-title">
                    <svg class="w-5 h-5 mr-2 animate-spin" id="import-spinner" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg class="w-5 h-5 mr-2 hidden" id="import-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="import-modal-title-text">Parsing scorecard...</span>
                </h3>
            </div>
            <!-- Modal Body -->
            <div class="p-6" id="import-modal-body">
                <div class="flex items-center justify-center py-8" id="import-loading">
                    <div class="text-center">
                        <div class="w-12 h-12 mx-auto mb-3 border-4 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
                        <p class="text-gray-500">Reading scorecard data...</p>
                    </div>
                </div>
                <div class="hidden space-y-3" id="import-results">
                    <!-- Filled by JS -->
                </div>
                <div class="hidden text-center py-6" id="import-error">
                    <svg class="w-12 h-12 mx-auto text-red-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-red-600 font-medium" id="import-error-text"></p>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 flex justify-end gap-3" id="import-modal-footer">
                <button type="button" id="import-cancel-btn"
                        class="px-5 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <button type="button" id="import-apply-btn"
                        class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-lg transition hidden">
                    Apply Changes
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const teamAId = '{{ $match->team_a_id }}';
    const teamBId = '{{ $match->team_b_id }}';
    const winnerRadios = document.querySelectorAll('input[name="winner_team_id"]');
    const resultTypeSelect = document.getElementById('result_type');
    const marginInput = document.getElementById('margin');
    const resultSummaryInput = document.getElementById('result_summary');

    // Score inputs
    const teamAScore = document.getElementById('team_a_score');
    const teamAWickets = document.getElementById('team_a_wickets');
    const teamBScore = document.getElementById('team_b_score');
    const teamBWickets = document.getElementById('team_b_wickets');

    // Toss-based score card reordering
    const tossWonBy = document.getElementById('toss_won_by');
    const tossDecision = document.getElementById('toss_decision');
    const scoreCardA = document.getElementById('score-card-a');
    const scoreCardB = document.getElementById('score-card-b');
    const scoreCardAHeader = document.getElementById('score-card-a-header');
    const scoreCardBHeader = document.getElementById('score-card-b-header');
    const scoreCardAInnings = document.getElementById('score-card-a-innings');
    const scoreCardBInnings = document.getElementById('score-card-b-innings');

    function updateScoreCardOrder() {
        const tossWinner = tossWonBy.value;
        const decision = tossDecision.value;

        if (!tossWinner || !decision) return;

        // Determine if Team A bats first
        let teamABatsFirst;
        if (tossWinner === teamAId) {
            teamABatsFirst = (decision === 'bat');
        } else {
            teamABatsFirst = (decision === 'bowl');
        }

        // Update visual order
        scoreCardA.style.order = teamABatsFirst ? 1 : 2;
        scoreCardB.style.order = teamABatsFirst ? 2 : 1;

        // Update header colors (green = 1st innings, orange = 2nd innings)
        scoreCardAHeader.className = 'bg-gradient-to-r ' +
            (teamABatsFirst ? 'from-green-500 to-emerald-500' : 'from-orange-500 to-red-500') +
            ' px-6 py-4';
        scoreCardBHeader.className = 'bg-gradient-to-r ' +
            (teamABatsFirst ? 'from-orange-500 to-red-500' : 'from-green-500 to-emerald-500') +
            ' px-6 py-4';

        // Update innings labels
        scoreCardAInnings.textContent = (teamABatsFirst ? '1st' : '2nd') + ' Innings';
        scoreCardBInnings.textContent = (teamABatsFirst ? '2nd' : '1st') + ' Innings';
    }

    tossWonBy.addEventListener('change', updateScoreCardOrder);
    tossDecision.addEventListener('change', updateScoreCardOrder);

    // Auto-calculate winner when scores change
    function autoCalculateResult() {
        const aScore = parseInt(teamAScore.value) || 0;
        const bScore = parseInt(teamBScore.value) || 0;
        const aWickets = parseInt(teamAWickets.value) || 0;
        const bWickets = parseInt(teamBWickets.value) || 0;

        if (aScore === 0 && bScore === 0) return;

        // Find winner radio buttons
        const teamARadio = document.querySelector(`input[name="winner_team_id"][value="${teamAId}"]`);
        const teamBRadio = document.querySelector(`input[name="winner_team_id"][value="${teamBId}"]`);
        const tieRadio = document.querySelector('input[name="winner_team_id"][data-result-type="tie"]');

        if (aScore > bScore) {
            if (teamARadio) teamARadio.checked = true;
            resultTypeSelect.value = 'runs';
            marginInput.value = aScore - bScore;
        } else if (bScore > aScore) {
            if (teamBRadio) teamBRadio.checked = true;
            resultTypeSelect.value = 'wickets';
            marginInput.value = 10 - bWickets;
        } else {
            if (tieRadio) tieRadio.checked = true;
            resultTypeSelect.value = 'tie';
            marginInput.value = '';
        }
    }

    // Listen for score changes
    [teamAScore, teamBScore, teamAWickets, teamBWickets].forEach(input => {
        input?.addEventListener('change', autoCalculateResult);
        input?.addEventListener('input', autoCalculateResult);
    });

    // Auto-select result type based on winner selection
    winnerRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.dataset.resultType === 'tie') {
                resultTypeSelect.value = 'tie';
                marginInput.value = '';
            } else if (this.dataset.resultType === 'no_result') {
                resultTypeSelect.value = 'no_result';
                marginInput.value = '';
            } else if (resultTypeSelect.value === 'tie' || resultTypeSelect.value === 'no_result') {
                const aScore = parseInt(teamAScore.value) || 0;
                const bScore = parseInt(teamBScore.value) || 0;
                const bWickets = parseInt(teamBWickets.value) || 0;

                if (this.value === teamAId) {
                    resultTypeSelect.value = 'runs';
                    marginInput.value = aScore - bScore;
                } else {
                    resultTypeSelect.value = 'wickets';
                    marginInput.value = 10 - bWickets;
                }
            }
        });
    });

    // CricHeroes Sync with Preview Modal
    const parseBtn = document.getElementById('cricheroes-parse-btn');
    const statusEl = document.getElementById('cricheroes-status');
    const overrideCheckbox = document.getElementById('cricheroes-override');
    const teamAName = @json($match->teamA?->name ?? 'Team A');
    const teamBName = @json($match->teamB?->name ?? 'Team B');

    // Modal elements
    const modal = document.getElementById('import-modal');
    const modalBackdrop = document.getElementById('import-modal-backdrop');
    const modalLoading = document.getElementById('import-loading');
    const modalResults = document.getElementById('import-results');
    const modalError = document.getElementById('import-error');
    const modalErrorText = document.getElementById('import-error-text');
    const modalTitleText = document.getElementById('import-modal-title-text');
    const modalSpinner = document.getElementById('import-spinner');
    const modalCheckIcon = document.getElementById('import-check-icon');
    const applyBtn = document.getElementById('import-apply-btn');
    const cancelBtn = document.getElementById('import-cancel-btn');

    let pendingChanges = null;

    function showModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        modalLoading.classList.remove('hidden');
        modalResults.classList.add('hidden');
        modalError.classList.add('hidden');
        applyBtn.classList.add('hidden');
        modalSpinner.classList.remove('hidden');
        modalCheckIcon.classList.add('hidden');
        modalTitleText.textContent = 'Parsing scorecard...';
    }

    function hideModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function showModalResults(changes) {
        modalLoading.classList.add('hidden');
        modalResults.classList.remove('hidden');
        modalSpinner.classList.add('hidden');
        modalCheckIcon.classList.remove('hidden');
        modalTitleText.textContent = 'Import Preview';
        applyBtn.classList.remove('hidden');

        let html = '';
        changes.forEach(c => {
            const icon = c.type === 'score' ? '&#127951;' : c.type === 'toss' ? '&#129689;' : '&#127942;';
            html += `<div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                <span class="text-lg">${icon}</span>
                <div class="flex-1">
                    <div class="font-medium text-gray-800 dark:text-gray-200 text-sm">${c.label}</div>
                    <div class="text-teal-600 dark:text-teal-400 font-bold">${c.value}</div>
                </div>
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>`;
        });
        modalResults.innerHTML = html;
    }

    function showModalError(message) {
        modalLoading.classList.add('hidden');
        modalError.classList.remove('hidden');
        modalSpinner.classList.add('hidden');
        modalTitleText.textContent = 'Import Failed';
        modalErrorText.textContent = message;
    }

    cancelBtn.addEventListener('click', hideModal);
    modalBackdrop.addEventListener('click', hideModal);

    applyBtn.addEventListener('click', function() {
        if (!pendingChanges) return;
        applyParsedData(pendingChanges);
        hideModal();
        statusEl.innerHTML = '<span class="text-green-600 font-semibold">Scorecard imported! Review the form and click Save.</span>';
    });

    function hasExistingData() {
        return (parseInt(teamAScore.value) || 0) > 0 || (parseInt(teamBScore.value) || 0) > 0;
    }

    if (parseBtn) {
        parseBtn.addEventListener('click', function() {
            const text = document.getElementById('cricheroes_paste').value.trim();
            if (!text) {
                statusEl.innerHTML = '<span class="text-red-500">Please paste the scorecard text</span>';
                return;
            }

            if (hasExistingData() && !overrideCheckbox.checked) {
                statusEl.innerHTML = '<span class="text-yellow-600">Existing data found. Check "Override existing values" to replace it.</span>';
                return;
            }

            statusEl.innerHTML = '';
            showModal();

            // Small delay to show loading animation
            setTimeout(() => parseScorecardText(text), 400);
        });
    }

    // Fetch from CricHeroes URL (primary method)
    const fetchBtn = document.getElementById('cricheroes-fetch-btn');
    if (fetchBtn) {
        fetchBtn.addEventListener('click', function() {
            const url = document.getElementById('cricheroes_url').value.trim();
            if (!url) {
                statusEl.innerHTML = '<span class="text-red-500">Please enter a CricHeroes URL</span>';
                return;
            }

            if (hasExistingData() && !overrideCheckbox.checked) {
                statusEl.innerHTML = '<span class="text-yellow-600">Existing data found. Check "Override existing values" to replace it.</span>';
                return;
            }

            statusEl.innerHTML = '';
            showModal();
            modalTitleText.textContent = 'Fetching from CricHeroes...';

            fetch('{{ route("admin.matches.result.cricheroes", $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    showModalError(data.message || 'Failed to fetch data from CricHeroes.');
                    return;
                }

                const chData = data.data;
                const changes = [];
                const parsed = { teamA: null, teamB: null, toss: null, result: null };

                // Map CricHeroes teams to our teams
                if (chData.teams && chData.teams.length >= 2) {
                    let aIdx = null, bIdx = null;
                    for (let i = 0; i < chData.teams.length; i++) {
                        if (fuzzyMatch(chData.teams[i].name, teamAName)) aIdx = i;
                        if (fuzzyMatch(chData.teams[i].name, teamBName)) bIdx = i;
                    }
                    if (aIdx === null && bIdx === null) { aIdx = 0; bIdx = 1; }
                    else if (aIdx === null) aIdx = bIdx === 0 ? 1 : 0;
                    else if (bIdx === null) bIdx = aIdx === 0 ? 1 : 0;

                    const ta = chData.teams[aIdx];
                    const tb = chData.teams[bIdx];

                    parsed.teamA = { runs: ta.runs, wickets: ta.wickets, overs: ta.overs, extras: ta.extras || 0 };
                    parsed.teamB = { runs: tb.runs, wickets: tb.wickets, overs: tb.overs, extras: tb.extras || 0 };

                    changes.push({ type: 'score', label: teamAName, value: `${ta.runs}/${ta.wickets} (${ta.overs} ov)` + (ta.extras ? ` [Extras: ${ta.extras}]` : '') });
                    changes.push({ type: 'score', label: teamBName, value: `${tb.runs}/${tb.wickets} (${tb.overs} ov)` + (tb.extras ? ` [Extras: ${tb.extras}]` : '') });
                }

                // Toss
                if (chData.toss) {
                    parsed.toss = { team: chData.toss.winner, decision: chData.toss.decision };
                    changes.push({ type: 'toss', label: 'Toss', value: `${chData.toss.winner} won, elected to ${chData.toss.decision}` });
                }

                // Result
                if (chData.result) {
                    parsed.result = { winner: chData.result.winner, margin: chData.result.margin, type: chData.result.type };
                    changes.push({ type: 'result', label: 'Result', value: chData.result.summary || `${chData.result.winner} won by ${chData.result.margin} ${chData.result.type}` });
                }

                if (changes.length === 0) {
                    showModalError('No data could be extracted from the CricHeroes page.');
                    return;
                }

                pendingChanges = parsed;
                showModalResults(changes);
            })
            .catch(err => {
                showModalError('Network error: ' + err.message);
            });
        });
    }

    function parseScorecardText(text) {
        // Normalize: collapse multiple spaces/tabs, keep newlines
        const normalized = text.replace(/[^\S\n]+/g, ' ').trim();
        const lines = normalized.split(/\n/).map(l => l.trim()).filter(l => l);

        const scores = [];

        // Strategy 1: Score on same line as team name
        // Handles: "ASIAN ROYALS CC 156/8 (20.0 Ov)" or "Team Name 150/6 (20.0)"
        const inlinePattern = /^(.+?)\s+(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?$/i;

        // Strategy 2: Score line alone like "156/8 (20.0 Ov)" preceded by a team name line
        const scoreOnlyPattern = /^(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?$/i;
        const teamNamePattern = /^[A-Za-z][A-Za-z0-9\s&\-'\.]+$/;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];

            // Try inline match first
            const inlineM = line.match(inlinePattern);
            if (inlineM) {
                const name = inlineM[1].trim();
                // Skip if it looks like a toss/result line
                if (/toss|won by|opt to|elected/i.test(name)) continue;
                scores.push({ name, runs: parseInt(inlineM[2]), wickets: parseInt(inlineM[3]), overs: parseFloat(inlineM[4]) });
                continue;
            }

            // Try score-only line with team name on previous line
            const scoreM = line.match(scoreOnlyPattern);
            if (scoreM && i > 0 && teamNamePattern.test(lines[i-1]) && !/toss|won|opt/i.test(lines[i-1])) {
                scores.push({ name: lines[i-1].trim(), runs: parseInt(scoreM[1]), wickets: parseInt(scoreM[2]), overs: parseFloat(scoreM[3]) });
                continue;
            }
        }

        // Strategy 3: Find all score patterns anywhere in full text (fallback)
        if (scores.length < 2) {
            const globalPattern = /(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?/g;
            const foundScores = [];
            let gm;
            while ((gm = globalPattern.exec(normalized)) !== null) {
                foundScores.push({ runs: parseInt(gm[1]), wickets: parseInt(gm[2]), overs: parseFloat(gm[3]), pos: gm.index });
            }

            // For each found score, look backwards in the text for the team name
            for (const fs of foundScores) {
                const before = normalized.substring(0, fs.pos).trim();
                // Get the last "word group" before the score (the team name)
                const nameMatch = before.match(/([A-Za-z][A-Za-z0-9\s&\-'\.]*?)\s*$/);
                if (nameMatch) {
                    let name = nameMatch[1].trim();
                    // Clean up: remove leading junk from previous lines
                    const lastNewline = name.lastIndexOf('\n');
                    if (lastNewline >= 0) name = name.substring(lastNewline + 1).trim();
                    if (name && !/toss|won by|opt to|elected/i.test(name)) {
                        scores.push({ name, ...fs });
                    }
                }
            }
        }

        if (scores.length < 2) {
            showModalError('Could not parse scorecard. Paste the text including team names and scores like "Team 156/8 (20.0 Ov)"');
            return;
        }

        // Take only first 2 scores (first innings, second innings)
        const teamScores = scores.slice(0, 2);

        const mapping = mapTeams(teamScores, teamAName, teamBName);
        const changes = [];
        const parsed = { teamA: null, teamB: null, toss: null, result: null };

        if (mapping.a !== null) {
            const s = teamScores[mapping.a];
            parsed.teamA = s;
            changes.push({ type: 'score', label: teamAName, value: `${s.runs}/${s.wickets} (${s.overs} ov)` });
        }
        if (mapping.b !== null) {
            const s = teamScores[mapping.b];
            parsed.teamB = s;
            changes.push({ type: 'score', label: teamBName, value: `${s.runs}/${s.wickets} (${s.overs} ov)` });
        }

        // Parse toss - multiple CricHeroes formats:
        // "Toss: Team opt to field/bat" or "Toss: Team elected to bat"
        // "Team won the toss and opted to bat/bowl/field"
        let tossM = normalized.match(/Toss\s*:\s*(.+?)\s+(?:opt|elected|chose)\s+to\s+(bat|bowl|field)/i);
        if (!tossM) tossM = normalized.match(/(.+?)\s+won\s+the\s+toss\s+and\s+(?:opted|elected|chose)\s+to\s+(bat|bowl|field)/i);
        if (tossM) {
            const tossTeam = tossM[1].trim();
            const tossChoice = tossM[2].toLowerCase();
            const decision = (tossChoice === 'field' || tossChoice === 'bowl') ? 'bowl' : 'bat';
            parsed.toss = { team: tossTeam, decision };
            changes.push({ type: 'toss', label: 'Toss', value: `${tossTeam} won, elected to ${tossChoice}` });
        }

        // Parse result
        const resultM = normalized.match(/(.+?)\s+won\s+by\s+(\d+)\s+(runs?|wickets?)/i);
        if (resultM) {
            parsed.result = { winner: resultM[1].trim(), margin: parseInt(resultM[2]), type: resultM[3].toLowerCase().startsWith('run') ? 'runs' : 'wickets' };
            changes.push({ type: 'result', label: 'Result', value: `${resultM[1].trim()} won by ${resultM[2]} ${resultM[3]}` });
        }

        pendingChanges = parsed;
        showModalResults(changes);
    }

    function applyParsedData(data) {
        if (data.teamA) {
            teamAScore.value = data.teamA.runs;
            document.getElementById('team_a_wickets').value = data.teamA.wickets;
            document.getElementById('team_a_overs').value = data.teamA.overs;
            if (data.teamA.extras !== undefined) document.getElementById('team_a_extras').value = data.teamA.extras;
        }
        if (data.teamB) {
            teamBScore.value = data.teamB.runs;
            document.getElementById('team_b_wickets').value = data.teamB.wickets;
            document.getElementById('team_b_overs').value = data.teamB.overs;
            if (data.teamB.extras !== undefined) document.getElementById('team_b_extras').value = data.teamB.extras;
        }
        if (data.toss) {
            const tossTeamId = fuzzyMatch(data.toss.team, teamAName) ? teamAId : teamBId;
            tossWonBy.value = tossTeamId;
            tossDecision.value = data.toss.decision;
            updateScoreCardOrder();
        }
        if (data.result) {
            const winnerId = fuzzyMatch(data.result.winner, teamAName) ? teamAId : teamBId;
            const winnerRadio = document.querySelector(`input[name="winner_team_id"][value="${winnerId}"]`);
            if (winnerRadio) winnerRadio.checked = true;
            resultTypeSelect.value = data.result.type;
            marginInput.value = data.result.margin;
        } else {
            autoCalculateResult();
        }
    }

    function mapTeams(scores, teamAName, teamBName) {
        let aIdx = null, bIdx = null;

        for (let i = 0; i < scores.length; i++) {
            if (fuzzyMatch(scores[i].name, teamAName)) aIdx = i;
            if (fuzzyMatch(scores[i].name, teamBName)) bIdx = i;
        }

        // If no fuzzy match, assume first score = first innings team, second = second innings team
        if (aIdx === null && bIdx === null) {
            aIdx = 0;
            bIdx = 1;
        } else if (aIdx === null) {
            aIdx = bIdx === 0 ? 1 : 0;
        } else if (bIdx === null) {
            bIdx = aIdx === 0 ? 1 : 0;
        }

        return { a: aIdx, b: bIdx };
    }

    function fuzzyMatch(str1, str2) {
        const a = str1.toLowerCase().trim();
        const b = str2.toLowerCase().trim();
        return a === b || a.includes(b) || b.includes(a);
    }

    // Run initial calculation only if both innings are complete
    @if(isset($ballStats) && $ballStats['hasBallData'] && $ballStats['bothInningsComplete'])
    autoCalculateResult();
    @endif
});
</script>
@endpush
@endsection
