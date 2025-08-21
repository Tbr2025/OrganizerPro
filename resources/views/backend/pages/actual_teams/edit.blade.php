@extends('backend.layouts.app')

@section('title', 'Edit Team | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-col md:flex-row md:justify-between md:items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Team Builder</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Editing team:
                    <span class="font-semibold">{{ $actualTeam->name }}</span>
                </p>
            </div>
            <div class="flex items-center space-x-2 mt-4 sm:mt-0 w-full md:w-auto justify-end">
                <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" form="team-form" class="btn btn-primary">Save Team Details</button>
            </div>
        </div>

        {{-- Form for team details --}}
        <form id="team-form" action="{{ route('admin.actual-teams.update', $actualTeam) }}" method="POST"
            enctype="multipart/form-data" class="mb-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-8">
                {{-- Team Details Card --}}
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
                                if (file && file.type.startsWith('image/')) {
                                    this.previewUrl = URL.createObjectURL(file);
                                } else {
                                    this.previewUrl = '{{ $actualTeam->team_logo ? Storage::url($actualTeam->team_logo) : '' }}';
                                }
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
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team
                                Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $actualTeam->name) }}"
                                required class="form-control mt-1">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="organization_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization</label>
                            <select id="organization_id" name="organization_id" required class="form-control mt-1">
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}"
                                        {{ old('organization_id', $actualTeam->organization_id) == $org->id ? 'selected' : '' }}>
                                        {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('organization_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="tournament_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
                            <select id="tournament_id" name="tournament_id" required class="form-control mt-1">
                                @foreach ($tournaments as $t)
                                    <option value="{{ $t->id }}"
                                        {{ old('tournament_id', $actualTeam->tournament_id) == $t->id ? 'selected' : '' }}>
                                        {{ $t->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('tournament_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Member Management Sections --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- LEFT COLUMN (Members) --}}
            <div class="space-y-8">
                {{-- Current Squad --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Current Squad (<span id="squad-count">
                                {{ $currentMembers->count() }}
                            </span>)
                        </h2>
                    </div>
                    <div id="current-squad-container" class="p-5 space-y-3 max-h-[600px] overflow-y-auto">
                        @forelse($currentMembers as $member)
                            @include('backend.pages.actual_teams.partials.member-card', [
                                'member' => $member,
                                'teamId' => $actualTeam->id,
                                'roles' => $availableRolesForSelection,
                            ])
                        @empty
                            <p id="no-squad-placeholder" class="text-center text-gray-500 py-4">No players assigned yet.</p>
                        @endforelse
                    </div>
                </div>


            </div>

            {{-- RIGHT COLUMN (Available Users) --}}
            <div>
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 sticky top-8">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Users</h2>
                    </div>
                    <div class="p-5">
                        <input type="text" id="userSearch" placeholder="Search available users..."
                            class="form-control w-full">
                    </div>
                    <div id="available-users-container" class="p-5 pt-0 space-y-3 max-h-[800px] overflow-y-auto">
                        @forelse ($availableUsers as $user)
                            @include('backend.pages.actual_teams.partials.available-user-card', [
                                'user' => $user,
                                'roles' => $availableRolesForSelection,
                            ])
                        @empty
                            <p class="text-center text-gray-500 py-4">No other users available for this team.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                const els = {
                    search: document.getElementById('userSearch'),
                    available: document.getElementById('available-users-container'),
                    squad: document.getElementById('current-squad-container'),
                    staff: document.getElementById('current-staff-container'),
                    squadCount: document.getElementById('squad-count'),
                    staffCount: document.getElementById('staff-count'),
                    noSquad: document.getElementById('no-squad-placeholder'),
                    noStaff: document.getElementById('no-staff-placeholder'),
                };

                /** Small helpers **/
                const request = async (url, method, body = null) => {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: body ? JSON.stringify(body) : null
                    });
                    if (!res.ok) {
                        let err;
                        try {
                            err = await res.json();
                        } catch {
                            err = {
                                message: 'Request failed'
                            };
                        }
                        throw err;
                    }
                    return res.json();
                };

                const updateCounts = () => {
                    els.squadCount.textContent = els.squad.querySelectorAll('[data-user-id]').length;
                };

                const togglePlaceholders = () => {
                    const squadEmpty = els.squad.querySelectorAll('[data-user-id]').length === 0;
                    if (els.noSquad) els.noSquad.style.display = squadEmpty ? 'block' : 'none';
                };

                /** Create a member card (supports MULTIPLE roles) */
                function createMemberCardHtml(memberData) {
                    const isPlayer = memberData.roles.includes('Player');
                    const container = isPlayer ? document.getElementById('current-squad-container') :
                        document.getElementById('current-staff-container');

                    const retainedTag = memberData.player_mode === 'retained' ?
                        `<span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
         Retained
       </span>` :
                        '';

                    const cardHtml = `
    <div id="member-card-${memberData.id}"
        class="flex items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm border mb-2"
        data-user-id="${memberData.id}">

        <img class="h-10 w-10 rounded-full object-cover mr-3"
            src="${memberData.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(memberData.name)}&color=7F9CF5&background=EBF4FF`}"
            alt="${memberData.name}">

        <div class="flex-1">
            <div class="flex items-center gap-2">
                <div class="font-semibold text-gray-800 dark:text-gray-200">${memberData.name}</div>
                ${
                    memberData.player_mode === 'retained'
                        ? `<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                                                Retained
                                                                           </span>`
                        : ''
                }
            </div>

            <div class="text-xs text-gray-500 dark:text-gray-400">${memberData.email}</div>

            <div class="text-xs text-gray-400">
                Roles: ${memberData.roles.join(', ')}
            </div>
        </div>

        <button type="button"
            class="remove-member-btn text-red-500 hover:text-red-700 p-1"
            data-user-id="${memberData.id}" title="Remove Member">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>

        <input type="hidden" name="members[]" value="${memberData.id}">
        <input type="hidden" name="user_roles[${memberData.id}]" value="${memberData.roles.join(',')}">
            <input type="hidden" name="user_retained[${memberData.id}]" value="${memberData.player_mode === 'retained' ? 'retained' : 'normal'}">

    </div>
`;



                    return {
                        html: cardHtml,
                        container
                    };
                }


                /** Search users in the "Available" list */
                els.search.addEventListener('input', (e) => {
                    const q = e.target.value.toLowerCase();
                    els.available.querySelectorAll('[data-user-id]').forEach(card => {
                        const name = (card.dataset.userName || '').toLowerCase();
                        const email = (card.dataset.userEmail || '').toLowerCase();
                        card.style.display = (name.includes(q) || email.includes(q)) ? 'flex' : 'none';
                    });
                });
                // --- Function to initialize listeners on existing/newly added cards ---
                function initializeCardListeners() {
                    // Add listeners for role changes on existing member cards in squad/staff
                    document.querySelectorAll(
                        '#current-squad-container .role-select, #current-staff-container .role-select').forEach(
                        selectElement => {
                            if (selectElement.dataset.listenerInitialized) return; // Prevent duplicate listeners

                            selectElement.addEventListener('change', async function() {
                                const userId = this.dataset.userId;
                                const teamId = this.dataset
                                    .teamId; // Assuming team ID is set on the select element
                                const newRole = this.value; // The selected role

                                if (!userId || !teamId || !newRole) {
                                    console.error('Missing data for role update.');
                                    return;
                                }

                                try {
                                    const data = await request(
                                        `{{ route('admin.actual-teams.update-member-role', [$actualTeam->id, 'USER_ID']) }}`
                                        .replace('USER_ID', userId),
                                        'POST', {
                                            role: newRole
                                        } // Send the new role
                                    );

                                    console.log(`Role updated for user ${userId} to ${newRole}`);
                                    const memberCard = document.getElementById(`member-card-${userId}`);
                                    if (memberCard) {
                                        memberCard.dataset.userRole =
                                            newRole; // Update if needed for display
                                    }
                                    this.dataset.previousRole =
                                        newRole; // Store current value for potential revert

                                } catch (error) {
                                    console.error('Error updating role:', error);
                                    alert('Failed to update role: ' + (error.message ||
                                        'Unknown error'));
                                    this.value = this.dataset.previousRole ||
                                        'N/A'; // Revert to previous value on error
                                }
                            });
                            selectElement.dataset.listenerInitialized = 'true'; // Mark as initialized
                            selectElement.dataset.previousRole = selectElement.value; // Store initial value
                        });
                }
                /** Global click handling: Add / Remove */
                document.addEventListener('click', async (e) => {
                    if (e.target.closest('.add-member-btn')) {
                        const card = e.target.closest('.parent'); // 🔹 gets the parent card
                        const userId = card.dataset.userId;

                        console.log('Adding user:', userId);

                        // Find hidden inputs for this user's roles
                        const roleInputs = card.querySelectorAll(`input[name^="user_roles[${userId}]"]`);
                        const roles = Array.from(roleInputs).map(input => input.value);

                        console.log(`Roles for user ${userId}:`, roles);






                        if (!roles.length) {
                            alert('Please select at least one role before adding.');
                            return;
                        }
                        const retainedStatusInput = card.querySelector(`#user_retained_${userId}`);
                        const retainedStatus = retainedStatusInput ? retainedStatusInput.value : 'normal';
                        fetch(`{{ route('admin.actual-teams.add-member', $actualTeam->id) }}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .getAttribute('content'),

                                },
                                body: JSON.stringify({
                                    user_id: userId,
                                    roles: roles.map(role => role.trim()).filter(role =>
                                        role),
                                    retained: retainedStatus

                                })
                            })
                            .then(r => r.ok ? r.json() : r.json().then(err => {
                                throw err;
                            }))
                            .then(data => {
                                const memberData = {
                                    id: userId,
                                    name: card.dataset.userName,
                                    email: card.dataset.userEmail,
                                    avatar: data.avatar ||
                                        `https://ui-avatars.com/api/?name=${encodeURIComponent(card.dataset.userName)}&color=7F9CF5&background=EBF4FF`,
                                    roles: roles,
                                    player_mode: data.user
                                        .retained // Use the retained status from response

                                };

                                const {
                                    html,
                                    container
                                } = createMemberCardHtml(memberData);
                                if (html && container) {
                                    container.insertAdjacentHTML('beforeend',
                                        html); // Append to squad or staff
                                    updateCounts(); // Update the counts
                                    togglePlaceholders(); // Update placeholder visibility
                                    card.remove(); // Remove the card from the "Available Users" list
                                    initializeCardListeners
                                        (); // Re-initialize listeners for the newly added card
                                } else {
                                    alert('Failed to create member card HTML.');
                                }
                            })

                            .catch(error => {
                                console.error('Error adding member:', error);
                                alert('Error adding member: ' + (error.message || 'Unknown error'));
                            });
                    }


                    // REMOVE MEMBER
                    if (e.target.closest('.remove-member-btn')) {
                        const btn = e.target.closest('.remove-member-btn');
                        const id = btn.dataset.userId;
                        const memberCard = document.getElementById(`member-card-${id}`);
                        if (!memberCard) return;

                        const memberName = memberCard?.querySelector('.font-semibold')?.textContent
                            ?.trim() || 'this member';

                        if (!confirm(`Remove ${memberName} from this team?`)) return;

                        try {
                            const url =
                                `{{ route('admin.actual-teams.remove-member', [$actualTeam->id, 'USER_ID']) }}`
                                .replace('USER_ID', id);

                            await request(url, 'DELETE');

                            // Remove hidden inputs & remove button (so it matches available card style)
                            memberCard.querySelectorAll(
                                'input[name="members[]"], input[name^="user_roles"]').forEach(el => el
                                .remove());
                            const removeBtn = memberCard.querySelector('.remove-member-btn');
                            if (removeBtn) removeBtn.remove();

                            // Move card back to available users container
                            els.available.appendChild(memberCard);

                            updateCounts();
                            togglePlaceholders();
                        } catch (err) {
                            console.error(err);
                            alert(err.message || 'Error removing member.');
                        }
                    }


                });

                updateCounts();
                togglePlaceholders();
            });
        </script>
    @endpush
@endsection
