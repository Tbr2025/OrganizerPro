@extends('backend.layouts.app')

@section('title', $otherTeam->name . ' - Squad')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'Other Teams', 'route' => route('team-manager.other-teams')],
    ['name' => $otherTeam->name]
]" />

<div class="p-4 mx-auto md:p-6 lg:p-8">

    {{-- HEADER / HERO SECTION --}}
    <div class="relative bg-gray-800 dark:bg-gray-900 rounded-lg shadow-lg p-6 mb-8 text-white overflow-hidden">
        <div class="absolute inset-0 opacity-5">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width=".5"
                    d="M3 5.25h18m-18 4.5h18m-18 4.5h18m-18 4.5h18M5.25 3v18m4.5-18v18m4.5-18v18m4.5-18v18" />
            </svg>
        </div>

        <div class="relative flex flex-col md:flex-row items-center gap-6">
            <div class="flex-shrink-0">
                @if ($otherTeam->team_logo)
                    <img class="h-24 w-24 object-cover rounded-full border-4 border-gray-700"
                        src="{{ Storage::url($otherTeam->team_logo) }}" alt="{{ $otherTeam->name }} Logo">
                @else
                    <div class="h-24 w-24 bg-gray-700 rounded-full flex items-center justify-center border-4 border-gray-600">
                        <span class="text-3xl font-bold text-gray-400">{{ strtoupper(substr($otherTeam->name, 0, 2)) }}</span>
                    </div>
                @endif
            </div>
            <div class="text-center md:text-left">
                <h1 class="text-4xl font-extrabold tracking-tight">{{ $otherTeam->name }}</h1>
                <p class="mt-1 text-lg text-gray-300">
                    Playing in: <span class="font-semibold">{{ $otherTeam->tournament->name ?? 'N/A' }}</span>
                </p>
            </div>
            <div class="md:ml-auto flex items-center gap-3 mt-4 md:mt-0">
                <a href="{{ route('team-manager.other-teams') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Teams
                </a>
            </div>
        </div>
    </div>

    {{-- INFO BAR --}}
    @php
        $retainedCount = $players->where('player_mode', 'retained')->count();
        $availableCount = $players->where('player_mode', '!=', 'retained')->count();
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8 text-center">
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tournament</div>
            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $otherTeam->tournament->name ?? 'N/A' }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Players</div>
            <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $players->count() }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Retained</div>
            <div class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $retainedCount }}</div>
        </div>
        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Available</div>
            <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $availableCount }}</div>
        </div>
    </div>

    {{-- SQUAD TABLE --}}
    @if($players->count() > 0)
        @php
            $uniqueTypes = $players->pluck('playerType.type')->filter()->unique()->sort();
            $uniqueBatting = $players->pluck('battingProfile.style')->filter()->unique()->sort();
            $uniqueBowling = $players->pluck('bowlingProfile.style')->filter()->unique()->sort();
        @endphp
        <div class="mb-8" x-data="{
            search: '',
            filterType: '',
            filterBatting: '',
            filterBowling: '',
            filterWk: '',
            filterStatus: '',
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
                if (this.filterWk) c++;
                if (this.filterStatus) c++;
                return c;
            },
            get hasFilters() {
                return this.search || this.activeFilterCount > 0 || this.sortBy;
            },
            clearAll() {
                this.search = ''; this.filterType = ''; this.filterBatting = ''; this.filterBowling = '';
                this.filterWk = ''; this.filterStatus = ''; this.sortBy = '';
            }
        }">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">The Squad</h2>
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
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Player Type</label>
                        <select x-model="filterType" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All</option>
                            @foreach($uniqueTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Batting</label>
                        <select x-model="filterBatting" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All</option>
                            @foreach($uniqueBatting as $style)
                                <option value="{{ $style }}">{{ $style }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Bowling</label>
                        <select x-model="filterBowling" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All</option>
                            @foreach($uniqueBowling as $style)
                                <option value="{{ $style }}">{{ $style }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Wicket Keeper</label>
                        <select x-model="filterWk" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Status</label>
                        <select x-model="filterStatus" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All</option>
                            <option value="available">Available</option>
                            <option value="retained">Retained</option>
                        </select>
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
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Wishlist</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($players as $player)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    data-player-name="{{ strtolower($player->name) }}"
                                    data-player-type="{{ $player->playerType?->type ?? '' }}"
                                    data-batting="{{ $player->battingProfile?->style ?? '' }}"
                                    data-bowling="{{ $player->bowlingProfile?->style ?? '' }}"
                                    data-wk="{{ $player->is_wicket_keeper ? '1' : '0' }}"
                                    data-player-status="{{ ($player->player_mode === 'retained') ? 'retained' : 'available' }}"
                                    data-matches="{{ $player->total_matches ?? 0 }}"
                                    data-runs="{{ $player->total_runs ?? 0 }}"
                                    data-wickets="{{ $player->total_wickets ?? 0 }}"
                                    x-show="
                                        (search === '' || $el.dataset.playerName.includes(search.toLowerCase()))
                                        && (filterType === '' || $el.dataset.playerType === filterType)
                                        && (filterBatting === '' || $el.dataset.batting === filterBatting)
                                        && (filterBowling === '' || $el.dataset.bowling === filterBowling)
                                        && (filterWk === '' || $el.dataset.wk === filterWk)
                                        && (filterStatus === '' || $el.dataset.playerStatus === filterStatus)
                                    "
                                >
                                    {{-- Player --}}
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            @if($player->image_path)
                                                <img class="h-9 w-9 rounded-full object-cover"
                                                    src="{{ Storage::url($player->image_path) }}"
                                                    alt="{{ $player->name }}">
                                            @else
                                                <div class="h-9 w-9 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                                    <span class="text-sm font-bold text-gray-400">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                                                </div>
                                            @endif
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ $player->name }}</span>
                                                    @if($player->jersey_number)
                                                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300">#{{ $player->jersey_number }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $player->mobile_number_full ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Type / Style --}}
                                    <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            @if($player->playerType)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                                    {{ $player->playerType->type }}
                                                </span>
                                            @endif
                                            @if($player->is_wicket_keeper)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                    WK
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-1 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if($player->battingProfile)
                                                <span>{{ $player->battingProfile->style ?? $player->battingProfile->name }}</span>
                                            @endif
                                            @if($player->battingProfile && $player->bowlingProfile)
                                                <span>&middot;</span>
                                            @endif
                                            @if($player->bowlingProfile)
                                                <span>{{ $player->bowlingProfile->style ?? $player->bowlingProfile->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Matches --}}
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                        {{ $player->total_matches ?? 0 }}
                                    </td>
                                    {{-- Runs --}}
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                        {{ $player->total_runs ?? 0 }}
                                    </td>
                                    {{-- Wickets --}}
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                                        {{ $player->total_wickets ?? 0 }}
                                    </td>
                                    {{-- Status --}}
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($player->player_mode === 'retained')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-violet-600 text-white shadow-sm">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                                Retained
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                Available
                                            </span>
                                        @endif
                                    </td>
                                    {{-- Wishlist --}}
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if($player->player_mode !== 'retained')
                                            <button type="button" onclick="toggleWishlist({{ $player->id }}, this)"
                                                class="wishlist-btn p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                                data-wishlisted="{{ in_array($player->id, $wishlistedIds) ? '1' : '0' }}"
                                                title="{{ in_array($player->id, $wishlistedIds) ? 'Remove from wishlist' : 'Add to wishlist' }}">
                                                <svg class="w-5 h-5 {{ in_array($player->id, $wishlistedIds) ? 'text-red-500 fill-red-500' : 'text-gray-400 dark:text-gray-500' }}" fill="{{ in_array($player->id, $wishlistedIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                </svg>
                                            </button>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">The Squad</h2>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This team has no players in their squad yet.</p>
        </div>
    @endif
</div>

<script>
    const toggleUrl = '{{ route("team-manager.wishlist.toggle") }}';
    const csrfToken = '{{ csrf_token() }}';

    function toggleWishlist(playerId, btn) {
        fetch(toggleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ player_id: playerId })
        })
        .then(r => r.json())
        .then(data => {
            const svg = btn.querySelector('svg');
            if (data.wishlisted) {
                svg.classList.remove('text-gray-400', 'dark:text-gray-500');
                svg.classList.add('text-red-500', 'fill-red-500');
                svg.setAttribute('fill', 'currentColor');
                btn.dataset.wishlisted = '1';
                btn.title = 'Remove from wishlist';
            } else {
                svg.classList.remove('text-red-500', 'fill-red-500');
                svg.classList.add('text-gray-400', 'dark:text-gray-500');
                svg.setAttribute('fill', 'none');
                btn.dataset.wishlisted = '0';
                btn.title = 'Add to wishlist';
            }
        });
    }
</script>
@endsection
