@extends('backend.layouts.app')

@section('title', 'Track Profiles')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Track Profiles']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Track Profiles</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Audit trail for player profile changes</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-100 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Submitted</div>
            <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $submittedCount }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-100 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Approved</div>
            <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $approvedCount }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-100 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rejected</div>
            <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ $rejectedCount }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-100 dark:border-gray-700">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</div>
            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalCount }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6 border border-gray-100 dark:border-gray-700">
        <form method="GET" action="{{ route('admin.profile-tracking.index') }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Search Player</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Player name..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Action</label>
                    <select name="action" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Actions</option>
                        <option value="submitted" {{ request('action') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="approved" {{ request('action') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('action') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="admin_edit" {{ request('action') === 'admin_edit' ? 'selected' : '' }}>Admin Edit</option>
                        <option value="verified" {{ request('action') === 'verified' ? 'selected' : '' }}>Verified</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Tournament</label>
                    <select name="tournament_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Tournaments</option>
                        @foreach($tournaments as $t)
                            <option value="{{ $t->id }}" {{ (string) request('tournament_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Filter
                </button>
                @if(request()->hasAny(['search', 'action', 'tournament_id', 'date_from', 'date_to']))
                    <a href="{{ route('admin.profile-tracking.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Clear filters</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border border-gray-100 dark:border-gray-700">
        @if($logs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Player</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden md:table-cell">Tournament</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden lg:table-cell">Changed By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase hidden lg:table-cell">Changes</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" x-data="{ expanded: false }">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $log->created_at->format('d M Y') }}
                                    <div class="text-xs">{{ $log->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($log->player?->image_path)
                                            <img src="{{ asset('storage/' . $log->player->image_path) }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-500">
                                                {{ strtoupper(substr($log->player?->name ?? '?', 0, 1)) }}
                                            </div>
                                        @endif
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $log->player?->name ?? 'Deleted' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                    {{ $log->tournament?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @php
                                        $actionColors = [
                                            'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                            'admin_edit' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                            'verified' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $actionColors[$log->action] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 hidden lg:table-cell">
                                    {{ $log->changedBy?->name ?? 'System' }}
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    @if($log->changes && count($log->changes) > 0)
                                        <button @click="expanded = !expanded" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                                            <span x-text="expanded ? 'Hide' : '{{ count($log->changes) }} field(s)'"></span>
                                        </button>
                                        <div x-show="expanded" x-cloak class="mt-2 text-xs space-y-1 max-w-xs">
                                            @foreach($log->changes as $field => $value)
                                                <div class="flex gap-2">
                                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ ucwords(str_replace('_', ' ', $field)) }}:</span>
                                                    <span class="text-gray-500 dark:text-gray-400 truncate">{{ is_array($value) ? json_encode($value) : Str::limit((string) $value, 40) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    @if($log->player)
                                        <a href="{{ route('admin.players.show', $log->player) }}"
                                           class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            View
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No profile changes logged</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Profile change logs will appear here as players submit or admins edit profiles.</p>
            </div>
        @endif
    </div>

    @if($logs->hasPages())
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
