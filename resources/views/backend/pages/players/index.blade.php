@extends('backend.layouts.app')

@section('title', 'Players | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6 lg:p-8" x-data="{ selectedPlayers: [], selectAll: false }">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Player Management</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Search, filter, and manage all players.</p>
            </div>
            @can('player.create')
                <a href="{{ route('admin.players.create') }}" class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add New Player
                </a>
            @endcan
        </div>

        <!-- Filter Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6 border border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ route('admin.players.index') }}">
                {{-- Increased grid columns to accommodate new filters --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
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
                                <option value="verified" @selected(request('status') == 'verified')>Verified</option>
                                <option value="pending" @selected(request('status') == 'pending')>Pending</option>
                            </select>
                        </div>
                    @endif
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
                class="bg-blue-100 dark:bg-blue-900/50 border border-blue-300 dark:border-blue-700 rounded-lg p-3 mb-6 flex items-center justify-between">
                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    <span x-text="selectedPlayers.length"></span> player(s) selected.
                </p>
                <form action="{{ route('admin.players.export') }}" method="POST">
                    @csrf
                    <template x-for="playerId in selectedPlayers" :key="playerId">
                        <input type="hidden" name="player_ids[]" :value="playerId">
                    </template>
                    <button type="submit" class="btn btn-primary btn-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export Selected
                    </button>
                </form>
            </div>
        @endif

        <!-- Players Table -->
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                                {{-- ADMIN & SUPERADMIN VIEW HEADERS --}}
                                <th scope="col" class="p-4"><input type="checkbox" class="form-checkbox"
                                        @click="selectAll = !selectAll; selectedPlayers = selectAll ? [...document.querySelectorAll('.player-checkbox')].map(cb => cb.value) : []">
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    ID</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Player</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Contact</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Team</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Last Updated</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            @else
                                {{-- OTHER ROLES (e.g., TEAM MANAGER) VIEW HEADERS --}}
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Player</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Location</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Player Role</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Stats</th>
                                <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($players as $player)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                                    {{-- ADMIN & SUPERADMIN VIEW CELLS --}}
                                    <td class="p-4"><input type="checkbox" class="form-checkbox player-checkbox"
                                            value="{{ $player->id }}" x-model="selectedPlayers"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-11 w-11"><img
                                                    class="h-11 w-11 rounded-full object-cover"
                                                    src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) }}"
                                                    alt="{{ $player->name }}"></div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $player->name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $player->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200"><svg
                                                    class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                                    </path>
                                                </svg><a href="tel:{{ $player->mobile_number_full }}"
                                                    class="hover:underline">+{{ $player->mobile_number_full }}</a></div>
                                            @if ($player->cricheroes_number_full)
                                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400"><svg
                                                        class="w-4 h-4 text-green-500" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg><span>+{{ $player->cricheroes_number_full }}</span></div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">
                                        @if ($player->team?->name === 'Others')
                                            {{ $player->team_name_ref ?? 'N/A' }}
                                        @else
                                            {{ $player->team?->name ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if (!is_null($player->welcome_email_sent_at))
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200">Verified</span>
                                        @else
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $player->updated_at->diffForHumans() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Menu is common --}}
                                        {{-- This is the code for the Kebab Menu --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" @click.away="open = false"
                                                class="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 5v.01M12 12v.01M12 19v.01" />
                                                </svg>
                                            </button>
                                            <div x-show="open" x-transition
                                                class="absolute z-20 w-48 right-0 mt-2 origin-top-right bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                                style="display: none;">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    @canany(['player.show', 'player.view'])
                                                        <a href="{{ route('admin.players.show', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            role="menuitem">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z">
                                                                </path>
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                                </path>
                                                            </svg>
                                                            View
                                                        </a>
                                                    @endcanany
                                                    @can('player.edit')
                                                        <a href="{{ route('admin.players.edit', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            role="menuitem">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                                                            </svg>
                                                            Edit
                                                        </a>
                                                    @endcan
                                                    @can('player.delete')
                                                        <form action="{{ route('admin.players.destroy', $player->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to delete this player?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-900/50"
                                                                role="menuitem">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-11 w-11"><img
                                                    class="h-11 w-11 rounded-full object-cover"
                                                    src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) }}"
                                                    alt="{{ $player->name }}"></div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $player->name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $player->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                            {{ $player->location?->name }}</div>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
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
                                    <td class="px-6 py-4 whitespace-nowrap">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Menu is common --}}
                                        {{-- This is the code for the Kebab Menu --}}
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" @click.away="open = false"
                                                class="p-2 text-gray-500 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 5v.01M12 12v.01M12 19v.01" />
                                                </svg>
                                            </button>
                                            <div x-show="open" x-transition
                                                class="absolute z-20 w-48 right-0 mt-2 origin-top-right bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                                style="display: none;">
                                                <div class="py-1" role="menu" aria-orientation="vertical">
                                                    @canany(['player.show', 'player.view'])
                                                        <a href="{{ route('admin.players.show', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            role="menuitem">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z">
                                                                </path>
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                                </path>
                                                            </svg>
                                                            View
                                                        </a>
                                                    @endcanany
                                                    @can('player.edit')
                                                        <a href="{{ route('admin.players.edit', $player->id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            role="menuitem">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 0l1.172 1.172a2 2 0 010 2.828L13 15l-4 1 1-4z" />
                                                            </svg>
                                                            Edit
                                                        </a>
                                                    @endcan
                                                    @can('player.delete')
                                                        <form action="{{ route('admin.players.destroy', $player->id) }}"
                                                            method="POST"
                                                            onsubmit="return confirm('Are you sure you want to delete this player?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-100 dark:hover:bg-red-900/50"
                                                                role="menuitem">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
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
                                <td colspan="{{ auth()->user()->hasAnyRole(['Superadmin', 'Admin'])? '8': '4' }}"
                                    class="px-6 py-10 text-center text-gray-500">
                                    <div class="max-w-md mx-auto">
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No Players Found
                                        </h3>
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
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                {{ $players->withQueryString()->links() }}
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
