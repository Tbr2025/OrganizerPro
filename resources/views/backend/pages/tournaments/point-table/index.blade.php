@extends('backend.layouts.app')

@section('title', 'Point Table | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Point Table']
]" />

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Point Table</h1>
            <p class="text-gray-500">Tournament standings and team rankings</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('admin.tournaments.point-table.initialize', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm">
                    Initialize Table
                </button>
            </form>
            <form action="{{ route('admin.tournaments.point-table.recalculate', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-sm">
                    Recalculate
                </button>
            </form>
            <form action="{{ route('admin.tournaments.point-table.generate-poster', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                    Generate Poster
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-100 text-red-700 rounded-lg">{{ session('error') }}</div>
    @endif

    <!-- Point Tables by Group -->
    @if($groups->count() > 0)
        @foreach($groups as $group)
            @php
                $groupEntries = $pointTableByGroups[$group->id] ?? collect();
            @endphp
            <div class="card overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-4 py-3 flex items-center justify-between">
                    <h3 class="text-white font-bold">{{ $group->name }}</h3>
                    <form action="{{ route('admin.tournaments.point-table.generate-poster', $tournament) }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="group_id" value="{{ $group->id }}">
                        <button type="submit" class="text-white/80 hover:text-white text-sm">
                            Generate Poster
                        </button>
                    </form>
                </div>

                @if($groupEntries->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">P</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">W</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">L</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">T</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">NR</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">NRR</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Pts</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qualified</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700">
                                @foreach($groupEntries as $index => $entry)
                                    <tr class="{{ $entry->qualified ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                @if($entry->team?->team_logo)
                                                    <img src="{{ Storage::url($entry->team->team_logo) }}" alt="{{ $entry->team->name }}" class="w-8 h-8 rounded-full mr-2 object-cover">
                                                @else
                                                    <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-600 mr-2 flex items-center justify-center text-xs font-bold">
                                                        {{ strtoupper(substr($entry->team?->name ?? 'T', 0, 2)) }}
                                                    </div>
                                                @endif
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $entry->team?->name ?? 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm">{{ $entry->played }}</td>
                                        <td class="px-4 py-3 text-center text-sm text-green-600 font-medium">{{ $entry->won }}</td>
                                        <td class="px-4 py-3 text-center text-sm text-red-600 font-medium">{{ $entry->lost }}</td>
                                        <td class="px-4 py-3 text-center text-sm">{{ $entry->tied }}</td>
                                        <td class="px-4 py-3 text-center text-sm">{{ $entry->no_result }}</td>
                                        <td class="px-4 py-3 text-center text-sm {{ $entry->net_run_rate >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $entry->net_run_rate >= 0 ? '+' : '' }}{{ number_format($entry->net_run_rate, 3) }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm font-bold text-gray-900 dark:text-white">{{ $entry->points }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($entry->qualified)
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Yes
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-gray-500">
                        <p>No entries for this group. Initialize the point table or add teams to the group.</p>
                    </div>
                @endif
            </div>
        @endforeach

        <!-- Update Qualified Teams -->
        <div class="card p-6">
            <h3 class="font-bold text-gray-900 dark:text-white mb-4">Mark Qualified Teams</h3>
            <form action="{{ route('admin.tournaments.point-table.qualified', $tournament) }}" method="POST">
                @csrf
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-4">
                    @foreach($groups as $group)
                        @php $groupEntries = $pointTableByGroups[$group->id] ?? collect(); @endphp
                        @foreach($groupEntries as $entry)
                            @if($entry->team)
                                <label class="flex items-center p-2 bg-gray-50 dark:bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <input type="checkbox" name="qualified_team_ids[]" value="{{ $entry->actual_team_id }}"
                                           {{ $entry->qualified ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <span class="ml-2 text-sm">{{ $entry->team->name }}</span>
                                </label>
                            @endif
                        @endforeach
                    @endforeach
                </div>
                <button type="submit" class="btn-primary">
                    Update Qualified Teams
                </button>
            </form>
        </div>
    @else
        <div class="card p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No groups created</h3>
            <p class="text-gray-500 mb-4">Create groups and add teams first to see the point table.</p>
            <a href="{{ route('admin.tournaments.groups.index', $tournament) }}" class="btn-primary">
                Go to Groups
            </a>
        </div>
    @endif

    <!-- Legend -->
    <div class="card p-4">
        <h4 class="font-medium text-gray-900 dark:text-white mb-2">Legend</h4>
        <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
            <span><strong>P</strong> = Played</span>
            <span><strong>W</strong> = Won</span>
            <span><strong>L</strong> = Lost</span>
            <span><strong>T</strong> = Tied</span>
            <span><strong>NR</strong> = No Result</span>
            <span><strong>NRR</strong> = Net Run Rate</span>
            <span><strong>Pts</strong> = Points</span>
        </div>
    </div>
</div>
@endsection
