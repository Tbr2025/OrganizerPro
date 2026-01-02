@extends('public.tournament.layouts.app')

@section('title', 'Scorecard - ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'))

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6 text-center">Full Scorecard</h1>

        {{-- Match Header --}}
        <div class="bg-gray-800 rounded-xl p-4 mb-6 border border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($match->teamA?->logo)
                        <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-10 w-10 object-contain">
                    @endif
                    <span class="font-bold {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                        {{ $match->teamA?->short_name ?? 'TBA' }}
                    </span>
                    @if($match->result)
                        <span class="text-gray-400">
                            {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                            ({{ $match->result->team_a_overs }})
                        </span>
                    @endif
                </div>
                <span class="text-gray-500">vs</span>
                <div class="flex items-center gap-3">
                    @if($match->result)
                        <span class="text-gray-400">
                            {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                            ({{ $match->result->team_b_overs }})
                        </span>
                    @endif
                    <span class="font-bold {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                        {{ $match->teamB?->short_name ?? 'TBA' }}
                    </span>
                    @if($match->teamB?->logo)
                        <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-10 w-10 object-contain">
                    @endif
                </div>
            </div>
            @if($match->result?->result_summary)
                <p class="text-center text-yellow-400 text-sm mt-3">{{ $match->result->result_summary }}</p>
            @endif
        </div>

        {{-- First Innings --}}
        @php
            $firstBattingTeam = $match->result?->team_a_batting_first ? $match->teamA : $match->teamB;
            $firstBattingScore = $match->result?->team_a_batting_first
                ? "{$match->result->team_a_score}/{$match->result->team_a_wickets} ({$match->result->team_a_overs} ov)"
                : "{$match->result->team_b_score}/{$match->result->team_b_wickets} ({$match->result->team_b_overs} ov)";
        @endphp

        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-6">
            <div class="bg-gray-700 px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    @if($firstBattingTeam?->logo)
                        <img src="{{ Storage::url($firstBattingTeam->logo) }}" alt="{{ $firstBattingTeam->name }}" class="h-8 w-8 object-contain">
                    @endif
                    <h2 class="font-semibold">{{ $firstBattingTeam?->name ?? 'Team A' }} - 1st Innings</h2>
                </div>
                <span class="text-yellow-400 font-bold">{{ $firstBattingScore }}</span>
            </div>

            {{-- Batting Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-750 text-sm">
                        <tr>
                            <th class="px-4 py-2 text-left">Batter</th>
                            <th class="px-4 py-2 text-left">Dismissal</th>
                            <th class="px-4 py-2 text-center">R</th>
                            <th class="px-4 py-2 text-center">B</th>
                            <th class="px-4 py-2 text-center">4s</th>
                            <th class="px-4 py-2 text-center">6s</th>
                            <th class="px-4 py-2 text-center">SR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm">
                        {{-- Placeholder for batting data --}}
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-info-circle mr-2"></i>
                                Detailed batting scorecard will be available when ball-by-ball data is entered.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Bowling Table --}}
            <div class="border-t border-gray-700">
                <div class="bg-gray-750 px-4 py-2">
                    <h3 class="text-sm font-medium text-gray-400">Bowling</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 text-sm">
                            <tr>
                                <th class="px-4 py-2 text-left">Bowler</th>
                                <th class="px-4 py-2 text-center">O</th>
                                <th class="px-4 py-2 text-center">M</th>
                                <th class="px-4 py-2 text-center">R</th>
                                <th class="px-4 py-2 text-center">W</th>
                                <th class="px-4 py-2 text-center">Econ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 text-sm">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Detailed bowling figures will be available when ball-by-ball data is entered.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Second Innings --}}
        @php
            $secondBattingTeam = $match->result?->team_a_batting_first ? $match->teamB : $match->teamA;
            $secondBattingScore = $match->result?->team_a_batting_first
                ? "{$match->result->team_b_score}/{$match->result->team_b_wickets} ({$match->result->team_b_overs} ov)"
                : "{$match->result->team_a_score}/{$match->result->team_a_wickets} ({$match->result->team_a_overs} ov)";
        @endphp

        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-6">
            <div class="bg-gray-700 px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    @if($secondBattingTeam?->logo)
                        <img src="{{ Storage::url($secondBattingTeam->logo) }}" alt="{{ $secondBattingTeam->name }}" class="h-8 w-8 object-contain">
                    @endif
                    <h2 class="font-semibold">{{ $secondBattingTeam?->name ?? 'Team B' }} - 2nd Innings</h2>
                </div>
                <span class="text-yellow-400 font-bold">{{ $secondBattingScore }}</span>
            </div>

            {{-- Batting Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-750 text-sm">
                        <tr>
                            <th class="px-4 py-2 text-left">Batter</th>
                            <th class="px-4 py-2 text-left">Dismissal</th>
                            <th class="px-4 py-2 text-center">R</th>
                            <th class="px-4 py-2 text-center">B</th>
                            <th class="px-4 py-2 text-center">4s</th>
                            <th class="px-4 py-2 text-center">6s</th>
                            <th class="px-4 py-2 text-center">SR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-sm">
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-info-circle mr-2"></i>
                                Detailed batting scorecard will be available when ball-by-ball data is entered.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Bowling Table --}}
            <div class="border-t border-gray-700">
                <div class="bg-gray-750 px-4 py-2">
                    <h3 class="text-sm font-medium text-gray-400">Bowling</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 text-sm">
                            <tr>
                                <th class="px-4 py-2 text-left">Bowler</th>
                                <th class="px-4 py-2 text-center">O</th>
                                <th class="px-4 py-2 text-center">M</th>
                                <th class="px-4 py-2 text-center">R</th>
                                <th class="px-4 py-2 text-center">W</th>
                                <th class="px-4 py-2 text-center">Econ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 text-sm">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Detailed bowling figures will be available when ball-by-ball data is entered.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Fall of Wickets --}}
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 mb-6">
            <h3 class="font-semibold mb-3">Fall of Wickets</h3>
            <p class="text-gray-500 text-sm text-center py-4">
                Fall of wickets data will be available when ball-by-ball scoring is enabled.
            </p>
        </div>

        {{-- Back Button --}}
        <div class="text-center">
            <a href="{{ route('public.match.show', $match->slug) }}"
               class="inline-block px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Match
            </a>
        </div>
    </div>
@endsection
