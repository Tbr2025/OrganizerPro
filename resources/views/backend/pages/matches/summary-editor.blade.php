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

            <!-- Teams Score Display -->
            @if($match->result)
                <div class="relative flex items-center justify-between gap-4">
                    <!-- Team A -->
                    <div class="flex-1 text-center team-card p-4 rounded-xl {{ $match->winner_team_id === $match->team_a_id ? 'bg-green-500/20 winner-glow' : 'bg-white/5' }}">
                        @if($match->teamA?->team_logo)
                            <img src="{{ Storage::url($match->teamA->team_logo) }}"
                                 alt="{{ $match->teamA->name }}"
                                 class="w-20 h-20 mx-auto mb-3 rounded-full object-cover border-3 {{ $match->winner_team_id === $match->team_a_id ? 'border-green-400' : 'border-white/30' }}">
                        @else
                            <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gray-600 flex items-center justify-center text-2xl font-bold">
                                {{ substr($match->teamA?->name ?? 'A', 0, 2) }}
                            </div>
                        @endif
                        <h4 class="font-bold text-lg mb-2">{{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'Team A' }}</h4>
                        <div class="text-4xl font-black {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->team_a_score ?? 0 }}/{{ $match->result->team_a_wickets ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-400 mt-1">
                            ({{ number_format($match->result->team_a_overs ?? 0, 1) }} overs)
                        </div>
                        @if($match->result->team_a_extras)
                            <div class="text-xs text-gray-500 mt-1">Extras: {{ $match->result->team_a_extras }}</div>
                        @endif
                        @if($match->winner_team_id === $match->team_a_id)
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

                    <!-- Team B -->
                    <div class="flex-1 text-center team-card p-4 rounded-xl {{ $match->winner_team_id === $match->team_b_id ? 'bg-green-500/20 winner-glow' : 'bg-white/5' }}">
                        @if($match->teamB?->team_logo)
                            <img src="{{ Storage::url($match->teamB->team_logo) }}"
                                 alt="{{ $match->teamB->name }}"
                                 class="w-20 h-20 mx-auto mb-3 rounded-full object-cover border-3 {{ $match->winner_team_id === $match->team_b_id ? 'border-green-400' : 'border-white/30' }}">
                        @else
                            <div class="w-20 h-20 mx-auto mb-3 rounded-full bg-gray-600 flex items-center justify-center text-2xl font-bold">
                                {{ substr($match->teamB?->name ?? 'B', 0, 2) }}
                            </div>
                        @endif
                        <h4 class="font-bold text-lg mb-2">{{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'Team B' }}</h4>
                        <div class="text-4xl font-black {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400' : 'text-white' }}">
                            {{ $match->result->team_b_score ?? 0 }}/{{ $match->result->team_b_wickets ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-400 mt-1">
                            ({{ number_format($match->result->team_b_overs ?? 0, 1) }} overs)
                        </div>
                        @if($match->result->team_b_extras)
                            <div class="text-xs text-gray-500 mt-1">Extras: {{ $match->result->team_b_extras }}</div>
                        @endif
                        @if($match->winner_team_id === $match->team_b_id)
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        @foreach($awards as $award)
                            <div class="relative bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition">
                                <!-- Delete Button -->
                                <form action="{{ route('admin.matches.summary.remove-award', [$match, $award]) }}" method="POST" class="absolute top-2 right-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>

                                <div class="text-center">
                                    <!-- Player Image -->
                                    @if($award->player?->image_path)
                                        <img src="{{ Storage::url($award->player->image_path) }}"
                                             alt="{{ $award->player->name }}"
                                             class="w-16 h-16 mx-auto rounded-full object-cover border-3 border-yellow-400 shadow-md">
                                    @else
                                        <div class="w-16 h-16 mx-auto rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-xl font-bold text-gray-600 dark:text-gray-300">
                                            {{ substr($award->player?->name ?? '?', 0, 1) }}
                                        </div>
                                    @endif

                                    <!-- Award Icon & Name -->
                                    <div class="mt-3">
                                        <span class="text-2xl">{{ $award->tournamentAward?->icon ?? '🏆' }}</span>
                                        <div class="text-sm font-semibold text-yellow-600 dark:text-yellow-400 mt-1">
                                            {{ $award->tournamentAward?->name ?? 'Award' }}
                                        </div>
                                    </div>

                                    <!-- Player Name -->
                                    <div class="mt-2 font-medium text-gray-800 dark:text-gray-200">
                                        {{ $award->player?->name ?? 'Unknown' }}
                                    </div>
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

                <!-- Assign Award Form -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                    <h4 class="font-medium mb-3 text-gray-700 dark:text-gray-300">Assign New Award</h4>

                    @if($tournamentAwards->isEmpty())
                        <!-- No Awards Configured - Show Create Default Awards -->
                        <div class="text-center py-4">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 mb-3">No awards configured for this tournament.</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mb-4">Create default cricket awards to get started quickly.</p>
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
                    @else
                        <!-- Awards Form -->
                        <form action="{{ route('admin.matches.summary.assign-award', $match) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            @csrf

                            <select name="tournament_award_id" required class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select Award</option>
                                @foreach($tournamentAwards as $award)
                                    <option value="{{ $award->id }}">{{ $award->icon ?? '' }} {{ $award->name }}</option>
                                @endforeach
                            </select>

                            <select name="player_id" required class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500">
                                <option value="">Select Player{{ $winnerTeam ? ' (' . $winnerTeam->name . ')' : '' }}</option>
                                @foreach($players as $player)
                                    @if($player)
                                        <option value="{{ $player->id }}">{{ $player->name }}</option>
                                    @endif
                                @endforeach
                            </select>

                            <input type="text"
                                   name="remarks"
                                   placeholder="Remarks (optional)"
                                   class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-yellow-500 focus:border-yellow-500">

                            <button type="submit" class="px-6 py-2 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-xl transition flex items-center justify-center">
                                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Assign
                            </button>
                        </form>

                        <!-- Award Templates Management -->
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h5 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">Customize Award Templates</h5>
                            <div class="flex flex-wrap gap-2">
                                @foreach($tournamentAwards as $award)
                                    <a href="{{ route('admin.awards.template.edit', $award) }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition">
                                        <span class="mr-1">{{ $award->icon ?? '🏆' }}</span>
                                        {{ $award->name }}
                                        <svg class="w-4 h-4 ml-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
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
</script>
@endpush
@endsection
