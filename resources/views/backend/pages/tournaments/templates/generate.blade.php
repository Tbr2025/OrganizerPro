@extends('backend.layouts.app')

@section('title', 'Generate Poster | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="array_filter([
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    auth()->user()->hasRole('Superadmin') ? ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)] : null,
    ['name' => 'Generate Poster']
])" />

<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-purple-700 via-indigo-600 to-purple-800 rounded-2xl p-8 mb-8">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 400 200" preserveAspectRatio="none"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="white" stroke-width="0.5"/></pattern></defs><rect width="400" height="200" fill="url(#grid)"/></svg>
        </div>
        <div class="relative flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white mb-1">Generate Poster</h1>
                <p class="text-purple-200 text-sm">Create professional posters with real tournament data</p>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Poster Type Selection - Horizontal Pill Tabs --}}
    <div class="mb-6" x-data="{ type: '{{ request('type', 'match_poster') }}' }">
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
            <button type="button" @click="type = 'match_poster'; updateType('match_poster')"
                    :class="type === 'match_poster' ? 'bg-cyan-500 text-white shadow-lg shadow-cyan-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-cyan-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Match Poster
            </button>
            <button type="button" @click="type = 'match_summary'; updateType('match_summary')"
                    :class="type === 'match_summary' ? 'bg-yellow-500 text-white shadow-lg shadow-yellow-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-yellow-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Summary
            </button>
            <button type="button" @click="type = 'award_poster'; updateType('award_poster')"
                    :class="type === 'award_poster' ? 'bg-red-500 text-white shadow-lg shadow-red-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-red-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Award
            </button>
            <button type="button" @click="type = 'welcome_card'; updateType('welcome_card')"
                    :class="type === 'welcome_card' ? 'bg-green-500 text-white shadow-lg shadow-green-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-green-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Welcome
            </button>
            <button type="button" @click="type = 'point_table'; updateType('point_table')"
                    :class="type === 'point_table' ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-blue-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                Points
            </button>
            <button type="button" @click="type = 'fixtures_poster'; updateType('fixtures_poster')"
                    :class="type === 'fixtures_poster' ? 'bg-teal-500 text-white shadow-lg shadow-teal-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-teal-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Fixtures
            </button>
            <button type="button" @click="type = 'flyer'; updateType('flyer')"
                    :class="type === 'flyer' ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-orange-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                Flyer
            </button>
            <button type="button" @click="type = 'champions_poster'; updateType('champions_poster')"
                    :class="type === 'champions_poster' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/25' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-amber-300'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                Champions
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Left: Data Selection --}}
        <div class="lg:col-span-3 space-y-5">

            {{-- Data Selection Based on Type --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center text-sm">
                    <span class="w-7 h-7 rounded-lg bg-purple-100 dark:bg-purple-900/50 text-purple-600 flex items-center justify-center mr-2.5 text-xs font-bold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7c-2 0-3 1-3 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6M12 9v6"/></svg>
                    </span>
                    Select Data
                </h3>

                {{-- Match Selection (for match_poster and match_summary) --}}
                <div id="matchSelection" class="{{ in_array(request('type', 'match_poster'), ['match_poster', 'match_summary']) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Match</label>
                    <select id="matchSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">-- Select a match --</option>
                        @foreach($matches as $match)
                            <option value="{{ $match->id }}"
                                    data-team-a="{{ $match->teamA?->name }}"
                                    data-team-b="{{ $match->teamB?->name }}"
                                    data-team-a-short="{{ $match->teamA?->short_name ?? $match->teamA?->name }}"
                                    data-team-b-short="{{ $match->teamB?->short_name ?? $match->teamB?->name }}"
                                    data-team-a-logo="{{ $match->teamA?->team_logo_url ?? '' }}"
                                    data-team-b-logo="{{ $match->teamB?->team_logo_url ?? '' }}"
                                    data-team-a-captain-image="{{ $match->teamA?->captain_image_url ?? '' }}"
                                    data-team-b-captain-image="{{ $match->teamB?->captain_image_url ?? '' }}"
                                    data-team-a-captain-name="{{ $match->teamA?->captain?->name ?? '' }}"
                                    data-team-b-captain-name="{{ $match->teamB?->captain?->name ?? '' }}"
                                    data-date="{{ $match->match_date?->format('M d, Y') }}"
                                    data-time="{{ $match->start_time ? \Carbon\Carbon::parse($match->start_time)->format('h:i A') : '' }}"
                                    data-venue="{{ $match->ground?->name ?? $match->venue }}"
                                    data-stage="{{ $match->stage }}"
                                    data-stage-display="{{ $match->stage_display }}"
                                    data-status="{{ $match->status }}"
                                    data-team-a-score="{{ $match->result?->team_a_score_display ?? '' }}"
                                    data-team-b-score="{{ $match->result?->team_b_score_display ?? '' }}"
                                    data-winner="{{ $match->winner?->name }}"
                                    data-winner-logo="{{ $match->winner?->team_logo ?? '' }}"
                                    data-result-summary="{{ $match->result?->result_summary ?? '' }}"
                                    data-match-number="{{ $match->match_number ?? $match->id }}"
                                    @php
                                        $motmAward = $match->matchAwards->first(fn($a) => in_array($a->tournamentAward?->slug, ['man-of-the-match', 'player-of-the-match']));
                                        $bestBatAward = $match->matchAwards->first(fn($a) => $a->tournamentAward?->slug === 'best-batsman');
                                        $bestBowlAward = $match->matchAwards->first(fn($a) => $a->tournamentAward?->slug === 'best-bowler');
                                    @endphp
                                    data-motm-name="{{ $motmAward?->player?->name ?? '' }}"
                                    data-motm-image="{{ $motmAward?->player?->image_path ?? '' }}"
                                    data-best-batsman="{{ $bestBatAward?->player?->name ?? '' }}"
                                    data-best-bowler="{{ $bestBowlAward?->player?->name ?? '' }}">
                                Match #{{ $match->match_number ?? $match->id }}: {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
                                @if($match->match_date) - {{ $match->match_date->format('M d') }} @endif
                                @if($match->status === 'completed') (Completed) @endif
                            </option>
                        @endforeach
                    </select>

                    @if($matches->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">No matches found. <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}" class="text-purple-600 hover:underline">Create fixtures first</a>.</p>
                    @endif

                    {{-- Scorecard note --}}
                    <div id="scorecardNote" class="hidden mt-3">
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                            <p class="text-xs text-blue-700 dark:text-blue-300"><strong>Scorecard Tables:</strong> If the template includes scorecard table elements, top batsmen and bowlers will be auto-populated from the match's scorecard data. No manual entry needed.</p>
                        </div>
                    </div>

                    {{-- Match Summary Stats (shown when match selected for match_summary type) --}}
                    <div id="matchSummaryStats" class="hidden space-y-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        {{-- Score Summary --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Score Summary</label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1" id="summaryTeamALabel">Team A Score</label>
                                    <input type="text" id="summaryTeamAScore" placeholder="e.g. 198/4 (20.0)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1" id="summaryTeamBLabel">Team B Score</label>
                                    <input type="text" id="summaryTeamBScore" placeholder="e.g. 200/6 (18.4)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- Result & Winner --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Result Summary</label>
                                <input type="text" id="summaryResultSummary" placeholder="e.g. Team Beta won by 4 wkts" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Winner</label>
                                <input type="text" id="summaryWinnerName" placeholder="Auto from match" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" readonly>
                            </div>
                        </div>

                        {{-- MOTM & Awards --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Awards</label>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Man of the Match</label>
                                    <input type="text" id="summaryMotmName" placeholder="Auto from match" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Best Batsman</label>
                                    <input type="text" id="summaryBestBatsman" placeholder="Auto from match" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Best Bowler</label>
                                    <input type="text" id="summaryBestBowler" placeholder="Auto from match" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- Batting Performance --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Batting Figures (MOTM)</label>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Runs</label>
                                    <input type="number" id="summaryBatRuns" placeholder="59" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Balls</label>
                                    <input type="number" id="summaryBatBalls" placeholder="36" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">4s</label>
                                    <input type="number" id="summaryBatFours" placeholder="9" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">6s</label>
                                    <input type="number" id="summaryBatSixes" placeholder="1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 mt-2 cursor-pointer">
                                <input type="checkbox" id="summaryBatNotOut" class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                <span class="text-xs text-gray-600 dark:text-gray-400">Not Out *</span>
                            </label>
                        </div>

                        {{-- Bowling Performance --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bowling Figures (MOTM)</label>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Overs</label>
                                    <input type="text" id="summaryBowlOvers" placeholder="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Maidens</label>
                                    <input type="number" id="summaryBowlMaidens" placeholder="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Runs</label>
                                    <input type="number" id="summaryBowlRuns" placeholder="25" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Wickets</label>
                                    <input type="number" id="summaryBowlWickets" placeholder="2" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Player Selection (for welcome_card) --}}
                <div id="playerSelection" class="{{ request('type') === 'welcome_card' ? '' : 'hidden' }}">
                    {{-- Auto Mode Toggle --}}
                    <div class="flex items-center justify-between mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"
                         x-data="{ autoMode: {{ $autoWelcome ?? true ? 'true' : 'false' }}, toggling: false }">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <div>
                                <span class="text-sm font-medium text-green-800 dark:text-green-300">Auto Mode</span>
                                <p class="text-xs text-green-600 dark:text-green-400">Automatically send welcome card when a player is approved</p>
                            </div>
                        </div>
                        <button type="button"
                                @click="toggling = true; fetch('{{ route('admin.tournaments.templates.toggle-auto-welcome', $tournament) }}', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                                    body: JSON.stringify({enabled: !autoMode})
                                }).then(r => r.json()).then(d => { autoMode = d.enabled; toggling = false; }).catch(() => toggling = false)"
                                :class="autoMode ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                                :disabled="toggling">
                            <span :class="autoMode ? 'translate-x-5' : 'translate-x-0'"
                                  class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out mt-0.5 ml-0.5"></span>
                        </button>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Filter by Team</label>
                    <select id="teamFilter" onchange="filterPlayersByTeam()" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 mb-3">
                        <option value="">All Teams</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Player</label>

                    {{-- Hidden native select for existing JS compatibility --}}
                    <select id="playerSelect" class="hidden">
                        <option value="">-- Select a player --</option>
                        @foreach($players as $player)
                            @php
                                $effectiveTeamId = $tournamentTeamMap[$player->id] ?? $player->actual_team_id;
                                $effectiveTeam = $effectiveTeamId ? $teams->firstWhere('id', $effectiveTeamId) : $player->actualTeam;
                            @endphp
                            <option value="{{ $player->id }}"
                                    data-name="{{ $player->name }}"
                                    data-jersey="{{ $player->jersey_number }}"
                                    data-team="{{ $effectiveTeam?->name ?? $player->actualTeam?->name }}"
                                    data-team-id="{{ $effectiveTeamId }}"
                                    data-team-logo="{{ $effectiveTeam?->team_logo_url ?? $player->actualTeam?->team_logo_url ?? '' }}"
                                    data-photo="{{ $player->image_path ? asset('storage/' . $player->image_path) : '' }}"
                                    data-type="{{ $player->playerType?->type ?? '' }}"
                                    data-batting="{{ $player->battingProfile?->style ?? '' }}"
                                    data-bowling="{{ $player->bowlingProfile?->style ?? '' }}">
                                {{ $player->name }} @if($player->actualTeam) ({{ $player->actualTeam->name }}) @else (Registered) @endif
                            </option>
                        @endforeach
                    </select>

                    {{-- Custom rich player dropdown --}}
                    <div x-data="playerDropdown()" class="relative" x-on:click.away="open = false" x-on:keydown.escape="open = false">
                        {{-- Trigger --}}
                        <button type="button" @click="open = !open; if(open) $nextTick(() => $refs.searchInput.focus())"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 px-3 py-2 text-left text-sm flex items-center gap-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                :class="open ? 'ring-2 ring-purple-500 border-purple-500' : ''">
                            <template x-if="selectedPlayer">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <img :src="selectedPlayer.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(selectedPlayer.name) + '&background=EBF4FF&color=7F9CF5'"
                                         class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-900 dark:text-white truncate" x-text="selectedPlayer.name"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="selectedPlayer.team || 'Registered'"></div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!selectedPlayer">
                                <span class="text-gray-400 flex-1">-- Select a player --</span>
                            </template>
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        {{-- Dropdown panel --}}
                        <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-xl max-h-96 overflow-hidden flex flex-col">
                            {{-- Search --}}
                            <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                                <input type="text" x-model="search" x-ref="searchInput" @input="filterPlayers()"
                                       placeholder="Search player..."
                                       class="w-full text-sm border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 py-1.5 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            {{-- Player list --}}
                            <div class="overflow-y-auto" style="max-height: 320px;">
                                <template x-for="player in filteredPlayers" :key="player.id">
                                    <div @click="selectPlayer(player)"
                                         class="flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/20 transition-colors border-b border-gray-50 dark:border-gray-700/50"
                                         :class="selectedPlayer && selectedPlayer.id === player.id ? 'bg-purple-50 dark:bg-purple-900/20' : ''">
                                        <img :src="player.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(player.name) + '&background=EBF4FF&color=7F9CF5'"
                                             class="w-10 h-10 rounded-full object-cover flex-shrink-0 ring-2 ring-gray-100 dark:ring-gray-700" alt="">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="player.name"></span>
                                                <span class="text-[10px] font-mono bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300 px-1.5 py-0.5 rounded" x-text="'ID:' + player.id"></span>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="player.team || 'Registered'"></div>
                                            <div x-show="player.email" class="text-[10px] text-gray-400 dark:text-gray-500 truncate" x-text="player.email"></div>
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                <template x-if="player.type">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300" x-text="player.type"></span>
                                                </template>
                                                <template x-if="player.batting">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300" x-text="player.batting"></span>
                                                </template>
                                                <template x-if="player.bowling">
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300" x-text="player.bowling"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="filteredPlayers.length === 0" class="px-3 py-4 text-sm text-gray-400 text-center">No players found</div>
                            </div>
                        </div>
                    </div>

                    @if($players->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">No registered players found.</p>
                    @endif
                </div>

                {{-- Award Selection (for award_poster) --}}
                <div id="awardSelection" class="{{ request('type') === 'award_poster' ? '' : 'hidden' }}">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Match</label>
                            <select id="awardMatchSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" onchange="loadMatchAwards(this.value)">
                                <option value="">-- Select a match --</option>
                                @foreach($matches as $match)
                                    <option value="{{ $match->id }}"
                                        data-team-a="{{ $match->teamA?->name ?? 'TBD' }}"
                                        data-team-b="{{ $match->teamB?->name ?? 'TBD' }}"
                                        data-team-a-logo="{{ $match->teamA?->team_logo_url ?? '' }}"
                                        data-team-b-logo="{{ $match->teamB?->team_logo_url ?? '' }}"
                                        data-date="{{ $match->match_date ? $match->match_date->format('d M Y') : '' }}"
                                        data-venue="{{ $match->ground?->name ?? '' }}"
                                        data-result="{{ $match->result?->result_summary ?? '' }}"
                                        data-status="{{ $match->status }}">
                                        Match #{{ $match->match_number ?? $match->id }}: {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
                                        @if($match->match_date) - {{ $match->match_date->format('M d') }} @endif
                                        @if($match->status === 'completed') (Completed)
                                        @elseif($match->status === 'live') (Live)
                                        @else (Upcoming)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Warning for non-completed matches --}}
                        <div id="awardMatchWarning" class="hidden rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 p-3">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-amber-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <p class="text-sm text-amber-700 dark:text-amber-300">Match not completed yet. Scores and result will need to be entered manually.</p>
                            </div>
                        </div>

                        <div id="awardPlayerSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Player</label>
                            <div class="relative">
                                <input type="text" id="awardPlayerSearch" placeholder="Search player..." class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm mb-2 pl-9">
                                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <select id="awardPlayerSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700" size="6">
                                <option value="">-- Select a player --</option>
                            </select>
                            <div id="awardNameInput" class="mt-2">
                                <label class="block text-xs text-gray-500 mb-1">Award Name</label>
                                <input type="text" id="awardNameOverride" placeholder="e.g. Man of the Match" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            </div>
                        </div>

                        {{-- Player Override Section --}}
                        <div id="awardPlayerOverride" class="hidden space-y-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                             x-data="awardImageCropper()" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Player Details</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Player Name</label>
                                    <input type="text" id="awardPlayerName" placeholder="Auto from award" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <p class="text-xs text-gray-400 mt-1">Leave empty to use award data</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Player Image</label>
                                    {{-- DB image preview --}}
                                    <div id="awardPlayerImagePreview" class="hidden mb-2">
                                        <div class="flex items-center gap-2">
                                            <img id="awardPlayerImageThumb" src="" alt="Player" class="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-600">
                                            <span id="awardPlayerImageLabel" class="text-xs text-green-600">From database</span>
                                        </div>
                                    </div>

                                    {{-- Custom upload preview --}}
                                    <template x-if="croppedPreview">
                                        <div class="relative group mb-2">
                                            <img :src="croppedPreview" alt="Cropped" class="w-full h-28 object-contain rounded-lg border border-gray-200 dark:border-gray-600" :class="isTransparent ? 'bg-[url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEwAACxMBAJqcGAAAADFJREFUOI1jfPbs2X8GKgImahkAAcMoA0YYMDIQ+QIjIyMjVQMRbACxXjBkDCBXMwCbHQgR0+clpgAAAABJRU5ErkJggg==)]' : 'bg-gray-50 dark:bg-gray-800'">
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center gap-2">
                                                <button type="button" @click="openFilePicker()" class="px-2.5 py-1 bg-white rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100">Change</button>
                                                <button type="button" @click="removeCropped()" class="px-2.5 py-1 bg-red-500 rounded-lg text-xs font-semibold text-white hover:bg-red-600">Remove</button>
                                            </div>
                                            <div x-show="skipBgRemoval" class="absolute bottom-1 right-1 text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">Transparent</div>
                                        </div>
                                    </template>

                                    {{-- Dropzone --}}
                                    <template x-if="!croppedPreview">
                                        <div @click="openFilePicker()"
                                             @dragover.prevent="isDragging = true"
                                             @dragleave.prevent="isDragging = false"
                                             @drop.prevent="handleDrop($event)"
                                             :class="isDragging ? 'border-purple-400 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-purple-300'"
                                             class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer transition">
                                            <svg class="w-6 h-6 mx-auto mb-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <p class="text-xs text-gray-500">Drop image or click to browse</p>
                                            <p class="text-[10px] text-gray-400 mt-0.5">Crop & BG removal available</p>
                                        </div>
                                    </template>

                                    {{-- Hidden file input --}}
                                    <input type="file" x-ref="fileInput" @change="onFileSelected($event)" accept="image/*" class="hidden">
                                    {{-- Hidden input to store cropped blob for FormData --}}
                                    <input type="file" id="awardPlayerImageUpload" class="hidden">
                                </div>
                            </div>

                            {{-- Crop Modal --}}
                            <div x-show="showCropModal" x-transition.opacity class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @keydown.escape.window="closeCropModal()">
                                <div @click.outside="closeCropModal()" class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                                    {{-- Modal Header --}}
                                    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <h4 class="font-semibold text-gray-900 dark:text-white text-sm">Crop Player Image</h4>
                                        <button type="button" @click="closeCropModal()" class="text-gray-400 hover:text-gray-600 p-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                    </div>

                                    {{-- Aspect Ratio Selection --}}
                                    <div class="px-5 pt-3 flex items-center gap-2">
                                        <span class="text-xs text-gray-500 mr-1">Ratio:</span>
                                        <template x-for="r in ratios" :key="r.value">
                                            <button type="button" @click="setAspectRatio(r.value)"
                                                    :class="activeRatio === r.value ? 'bg-purple-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200'"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold transition" x-text="r.label"></button>
                                        </template>
                                    </div>

                                    {{-- Crop Area --}}
                                    <div class="px-5 py-3">
                                        <div class="bg-gray-900 rounded-lg overflow-hidden" style="max-height: 400px;">
                                            <img x-ref="cropImage" :src="cropImageSrc" alt="Crop" class="max-w-full" style="display: block; max-height: 400px;">
                                        </div>
                                    </div>

                                    {{-- Modal Footer --}}
                                    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <label class="flex items-center gap-2 cursor-pointer" x-show="!isTransparent">
                                            <input type="checkbox" x-model="removeBg" class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">Remove Background</span>
                                        </label>
                                        <span x-show="isTransparent" class="text-xs text-green-600 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            Transparent — no BG removal needed
                                        </span>
                                        <div class="flex gap-2">
                                            <button type="button" @click="closeCropModal()" class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-800 rounded-lg">Cancel</button>
                                            <button type="button" @click="applyCrop()" class="px-4 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition">
                                                Apply Crop
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Manual Stats Input (shown after award selected) --}}
                        <div id="awardStatsSection" class="hidden space-y-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            {{-- Score Summary --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Score Summary</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1" id="teamAScoreLabel">Team A Score</label>
                                        <input type="text" id="awardTeamAScore" placeholder="e.g. 198/4 (20 Ov)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1" id="teamBScoreLabel">Team B Score</label>
                                        <input type="text" id="awardTeamBScore" placeholder="e.g. 200/6 (18.4 Ov)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- Result Summary --}}
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Result Summary</label>
                                <input type="text" id="awardResultSummary" placeholder="e.g. Team Beta won by 4 wkts" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            </div>

                            {{-- Batting Performance --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Batting Performance</label>
                                <div class="grid grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Runs</label>
                                        <input type="number" id="batRuns" placeholder="59" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Balls</label>
                                        <input type="number" id="batBalls" placeholder="36" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">4s</label>
                                        <input type="number" id="batFours" placeholder="9" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">6s</label>
                                        <input type="number" id="batSixes" placeholder="1" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 mt-2 cursor-pointer">
                                    <input type="checkbox" id="batNotOut" class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Not Out *</span>
                                </label>
                            </div>

                            {{-- Bowling Performance --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bowling Performance</label>
                                <div class="grid grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Overs</label>
                                        <input type="text" id="bowlOvers" placeholder="4" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Maidens</label>
                                        <input type="number" id="bowlMaidens" placeholder="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Runs</label>
                                        <input type="number" id="bowlRuns" placeholder="25" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Wickets</label>
                                        <input type="number" id="bowlWickets" placeholder="2" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Group Selection (for point_table) --}}
                <div id="groupSelection" class="{{ request('type') === 'point_table' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Group</label>
                    <select id="groupSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">-- Select a group --</option>
                        @isset($groups)
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" data-name="{{ $group->name }}">{{ $group->name }}</option>
                            @endforeach
                        @endisset
                    </select>
                    @if(!isset($groups) || (isset($groups) && $groups->isEmpty()))
                        <p class="text-sm text-gray-500 mt-2">No groups found. <a href="{{ route('admin.tournaments.groups.index', $tournament) }}" class="text-purple-600 hover:underline">Create groups first</a>.</p>
                    @endif
                </div>

                {{-- Fixtures Poster Controls --}}
                <div id="fixturesSelection" class="hidden space-y-5" x-data="{ fixtureCount: '5', customCount: '', fixtureLayout: 'row' }">
                    {{-- Design Layout --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Design Layout</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="fixtureLayout = 'row'"
                                :class="fixtureLayout === 'row' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/30 ring-2 ring-teal-500' : 'border-gray-200 dark:border-gray-700 hover:border-teal-300'"
                                class="border-2 rounded-xl p-3 transition text-left">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Row List</span>
                                </div>
                                <div class="space-y-1">
                                    <div class="h-2 bg-gray-200 dark:bg-gray-600 rounded w-full"></div>
                                    <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded w-full"></div>
                                    <div class="h-2 bg-gray-200 dark:bg-gray-600 rounded w-full"></div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">IPL-style horizontal rows</p>
                            </button>
                            <button type="button" @click="fixtureLayout = 'card'"
                                :class="fixtureLayout === 'card' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/30 ring-2 ring-teal-500' : 'border-gray-200 dark:border-gray-700 hover:border-teal-300'"
                                class="border-2 rounded-xl p-3 transition text-left">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Card Grid</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1">
                                    <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div class="h-4 bg-gray-100 dark:bg-gray-700 rounded"></div>
                                    <div class="h-4 bg-gray-100 dark:bg-gray-700 rounded"></div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Match cards in grid</p>
                            </button>
                        </div>
                        <input type="hidden" id="fixtureLayoutValue" :value="fixtureLayout" value="row">
                    </div>

                    {{-- Match Count --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Number of Upcoming Matches</label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="fixtureCount = '5'; customCount = ''" :class="fixtureCount === '5' ? 'bg-teal-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'" class="px-4 py-2 rounded-lg text-sm font-medium transition">5</button>
                            <button type="button" @click="fixtureCount = '10'; customCount = ''" :class="fixtureCount === '10' ? 'bg-teal-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'" class="px-4 py-2 rounded-lg text-sm font-medium transition">10</button>
                            <button type="button" @click="fixtureCount = 'all'; customCount = ''" :class="fixtureCount === 'all' ? 'bg-teal-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'" class="px-4 py-2 rounded-lg text-sm font-medium transition">All</button>
                            <input type="number" min="1" max="50" placeholder="Custom"
                                   x-model="customCount"
                                   @input="fixtureCount = 'custom'"
                                   :class="fixtureCount === 'custom' ? 'border-teal-500 ring-1 ring-teal-500' : 'border-gray-300 dark:border-gray-600'"
                                   class="w-20 rounded-lg dark:bg-gray-700 text-sm px-2 py-2">
                            <input type="hidden" id="fixtureCountValue" value="5"
                                   :value="fixtureCount === 'custom' ? customCount : (fixtureCount === 'all' ? '100' : fixtureCount)">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Upcoming matches will be rendered into the Fixture Area element on the template.</p>
                </div>
            </div>

            {{-- Template Selection --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center text-sm">
                    <span class="w-7 h-7 rounded-lg bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 flex items-center justify-center mr-2.5 text-xs font-bold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    </span>
                    Choose Template
                </h3>

                <div id="templatesList" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @forelse($templates as $template)
                        <div class="relative group">
                            <label class="cursor-pointer block">
                                <input type="radio" name="template_id" value="{{ $template->id }}" class="hidden peer" {{ $loop->first ? 'checked' : '' }}>
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all peer-checked:border-purple-500 peer-checked:ring-2 peer-checked:ring-purple-500/20 hover:border-purple-300 hover:shadow-md">
                                    @if($template->background_image)
                                        <img src="{{ $template->background_image_url }}" alt="{{ $template->name }}" class="w-full h-36 object-cover">
                                    @else
                                        <div class="w-full h-36 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                                            <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    <div class="px-3 py-2 flex items-center justify-between">
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 truncate">{{ $template->name }}</p>
                                        @if($template->is_default)
                                            <span class="text-[10px] bg-purple-100 dark:bg-purple-900/50 text-purple-600 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0 ml-1">Default</span>
                                        @endif
                                    </div>
                                </div>
                                {{-- Selected indicator (sibling of input.peer) --}}
                                <div class="absolute top-2 left-2 w-5 h-5 rounded-full bg-purple-500 text-white hidden peer-checked:flex items-center justify-center shadow-lg z-10">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </label>
                            @role('Superadmin')
                            <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                               target="_blank"
                               class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/50 backdrop-blur text-white opacity-0 group-hover:opacity-100 hover:bg-black/70 shadow-sm transition-all z-10"
                               title="Edit Template">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @endrole
                        </div>
                    @empty
                        <div class="col-span-full text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/></svg>
                            <p class="text-sm">No templates found for this type.</p>
                            @role('Superadmin')
                            <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => request('type', 'match_poster')]) }}"
                               class="text-purple-600 hover:underline mt-2 inline-block text-sm">Create a template</a>
                            @endrole
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Field Visibility Toggles --}}
            <div id="fieldTogglesSection" class="hidden bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center text-sm cursor-pointer" onclick="document.getElementById('togglesBody').classList.toggle('hidden'); this.querySelector('.chevron-icon').classList.toggle('rotate-180')">
                    <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 flex items-center justify-center mr-2.5 text-xs font-bold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </span>
                    Field Visibility
                    <span class="ml-auto text-xs text-gray-400 font-normal mr-2">Toggle fields on/off</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform chevron-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </h3>
                <div id="togglesBody" class="grid grid-cols-2 gap-x-4 gap-y-1.5 max-h-[250px] overflow-y-auto">
                    {{-- Populated by JS --}}
                </div>
            </div>
        </div>

        {{-- Right: Preview & Actions --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 sticky top-4 overflow-hidden">
                {{-- Preview Header --}}
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm flex items-center">
                        <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Preview
                    </h3>
                    <span id="previewStatus" class="text-xs text-gray-400 font-medium">Ready</span>
                </div>

                {{-- Preview Area --}}
                <div id="previewArea" class="bg-gray-50 dark:bg-gray-900/50 p-4 min-h-[350px] flex items-center justify-center">
                    <div id="previewPlaceholder" class="text-center text-gray-400 py-8">
                        <div class="w-20 h-20 mx-auto mb-4 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No preview yet</p>
                        <p class="text-xs text-gray-400 mt-1">Select data and click Generate</p>
                    </div>
                    <img id="previewImage" src="" alt="Preview" class="hidden max-w-full max-h-[500px] rounded-lg shadow-xl ring-1 ring-black/5">
                    <div id="previewLoading" class="hidden text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                            <svg class="w-8 h-8 animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Generating poster...</p>
                        <p class="text-xs text-gray-400 mt-1">This may take a few seconds</p>
                    </div>
                </div>

                {{-- Data Summary --}}
                <div id="dataSummary" class="border-t border-gray-100 dark:border-gray-700 px-5 py-3 hidden">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Selected Data</h4>
                    <div id="summaryContent" class="text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                        <!-- Populated by JS -->
                    </div>
                </div>

                {{-- Innings Selector --}}
                <div id="inningsSelector" class="border-t border-gray-100 dark:border-gray-700 px-5 py-3 hidden">
                    <label class="flex items-center text-xs font-semibold text-purple-700 dark:text-purple-300 mb-1.5">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Innings View
                    </label>
                    <select id="inningsSelect" onchange="onInningsChange()"
                            class="w-full rounded-lg border border-purple-200 dark:border-purple-700 bg-purple-50 dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-xs font-medium focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="1">1st Innings (Batting First on Left)</option>
                        <option value="2">2nd Innings (Chasing Team on Left)</option>
                    </select>
                </div>

                {{-- Action Buttons --}}
                <div class="border-t border-gray-100 dark:border-gray-700 p-4 flex gap-3">
                    <button type="button" onclick="generatePreview(false)" id="previewBtn"
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 text-sm flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview
                    </button>

                    <button type="button" onclick="generatePreview(true)" id="generateSaveBtn"
                            class="flex-1 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-green-500/25 hover:shadow-green-500/40 text-sm flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        Generate & Save
                    </button>

                    <button type="button" onclick="downloadPoster()" id="downloadBtn" disabled
                            class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-200 dark:disabled:bg-gray-700 disabled:text-gray-400 disabled:cursor-not-allowed disabled:shadow-none text-white font-semibold rounded-xl transition-all shadow-lg shadow-emerald-500/25 text-sm flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Saved Posters Gallery --}}
    <div class="mt-8" x-data="posterGallery()" x-cloak>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Generated Posters
                <span class="ml-2 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 px-2 py-0.5 rounded-full" id="posterCount">{{ $savedPosters->count() }}</span>
            </h2>
            {{-- Filter tabs --}}
            <div class="flex items-center gap-1.5">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'" class="px-2.5 py-1 rounded-lg text-xs font-medium transition">All</button>
                <button @click="filter = 'match_poster'" :class="filter === 'match_poster' ? 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-700' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'" class="px-2.5 py-1 rounded-lg text-xs font-medium transition">Match</button>
                <button @click="filter = 'match_summary'" :class="filter === 'match_summary' ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'" class="px-2.5 py-1 rounded-lg text-xs font-medium transition">Summary</button>
                <button @click="filter = 'award_poster'" :class="filter === 'award_poster' ? 'bg-red-100 dark:bg-red-900/40 text-red-700' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'" class="px-2.5 py-1 rounded-lg text-xs font-medium transition">Award</button>
                <button @click="filter = 'other'" :class="filter === 'other' ? 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'" class="px-2.5 py-1 rounded-lg text-xs font-medium transition">Other</button>
            </div>
        </div>

        <div id="postersGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @forelse($savedPosters as $poster)
                <div class="poster-card group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-all"
                     data-poster-id="{{ $poster->id }}"
                     data-poster-type="{{ $poster->type }}"
                     x-show="filter === 'all' || filter === '{{ $poster->type }}' || (filter === 'other' && !['match_poster','match_summary','award_poster'].includes('{{ $poster->type }}'))">
                    {{-- Thumbnail --}}
                    <div class="relative cursor-pointer" @click="viewPoster('{{ $poster->image_url }}', '{{ addslashes($poster->label) }}')">
                        <img src="{{ $poster->image_url }}" alt="{{ $poster->label }}" class="w-full h-40 object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <span class="bg-white/90 backdrop-blur rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 shadow">
                                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View
                            </span>
                        </div>
                        {{-- Type badge --}}
                        @php
                            $typeColors = [
                                'match_poster' => 'bg-cyan-500',
                                'match_summary' => 'bg-yellow-500',
                                'award_poster' => 'bg-red-500',
                                'welcome_card' => 'bg-green-500',
                                'point_table' => 'bg-blue-500',
                                'fixtures_poster' => 'bg-teal-500',
                                'flyer' => 'bg-orange-500',
                                'champions_poster' => 'bg-amber-500',
                            ];
                        @endphp
                        <span class="absolute top-2 left-2 {{ $typeColors[$poster->type] ?? 'bg-gray-500' }} text-white text-[9px] font-bold px-1.5 py-0.5 rounded-md uppercase tracking-wider">{{ str_replace('_', ' ', Str::limit($poster->type, 10, '')) }}</span>
                    </div>

                    {{-- Info & Actions --}}
                    <div class="px-3 py-2">
                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate" title="{{ $poster->label }}">{{ $poster->label ?: 'Poster' }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $poster->created_at->format('M d, h:i A') }}</p>
                        <div class="flex items-center gap-1.5 mt-2">
                            <a href="{{ $poster->image_url }}" download="poster-{{ $poster->id }}.png" class="flex-1 text-center px-2 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 rounded-lg text-[10px] font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition">
                                <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Download
                            </a>
                            <button @click="deletePoster({{ $poster->id }}, $el)" class="px-2 py-1 bg-red-50 dark:bg-red-900/30 text-red-500 rounded-lg text-[10px] font-semibold hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div id="emptyPostersMsg" class="col-span-full text-center py-10">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <p class="text-sm text-gray-500 font-medium">No posters generated yet</p>
                    <p class="text-xs text-gray-400 mt-1">Generated posters will appear here</p>
                </div>
            @endforelse
        </div>

        {{-- Lightbox Modal --}}
        <div x-show="showLightbox" x-transition.opacity class="fixed inset-0 z-[9999] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showLightbox = false" @keydown.escape.window="showLightbox = false">
            <div class="relative max-w-4xl w-full">
                <button @click="showLightbox = false" class="absolute -top-10 right-0 text-white/70 hover:text-white transition">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <img :src="lightboxSrc" :alt="lightboxLabel" class="max-w-full max-h-[85vh] mx-auto rounded-xl shadow-2xl">
                <div class="text-center mt-3">
                    <p class="text-white/90 font-semibold text-sm" x-text="lightboxLabel"></p>
                    <a :href="lightboxSrc" :download="'poster-' + Date.now() + '.png'" class="inline-flex items-center gap-1.5 mt-2 px-4 py-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg backdrop-blur transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
// Cropped image blob for award poster
let awardCroppedBlob = null;

function awardImageCropper() {
    return {
        showCropModal: false,
        cropImageSrc: '',
        cropper: null,
        croppedPreview: null,
        isDragging: false,
        isTransparent: false,
        skipBgRemoval: false,
        removeBg: true,
        activeRatio: '0.75',
        ratios: [
            { label: '3:4', value: '0.75' },
            { label: '4:3', value: '1.333' },
            { label: '1:1', value: '1' },
            { label: 'Free', value: 'free' },
        ],

        openFilePicker() {
            this.$refs.fileInput.click();
        },

        onFileSelected(e) {
            const file = e.target.files[0];
            if (file) this.loadImage(file);
        },

        handleDrop(e) {
            this.isDragging = false;
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) this.loadImage(file);
        },

        loadImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.cropImageSrc = e.target.result;
                this.detectTransparency(e.target.result);
                this.showCropModal = true;
                this.$nextTick(() => this.initCropper());
            };
            reader.readAsDataURL(file);
        },

        detectTransparency(dataUrl) {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                const corners = [
                    ctx.getImageData(0, 0, 1, 1).data,
                    ctx.getImageData(img.width - 1, 0, 1, 1).data,
                    ctx.getImageData(0, img.height - 1, 1, 1).data,
                    ctx.getImageData(img.width - 1, img.height - 1, 1, 1).data,
                ];
                this.isTransparent = corners.some(c => c[3] < 10);
                this.removeBg = !this.isTransparent;
                this.skipBgRemoval = this.isTransparent;
            };
            img.src = dataUrl;
        },

        initCropper() {
            if (this.cropper) this.cropper.destroy();
            const imgEl = this.$refs.cropImage;
            if (!imgEl) return;
            this.cropper = new Cropper(imgEl, {
                viewMode: 1,
                dragMode: 'move',
                aspectRatio: this.activeRatio === 'free' ? NaN : parseFloat(this.activeRatio),
                autoCropArea: 0.9,
                responsive: true,
                background: true,
            });
        },

        setAspectRatio(ratio) {
            this.activeRatio = ratio;
            if (this.cropper) {
                this.cropper.setAspectRatio(ratio === 'free' ? NaN : parseFloat(ratio));
            }
        },

        applyCrop() {
            if (!this.cropper) return;
            const canvas = this.cropper.getCroppedCanvas({ maxWidth: 800, maxHeight: 1000, imageSmoothingQuality: 'high' });
            this.croppedPreview = canvas.toDataURL('image/png');
            canvas.toBlob((blob) => {
                awardCroppedBlob = blob;
                // Create a new File from blob and set it on the hidden input
                const dt = new DataTransfer();
                dt.items.add(new File([blob], 'cropped-player.png', { type: 'image/png' }));
                document.getElementById('awardPlayerImageUpload').files = dt.files;
                // Update existing preview elements
                const thumb = document.getElementById('awardPlayerImageThumb');
                const label = document.getElementById('awardPlayerImageLabel');
                const preview = document.getElementById('awardPlayerImagePreview');
                if (thumb) thumb.src = this.croppedPreview;
                if (label) { label.textContent = 'Custom crop'; label.className = 'text-xs text-purple-600'; }
                if (preview) preview.classList.remove('hidden');
            }, 'image/png');
            this.closeCropModal();
        },

        removeCropped() {
            this.croppedPreview = null;
            awardCroppedBlob = null;
            this.isTransparent = false;
            this.skipBgRemoval = false;
            document.getElementById('awardPlayerImageUpload').value = '';
            this.$refs.fileInput.value = '';
        },

        closeCropModal() {
            this.showCropModal = false;
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
        },
    };
}

