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
            <form method="GET" action="{{ route('admin.players.index') }}" id="playerFilterForm">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-4">

                    {{-- Tournament — first, full width --}}
                    <div class="sm:col-span-2">
                        <label for="tournament" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament</label>
                        <select name="tournament" id="tournament" class="form-control mt-1" onchange="document.getElementById('playerFilterForm').submit()">
                            <option value="">All Tournaments</option>
                            @foreach ($tournaments as $t)
                                <option value="{{ $t->id }}" @selected(request('tournament') == $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search --}}
                    <div class="lg:col-span-2">
                        <label for="search"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                            placeholder="By name or email..." class="form-control mt-1">
                    </div>

                    {{-- Team (actual teams) --}}
                    <div>
                        <label for="actual_team_id"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
                        <select name="actual_team_id" id="actual_team_id" class="form-control mt-1">
                            <option value="">All Teams</option>
                            @foreach ($filterTeams as $ft)
                                <option value="{{ $ft->id }}" @selected(request('actual_team_id') == $ft->id)>{{ $ft->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Player Role --}}
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

                    {{-- Batting Style --}}
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

                    {{-- Bowling Style --}}
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

                    {{-- Status --}}
                    @if (!auth()->user()->hasRole('Team Manager'))
                        <div>
                            <label for="status"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" id="status" class="form-control mt-1">
                                <option value="all" @selected(request('status') == 'all')>All Status</option>
                                <option value="approved" @selected(request('status', 'approved') == 'approved')>Approved</option>
                                <option value="pending" @selected(request('status') == 'pending')>Pending</option>
                                <option value="queued" @selected(request('status') == 'queued')>Queued</option>
                                <option value="rejected" @selected(request('status') == 'rejected')>Rejected</option>
                            </select>
                        </div>
                    @endif

                    {{-- Player Mode --}}
                    <div>
                        <label for="player_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player
                            Mode</label>
                        <select name="player_mode" id="player_mode" class="form-control mt-1">
                            <option value="">All</option>
                            <option value="retained" @selected(request('player_mode') == 'retained')>Retained</option>
                            <option value="normal" @selected(request('player_mode') == 'normal')>Available</option>
                            <option value="sold" @selected(request('player_mode') == 'sold')>Sold</option>
                            <option value="Unsold" @selected(request('player_mode') == 'Unsold')>Unsold</option>
                        </select>
                    </div>

                    {{-- Source --}}
                    <div>
                        <label for="source" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source</label>
                        <select name="source" id="source" class="form-control mt-1">
                            <option value="">All</option>
                            <option value="registration" @selected(request('source') == 'registration')>Through Registration</option>
                            <option value="direct" @selected(request('source') == 'direct')>Direct Entry</option>
                        </select>
                    </div>

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

        {{-- Summary Cloud Tags --}}
        @if($players->total() > 0)
            @php
                $allItems = $players->getCollection();
                $totalShown = $players->total();

                // Player type counts
                $typeCounts = $allItems->groupBy(fn($p) => $p->playerType?->type ?? 'Unknown')->map->count()->sortDesc();
                // Wicket keeper count
                $wkCount = $allItems->where('is_wicket_keeper', true)->count();
                // Batting profile counts
                $batCounts = $allItems->groupBy(fn($p) => $p->battingProfile?->style)->filter(fn($v, $k) => $k)->map->count()->sortDesc();
                // Bowling profile counts
                $bowlCounts = $allItems->groupBy(fn($p) => $p->bowlingProfile?->style)->filter(fn($v, $k) => $k)->map->count()->sortDesc();
                // Status counts
                $statusCounts = $allItems->groupBy('status')->map->count();
                // Transportation required
                $transportCount = $allItems->where('transportation_required', true)->count();
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 p-4 mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <iconify-icon icon="lucide:bar-chart-3" width="16" class="text-gray-400"></iconify-icon>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Quick Filters ({{ $totalShown }} players)</h3>
                    @if(request()->hasAny(['role', 'batting_profile', 'bowling_profile', 'need_transport']))
                        <a href="{{ route('admin.players.index', request()->except(['role', 'batting_profile', 'bowling_profile', 'need_transport', 'page'])) }}" class="text-xs text-blue-600 hover:underline ml-auto">Clear tag filters</a>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    {{-- Player Types --}}
                    @foreach($typeCounts as $type => $count)
                        @php $isActive = request('role') === $type; @endphp
                        <a href="{{ route('admin.players.index', array_merge(request()->except('page'), ['role' => $type])) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-blue-600 text-white ring-2 ring-blue-400' : 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/10 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-400/20 hover:bg-blue-100' }}">
                            {{ $type }} <span class="{{ $isActive ? 'bg-blue-400 text-white' : 'bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-100' }} px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none">{{ $count }}</span>
                        </a>
                    @endforeach

                    {{-- Wicket Keeper --}}
                    @if($wkCount > 0)
                        @php $isActive = request('role') === 'Wicket Keeper'; @endphp
                        <a href="{{ route('admin.players.index', array_merge(request()->except('page'), ['role' => 'Wicket Keeper'])) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-orange-600 text-white ring-2 ring-orange-400' : 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/10 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-400/20 hover:bg-orange-100' }}">
                            Wicket Keeper <span class="{{ $isActive ? 'bg-orange-400 text-white' : 'bg-orange-200 dark:bg-orange-700 text-orange-800 dark:text-orange-100' }} px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none">{{ $wkCount }}</span>
                        </a>
                    @endif

                    {{-- Batting Profiles --}}
                    @foreach($batCounts as $style => $count)
                        @php $isActive = request('batting_profile') === $style; @endphp
                        <a href="{{ route('admin.players.index', array_merge(request()->except('page'), ['batting_profile' => $style])) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-indigo-600 text-white ring-2 ring-indigo-400' : 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/20 hover:bg-indigo-100' }}">
                            {{ $style }} <span class="{{ $isActive ? 'bg-indigo-400 text-white' : 'bg-indigo-200 dark:bg-indigo-700 text-indigo-800 dark:text-indigo-100' }} px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none">{{ $count }}</span>
                        </a>
                    @endforeach

                    {{-- Bowling Profiles --}}
                    @foreach($bowlCounts as $style => $count)
                        @php $isActive = request('bowling_profile') === $style; @endphp
                        <a href="{{ route('admin.players.index', array_merge(request()->except('page'), ['bowling_profile' => $style])) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-green-600 text-white ring-2 ring-green-400' : 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/10 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-400/20 hover:bg-green-100' }}">
                            {{ $style }} <span class="{{ $isActive ? 'bg-green-400 text-white' : 'bg-green-200 dark:bg-green-700 text-green-800 dark:text-green-100' }} px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none">{{ $count }}</span>
                        </a>
                    @endforeach

                    {{-- Transportation --}}
                    @if($transportCount > 0)
                        @php $isActive = request('need_transport') === '1'; @endphp
                        <a href="{{ route('admin.players.index', array_merge(request()->except('page'), ['need_transport' => '1'])) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer {{ $isActive ? 'bg-purple-600 text-white ring-2 ring-purple-400' : 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-300 dark:ring-purple-400/20 hover:bg-purple-100' }}">
                            Need Transport <span class="{{ $isActive ? 'bg-purple-400 text-white' : 'bg-purple-200 dark:bg-purple-700 text-purple-800 dark:text-purple-100' }} px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none">{{ $transportCount }}</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

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
                <table class="min-w-full table-fixed">
                    <thead>
                        <tr class="bg-gray-50/80 dark:bg-white/[0.03]">
                            @if (auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                                {{-- ADMIN & SUPERADMIN VIEW HEADERS --}}
                                <th scope="col" class="py-3 px-3 w-10">
                                    <input type="checkbox"
                                        class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600"
                                        @click="selectAll = !selectAll; selectedPlayers = selectAll ? [...document.querySelectorAll('.player-checkbox')].map(cb => cb.value) : []">
                                </th>
                                <th scope="col"
                                    class="py-3 px-3 w-20 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    ID</th>
                                <th scope="col"
                                    class="py-3 px-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" style="min-width: 280px;">
                                    Player</th>
                                <th scope="col"
                                    class="py-3 px-4 w-40 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Contact</th>
                                <th scope="col"
                                    class="py-3 px-4 w-36 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Team</th>
                                <th scope="col"
                                    class="py-3 px-3 w-16 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Verified</th>
                                <th scope="col"
                                    class="py-3 px-3 w-24 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Status</th>
                                <th scope="col"
                                    class="py-3 px-3 w-28 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Last Updated</th>
                                <th scope="col" class="relative py-3 px-3 w-20"><span class="sr-only">Actions</span></th>
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
                                    <td class="px-3 py-3.5">
                                        <input type="checkbox"
                                            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 player-checkbox"
                                            value="{{ $player->id }}" x-model="selectedPlayers">
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
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
                                    <td class="px-4 py-3.5">
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

                                                    @if ($player->player_mode == 'retained')
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                            Retained
                                                        </span>
                                                    @endif
                                                    @if ($player->created_by)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-600 ring-slate-500/10 dark:bg-slate-500/10 dark:text-slate-400" title="Added by {{ $player->creator?->name ?? 'Admin' }}">
                                                            Direct Entry
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-teal-50 text-teal-700 ring-teal-600/10 dark:bg-teal-500/10 dark:text-teal-400">
                                                            Through Registration
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Email as a clickable mailto link --}}
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <iconify-icon icon="lucide:mail" width="12" class="text-gray-400"></iconify-icon>
                                                    <a href="mailto:{{ $player->email ?: $player->user->email ?? '' }}"
                                                        class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                                                        {{ $player->email ?: $player->user->email ?? '' }}
                                                    </a>
                                                </div>

                                                {{-- Player type & style badges --}}
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @if ($player->playerType?->type)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $player->playerType->type }}</span>
                                                    @endif
                                                    @if ($player->is_wicket_keeper)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">Wicket Keeper</span>
                                                    @endif
                                                    @if ($player->battingProfile?->style)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                                                    @endif
                                                    @if ($player->bowlingProfile?->style)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                                                    @endif
                                                </div>

                                                {{-- Sold Status Badge --}}
                                                @if ($player->player_mode === 'sold' && $player->actualTeam)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 mt-1">
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
                                    <td class="px-3 py-3.5 whitespace-nowrap text-sm">
                                        @php
                                            $mobileDisplay = $player->mobile_number_full
                                                ?: (($player->mobile_country_code && $player->mobile_national_number)
                                                    ? str_replace('+', '', $player->mobile_country_code) . $player->mobile_national_number
                                                    : null);
                                            $cricDisplay = $player->cricheroes_number_full
                                                ?: (($player->cricheroes_country_code && $player->cricheroes_national_number)
                                                    ? str_replace('+', '', $player->cricheroes_country_code) . $player->cricheroes_national_number
                                                    : null);
                                        @endphp
                                        <div class="space-y-1">
                                            @if ($mobileDisplay)
                                            <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                                <iconify-icon icon="lucide:phone" width="14" class="text-blue-500"></iconify-icon>
                                                <a href="tel:{{ $mobileDisplay }}"
                                                    class="hover:underline">+{{ ltrim($mobileDisplay, '+') }}</a>
                                            </div>
                                            @endif
                                            @if ($cricDisplay)
                                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                                    <iconify-icon icon="lucide:check-circle" width="14" class="text-green-500"></iconify-icon>
                                                    <span>+{{ ltrim($cricDisplay, '+') }}</span>
                                                </div>
                                            @endif
                                            @if (!$mobileDisplay && !$cricDisplay)
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">
                                        {{ ($player->actualTeam?->name === 'Others' && $player->playing_team_name_ref) ? $player->playing_team_name_ref : ($player->actualTeam?->name ?? ($player->playing_team_name_ref ?: ($player->team?->name === 'Others' ? ($player->team_name_ref ?? 'Others') : ($player->team?->name ?? 'N/A')))) }}
                                        @if($player->actualTeam && $player->team && $player->actualTeam->name !== $player->team->name && $player->team->name !== 'Others')
                                            <div class="text-[10px] text-gray-400 dark:text-gray-500">Reg: {{ $player->team->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        @php
                                            $reg = $player->registrations->first();
                                            $regVerified = (array) ($reg?->verified_fields ?? []);
                                            $regSettings = $reg?->tournament?->settings;
                                            $vLayout = \App\Helpers\PlayerFormConfig::getFormLayout($regSettings, false);
                                            $vCustom = $reg?->tournament?->customFields?->where('form', 'player')->where('visible', true) ?? collect();
                                            $skip = ['name', 'image', 'terms_and_conditions'];
                                            $vTotal = 0; $vDone = 0;
                                            if ($player->image_path) { $vTotal++; if (in_array('image', $regVerified, true)) $vDone++; }
                                            foreach ($vLayout as $sec) {
                                                foreach ($sec['fields'] as $fk) {
                                                    if (in_array($fk, $skip, true)) continue;
                                                    $vTotal++;
                                                    if (in_array($fk, $regVerified, true)) $vDone++;
                                                }
                                                foreach (($vCustom->where('section', $sec['key']) ?? collect()) as $scf) {
                                                    $vTotal++;
                                                    if (in_array('cf_' . $scf->id, $regVerified, true)) $vDone++;
                                                }
                                            }
                                            $pct = $vTotal > 0 ? round(($vDone / $vTotal) * 100) : 0;
                                            $radius = 16;
                                            $circumference = 2 * 3.14159 * $radius;
                                            $offset = $circumference - ($pct / 100) * $circumference;
                                            $color = $pct === 100 ? '#22c55e' : ($pct > 70 ? '#eab308' : ($pct > 40 ? '#f97316' : '#ef4444'));
                                        @endphp
                                        <div class="flex items-center justify-center">
                                            <svg width="42" height="42" viewBox="0 0 42 42">
                                                <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                                                <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="{{ $color }}" stroke-width="3"
                                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                                                    stroke-linecap="round" transform="rotate(-90 21 21)"/>
                                                <text x="21" y="21" text-anchor="middle" dominant-baseline="central"
                                                    class="fill-gray-700 dark:fill-gray-200" style="font-size: 10px; font-weight: 600;">{{ $pct }}%</text>
                                            </svg>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            @if ($player->status === 'approved')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400">Player Approved</span>
                                            @elseif ($player->status === 'rejected')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-500/10 dark:text-red-400">Player Rejected</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400">Player Pending</span>
                                            @endif
                                            @if($player->user?->hasRole('Team Manager'))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-indigo-50 text-indigo-700 ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-400">Team Manager</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $player->updated_at->diffForHumans() }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Kebab Menu --}}
                                        <div x-data="{ open: false, menuStyle: '' }" class="relative">
                                            <button @click="open = !open; if(open) { let r = $el.getBoundingClientRect(); menuStyle = 'position:fixed;top:'+(r.bottom+4)+'px;right:'+(window.innerWidth-r.right)+'px;z-index:50;'; }" @click.away="open = false"
                                                class="p-2 text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                                                <iconify-icon icon="lucide:more-vertical" width="18"></iconify-icon>
                                            </button>
                                            <div x-show="open" x-transition :style="menuStyle"
                                                class="w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 focus:outline-none"
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
                                                    @if($player->user_id && auth()->user()->can('user.login_as') && $player->user_id !== auth()->id())
                                                        <a href="{{ route('admin.users.login-as', $player->user_id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:log-in" width="16" class="text-gray-400"></iconify-icon>
                                                            Login as Player
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    {{-- OTHER ROLES (e.g., TEAM MANAGER) VIEW CELLS --}}
                                    <td class="px-4 py-3.5">
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

                                                    @if ($player->player_mode == 'retained')
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-500/10 dark:text-purple-400">
                                                            Retained
                                                        </span>
                                                    @endif
                                                    @if ($player->created_by)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-slate-50 text-slate-600 ring-slate-500/10 dark:bg-slate-500/10 dark:text-slate-400" title="Added by {{ $player->creator?->name ?? 'Admin' }}">
                                                            Direct Entry
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset bg-teal-50 text-teal-700 ring-teal-600/10 dark:bg-teal-500/10 dark:text-teal-400">
                                                            Through Registration
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Email as a clickable mailto link --}}
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <iconify-icon icon="lucide:mail" width="12" class="text-gray-400"></iconify-icon>
                                                    <a href="mailto:{{ $player->email ?: $player->user->email ?? '' }}"
                                                        class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                                                        {{ $player->email ?: $player->user->email ?? '' }}
                                                    </a>
                                                </div>

                                                {{-- Player type & style badges --}}
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @if ($player->playerType?->type)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $player->playerType->type }}</span>
                                                    @endif
                                                    @if ($player->is_wicket_keeper)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">Wicket Keeper</span>
                                                    @endif
                                                    @if ($player->battingProfile?->style)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                                                    @endif
                                                    @if ($player->bowlingProfile?->style)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                                                    @endif
                                                </div>

                                                {{-- Sold Status Badge --}}
                                                @if ($player->player_mode === 'sold' && $player->actualTeam)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset bg-emerald-50 text-emerald-700 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 mt-1">
                                                        Sold to {{ $player->actualTeam->name }}
                                                    </span>
                                                @endif

                                            </div>

                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
                                            {{ $player->location?->name }}</div>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">
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
                                    <td class="px-3 py-3.5 whitespace-nowrap">
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
                                    <td class="px-3 py-3.5 whitespace-nowrap text-right text-sm font-medium">
                                        {{-- Actions Kebab Menu --}}
                                        <div x-data="{ open: false, menuStyle: '' }" class="relative">
                                            <button @click="open = !open; if(open) { let r = $el.getBoundingClientRect(); menuStyle = 'position:fixed;top:'+(r.bottom+4)+'px;right:'+(window.innerWidth-r.right)+'px;z-index:50;'; }" @click.away="open = false"
                                                class="p-2 text-gray-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                                                <iconify-icon icon="lucide:more-vertical" width="18"></iconify-icon>
                                            </button>
                                            <div x-show="open" x-transition :style="menuStyle"
                                                class="w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 focus:outline-none"
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
                                                    @if($player->user_id && auth()->user()->can('user.login_as') && $player->user_id !== auth()->id())
                                                        <a href="{{ route('admin.users.login-as', $player->user_id) }}"
                                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                                                            role="menuitem">
                                                            <iconify-icon icon="lucide:log-in" width="16" class="text-gray-400"></iconify-icon>
                                                            Login as Player
                                                        </a>
                                                    @endif
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
            selectedTournament: '',
            allTeams: @js($actualTeams->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'tournament_ids' => collect([$t->tournament_id])->merge($t->tournaments->pluck('id'))->filter()->unique()->values(),
            ])),
            allTournaments: @js(
                $actualTeams->flatMap(fn($t) => $t->tournaments->map(fn($tr) => ['id' => $tr->id, 'name' => $tr->name]))
                    ->unique('id')->sortByDesc('id')->values()
            ),
            get filteredTeams() {
                if (!this.selectedTournament) return this.allTeams;
                const tid = parseInt(this.selectedTournament);
                return this.allTeams.filter(t => t.tournament_ids.includes(tid));
            }
        }"
        @open-retain-modal.window="
            retainPlayerId = $event.detail.playerId;
            retainPlayerName = $event.detail.playerName;
            selectedTournament = '';
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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tournament</label>
                            <select x-model="selectedTournament" class="form-control">
                                <option value="">-- All Tournaments --</option>
                                <template x-for="t in allTournaments" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team <span class="text-red-500">*</span></label>
                            <select name="actual_team_id" required class="form-control">
                                <option value="">-- Select Team --</option>
                                <template x-for="t in filteredTeams" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div x-data="{ retainVal: '' }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retained Value <span class="text-red-500">*</span></label>
                            <input type="number" name="retained_value" required min="0" step="any" placeholder="e.g. 500000" class="form-control" x-model="retainVal">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="retainVal > 0" x-text="(retainVal / 1000000).toFixed(2) + 'M'"></p>
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
