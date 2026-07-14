@extends('backend.layouts.app')

@section('title', 'Players | ' . config('app.name'))

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="[['name' => 'Dashboard', 'route' => route('admin.dashboard')], ['name' => 'Players']]" />
    <div class="p-4 mx-auto md:p-6 lg:p-8" x-data="{ selectedPlayers: [], selectAll: false }">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Player Management</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Search, filter, and manage all players.</p>
            </div>
            @can('player.create')
                <a href="{{ route('admin.players.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                    <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                    Add New Player
                </a>
            @endcan
        </div>

        <!-- Filter Section -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-5 mb-6">
            <form method="GET" action="{{ route('admin.players.index') }}">
                {{-- Increased grid columns to accommodate new filters --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-4">
                    <div class="lg:col-span-2">
                        <label for="search"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                            placeholder="By name or email..." class="form-control mt-1">
                    </div>
                    <div>
                        <label for="team_name"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select name="team_name" id="team_name" class="form-control mt-1">
                            <option value="">All Teams</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->name }}" @selected(request('team_name') == $team->name)>{{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player
                            Role</label>
                        <select name="role" id="role" class="form-control mt-1">
                            <option value="">All Roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->type }}" @selected(request('role') == $role->type)>{{ $role->type }}
                                </option>
                            @endforeach
                            <option value="Wicket Keeper" @selected(request('role') == 'Wicket Keeper')>Wicket Keeper</option>
                        </select>
                    </div>

                    {{-- **NEW**: Batting Profile Filter --}}
                    <div>
                        <label for="batting_profile"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batting Style</label>
                        <select name="batting_profile" id="batting_profile" class="form-control mt-1">
                            <option value="">All Styles</option>
                            @foreach ($battingProfiles as $profile)
                                <option value="{{ $profile->style }}" @selected(request('batting_profile') == $profile->style)>{{ $profile->style }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- **NEW**: Bowling Profile Filter --}}
                    <div>
                        <label for="bowling_profile"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bowling Style</label>
                        <select name="bowling_profile" id="bowling_profile" class="form-control mt-1">
                            <option value="">All Styles</option>
                            @foreach ($bowlingProfiles as $profile)
                                <option value="{{ $profile->style }}" @selected(request('bowling_profile') == $profile->style)>{{ $profile->style }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if (!auth()->user()->hasRole('Team Manager'))
                        <div>
                            <label for="status"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" id="status" class="form-control mt-1">
                                <option value="">All Status</option>
                                <option value="approved" @selected(request('status') == 'approved')>Approved</option>
                                <option value="pending" @selected(request('status') == 'pending')>Pending</option>
                                <option value="rejected" @selected(request('status') == 'rejected')>Rejected</option>
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="player_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player
                            Mode</label>
                        <select name="player_mode" id="player_mode" class="form-control mt-1">
                            <option value="">All</option>
                            <option value="retained" @selected(request('player_mode') == 'retained')>Retained</option>
                            <option value="normal" @selected(request('player_mode') == 'normal')>Available</option>
                            <option value="sold" @selected(request('player_mode') == 'sold')>Sold</option>
                            <option value="Unsold" @selected(request('player_mode') == 'sold')>Unsold</option>
                        </select>
                    </div>

                    {{-- Tournament filter — Superadmin only (organizers are already org-scoped) --}}
                    @if (auth()->user()->hasRole('Superadmin'))
                        <div>
                            <label for="tournament" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
                            <select name="tournament" id="tournament" class="form-control mt-1">
                                <option value="">All Tournaments</option>
                                @foreach ($tournaments as $t)
                                    <option value="{{ $t->id }}" @selected(request('tournament') == $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Sort by --}}
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort by</label>
                        <select name="sort" id="sort" class="form-control mt-1">
                            <option value="recently_updated" @selected(request('sort') == 'recently_updated' || request('sort') == '')>Recently updated</option>
                            <option value="name_asc" @selected(request('sort') == 'name_asc')>Name (A–Z)</option>
                            <option value="name_desc" @selected(request('sort') == 'name_desc')>Name (Z–A)</option>
                            <option value="newest" @selected(request('sort') == 'newest')>Newest</option>
                            <option value="oldest" @selected(request('sort') == 'oldest')>Oldest</option>
                        </select>
                    </div>

                    <div class="flex items-end space-x-2 pt-2">
                        <button type="submit" class="btn btn-primary w-full sm:w-auto">Filter</button>
                        <a href="{{ route('admin.players.index') }}" class="btn btn-secondary w-full sm:w-auto">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
            <div x-show="selectedPlayers.length > 0" x-cloak
                class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-xl p-3 mb-6 flex items-center justify-between">
                <p class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                    <span x-text="selectedPlayers.length"></span> player(s) selected.
                </p>
                <form action="{{ route('admin.players.export') }}" method="POST">
                    @csrf
                    <template x-for="playerId in selectedPlayers" :key="playerId">
                        <input type="hidden" name="player_ids[]" :value="playerId">
                    </template>
                    <button type="submit" class="btn btn-primary btn-sm inline-flex items-center gap-2">
                        <iconify-icon icon="lucide:download" width="16"></iconify-icon>
                        Export Selected
                    </button>
                </form>
            </div>
        @endif

        <!-- Players Table -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-white/[0.03]">
                            @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                                {{-- ADMIN & SUPERADMIN VIEW HEADERS --}}
                                <th scope="col" class="py-3 px-5">
                                    <input type="checkbox"
                                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                        @click="selectAll = !selectAll; selectedPlayers = selectAll ? [...document.querySelectorAll('.player-checkbox')].map(cb => cb.value) : []">
                                </th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    ID</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Player</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Contact</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Team</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Last Updated</th>
                                <th scope="col" class="relative py-3 px-5"><span class="sr-only">Actions</span></th>
                            @else
                                {{-- OTHER ROLES (e.g., TEAM MANAGER) VIEW HEADERS --}}
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Player</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Location</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Player Role</th>
                                <th scope="col"
                                    class="py-3 px-5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Stats</th>
                                <th scope="col" class="relative py-3 px-5"><span class="sr-only">Actions</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($players as $player)
                            <tr class="group hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors duration-150">
                                @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                                    {{-- ADMIN & SUPERADMIN VIEW CELLS --}}
                                    <td class="px-5 py-3.5">
                                        <input type="checkbox"
                                            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 player-checkbox"
                                            value="{{ $player->id }}" x-model="selectedPlayers">
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @php
                                            $orgName = $player->user?->organization?->name;
                                            $prefix = $orgName
                                                ? strtoupper(
                                                    substr(explode(' ', $orgName)[0] ?? '', 0, 1) .
                                                        substr(explode(' ', $orgName)[1] ?? '', 0, 1),
                                                )
                                                : '';
                                        @endphp
                                        {{ $prefix ? $prefix . '-' . $player->id : $player->id }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center">

                                            {{-- Avatar with Verified Badge --}}
                                            <div class="flex-shrink-0 h-11 w-11 relative">
                                                <img class="h-11 w-11 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700"
                                                    src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) . '&background=EBF4FF&color=7F9CF5' }}"
                                                    alt="{{ $player->name }}">

                                                @if ($player->status === 'approved')
                                                    <span
                                                        class="absolute -bottom-0.5 -right-0.5 block rounded-full bg-blue-600 border-2 border-white dark:border-gray-800"
                                                        title="Approved Player">
                                                        <svg class="w-4 h-4 text-white" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Name, Badges, and Email --}}
                                            <div class="ml-4">
                                                <div class="flex items-center gap-2">
                                                    {{-- Player name as a link --}}
                                                    <a href="{{ route('admin.players.show', $player->id) }}"
                                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors duration-150">
                                                        {{ $player->name }}
                                                    </a>

                                                    {{-- **NEW**: Retained Status Badge --}}
                                                    @if ($player->player_status == 'Retained')
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                            Retained
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Email as a clickable mailto link --}}
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <iconify-icon icon="lucide:mail" width="12" class="text-gray-400"></iconify-icon>
                                                    <a href="mailto:{{ $player->email }}"
                                                        class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                                                        {{ $player->email }}
                                                    </a>
                                                </div>
                                                {{-- Retained Status Badge --}}
                                                @if ($player->player_mode == 'retained')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                        Retained
                                                    </span>
                                                @endif

                                                {{-- Sold Status Badge --}}
                                                @if ($player->player_mode === 'sold' && $player->actualTeam)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                        Sold to {{ $player->actualTeam->name }}
                                                    </span>
                                                @endif

                                                {{-- Registered tournaments as tags --}}
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @forelse ($player->registeredTournaments as $rt)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                                                              title="Registered: {{ $rt->name }}">
                                                            {{ \Illuminate\Support\Str::limit($rt->name, 18) }}
                                                        </span>
                                                    @empty
                                                        <span class="text-xs text-gray-400">— no tournament</span>
                                                    @endforelse
                                                </div>
                                            </div>

                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm">
                                        <div class="space-y-1">
                                            @if ($player->mobile_number_full)
                                            <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                                <iconify-icon icon="lucide:phone" width="14" class="text-blue-500"></iconify-icon>
                                                <a href="tel:{{ $player->mobile_number_full }}"
                                                    class="hover:underline">+{{ ltrim($player->mobile_number_full, '+') }}</a>
                                            </div>
                                            @endif
                                            @if ($player->cricheroes_number_full)
                                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                                    <iconify-icon icon="lucide:check-circle" width="14" class="text-green-500"></iconify-icon>
                                                    <span>+{{ ltrim($player->cricheroes_number_full, '+') }}</span>
                                                </div>
                                            @endif
                                            @if (!$player->mobile_number_full && !$player->cricheroes_number_full)
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">
                                        {{ $player->display_team_name ?? 'N/A' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        @if ($player->status === 'approved')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400">Approved</span>
                                        @elseif ($player->status === 'rejected')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400">Rejected</span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $player->updated_at->diffForHumans() }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Kebab Menu --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" @click.away="open = false"
                                                class="p-2 text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                                                <iconify-icon icon="lucide:more-vertical" width="18"></iconify-icon>
                                            </button>
                                            <div x-show="open" x-transition
                                                class="absolute z-20 w-48 right-0 mt-2 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 focus:outline-none"
                                                style="display: none;">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    @canany(['player.show', 'player.view'])
                                                        <a href="{{ route('admin.players.show', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:eye" width="16" class="text-gray-400"></iconify-icon>
                                                            View
                                                        </a>
                                                    @endcanany
                                                    @can('player.edit')
                                                        <a href="{{ route('admin.players.edit', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:pencil" width="16" class="text-gray-400"></iconify-icon>
                                                            Edit
                                                        </a>
                                                        @if ($player->status === 'approved' && $player->player_mode !== 'retained')
                                                            <button type="button"
                                                                @click="open = false; $dispatch('open-retain-modal', { playerId: {{ $player->id }}, playerName: '{{ addslashes($player->name) }}' })"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors duration-150"
                                                                role="menuitem">
                                                                <iconify-icon icon="lucide:lock" width="16"></iconify-icon>
                                                                Retain Player
                                                            </button>
                                                        @elseif ($player->player_mode === 'retained')
                                                            <form action="{{ route('admin.players.unretain', $player->id) }}" method="POST"
                                                                onsubmit="return confirm('Remove retention for {{ addslashes($player->name) }}?')">
                                                                @csrf
                                                                <button type="submit"
                                                                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors duration-150"
                                                                    role="menuitem">
                                                                    <iconify-icon icon="lucide:unlock" width="16"></iconify-icon>
                                                                    Remove Retention
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('player.delete')
                                                        <form action="{{ route('admin.players.destroy', $player->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to delete this player?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors duration-150"
                                                                role="menuitem">
                                                                <iconify-icon icon="lucide:trash-2" width="16"></iconify-icon>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    {{-- OTHER ROLES (e.g., TEAM MANAGER) VIEW CELLS --}}
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center">

                                            {{-- Avatar with Verified Badge --}}
                                            <div class="flex-shrink-0 h-11 w-11 relative">
                                                <img class="h-11 w-11 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700"
                                                    src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) . '&background=EBF4FF&color=7F9CF5' }}"
                                                    alt="{{ $player->name }}">

                                                @if ($player->status === 'approved')
                                                    <span
                                                        class="absolute -bottom-0.5 -right-0.5 block rounded-full bg-blue-600 border-2 border-white dark:border-gray-800"
                                                        title="Approved Player">
                                                        <svg class="w-4 h-4 text-white" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Name, Badges, and Email --}}
                                            <div class="ml-4">
                                                <div class="flex items-center gap-2">
                                                    {{-- Player name as a link --}}
                                                    <a href="{{ route('admin.players.show', $player->id) }}"
                                                        class="text-sm font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors duration-150">
                                                        {{ $player->name }}
                                                    </a>

                                                    {{-- Retained Status Badge --}}
                                                    @if ($player->player_mode === 'retained')
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                            Retained
                                                        </span>
                                                    @endif

                                                    {{-- Sold Status Badge --}}
                                                    @if ($player->player_mode === 'sold' && $player->actualTeam)
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                            Sold to {{ $player->actualTeam->name }}
                                                        </span>
                                                    @endif

                                                </div>

                                                {{-- Email as a clickable mailto link --}}
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <iconify-icon icon="lucide:mail" width="12" class="text-gray-400"></iconify-icon>
                                                    <a href="mailto:{{ $player->email }}"
                                                        class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                                                        {{ $player->email }}
                                                    </a>
                                                </div>
                                                {{-- Retained Status Badge with team --}}
                                                @if ($player->player_mode == 'retained')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                        Retained
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold">
                                                        in
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-gray-800 ring-purple-600/10 dark:bg-purple-500/10 dark:text-red-200">
                                                        {{ $player->display_team_name ?? 'N/A' }}
                                                    </span>
                                                @endif

                                            </div>

                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                            {{ $player->location?->name }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex flex-col items-start space-y-2">

                                            {{-- Primary Role (e.g., Batsman, Bowler) --}}
                                            <div class="flex items-center gap-2" title="Primary Role">
                                                <span
                                                    class="text-sm font-semibold text-gray-900 dark:text-white">{{ $player->playerType?->type ?? 'N/A' }}</span>
                                            </div>

                                            {{-- Secondary Skills (Batting, Bowling, WK) --}}
                                            <div class="flex flex-wrap items-center gap-2">
                                                {{-- Batting Style Badge --}}
                                                @if ($player->battingProfile?->style)
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200"
                                                        title="Batting: {{ $player->battingProfile->style }}">
                                                        <img src="{{ asset('images/icons/bat.svg') }}" alt="Batting"
                                                            class="w-4 h-4 dark:invert">
                                                        <span>{{ $player->battingProfile->style }}</span>
                                                    </div>
                                                @endif

                                                {{-- Bowling Style Badge --}}
                                                @if ($player->bowlingProfile?->style)
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200"
                                                        title="Bowling: {{ $player->bowlingProfile->style }}">
                                                        <img src="{{ asset('images/icons/ball.svg') }}" alt="Bowling"
                                                            class="w-4 h-4 dark:invert">
                                                        <span>{{ $player->bowlingProfile->style }}</span>
                                                    </div>
                                                @endif

                                                {{-- Wicket Keeper Badge --}}
                                                @if ($player->is_wicket_keeper)
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200"
                                                        title="Wicket Keeper">
                                                        <img src="{{ asset('images/icons/wicket.svg') }}"
                                                            alt="Wicket Keeper" class="w-4 h-4 dark:invert">
                                                        <span>WK</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-300 space-x-4">
                                            {{-- Matches --}}
                                            <div class="text-center" title="Matches Played">
                                                <span
                                                    class="block font-bold text-gray-900 dark:text-white">{{ $player->total_matches ?? 0 }}</span>
                                                <span class="text-xs text-gray-500 uppercase">Matches</span>
                                            </div>

                                            <div class="border-l border-gray-200 dark:border-gray-600 h-6"></div>

                                            {{-- Runs --}}
                                            <div class="text-center" title="Total Runs Scored">
                                                <span
                                                    class="block font-bold text-gray-900 dark:text-white">{{ $player->total_runs ?? 0 }}</span>
                                                <span class="text-xs text-gray-500 uppercase">Runs</span>
                                            </div>

                                            <div class="border-l border-gray-200 dark:border-gray-600 h-6"></div>

                                            {{-- Wickets --}}
                                            <div class="text-center" title="Total Wickets Taken">
                                                <span
                                                    class="block font-bold text-gray-900 dark:text-white">{{ $player->total_wickets ?? 0 }}</span>
                                                <span class="text-xs text-gray-500 uppercase">Wickets</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Kebab Menu --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" @click.away="open = false"
                                                class="p-2 text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                                                <iconify-icon icon="lucide:more-vertical" width="18"></iconify-icon>
                                            </button>
                                            <div x-show="open" x-transition
                                                class="absolute z-20 w-48 right-0 mt-2 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 focus:outline-none"
                                                style="display: none;">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    @canany(['player.show', 'player.view'])
                                                        <a href="{{ route('admin.players.show', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:eye" width="16" class="text-gray-400"></iconify-icon>
                                                            View
                                                        </a>
                                                    @endcanany
                                                    @can('player.edit')
                                                        <a href="{{ route('admin.players.edit', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:pencil" width="16" class="text-gray-400"></iconify-icon>
                                                            Edit
                                                        </a>
                                                        @if ($player->status === 'approved' && $player->player_mode !== 'retained')
                                                            <button type="button"
                                                                @click="open = false; $dispatch('open-retain-modal', { playerId: {{ $player->id }}, playerName: '{{ addslashes($player->name) }}' })"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors duration-150"
                                                                role="menuitem">
                                                                <iconify-icon icon="lucide:lock" width="16"></iconify-icon>
                                                                Retain Player
                                                            </button>
                                                        @elseif ($player->player_mode === 'retained')
                                                            <form action="{{ route('admin.players.unretain', $player->id) }}" method="POST"
                                                                onsubmit="return confirm('Remove retention for {{ addslashes($player->name) }}?')">
                                                                @csrf
                                                                <button type="submit"
                                                                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors duration-150"
                                                                    role="menuitem">
                                                                    <iconify-icon icon="lucide:unlock" width="16"></iconify-icon>
                                                                    Remove Retention
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                    @can('player.delete')
                                                        <form action="{{ route('admin.players.destroy', $player->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to delete this player?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors duration-150"
                                                                role="menuitem">
                                                                <iconify-icon icon="lucide:trash-2" width="16"></iconify-icon>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasAnyRole(['Superadmin', 'Admin']) ? '8' : '5' }}"
                                    class="px-5 py-16 text-center">
                                    <div class="flex flex-col items-center justify-center max-w-md mx-auto">
                                        <div class="flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-800 mb-4">
                                            <iconify-icon icon="lucide:users" width="32" class="text-gray-400 dark:text-gray-500"></iconify-icon>
                                        </div>
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white">No Players Found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No players matched your
                                            current filters. Try resetting them.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($players->hasPages())
                <div class="border-t border-gray-100 dark:border-gray-800 px-5 py-4">
                    {{ $players->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Retain Player Modal --}}
    <div x-data="{
            showRetainModal: false,
            retainPlayerId: null,
            retainPlayerName: '',
        }"
        @open-retain-modal.window="
            retainPlayerId = $event.detail.playerId;
            retainPlayerName = $event.detail.playerName;
            showRetainModal = true;
        "
    >
        <div x-show="showRetainModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Backdrop --}}
            <div x-show="showRetainModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50" @click="showRetainModal = false"></div>

            {{-- Modal --}}
            <div x-show="showRetainModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md z-10">
                <form method="POST" :action="'/admin/players/' + retainPlayerId + '/retain'">
                    @csrf
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <iconify-icon icon="lucide:lock" width="20" class="text-purple-500"></iconify-icon>
                            Retain Player
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Retaining <span class="font-medium text-gray-700 dark:text-gray-300" x-text="retainPlayerName"></span></p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team <span class="text-red-500">*</span></label>
                            <select name="actual_team_id" required class="form-control">
                                <option value="">-- Select Team --</option>
                                @foreach ($actualTeams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retained Value <span class="text-red-500">*</span></label>
                            <input type="number" name="retained_value" required min="0" step="any" placeholder="e.g. 500000" class="form-control">
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" @click="showRetainModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                            Retain Player
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- This is a placeholder for your Kebab Menu component.
     You would create this file at resources/views/components/buttons/kebab-menu.blade.php --}}
    @once
        @push('components')
            @verbatim
                <script>
                    function kebabMenuComponent(player) {
                        return {
                            open: false,
                            player: player,
                        }
                    }
                </script>
            @endverbatim
        @endpush
    @endonce

    @verbatim
        <script>
            // This is a simplified Kebab Menu component for demonstration
            // In a real app, you would have this in its own file.
            document.addEventListener('alpine:init', () => {
                Alpine.data('kebabMenu', (player) => ({
                    open: false,
                    player: player,
                    // Add routes here if needed, passed from the main view
                }));
            });
        </script>
    @endverbatim

@endsection