// Poster gallery component
function posterGallery() {
    return {
        filter: 'all',
        showLightbox: false,
        lightboxSrc: '',
        lightboxLabel: '',

        viewPoster(url, label) {
            this.lightboxSrc = url;
            this.lightboxLabel = label;
            this.showLightbox = true;
        },

        deletePoster(id, el) {
            if (!confirm('Delete this poster?')) return;
            _deletePosterById(id, el);
        },
    };
}

function _deletePosterById(id, el) {
    fetch(`{{ url('admin/tournaments/' . $tournament->id) }}/generated-posters/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            const card = el.closest('.poster-card');
            if (card) {
                card.style.transition = 'all 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => card.remove(), 300);
            }
            const countEl = document.getElementById('posterCount');
            if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
        }
    })
    .catch(err => console.error('Delete failed:', err));
}

function _viewPoster(url, label) {
    const galleryEl = document.querySelector('[x-data]');
    // Find the posterGallery Alpine instance
    document.querySelectorAll('[x-data]').forEach(el => {
        if (el._x_dataStack && el._x_dataStack[0] && typeof el._x_dataStack[0].showLightbox !== 'undefined') {
            el._x_dataStack[0].lightboxSrc = url;
            el._x_dataStack[0].lightboxLabel = label;
            el._x_dataStack[0].showLightbox = true;
        }
    });
}

// Add newly generated poster to gallery
function addPosterToGallery(data) {
    const grid = document.getElementById('postersGrid');
    if (!grid) return;

    // Remove empty message if present
    const emptyMsg = document.getElementById('emptyPostersMsg');
    if (emptyMsg) emptyMsg.remove();

    const typeColors = {
        'match_poster': 'bg-cyan-500', 'match_summary': 'bg-yellow-500', 'award_poster': 'bg-red-500',
        'welcome_card': 'bg-green-500', 'point_table': 'bg-blue-500', 'fixtures_poster': 'bg-teal-500',
        'flyer': 'bg-orange-500', 'champions_poster': 'bg-amber-500',
    };
    const badgeColor = typeColors[data.poster_type] || 'bg-gray-500';
    const typeLabel = (data.poster_type || '').replace(/_/g, ' ').substring(0, 10).toUpperCase();

    const card = document.createElement('div');
    card.className = 'poster-card group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-all';
    card.dataset.posterId = data.poster_id;
    card.dataset.posterType = data.poster_type;
    card.style.opacity = '0';
    card.style.transform = 'scale(0.95)';
    const safeLabel = (data.poster_label || 'Poster').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    card.innerHTML = `
        <div class="relative cursor-pointer" onclick="_viewPoster('${data.download_url}', '${safeLabel}')">
            <img src="${data.download_url}" alt="${data.poster_label || 'Poster'}" class="w-full h-40 object-cover" loading="lazy">
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                <span class="bg-white/90 backdrop-blur rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700 shadow">
                    <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    View
                </span>
            </div>
            <span class="absolute top-2 left-2 ${badgeColor} text-white text-[9px] font-bold px-1.5 py-0.5 rounded-md uppercase tracking-wider">${typeLabel}</span>
        </div>
        <div class="px-3 py-2">
            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate">${data.poster_label || 'Poster'}</p>
            <p class="text-[10px] text-gray-400 mt-0.5">${data.poster_created || 'Just now'}</p>
            <div class="flex items-center gap-1.5 mt-2">
                <a href="${data.download_url}" download="{{ config('settings.app_name') ?: config('app.name') }}-${data.poster_id}.png" class="flex-1 text-center px-2 py-1 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 rounded-lg text-[10px] font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition">
                    <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download
                </a>
                <button onclick="_deletePosterById(${data.poster_id}, this)" class="px-2 py-1 bg-red-50 dark:bg-red-900/30 text-red-500 rounded-lg text-[10px] font-semibold hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>
    `;
    grid.prepend(card);
    // Animate in
    requestAnimationFrame(() => {
        card.style.transition = 'all 0.3s';
        card.style.opacity = '1';
        card.style.transform = 'scale(1)';
    });
    // Update count
    const countEl = document.getElementById('posterCount');
    if (countEl) countEl.textContent = parseInt(countEl.textContent || '0') + 1;
}

let currentType = '{{ request('type', 'match_poster') }}';
let generatedImageUrl = null;
let savedDownloadUrl = null;

function updateType(type) {
    currentType = type;

    // Update URL so refresh stays on same type
    const url = new URL(window.location);
    url.searchParams.set('type', type);
    window.history.replaceState({}, '', url);

    // Show/hide data selection sections
    document.getElementById('matchSelection').classList.toggle('hidden', !['match_poster', 'match_summary'].includes(type));
    document.getElementById('playerSelection').classList.toggle('hidden', type !== 'welcome_card');
    document.getElementById('awardSelection').classList.toggle('hidden', type !== 'award_poster');
    document.getElementById('groupSelection').classList.toggle('hidden', type !== 'point_table');
    document.getElementById('fixturesSelection').classList.toggle('hidden', type !== 'fixtures_poster');

    // Show/hide innings selector
    showInningsSelector();

    // Reset innings to 1st
    document.getElementById('inningsSelect').value = '1';

    // Reset preview
    resetPreview();

    // Load templates for this type
    loadTemplates(type);
}

function loadTemplates(type) {
    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/templates') }}?type=${type}&ajax=1`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('templatesList');
            if (data.templates && data.templates.length > 0) {
                const editBaseUrl = `{{ url('admin/tournaments/' . $tournament->id . '/templates') }}`;
                const isSuperadmin = {{ auth()->user()->hasRole('Superadmin') ? 'true' : 'false' }};
                container.innerHTML = data.templates.map((t, i) => `
                    <div class="relative group">
                        <label class="cursor-pointer block">
                            <input type="radio" name="template_id" value="${t.id}" class="hidden peer" ${i === 0 ? 'checked' : ''}>
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all peer-checked:border-purple-500 peer-checked:ring-2 peer-checked:ring-purple-500/20 hover:border-purple-300 hover:shadow-md">
                                ${t.background_image_url ?
                                    `<img src="${t.background_image_url}" alt="${t.name}" class="w-full h-36 object-cover">` :
                                    `<div class="w-full h-36 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center"><svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>`
                                }
                                <div class="px-3 py-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 truncate">${t.name}</p>
                                    ${t.is_default ? '<span class="text-[10px] bg-purple-100 dark:bg-purple-900/50 text-purple-600 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0 ml-1">Default</span>' : ''}
                                </div>
                            </div>
                        </label>
                        ${isSuperadmin ? `<a href="${editBaseUrl}/${t.id}/edit" target="_blank" class="absolute top-2 right-2 p-1.5 rounded-lg bg-black/50 backdrop-blur text-white opacity-0 group-hover:opacity-100 hover:bg-black/70 shadow-sm transition-all z-10" title="Edit Template">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>` : ''}
                    </div>
                `).join('');
                // Show first template's background as preview
                setTimeout(() => showTemplatePreview(), 50);
            } else {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/></svg>
                        <p class="text-sm">No templates found for this type.</p>
                        ${isSuperadmin ? `<a href="{{ route('admin.tournaments.templates.create', $tournament) }}?type=${type}"
                           class="text-purple-600 hover:underline mt-2 inline-block text-sm">Create a template</a>` : ''}
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error loading templates:', err));
}

function resetPreview() {
    document.getElementById('previewPlaceholder').classList.remove('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('previewLoading').classList.add('hidden');
    document.getElementById('dataSummary').classList.add('hidden');
    document.getElementById('downloadBtn').disabled = true;
    document.getElementById('previewStatus').textContent = 'Ready';
    document.getElementById('previewStatus').className = 'text-xs text-gray-400 font-medium';
    generatedImageUrl = null;
    savedDownloadUrl = null;
}

// Show selected template's background image as preview
function showTemplatePreview() {
    const templateInput = document.querySelector('input[name="template_id"]:checked');
    if (!templateInput) return;

    const previewImage = document.getElementById('previewImage');
    const previewPlaceholder = document.getElementById('previewPlaceholder');

    // Find the template's background image from the thumbnail
    const label = templateInput.closest('label') || templateInput.parentElement;
    const thumb = label?.querySelector('img');

    if (thumb && thumb.src) {
        previewImage.src = thumb.src;
        previewImage.classList.remove('hidden');
        previewPlaceholder.classList.add('hidden');
    } else {
        previewImage.classList.add('hidden');
        previewPlaceholder.classList.remove('hidden');
    }
}

// Listen for template selection changes
document.addEventListener('change', function(e) {
    if (e.target.name === 'template_id') {
        showTemplatePreview();
    }
});

function getSelectedData() {
    const data = { type: currentType };

    if (['match_poster', 'match_summary'].includes(currentType)) {
        const matchSelect = document.getElementById('matchSelect');
        const selected = matchSelect.options[matchSelect.selectedIndex];
        if (selected && selected.value) {
            data.match_id = selected.value;
            data.team_a_name = selected.dataset.teamA;
            data.team_b_name = selected.dataset.teamB;
            data.team_a_short_name = selected.dataset.teamAShort;
            data.team_b_short_name = selected.dataset.teamBShort;
            data.team_a_logo = selected.dataset.teamALogo;
            data.team_b_logo = selected.dataset.teamBLogo;
            data.team_a_captain_image = selected.dataset.teamACaptainImage;
            data.team_b_captain_image = selected.dataset.teamBCaptainImage;
            data.team_a_captain_name = selected.dataset.teamACaptainName;
            data.team_b_captain_name = selected.dataset.teamBCaptainName;
            data.match_date = selected.dataset.date;
            data.match_time = selected.dataset.time;
            data.venue = selected.dataset.venue;
            data.ground_name = selected.dataset.venue;
            data.match_stage = selected.dataset.stageDisplay || selected.dataset.stage;
            data.match_number = selected.dataset.matchNumber;
            if (currentType === 'match_summary') {
                // Read from editable input fields (pre-filled from data attrs)
                data.team_a_score = document.getElementById('summaryTeamAScore')?.value?.trim() || selected.dataset.teamAScore;
                data.team_b_score = document.getElementById('summaryTeamBScore')?.value?.trim() || selected.dataset.teamBScore;
                data.result_summary = document.getElementById('summaryResultSummary')?.value?.trim() || selected.dataset.resultSummary;
                data.winner_name = document.getElementById('summaryWinnerName')?.value?.trim() || selected.dataset.winner;
                data.winner_logo = selected.dataset.winnerLogo;

                // Awards
                const motm = document.getElementById('summaryMotmName')?.value?.trim();
                const bestBat = document.getElementById('summaryBestBatsman')?.value?.trim();
                const bestBowl = document.getElementById('summaryBestBowler')?.value?.trim();
                if (motm) data.man_of_the_match_name = motm;
                if (bestBat) data.best_batsman_name = bestBat;
                if (bestBowl) data.best_bowler_name = bestBowl;

                // Batting figures
                const batRuns = document.getElementById('summaryBatRuns')?.value?.trim();
                const batBalls = document.getElementById('summaryBatBalls')?.value?.trim();
                const batFours = document.getElementById('summaryBatFours')?.value?.trim();
                const batSixes = document.getElementById('summaryBatSixes')?.value?.trim();
                const batNotOut = document.getElementById('summaryBatNotOut')?.checked;
                if (batRuns) {
                    let bf = batRuns + (batNotOut ? '*' : '');
                    if (batBalls) bf += ` (${batBalls})`;
                    if (batFours) bf += ` ${batFours}x4`;
                    if (batSixes) bf += ` ${batSixes}x6`;
                    data.batting_figures = bf;
                }

                // Bowling figures
                const bowlOvers = document.getElementById('summaryBowlOvers')?.value?.trim();
                const bowlMaidens = document.getElementById('summaryBowlMaidens')?.value?.trim();
                const bowlRuns = document.getElementById('summaryBowlRuns')?.value?.trim();
                const bowlWickets = document.getElementById('summaryBowlWickets')?.value?.trim();
                if (bowlOvers) {
                    data.bowling_figures = `${bowlOvers} - ${bowlMaidens || '0'} - ${bowlRuns || '0'} - ${bowlWickets || '0'}`;
                }
            }
        }
    } else if (currentType === 'welcome_card') {
        const playerSelect = document.getElementById('playerSelect');
        const selected = playerSelect.options[playerSelect.selectedIndex];
        if (selected && selected.value) {
            data.player_id = selected.value;
            data.player_name = selected.dataset.name;
            data.jersey_number = selected.dataset.jersey;
            data.team_name = selected.dataset.team;
            data.team_logo = selected.dataset.teamLogo;
            data.player_image = selected.dataset.photo;
            data.player_type = selected.dataset.type;
            data.batting_style = selected.dataset.batting;
            data.bowling_style = selected.dataset.bowling;
        }
    } else if (currentType === 'award_poster') {
        // Get match details
        const matchSelect = document.getElementById('awardMatchSelect');
        const matchOpt = matchSelect.options[matchSelect.selectedIndex];
        if (matchOpt && matchOpt.value) {
            data.match_id = matchOpt.value;
            data.match_details = (matchOpt.dataset.teamA || 'TBD') + ' vs ' + (matchOpt.dataset.teamB || 'TBD');
            data.team_a_name = matchOpt.dataset.teamA || '';
            data.team_b_name = matchOpt.dataset.teamB || '';
            data.team_a_logo = matchOpt.dataset.teamALogo || '';
            data.team_b_logo = matchOpt.dataset.teamBLogo || '';
            data.match_date = matchOpt.dataset.date || '';
            data.venue = matchOpt.dataset.venue || '';
            data.result_summary = matchOpt.dataset.result || '';
        }
        // Get player data from select
        const awardSelect = document.getElementById('awardPlayerSelect');
        const selected = awardSelect.options[awardSelect.selectedIndex];
        if (selected && selected.value) {
            const playerData = JSON.parse(selected.dataset.player || '{}');
            Object.assign(data, playerData);
        }

        // Override award name if custom value entered
        const customAwardName = document.getElementById('awardNameOverride')?.value?.trim();
        if (customAwardName) {
            data.award_name = customAwardName;
        }

        // Override player name if custom value entered
        const customPlayerName = document.getElementById('awardPlayerName')?.value?.trim();
        if (customPlayerName) {
            data.player_name = customPlayerName;
        }

        // Flag if custom image was uploaded (handled in generatePreview via FormData)
        const customImageFile = document.getElementById('awardPlayerImageUpload')?.files?.[0];
        if (customImageFile) {
            data._hasCustomPlayerImage = true;
            // Send skip_bg_removal flag from cropper's transparency detection
            const cropperEl = document.getElementById('awardPlayerOverride');
            if (cropperEl && cropperEl._x_dataStack && cropperEl._x_dataStack[0]) {
                data.skip_bg_removal = cropperEl._x_dataStack[0].skipBgRemoval ? '1' : '0';
            }
        }

        // Manual stat fields
        const teamAScore = document.getElementById('awardTeamAScore')?.value?.trim();
        const teamBScore = document.getElementById('awardTeamBScore')?.value?.trim();
        const resultSummary = document.getElementById('awardResultSummary')?.value?.trim();
        if (teamAScore) data.team_a_score = teamAScore;
        if (teamBScore) data.team_b_score = teamBScore;
        if (resultSummary) data.result_summary = resultSummary;

        // Build batting_figures: "59 (36) 9x4 1x6"
        const batRuns = document.getElementById('batRuns')?.value?.trim();
        const batBalls = document.getElementById('batBalls')?.value?.trim();
        const batFours = document.getElementById('batFours')?.value?.trim();
        const batSixes = document.getElementById('batSixes')?.value?.trim();
        const batNotOut = document.getElementById('batNotOut')?.checked;
        if (batRuns) {
            let bf = batRuns + (batNotOut ? '*' : '');
            if (batBalls) bf += ` (${batBalls})`;
            if (batFours) bf += ` ${batFours}x4`;
            if (batSixes) bf += ` ${batSixes}x6`;
            data.batting_figures = bf;
            // Individual stat placeholders
            data.batting_runs = batRuns + (batNotOut ? '*' : '');
            data.batting_balls = batBalls || '';
            data.batting_fours = batFours || '';
            data.batting_sixes = batSixes || '';
        }

        // Build bowling_figures: "4 - 0 - 25 - 2"
        const bowlOvers = document.getElementById('bowlOvers')?.value?.trim();
        const bowlMaidens = document.getElementById('bowlMaidens')?.value?.trim();
        const bowlRuns = document.getElementById('bowlRuns')?.value?.trim();
        const bowlWickets = document.getElementById('bowlWickets')?.value?.trim();
        if (bowlOvers) {
            data.bowling_figures = `${bowlOvers} - ${bowlMaidens || '0'} - ${bowlRuns || '0'} - ${bowlWickets || '0'}`;
            // Individual stat placeholders
            data.bowling_overs = bowlOvers;
            data.bowling_runs = bowlRuns || '0';
            data.bowling_maidens = bowlMaidens || '0';
            data.bowling_wickets = bowlWickets || '0';
        }

        // Combined score+overs format
        if (teamAScore) {
            data.team_a_score_overs = teamAScore;
        }
        if (teamBScore) {
            data.team_b_score_overs = teamBScore;
        }
    } else if (currentType === 'point_table') {
        const groupSelect = document.getElementById('groupSelect');
        const selected = groupSelect.options[groupSelect.selectedIndex];
        if (selected && selected.value) {
            data.group_id = selected.value;
            data.group_name = selected.dataset.name;
        }
    } else if (currentType === 'fixtures_poster') {
        data.fixture_count = document.getElementById('fixtureCountValue')?.value || '5';
        data.fixture_layout = document.getElementById('fixtureLayoutValue')?.value || 'row';
    }

    // Get selected template
    const templateInput = document.querySelector('input[name="template_id"]:checked');
    if (templateInput) {
        data.template_id = templateInput.value;
    }

    // Include innings selection
    const inningsSelect = document.getElementById('inningsSelect');
    if (inningsSelect && !document.getElementById('inningsSelector').classList.contains('hidden')) {
        data.innings = inningsSelect.value;
    }

    return data;
}

// Field visibility toggles
let hiddenFields = [];

const fieldLabels = {
    'tournament_name': 'Tournament Name', 'tournament_logo': 'Tournament Logo',
    'team_a_name': 'Team A Name', 'team_a_short_name': 'Team A Short', 'team_a_logo': 'Team A Logo',
    'team_a_score': 'Team A Score (Full)', 'team_a_score_wickets': 'Team A Score (R/W)',
    'team_a_runs': 'Team A Runs', 'team_a_wickets': 'Team A Wickets', 'team_a_overs': 'Team A Overs',
    'team_b_name': 'Team B Name', 'team_b_short_name': 'Team B Short', 'team_b_logo': 'Team B Logo',
    'team_b_score': 'Team B Score (Full)', 'team_b_score_wickets': 'Team B Score (R/W)',
    'team_b_runs': 'Team B Runs', 'team_b_wickets': 'Team B Wickets', 'team_b_overs': 'Team B Overs',
    'result_summary': 'Result Summary', 'winner_name': 'Winner Name', 'winner_logo': 'Winner Logo',
    'win_margin': 'Win Margin', 'toss_result': 'Toss Result',
    'match_date': 'Match Date', 'match_time': 'Match Time', 'venue': 'Venue',
    'match_stage': 'Match Stage', 'match_number': 'Match Number',
    'man_of_the_match_name': 'MOTM Name', 'man_of_the_match_image': 'MOTM Image',
    'best_batsman_name': 'Best Batsman', 'best_bowler_name': 'Best Bowler',
    'player_name': 'Player Name', 'player_image': 'Player Image',
    'award_name': 'Award Name', 'achievement_text': 'Achievement',
    'match_details': 'Match Details', 'batting_figures': 'Batting Figures', 'bowling_figures': 'Bowling Figures',
    'jersey_number': 'Jersey Number', 'team_name': 'Team Name', 'team_logo': 'Team Logo',
};

function buildFieldToggles() {
    const section = document.getElementById('fieldTogglesSection');
    const container = document.getElementById('togglesBody');
    const templateInput = document.querySelector('input[name="template_id"]:checked');

    if (!templateInput) {
        section.classList.add('hidden');
        return;
    }

    // Fetch template layout to get used placeholders
    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/templates') }}/${templateInput.value}/edit?ajax_layout=1`)
        .then(r => r.json())
        .then(result => {
            const usedFields = (result.layout || []).map(el => el.placeholder).filter(Boolean);
            if (usedFields.length === 0) {
                section.classList.add('hidden');
                return;
            }

            hiddenFields = [];
            container.innerHTML = usedFields.map(field => {
                const label = fieldLabels[field] || field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                return `<label class="flex items-center gap-2 py-1 px-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input type="checkbox" checked data-field="${field}" onchange="toggleField('${field}', this.checked)"
                           class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate">${label}</span>
                </label>`;
            }).join('');

            section.classList.remove('hidden');
        })
        .catch(() => {
            section.classList.add('hidden');
        });
}

function toggleField(field, checked) {
    if (checked) {
        hiddenFields = hiddenFields.filter(f => f !== field);
    } else {
        if (!hiddenFields.includes(field)) hiddenFields.push(field);
    }
}

// Build toggles when template changes
document.addEventListener('change', function(e) {
    if (e.target.name === 'template_id') buildFieldToggles();
});

// Build toggles when match/player changes
document.addEventListener('change', function(e) {
    if (['matchSelect', 'awardMatchSelect', 'playerSelect', 'groupSelect'].includes(e.target.id)) {
        buildFieldToggles();
    }
});

function showDataSummary(data) {
    const summary = document.getElementById('dataSummary');
    const content = document.getElementById('summaryContent');

    let html = '';
    if (data.team_a_name && data.team_b_name) {
        html += `<p><strong>${data.team_a_name}</strong> vs <strong>${data.team_b_name}</strong></p>`;
    }
    if (data.match_date) html += `<p>Date: ${data.match_date}${data.match_time ? ' at ' + data.match_time : ''}</p>`;
    if (data.venue) html += `<p>Venue: ${data.venue}</p>`;
    if (data.team_a_captain_name || data.team_b_captain_name) {
        html += `<p class="text-xs text-gray-500 mt-1">Captains: ${data.team_a_captain_name || 'TBD'} vs ${data.team_b_captain_name || 'TBD'}</p>`;
    }
    if (data.team_a_captain_image || data.team_b_captain_image) {
        html += `<div class="flex items-center gap-2 mt-2">`;
        if (data.team_a_captain_image) html += `<img src="${data.team_a_captain_image}" class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm">`;
        if (data.team_a_logo) html += `<img src="${data.team_a_logo}" class="w-6 h-6 object-contain">`;
        html += `<span class="text-xs text-gray-400">vs</span>`;
        if (data.team_b_logo) html += `<img src="${data.team_b_logo}" class="w-6 h-6 object-contain">`;
        if (data.team_b_captain_image) html += `<img src="${data.team_b_captain_image}" class="w-8 h-8 rounded-full object-cover border-2 border-white shadow-sm">`;
        html += `</div>`;
    }
    if (data.player_name) html += `<p>Player: <strong>${data.player_name}</strong></p>`;
    if (data.team_name) html += `<p>Team: ${data.team_name}</p>`;
    if (data.award_name) html += `<p>Award: ${data.award_name}</p>`;
    if (data.group_name) html += `<p>Group: <strong>${data.group_name}</strong></p>`;
    if (data.fixture_count) html += `<p>Fixtures: <strong>${data.fixture_count === '100' ? 'All' : data.fixture_count} upcoming matches</strong> (${data.fixture_layout === 'card' ? 'Card Grid' : 'Row List'})</p>`;

    if (html) {
        content.innerHTML = html;
        summary.classList.remove('hidden');
    }
}

function generatePreview(saveMode = false) {
    const data = getSelectedData();

    if (!data.template_id) {
        alert('Please select a template');
        return;
    }

    if (['match_poster', 'match_summary'].includes(currentType) && !data.match_id) {
        alert('Please select a match');
        return;
    }

    if (currentType === 'welcome_card' && !data.player_id) {
        alert('Please select a player');
        return;
    }

    if (currentType === 'point_table' && !data.group_id) {
        alert('Please select a group');
        return;
    }

    // Add save flag to data
    data.save_poster = saveMode;

    // Determine which button triggered
    const activeBtn = saveMode ? document.getElementById('generateSaveBtn') : document.getElementById('previewBtn');
    const activeLabel = saveMode ? 'Saving...' : 'Generating...';

    // Show loading
    document.getElementById('previewPlaceholder').classList.add('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('previewLoading').classList.remove('hidden');
    document.getElementById('previewStatus').textContent = activeLabel;
    document.getElementById('previewStatus').className = 'text-xs text-purple-500 font-medium animate-pulse';
    document.getElementById('previewBtn').disabled = true;
    document.getElementById('generateSaveBtn').disabled = true;
    activeBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        ${activeLabel}
    `;

    showDataSummary(data);

    // Add hidden fields to data
    if (hiddenFields.length > 0) {
        data.hidden_fields = hiddenFields;
    }

    // Create abort controller with 3 minute timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 180000);

    // Build request body — use FormData if custom image uploaded, else JSON
    const customImageFile = document.getElementById('awardPlayerImageUpload')?.files?.[0];
    let fetchOptions = {};

    if (customImageFile && currentType === 'award_poster') {
        const formData = new FormData();
        for (const [key, value] of Object.entries(data)) {
            if (key !== '_hasCustomPlayerImage' && value !== null && value !== undefined) {
                if (key === 'hidden_fields' && Array.isArray(value)) {
                    value.forEach((f, i) => formData.append(`hidden_fields[${i}]`, f));
                } else {
                    formData.append(key, value);
                }
            }
        }
        formData.append('player_image_file', customImageFile);
        fetchOptions = {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData,
            signal: controller.signal
        };
    } else {
        // Remove internal flag before sending
        delete data._hasCustomPlayerImage;
        fetchOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
            signal: controller.signal
        };
    }

    // Call API to generate preview
    fetch(`{{ route('admin.tournaments.templates.generate-preview', $tournament) }}`, fetchOptions)
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Server error (${response.status}): ${text.substring(0, 200)}`);
            });
        }
        return response.json();
    })
    .then(result => {
        resetPreviewBtn();
        document.getElementById('previewLoading').classList.add('hidden');

        if (result.success && result.image) {
            document.getElementById('previewImage').src = result.image;
            document.getElementById('previewImage').classList.remove('hidden');
            document.getElementById('downloadBtn').disabled = false;
            const statusText = result.poster_id ? 'Saved' : 'Preview';
            document.getElementById('previewStatus').textContent = statusText;
            document.getElementById('previewStatus').className = 'text-xs text-emerald-500 font-medium';
            generatedImageUrl = result.image;
            savedDownloadUrl = result.download_url || null;
            // Add to saved posters gallery
            if (result.poster_id && result.download_url) {
                addPosterToGallery(result);
            }
        } else {
            document.getElementById('previewPlaceholder').classList.remove('hidden');
            document.getElementById('previewStatus').textContent = 'Error';
            document.getElementById('previewStatus').className = 'text-xs text-red-500 font-medium';
            alert(result.error || 'Failed to generate preview');
        }
    })
    .catch(err => {
        clearTimeout(timeoutId);
        resetPreviewBtn();
        document.getElementById('previewLoading').classList.add('hidden');
        document.getElementById('previewPlaceholder').classList.remove('hidden');
        document.getElementById('previewStatus').textContent = 'Error';
        document.getElementById('previewStatus').className = 'text-xs text-red-500 font-medium';
        console.error('Generation Error:', err);

        let errorMsg = 'Failed to generate preview: ';
        if (err.name === 'AbortError') {
            errorMsg += 'Request timed out. Please try again.';
        } else if (err.message.includes('Failed to fetch')) {
            errorMsg += 'Network error or server timeout. Please check your connection and try again.';
        } else {
            errorMsg += err.message;
        }
        alert(errorMsg);
    });
}

