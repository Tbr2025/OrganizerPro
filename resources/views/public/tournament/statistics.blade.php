@extends('public.tournament.layouts.app')

@section('title', 'Statistics - ' . $tournament->name)

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Tournament Statistics</h1>

        {{-- Tabs --}}
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'batting']) }}"
               class="px-6 py-2 rounded-lg font-medium transition {{ $tab === 'batting' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-800 hover:bg-gray-700' }}">
                Batting
            </a>
            <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'bowling']) }}"
               class="px-6 py-2 rounded-lg font-medium transition {{ $tab === 'bowling' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-800 hover:bg-gray-700' }}">
                Bowling
            </a>
            <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'sixes']) }}"
               class="px-6 py-2 rounded-lg font-medium transition {{ $tab === 'sixes' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-800 hover:bg-gray-700' }}">
                Most Sixes
            </a>
            <a href="{{ route('public.tournament.statistics', [$tournament->slug, 'tab' => 'fielding']) }}"
               class="px-6 py-2 rounded-lg font-medium transition {{ $tab === 'fielding' ? 'bg-yellow-500 text-gray-900' : 'bg-gray-800 hover:bg-gray-700' }}">
                Fielding
            </a>
        </div>

        {{-- Batting Stats --}}
        @if($tab === 'batting')
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Top Run Scorers</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Player</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Team</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">M</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Runs</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">HS</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Avg</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">SR</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">50s</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">100s</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($topBatsmen as $index => $stat)
                                <tr class="hover:bg-gray-700/50 {{ $index === 0 ? 'bg-yellow-900/20' : '' }}">
                                    <td class="px-4 py-3">
                                        @if($index === 0)
                                            <span class="text-yellow-400"><i class="fas fa-trophy"></i></span>
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $stat->player?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $stat->team?->short_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->matches }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-yellow-400">{{ $stat->runs }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->highest_score }}{{ $stat->highest_not_out ? '*' : '' }}</td>
                                    <td class="px-4 py-3 text-center">{{ number_format($stat->batting_average, 2) }}</td>
                                    <td class="px-4 py-3 text-center">{{ number_format($stat->strike_rate, 2) }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->fifties }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->hundreds }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                                        No batting statistics available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Bowling Stats --}}
        @if($tab === 'bowling')
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Top Wicket Takers</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Player</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Team</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">M</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Wkts</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">BB</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Avg</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Econ</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">3W</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">5W</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($topBowlers as $index => $stat)
                                <tr class="hover:bg-gray-700/50 {{ $index === 0 ? 'bg-yellow-900/20' : '' }}">
                                    <td class="px-4 py-3">
                                        @if($index === 0)
                                            <span class="text-yellow-400"><i class="fas fa-trophy"></i></span>
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $stat->player?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $stat->team?->short_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->matches }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-yellow-400">{{ $stat->wickets }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->best_bowling ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ number_format($stat->bowling_average, 2) }}</td>
                                    <td class="px-4 py-3 text-center">{{ number_format($stat->economy, 2) }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->three_wickets }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->five_wickets }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                                        No bowling statistics available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Most Sixes --}}
        @if($tab === 'sixes')
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Most Sixes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Player</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Team</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Matches</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Sixes</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Fours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($topSixHitters as $index => $stat)
                                <tr class="hover:bg-gray-700/50 {{ $index === 0 ? 'bg-yellow-900/20' : '' }}">
                                    <td class="px-4 py-3">
                                        @if($index === 0)
                                            <span class="text-yellow-400"><i class="fas fa-trophy"></i></span>
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $stat->player?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $stat->team?->short_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->matches }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-yellow-400">{{ $stat->sixes }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->fours }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                        No data available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Fielding Stats --}}
        @if($tab === 'fielding')
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Top Fielders</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-750 border-b border-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Player</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold">Team</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Catches</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Stumpings</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Run Outs</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @forelse($topFielders as $index => $stat)
                                <tr class="hover:bg-gray-700/50 {{ $index === 0 ? 'bg-yellow-900/20' : '' }}">
                                    <td class="px-4 py-3">
                                        @if($index === 0)
                                            <span class="text-yellow-400"><i class="fas fa-trophy"></i></span>
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $stat->player?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3 text-gray-400">{{ $stat->team?->short_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->catches }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->stumpings }}</td>
                                    <td class="px-4 py-3 text-center">{{ $stat->run_outs }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-yellow-400">
                                        {{ $stat->catches + $stat->stumpings + $stat->run_outs }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                        No fielding statistics available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
