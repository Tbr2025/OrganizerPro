{{-- Read-only detailed scorecard (from imported PDF / scorecard_data) --}}
@php
    $sc = $match->result?->scorecard_data;
    $innings = is_array($sc) ? ($sc['innings'] ?? $sc) : [];
    $innings = (is_array($innings) && isset($innings[0])) ? $innings : [];
@endphp

@if(!empty($innings))
<div class="card rounded-2xl overflow-hidden mb-6">
    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-6 py-4 flex items-center justify-between">
        <h3 class="text-white font-bold text-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Imported Scorecard
        </h3>
        <span class="text-xs text-white/80">read-only · from uploaded PDF</span>
    </div>
    <div class="p-4 sm:p-6 space-y-8">
        @foreach($innings as $i => $inn)
            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <h4 class="font-bold text-gray-900 dark:text-white">
                        {{ $inn['team_name'] ?? ('Innings ' . ($i + 1)) }}
                        <span class="ml-2 text-indigo-600 dark:text-indigo-400">{{ $inn['total_runs'] ?? 0 }}/{{ $inn['total_wickets'] ?? 0 }}</span>
                        <span class="text-sm font-normal text-gray-500">({{ $inn['overs_played'] ?? '0' }} ov)</span>
                    </h4>
                    <span class="text-xs text-gray-500">Innings {{ $i + 1 }}</span>
                </div>

                {{-- Batting --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Batter</th>
                                <th class="px-3 py-2 text-left font-semibold">Dismissal</th>
                                <th class="px-3 py-2 text-right font-semibold">R</th>
                                <th class="px-3 py-2 text-right font-semibold">B</th>
                                <th class="px-3 py-2 text-right font-semibold">4s</th>
                                <th class="px-3 py-2 text-right font-semibold">6s</th>
                                <th class="px-3 py-2 text-right font-semibold">SR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($inn['batting'] ?? [] as $b)
                                <tr class="text-gray-800 dark:text-gray-200">
                                    <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $b['name'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $b['how_out'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-right font-bold">{{ $b['runs'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right">{{ $b['balls'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right">{{ $b['fours'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right">{{ $b['sixes'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ $b['strike_rate'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 dark:bg-gray-800/60 font-semibold text-gray-700 dark:text-gray-300">
                                <td class="px-3 py-2" colspan="2">Extras {{ !empty($inn['extras_summary']) ? '(' . $inn['extras_summary'] . ')' : '' }}</td>
                                <td class="px-3 py-2 text-right">{{ $inn['total_extras'] ?? 0 }}</td>
                                <td class="px-3 py-2" colspan="4"></td>
                            </tr>
                            <tr class="bg-indigo-50 dark:bg-indigo-900/20 font-bold text-gray-900 dark:text-white">
                                <td class="px-3 py-2" colspan="2">Total · {{ $inn['overs_played'] ?? '0' }} ov</td>
                                <td class="px-3 py-2 text-right">{{ $inn['total_runs'] ?? 0 }}/{{ $inn['total_wickets'] ?? 0 }}</td>
                                <td class="px-3 py-2" colspan="4"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if(!empty($inn['did_not_bat']))
                    <p class="text-xs text-gray-500 mt-2"><span class="font-semibold">Did not bat:</span> {{ implode(', ', $inn['did_not_bat']) }}</p>
                @endif

                {{-- Bowling --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 mt-3">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Bowler</th>
                                <th class="px-3 py-2 text-right font-semibold">O</th>
                                <th class="px-3 py-2 text-right font-semibold">M</th>
                                <th class="px-3 py-2 text-right font-semibold">R</th>
                                <th class="px-3 py-2 text-right font-semibold">W</th>
                                <th class="px-3 py-2 text-right font-semibold">Econ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($inn['bowling'] ?? [] as $bw)
                                <tr class="text-gray-800 dark:text-gray-200">
                                    <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $bw['name'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $bw['overs'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $bw['maidens'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right">{{ $bw['runs'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right font-bold">{{ $bw['wickets'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ $bw['economy'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($inn['fall_of_wickets']))
                    <p class="text-xs text-gray-500 mt-2">
                        <span class="font-semibold">Fall of wickets:</span>
                        @foreach($inn['fall_of_wickets'] as $fw)
                            {{ $fw['runs'] ?? '' }}-{{ $fw['wicket'] ?? '' }} ({{ $fw['player_name'] ?? '' }}, {{ $fw['over'] ?? '' }}){{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
