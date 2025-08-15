@extends('backend.layouts.app')

@section('title', 'Edit Team | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Team Builder</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Editing team: <span
                    class="font-semibold">{{ $actualTeam->name }}</span></p>
        </div>
        <div class="flex items-center space-x-2 mt-4 sm:mt-0">
            <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" form="team-form" class="btn btn-primary">Save Changes</button>
        </div>
    </div>

    {{-- Form --}}
    <form id="team-form" action="{{ route('admin.actual-teams.update', $actualTeam) }}" method="POST"
        enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            {{-- LEFT COLUMN --}}
            <div class="space-y-8">

                {{-- Team Details --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Team Details</h2>
                    </div>
                    <div class="p-5 space-y-6">

                        {{-- Logo Upload --}}
                        <div x-data="{
                            previewUrl: '{{ $actualTeam->team_logo ? Storage::url($actualTeam->team_logo) : '' }}',
                            handleFileChange(event) {
                                const file = event.target.files[0];
                                if (file && file.type.startsWith('image/')) { this.previewUrl = URL.createObjectURL(file); } else { this.previewUrl = '{{ $actualTeam->team_logo ? Storage::url($actualTeam->team_logo) : '' }}'; }
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Logo</label>
                            <div class="mt-2 flex items-center gap-4">
                                <span
                                    class="inline-block h-20 w-20 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                                    <template x-if="!previewUrl">
                                        <svg class="h-full w-full text-gray-300 dark:text-gray-500" fill="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M24 20.993V24H0v-2.997A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </template>
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl" alt="Team Logo Preview"
                                            class="h-full w-full object-cover">
                                    </template>
                                </span>
                                <label for="team_logo"
                                    class="cursor-pointer bg-white dark:bg-gray-700 py-2 px-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <span>Change Logo</span>
                                    <input id="team_logo" name="team_logo" type="file" class="sr-only"
                                        @change="handleFileChange">
                                </label>
                            </div>
                            @error('team_logo')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Other Details --}}
                        <div>
                            <label for="name"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Name</label>
                            <input type="text" id="name" name="name"
                                value="{{ old('name', $actualTeam->name) }}" required class="form-control mt-1">
                        </div>
                        <div>
                            <label for="organization_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                            <select id="organization_id" name="organization_id" required class="form-control mt-1">
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}"
                                        {{ old('organization_id', $actualTeam->organization_id) == $org->id ? 'selected' : '' }}>
                                        {{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="tournament_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
                            <select id="tournament_id" name="tournament_id" required class="form-control mt-1">
                                @foreach ($tournaments as $t)
                                    <option value="{{ $t->id }}"
                                        {{ old('tournament_id', $actualTeam->tournament_id) == $t->id ? 'selected' : '' }}>
                                        {{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Current Squad --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Current Squad
                            ({{ $currentMembers->filter(fn($user) => $user->hasRole('Player'))->count() }})</h2>
                    </div>
                    <div id="current-squad-container" class="p-5 space-y-3 max-h-[600px] overflow-y-auto">
                        @forelse($currentMembers->filter(fn($user) => $user->hasRole('Player')) as $member)
                            <div id="current-member-{{ $member->id }}"
                                class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <img class="h-10 w-10 rounded-full object-cover mr-3"
                                    src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&color=7F9CF5&background=EBF4FF"
                                    alt="{{ $member->name }}">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $member->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                </div>
                                <div class="w-40 mr-2">
                                    <select name="user_roles[{{ $member->id }}]" class="form-control form-control-sm">
                                        @foreach ($roles->filter(fn($role) => $role->name === 'Player') as $role)
                                            <option value="{{ $role->name }}"
                                                {{ $member->getRoleNames()->contains($role->name) ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1"
                                    data-user-id="{{ $member->id }}" title="Remove Member">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <input type="hidden" name="members[]" value="{{ $member->id }}">
                            </div>
                        @empty
                            <p id="no-squad-placeholder" class="text-center text-gray-500 py-4">No players assigned yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Current Staff --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Current Staff
                            ({{ $currentMembers->filter(fn($user) => !$user->hasRole('Player'))->count() }})</h2>
                    </div>
                    <div id="current-staff-container" class="p-5 space-y-3 max-h-[600px] overflow-y-auto">
                        @forelse($currentMembers->filter(fn($user) => !$user->hasRole('Player')) as $member)
                            <div id="current-member-{{ $member->id }}"
                                class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <img class="h-10 w-10 rounded-full object-cover mr-3"
                                    src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&color=7F9CF5&background=EBF4FF"
                                    alt="{{ $member->name }}">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $member->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                </div>
                                <div class="w-40 mr-2">
                                    <select name="user_roles[{{ $member->id }}]" class="form-control form-control-sm">
                                        @foreach ($roles->filter(fn($role) => $role->name !== 'Player') as $role)
                                            <option value="{{ $role->name }}"
                                                {{ $member->getRoleNames()->contains($role->name) ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1"
                                    data-user-id="{{ $member->id }}" title="Remove Member">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <input type="hidden" name="members[]" value="{{ $member->id }}">
                            </div>
                        @empty
                            <p id="no-staff-placeholder" class="text-center text-gray-500 py-4">No staff assigned yet.</p>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- RIGHT COLUMN --}}
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 sticky top-8">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Players</h2>
                    </div>
                    <div class="p-5">
                        <input type="text" id="userSearch" placeholder="Search available players..." class="form-control w-full">
                    </div>
                    <div id="available-users-container" class="p-5 pt-0 space-y-3 max-h-[800px] overflow-y-auto">
                        @forelse ($users as $user)
                            <div class="flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                                data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}"
                                data-user-email="{{ $user->email }}">
                                <img class="h-10 w-10 rounded-full object-cover mr-3"
                                    src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&color=7F9CF5&background=EBF4FF"
                                    alt="{{ $user->name }}">
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
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
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
document.addEventListener('DOMContentLoaded', function() {
    const userSearch = document.getElementById('userSearch');
    const availableContainer = document.getElementById('available-users-container');
    const form = document.getElementById('team-form');

    // Live search
    userSearch.addEventListener('input', function() {
        const searchValue = this.value.toLowerCase();
        availableContainer.querySelectorAll('[data-user-id]').forEach(row => {
            const name = row.dataset.userName.toLowerCase();
            const email = row.dataset.userEmail.toLowerCase();
            row.style.display = name.includes(searchValue) || email.includes(searchValue) ? 'flex' : 'none';
        });
    });

    // Add/Remove Members
    document.addEventListener('click', function(e) {

        // --- ADD MEMBER ---
        if (e.target.closest('.add-member-btn')) {
            const button = e.target.closest('.add-member-btn');
            const card = button.closest('[data-user-id]');
            const userId = card.dataset.userId;
            const userName = card.dataset.userName;
            const userEmail = card.dataset.userEmail;
            const roleSelect = card.querySelector('.role-select');
            const roleName = roleSelect.value;

            if (!roleName) { alert('Please select a role before adding.'); return; }

            // Determine container
            const containerId = roleName === 'Player' ? 'current-squad-container' : 'current-staff-container';
            const currentContainer = document.getElementById(containerId);

            // Hide placeholder
            const placeholderId = roleName === 'Player' ? 'no-squad-placeholder' : 'no-staff-placeholder';
            const placeholder = document.getElementById(placeholderId);
            if (placeholder) placeholder.style.display = 'none';

            // Append member
            const newCard = document.createElement('div');
            newCard.id = `current-member-${userId}`;
            newCard.className = 'flex items-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg';
            newCard.innerHTML = `
                <img class="h-10 w-10 rounded-full object-cover mr-3" src="https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&color=7F9CF5&background=EBF4FF" alt="${userName}">
                <div class="flex-1">
                    <div class="font-semibold text-gray-800 dark:text-gray-200">${userName}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">${userEmail}</div>
                </div>
                <div class="w-40 mr-2">
                    <select name="user_roles[${userId}]" class="form-control form-control-sm">
                        <option value="${roleName}" selected>${roleName.charAt(0).toUpperCase() + roleName.slice(1)}</option>
                    </select>
                </div>
                <button type="button" class="remove-member-btn text-red-500 hover:text-red-700 p-1" data-user-id="${userId}" title="Remove Member">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
                <input type="hidden" name="members[]" value="${userId}">
            `;
            currentContainer.appendChild(newCard);

            // Hide original card
            card.style.display = 'none';
        }

        // --- REMOVE MEMBER ---
        if (e.target.closest('.remove-member-btn')) {
            const button = e.target.closest('.remove-member-btn');
            const userId = button.dataset.userId;
            const memberCard = document.getElementById(`current-member-${userId}`);
            if (!memberCard) return;

            // Remove hidden input
            const input = memberCard.querySelector(`input[name="members[]"][value="${userId}"]`);
            if (input) input.remove();

            memberCard.remove();

            // If container is empty, show placeholder
            const container = memberCard.parentElement;
            if (container.children.length === 0) {
                if (container.id === 'current-squad-container') {
                    const placeholder = document.getElementById('no-squad-placeholder');
                    if (placeholder) placeholder.style.display = 'block';
                } else if (container.id === 'current-staff-container') {
                    const placeholder = document.getElementById('no-staff-placeholder');
                    if (placeholder) placeholder.style.display = 'block';
                }
            }

            // Show original available user
            const originalCard = availableContainer.querySelector(`[data-user-id="${userId}"]`);
            if (originalCard) originalCard.style.display = 'flex';
        }

    });
});
</script>
@endsection