function resetPreviewBtn() {
    const previewBtn = document.getElementById('previewBtn');
    previewBtn.disabled = false;
    previewBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        Preview
    `;
    const saveBtn = document.getElementById('generateSaveBtn');
    saveBtn.disabled = false;
    saveBtn.innerHTML = `
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
        </svg>
        Generate & Save
    `;
}

function filterPlayersByTeam() {
    const teamId = document.getElementById('teamFilter').value;
    const playerSelect = document.getElementById('playerSelect');
    const options = playerSelect.querySelectorAll('option[value]');

    playerSelect.value = '';
    options.forEach(opt => {
        if (!teamId || opt.dataset.teamId === teamId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });

    // Also update the custom Alpine dropdown
    const dropdownEl = document.querySelector('[x-data*="playerDropdown"]');
    if (dropdownEl && dropdownEl._x_dataStack) {
        const dd = dropdownEl._x_dataStack[0];
        dd.teamFilter = teamId;
        dd.selectedPlayer = null;
        dd.filterPlayers();
    }
}

function playerDropdown() {
    @php
        $playerDropdownData = $players->map(function($p) use ($tournamentTeamMap, $teams) {
            $effTeamId = $tournamentTeamMap[$p->id] ?? $p->actual_team_id;
            $effTeam = $effTeamId ? $teams->firstWhere('id', $effTeamId) : $p->actualTeam;
            return [
                'id' => (string) $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'jersey' => $p->jersey_number,
                'team' => $effTeam?->name ?? $p->actualTeam?->name,
                'teamId' => (string) $effTeamId,
                'teamLogo' => $effTeam?->team_logo_url ?? $p->actualTeam?->team_logo_url ?? '',
                'photo' => $p->image_path ? asset('storage/' . $p->image_path) : '',
                'type' => $p->playerType?->type ?? '',
                'batting' => $p->battingProfile?->style ?? '',
                'bowling' => $p->bowlingProfile?->style ?? '',
            ];
        })->values();
    @endphp
    const allPlayers = @json($playerDropdownData);

    return {
        open: false,
        search: '',
        teamFilter: '',
        selectedPlayer: null,
        allPlayers: allPlayers,
        filteredPlayers: allPlayers,

        filterPlayers() {
            const q = this.search.toLowerCase();
            this.filteredPlayers = this.allPlayers.filter(p => {
                const matchesTeam = !this.teamFilter || p.teamId === this.teamFilter;
                const matchesSearch = !q || p.name.toLowerCase().includes(q) || (p.team && p.team.toLowerCase().includes(q)) || (p.email && p.email.toLowerCase().includes(q));
                return matchesTeam && matchesSearch;
            });
        },

        selectPlayer(player) {
            this.selectedPlayer = player;
            this.open = false;
            this.search = '';
            this.filterPlayers();

            // Sync with native hidden select
            const sel = document.getElementById('playerSelect');
            sel.value = player.id;
            sel.dispatchEvent(new Event('change'));
        }
    };
}

function downloadPoster() {
    if (!generatedImageUrl && !savedDownloadUrl) return;

    const innings = document.getElementById('inningsSelect')?.value || '1';
    const filename = `{{ config('settings.app_name') ?: config('app.name') }}-${currentType}-innings${innings}-${Date.now()}.png`;

    // Prefer saved server URL for faster download
    if (savedDownloadUrl) {
        fetch(savedDownloadUrl)
            .then(r => r.blob())
            .then(blob => {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            })
            .catch(() => {
                // Fallback to base64
                downloadViaBase64(filename);
            });
    } else {
        downloadViaBase64(filename);
    }
}

function downloadViaBase64(filename) {
    if (!generatedImageUrl) return;
    const link = document.createElement('a');
    link.href = generatedImageUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function onInningsChange() {
    // Reset preview when innings changes so user regenerates
    resetPreview();
}

function showInningsSelector() {
    const selector = document.getElementById('inningsSelector');
    // Show for match-based poster types
    if (['match_poster', 'match_summary', 'award_poster'].includes(currentType)) {
        selector.classList.remove('hidden');
    } else {
        selector.classList.add('hidden');
    }
}

function loadMatchAwards(matchId) {
    if (!matchId) {
        document.getElementById('awardPlayerSection').classList.add('hidden');
        document.getElementById('awardStatsSection').classList.add('hidden');
        document.getElementById('awardMatchWarning').classList.add('hidden');
        document.getElementById('awardPlayerOverride').classList.add('hidden');
        return;
    }

    // Show/hide warning based on match status
    const matchOpt = document.getElementById('awardMatchSelect').options[document.getElementById('awardMatchSelect').selectedIndex];
    const matchStatus = matchOpt?.dataset?.status || '';
    const warningEl = document.getElementById('awardMatchWarning');
    if (matchStatus !== 'completed') {
        warningEl.classList.remove('hidden');
    } else {
        warningEl.classList.add('hidden');
    }

    // Always show stats section on match select so user can fill manually
    document.getElementById('awardStatsSection').classList.remove('hidden');

    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/matches') }}/${matchId}/awards`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('awardPlayerSelect');
            select.innerHTML = '<option value="">-- Select a player --</option>';
            document.getElementById('awardPlayerSearch').value = '';

            // Store match-level data on the match select option
            if (data.match) {
                if (matchOpt) {
                    matchOpt.dataset.teamALogo = data.match.team_a_logo || '';
                    matchOpt.dataset.teamBLogo = data.match.team_b_logo || '';
                }
                const teamA = matchOpt?.dataset?.teamA || 'Team A';
                const teamB = matchOpt?.dataset?.teamB || 'Team B';
                document.getElementById('teamAScoreLabel').textContent = teamA + ' Score';
                document.getElementById('teamBScoreLabel').textContent = teamB + ' Score';

                // Auto-populate scores
                document.getElementById('awardTeamAScore').value = data.match.team_a_score || '';
                document.getElementById('awardTeamBScore').value = data.match.team_b_score || '';
                document.getElementById('awardResultSummary').value = data.match.result_summary || '';
            }

            // Build award lookup by player_id
            const awardMap = {};
            if (data.awards) {
                data.awards.forEach(a => { awardMap[a.player_id] = a; });
            }

            // Helper to add player options to a group
            function addPlayerGroup(players, teamName) {
                if (!players || players.length === 0) return;
                const group = document.createElement('optgroup');
                group.label = teamName;
                players.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = 'player_' + p.id;
                    const award = awardMap[p.id];
                    let label = p.name;
                    if (award) label += ` ★ ${award.award_name}`;
                    if (p.image) label += ' 📷';
                    opt.textContent = label;
                    opt.dataset.player = JSON.stringify({
                        player_name: p.name,
                        player_image: p.image,
                        team_name: p.team_name,
                        team_logo: p.team_logo,
                        award_name: award ? award.award_name : '',
                        match_id: matchId
                    });
                    group.appendChild(opt);
                });
                select.appendChild(group);
            }

            const teamAName = matchOpt?.dataset?.teamA || 'Team A';
            const teamBName = matchOpt?.dataset?.teamB || 'Team B';
            addPlayerGroup(data.players?.team_a || [], teamAName);
            addPlayerGroup(data.players?.team_b || [], teamBName);

            document.getElementById('awardPlayerSection').classList.remove('hidden');
        })
        .catch(err => console.error('Error loading awards:', err));
}

