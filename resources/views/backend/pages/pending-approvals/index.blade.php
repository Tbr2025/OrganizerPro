@extends('backend.layouts.app')

@section('title', 'Pending Profile Approvals')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Players', 'route' => route('admin.players.index')],
    ['name' => 'Pending Approvals']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pending Profile Approvals</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Players who have submitted profile changes awaiting your approval</p>
        </div>
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
            {{ $totalPending }} pending
        </span>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6 border border-gray-100 dark:border-gray-700">
        <form method="GET" action="{{ route('admin.pending-approvals.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px] max-w-xs">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Search Player</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Player name..."
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="min-w-[180px]">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Tournament</label>
                <select name="tournament_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Tournaments</option>
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" {{ (string) request('tournament_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Filter
            </button>
            @if(request()->hasAny(['search', 'tournament_id']))
                <a href="{{ route('admin.pending-approvals.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
        @if($registrations->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Player</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tournament</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Submitted</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden lg:table-cell">Changes</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($registrations as $reg)
                            @php
                                $player = $reg->player;
                                $changes = (array) $reg->pending_changes;
                                $labels = [
                                    'name' => 'Name', 'mobile_number_full' => 'Mobile', 'jersey_name' => 'Jersey Name',
                                    'jersey_number' => 'Jersey #', 'team_name_ref' => 'Team', 'location_id' => 'Location',
                                    'batting_profile_id' => 'Batting', 'bowling_profile_id' => 'Bowling',
                                    'player_type_id' => 'Player Type', 'image_path' => 'Photo',
                                    'tshirt_size' => 'T-Shirt', 'pant_size' => 'Pant',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        @if($player?->image_path)
                                            <img src="{{ asset('storage/' . $player->image_path) }}" alt="" class="w-9 h-9 rounded-full object-cover">
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-sm font-bold text-gray-500">
                                                {{ strtoupper(substr($player?->name ?? '?', 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $player?->name ?? 'Unknown' }}</div>
                                            <div class="text-xs text-gray-500">{{ $player?->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $reg->tournament?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                    @if($reg->pending_changes_submitted_at)
                                        {{ $reg->pending_changes_submitted_at->format('d M Y') }}
                                        <div class="text-xs">{{ $reg->pending_changes_submitted_at->diffForHumans() }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(array_keys($changes) as $field)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                {{ $labels[$field] ?? ucwords(str_replace('_', ' ', $field)) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <a href="{{ route('admin.tournaments.registrations.show', [$reg->tournament_id, $reg->id]) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">All clear!</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No pending profile changes to review.</p>
            </div>
        @endif
    </div>

    @if($registrations->hasPages())
        <div class="mt-4">
            {{ $registrations->links() }}
        </div>
    @endif
</div>
@endsection
