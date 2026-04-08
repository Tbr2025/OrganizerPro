@extends('backend.layouts.app')

@section('title', 'Generate Poster | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Templates', 'route' => route('admin.tournaments.templates.index', $tournament)],
    ['name' => 'Generate Poster']
]" />

<div class="max-w-6xl mx-auto">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-bold text-white mb-2">Generate Poster</h1>
        <p class="text-purple-100">Select data and template to create a poster with real tournament information</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Data Selection --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Poster Type Selection --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">1</span>
                    Select Poster Type
                </h3>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-3" x-data="{ type: '{{ request('type', 'match_poster') }}' }">
                    <button type="button" @click="type = 'match_poster'; updateType('match_poster')"
                            :class="type === 'match_poster' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-cyan-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Match Poster</span>
                    </button>

                    <button type="button" @click="type = 'match_summary'; updateType('match_summary')"
                            :class="type === 'match_summary' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-yellow-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Match Summary</span>
                    </button>

                    <button type="button" @click="type = 'award_poster'; updateType('award_poster')"
                            :class="type === 'award_poster' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-red-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Award Poster</span>
                    </button>

                    <button type="button" @click="type = 'welcome_card'; updateType('welcome_card')"
                            :class="type === 'welcome_card' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-green-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Welcome Card</span>
                    </button>

                    <button type="button" @click="type = 'point_table'; updateType('point_table')"
                            :class="type === 'point_table' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-700'"
                            class="p-4 rounded-xl border-2 text-center transition hover:border-purple-300">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-blue-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M3 6h18M3 18h18"/></svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Point Table</span>
                    </button>
                </div>
            </div>

            {{-- Data Selection Based on Type --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">2</span>
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
                                    data-match-number="{{ $match->match_number ?? $match->id }}">
                                Match #{{ $match->match_number ?? $match->id }}: {{ $match->teamA?->name ?? 'TBD' }} vs {{ $match->teamB?->name ?? 'TBD' }}
                                @if($match->match_date) - {{ $match->match_date->format('M d') }} @endif
                                @if($match->status === 'completed') (Completed) @endif
                            </option>
                        @endforeach
                    </select>

                    @if($matches->isEmpty())
                        <p class="text-sm text-gray-500 mt-2">No matches found. <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}" class="text-purple-600 hover:underline">Create fixtures first</a>.</p>
                    @endif

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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Player</label>
                    <select id="playerSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">-- Select a player --</option>
                        @foreach($players as $player)
                            <option value="{{ $player->id }}"
                                    data-name="{{ $player->name }}"
                                    data-jersey="{{ $player->jersey_number }}"
                                    data-team="{{ $player->actualTeam?->name }}"
                                    data-team-logo="{{ $player->actualTeam?->team_logo_url ?? '' }}"
                                    data-photo="{{ $player->image_path ? asset('storage/' . $player->image_path) : '' }}"
                                    data-type="{{ $player->playerType?->type ?? '' }}"
                                    data-batting="{{ $player->battingProfile?->style ?? '' }}"
                                    data-bowling="{{ $player->bowlingProfile?->style ?? '' }}">
                                {{ $player->name }} @if($player->actualTeam) ({{ $player->actualTeam->name }}) @endif
                            </option>
                        @endforeach
                    </select>

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
                        <div id="awardPlayerOverride" class="hidden space-y-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Player Details</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Player Name</label>
                                    <input type="text" id="awardPlayerName" placeholder="Auto from award" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <p class="text-xs text-gray-400 mt-1">Leave empty to use award data</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Player Image</label>
                                    <div id="awardPlayerImagePreview" class="hidden mb-2">
                                        <img id="awardPlayerImageThumb" src="" alt="Player" class="w-12 h-12 rounded-full object-cover border-2 border-purple-300 inline-block align-middle">
                                        <span id="awardPlayerImageLabel" class="text-xs text-green-600 ml-2 align-middle">From database</span>
                                    </div>
                                    <input type="file" id="awardPlayerImageUpload" accept="image/*" class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-600 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-300">
                                    <p class="text-xs text-gray-400 mt-1">Upload to override. BG auto-removed.</p>
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
            </div>

            {{-- Template Selection --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">3</span>
                    Select Template
                </h3>

                <div id="templatesList" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @forelse($templates as $template)
                        <div class="relative">
                            <label class="cursor-pointer">
                                <input type="radio" name="template_id" value="{{ $template->id }}" class="hidden peer" {{ $loop->first ? 'checked' : '' }}>
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                                    @if($template->background_image)
                                        <img src="{{ $template->background_image_url }}" alt="{{ $template->name }}" class="w-full h-24 object-cover rounded-lg mb-2">
                                    @else
                                        <div class="w-full h-24 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 flex items-center justify-center">
                                            <span class="text-gray-400 text-xs">No preview</span>
                                        </div>
                                    @endif
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $template->name }}</p>
                                    @if($template->is_default)
                                        <span class="text-xs text-purple-600">Default</span>
                                    @endif
                                </div>
                            </label>
                            <a href="{{ route('admin.tournaments.templates.edit', [$tournament, $template]) }}"
                               class="absolute top-1 right-1 p-1.5 rounded-lg bg-white/90 dark:bg-gray-800/90 text-gray-500 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/30 shadow-sm transition z-10"
                               title="Edit Template">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-8 text-gray-500">
                            <p>No templates found for this type.</p>
                            <a href="{{ route('admin.tournaments.templates.create', ['tournament' => $tournament, 'type' => request('type', 'match_poster')]) }}"
                               class="text-purple-600 hover:underline mt-2 inline-block">Create a template</a>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Field Visibility Toggles --}}
            <div id="fieldTogglesSection" class="hidden bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center cursor-pointer" onclick="document.getElementById('togglesBody').classList.toggle('hidden'); this.querySelector('svg').classList.toggle('rotate-180')">
                    <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 flex items-center justify-center mr-3 text-sm font-bold">4</span>
                    Field Visibility
                    <svg class="w-4 h-4 ml-auto text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </h3>
                <p class="text-xs text-gray-500 mb-3">Toggle off fields you don't want on the poster</p>
                <div id="togglesBody" class="grid grid-cols-2 gap-x-4 gap-y-2 max-h-[300px] overflow-y-auto">
                    {{-- Populated by JS --}}
                </div>
            </div>
        </div>

        {{-- Right: Preview & Actions --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 sticky top-4">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Preview & Generate</h3>

                {{-- Preview Area --}}
                <div id="previewArea" class="bg-gray-100 dark:bg-gray-900 rounded-xl p-4 mb-4 min-h-[300px] flex items-center justify-center">
                    <div id="previewPlaceholder" class="text-center text-gray-400">
                        <svg class="w-16 h-16 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm">Select data to preview</p>
                    </div>
                    <img id="previewImage" src="" alt="Preview" class="hidden max-w-full rounded-lg shadow-lg">
                    <div id="previewLoading" class="hidden text-center">
                        <svg class="w-10 h-10 mx-auto animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 mt-2">Generating preview...</p>
                    </div>
                </div>

                {{-- Data Summary --}}
                <div id="dataSummary" class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mb-4 hidden">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Selected Data</h4>
                    <div id="summaryContent" class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <!-- Populated by JS -->
                    </div>
                </div>

                {{-- Innings Selector --}}
                <div id="inningsSelector" class="mb-4 hidden">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Innings View</label>
                    <select id="inningsSelect" onchange="onInningsChange()"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="1">1st Innings (Batting First on Left)</option>
                        <option value="2">2nd Innings (Chasing Team on Left)</option>
                    </select>
                </div>

                {{-- Action Buttons --}}
                <div class="space-y-3">
                    <button type="button" onclick="generatePreview()" id="previewBtn"
                            class="w-full px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-xl transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Generate Preview
                    </button>

                    <button type="button" onclick="downloadPoster()" id="downloadBtn" disabled
                            class="w-full px-4 py-3 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium rounded-xl transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download Poster
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentType = '{{ request('type', 'match_poster') }}';
let generatedImageUrl = null;

function updateType(type) {
    currentType = type;

    // Show/hide data selection sections
    document.getElementById('matchSelection').classList.toggle('hidden', !['match_poster', 'match_summary'].includes(type));
    document.getElementById('playerSelection').classList.toggle('hidden', type !== 'welcome_card');
    document.getElementById('awardSelection').classList.toggle('hidden', type !== 'award_poster');
    document.getElementById('groupSelection').classList.toggle('hidden', type !== 'point_table');

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
                container.innerHTML = data.templates.map((t, i) => `
                    <div class="relative">
                        <label class="cursor-pointer">
                            <input type="radio" name="template_id" value="${t.id}" class="hidden peer" ${i === 0 ? 'checked' : ''}>
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                                ${t.background_image_url ?
                                    `<img src="${t.background_image_url}" alt="${t.name}" class="w-full h-24 object-cover rounded-lg mb-2">` :
                                    `<div class="w-full h-24 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 flex items-center justify-center"><span class="text-gray-400 text-xs">No preview</span></div>`
                                }
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">${t.name}</p>
                                ${t.is_default ? '<span class="text-xs text-purple-600">Default</span>' : ''}
                            </div>
                        </label>
                        <a href="${editBaseUrl}/${t.id}/edit" class="absolute top-1 right-1 p-1.5 rounded-lg bg-white/90 dark:bg-gray-800/90 text-gray-500 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/30 shadow-sm transition z-10" title="Edit Template">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                    </div>
                `).join('');
                // Show first template's background as preview
                setTimeout(() => showTemplatePreview(), 50);
            } else {
                container.innerHTML = `
                    <div class="col-span-full text-center py-8 text-gray-500">
                        <p>No templates found for this type.</p>
                        <a href="{{ route('admin.tournaments.templates.create', $tournament) }}?type=${type}"
                           class="text-purple-600 hover:underline mt-2 inline-block">Create a template</a>
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
    generatedImageUrl = null;
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
                if (batRuns) {
                    let bf = batRuns;
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
        if (batRuns) {
            let bf = batRuns;
            if (batBalls) bf += ` (${batBalls})`;
            if (batFours) bf += ` ${batFours}x4`;
            if (batSixes) bf += ` ${batSixes}x6`;
            data.batting_figures = bf;
        }

        // Build bowling_figures: "4 - 0 - 25 - 2"
        const bowlOvers = document.getElementById('bowlOvers')?.value?.trim();
        const bowlMaidens = document.getElementById('bowlMaidens')?.value?.trim();
        const bowlRuns = document.getElementById('bowlRuns')?.value?.trim();
        const bowlWickets = document.getElementById('bowlWickets')?.value?.trim();
        if (bowlOvers) {
            data.bowling_figures = `${bowlOvers} - ${bowlMaidens || '0'} - ${bowlRuns || '0'} - ${bowlWickets || '0'}`;
        }
    } else if (currentType === 'point_table') {
        const groupSelect = document.getElementById('groupSelect');
        const selected = groupSelect.options[groupSelect.selectedIndex];
        if (selected && selected.value) {
            data.group_id = selected.value;
            data.group_name = selected.dataset.name;
        }
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

    if (html) {
        content.innerHTML = html;
        summary.classList.remove('hidden');
    }
}

function generatePreview() {
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

    // Show loading
    document.getElementById('previewPlaceholder').classList.add('hidden');
    document.getElementById('previewImage').classList.add('hidden');
    document.getElementById('previewLoading').classList.remove('hidden');
    document.getElementById('previewBtn').disabled = true;
    document.getElementById('previewBtn').innerHTML = `
        <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Generating...
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
            generatedImageUrl = result.image;
        } else {
            document.getElementById('previewPlaceholder').classList.remove('hidden');
            alert(result.error || 'Failed to generate preview');
        }
    })
    .catch(err => {
        clearTimeout(timeoutId);
        resetPreviewBtn();
        document.getElementById('previewLoading').classList.add('hidden');
        document.getElementById('previewPlaceholder').classList.remove('hidden');
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
    const btn = document.getElementById('previewBtn');
    btn.disabled = false;
    btn.innerHTML = `
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        Generate Preview
    `;
}

function downloadPoster() {
    if (!generatedImageUrl) return;

    const innings = document.getElementById('inningsSelect')?.value || '1';
    const link = document.createElement('a');
    link.href = generatedImageUrl;
    link.download = `poster-${currentType}-innings${innings}-${Date.now()}.png`;
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

        // Clear manual fields
        document.getElementById('summaryMotmName').value = '';
        document.getElementById('summaryBestBatsman').value = '';
        document.getElementById('summaryBestBowler').value = '';
        document.getElementById('summaryBatRuns').value = '';
        document.getElementById('summaryBatBalls').value = '';
        document.getElementById('summaryBatFours').value = '';
        document.getElementById('summaryBatSixes').value = '';
        document.getElementById('summaryBowlOvers').value = '';
        document.getElementById('summaryBowlMaidens').value = '';
        document.getElementById('summaryBowlRuns').value = '';
        document.getElementById('summaryBowlWickets').value = '';

        statsSection.classList.remove('hidden');
    } else {
        statsSection?.classList.add('hidden');
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
            imageLabel.className = 'text-xs text-green-600 ml-2 align-middle';
            imagePreview.classList.remove('hidden');
        } else {
            imageThumb.src = '';
            imageLabel.textContent = 'No image — upload or use default';
            imageLabel.className = 'text-xs text-amber-500 ml-2 align-middle';
            imagePreview.classList.remove('hidden');
        }

        // Reset file upload
        imageUpload.value = '';

        overrideSection.classList.remove('hidden');
        showDataSummary(getSelectedData());
    } else {
        overrideSection.classList.add('hidden');
    }
});

// Update preview label when user uploads a custom image
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
