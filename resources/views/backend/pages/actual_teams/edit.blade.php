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

        {{-- Team Managers Section --}}
        <div x-data="teamManagerHandler()" class="mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Team Managers</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Users who can participate in auctions and manage this team</p>
                    </div>
                    <button type="button" @click="showCreateModal = true" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i>Add Team Manager
                    </button>
                </div>
                <div class="p-5">
                    {{-- Auction Link --}}
                    @if(isset($teamAuction) && $teamAuction)
                        <div class="mb-4 p-4 bg-gradient-to-r from-green-500/10 to-emerald-500/10 border border-green-500/30 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                                        <i class="fas fa-gavel text-green-500"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-white">{{ $teamAuction->name ?? 'Auction' }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Status: <span class="capitalize {{ $teamAuction->status === 'live' ? 'text-green-500' : 'text-yellow-500' }}">{{ $teamAuction->status }}</span>
                                            &bull; Tournament: {{ $actualTeam->tournament->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('team.auction.bidding.show', $teamAuction->id) }}" target="_blank" class="btn btn-success">
                                        <i class="fas fa-external-link-alt mr-2"></i>Open Bidding Page
                                    </a>
                                    <button type="button" onclick="copyToClipboard('{{ route('team.auction.bidding.show', $teamAuction->id) }}')" class="btn btn-secondary" title="Copy Link">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-1"></i>
                                Share this link with team managers: <code class="bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded">{{ route('team.auction.bidding.show', $teamAuction->id) }}</code>
                            </p>
                        </div>
                    @else
                        <div class="mb-4 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-yellow-500/20 flex items-center justify-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800 dark:text-white">No Active Auction for Current Tournament</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Current tournament: <strong>{{ $actualTeam->tournament->name ?? 'N/A' }}</strong>
                                    </p>
                                </div>
                            </div>

                            @if(isset($availableAuctions) && $availableAuctions->count() > 0)
                                <div class="mt-4 pt-4 border-t border-yellow-500/30">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        <i class="fas fa-link mr-1"></i> Link team to an auction by changing tournament:
                                    </p>
                                    <div class="space-y-2">
                                        @foreach($availableAuctions as $auction)
                                            <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-600">
                                                <div>
                                                    <p class="font-medium text-gray-800 dark:text-white">{{ $auction->name }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        Tournament: {{ $auction->tournament->name ?? 'N/A' }}
                                                        &bull; Status: <span class="capitalize">{{ $auction->status }}</span>
                                                    </p>
                                                </div>
                                                <button type="button"
                                                    onclick="changeTournament({{ $auction->tournament_id }})"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-exchange-alt mr-1"></i> Use This
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="mt-3 text-sm text-gray-500">No active auctions available. Create an auction first.</p>
                            @endif
                        </div>
                    @endif

                    {{-- Existing Team Managers --}}
                    <div id="team-managers-list" class="space-y-3">
                        <template x-if="managers.length === 0 && !loading">
                            <p class="text-center text-gray-500 py-4">No team managers assigned yet. Add a team manager to allow them to participate in auctions.</p>
                        </template>
                        <template x-if="loading">
                            <p class="text-center text-gray-500 py-4">Loading...</p>
                        </template>
                        <template x-for="manager in managers" :key="manager.id">
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                                        <span x-text="manager.name.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-white" x-text="manager.name"></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="manager.email"></p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            <i class="fas fa-user-shield mr-1"></i> <span x-text="manager.role"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="resetPassword(manager)" class="btn btn-sm btn-secondary" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Create Team Manager Modal --}}
            <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="showCreateModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div x-show="showCreateModal" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-semibold text-gray-900 dark:text-white mb-4">Add Team Manager</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                    <input type="text" x-model="newManager.name" class="form-control mt-1" placeholder="Enter name">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                                    <input type="email" x-model="newManager.email" class="form-control mt-1" placeholder="Enter email">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password (leave blank to auto-generate)</label>
                                    <input type="text" x-model="newManager.password" class="form-control mt-1" placeholder="Enter password or leave blank">
                                </div>
                            </div>
                            <div x-show="createError" class="mt-4 p-3 bg-red-100 text-red-700 rounded">
                                <span x-text="createError"></span>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button type="button" @click="createManager()" :disabled="creating" class="btn btn-primary">
                                <span x-show="creating"><i class="fas fa-spinner fa-spin mr-2"></i></span>
                                Create Manager
                            </button>
                            <button type="button" @click="showCreateModal = false" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Credentials Modal --}}
            <div x-show="showCredentialsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCredentialsModal = false"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div @click.stop class="relative inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="text-center mb-4">
                                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900">
                                    <i class="fas fa-check text-green-600 dark:text-green-400 text-xl"></i>
                                </div>
                                <h3 class="text-lg leading-6 font-semibold text-gray-900 dark:text-white mt-4">Login Credentials</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Save these credentials. The password cannot be retrieved later.</p>
                            </div>
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Email:</span>
                                    <span class="font-mono text-sm text-gray-900 dark:text-white" x-text="credentials.email"></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Password:</span>
                                    <span class="font-mono text-sm text-gray-900 dark:text-white font-bold" x-text="credentials.password"></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Login URL:</span>
                                    <span class="font-mono text-xs text-blue-600 dark:text-blue-400">{{ url('/login') }}</span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="button" @click="copyCredentials()" class="w-full btn btn-secondary">
                                    <i class="fas fa-copy mr-2"></i>Copy to Clipboard
                                </button>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6">
                            <button type="button" @click="showCredentialsModal = false" class="w-full btn btn-primary">Done</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Member Management Sections --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- LEFT COLUMN (Members) --}}
            <div class="space-y-8">
                {{-- Current Squad --}}
              
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                    <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Current Squad (<span id="squad-count">
                               {{$currentMembers->count();}}
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
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Available Users (<span id="squad-count">
                                {{ $availableUsers->count() }}
                            </span>)</h2>
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
            // Copy to clipboard helper
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    alert('Link copied to clipboard!');
                });
            }

            // Change tournament and save
            function changeTournament(tournamentId) {
                if (!confirm('Change team tournament to match this auction? The page will reload after saving.')) {
                    return;
                }

                const tournamentSelect = document.getElementById('tournament_id');
                if (tournamentSelect) {
                    tournamentSelect.value = tournamentId;
                    // Submit the form
                    document.getElementById('team-form').submit();
                }
            }

            // Team Manager Handler
            function teamManagerHandler() {
                return {
                    managers: [],
                    loading: true,
                    showCreateModal: false,
                    showCredentialsModal: false,
                    creating: false,
                    createError: '',
                    newManager: {
                        name: '',
                        email: '',
                        password: ''
                    },
                    credentials: {
                        email: '',
                        password: ''
                    },

                    init() {
                        this.loadManagers();
                    },

                    async loadManagers() {
                        this.loading = true;
                        try {
                            const res = await fetch('{{ route("admin.actual-teams.get-team-managers", $actualTeam->id) }}');
                            const data = await res.json();
                            if (data.success) {
                                this.managers = data.managers;
                            }
                        } catch (e) {
                            console.error('Failed to load managers:', e);
                        }
                        this.loading = false;
                    },

                    async createManager() {
                        if (!this.newManager.name || !this.newManager.email) {
                            this.createError = 'Please enter name and email.';
                            return;
                        }

                        this.creating = true;
                        this.createError = '';

                        try {
                            const res = await fetch('{{ route("admin.actual-teams.create-team-manager", $actualTeam->id) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.newManager)
                            });

                            const data = await res.json();

                            if (data.success) {
                                this.credentials = data.credentials;
                                this.showCreateModal = false;
                                this.showCredentialsModal = true;
                                this.newManager = { name: '', email: '', password: '' };
                                this.loadManagers();
                            } else {
                                this.createError = data.message || 'Failed to create manager.';
                            }
                        } catch (e) {
                            this.createError = 'An error occurred. Please try again.';
                            console.error(e);
                        }

                        this.creating = false;
                    },

                    async resetPassword(manager) {
                        if (!confirm(`Reset password for ${manager.name}?`)) return;

                        try {
                            const res = await fetch(`/admin/actual-teams/{{ $actualTeam->id }}/team-manager/${manager.id}/reset-password`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({})
                            });

                            const data = await res.json();

                            if (data.success) {
                                this.credentials = data.credentials;
                                this.showCredentialsModal = true;
                            } else {
                                alert(data.message || 'Failed to reset password.');
                            }
                        } catch (e) {
                            alert('An error occurred. Please try again.');
                            console.error(e);
                        }
                    },

                    copyCredentials() {
                        const text = `Login URL: {{ url('/login') }}\nEmail: ${this.credentials.email}\nPassword: ${this.credentials.password}`;
                        navigator.clipboard.writeText(text).then(() => {
                            alert('Credentials copied to clipboard!');
                        });
                    }
                }
            }

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
                    els.squadCount.textContent = els.squad.querySelectorAll('#current-squad-container > [data-user-id]').length;
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
                                  els.available.querySelectorAll('.parent').forEach(card => { // Ensure '.parent' is the correct selector for your available user cards
                        const name = (card.dataset.userName || '').toLowerCase();
                        const email = (card.dataset.userEmail || '').toLowerCase();
                        // Show the card if it matches the search query, otherwise hide it
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
                                        'PUT', {
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
