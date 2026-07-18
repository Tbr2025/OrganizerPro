@extends('backend.layouts.app')

@section('title', $actualTeam->name . ' | Team Details')

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="[
        ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
        ['name' => 'Teams'],
        ['name' => 'Team Details']
    ]" />

    @php
        $isTeamManagerView = auth()->user()?->hasAnyRole(['Team Manager', 'Team Owner']) && !auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Organizer']);
    @endphp

    <div class="p-4 mx-auto  md:p-6 lg:p-8">

        {{-- HEADER / HERO SECTION --}}
        <div class="relative bg-gray-800 dark:bg-gray-900 rounded-lg shadow-lg p-6 mb-8 text-white overflow-hidden">
            {{-- Background decorative pattern --}}
            <div class="absolute inset-0 opacity-5">
                <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width=".5"
                        d="M3 5.25h18m-18 4.5h18m-18 4.5h18m-18 4.5h18M5.25 3v18m4.5-18v18m4.5-18v18m4.5-18v18" />
                </svg>
            </div>

            <div class="relative flex flex-col md:flex-row items-center gap-6">
                {{-- Team Logo --}}
                <div class="flex-shrink-0">
                    {{-- TODO: Replace with your actual logo logic --}}
                    @if ($actualTeam->team_logo)
                        <img class="h-24 w-24 object-cover rounded-full border-4 border-gray-700"
                            src="{{ Storage::url($actualTeam->team_logo) }}" alt="{{ $actualTeam->name }} Logo">
                    @else
                        <div
                            class="h-24 w-24 bg-gray-700 rounded-full flex items-center justify-center border-4 border-gray-600">
                            <svg class="w-16 h-16 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    @endif
                </div>
                {{-- Team Name & Details --}}
                <div class="text-center md:text-left">
                    <h1 class="text-4xl font-extrabold tracking-tight">{{ $actualTeam->name }}</h1>
                    <p class="mt-1 text-lg text-gray-300">
                        @if ($actualTeam->is_global)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-200 border border-purple-400/30">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Global Team
                            </span>
                        @else
                            Playing in: <span class="font-semibold">{{ $actualTeam->tournament->name ?? 'N/A' }}</span>
                        @endif
                    </p>
                </div>
                {{-- Action Buttons --}}
                <div class="md:ml-auto flex items-center gap-3 mt-4 md:mt-0">
                    @if(!$isTeamManagerView)
                        <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
                    @endif
                    @can('actual-team.edit')
                        <a href="{{ route('admin.actual-teams.edit', $actualTeam) }}" class="btn btn-primary btn-sm">Edit
                            Team</a>
                    @endcan
                </div>
            </div>
        </div>

        @php
            // Only show users who are actual activated players (not Owner/Manager/Team Manager)
            $nonPlayerRoles = ['Owner', 'Manager', 'Team Manager'];
            $players = $actualTeam->users->filter(fn($u) =>
                !in_array($u->pivot->role, $nonPlayerRoles)
                && $u->player
                && $u->player->status === 'approved'
            );
            $teamOwner = $actualTeam->users->first(fn($u) => $u->pivot->role === 'Owner');
            $teamManager = $actualTeam->users->first(fn($u) => in_array($u->pivot->role, ['Manager', 'Team Manager']));
            $totalPlayers = $players->count();

            // Count Captains
            $captainsCount = $players
                ->filter(function ($user) {
                    return $user->roles->contains('name', 'Captain');
                })
                ->count();

            // Count for all roles
            $roleCounts = [];
            foreach ($players as $user) {
                foreach ($user->roles as $role) {
                    $roleName = $role->name;
                    if (!isset($roleCounts[$roleName])) {
                        $roleCounts[$roleName] = 0;
                    }
                    $roleCounts[$roleName]++;
                }
            }
        @endphp

        @if($isTeamManagerView)
            {{-- Simplified info bar for Team Managers --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8 text-center">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tournament</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $actualTeam->tournament->name ?? 'N/A' }}</div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved Players</div>
                    <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $approvedPlayers->count() }}</div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Retained</div>
                    <div class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $approvedPlayers->where('player_mode', 'retained')->count() }}</div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Available</div>
                    <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $approvedPlayers->where('player_mode', '!=', 'retained')->count() }}</div>
                </div>
            </div>
        @else
            {{-- Full info bar for Admins --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 text-center">
                @role('superadmin')
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Organization</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $actualTeam->organization->name ?? 'N/A' }}
                        </div>
                    </div>
                @endrole

                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Squad Size</div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow text-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Numbers</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $totalPlayers }}</div>
                        </div>

                        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow text-center">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Captains</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $captainsCount }}</div>
                        </div>

                        @foreach ($roleCounts as $roleName => $count)
                            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow text-center">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $roleName }}</div>
                                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $count }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tournament</div>
                    <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $actualTeam->tournament->name ?? 'N/A' }}</div>
                </div>
            </div>
        @endif

        {{-- ======================================================= --}}
        {{-- START: PLAYER ROSTER (from pivot table) --}}
        {{-- ======================================================= --}}
        @if(!$isTeamManagerView)
        @php
            $pivotPlayers = \Illuminate\Support\Facades\DB::table('player_actual_team_tournament')
                ->where('actual_team_id', $actualTeam->id)
                ->get();
            $pivotPlayersByTournament = $pivotPlayers->groupBy('tournament_id');
            $pivotPlayerIds = $pivotPlayers->pluck('player_id')->unique()->toArray();
            $pivotPlayersMap = \App\Models\Player::whereIn('id', $pivotPlayerIds)->get()->keyBy('id');
            $showEffectiveTournaments = $actualTeam->effective_tournaments;
        @endphp

        @if($pivotPlayers->count() > 0)
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Player Roster</h2>
            <div class="space-y-6 mb-8">
                @foreach($showEffectiveTournaments as $showTournament)
                    @php $tPlayers = $pivotPlayersByTournament->get($showTournament->id, collect()); @endphp
                    @if($tPlayers->count() > 0)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">{{ $showTournament->name }}</h3>
                            <div class="space-y-2">
                                @foreach($tPlayers as $tAssignment)
                                    @php $tPlayer = $pivotPlayersMap->get($tAssignment->player_id); @endphp
                                    @if($tPlayer)
                                        <div class="flex items-center p-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                                            <img class="h-10 w-10 rounded-full object-cover mr-3"
                                                src="{{ $tPlayer->image_path ? \Illuminate\Support\Facades\Storage::url($tPlayer->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($tPlayer->name) . '&color=7F9CF5&background=EBF4FF' }}"
                                                alt="{{ $tPlayer->name }}">
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-800 dark:text-white">{{ $tPlayer->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $tPlayer->mobile_number_full ?? '' }}</p>
                                            </div>
                                            @if($tAssignment->role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    {{ ucfirst($tAssignment->role) }}
                                                </span>
                                            @endif
                                            @if($tPlayer->player_mode === 'retained')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 ml-1">
                                                    Retained{{ $tPlayer->retained_value ? ' (' . number_format($tPlayer->retained_value) . ')' : '' }}
                                                </span>
                                                <form action="{{ route('admin.players.unretain', $tPlayer) }}" method="POST" class="inline ml-1"
                                                    onsubmit="return confirm('Remove retention for {{ $tPlayer->name }}?')">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900 dark:text-amber-200 dark:hover:bg-amber-800 transition">
                                                        Unretain
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        @endif
        {{-- END: PLAYER ROSTER (hidden for team managers) --}}

        {{-- ======================================================= --}}
        {{-- TEAM MANAGEMENT (Owner / Manager) --}}
        {{-- ======================================================= --}}
        @if (!$isTeamManagerView && ($teamOwner || $teamManager))
            <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                @if ($teamOwner)
                    <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-blue-500">
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700"
                            src="{{ $teamOwner->player?->image_path ? Storage::url($teamOwner->player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($teamOwner->name) }}"
                            alt="{{ $teamOwner->name }}">
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Owner</div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ $teamOwner->name }}</div>
                        </div>
                    </div>
                @endif
                @if ($teamManager)
                    <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-green-500">
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700"
                            src="{{ $teamManager->player?->image_path ? Storage::url($teamManager->player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($teamManager->name) }}"
                            alt="{{ $teamManager->name }}">
                        <div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Team Manager</div>
                            <div class="font-bold text-gray-900 dark:text-white">{{ $teamManager->name }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- ======================================================= --}}
        {{-- APPROVED PLAYERS FOR AUCTION (Retain Management) --}}
        {{-- ======================================================= --}}
        @if($approvedPlayers->count() > 0)
            @php
                $uniqueTypes = $approvedPlayers->pluck('playerType.type')->filter()->unique()->sort();
                $uniqueBatting = $approvedPlayers->pluck('battingProfile.style')->filter()->unique()->sort();
                $uniqueBowling = $approvedPlayers->pluck('bowlingProfile.style')->filter()->unique()->sort();
                $uniqueCountries = $approvedPlayers->pluck('country')->filter()->unique()->sort();
                $countryList = config('countries.list', []);
            @endphp
            <div class="mb-8" x-data="{
                retainModal: false, retainPlayer: null, retainPlayerName: '', retainAmount: '',
                get retainDisplayM() { return this.retainAmount ? (this.retainAmount / 1000000).toFixed(2) : ''; },
                set retainDisplayM(val) { this.retainAmount = val ? Math.round(val * 1000000) : ''; },
                search: '',
                filterType: '',
                filterBatting: '',
                filterBowling: '',
                filterCountry: '',
                filterWk: '',
                filterStatus: '',
                minMatches: '',
                minRuns: '',
                minWickets: '',
                sortBy: '',
                sortDir: 'desc',
                showFilters: false,
                toggleSort(col) {
                    if (this.sortBy === col) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; }
                    else { this.sortBy = col; this.sortDir = 'desc'; }
                    this.doSort();
                },
                doSort() {
                    if (!this.sortBy) return;
                    let tbody = this.$root.querySelector('tbody');
                    let rows = Array.from(tbody.querySelectorAll('tr'));
                    let key = this.sortBy;
                    let dir = this.sortDir;
                    rows.sort((a, b) => {
                        let va = parseInt(a.dataset[key] || 0);
                        let vb = parseInt(b.dataset[key] || 0);
                        return dir === 'asc' ? va - vb : vb - va;
                    });
                    rows.forEach(r => tbody.appendChild(r));
                },
                get activeFilterCount() {
                    let c = 0;
                    if (this.filterType) c++;
                    if (this.filterBatting) c++;
                    if (this.filterBowling) c++;
                    if (this.filterCountry) c++;
                    if (this.filterWk) c++;
                    if (this.filterStatus) c++;
                    if (this.minMatches !== '') c++;
                    if (this.minRuns !== '') c++;
                    if (this.minWickets !== '') c++;
                    return c;
                },
                get hasFilters() {
                    return this.search || this.activeFilterCount > 0 || this.sortBy;
                },
                clearAll() {
                    this.search = ''; this.filterType = ''; this.filterBatting = ''; this.filterBowling = '';
                    this.filterCountry = ''; this.filterWk = ''; this.filterStatus = '';
                    this.minMatches = ''; this.minRuns = ''; this.minWickets = ''; this.sortBy = '';
                }
            }">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Approved Players</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400" x-show="hasFilters" x-cloak>
                        Showing filtered results
                    </span>
                </div>

                {{-- Search + Filter Toggle --}}
                <div class="flex flex-wrap items-center gap-3 mb-3">
                    <div class="relative flex-1 min-w-[200px] max-w-md">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" x-model="search" placeholder="Search player name..."
                            class="w-full pl-10 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button @click="showFilters = !showFilters"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border transition"
                        :class="showFilters || activeFilterCount > 0
                            ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-600'
                            : 'border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filters
                        <span x-show="activeFilterCount > 0" x-text="activeFilterCount"
                            class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-blue-600 rounded-full"></span>
                    </button>
                    <button x-show="hasFilters" @click="clearAll()" x-cloak
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear All
                    </button>
                </div>

                {{-- Expandable Filters Panel --}}
                <div x-show="showFilters" x-collapse x-cloak
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4 shadow-sm">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        {{-- Player Type --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Player Type</label>
                            <select x-model="filterType" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                @foreach($uniqueTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Batting Style --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Batting</label>
                            <select x-model="filterBatting" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                @foreach($uniqueBatting as $style)
                                    <option value="{{ $style }}">{{ $style }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Bowling Style --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Bowling</label>
                            <select x-model="filterBowling" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                @foreach($uniqueBowling as $style)
                                    <option value="{{ $style }}">{{ $style }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Country --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Country</label>
                            <select x-model="filterCountry" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                @foreach($uniqueCountries as $code)
                                    <option value="{{ $code }}">{{ $countryList[$code] ?? $code }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Wicket Keeper --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Wicket Keeper</label>
                            <select x-model="filterWk" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        {{-- Auction Status --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Status</label>
                            <select x-model="filterStatus" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All</option>
                                <option value="available">Available</option>
                                <option value="retained">Retained</option>
                            </select>
                        </div>
                        {{-- Min Matches --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Min Matches</label>
                            <input type="number" x-model="minMatches" min="0" placeholder="Any"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        {{-- Min Runs --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Min Runs</label>
                            <input type="number" x-model="minRuns" min="0" placeholder="Any"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        {{-- Min Wickets --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Min Wickets</label>
                            <input type="number" x-model="minWickets" min="0" placeholder="Any"
                                class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Player</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden md:table-cell">Type / Style</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden lg:table-cell cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400 transition" @click="toggleSort('matches')">
                                        <span class="inline-flex items-center gap-1">Mat <template x-if="sortBy === 'matches'"><span x-text="sortDir === 'asc' ? '&#9650;' : '&#9660;'" class="text-blue-600 dark:text-blue-400"></span></template></span>
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden lg:table-cell cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400 transition" @click="toggleSort('runs')">
                                        <span class="inline-flex items-center gap-1">Runs <template x-if="sortBy === 'runs'"><span x-text="sortDir === 'asc' ? '&#9650;' : '&#9660;'" class="text-blue-600 dark:text-blue-400"></span></template></span>
                                    </th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden lg:table-cell cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400 transition" @click="toggleSort('wickets')">
                                        <span class="inline-flex items-center gap-1">Wkts <template x-if="sortBy === 'wickets'"><span x-text="sortDir === 'asc' ? '&#9650;' : '&#9660;'" class="text-blue-600 dark:text-blue-400"></span></template></span>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($approvedPlayers as $ap)
                                    @if($ap->player_mode === 'retained')
                                        @continue
                                    @endif
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                        data-player-name="{{ strtolower($ap->name) }}"
                                        data-player-type="{{ $ap->playerType?->type ?? '' }}"
                                        data-batting="{{ $ap->battingProfile?->style ?? '' }}"
                                        data-bowling="{{ $ap->bowlingProfile?->style ?? '' }}"
                                        data-country="{{ $ap->country ?? '' }}"
                                        data-wk="{{ $ap->is_wicket_keeper ? '1' : '0' }}"
                                        data-player-status="{{ ($ap->player_mode === 'retained') ? 'retained' : 'available' }}"
                                        data-matches="{{ $ap->total_matches ?? 0 }}"
                                        data-runs="{{ $ap->total_runs ?? 0 }}"
                                        data-wickets="{{ $ap->total_wickets ?? 0 }}"
                                        x-show="
                                            (search === '' || $el.dataset.playerName.includes(search.toLowerCase()))
                                            && (filterType === '' || $el.dataset.playerType === filterType)
                                            && (filterBatting === '' || $el.dataset.batting === filterBatting)
                                            && (filterBowling === '' || $el.dataset.bowling === filterBowling)
                                            && (filterCountry === '' || $el.dataset.country === filterCountry)
                                            && (filterWk === '' || $el.dataset.wk === filterWk)
                                            && (filterStatus === '' || $el.dataset.playerStatus === filterStatus)
                                            && (minMatches === '' || parseInt($el.dataset.matches) >= parseInt(minMatches))
                                            && (minRuns === '' || parseInt($el.dataset.runs) >= parseInt(minRuns))
                                            && (minWickets === '' || parseInt($el.dataset.wickets) >= parseInt(minWickets))
                                        "
                                    >
                                        {{-- Player --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <img class="h-9 w-9 rounded-full object-cover"
                                                    src="{{ $ap->image_path ? Storage::url($ap->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($ap->name) . '&color=7F9CF5&background=EBF4FF' }}"
                                                    alt="{{ $ap->name }}">
                                                <div>
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $ap->name }}</span>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ap->mobile_number_full ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Type / Style --}}
                                        <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @if($ap->playerType)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                        {{ $ap->playerType->type }}
                                                    </span>
                                                @endif
                                                @if($ap->is_wicket_keeper)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                        WK
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap gap-1 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                @if($ap->battingProfile)
                                                    <span>{{ $ap->battingProfile->style }}</span>
                                                @endif
                                                @if($ap->battingProfile && $ap->bowlingProfile)
                                                    <span>&middot;</span>
                                                @endif
                                                @if($ap->bowlingProfile)
                                                    <span>{{ $ap->bowlingProfile->style }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Matches --}}
                                        <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                            {{ $ap->total_matches ?? 0 }}
                                        </td>
                                        {{-- Runs --}}
                                        <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                            {{ $ap->total_runs ?? 0 }}
                                        </td>
                                        {{-- Wickets --}}
                                        <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                            {{ $ap->total_wickets ?? 0 }}
                                        </td>
                                        {{-- Status --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($ap->player_mode === 'retained' && $ap->actual_team_id == $actualTeam->id)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-violet-600 text-white shadow-sm">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                    Retained ({{ number_format($ap->retained_value) }})
                                                </span>
                                            @elseif($ap->player_mode === 'retained' && $ap->actual_team_id && $ap->actual_team_id != $actualTeam->id)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gradient-to-r from-gray-400 to-gray-500 text-white shadow-sm">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                    Retained by {{ $ap->actualTeam->name ?? 'Other' }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                    Available
                                                </span>
                                            @endif
                                        </td>
                                        {{-- Action --}}
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                @can('player.view')
                                                    <a href="{{ route('admin.players.show', $ap) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 dark:text-blue-300 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 transition">
                                                        View
                                                    </a>
                                                @endcan
                                                @can('player.edit')
                                                    <a href="{{ route('admin.players.edit', $ap) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-amber-700 bg-amber-100 hover:bg-amber-200 dark:text-amber-300 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 transition">
                                                        Edit
                                                    </a>
                                                    @if($ap->player_mode === 'retained' && $ap->actual_team_id == $actualTeam->id)
                                                        <form action="{{ route('admin.players.unretain', $ap) }}" method="POST" class="inline"
                                                            onsubmit="return confirm('Remove retention for {{ $ap->name }}?')">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 dark:text-red-300 dark:bg-red-900/30 dark:hover:bg-red-900/50 transition">
                                                                Unretain
                                                            </button>
                                                        </form>
                                                    @elseif(!($ap->player_mode === 'retained' && $ap->actual_team_id && $ap->actual_team_id != $actualTeam->id))
                                                        <button type="button"
                                                            @click="retainModal = true; retainPlayer = {{ $ap->id }}; retainPlayerName = '{{ addslashes($ap->name) }}'; retainAmount = ''"
                                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 dark:text-purple-300 dark:bg-purple-900/30 dark:hover:bg-purple-900/50 transition">
                                                            Retain
                                                        </button>
                                                    @endif
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Retain Modal --}}
                <div x-show="retainModal" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    @keydown.escape.window="retainModal = false">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4 p-6"
                        @click.outside="retainModal = false">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                            Retain <span x-text="retainPlayerName"></span>
                        </h3>
                        <form method="POST" x-bind:action="'{{ url('admin/players') }}/' + retainPlayer + '/retain'">
                            @csrf
                            <input type="hidden" name="actual_team_id" value="{{ $actualTeam->id }}">
                            <input type="hidden" name="retained_value" x-model="retainAmount">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retained Value (in Millions)</label>
                                <input type="number" x-model="retainDisplayM" min="0" step="0.01" required
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                    placeholder="e.g. 1.5 for 15,00,000">
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="retainModal = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 transition">
                                    Retain Player
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- ======================================================= --}}
        {{-- START: LEGACY SQUAD (from actual_team_users) --}}
        {{-- ======================================================= --}}
        @if(!$isTeamManagerView)
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">The Squad</h2>
        <div class="space-y-3">
            @forelse($players as $user)

                @can('player.view')
                    {{-- Clickable Row --}}
                    <a href="{{ $user->player ? route('admin.players.show', $user->player->id) : '#' }}" class="block group">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent transition-all duration-300 ease-in-out group-hover:shadow-xl group-hover:border-blue-500 group-hover:scale-[1.02]">
                            <div class="flex items-center p-3 gap-4">

                                {{-- 1. Player Image, Name, and Role on Team (flex-basis: 30%) --}}
                                <div class="flex items-center gap-4 flex-shrink-0" style="flex-basis: 30%;">
                                    <img class="h-14 w-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700"
                                        src="{{ $user->player?->image_path ? Storage::url($user->player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) }}"
                                        alt="{{ $user->name }}">
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-900 dark:text-white truncate">
                                            {{ $user->name }}</h3>
                                              @if ($user->player && $user->player->player_mode === 'retained')
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">
                    Retained
                </span>
            @endif
                                        @if ($user->roles->count())
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                @foreach ($user->roles as $role)
                                                    <span
                                                        class="text-xs font-semibold px-2 py-0.5 rounded-full
                @if (strtolower($role->name) === 'captain') bg-yellow-400 text-yellow-900
                @else
                    bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 @endif">
                                                        {{ $role->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                    </div>
                                </div>

                                {{-- 2. Player Skills & Attributes (flex-basis: 35%) --}}
                                <div class="hidden md:flex items-center gap-4 flex-grow" style="flex-basis: 35%;">
                                    <div class="text-center" title="Primary Role">
                                        <div class="font-bold text-gray-800 dark:text-white">
                                            {{ $user->player?->playerType?->type ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Role</div>
                                    </div>
                                    <div class="border-l border-gray-200 dark:border-gray-600 h-8"></div>
                                    <div class="text-left text-sm">
                                        <div class="flex items-center gap-2" title="Batting Style"><img
                                                src="{{ asset('images/icons/bat.svg') }}"
                                                class="w-4 h-4 dark:invert opacity-60"><span>{{ $user->player?->battingProfile?->style ?? 'N/A' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1" title="Bowling Style"><img
                                                src="{{ asset('images/icons/ball.svg') }}"
                                                class="w-4 h-4 dark:invert opacity-60"><span>{{ $user->player?->bowlingProfile?->style ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                    @if ($user->player?->is_wicket_keeper)
                                        <div class="border-l border-gray-200 dark:border-gray-600 h-8"></div>
                                        <div class="text-center" title="Wicket Keeper"><img
                                                src="{{ asset('images/icons/wicket.svg') }}"
                                                class="w-6 h-6 mx-auto dark:invert opacity-60">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">WK</div>
                                        </div>
                                    @endif
                                </div>

                                {{-- 3. **NEW**: Player Stats Section (flex-basis: 25%) --}}
                                <div class="hidden lg:flex items-center justify-around flex-grow text-center"
                                    style="flex-basis: 25%;">
                                    <div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-white">
                                            {{ $user->player?->total_matches ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Matches
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-white">
                                            {{ $user->player?->total_runs ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Runs
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-xl font-bold text-gray-800 dark:text-white">
                                            {{ $user->player?->total_wickets ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Wickets
                                        </div>
                                    </div>
                                </div>

                                {{-- 4. Action / Link Indicator (flex-basis: 10%) --}}
                                <div class="flex items-center justify-end ml-auto">
                                    <div
                                        class="flex items-center gap-2 text-blue-600 dark:text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                        </svg>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </a>
                @else
                    {{-- Fallback Non-clickable Row (content is identical) --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent">
                        {{-- ... same inner content as above ... --}}
                    </div>
                @endcan

            @empty
                {{-- Empty State --}}
                <div
                    class="col-span-full p-8 text-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                    {{-- ... your empty state HTML ... --}}
                </div>
            @endforelse
        </div>
        @endif
        {{-- ======================================================= --}}
        {{-- END: LEGACY SQUAD --}}
        {{-- ======================================================= --}}
    </div>
@endsection
