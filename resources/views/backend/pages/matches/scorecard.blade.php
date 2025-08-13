@extends('backend.layouts.app')

@section('title', 'Scorecard | ' . $match->name)

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6 space-y-6">

        <x-breadcrumbs :breadcrumbs="[['label' => 'Matches', 'url' => route('admin.matches.index')], ['label' => 'Scorecard']]" />

        <div class="bg-white shadow rounded-lg p-6 border dark:bg-gray-900 dark:border-gray-700">
            <h2 class="text-2xl font-bold mb-4 text-indigo-600">Scorecard: {{ $match->name }}</h2>

            {{-- Team A Batting --}}
            <h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800 dark:text-white">{{ $match->teamA->name }} Batting</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-2">Player</th>
                            <th class="p-2">Runs</th>
                            <th class="p-2">Balls</th>
                            <th class="p-2">Fours</th>
                            <th class="p-2">Sixes</th>
                            <th class="p-2">Strike Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamAPlayers as $player)
                            <tr class="text-center border-b dark:border-gray-700">
                                <td class="p-2 font-medium text-left">{{ $player->name }}</td>
                                <td class="p-2">{{ $player->runs }}</td>
                                <td class="p-2">{{ $player->balls_faced }}</td>
                                <td class="p-2">{{ $player->fours }}</td>
                                <td class="p-2">{{ $player->sixes }}</td>
                                <td class="p-2">
                                    {{ $player->balls_faced > 0 ? round(($player->runs / $player->balls_faced) * 100, 2) : '0.00' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Team A Bowling --}}
            <h3 class="text-xl font-semibold mt-6 mb-2 text-gray-800 dark:text-white">{{ $match->teamA->name }} Bowling</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-auto border text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="p-2">Player</th>
                            <th class="p-2">Overs</th>
                            <th class="p-2">Runs</th>
                            <th class="p-2">Wickets</th>
                            <th class="p-2">Economy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamAPlayers as $player)
                            <tr class="text-center border-b dark:border-gray-700">
                                <td class="p-2 font-medium text-left">{{ $player->name }}</td>
                                <td class="p-2">{{ $player->overs_bowled }}</td>
                                <td class="p-2">{{ $player->runs_conceded }}</td>
                                <td class="p-2">{{ $player->wickets }}</td>
                                <td class="p-2">
                                    {{ $player->overs_bowled > 0 ? round($player->runs_conceded / $player->overs_bowled, 2) : '0.00' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Repeat above for Team B Batting & Bowling --}}
            {{-- Appreciation Section --}}
            <h3 class="text-xl font-semibold mt-10 text-yellow-500">Appreciations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                @foreach ($appreciations as $type => $items)
                    <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded shadow-sm">
                        <h4 class="text-lg font-bold uppercase mb-2 text-indigo-600">{{ str_replace('_', ' ', $type) }}
                        </h4>
                        @foreach ($items as $item)
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $item->player->name }}
                                ({{ $item->player->team->name }})</p>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