// Event listeners
document.getElementById('matchSelect')?.addEventListener('change', function() {
    const statsSection = document.getElementById('matchSummaryStats');
    if (this.value && currentType === 'match_summary') {
        const selected = this.options[this.selectedIndex];
        const teamA = selected.dataset.teamA || 'Team A';
        const teamB = selected.dataset.teamB || 'Team B';

        // Update labels
        document.getElementById('summaryTeamALabel').textContent = teamA + ' Score';
        document.getElementById('summaryTeamBLabel').textContent = teamB + ' Score';

        // Auto-fill scores from data attributes
        document.getElementById('summaryTeamAScore').value = selected.dataset.teamAScore || '';
        document.getElementById('summaryTeamBScore').value = selected.dataset.teamBScore || '';
        document.getElementById('summaryResultSummary').value = selected.dataset.resultSummary || '';
        document.getElementById('summaryWinnerName').value = selected.dataset.winner || '';

        // Auto-fill award fields from match data (or clear if not available)
        document.getElementById('summaryMotmName').value = selected.dataset.motmName || '';
        document.getElementById('summaryBestBatsman').value = selected.dataset.bestBatsman || '';
        document.getElementById('summaryBestBowler').value = selected.dataset.bestBowler || '';
        document.getElementById('summaryBatRuns').value = '';
        document.getElementById('summaryBatBalls').value = '';
        document.getElementById('summaryBatFours').value = '';
        document.getElementById('summaryBatSixes').value = '';
        document.getElementById('summaryBowlOvers').value = '';
        document.getElementById('summaryBowlMaidens').value = '';
        document.getElementById('summaryBowlRuns').value = '';
        document.getElementById('summaryBowlWickets').value = '';

        statsSection.classList.remove('hidden');
        document.getElementById('scorecardNote')?.classList.remove('hidden');
    } else {
        statsSection?.classList.add('hidden');
        document.getElementById('scorecardNote')?.classList.add('hidden');
    }
    if (this.value) showDataSummary(getSelectedData());
});
document.getElementById('playerSelect')?.addEventListener('change', function() {
    if (this.value) showDataSummary(getSelectedData());
});
document.getElementById('groupSelect')?.addEventListener('change', function() {
    if (this.value) showDataSummary(getSelectedData());
});
document.getElementById('awardPlayerSelect')?.addEventListener('change', function() {
    const overrideSection = document.getElementById('awardPlayerOverride');
    const nameInput = document.getElementById('awardPlayerName');
    const imagePreview = document.getElementById('awardPlayerImagePreview');
    const imageThumb = document.getElementById('awardPlayerImageThumb');
    const imageLabel = document.getElementById('awardPlayerImageLabel');
    const imageUpload = document.getElementById('awardPlayerImageUpload');
    const awardNameInput = document.getElementById('awardNameOverride');

    if (this.value) {
        const playerData = JSON.parse(this.options[this.selectedIndex].dataset.player || '{}');

        // Pre-fill player name
        nameInput.value = playerData.player_name || '';
        nameInput.placeholder = playerData.player_name || 'Enter player name';

        // Pre-fill award name if player has one
        if (playerData.award_name) {
            awardNameInput.value = playerData.award_name;
        } else {
            awardNameInput.value = '';
        }

        // Show player image preview from DB
        if (playerData.player_image) {
            imageThumb.src = '/storage/' + playerData.player_image;
            imageLabel.textContent = 'From database';
            imageLabel.className = 'text-xs text-green-600';
            imagePreview.classList.remove('hidden');
        } else {
            imageThumb.src = '';
            imageLabel.textContent = 'No image — upload or use default';
            imageLabel.className = 'text-xs text-amber-500';
            imagePreview.classList.remove('hidden');
        }

        // Reset file upload and crop component
        imageUpload.value = '';
        awardCroppedBlob = null;
        // Reset Alpine crop component if exists
        const cropperEl = document.getElementById('awardPlayerOverride');
        if (cropperEl && cropperEl._x_dataStack) {
            const alpineData = cropperEl._x_dataStack[0];
            if (alpineData) {
                alpineData.croppedPreview = null;
                alpineData.isTransparent = false;
                alpineData.skipBgRemoval = false;
            }
        }

        overrideSection.classList.remove('hidden');
        showDataSummary(getSelectedData());
    } else {
        overrideSection.classList.add('hidden');
    }
});

