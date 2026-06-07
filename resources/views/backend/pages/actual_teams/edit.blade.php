@extends('backend.layouts.app')

@section('title', 'Edit Team | ' . config('app.name'))

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="[
        ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
        ['name' => 'Teams', 'route' => route('admin.actual-teams.index')],
        ['name' => 'Edit']
    ]" />

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
                        {{-- Logo Upload with Cropper --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Logo</label>
                            <div class="mt-2">
                                <x-logo-cropper name="team_logo" :existingImage="$actualTeam->team_logo" />
                            </div>
                            @error('team_logo')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Other Details --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $actualTeam->name) }}"
                                    required class="form-control mt-1">
                                @error('name')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Short Name</label>
                                <input type="text" id="short_name" name="short_name" value="{{ old('short_name', $actualTeam->short_name) }}"
                                    class="form-control mt-1" placeholder="e.g., MCC">
                                @error('short_name')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location / District</label>
                            <input type="text" id="location" name="location" value="{{ old('location', $actualTeam->location) }}"
                                class="form-control mt-1" placeholder="e.g., Ernakulam">
                            <p class="text-xs text-gray-500 mt-1">Displayed on match posters</p>
                            @error('location')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" id="primary_color" name="primary_color"
                                        value="{{ old('primary_color', $actualTeam->primary_color ?? '#00BCD4') }}"
                                        class="w-12 h-10 rounded cursor-pointer border-0">
                                    <input type="text" id="primary_color_text"
                                        value="{{ old('primary_color', $actualTeam->primary_color ?? '#00BCD4') }}"
                                        class="form-control flex-1" readonly>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Used for poster background accent</p>
                            </div>

                            <div>
                                <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" id="secondary_color" name="secondary_color"
                                        value="{{ old('secondary_color', $actualTeam->secondary_color ?? '#ffffff') }}"
                                        class="w-12 h-10 rounded cursor-pointer border-0">
                                    <input type="text" id="secondary_color_text"
                                        value="{{ old('secondary_color', $actualTeam->secondary_color ?? '#ffffff') }}"
                                        class="form-control flex-1" readonly>
                                </div>
                            </div>
                        </div>

                        {{-- Sponsor Logo Upload --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Sponsor Logo</label>
                            <x-image-dropzone
                                name="sponsor_logo"
                                :existingImage="$actualTeam->sponsor_logo"
                                hint="Displayed on match posters below team name"
                                previewHeight="h-32"
                            />
                            @error('sponsor_logo')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Captain Selection --}}
                        <div>
                            <label for="captain_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Team Captain
                            </label>
                            <select id="captain_user_id" name="captain_user_id" class="form-control mt-1">
                                <option value="">-- Select Captain --</option>
                                <optgroup label="Current Team Members">
                                    @foreach($currentMembers as $member)
                                        <option value="{{ $member->id }}"
                                            {{ ($actualTeam->captain && $actualTeam->captain->id == $member->id) ? 'selected' : '' }}>
                                            {{ $member->name }} ({{ $member->email }})
                                        </option>
                                    @endforeach
                                </optgroup>
                                @if($registeredPlayersForCaptain->count())
                                    <optgroup label="Registered Players">
                                        @foreach($registeredPlayersForCaptain as $regPlayer)
                                            <option value="{{ $regPlayer->id }}"
                                                {{ ($actualTeam->captain && $actualTeam->captain->id == $regPlayer->id) ? 'selected' : '' }}>
                                                {{ $regPlayer->name }} ({{ $regPlayer->email }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Select the team captain from current members or registered players</p>
                            @error('captain_user_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Captain Image Upload --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Captain / Featured Player Image</label>
                            <x-player-image-upload
                                name="captain_image"
                                :existingImage="$actualTeam->captain_image"
                                mode="captain"
                            />
                            @error('captain_image')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="organization_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization
                                @if (auth()->user()->hasRole('Superadmin'))
                                    <span class="text-xs text-gray-400">(Reassignable)</span>
                                @endif
                            </label>
                            <select id="organization_id" name="organization_id" required class="form-control mt-1"
                                @unless(auth()->user()->hasRole('Superadmin')) disabled @endunless>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}"
                                        {{ old('organization_id', $actualTeam->organization_id) == $org->id ? 'selected' : '' }}>
                                        {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                            @unless(auth()->user()->hasRole('Superadmin'))
                                {{-- Hidden input to ensure value is submitted when select is disabled --}}
                                <input type="hidden" name="organization_id" value="{{ $actualTeam->organization_id }}">
                            @endunless
                            @error('organization_id')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Team Scope Toggle --}}
                        <div x-data="{ teamScope: '{{ old('team_scope', $actualTeam->is_global ? 'global' : 'tournament') }}' }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Scope</label>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="team_scope" value="tournament" x-model="teamScope"
                                        class="form-radio text-blue-600">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Open Tournament</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="team_scope" value="global" x-model="teamScope"
                                        class="form-radio text-purple-600">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Global</span>
                                </label>
                            </div>

                            {{-- Global info banner --}}
                            <div x-show="teamScope === 'global'" x-cloak
                                class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-sm text-purple-700 dark:text-purple-300">This team will be available in <strong>all tournaments</strong> within the organization.</p>
                                </div>
                            </div>

                            {{-- Tournaments (only for Open Tournament) --}}
                            <div x-show="teamScope === 'tournament'" x-cloak class="mt-4">
                                <x-inputs.combobox
                                    name="tournament_ids[]"
                                    label="Tournaments"
                                    placeholder="Select Tournaments"
                                    :options="$tournaments->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                                    :selected="old('tournament_ids', array_map('strval', $selectedTournamentIds))"
                                    :multiple="true"
                                    :searchable="true"
                                    :required="false"
                                />
                                @error('tournament_ids')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                @error('tournament_ids.*')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
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
                                    <button type="button" @click="resendCredentials(manager)" class="btn btn-sm btn-primary" title="Send Credentials Email" :disabled="manager.sending">
                                        <i class="fas fa-envelope" x-show="!manager.sending"></i>
                                        <i class="fas fa-spinner fa-spin" x-show="manager.sending"></i>
                                    </button>
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

        {{-- ======================================================= --}}
        {{-- Player Roster Section --}}
        {{-- ======================================================= --}}
        <div x-data="playerRosterHandler()" class="mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
                <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Player Roster</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage players and their tournament-team assignments</p>
                </div>

                <div class="p-5">
                    @if($effectiveTournaments->count() > 0)
                        <div class="space-y-3">
                            @foreach($effectiveTournaments as $tournament)
                                @php
                                    $tournamentPlayers = $teamPlayersByTournament->get($tournament->id, collect());
                                @endphp
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden"
                                     :class="activeAccordions[{{ $tournament->id }}] ? 'border-l-4 border-l-blue-500' : ''">
                                    {{-- Accordion Header --}}
                                    <button type="button"
                                        @click="toggleAccordion({{ $tournament->id }})"
                                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                                                 :class="activeAccordions[{{ $tournament->id }}] ? 'rotate-90' : ''"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $tournament->name }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                                  x-text="playerCounts[{{ $tournament->id }}] ?? {{ $tournamentPlayers->count() }}"
                                                  id="badge-tournament-{{ $tournament->id }}">
                                                {{ $tournamentPlayers->count() }}
                                            </span>
                                        </div>
                                        <span @click.stop="openAddDrawer({{ $tournament->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                                            Add Player
                                        </span>
                                    </button>

                                    {{-- Accordion Body --}}
                                    <div x-show="activeAccordions[{{ $tournament->id }}]"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 -translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 -translate-y-1"
                                         x-cloak>
                                        <div class="p-4" id="roster-tournament-{{ $tournament->id }}">
                                            @if($tournamentPlayers->count() > 0)
                                                <div class="space-y-2">
                                                    @foreach($tournamentPlayers as $assignment)
                                                        @php $p = $playersMap->get($assignment->player_id); @endphp
                                                        @if($p)
                                                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg player-card" data-player-id="{{ $p->id }}">
                                                                <div class="flex items-center gap-3">
                                                                    <img class="h-10 w-10 rounded-full object-cover"
                                                                        src="{{ $p->image_path ? asset('storage/' . $p->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($p->name) . '&color=7F9CF5&background=EBF4FF' }}"
                                                                        alt="{{ $p->name }}">
                                                                    <div>
                                                                        <p class="font-medium text-gray-800 dark:text-white text-sm">{{ $p->name }}</p>
                                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $p->mobile_number_full ?? 'No phone' }}</p>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center gap-2">
                                                                    @if($assignment->role)
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                            {{ ucfirst($assignment->role) }}
                                                                        </span>
                                                                    @endif
                                                                    <button type="button"
                                                                        @click="removePlayer({{ $p->id }}, '{{ addslashes($p->name) }}')"
                                                                        class="p-1.5 text-red-500 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50"
                                                                        title="Remove player">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-sm text-gray-400 italic empty-message">No players assigned for this tournament.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-gray-500 py-4">No tournaments configured. Set a team scope above to manage players.</p>
                    @endif
                </div>
            </div>

            {{-- Add Player Drawer --}}
            <div x-show="showDrawer" x-cloak class="fixed inset-0 z-50 overflow-hidden">
                {{-- Overlay --}}
                <div x-show="showDrawer" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showDrawer = false"></div>

                {{-- Drawer panel --}}
                <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div x-show="showDrawer" x-transition:enter="transform transition ease-in-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in-out duration-300" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                        class="w-screen max-w-md" @click.stop>
                        <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-xl">
                            {{-- Header --}}
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Player</h3>
                                <button type="button" @click="showDrawer = false" class="text-gray-400 hover:text-gray-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-5">
                                {{-- Mode Toggle --}}
                                <div class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
                                    <button type="button" @click="addMode = 'existing'; selectedExistingPlayerId = null; squadSearch = ''"
                                        :class="addMode === 'existing' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium transition-colors text-center">
                                        From Squad
                                    </button>
                                    <button type="button" @click="addMode = 'new'"
                                        :class="addMode === 'new' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'"
                                        class="flex-1 px-4 py-2.5 text-sm font-medium transition-colors text-center">
                                        New Player
                                    </button>
                                </div>

                                {{-- EXISTING PLAYER MODE --}}
                                <template x-if="addMode === 'existing'">
                                    <div class="space-y-4">
                                        {{-- Search --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Squad</label>
                                            <input type="text" x-model="squadSearch" class="form-control mt-1" placeholder="Search by name or phone...">
                                        </div>

                                        {{-- Player List --}}
                                        <div class="max-h-52 overflow-y-auto space-y-1 border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                                            <template x-for="sp in filteredSquadPlayers" :key="sp.id">
                                                <button type="button" @click="selectedExistingPlayerId = sp.id"
                                                    :class="selectedExistingPlayerId === sp.id ? 'bg-blue-50 dark:bg-blue-900/30 ring-2 ring-blue-500' : 'hover:bg-gray-50 dark:hover:bg-gray-700'"
                                                    class="w-full flex items-center gap-3 p-2.5 rounded-lg transition-colors text-left">
                                                    <img :src="sp.image || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(sp.name) + '&color=7F9CF5&background=EBF4FF')"
                                                         class="h-9 w-9 rounded-full object-cover flex-shrink-0" :alt="sp.name">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-gray-800 dark:text-white truncate" x-text="sp.name"></p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="sp.phone || sp.email"></p>
                                                    </div>
                                                    <svg x-show="selectedExistingPlayerId === sp.id" class="w-5 h-5 text-blue-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                </button>
                                            </template>
                                            <template x-if="filteredSquadPlayers.length === 0">
                                                <p class="text-sm text-gray-400 italic text-center py-3">No squad players found.</p>
                                            </template>
                                        </div>

                                        {{-- Selected Player Preview --}}
                                        <template x-if="selectedSquadPlayer">
                                            <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg flex items-center gap-3">
                                                <img :src="selectedSquadPlayer.image || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(selectedSquadPlayer.name) + '&color=7F9CF5&background=EBF4FF')"
                                                     class="h-10 w-10 rounded-full object-cover" :alt="selectedSquadPlayer.name">
                                                <div>
                                                    <p class="text-sm font-semibold text-green-800 dark:text-green-200" x-text="selectedSquadPlayer.name"></p>
                                                    <p class="text-xs text-green-600 dark:text-green-400" x-text="selectedSquadPlayer.phone || selectedSquadPlayer.email"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- NEW PLAYER MODE --}}
                                <template x-if="addMode === 'new'">
                                    <div class="space-y-5">
                                        {{-- Player Name --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Name <span class="text-red-500">*</span></label>
                                            <input type="text" x-model="newPlayer.name" class="form-control mt-1" placeholder="Enter player name">
                                        </div>

                                        {{-- Email --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address <span class="text-red-500">*</span></label>
                                            <input type="email" x-model="newPlayer.email" class="form-control mt-1" placeholder="e.g., player@example.com">
                                        </div>

                                        {{-- Phone --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number <span class="text-red-500">*</span></label>
                                            <input type="text" x-model="newPlayer.phone" class="form-control mt-1" placeholder="e.g., +919876543210"
                                                @blur="lookupPlayer()">
                                            <p class="text-xs text-gray-500 mt-1">If an existing player is found by phone, they will be linked instead of creating a new one.</p>
                                            <template x-if="lookupResult">
                                                <div class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded text-sm text-green-700 dark:text-green-300">
                                                    Found existing player: <strong x-text="lookupResult"></strong>
                                                </div>
                                            </template>
                                        </div>

                                        {{-- Player Image --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Image</label>
                                            <div class="mt-2 flex items-center gap-4">
                                                <template x-if="imagePreview">
                                                    <img :src="imagePreview" class="h-16 w-16 rounded-full object-cover">
                                                </template>
                                                <template x-if="!imagePreview">
                                                    <div class="h-16 w-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                                        <svg class="w-8 h-8 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M24 20.993V24H0v-2.997A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                                    </div>
                                                </template>
                                                <label class="cursor-pointer bg-white dark:bg-gray-700 py-2 px-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                    <span>Upload</span>
                                                    <input type="file" class="sr-only" accept="image/*" @change="handleImageChange($event)">
                                                </label>
                                            </div>
                                        </div>

                                        {{-- Home Team (read-only) --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Home Team</label>
                                            <input type="text" value="{{ $actualTeam->name }}" class="form-control mt-1 bg-gray-50 dark:bg-gray-700" readonly>
                                        </div>
                                    </div>
                                </template>

                                {{-- Player Role --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Role</label>
                                    <select x-model="playerRole" class="form-control mt-1 text-sm">
                                        <option value="">-- None --</option>
                                        <option value="captain">Captain</option>
                                        <option value="vice_captain">Vice Captain</option>
                                        <option value="wicket_keeper">Wicket Keeper</option>
                                    </select>
                                </div>

                                {{-- Tournament Assignments (only for new player mode) --}}
                                <div x-show="addMode === 'new'">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament-Team Assignments</label>
                                    <div class="space-y-3">
                                        @foreach($effectiveTournaments as $idx => $tournament)
                                            <div class="p-3 rounded-lg transition-colors"
                                                 :class="targetTournamentId === {{ $tournament->id }} ? 'bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-300 dark:ring-blue-700' : 'bg-gray-50 dark:bg-gray-700'">
                                                <p class="text-sm font-medium mb-1"
                                                   :class="targetTournamentId === {{ $tournament->id }} ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-300'">
                                                    {{ $tournament->name }}
                                                </p>
                                                <input type="hidden" x-bind:name="'tournament_assignments[' + {{ $idx }} + '][tournament_id]'" value="{{ $tournament->id }}">
                                                <select x-model="newPlayer.assignments[{{ $tournament->id }}]"
                                                    class="form-control text-sm">
                                                    @foreach($allTeamsForTournaments[$tournament->id] ?? [] as $t)
                                                        <option value="{{ $t->id }}" {{ $t->id === $actualTeam->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Info for existing player mode --}}
                                <div x-show="addMode === 'existing'" x-cloak class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                        Player will be added to all tournament rosters for this team.
                                    </p>
                                </div>

                                {{-- Error display --}}
                                <div x-show="error" x-cloak class="p-3 bg-red-100 text-red-700 rounded">
                                    <span x-text="error"></span>
                                </div>
                            </div>

                            {{-- Footer --}}
                            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                                <button type="button" @click="showDrawer = false" class="btn btn-secondary">Cancel</button>
                                <button type="button" @click="savePlayer()" :disabled="saving" class="btn btn-primary">
                                    <span x-show="saving"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></span>
                                    Save Player
                                </button>
                            </div>
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
            // Color picker sync
            document.addEventListener('DOMContentLoaded', function() {
                const primaryColor = document.getElementById('primary_color');
                const primaryColorText = document.getElementById('primary_color_text');
                const secondaryColor = document.getElementById('secondary_color');
                const secondaryColorText = document.getElementById('secondary_color_text');

                if (primaryColor && primaryColorText) {
                    primaryColor.addEventListener('input', function() {
                        primaryColorText.value = this.value;
                    });
                }

                if (secondaryColor && secondaryColorText) {
                    secondaryColor.addEventListener('input', function() {
                        secondaryColorText.value = this.value;
                    });
                }
            });

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

            // Change tournament and save — inject a hidden input for the tournament and submit
            function changeTournament(tournamentId) {
                if (!confirm('Add this auction\'s tournament to the team? The page will reload after saving.')) {
                    return;
                }

                const form = document.getElementById('team-form');
                if (form) {
                    // Check if this tournament is already in the form as a hidden input
                    const existing = form.querySelectorAll('input[name^="tournament_ids"]');
                    let found = false;
                    existing.forEach(el => { if (el.value == tournamentId) found = true; });

                    if (!found) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'tournament_ids[' + existing.length + ']';
                        input.value = tournamentId;
                        form.appendChild(input);
                    }
                    form.submit();
                }
            }

            // Player Roster Handler
            function playerRosterHandler() {
                return {
                    showDrawer: false,
                    saving: false,
                    error: '',
                    lookupResult: '',
                    imagePreview: '',
                    imageFile: null,
                    targetTournamentId: null,
                    addMode: 'new', // 'new' or 'existing'
                    selectedExistingPlayerId: null,
                    squadSearch: '',
                    playerRole: '',
                    squadPlayers: @json($squadPlayersJson),
                    activeAccordions: {
                        @foreach($effectiveTournaments as $tournament)
                            {{ $tournament->id }}: true,
                        @endforeach
                    },
                    playerCounts: {
                        @foreach($effectiveTournaments as $tournament)
                            {{ $tournament->id }}: {{ $teamPlayersByTournament->get($tournament->id, collect())->count() }},
                        @endforeach
                    },
                    newPlayer: {
                        name: '',
                        email: '',
                        phone: '',
                        assignments: {
                            @foreach($effectiveTournaments as $tournament)
                                {{ $tournament->id }}: '{{ $actualTeam->id }}',
                            @endforeach
                        }
                    },

                    get filteredSquadPlayers() {
                        if (!this.squadSearch) return this.squadPlayers;
                        const q = this.squadSearch.toLowerCase();
                        return this.squadPlayers.filter(p =>
                            p.name.toLowerCase().includes(q) || (p.phone && p.phone.includes(q))
                        );
                    },

                    get selectedSquadPlayer() {
                        return this.squadPlayers.find(p => p.id === this.selectedExistingPlayerId);
                    },

                    toggleAccordion(id) {
                        this.activeAccordions[id] = !this.activeAccordions[id];
                    },

                    openAddDrawer(tournamentId = null) {
                        this.targetTournamentId = tournamentId;
                        this.addMode = 'new';
                        this.selectedExistingPlayerId = null;
                        this.squadSearch = '';
                        this.playerRole = '';
                        this.newPlayer = {
                            name: '',
                            email: '',
                            phone: '',
                            assignments: {
                                @foreach($effectiveTournaments as $tournament)
                                    {{ $tournament->id }}: '{{ $actualTeam->id }}',
                                @endforeach
                            }
                        };
                        this.error = '';
                        this.lookupResult = '';
                        this.imagePreview = '';
                        this.imageFile = null;
                        this.showDrawer = true;
                    },

                    handleImageChange(event) {
                        const file = event.target.files[0];
                        if (file && file.type.startsWith('image/')) {
                            this.imageFile = file;
                            this.imagePreview = URL.createObjectURL(file);
                        }
                    },

                    async lookupPlayer() {
                        this.lookupResult = '';
                        if (!this.newPlayer.phone || this.newPlayer.phone.length < 5) return;
                    },

                    insertPlayerCard(tournamentId, player) {
                        const container = document.getElementById('roster-tournament-' + tournamentId);
                        if (!container) return;

                        // Remove empty message if present
                        const emptyMsg = container.querySelector('.empty-message');
                        if (emptyMsg) emptyMsg.remove();

                        // Get or create the space-y-2 wrapper
                        let wrapper = container.querySelector('.space-y-2');
                        if (!wrapper) {
                            wrapper = document.createElement('div');
                            wrapper.className = 'space-y-2';
                            container.appendChild(wrapper);
                        }

                        const avatarUrl = player.image
                            ? player.image
                            : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(player.name) + '&color=7F9CF5&background=EBF4FF';

                        const card = document.createElement('div');
                        card.className = 'flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg player-card';
                        card.setAttribute('data-player-id', player.id);
                        const escapedName = player.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        card.innerHTML = `
                            <div class="flex items-center gap-3">
                                <img class="h-10 w-10 rounded-full object-cover" src="${avatarUrl}" alt="${escapedName}">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white text-sm">${player.name}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">${player.phone || 'No phone'}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="p-1.5 text-red-500 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50 remove-player-btn" title="Remove player"
                                    data-player-id="${player.id}" data-player-name="${escapedName}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>`;
                        // Bind remove handler
                        card.querySelector('.remove-player-btn').addEventListener('click', () => {
                            this.removePlayer(player.id, player.name);
                        });
                        wrapper.appendChild(card);

                        // Update badge count
                        this.playerCounts[tournamentId] = (this.playerCounts[tournamentId] || 0) + 1;

                        // Expand the accordion if collapsed
                        this.activeAccordions[tournamentId] = true;
                    },

                    async savePlayer() {
                        if (this.addMode === 'new') {
                            if (!this.newPlayer.name.trim()) {
                                this.error = 'Player name is required.';
                                return;
                            }
                            if (!this.newPlayer.email.trim()) {
                                this.error = 'Email address is required.';
                                return;
                            }
                            if (!this.newPlayer.phone.trim()) {
                                this.error = 'Phone number is required.';
                                return;
                            }
                        } else {
                            if (!this.selectedExistingPlayerId) {
                                this.error = 'Please select a player from the squad.';
                                return;
                            }
                        }

                        this.saving = true;
                        this.error = '';

                        try {
                            const formData = new FormData();

                            if (this.addMode === 'existing') {
                                formData.append('existing_player_id', this.selectedExistingPlayerId);
                            } else {
                                formData.append('name', this.newPlayer.name);
                                formData.append('email', this.newPlayer.email);
                                formData.append('phone', this.newPlayer.phone);
                                if (this.imageFile) {
                                    formData.append('player_image', this.imageFile);
                                }
                            }

                            // Build tournament assignments
                            let idx = 0;
                            for (const [tournamentId, teamId] of Object.entries(this.newPlayer.assignments)) {
                                formData.append(`tournament_assignments[${idx}][tournament_id]`, tournamentId);
                                formData.append(`tournament_assignments[${idx}][team_id]`, teamId);
                                if (this.playerRole) {
                                    formData.append(`tournament_assignments[${idx}][role]`, this.playerRole);
                                }
                                idx++;
                            }

                            const res = await fetch('{{ route("admin.actual-teams.add-player", $actualTeam->id) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: formData
                            });

                            const data = await res.json();

                            if (data.success) {
                                // Insert player card into all assigned tournament accordions
                                for (const [tournamentId, teamId] of Object.entries(this.newPlayer.assignments)) {
                                    this.insertPlayerCard(parseInt(tournamentId), data.player);
                                }
                                this.showDrawer = false;
                            } else {
                                this.error = data.message || 'Failed to add player.';
                            }
                        } catch (e) {
                            this.error = 'An error occurred. Please try again.';
                            console.error(e);
                        }

                        this.saving = false;
                    },

                    async removePlayer(playerId, playerName) {
                        if (!confirm(`Remove ${playerName} from this team?`)) return;

                        try {
                            const res = await fetch(`/admin/actual-teams/{{ $actualTeam->id }}/players/${playerId}`, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });

                            const data = await res.json();

                            if (data.success) {
                                // Remove player cards from DOM and update counts
                                document.querySelectorAll(`.player-card[data-player-id="${playerId}"]`).forEach(card => {
                                    const container = card.closest('[id^="roster-tournament-"]');
                                    if (container) {
                                        const tId = parseInt(container.id.replace('roster-tournament-', ''));
                                        this.playerCounts[tId] = Math.max(0, (this.playerCounts[tId] || 1) - 1);

                                        card.remove();

                                        // Show empty message if no more players
                                        const wrapper = container.querySelector('.space-y-2');
                                        if (wrapper && wrapper.children.length === 0) {
                                            wrapper.remove();
                                            const msg = document.createElement('p');
                                            msg.className = 'text-sm text-gray-400 italic empty-message';
                                            msg.textContent = 'No players assigned for this tournament.';
                                            container.appendChild(msg);
                                        }
                                    }
                                });
                            } else {
                                alert(data.message || 'Failed to remove player.');
                            }
                        } catch (e) {
                            alert('An error occurred. Please try again.');
                            console.error(e);
                        }
                    }
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

                    async resendCredentials(manager) {
                        if (!confirm(`Send credentials email to ${manager.email}? This will reset their password.`)) return;

                        manager.sending = true;
                        try {
                            const res = await fetch(`/admin/actual-teams/{{ $actualTeam->id }}/team-manager/${manager.id}/resend-credentials`, {
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
                                alert(data.message || 'Credentials email sent!');
                            } else {
                                alert(data.message || 'Failed to send credentials.');
                            }
                        } catch (e) {
                            alert('An error occurred. Please try again.');
                            console.error(e);
                        }
                        manager.sending = false;
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
                    const container = document.getElementById('current-squad-container');

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
                                `{{ route('admin.actual-teams.delete-member', [$actualTeam->id, 'USER_ID']) }}`
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
