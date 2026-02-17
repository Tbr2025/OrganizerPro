@extends('backend.layouts.app')

@section('title', 'Record Match Result | ' . config('app.name'))

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => $match->match_title ?? 'Match', 'route' => route('admin.matches.show', $match)],
    ['name' => 'Record Result']
]" />

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
                    @if($match->teamA?->team_logo)
                        <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="{{ $match->teamA->name }}"
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 border-white/30">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
                            {{ substr($match->teamA?->name ?? 'A', 0, 2) }}
                        </div>
                    @endif
                    <div class="mt-2 font-semibold">{{ $match->teamA?->name ?? 'Team A' }}</div>
                </div>
                <div class="text-2xl font-bold text-blue-200">VS</div>
                <div class="text-center">
                    @if($match->teamB?->team_logo)
                        <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="{{ $match->teamB->name }}"
                             class="w-16 h-16 mx-auto rounded-full object-cover border-2 border-white/30">
                    @else
                        <div class="w-16 h-16 mx-auto rounded-full bg-white/20 flex items-center justify-center text-xl font-bold">
                            {{ substr($match->teamB?->name ?? 'B', 0, 2) }}
                        </div>
                    @endif
                    <div class="mt-2 font-semibold">{{ $match->teamB?->name ?? 'Team B' }}</div>
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

    <!-- Result Form -->
    <form action="{{ route('admin.matches.result.update', $match) }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Team A Score Card -->
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    @if($match->teamA?->team_logo)
                        <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="" class="w-8 h-8 rounded-full mr-3 object-cover">
                    @endif
                    {{ $match->teamA?->name ?? 'Team A' }} - Score
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
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-orange-500 to-red-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    @if($match->teamB?->team_logo)
                        <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="" class="w-8 h-8 rounded-full mr-3 object-cover">
                    @endif
                    {{ $match->teamB?->name ?? 'Team B' }} - Score
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

        <!-- Toss Details -->
        <div class="card rounded-2xl overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-yellow-500 to-amber-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Toss Details (Optional)
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
            // Team A won
            if (teamARadio) teamARadio.checked = true;
            resultTypeSelect.value = 'runs';
            marginInput.value = aScore - bScore;
        } else if (bScore > aScore) {
            // Team B won
            if (teamBRadio) teamBRadio.checked = true;
            resultTypeSelect.value = 'wickets';
            marginInput.value = 10 - bWickets;
        } else {
            // Tie
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
                // Recalculate based on scores
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

    // Run initial calculation only if both innings are complete
    @if(isset($ballStats) && $ballStats['hasBallData'] && $ballStats['bothInningsComplete'])
    autoCalculateResult();
    @endif
});
</script>
@endpush
@endsection