// Update preview label when user uploads a custom image (via crop component)
document.getElementById('awardPlayerImageUpload')?.addEventListener('change', function() {
    const imagePreview = document.getElementById('awardPlayerImagePreview');
    const imageThumb = document.getElementById('awardPlayerImageThumb');
    const imageLabel = document.getElementById('awardPlayerImageLabel');
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imageThumb.src = e.target.result;
            imageLabel.textContent = 'Custom upload';
            imageLabel.className = 'text-xs text-purple-600 ml-2 align-middle';
            imagePreview.classList.remove('hidden');
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Player search filter
document.getElementById('awardPlayerSearch')?.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const select = document.getElementById('awardPlayerSelect');
    const groups = select.querySelectorAll('optgroup');

    groups.forEach(group => {
        let visibleCount = 0;
        Array.from(group.options).forEach(opt => {
            const match = opt.textContent.toLowerCase().includes(query);
            opt.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        group.style.display = visibleCount > 0 ? '' : 'none';
    });

    // Also check the default option
    const defaultOpt = select.querySelector('option:not([data-player])');
    if (defaultOpt) defaultOpt.style.display = query ? 'none' : '';
});

// Init: build field toggles, show template preview, and handle query param pre-selection
setTimeout(() => {
    buildFieldToggles();
    showTemplatePreview();

    // Auto-select match and player from query params (e.g., coming from summary editor)
    const urlParams = new URLSearchParams(window.location.search);
    const paramMatchId = urlParams.get('match_id');
    const paramPlayerId = urlParams.get('player_id');
    const paramAwardName = urlParams.get('award_name');

    if (paramMatchId && currentType === 'award_poster') {
        const matchSelect = document.getElementById('awardMatchSelect');
        if (matchSelect) {
            // Select the match
            for (let i = 0; i < matchSelect.options.length; i++) {
                if (matchSelect.options[i].value === paramMatchId) {
                    matchSelect.selectedIndex = i;
                    break;
                }
            }
            // Load awards, then auto-select player after data loads
            const origFn = window.loadMatchAwards;
            loadMatchAwards(paramMatchId);

            if (paramPlayerId) {
                // Wait for AJAX to complete, then select the player
                const checkInterval = setInterval(() => {
                    const playerSelect = document.getElementById('awardPlayerSelect');
                    if (playerSelect && playerSelect.options.length > 1) {
                        clearInterval(checkInterval);
                        const targetValue = 'player_' + paramPlayerId;
                        for (let i = 0; i < playerSelect.options.length; i++) {
                            if (playerSelect.options[i].value === targetValue) {
                                playerSelect.selectedIndex = i;
                                playerSelect.dispatchEvent(new Event('change'));
                                break;
                            }
                        }
                        // Set award name override
                        if (paramAwardName) {
                            const awardInput = document.getElementById('awardNameOverride');
                            if (awardInput) awardInput.value = paramAwardName;
                        }
                    }
                }, 200);
                // Safety: clear interval after 5s
                setTimeout(() => clearInterval(checkInterval), 5000);
            } else if (paramAwardName) {
                // No player but award name provided
                setTimeout(() => {
                    const awardInput = document.getElementById('awardNameOverride');
                    if (awardInput) awardInput.value = paramAwardName;
                }, 1000);
            }
        }
    } else if (paramMatchId && ['match_poster', 'match_summary'].includes(currentType)) {
        const matchSelect = document.getElementById('matchSelect');
        if (matchSelect) {
            for (let i = 0; i < matchSelect.options.length; i++) {
                if (matchSelect.options[i].value === paramMatchId) {
                    matchSelect.selectedIndex = i;
                    matchSelect.dispatchEvent(new Event('change'));
                    break;
                }
            }
        }
    }
}, 500);

// Show innings selector on initial load based on current type
showInningsSelector();
</script>
@endpush
@endsection
