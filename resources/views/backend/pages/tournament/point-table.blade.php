@extends('backend.layouts.app')

@section('title', 'Point Table | ' . $tournament->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Point Table</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tournament->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('public.tournament.point-table', $tournament->slug) }}" target="_blank"
               class="btn btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                Public View
            </a>
            <form action="{{ route('admin.tournaments.point-table.recalculate', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Recalculate
                </button>
            </form>
        </div>
    </div>

    {{-- Point Tables by Group --}}
    @forelse($pointTableByGroups as $groupName => $entries)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-8 overflow-hidden">
            @if($groupName !== 'default')
                <div class="p-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $groupName }}</h2>
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Team</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">P</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">W</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">L</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">T</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NR</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">RF</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">OF</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">RA</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">OA</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">NRR</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($entries as $index => $entry)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $index < 2 ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                <td class="px-4 py-3 text-sm">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($entry->team?->logo)
                                            <img src="{{ Storage::url($entry->team->logo) }}" alt="{{ $entry->team->name }}" class="h-8 w-8 object-contain rounded">
                                        @else
                                            <div class="h-8 w-8 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                                                <span class="text-xs font-bold">{{ substr($entry->team?->short_name ?? 'T', 0, 2) }}</span>
                                            </div>
                                        @endif
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $entry->team?->name ?? 'Unknown' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center text-sm">{{ $entry->played }}</td>
                                <td class="px-4 py-3 text-center text-sm text-green-600 dark:text-green-400 font-medium">{{ $entry->won }}</td>
                                <td class="px-4 py-3 text-center text-sm text-red-600 dark:text-red-400">{{ $entry->lost }}</td>
                                <td class="px-4 py-3 text-center text-sm">{{ $entry->tied }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ $entry->no_result }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ $entry->runs_for }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ number_format($entry->overs_faced, 1) }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ $entry->runs_against }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ number_format($entry->overs_bowled, 1) }}</td>
                                <td class="px-4 py-3 text-center text-sm {{ $entry->nrr >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $entry->nrr >= 0 ? '+' : '' }}{{ number_format($entry->nrr, 3) }}
                                </td>
                                <td class="px-4 py-3 text-center text-sm font-bold text-blue-600 dark:text-blue-400">{{ $entry->points }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Point Table Data</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">
                Point table will be generated automatically when teams are assigned to groups and matches are completed.
            </p>
            <a href="{{ route('admin.tournaments.groups', $tournament) }}" class="btn btn-primary">
                Manage Groups
            </a>
        </div>
    @endforelse

    {{-- Legend --}}
    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        <p class="mb-2"><strong>Legend:</strong></p>
        <div class="flex flex-wrap gap-4">
            <span>P = Played</span>
            <span>W = Won</span>
            <span>L = Lost</span>
            <span>T = Tied</span>
            <span>NR = No Result</span>
            <span>RF = Runs For</span>
            <span>OF = Overs Faced</span>
            <span>RA = Runs Against</span>
            <span>OA = Overs Against</span>
            <span>NRR = Net Run Rate</span>
            <span>Pts = Points</span>
        </div>
        <p class="mt-2">
            <span class="inline-block w-4 h-4 bg-green-50 dark:bg-green-900/20 rounded mr-1"></span>
            Qualified for next stage
        </p>
    </div>
</div>
@endsection
