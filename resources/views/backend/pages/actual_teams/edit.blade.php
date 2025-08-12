@extends('backend.layouts.app')

@section('title', 'Edit Team | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Team Builder</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Editing team: <span class="font-semibold">{{ $actualTeam->name }}</span></p>
        </div>
        <div class="flex items-center space-x-2 mt-4 sm:mt-0">
             <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" form="team-form" class="btn btn-primary">Save Changes</button>
        </div>
    </div>

    <form id="team-form" action="{{ route('admin.actual-teams.update', $actualTeam) }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Main two-column grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            {{-- LEFT COLUMN --}}
            <div class="space-y-8">
                <!-- Team Details Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Team Details</h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $actualTeam->name) }}" required class="form-control mt-1">
                        </div>
                        <div>
                            <label for="organization_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                            <select id="organization_id" name="organization_id" required class="form-control mt-1">
                                @foreach ($organizations as $org)
                                <option value="{{ $org->id }}" {{ old('organization_id', $actualTeam->organization_id) == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="tournament_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
                            <select id="tournament_id" name="tournament_id" required class="form-control mt-1">
                                @foreach ($tournaments as $t)
                                <option value="{{ $t->id }}" {{ old('tournament_id', $actualTeam->tournament_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Current Squad Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Current Squad ({{ $currentMembers->count() }})</h2>
                    </div>
                    <div id="current-members-container" class="p-5 space-y-3 max-h-[600px] overflow-y-auto">
                        @forelse($currentMembers as $member)
                            {{-- This is a card for a current member --}}
                            <div id="current-member-{{$member->id}}" class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <img class="h-10 w-10 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&color=7F9CF5&background=EBF4FF" alt="{{ $member->name }}">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $member->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                </div>
                                <div class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-4">{{ $member->pivot->role ?? 'N/A' }}</div>
                                <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1" data-user-id="{{ $member->id }}" title="Remove Member">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </button>
                                {{-- Hidden inputs to submit current members --}}
                                <input type="hidden" name="members[]" value="{{ $member->id }}">
                                <input type="hidden" name="user_roles[{{$member->id}}]" value="{{ $member->pivot->role ?? 'Player' }}">
                            </div>
                        @empty
                            <p id="no-members-placeholder" class="text-center text-gray-500 py-4">No members assigned yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- RIGHT COLUMN --}}
            <div>
                <!-- Available Players Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 sticky top-8">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Players</h2>
                    </div>
                    <div class="p-5">
                         <input type="text" id="userSearch" placeholder="Search available players..." class="form-control w-full">
                    </div>
                    <div id="available-users-container" class="p-5 pt-0 space-y-3 max-h-[800px] overflow-y-auto">
                        @forelse ($users as $user)
                            {{-- This is a card for an available player --}}
                            <div class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}">
                                <img class="h-10 w-10 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&color=7F9CF5&background=EBF4FF" alt="{{ $user->name }}">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                </div>
                                <div class="w-40 mr-2">
                                    <select class="form-control form-control-sm role-select">
                                        <option value="">-- Select Role --</option>
                                        @foreach ($roles as $role)
                                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="add-member-btn text-green-500 hover:text-green-700 p-1" title="Add Member">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </button>
                            </div>
                        @empty
                            <p class="text-center text-gray-500 py-4">No other users available.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const userSearch = document.getElementById('userSearch');
    const availableContainer = document.getElementById('available-users-container');
    const currentContainer = document.getElementById('current-members-container');
    const form = document.getElementById('team-form');

    // Live search for available players
    userSearch.addEventListener('input', function() {
        const searchValue = this.value.toLowerCase();
        availableContainer.querySelectorAll('[data-user-id]').forEach(row => {
            const name = row.dataset.userName.toLowerCase();
            const email = row.dataset.userEmail.toLowerCase();
            row.style.display = name.includes(searchValue) || email.includes(searchValue) ? 'flex' : 'none';
        });
    });

    // Handle clicks on "Add" and "Remove" buttons
    document.addEventListener('click', function(e) {

        // --- ADD MEMBER ---
        if (e.target.closest('.add-member-btn')) {
            const button = e.target.closest('.add-member-btn');
            const playerCard = button.closest('[data-user-id]');
            const userId = playerCard.dataset.userId;
            const userName = playerCard.dataset.userName;
            const userEmail = playerCard.dataset.userEmail;
            const roleSelect = playerCard.querySelector('.role-select');
            const roleName = roleSelect.value;

            if (!roleName) {
                alert('Please select a role for the player before adding them.');
                return;
            }

            // Hide the 'no members' placeholder if it exists
            const placeholder = document.getElementById('no-members-placeholder');
            if(placeholder) placeholder.style.display = 'none';

            // Create and append new card to current members
            const newMemberCardHTML = `
                <div id="current-member-${userId}" class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <img class="h-10 w-10 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&color=7F9CF5&background=EBF4FF" alt="${userName}">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800 dark:text-gray-200">${userName}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${userEmail}</div>
                    </div>
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-300 mr-4">${roleName.charAt(0).toUpperCase() + roleName.slice(1)}</div>
                    <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1" data-user-id="${userId}" title="Remove Member">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </button>
                    <input type="hidden" name="members[]" value="${userId}">
                    <input type="hidden" name="user_roles[${userId}]" value="${roleName}">
                </div>`;
            currentContainer.insertAdjacentHTML('beforeend', newMemberCardHTML);

            // Hide the original card from available list
            playerCard.style.display = 'none';
        }

        // --- REMOVE MEMBER ---
        if (e.target.closest('.remove-member-btn')) {
            const button = e.target.closest('.remove-member-btn');
            const userId = button.dataset.userId;
            const memberCard = document.getElementById(`current-member-${userId}`);
            
            // Find and remove the hidden inputs associated with this user
            form.querySelector(`input[name="members[]"][value="${userId}"]`)?.remove();
            form.querySelector(`input[name="user_roles[${userId}]"]`)?.remove();
            
            // Remove the visual card
            memberCard?.remove();

            // Un-hide the original card in the available list
            const availableCard = availableContainer.querySelector(`[data-user-id="${userId}"]`);
            if(availableCard) availableCard.style.display = 'flex';

            // Show placeholder if no members left
            if(currentContainer.children.length === 0) {
                const placeholder = document.getElementById('no-members-placeholder');
                if(placeholder) placeholder.style.display = 'block';
            }
        }
    });
});
</script>
@endsection