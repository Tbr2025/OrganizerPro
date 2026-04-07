@extends('backend.layouts.app')

@section('title', 'Match Summary | ' . $match->match_title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .gradient-card {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    }
    .winner-glow {
        box-shadow: 0 0 20px rgba(34, 197, 94, 0.4);
    }
    .score-gradient {
        background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
    }
    .team-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .team-card:hover {
        transform: translateY(-2px);
    }
    .highlight-item {
        border-left: 3px solid #3b82f6;
    }
    .award-card {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    .motm-card {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
    }
</style>
@endpush

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Matches', 'route' => route('admin.matches.index')],
    ['name' => $match->match_title, 'route' => route('admin.matches.show', $match)],
    ['name' => 'Summary Editor']
]" />

@php
    $summaryMode = $tournament->settings?->summary_update_mode ?? 'manual';
@endphp

{{-- Summary Mode & CricHeroes Import --}}
@if($summaryMode === 'manual')
<div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    {{-- Header bar --}}
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                {{ ucfirst($summaryMode) }} Mode
            </span>
            @if($match->cricheroes_match_url)
                <a href="{{ $match->cricheroes_match_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    CricHeroes
                </a>
            @endif
        </div>
        <button type="button" id="ch-toggle-import" class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Import from CricHeroes
        </button>
    </div>

    {{-- Expandable import panel --}}
    <div id="ch-import-panel" class="hidden border-t border-gray-200 dark:border-gray-700">
        <div class="p-4 space-y-3">
            {{-- URL Fetch (primary) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CricHeroes Match URL</label>
                <div class="flex gap-2">
                    <input type="text" id="ch-fetch-url"
                           value="{{ $match->cricheroes_match_url ?? '' }}"
                           class="form-control flex-1 text-sm"
                           placeholder="https://cricheroes.com/scorecard/...">
                    <button type="button" id="ch-fetch-btn"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-lg transition text-sm flex items-center whitespace-nowrap">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Fetch
                    </button>
                </div>
            </div>

            {{-- Paste fallback --}}
            <details class="group">
                <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Or paste scorecard text manually
                </summary>
                <div class="mt-2 space-y-2">
                    <textarea id="ch-paste-text" rows="4"
                              class="form-control text-sm w-full"
                              placeholder="Paste text from CricHeroes scorecard page, e.g.:&#10;&#10;ASIAN ROYALS CC 156/8 (20.0 Ov)&#10;EVEXIA ALL STARS 139/9 (20.0 Ov)&#10;Toss: Evexia All Stars opt to field&#10;Asian ROYALS CC won by 17 runs"></textarea>
                    <button type="button" id="ch-import-btn"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg transition text-sm">
                        Parse & Import
                    </button>
                </div>
            </details>
            <span id="ch-import-status" class="text-sm"></span>
        </div>

        {{-- Preview card (shown after parsing, before save) --}}
        <div id="ch-preview" class="hidden border-t border-gray-200 dark:border-gray-700">
            <div class="gradient-card p-5 text-white">
                <div class="text-center mb-4">
                    <span class="text-xs uppercase tracking-wider text-gray-400">Import Preview</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <div class="flex-1 text-center p-3 rounded-xl bg-white/5">
                        <h4 class="font-bold text-sm mb-1">{{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'Team A' }}</h4>
                        <div class="text-2xl font-black" id="ch-preview-a-score">-</div>
                        <div class="text-xs text-gray-400" id="ch-preview-a-overs">-</div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow">
                            <span class="text-white font-black text-xs">VS</span>
                        </div>
                    </div>
                    <div class="flex-1 text-center p-3 rounded-xl bg-white/5">
                        <h4 class="font-bold text-sm mb-1">{{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'Team B' }}</h4>
                        <div class="text-2xl font-black" id="ch-preview-b-score">-</div>
                        <div class="text-xs text-gray-400" id="ch-preview-b-overs">-</div>
                    </div>
                </div>
                <div id="ch-preview-result" class="mt-3 text-center hidden">
                    <span class="inline-flex items-center px-4 py-1.5 bg-yellow-500/20 rounded-full border border-yellow-500/30 text-yellow-300 text-sm font-semibold" id="ch-preview-result-text"></span>
                </div>
                <div id="ch-preview-toss" class="mt-2 text-center text-xs text-gray-400 hidden" id="ch-preview-toss-text"></div>
            </div>
            <div class="p-4 flex justify-end gap-3">
                <button type="button" id="ch-cancel-btn" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <button type="button" id="ch-save-btn" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Result
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Match Header Card -->
        <div class="gradient-card rounded-2xl p-6 text-white overflow-hidden relative">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-5">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <circle cx="5" cy="5" r="1" fill="white"/>
                    </pattern>
                    <rect width="100" height="100" fill="url(#grid)"/>
                </svg>
            </div>

            <!-- Tournament Info -->
            <div class="relative text-center mb-6">
                @if($tournament->settings?->logo)
                    <img src="{{ Storage::url($tournament->settings->logo) }}"
                         alt="{{ $tournament->name }}"
                         class="w-16 h-16 mx-auto mb-3 rounded-full object-cover border-2 border-white/30">
                @endif
                <h3 class="text-lg font-semibold text-gray-300">{{ $tournament->name }}</h3>
                <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full text-xs font-bold uppercase tracking-wider
                    {{ $match->stage === 'final' ? 'bg-yellow-500 text-yellow-900' :
                       ($match->stage === 'semi_final' ? 'bg-gray-300 text-gray-800' : 'bg-blue-500/20 text-blue-300') }}">
                    {{ $match->stage_display }}
                </span>
            </div>

            @php
                $teamABatsFirst = $match->result?->team_a_batting_first ?? true;
                $firstTeamId = $teamABatsFirst ? $match->team_a_id : $match->team_b_id;
                $secondTeamId = $teamABatsFirst ? $match->team_b_id : $match->team_a_id;
                $sFirstTeam = $teamABatsFirst ? $match->teamA : $match->teamB;
                $sSecondTeam = $teamABatsFirst ? $match->teamB : $match->teamA;
                $firstSideKey = $teamABatsFirst ? 'a' : 'b';
                $secondSideKey = $teamABatsFirst ? 'b' : 'a';
            @endphp

            <!-- Teams Score Display -->
            @if($match->result)
                <div class="relative flex items-center justify-between gap-4">
                    <!-- First Batting Team -->
                    <div class="flex-1 text-center team-card p-4 rounded-xl {{ $match->winner_team_id === $firstTeamId ? 'bg-green-500/20 winner-glow' : 'bg-white/5' }}">
                        @if($sFirstTeam?->team_logo)
                            <img src="{{ Storage::url($sFirstTeam->team_logo) }}"
                                 alt="{{ $sFirstTeam->name }}"
                                 class="w-20 h-20 mx-auto mb-3 rounded-full object-cover border-3 {{ $match->winner_team_id === $firstTeamId ? 'border-green-400' : 'border-white/30' }}">
                        @else
                            <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gray-600 flex items-center justify-center text-2xl font-bold">
                                {{ substr($sFirstTeam?->name ?? 'A', 0, 2) }}
                            </div>
                        @endif
                        <h4 class="font-bold text-lg mb-2">{{ $sFirstTeam?->short_name ?? $sFirstTeam?->name ?? 'Team A' }}</h4>
                        <div class="text-4xl font-black {{ $match->winner_team_id === $firstTeamId ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->{'team_' . $firstSideKey . '_score'} ?? 0 }}/{{ $match->result->{'team_' . $firstSideKey . '_wickets'} ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-400 mt-1">
                            ({{ number_format($match->result->{'team_' . $firstSideKey . '_overs'} ?? 0, 1) }} overs)
                        </div>
                        @if($match->result->{'team_' . $firstSideKey . '_extras'})
                            <div class="text-xs text-gray-500 mt-1">Extras: {{ $match->result->{'team_' . $firstSideKey . '_extras'} }}</div>
                        @endif
                        @if($match->winner_team_id === $firstTeamId)
                            <span class="inline-flex items-center px-2 py-1 mt-2 bg-green-500 text-white text-xs font-bold rounded-full">
                                WINNER
                            </span>
                        @endif
                    </div>

                    <!-- VS Badge -->
                    <div class="flex-shrink-0 z-10">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center shadow-lg">
                            <span class="text-white font-black text-sm">VS</span>
                        </div>
                    </div>

                    <!-- Second Batting Team -->
                    <div class="flex-1 text-center team-card p-4 rounded-xl {{ $match->winner_team_id === $secondTeamId ? 'bg-green-500/20 winner-glow' : 'bg-white/5' }}">
                        @if($sSecondTeam?->team_logo)
                            <img src="{{ Storage::url($sSecondTeam->team_logo) }}"
                                 alt="{{ $sSecondTeam->name }}"
                                 class="w-20 h-20 mx-auto mb-3 rounded-full object-cover border-3 {{ $match->winner_team_id === $secondTeamId ? 'border-green-400' : 'border-white/30' }}">
                        @else
                            <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gray-600 flex items-center justify-center text-2xl font-bold">
                                {{ substr($sSecondTeam?->name ?? 'B', 0, 2) }}
                            </div>
                        @endif
                        <h4 class="font-bold text-lg mb-2">{{ $sSecondTeam?->short_name ?? $sSecondTeam?->name ?? 'Team B' }}</h4>
                        <div class="text-4xl font-black {{ $match->winner_team_id === $secondTeamId ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->{'team_' . $secondSideKey . '_score'} ?? 0 }}/{{ $match->result->{'team_' . $secondSideKey . '_wickets'} ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-400 mt-1">
                            ({{ number_format($match->result->{'team_' . $secondSideKey . '_overs'} ?? 0, 1) }} overs)
                        </div>
                        @if($match->result->{'team_' . $secondSideKey . '_extras'})
                            <div class="text-xs text-gray-500 mt-1">Extras: {{ $match->result->{'team_' . $secondSideKey . '_extras'} }}</div>
                        @endif
                        @if($match->winner_team_id === $secondTeamId)
                            <span class="inline-flex items-center px-2 py-1 mt-2 bg-green-500 text-white text-xs font-bold rounded-full">
                                WINNER
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Result Summary -->
                @if($match->result->result_summary)
                    <div class="mt-6 text-center">
                        <div class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-yellow-500/20 to-orange-500/20 rounded-full border border-yellow-500/30">
                            <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <span class="text-yellow-300 font-semibold">{{ $match->result->result_summary }}</span>
                        </div>
                    </div>
                @endif

                <!-- Match Details -->
                <div class="mt-6 flex items-center justify-center gap-6 text-sm text-gray-400">
                    @if($match->match_date)
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ $match->match_date->format('D, M d, Y') }}
                        </div>
                    @endif
                    @if($match->venue)
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $match->venue }}
                        </div>
                    @endif
                    @if($match->match_time)
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $match->match_time }}
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-yellow-500/20 flex items-center justify-center">
                        <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <p class="text-yellow-300 font-medium mb-2">Match result not yet recorded</p>
                    <a href="{{ route('admin.matches.result.edit', $match) }}"
                       class="inline-flex items-center px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-yellow-900 font-semibold rounded-lg transition">
                        Record Result
                    </a>
                </div>
            @endif
        </div>

        <!-- Man of the Match Section -->
        @php
            $motmAward = $awards->first(function($award) {
                return $award->tournamentAward && str_contains(strtolower($award->tournamentAward->name), 'man of the match');
            });
        @endphp
        @if($motmAward)
            <div class="motm-card rounded-2xl p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 opacity-10">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                    </svg>
                </div>
                <div class="relative flex items-center gap-6">
                    <div class="flex-shrink-0">
                        @if($motmAward->player?->image_path)
                            <img src="{{ Storage::url($motmAward->player->image_path) }}"
                                 alt="{{ $motmAward->player->name }}"
                                 class="w-24 h-24 rounded-full object-cover border-4 border-white/30 shadow-xl">
                        @else
                            <div class="w-24 h-24 rounded-full bg-white/20 flex items-center justify-center text-3xl font-bold">
                                {{ substr($motmAward->player?->name ?? 'M', 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-2xl">{{ $motmAward->tournamentAward->icon ?? '🏆' }}</span>
                            <span class="text-sm font-medium text-purple-200 uppercase tracking-wider">Man of the Match</span>
                        </div>
                        <h3 class="text-2xl font-bold">{{ $motmAward->player?->name ?? 'Unknown Player' }}</h3>
                        @if($motmAward->remarks)
                            <p class="text-purple-200 text-sm mt-1">{{ $motmAward->remarks }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Match Highlights Section -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/>
                    </svg>
                    Match Highlights
                </h3>
            </div>
            <div class="p-6">
                @if($summary->hasHighlights())
                    <ul class="space-y-3 mb-6">
                        @foreach($summary->highlights as $index => $highlight)
                            <li class="highlight-item flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                <div class="flex items-center">
                                    <span class="w-6 h-6 rounded-full bg-blue-500 text-white flex items-center justify-center text-xs font-bold mr-3">
                                        {{ $index + 1 }}
                                    </span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ $highlight }}</span>
                                </div>
                                <form action="{{ route('admin.matches.summary.remove-highlight', $match) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="index" value="{{ $index }}">
                                    <button type="submit" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-6 text-gray-400 mb-4">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/>
                        </svg>
                        <p>No highlights added yet</p>
                    </div>
                @endif

                <form action="{{ route('admin.matches.summary.add-highlight', $match) }}" method="POST" class="flex gap-3">
                    @csrf
                    <input type="text"
                           name="highlight"
                           placeholder="Add a highlight (e.g., Player X scored 50 runs off 30 balls)"
                           class="flex-1 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-blue-500 focus:border-blue-500"
                           required>
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition flex items-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add
                    </button>
                </form>

                <!-- Quick Add Highlight Buttons -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="text-xs text-gray-500 mr-2 py-1">Quick add:</span>
                    @php
                        $quickHighlights = [
                            'Excellent batting display',
                            'Outstanding bowling performance',
                            'Crucial partnership',
                            'Match-winning innings',
                            'Hat-trick',
                            'Century scored',
                            'Five-wicket haul',
                        ];
                    @endphp
                    @foreach($quickHighlights as $qh)
                        <button type="button"
                                onclick="document.querySelector('input[name=highlight]').value = '{{ $qh }}'"
                                class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-full transition">
                            {{ $qh }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Commentary Section -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z" clip-rule="evenodd"/>
                    </svg>
                    Match Commentary / Notes
                </h3>
            </div>
            <div class="p-6">
                <form action="{{ route('admin.matches.summary.update', $match) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <textarea name="commentary"
                              rows="5"
                              class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-green-500 focus:border-green-500"
                              placeholder="Write match commentary, key moments, or notes about the game...">{{ $summary->commentary }}</textarea>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition flex items-center">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Commentary
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Awards Section -->
        <div class="card rounded-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    Match Awards
                </h3>
            </div>
            <div class="p-6">
                @if($awards->count() > 0)
                    <div class="space-y-3 mb-6">
                        @foreach($awards as $award)
                            <div class="relative flex items-center gap-4 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition">
                                <!-- Player Image -->
                                <div class="flex-shrink-0">
                                    @if($award->player?->image_path)
                                        <img src="{{ Storage::url($award->player->image_path) }}"
                                             alt="{{ $award->player->name }}"
                                             class="w-14 h-14 rounded-full object-cover border-2 border-yellow-400 shadow-md">
                                    @else
                                        <div class="w-14 h-14 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-lg font-bold text-gray-600 dark:text-gray-300">
                                            {{ substr($award->player?->name ?? '?', 0, 1) }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Award Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-lg">{{ $award->tournamentAward?->icon ?? '🏆' }}</span>
                                        <span class="text-sm font-semibold text-yellow-600 dark:text-yellow-400">
                                            {{ $award->tournamentAward?->name ?? 'Award' }}
                                        </span>
                                    </div>
                                    <div class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $award->player?->name ?? 'Unknown' }}
                                    </div>
                                    @if($award->remarks)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $award->remarks }}</p>
                                    @endif
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <a href="{{ route('admin.tournaments.templates.generate', $tournament) }}?type=award_poster&match_id={{ $match->id }}&player_id={{ $award->player_id }}&award_name={{ urlencode($award->tournamentAward?->name ?? 'Award') }}"
                                       class="p-2 text-purple-400 hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition" title="Generate Award Poster">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </a>
                                    <form action="{{ route('admin.matches.summary.remove-award', [$match, $award]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition" title="Remove Award">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6 text-gray-400 mb-4">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        <p>No awards assigned yet</p>
                    </div>
                @endif

                @if($tournamentAwards->isEmpty())
                    <!-- No Awards Configured - Show Create Default Awards -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                        <div class="text-center py-4">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 mb-3">No awards configured for this tournament.</p>
                            <form action="{{ route('admin.matches.summary.create-default-awards', $match) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white font-semibold rounded-xl transition flex items-center justify-center mx-auto shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Create Default Awards
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 mt-3">Creates: Man of the Match, Best Batsman, Best Bowler, Best Fielder, Best Catch</p>
                        </div>
                    </div>
                @else
                    <!-- Unified Award & Poster Section -->
                    <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-gray-800 dark:to-gray-800 rounded-xl p-4 border border-purple-200 dark:border-purple-700">
                        {{-- Award + Player Selection (shared by both assign and poster) --}}
                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Award</label>
                                    <select id="apAwardSelect" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                                        <option value="" data-name="">Select Award</option>
                                        @foreach($tournamentAwards as $tAward)
                                            <option value="{{ $tAward->id }}" data-name="{{ $tAward->name }}">{{ $tAward->icon ?? '' }} {{ $tAward->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Player</label>
                                    <select id="apPlayerSelect" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                                        <option value="">Select Player</option>
                                        @if($teamAPlayers->count() > 0)
                                            <optgroup label="{{ $match->teamA?->name ?? 'Team A' }}">
                                                @foreach($teamAPlayers as $player)
                                                    <option value="{{ $player->id }}"
                                                        data-name="{{ $player->jersey_name ?: $player->name }}"
                                                        data-image="{{ $player->image_path ?? '' }}"
                                                        data-team="{{ $match->teamA?->name ?? '' }}"
                                                        data-team-logo="{{ $match->teamA?->team_logo ?? '' }}">
                                                        {{ $player->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if($teamBPlayers->count() > 0)
                                            <optgroup label="{{ $match->teamB?->name ?? 'Team B' }}">
                                                @foreach($teamBPlayers as $player)
                                                    <option value="{{ $player->id }}"
                                                        data-name="{{ $player->jersey_name ?: $player->name }}"
                                                        data-image="{{ $player->image_path ?? '' }}"
                                                        data-team="{{ $match->teamB?->name ?? '' }}"
                                                        data-team-logo="{{ $match->teamB?->team_logo ?? '' }}">
                                                        {{ $player->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            {{-- Remarks + Assign Button --}}
                            <form action="{{ route('admin.matches.summary.assign-award', $match) }}" method="POST" id="apAssignForm">
                                @csrf
                                <input type="hidden" name="tournament_award_id" id="apAssignAwardId">
                                <input type="hidden" name="player_id" id="apAssignPlayerId">
                                <div class="flex gap-2">
                                    <input type="text" name="remarks" id="apRemarks"
                                           placeholder="Performance remarks (e.g., 65 runs off 40 balls)"
                                           class="flex-1 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                                    <button type="submit" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-xl transition flex items-center justify-center whitespace-nowrap text-sm">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Assign Award
                                    </button>
                                </div>
                            </form>

                            {{-- Divider --}}
                            <div class="relative py-1">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-purple-200 dark:border-purple-700"></div></div>
                                <div class="relative flex justify-center"><span class="bg-purple-50 dark:bg-gray-800 px-3 text-xs font-medium text-purple-500 dark:text-purple-400">Generate Award Poster</span></div>
                            </div>

                            {{-- Player Image --}}
                            <div id="apPlayerImageSection" class="hidden">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Player Image</label>
                                <div class="flex items-center gap-3">
                                    <div id="apPlayerImagePreview" class="hidden">
                                        <img id="apPlayerImageThumb" src="" alt="Player" class="w-12 h-12 rounded-full object-cover border-2 border-purple-300">
                                    </div>
                                    <input type="file" id="apPlayerImageUpload" accept="image/*" class="flex-1 text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-600 hover:file:bg-purple-100 dark:file:bg-purple-900/30 dark:file:text-purple-300">
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Upload to override. BG auto-removed.</p>
                            </div>

                            {{-- Score Summary (auto-filled) --}}
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Score Summary</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" id="apTeamAScore" placeholder="{{ $match->teamA?->name ?? 'Team A' }} Score"
                                           value="{{ $match->result ? $match->result->team_a_score_display : '' }}"
                                           class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                                    <input type="text" id="apTeamBScore" placeholder="{{ $match->teamB?->name ?? 'Team B' }} Score"
                                           value="{{ $match->result ? $match->result->team_b_score_display : '' }}"
                                           class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Result Summary</label>
                                <input type="text" id="apResultSummary" placeholder="e.g. Team A won by 5 runs"
                                       value="{{ $match->result?->result_summary ?? '' }}"
                                       class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-purple-500 focus:border-purple-500">
                            </div>

                            {{-- Batting & Bowling --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Batting Figures</label>
                                    <div class="grid grid-cols-4 gap-1">
                                        <input type="number" id="apBatRuns" placeholder="R" title="Runs" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBatBalls" placeholder="B" title="Balls" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBatFours" placeholder="4s" title="Fours" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBatSixes" placeholder="6s" title="Sixes" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Bowling Figures</label>
                                    <div class="grid grid-cols-4 gap-1">
                                        <input type="text" id="apBowlOvers" placeholder="Ov" title="Overs" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBowlMaidens" placeholder="M" title="Maidens" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBowlRuns" placeholder="R" title="Runs" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                        <input type="number" id="apBowlWickets" placeholder="W" title="Wickets" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs focus:ring-purple-500 focus:border-purple-500 px-2">
                                    </div>
                                </div>
                            </div>

                            {{-- Template Selection --}}
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Template</label>
                                <div id="apTemplatesList" class="grid grid-cols-3 gap-2">
                                    <p class="col-span-3 text-xs text-gray-400">Loading templates...</p>
                                </div>
                            </div>

                            {{-- Generate & Download --}}
                            <div class="flex gap-2">
                                <button type="button" onclick="generateAwardPoster()" id="apPreviewBtn"
                                        class="flex-1 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition flex items-center justify-center text-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Generate Preview
                                </button>
                                <button type="button" onclick="downloadAwardPoster()" id="apDownloadBtn" disabled
                                        class="px-4 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition flex items-center justify-center text-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Download
                                </button>
                            </div>

                            {{-- Preview Image --}}
                            <div id="apPreviewContainer" class="hidden">
                                <div id="apPreviewLoading" class="hidden text-center py-6">
                                    <svg class="animate-spin h-8 w-8 mx-auto text-purple-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-xs text-gray-500 mt-2">Generating poster...</p>
                                </div>
                                <img id="apPreviewImage" src="" alt="Award Poster Preview" class="hidden w-full rounded-xl border border-purple-200 dark:border-purple-700 shadow-lg">
                            </div>

                            {{-- Edit Template Link --}}
                            <div class="text-center">
                                <a href="{{ route('admin.tournaments.templates.index', $tournament) }}?type=award_poster"
                                   class="inline-flex items-center text-xs text-purple-500 hover:text-purple-700 dark:text-purple-400">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Edit Award Templates
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar: Poster Preview & Actions -->
    <div class="lg:col-span-1" x-data="{
        selectedTemplate: '{{ $summary->poster_template ?? 'classic' }}',
        showCustomize: false,
        customColors: {
            primary: '{{ $tournament->settings?->primary_color ?? '#1a1a2e' }}',
            secondary: '{{ $tournament->settings?->secondary_color ?? '#fbbf24' }}',
            background: '{{ $tournament->settings?->primary_color ?? '#1a1a2e' }}'
        }
    }">
        <div class="card rounded-2xl overflow-hidden sticky top-4">
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-4">
                <h3 class="text-white font-bold text-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                    </svg>
                    Summary Poster
                </h3>
            </div>

            <div class="p-4">
                <!-- Template Selector -->
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Template</label>
                        <button type="button"
                                @click="showCustomize = !showCustomize"
                                class="text-xs text-purple-600 hover:text-purple-700 dark:text-purple-400 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="showCustomize ? 'Hide' : 'Customize'"></span>
                        </button>
                    </div>
                    <div class="grid grid-cols-5 gap-2">
                        @php
                            $templates = [
                                'classic' => ['name' => 'Classic', 'color' => 'from-blue-900 to-indigo-900'],
                                'modern' => ['name' => 'Modern', 'color' => 'from-slate-800 to-slate-900'],
                                'minimal' => ['name' => 'Light', 'color' => 'from-gray-100 to-white'],
                                'gradient' => ['name' => 'Vibrant', 'color' => 'from-purple-500 to-pink-500'],
                                'dark' => ['name' => 'Dark', 'color' => 'from-black to-gray-900'],
                            ];
                        @endphp
                        @foreach($templates as $key => $template)
                            <button type="button"
                                    @click="selectedTemplate = '{{ $key }}'"
                                    :class="selectedTemplate === '{{ $key }}' ? 'ring-2 ring-purple-500 ring-offset-2 dark:ring-offset-gray-800' : ''"
                                    class="relative aspect-[3/4] rounded-lg overflow-hidden transition-all hover:scale-105 focus:outline-none bg-gradient-to-br {{ $template['color'] }} {{ $key === 'minimal' ? 'border border-gray-300' : '' }}">
                                <span class="absolute bottom-1 left-0 right-0 text-center text-xs font-medium {{ $key === 'minimal' ? 'text-gray-600' : 'text-white' }}">{{ $template['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Customization Panel -->
                <div x-show="showCustomize" x-collapse class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Customize Colors</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Primary Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       x-model="customColors.primary"
                                       class="w-10 h-10 rounded-lg border-0 cursor-pointer">
                                <input type="text"
                                       x-model="customColors.primary"
                                       class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Accent Color</label>
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       x-model="customColors.secondary"
                                       class="w-10 h-10 rounded-lg border-0 cursor-pointer">
                                <input type="text"
                                       x-model="customColors.secondary"
                                       class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 space-y-2">
                        <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                           class="flex items-center text-xs text-gray-600 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Edit Tournament Colors
                        </a>
                        <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                           class="flex items-center text-xs text-gray-600 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-400">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                            </svg>
                            Manage All Templates
                        </a>
                    </div>
                </div>

                <!-- Poster Preview -->
                <div class="bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 rounded-xl overflow-hidden mb-4 shadow-inner" style="max-height: 600px; overflow-y: auto;">
                    @if($match->result)
                        <div id="poster-preview-container">
                            <div x-show="selectedTemplate === 'classic'">
                                <x-posters.summary-poster :match="$match" template="classic" :scale="0.35" />
                            </div>
                            <div x-show="selectedTemplate === 'modern'">
                                <x-posters.summary-poster :match="$match" template="modern" :scale="0.35" />
                            </div>
                            <div x-show="selectedTemplate === 'minimal'">
                                <x-posters.summary-poster :match="$match" template="minimal" :scale="0.35" />
                            </div>
                            <div x-show="selectedTemplate === 'gradient'">
                                <x-posters.summary-poster :match="$match" template="gradient" :scale="0.35" />
                            </div>
                            <div x-show="selectedTemplate === 'dark'">
                                <x-posters.summary-poster :match="$match" template="dark" :scale="0.35" />
                            </div>
                        </div>
                    @else
                        <div class="aspect-[3/4] flex items-center justify-center text-gray-400">
                            <div class="text-center p-4">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm">No result recorded yet</p>
                                <p class="text-xs text-gray-400 mt-1">Record match result to preview poster</p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Hidden Full-Size Posters for Download -->
                @if($match->result)
                    <div id="poster-full-size" style="position: absolute; left: -9999px; top: 0; width: 1080px; overflow: visible;">
                        <div id="poster-classic-full" style="width: 1080px; height: 1350px;">
                            <x-posters.summary-poster :match="$match" template="classic" :scale="1" />
                        </div>
                        <div id="poster-modern-full" style="width: 1080px; height: 1350px; display: none;">
                            <x-posters.summary-poster :match="$match" template="modern" :scale="1" />
                        </div>
                        <div id="poster-minimal-full" style="width: 1080px; height: 1350px; display: none;">
                            <x-posters.summary-poster :match="$match" template="minimal" :scale="1" />
                        </div>
                        <div id="poster-gradient-full" style="width: 1080px; height: 1350px; display: none;">
                            <x-posters.summary-poster :match="$match" template="gradient" :scale="1" />
                        </div>
                        <div id="poster-dark-full" style="width: 1080px; height: 1350px; display: none;">
                            <x-posters.summary-poster :match="$match" template="dark" :scale="1" />
                        </div>
                    </div>
                @endif

                <!-- Status Badge -->
                @if($summary->poster_sent)
                    <div class="mb-4 flex items-center justify-center gap-2 text-sm text-green-600 bg-green-50 dark:bg-green-900/30 py-2 rounded-lg">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Sent on {{ $summary->poster_sent_at->format('M d, Y H:i') }}
                    </div>
                @endif

                <!-- Actions -->
                <div class="space-y-3">
                    <form action="{{ route('admin.matches.summary.generate-poster', $match) }}" method="POST">
                        @csrf
                        <input type="hidden" name="template" :value="selectedTemplate">
                        <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Regenerate Poster
                        </button>
                    </form>

                    @if($match->result)
                        <button type="button"
                                onclick="downloadPoster()"
                                id="download-btn"
                                class="w-full px-4 py-3 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-semibold rounded-xl transition flex items-center justify-center">
                            <svg id="download-icon" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <svg id="download-spinner" class="w-5 h-5 mr-2 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="download-text">Download Poster</span>
                        </button>

                        @if(!$summary->poster_sent)
                            <form action="{{ route('admin.matches.summary.send', $match) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl transition flex items-center justify-center shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    Send to Teams
                                </button>
                            </form>
                        @endif
                    @endif

                    <!-- Recalculate Statistics -->
                    <form action="{{ route('admin.matches.summary.recalculate-statistics', $match) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold rounded-xl transition flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Recalculate All Statistics
                        </button>
                    </form>
                </div>

                <!-- Share Links -->
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="font-medium text-sm mb-3 text-gray-600 dark:text-gray-400">Share & Preview</h4>
                    <div class="space-y-2">
                        <a href="{{ route('public.match.summary', $match->slug) }}"
                           target="_blank"
                           class="flex items-center px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            View Public Page
                        </a>

                        <button type="button"
                                onclick="copyToClipboard('{{ route('public.match.summary', $match->slug) }}')"
                                class="w-full flex items-center px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Copy Link
                        </button>

                        @php
                            $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                            $whatsappLink = $whatsappService->getResultShareLink($match);
                        @endphp
                        <a href="{{ $whatsappLink }}"
                           target="_blank"
                           class="flex items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 rounded-lg transition">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            Share on WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                @if($match->result)
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="font-medium text-sm mb-3 text-gray-600 dark:text-gray-400">Quick Stats</h4>
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                            <div class="text-2xl font-bold text-blue-600">{{ ($match->result->team_a_score ?? 0) + ($match->result->team_b_score ?? 0) }}</div>
                            <div class="text-xs text-gray-500">Total Runs</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                            <div class="text-2xl font-bold text-red-600">{{ ($match->result->team_a_wickets ?? 0) + ($match->result->team_b_wickets ?? 0) }}</div>
                            <div class="text-xs text-gray-500">Total Wickets</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                            <div class="text-2xl font-bold text-green-600">{{ $awards->count() }}</div>
                            <div class="text-xs text-gray-500">Awards Given</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                            <div class="text-2xl font-bold text-purple-600">{{ $summary->highlights_count ?? 0 }}</div>
                            <div class="text-xs text-gray-500">Highlights</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast notification
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
        toast.textContent = 'Link copied to clipboard!';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    });
}

async function downloadPoster() {
    const btn = document.getElementById('download-btn');
    const icon = document.getElementById('download-icon');
    const spinner = document.getElementById('download-spinner');
    const text = document.getElementById('download-text');

    // Get selected template from Alpine
    const selectedTemplate = Alpine.$data(btn.closest('[x-data]')).selectedTemplate || 'classic';

    // Show loading state
    btn.disabled = true;
    icon.classList.add('hidden');
    spinner.classList.remove('hidden');
    text.textContent = 'Generating...';

    try {
        // Show the correct full-size poster
        const fullSizeContainer = document.getElementById('poster-full-size');
        const allPosters = fullSizeContainer.querySelectorAll('[id$="-full"]');
        allPosters.forEach(p => p.style.display = 'none');

        const targetPoster = document.getElementById(`poster-${selectedTemplate}-full`);
        targetPoster.style.display = 'block';

        // Move container to visible area temporarily for rendering
        fullSizeContainer.style.left = '0';
        fullSizeContainer.style.top = '0';
        fullSizeContainer.style.position = 'fixed';
        fullSizeContainer.style.zIndex = '9999';
        fullSizeContainer.style.opacity = '0.01'; // Almost invisible but rendered
        fullSizeContainer.style.pointerEvents = 'none';

        // Get the actual poster element (the div with 1080x1350 dimensions)
        const posterElement = targetPoster.querySelector('[class^="poster-"]');

        if (!posterElement) {
            throw new Error('Poster element not found');
        }

        // Wait for images to load
        const images = posterElement.querySelectorAll('img');
        if (images.length > 0) {
            await Promise.all(Array.from(images).map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise((resolve) => {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            }));
        }

        // Additional wait for rendering
        await new Promise(resolve => setTimeout(resolve, 300));

        // Generate canvas - let html2canvas capture at natural size
        const canvas = await html2canvas(posterElement, {
            scale: 1, // Natural scale since poster is already 1080x1350
            useCORS: true,
            allowTaint: true,
            backgroundColor: null,
            logging: false,
            onclone: function(clonedDoc, element) {
                // Ensure the cloned element is properly visible
                element.style.display = 'block';
                element.style.visibility = 'visible';
            }
        });

        // Move container back off-screen
        fullSizeContainer.style.left = '-9999px';
        fullSizeContainer.style.top = '0';
        fullSizeContainer.style.position = 'absolute';
        fullSizeContainer.style.zIndex = '-1';
        fullSizeContainer.style.opacity = '1';
        fullSizeContainer.style.pointerEvents = 'auto';

        // Convert to blob and download
        canvas.toBlob((blob) => {
            if (!blob) {
                showToast('Failed to generate poster image.', 'error');
                return;
            }
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `match-summary-{{ $match->id }}-${selectedTemplate}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            // Show success toast
            showToast('Poster downloaded successfully!', 'success');
        }, 'image/png', 1.0);

    } catch (error) {
        console.error('Error generating poster:', error);
        showToast('Failed to generate poster. Please try again.', 'error');

        // Reset container position on error
        const fullSizeContainer = document.getElementById('poster-full-size');
        if (fullSizeContainer) {
            fullSizeContainer.style.left = '-9999px';
            fullSizeContainer.style.position = 'absolute';
        }
    } finally {
        // Reset button state
        btn.disabled = false;
        icon.classList.remove('hidden');
        spinner.classList.add('hidden');
        text.textContent = 'Download Poster';
    }
}

function showToast(message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-800';
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2`;
    toast.innerHTML = `
        ${type === 'success' ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' : ''}
        ${type === 'error' ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>' : ''}
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ===== Inline Award Poster Generator =====
let apSelectedTemplateId = null;
let apGeneratedImageBase64 = null;

// Load award_poster templates on page load
(function loadAwardTemplates() {
    fetch(`{{ url('admin/tournaments/' . $tournament->id . '/templates') }}?type=award_poster&ajax=1`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('apTemplatesList');
            if (data.templates && data.templates.length > 0) {
                container.innerHTML = data.templates.map((t, i) => `
                    <label class="cursor-pointer">
                        <input type="radio" name="ap_template_id" value="${t.id}" class="hidden peer" ${i === 0 ? 'checked' : ''}>
                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-1.5 transition peer-checked:border-purple-500 peer-checked:bg-purple-50 dark:peer-checked:bg-purple-900/30 hover:border-purple-300">
                            ${t.background_image_url ?
                                `<img src="${t.background_image_url}" alt="${t.name}" class="w-full h-16 object-cover rounded mb-1">` :
                                `<div class="w-full h-16 bg-gray-100 dark:bg-gray-700 rounded mb-1 flex items-center justify-center"><span class="text-gray-400 text-[10px]">No preview</span></div>`
                            }
                            <p class="text-[10px] font-medium text-gray-600 dark:text-gray-400 truncate text-center">${t.name}</p>
                        </div>
                    </label>
                `).join('');
                apSelectedTemplateId = data.templates[0].id;

                // Track template selection
                container.addEventListener('change', function(e) {
                    if (e.target.name === 'ap_template_id') {
                        apSelectedTemplateId = e.target.value;
                    }
                });
            } else {
                container.innerHTML = `<p class="col-span-3 text-xs text-gray-400">No award poster templates found. <a href="{{ route('admin.tournaments.templates.create', $tournament) }}?type=award_poster" class="text-purple-500 hover:underline">Create one</a></p>`;
            }
        })
        .catch(() => {
            document.getElementById('apTemplatesList').innerHTML = '<p class="col-span-3 text-xs text-red-400">Failed to load templates.</p>';
        });
})();

// Sync shared dropdowns to hidden assign form fields
document.getElementById('apAwardSelect')?.addEventListener('change', function() {
    document.getElementById('apAssignAwardId').value = this.value;
});
document.getElementById('apPlayerSelect')?.addEventListener('change', function() {
    document.getElementById('apAssignPlayerId').value = this.value;

    // Show player image section
    const section = document.getElementById('apPlayerImageSection');
    const preview = document.getElementById('apPlayerImagePreview');
    const thumb = document.getElementById('apPlayerImageThumb');

    if (this.value) {
        section.classList.remove('hidden');
        const opt = this.options[this.selectedIndex];
        const imagePath = opt.dataset.image;
        if (imagePath) {
            thumb.src = '/storage/' + imagePath;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    } else {
        section.classList.add('hidden');
    }
});

// Validate assign form before submit
document.getElementById('apAssignForm')?.addEventListener('submit', function(e) {
    const awardId = document.getElementById('apAssignAwardId').value;
    const playerId = document.getElementById('apAssignPlayerId').value;
    if (!awardId || !playerId) {
        e.preventDefault();
        showToast('Please select both an award and a player.', 'error');
    }
});

// Generate award poster preview
async function generateAwardPoster() {
    const templateInput = document.querySelector('input[name="ap_template_id"]:checked');
    if (!templateInput) {
        showToast('Please select a template.', 'error');
        return;
    }

    const playerSelect = document.getElementById('apPlayerSelect');
    const playerOpt = playerSelect.options[playerSelect.selectedIndex];

    // Build form data
    const formData = new FormData();
    formData.append('template_id', templateInput.value);
    formData.append('type', 'award_poster');
    formData.append('match_id', '{{ $match->id }}');

    // Award name from shared dropdown
    const awardSelect = document.getElementById('apAwardSelect');
    const awardOpt = awardSelect?.options[awardSelect.selectedIndex];
    const awardName = awardOpt?.dataset?.name || '';
    if (awardName) formData.append('award_name', awardName);

    // Player data
    if (playerOpt && playerOpt.value) {
        formData.append('player_name', playerOpt.dataset.name || '');
        if (playerOpt.dataset.image) formData.append('player_image', playerOpt.dataset.image);
        if (playerOpt.dataset.team) formData.append('team_name', playerOpt.dataset.team);
        if (playerOpt.dataset.teamLogo) formData.append('team_logo', playerOpt.dataset.teamLogo);
    }

    // Scores
    const teamAScore = document.getElementById('apTeamAScore')?.value?.trim();
    const teamBScore = document.getElementById('apTeamBScore')?.value?.trim();
    const resultSummary = document.getElementById('apResultSummary')?.value?.trim();
    if (teamAScore) formData.append('team_a_score', teamAScore);
    if (teamBScore) formData.append('team_b_score', teamBScore);
    if (resultSummary) formData.append('result_summary', resultSummary);

    // Batting figures
    const batRuns = document.getElementById('apBatRuns')?.value?.trim();
    const batBalls = document.getElementById('apBatBalls')?.value?.trim();
    const batFours = document.getElementById('apBatFours')?.value?.trim();
    const batSixes = document.getElementById('apBatSixes')?.value?.trim();
    if (batRuns) {
        let bf = batRuns;
        if (batBalls) bf += ` (${batBalls})`;
        if (batFours) bf += ` ${batFours}x4`;
        if (batSixes) bf += ` ${batSixes}x6`;
        formData.append('batting_figures', bf);
    }

    // Bowling figures
    const bowlOvers = document.getElementById('apBowlOvers')?.value?.trim();
    const bowlMaidens = document.getElementById('apBowlMaidens')?.value?.trim();
    const bowlRuns = document.getElementById('apBowlRuns')?.value?.trim();
    const bowlWickets = document.getElementById('apBowlWickets')?.value?.trim();
    if (bowlOvers) {
        formData.append('bowling_figures', `${bowlOvers} - ${bowlMaidens || '0'} - ${bowlRuns || '0'} - ${bowlWickets || '0'}`);
    }

    // Custom player image upload
    const customImage = document.getElementById('apPlayerImageUpload')?.files?.[0];
    if (customImage) {
        formData.append('player_image_file', customImage);
    }

    // Show loading
    const container = document.getElementById('apPreviewContainer');
    const loading = document.getElementById('apPreviewLoading');
    const previewImg = document.getElementById('apPreviewImage');
    const btn = document.getElementById('apPreviewBtn');
    container.classList.remove('hidden');
    loading.classList.remove('hidden');
    previewImg.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating...';

    try {
        const response = await fetch('{{ route("admin.tournaments.templates.generate-preview", $tournament) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData
        });
        const result = await response.json();

        if (result.success && result.image) {
            previewImg.src = result.image;
            previewImg.classList.remove('hidden');
            loading.classList.add('hidden');
            apGeneratedImageBase64 = result.image;
            document.getElementById('apDownloadBtn').disabled = false;
            showToast('Award poster generated!', 'success');
        } else {
            throw new Error(result.error || 'Generation failed');
        }
    } catch (err) {
        loading.classList.add('hidden');
        container.classList.add('hidden');
        showToast('Failed: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> Generate Preview';
    }
}

// Download generated award poster
function downloadAwardPoster() {
    if (!apGeneratedImageBase64) return;

    const link = document.createElement('a');
    link.href = apGeneratedImageBase64;
    link.download = `award-poster-{{ $match->id }}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Award poster downloaded!', 'success');
}

// --- CricHeroes Import ---
(function() {
    const toggleBtn = document.getElementById('ch-toggle-import');
    const panel = document.getElementById('ch-import-panel');
    const importBtn = document.getElementById('ch-import-btn');
    const statusEl = document.getElementById('ch-import-status');
    const preview = document.getElementById('ch-preview');
    const cancelBtn = document.getElementById('ch-cancel-btn');
    const saveBtn = document.getElementById('ch-save-btn');

    if (!toggleBtn) return;

    const teamAId = '{{ $match->team_a_id }}';
    const teamBId = '{{ $match->team_b_id }}';
    const teamAName = @json($match->teamA?->name ?? 'Team A');
    const teamBName = @json($match->teamB?->name ?? 'Team B');
    let pendingData = null;

    toggleBtn.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        preview.classList.add('hidden');
    });

    function fuzzy(a, b) {
        const x = a.toLowerCase().trim(), y = b.toLowerCase().trim();
        return x === y || x.includes(y) || y.includes(x);
    }

    function showPreviewFromData(teamA, teamB, resultText, tossText) {
        document.getElementById('ch-preview-a-score').textContent = `${teamA.runs}/${teamA.wickets}`;
        document.getElementById('ch-preview-a-overs').textContent = `(${teamA.overs} ov)`;
        document.getElementById('ch-preview-b-score').textContent = `${teamB.runs}/${teamB.wickets}`;
        document.getElementById('ch-preview-b-overs').textContent = `(${teamB.overs} ov)`;

        const resultEl = document.getElementById('ch-preview-result');
        const resultTextEl = document.getElementById('ch-preview-result-text');
        if (resultText) {
            resultTextEl.textContent = resultText;
            resultEl.classList.remove('hidden');
        } else {
            resultEl.classList.add('hidden');
        }

        const tossEl = document.getElementById('ch-preview-toss');
        if (tossText) {
            tossEl.textContent = tossText;
            tossEl.classList.remove('hidden');
        } else {
            tossEl.classList.add('hidden');
        }

        preview.classList.remove('hidden');
    }

    // Fetch from CricHeroes URL (primary method)
    const fetchUrlInput = document.getElementById('ch-fetch-url');
    const fetchBtnEl = document.getElementById('ch-fetch-btn');
    if (fetchBtnEl) {
        fetchBtnEl.addEventListener('click', async () => {
            const url = fetchUrlInput.value.trim();
            if (!url) { statusEl.innerHTML = '<span class="text-red-500">Enter a CricHeroes URL</span>'; return; }

            statusEl.innerHTML = '<span class="text-blue-500">Fetching from CricHeroes...</span>';
            fetchBtnEl.disabled = true;
            fetchBtnEl.innerHTML = '<svg class="w-4 h-4 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Fetching...';

            try {
                const res = await fetch('{{ route("admin.matches.result.cricheroes", $match) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ url: url }),
                });
                const data = await res.json();

                if (!data.success) {
                    statusEl.innerHTML = `<span class="text-red-500">${data.message || 'Failed to fetch'}</span>`;
                    return;
                }

                const chData = data.data;

                // Map teams
                let aIdx = null, bIdx = null;
                if (chData.teams && chData.teams.length >= 2) {
                    for (let i = 0; i < chData.teams.length; i++) {
                        if (fuzzy(chData.teams[i].name, teamAName)) aIdx = i;
                        if (fuzzy(chData.teams[i].name, teamBName)) bIdx = i;
                    }
                    if (aIdx === null && bIdx === null) { aIdx = 0; bIdx = 1; }
                    else if (aIdx === null) aIdx = bIdx === 0 ? 1 : 0;
                    else if (bIdx === null) bIdx = aIdx === 0 ? 1 : 0;

                    const ta = chData.teams[aIdx], tb = chData.teams[bIdx];

                    pendingData = {
                        team_a_score: ta.runs, team_a_wickets: ta.wickets, team_a_overs: ta.overs,
                        team_b_score: tb.runs, team_b_wickets: tb.wickets, team_b_overs: tb.overs,
                    };

                    // Toss
                    let tossText = null;
                    if (chData.toss) {
                        const decision = chData.toss.decision;
                        pendingData.toss_won_by = fuzzy(chData.toss.winner, teamAName) ? teamAId : teamBId;
                        pendingData.toss_decision = decision;
                        tossText = `Toss: ${chData.toss.winner} won, elected to ${decision}`;
                    }

                    // Result
                    let resultText = null;
                    if (chData.result && chData.result.winner) {
                        pendingData.winner_team_id = fuzzy(chData.result.winner, teamAName) ? teamAId : teamBId;
                        pendingData.result_type = chData.result.type;
                        pendingData.margin = chData.result.margin;
                        resultText = chData.result.summary || `${chData.result.winner} won by ${chData.result.margin} ${chData.result.type}`;
                    } else if (chData.result && chData.result.type === 'tie') {
                        pendingData.result_type = 'tie';
                        resultText = 'Match Tied';
                    } else if (ta.runs > tb.runs) {
                        pendingData.winner_team_id = teamAId;
                        pendingData.result_type = 'runs';
                        pendingData.margin = ta.runs - tb.runs;
                        resultText = `${teamAName} won by ${ta.runs - tb.runs} runs`;
                    } else if (tb.runs > ta.runs) {
                        pendingData.winner_team_id = teamBId;
                        pendingData.result_type = 'wickets';
                        pendingData.margin = 10 - tb.wickets;
                        resultText = `${teamBName} won by ${10 - tb.wickets} wickets`;
                    }

                    showPreviewFromData(ta, tb, resultText, tossText);
                    statusEl.innerHTML = '<span class="text-green-600">Fetched! Review preview below.</span>';
                } else {
                    statusEl.innerHTML = '<span class="text-red-500">No team data found in CricHeroes response.</span>';
                }
            } catch (err) {
                statusEl.innerHTML = `<span class="text-red-500">Error: ${err.message}</span>`;
            } finally {
                fetchBtnEl.disabled = false;
                fetchBtnEl.innerHTML = '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Fetch';
            }
        });
    }

    importBtn.addEventListener('click', () => {
        const text = document.getElementById('ch-paste-text').value.trim();
        if (!text) { statusEl.innerHTML = '<span class="text-red-500">Paste scorecard text first</span>'; return; }

        statusEl.innerHTML = '<span class="text-blue-500">Parsing...</span>';

        // Normalize whitespace
        const normalized = text.replace(/[^\S\n]+/g, ' ').trim();
        const lines = normalized.split(/\n/).map(l => l.trim()).filter(l => l);

        const scores = [];

        // Strategy 1: Score on same line as team name
        const inlinePattern = /^(.+?)\s+(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?$/i;
        // Strategy 2: Score line alone preceded by team name line
        const scoreOnlyPattern = /^(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?$/i;
        const teamNamePattern = /^[A-Za-z][A-Za-z0-9\s&\-'\.]+$/;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const inlineM = line.match(inlinePattern);
            if (inlineM) {
                const name = inlineM[1].trim();
                if (/toss|won by|opt to|elected/i.test(name)) continue;
                scores.push({ name, runs: parseInt(inlineM[2]), wickets: parseInt(inlineM[3]), overs: parseFloat(inlineM[4]) });
                continue;
            }
            const scoreM = line.match(scoreOnlyPattern);
            if (scoreM && i > 0 && teamNamePattern.test(lines[i-1]) && !/toss|won|opt/i.test(lines[i-1])) {
                scores.push({ name: lines[i-1].trim(), runs: parseInt(scoreM[1]), wickets: parseInt(scoreM[2]), overs: parseFloat(scoreM[3]) });
                continue;
            }
        }

        // Strategy 3: Find all score patterns anywhere in text
        if (scores.length < 2) {
            const globalPattern = /(\d{1,4})\/(\d{1,2})\s*\(?([\d.]+)\s*(?:Ov(?:ers?)?|ov(?:ers?)?)?\)?/g;
            let gm;
            while ((gm = globalPattern.exec(normalized)) !== null) {
                const before = normalized.substring(0, gm.index).trim();
                const nameMatch = before.match(/([A-Za-z][A-Za-z0-9\s&\-'\.]*?)\s*$/);
                if (nameMatch) {
                    let name = nameMatch[1].trim();
                    const nl = name.lastIndexOf('\n');
                    if (nl >= 0) name = name.substring(nl + 1).trim();
                    if (name && !/toss|won by|opt to|elected/i.test(name)) {
                        scores.push({ name, runs: parseInt(gm[1]), wickets: parseInt(gm[2]), overs: parseFloat(gm[3]) });
                    }
                }
            }
        }

        if (scores.length < 2) {
            statusEl.innerHTML = '<span class="text-red-500">Could not parse. Paste text with scores like "Team 156/8 (20.0 Ov)"</span>';
            return;
        }

        const teamScores = scores.slice(0, 2);

        // Map teams
        let aIdx = null, bIdx = null;
        for (let i = 0; i < teamScores.length; i++) {
            if (fuzzy(teamScores[i].name, teamAName)) aIdx = i;
            if (fuzzy(teamScores[i].name, teamBName)) bIdx = i;
        }
        if (aIdx === null && bIdx === null) { aIdx = 0; bIdx = 1; }
        else if (aIdx === null) aIdx = bIdx === 0 ? 1 : 0;
        else if (bIdx === null) bIdx = aIdx === 0 ? 1 : 0;

        const teamA = teamScores[aIdx], teamB = teamScores[bIdx];

        // Build pending data
        pendingData = {
            team_a_score: teamA.runs, team_a_wickets: teamA.wickets, team_a_overs: teamA.overs,
            team_b_score: teamB.runs, team_b_wickets: teamB.wickets, team_b_overs: teamB.overs,
        };

        // Toss - CricHeroes formats: "Toss: Team opt to field" or "Team won the toss and opted to bat"
        let tossM = normalized.match(/Toss\s*:\s*(.+?)\s+(?:opt|elected|chose)\s+to\s+(bat|bowl|field)/i);
        if (!tossM) tossM = normalized.match(/(.+?)\s+won\s+the\s+toss\s+and\s+(?:opted|elected|chose)\s+to\s+(bat|bowl|field)/i);
        if (tossM) {
            const decision = (tossM[2].toLowerCase() === 'field' || tossM[2].toLowerCase() === 'bowl') ? 'bowl' : 'bat';
            pendingData.toss_won_by = fuzzy(tossM[1].trim(), teamAName) ? teamAId : teamBId;
            pendingData.toss_decision = decision;
        }

        // Result
        const resultM = text.match(/(.+?)\s+won\s+by\s+(\d+)\s+(runs?|wickets?)/i);
        if (resultM) {
            pendingData.winner_team_id = fuzzy(resultM[1].trim(), teamAName) ? teamAId : teamBId;
            pendingData.result_type = resultM[3].toLowerCase().startsWith('run') ? 'runs' : 'wickets';
            pendingData.margin = parseInt(resultM[2]);
        } else if (teamA.runs > teamB.runs) {
            pendingData.winner_team_id = teamAId;
            pendingData.result_type = 'runs';
            pendingData.margin = teamA.runs - teamB.runs;
        } else if (teamB.runs > teamA.runs) {
            pendingData.winner_team_id = teamBId;
            pendingData.result_type = 'wickets';
            pendingData.margin = 10 - teamB.wickets;
        } else {
            pendingData.result_type = 'tie';
        }

        // Update preview card
        const resultText = resultM ? `${resultM[1].trim()} won by ${resultM[2]} ${resultM[3]}` : null;
        const tossText = tossM ? `Toss: ${tossM[1].trim()} won, elected to ${tossM[2].toLowerCase()}` : null;
        showPreviewFromData(teamA, teamB, resultText, tossText);
        statusEl.innerHTML = '<span class="text-green-600">Parsed! Review preview below.</span>';
    });

    cancelBtn.addEventListener('click', () => {
        preview.classList.add('hidden');
        pendingData = null;
        statusEl.innerHTML = '';
    });

    saveBtn.addEventListener('click', async () => {
        if (!pendingData) return;

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<svg class="w-4 h-4 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Saving...';

        try {
            const formData = new URLSearchParams(pendingData);
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('_method', 'PUT');

            const res = await fetch('{{ route("admin.matches.result.update", $match) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'text/html' },
                body: formData.toString(),
                redirect: 'follow'
            });

            if (res.ok || res.redirected) {
                showToast('Match result saved from CricHeroes!', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                const text = await res.text();
                throw new Error('Save failed');
            }
        } catch (err) {
            showToast('Failed to save: ' + err.message, 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Save Result';
        }
    });
})();
</script>
@endpush
@endsection
