@extends('backend.layouts.app')

@section('title', 'Groups & Teams | ' . $tournament->name)

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Tournaments', 'route' => route('admin.tournaments.index')],
    ['name' => $tournament->name, 'route' => route('admin.tournaments.dashboard', $tournament)],
    ['name' => 'Groups & Teams']
]" />

<div class="space-y-8">
    @if(session('success'))
        <div class="p-4 bg-green-100 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-100 text-red-700 rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- ============================================================ --}}
    {{-- SECTION 1: Registered Teams (from public registration)       --}}
    {{-- ============================================================ --}}
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Registered Teams
                    <span class="text-sm font-normal text-gray-500">({{ $allTeams->count() }})</span>
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Teams approved from public registration</p>
            </div>
        </div>

        @if($allTeams->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Team</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Manager</th>
                            <th class="px-4 py-3">Group</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            @if($canEdit)
                                <th class="px-4 py-3 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($allTeams as $team)
                            @php
                                $owner = $team->users->first(fn($u) => $u->pivot->role === 'Owner');
                                $manager = $team->users->first(fn($u) => $u->pivot->role === 'Manager');
                                $assignedGroup = $team->groups()->where('tournament_id', $tournament->id)->first();
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($team->team_logo)
                                            <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}" class="w-9 h-9 rounded-lg object-cover">
                                        @else
                                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                                                {{ strtoupper(substr($team->name, 0, 2)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $team->name }}</p>
                                            @if($team->short_name)
                                                <p class="text-xs text-gray-400">{{ $team->short_name }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($owner)
                                        <div>
                                            <p class="text-gray-900 dark:text-white">{{ $owner->name }}</p>
                                            <p class="text-xs text-gray-400">{{ $owner->email }}</p>
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($manager)
                                        <div>
                                            <p class="text-gray-900 dark:text-white">{{ $manager->name }}</p>
                                            <p class="text-xs text-gray-400">{{ $manager->email }}</p>
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($assignedGroup)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            {{ $assignedGroup->name }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                            Unassigned
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Approved
                                    </span>
                                </td>
                                @if($canEdit)
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.actual-teams.show', $team) }}"
                                           class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50 rounded-lg transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            Manage
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">No registered teams yet</h3>
                <p class="text-sm text-gray-500 mt-1">Teams will appear here once registrations are approved.</p>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 2: Tournament Groups (internal organization)         --}}
    {{-- ============================================================ --}}
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    Tournament Groups
                    <span class="text-sm font-normal text-gray-500">({{ $groups->count() }})</span>
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Organize registered teams into groups for fixtures</p>
            </div>
            @if($canEdit)
                <div class="flex gap-2">
                    <form action="{{ route('admin.tournaments.groups.auto-create', $tournament) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm">
                            Auto-Create Groups
                        </button>
                    </form>
                    <button type="button" onclick="document.getElementById('newGroupModal').classList.remove('hidden')"
                            class="btn-primary text-sm">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Group
                    </button>
                </div>
            @endif
        </div>

        @if($groups->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($groups as $group)
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-4 py-3 flex items-center justify-between">
                            <h3 class="text-white font-bold">{{ $group->name }}</h3>
                            <span class="text-white/80 text-sm">{{ $group->teams->count() }} Teams</span>
                        </div>
                        <div class="p-4">
                            @if($group->teams->count() > 0)
                                <ul class="space-y-2">
                                    @foreach($group->teams as $team)
                                        <li class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                            <div class="flex items-center">
                                                @if($team->team_logo)
                                                    <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}" class="w-8 h-8 rounded-full mr-2 object-cover">
                                                @else
                                                    <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-600 mr-2 flex items-center justify-center text-xs font-bold">
                                                        {{ strtoupper(substr($team->name, 0, 2)) }}
                                                    </div>
                                                @endif
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $team->name }}</span>
                                            </div>
                                            @if($canEdit)
                                                <form action="{{ route('admin.tournaments.groups.remove-team', [$tournament, $group, $team]) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" onclick="return confirm('Remove this team from the group?')" class="text-red-500 hover:text-red-700">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-gray-500 text-sm text-center py-4">No teams in this group</p>
                            @endif

                            @if($canEdit && $availableTeams->count() > 0)
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
                        @if($canEdit)
                            <div class="border-t dark:border-gray-700 px-4 py-2 flex justify-end">
                                <form action="{{ route('admin.tournaments.groups.destroy', [$tournament, $group]) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Delete this group?')" class="text-sm text-red-500 hover:text-red-700">
                                        Delete Group
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                    <svg class="w-7 h-7 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-1">No groups created</h3>
                <p class="text-sm text-gray-500 mb-4">Create groups to organize teams for fixtures and matches.</p>
            </div>
        @endif
    </div>
</div>

@if($canEdit)
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
@endif
@endsection
