@extends('backend.layouts.app')

@section('title', 'Scorecard | ' . $match->name)
@section('admin-content')
    <div class="p-4 mx-auto md:p-6 space-y-6">

        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Scorecard']]" />

        <div class="bg-white shadow-lg rounded-2xl p-6 border dark:bg-gray-900 dark:border-gray-700">
            <h2 class="text-3xl font-extrabold mb-6 text-indigo-600">{{ $match->name }} - Scorecard</h2>

            {{-- Match Summary --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-indigo-50 dark:bg-gray-800 p-4 rounded-xl shadow text-center">
                    <p class="text-gray-500 text-sm">Total Runs</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ $totalRuns }}</p>
                </div>
                <div class="bg-red-50 dark:bg-gray-800 p-4 rounded-xl shadow text-center">
                    <p class="text-gray-500 text-sm">Wickets</p>
                    <p class="text-2xl font-bold text-red-600">{{ $totalWickets }}</p>
                </div>
                <div class="bg-green-50 dark:bg-gray-800 p-4 rounded-xl shadow text-center">
                    <p class="text-gray-500 text-sm">Overs</p>
                    <p class="text-2xl font-bold text-green-600">{{ $totalOvers }}</p>
                </div>
            </div>

            {{-- Team A Section --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Batting --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl shadow p-4">
                    <h3 class="text-xl font-bold mb-4 text-indigo-500">{{ $match->teamA->name }} Batting</h3>
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-indigo-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th class="p-2 text-left">Player</th>
                                <th class="p-2">R</th>
                                <th class="p-2">B</th>
                                <th class="p-2">4s</th>
                                <th class="p-2">6s</th>
                                <th class="p-2">SR</th>
                            </tr>
                        </thead>
                                        <tbody>
                        @foreach ($teamAPlayers as $player)
                            @php
                                $ballsForPlayer = $battingStats->get($player->user_id, collect());
                                $runs = $ballsForPlayer->sum('runs') + $ballsForPlayer->sum('extra_runs');
                                $ballsFaced = $ballsForPlayer->count();
                                $fours = $ballsForPlayer->where('runs', 4)->count();
                                $sixes = $ballsForPlayer->where('runs', 6)->count();
                                $displayName = $player->player->name ?? ($player->player->user->name ?? 'Unknown');

                                // Determine if the player is out
                                $isOut = \App\Models\Ball::where('match_id', $match->id)
                                    ->where('batsman_id', $player->user_id)
                                    ->where('is_wicket', 1)
                                    ->exists();

                                // --- Correctly fetch the current striker's user ID from the session ---
                                // IMPORTANT: Ensure the session key matches what you set in your ajaxStore method.
                                $currentStrikerUserId = session()->has('current_striker_id_' . $match->id)
                                    ? session('current_striker_id_' . $match->id)
                                    : null;

                                // Determine if the player is the current striker
                                $isCurrentStriker = ($currentStrikerUserId && $currentStrikerUserId == $player->user_id);

                                // Build the display name with asterisk if striker
                                $playerDisplayName = $displayName;
                                if ($isCurrentStriker) {
                                    $playerDisplayName .= '*';
                                }

                                // Apply conditional styling if the player is out
                                $rowClasses = '';
                                if ($isOut) {
                                    $rowClasses = 'text-gray-400 dark:text-gray-500 italic'; // Example styling for out players
                                }
                            @endphp
                            <tr class="hover:bg-gray-100 dark:hover:bg-gray-700 {{ $rowClasses }}">
                                <td class="p-2 font-medium">
                                    {{ $playerDisplayName }} {{$player->user_id}} {{ $currentStrikerUserId }}
                                </td>
                                <td class="p-2 text-center">{{ $runs }}</td>
                                <td class="p-2 text-center">{{ $ballsFaced }}</td>
                                <td class="p-2 text-center">{{ $fours }}</td>
                                <td class="p-2 text-center">{{ $sixes }}</td>
                                <td class="p-2 text-center">
                                    {{ $ballsFaced > 0 ? round(($runs / $ballsFaced) * 100, 2) : '0.00' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bowling --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl shadow p-4">
                    <h3 class="text-xl font-bold mb-4 text-indigo-500">{{ $match->teamB->name }} Bowling</h3>
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-indigo-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th class="p-2 text-left">Player</th>
                                <th class="p-2">O</th>
                                <th class="p-2">R</th>
                                <th class="p-2">W</th>
                                <th class="p-2">Eco</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teamBPlayers as $player)
                                @php
                                    $ballsBowled = $bowlingStats->get($player->user_id, collect());
                                    $ballsCount = $ballsBowled->count();
                                    $overs = $ballsCount > 0 ? floor($ballsCount / 6) . '.' . $ballsCount % 6 : '0.0';
                                    $runsConceded = $ballsBowled->sum('runs') + $ballsBowled->sum('extra_runs');
                                    $wickets = $ballsBowled->where('is_wicket', true)->count();
                                    $economy = $ballsCount > 0 ? round($runsConceded / ($ballsCount / 6), 2) : 0;
                                @endphp
                                <tr class="hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <td class="p-2 font-medium">{{ $player->player->name }}</td>
                                    <td class="p-2 text-center">{{ $overs }}</td>
                                    <td class="p-2 text-center">{{ $runsConceded }}</td>
                                    <td class="p-2 text-center">{{ $wickets }}</td>
                                    <td class="p-2 text-center">{{ $economy }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Match Awards --}}
            @if($matchAwards->count() > 0)
            <h3 class="text-xl font-semibold mt-10 text-yellow-500">Match Awards</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                @foreach ($matchAwards as $award)
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border flex items-center gap-4">
                        @if($award->player?->image_path)
                            <img src="{{ asset('storage/' . $award->player->image_path) }}" alt="{{ $award->player->name }}" class="w-12 h-12 rounded-full object-cover">
                        @else
                            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        @endif
                        <div>
                            <h4 class="text-sm font-bold uppercase text-indigo-600">{{ $award->tournamentAward->name ?? 'Award' }}</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $award->player->name ?? 'Unknown' }}
                                @if($award->player?->actualTeam)
                                    ({{ $award->player->actualTeam->name }})
                                @endif
                            </p>
                            @if($award->remarks)
                                <p class="text-xs text-gray-500">{{ $award->remarks }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
@endsection
