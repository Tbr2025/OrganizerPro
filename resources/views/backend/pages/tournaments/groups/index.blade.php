@extends('backend.layouts.app')

@section('title', 'Groups & Teams | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Groups & Teams']
]" />

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Groups & Teams</h1>
            <p class="text-gray-500">Manage tournament groups and team assignments</p>
        </div>
        <div class="flex gap-2">
            <form action="{{ route('admin.tournaments.groups.auto-create', $tournament) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Auto-Create Groups
                </button>
            </form>
            <button type="button" onclick="document.getElementById('newGroupModal').classList.remove('hidden')"
                    class="btn-primary">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Add Group
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-100 text-red-700 rounded-lg">{{ session('error') }}</div>
    @endif

    <!-- Groups Grid -->
    @if($groups->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($groups as $group)
                <div class="card rounded-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-3 flex items-center justify-between">
                        <h3 class="text-white font-bold">{{ $group->name }}</h3>
                        <span class="text-white/80 text-sm">{{ $group->teams->count() }} Teams</span>
                    </div>
                    <div class="p-4">
                        @if($group->teams->count() > 0)
                            <ul class="space-y-2">
                                @foreach($group->teams as $team)
                                    <li class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <div class="flex items-center">
                                            @if($team->logo)
                                                <img src="{{ Storage::url($team->logo) }}" alt="{{ $team->name }}" class="w-8 h-8 rounded-full mr-2 object-cover">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-600 mr-2 flex items-center justify-center text-xs font-bold">
                                                    {{ strtoupper(substr($team->name, 0, 2)) }}
                                                </div>
                                            @endif
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $team->name }}</span>
                                        </div>
                                        <form action="{{ route('admin.tournaments.groups.remove-team', [$tournament, $group, $team]) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Remove this team from the group?')" class="text-red-500 hover:text-red-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-gray-500 text-sm text-center py-4">No teams in this group</p>
                        @endif

                        <!-- Add Team Form -->
                        @if($availableTeams->count() > 0)
                            <form action="{{ route('admin.tournaments.groups.add-team', [$tournament, $group]) }}" method="POST" class="mt-4">
                                @csrf
                                <div class="flex gap-2">
                                    <select name="actual_team_id" class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                                        <option value="">Select team...</option>
                                        @foreach($availableTeams as $team)
                                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm">
                                        Add
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                    <div class="border-t dark:border-gray-700 px-4 py-2 flex justify-end">
                        <form action="{{ route('admin.tournaments.groups.destroy', [$tournament, $group]) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Delete this group?')" class="text-sm text-red-500 hover:text-red-700">
                                Delete Group
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No groups created</h3>
            <p class="text-gray-500 mb-4">Create groups to organize teams for the tournament.</p>
        </div>
    @endif
</div>

<!-- New Group Modal -->
<div id="newGroupModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50" onclick="document.getElementById('newGroupModal').classList.add('hidden')"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Create New Group</h3>
            <form action="{{ route('admin.tournaments.groups.store', $tournament) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Group Name</label>
                    <input type="text" name="name" required placeholder="e.g., Pool A"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('newGroupModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 btn-primary">
                        Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
