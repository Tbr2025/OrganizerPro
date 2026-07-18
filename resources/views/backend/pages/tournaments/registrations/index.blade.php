@extends('backend.layouts.app')

@section('title', 'Registrations | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <x-breadcrumbs :breadcrumbs="[
                ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
                ['label' => $tournament->name],
                ['label' => 'Registrations']
            ]" />
            <a href="{{ route('admin.tournaments.settings.edit', $tournament) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg">
                <iconify-icon icon="lucide:settings"></iconify-icon>
                Registration Settings
            </a>
        </div>

        <div class="mt-6">
            {{-- Public Registration Links --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-4 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Public Registration Links</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Share these links with players and teams to register for this tournament.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Player Registration Link --}}
                    <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">Player Registration</p>
                            <input type="text"
                                   readonly
                                   value="{{ route('public.tournament.registration.player', $tournament->slug) }}"
                                   class="w-full text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded px-2 py-1 text-gray-600 dark:text-gray-400"
                                   id="player-reg-link">
                        </div>
                        <button type="button"
                                onclick="copyToClipboard('player-reg-link', this)"
                                class="flex-shrink-0 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition">
                            Copy
                        </button>
                    </div>

                    {{-- Team Registration Link --}}
                    <div class="flex items-center gap-2 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-purple-700 dark:text-purple-300 mb-1">Team Registration</p>
                            <input type="text"
                                   readonly
                                   value="{{ route('public.tournament.registration.team', $tournament->slug) }}"
                                   class="w-full text-xs bg-white dark:bg-gray-800 border border-purple-200 dark:border-purple-700 rounded px-2 py-1 text-gray-600 dark:text-gray-400"
                                   id="team-reg-link">
                        </div>
                        <button type="button"
                                onclick="copyToClipboard('team-reg-link', this)"
                                class="flex-shrink-0 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded transition">
                            Copy
                        </button>
                    </div>
                </div>
            </div>

            {{-- Player vs Team — separate pages (never mixed) --}}
            <div class="inline-flex p-1 mb-5 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                @foreach(['player' => 'Player Registrations', 'team' => 'Team Registrations'] as $t => $label)
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'type' => $t]) }}"
                       class="px-4 py-2 text-sm font-semibold rounded-lg transition {{ $type === $t ? 'bg-white dark:bg-gray-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Stats Cards --}}
            @php
                $statCards = [
                    ['status' => 'all',        'label' => 'Total',      'count' => $totalCount,      'bg' => 'bg-indigo-50 dark:bg-indigo-950/40',  'border' => 'border-indigo-200 dark:border-indigo-800', 'icon_bg' => 'bg-indigo-100 dark:bg-indigo-900/50',  'icon_color' => 'text-indigo-600 dark:text-indigo-400',  'text' => 'text-indigo-600 dark:text-indigo-400',  'num' => 'text-indigo-700 dark:text-indigo-200',  'hover_border' => 'hover:border-indigo-400 dark:hover:border-indigo-600',  'shadow' => 'hover:shadow-indigo-200/50 dark:hover:shadow-indigo-900/30',  'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['status' => 'pending',    'label' => 'Pending',    'count' => $pendingCount,    'bg' => 'bg-amber-50 dark:bg-amber-950/40',    'border' => 'border-amber-200 dark:border-amber-800',   'icon_bg' => 'bg-amber-100 dark:bg-amber-900/50',    'icon_color' => 'text-amber-600 dark:text-amber-400',    'text' => 'text-amber-600 dark:text-amber-400',    'num' => 'text-amber-700 dark:text-amber-200',    'hover_border' => 'hover:border-amber-400 dark:hover:border-amber-600',    'shadow' => 'hover:shadow-amber-200/50 dark:hover:shadow-amber-900/30',    'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['status' => 'approved',   'label' => 'Approved',   'count' => $approvedCount,   'bg' => 'bg-emerald-50 dark:bg-emerald-950/40','border' => 'border-emerald-200 dark:border-emerald-800','icon_bg' => 'bg-emerald-100 dark:bg-emerald-900/50','icon_color' => 'text-emerald-600 dark:text-emerald-400','text' => 'text-emerald-600 dark:text-emerald-400','num' => 'text-emerald-700 dark:text-emerald-200','hover_border' => 'hover:border-emerald-400 dark:hover:border-emerald-600','shadow' => 'hover:shadow-emerald-200/50 dark:hover:shadow-emerald-900/30','icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['status' => 'rejected',   'label' => 'Rejected',   'count' => $rejectedCount,   'bg' => 'bg-rose-50 dark:bg-rose-950/40',      'border' => 'border-rose-200 dark:border-rose-800',     'icon_bg' => 'bg-rose-100 dark:bg-rose-900/50',      'icon_color' => 'text-rose-600 dark:text-rose-400',      'text' => 'text-rose-600 dark:text-rose-400',      'num' => 'text-rose-700 dark:text-rose-200',      'hover_border' => 'hover:border-rose-400 dark:hover:border-rose-600',      'shadow' => 'hover:shadow-rose-200/50 dark:hover:shadow-rose-900/30',      'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['status' => 'cancelled',  'label' => 'Cancelled',  'count' => $cancelledCount,  'bg' => 'bg-gray-50 dark:bg-gray-900/40',      'border' => 'border-gray-200 dark:border-gray-700',     'icon_bg' => 'bg-gray-100 dark:bg-gray-800/50',      'icon_color' => 'text-gray-500 dark:text-gray-400',      'text' => 'text-gray-500 dark:text-gray-400',      'num' => 'text-gray-700 dark:text-gray-200',      'hover_border' => 'hover:border-gray-400 dark:hover:border-gray-500',      'shadow' => 'hover:shadow-gray-200/50 dark:hover:shadow-gray-900/30',      'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
                    ['status' => 'queued',     'label' => 'In Queue',   'count' => $queuedCount,     'bg' => 'bg-sky-50 dark:bg-sky-950/40',        'border' => 'border-sky-200 dark:border-sky-800',       'icon_bg' => 'bg-sky-100 dark:bg-sky-900/50',        'icon_color' => 'text-sky-600 dark:text-sky-400',        'text' => 'text-sky-600 dark:text-sky-400',        'num' => 'text-sky-700 dark:text-sky-200',        'hover_border' => 'hover:border-sky-400 dark:hover:border-sky-600',        'shadow' => 'hover:shadow-sky-200/50 dark:hover:shadow-sky-900/30',        'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
                    ['status' => 'retained',   'label' => 'Retained',   'count' => $retainedCount,   'bg' => 'bg-purple-50 dark:bg-purple-950/40',  'border' => 'border-purple-200 dark:border-purple-800', 'icon_bg' => 'bg-purple-100 dark:bg-purple-900/50',  'icon_color' => 'text-purple-600 dark:text-purple-400',  'text' => 'text-purple-600 dark:text-purple-400',  'num' => 'text-purple-700 dark:text-purple-200',  'hover_border' => 'hover:border-purple-400 dark:hover:border-purple-600',  'shadow' => 'hover:shadow-purple-200/50 dark:hover:shadow-purple-900/30',  'icon' => 'M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z'],
                    ['status' => 'unretained', 'label' => 'Unretained', 'count' => $unretainedCount, 'bg' => 'bg-teal-50 dark:bg-teal-950/40',      'border' => 'border-teal-200 dark:border-teal-800',     'icon_bg' => 'bg-teal-100 dark:bg-teal-900/50',      'icon_color' => 'text-teal-600 dark:text-teal-400',      'text' => 'text-teal-600 dark:text-teal-400',      'num' => 'text-teal-700 dark:text-teal-200',      'hover_border' => 'hover:border-teal-400 dark:hover:border-teal-600',      'shadow' => 'hover:shadow-teal-200/50 dark:hover:shadow-teal-900/30',      'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ];
            @endphp
            <div class="grid grid-cols-4 md:grid-cols-8 gap-3 mb-6">
                @foreach($statCards as $card)
                    @php $isActive = $filters['status'] === $card['status']; @endphp
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'type' => $type, 'status' => $card['status']]) }}"
                       class="group relative overflow-hidden rounded-xl border-2 {{ $isActive ? 'border-blue-500 dark:border-blue-400 ring-2 ring-blue-500/20' : $card['border'] }} {{ $card['bg'] }} {{ $card['hover_border'] }} {{ $card['shadow'] }} px-3 py-4 text-center transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-lg cursor-pointer">
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="w-8 h-8 rounded-lg {{ $card['icon_bg'] }} flex items-center justify-center transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                                <svg class="w-4 h-4 {{ $card['icon_color'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"></path>
                                </svg>
                            </div>
                            <p class="text-xl font-bold {{ $card['num'] }} transition-transform duration-300 group-hover:scale-110">{{ $card['count'] }}</p>
                            <p class="text-[10px] font-semibold {{ $card['text'] }} uppercase tracking-wider leading-tight">{{ $card['label'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Filter Tabs (preserve search/sort when switching) --}}
            @php $q = request()->query(); @endphp
            <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex flex-wrap gap-x-8">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'queued' => 'In Queue', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'retained' => 'Retained', 'unretained' => 'Unretained'] as $key => $label)
                        @php $active = request('status', 'pending') === $key; @endphp
                        <a href="{{ route('admin.tournaments.registrations.index', array_merge($q, ['tournament' => $tournament, 'status' => $key])) }}"
                            class="py-2 px-1 border-b-2 font-medium text-sm {{ $active ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>

            {{-- Search / filter / sort bar --}}
            <form method="GET" action="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                  class="mb-4 flex flex-wrap items-end gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <input type="hidden" name="type" value="{{ $filters['type'] }}">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name, team, or email…"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                </div>
                @if($filters['type'] === 'player' && $playingTeamOptions->isNotEmpty())
                    <div class="min-w-[160px]">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Playing Team</label>
                        <select name="playing_team" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                            <option value="">All Teams</option>
                            <option value="none" @selected(($filters['playingTeam'] ?? '') === 'none')>No Team</option>
                            @foreach($playingTeamOptions as $pt)
                                <option value="{{ $pt->id }}" @selected(($filters['playingTeam'] ?? '') == $pt->id)>{{ $pt->name }} ({{ ucfirst($pt->tournament?->type ?? 'unknown') }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($filters['type'] === 'player')
                    <div class="min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tournament Type</label>
                        <select name="tournament_type" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                            <option value="">All</option>
                            <option value="open" @selected(($filters['tournamentType'] ?? '') === 'open')>Open</option>
                            <option value="auction" @selected(($filters['tournamentType'] ?? '') === 'auction')>Auction</option>
                            <option value="others" @selected(($filters['tournamentType'] ?? '') === 'others')>Others</option>
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sort by</label>
                    <select name="sort" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="date" @selected($filters['sort'] === 'date')>Date submitted</option>
                        <option value="modified" @selected($filters['sort'] === 'modified')>Last modified</option>
                        <option value="name" @selected($filters['sort'] === 'name')>Name (A–Z)</option>
                        <option value="status" @selected($filters['sort'] === 'status')>Status</option>
                        <option value="type" @selected($filters['sort'] === 'type')>Type</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Order</label>
                    <select name="direction" class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                        <option value="desc" @selected($filters['direction'] === 'desc')>Descending</option>
                        <option value="asc" @selected($filters['direction'] === 'asc')>Ascending</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">Apply</button>
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'type' => $filters['type'], 'status' => $filters['status']]) }}"
                       class="px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Reset</a>
                </div>
            </form>

            {{-- Active filter indicator --}}
            @if(($filters['playingTeam'] ?? '') !== '' || ($filters['tournamentType'] ?? '') !== '' || ($filters['search'] ?? '') !== '')
                <div class="mb-3 flex items-center gap-2 text-sm text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg px-4 py-2">
                    <iconify-icon icon="lucide:filter" class="text-base"></iconify-icon>
                    <span>Showing <strong>{{ $registrations->total() }}</strong> filtered results</span>
                    @if(($filters['tournamentType'] ?? '') !== '')
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $filters['tournamentType'] === 'auction' ? 'bg-amber-100 text-amber-800' : ($filters['tournamentType'] === 'others' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800') }}">
                            {{ $filters['tournamentType'] === 'open' ? 'Open Tournament' : ($filters['tournamentType'] === 'auction' ? 'Auction Tournament' : 'Others') }}
                        </span>
                    @endif
                    <a href="{{ route('admin.tournaments.registrations.index', ['tournament' => $tournament, 'type' => $filters['type'], 'status' => $filters['status']]) }}"
                       class="ml-auto text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 underline">Clear filters</a>
                </div>
            @endif

            {{-- Registrations Table --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl">
                @if($registrations->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Name / Team</th>
                                    <th class="px-6 py-3 hidden sm:table-cell">Contact</th>
                                    <th class="px-6 py-3 hidden md:table-cell">Type</th>
                                    @if($type === 'player')
                                        <th class="px-6 py-3 hidden md:table-cell">Playing Team</th>
                                    @endif
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3 hidden lg:table-cell">Date</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrations as $registration)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                        onclick="if(!event.target.closest('button, a, form, select')) window.location='{{ route('admin.tournaments.registrations.show', [$tournament, $registration]) }}'">
                                        {{-- Name / Team Column --}}
                                        <td class="px-6 py-4">
                                            @if($registration->type == 'team')
                                                <div class="flex items-center gap-3">
                                                    @if($registration->team_logo)
                                                        <img src="{{ Storage::url($registration->team_logo) }}" alt="Team Logo" class="w-10 h-10 rounded-lg object-cover">
                                                    @else
                                                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900 flex items-center justify-center">
                                                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $registration->team_name }}</div>
                                                        @if($registration->team_short_name)
                                                            <div class="text-xs text-gray-500">({{ $registration->team_short_name }})</div>
                                                        @endif
                                                        <div class="text-xs text-gray-500">Manager: {{ $registration->captain_name }}</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-3">
                                                    @if($registration->player?->image_path)
                                                        <img src="{{ Storage::url($registration->player->image_path) }}" alt="Player" class="w-10 h-10 rounded-full object-cover">
                                                    @else
                                                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                            </svg>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $registration->player->name ?? 'N/A' }}</div>
                                                        @if($registration->player?->actualTeam)
                                                            <span class="inline-flex items-center mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium {{ $registration->player->actualTeam->tournament?->isAuction() ? 'bg-amber-50 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                                                {{ $registration->player->actualTeam->name }}
                                                            </span>
                                                        @elseif($registration->player?->playing_team_name_ref)
                                                            <span class="inline-flex items-center mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                                {{ $registration->player->playing_team_name_ref }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Contact Column --}}
                                        <td class="px-6 py-4 hidden sm:table-cell">
                                            @if($registration->type == 'team')
                                                <div class="text-xs space-y-1">
                                                    <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                        </svg>
                                                        {{ $registration->captain_email }}
                                                    </div>
                                                    <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                        </svg>
                                                        {{ $registration->captain_phone }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-xs space-y-1">
                                                    @if($registration->player?->email)
                                                        <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                            </svg>
                                                            {{ $registration->player->email }}
                                                        </div>
                                                    @endif
                                                    @if($registration->player?->mobile_number_full)
                                                        <div class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                            </svg>
                                                            +{{ $registration->player->mobile_number_full }}
                                                        </div>
                                                    @endif
                                                    @if(!$registration->player?->email && !$registration->player?->mobile_number_full)
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Type Column --}}
                                        <td class="px-6 py-4 hidden md:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $registration->type == 'player' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' }}">
                                                    {{ ucfirst($registration->type) }}
                                                </span>
                                                @if($registration->type === 'player' && $registration->player?->user)
                                                    @if($registration->player->user->hasRole('Team Manager'))
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                            Team Manager
                                                        </span>
                                                    @endif
                                                    @if($registration->player->user->hasRole('Team Owner'))
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                            Team Owner
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Playing Team Column (player rows only) --}}
                                        @if($type === 'player')
                                            <td class="px-6 py-4 hidden md:table-cell">
                                                @if($registration->player?->actualTeam)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium {{ $registration->player->actualTeam->tournament?->isAuction() ? 'bg-amber-50 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                                        {{ $registration->player->actualTeam->name }}
                                                    </span>
                                                    @if($registration->player->actualTeam->tournament)
                                                        <div class="mt-1">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $registration->player->actualTeam->tournament->isAuction() ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' }}">
                                                                {{ ucfirst($registration->player->actualTeam->tournament->type) }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                @elseif($registration->player?->playing_team_name_ref)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        {{ $registration->player->playing_team_name_ref }}
                                                    </span>
                                                    <div class="mt-1">
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                                                            Others
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                        @endif

                                        {{-- Status Column --}}
                                        <td class="px-6 py-4">
                                            @if($registration->status == 'pending')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Pending
                                                </span>
                                            @elseif($registration->status == 'approved')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Approved
                                                </span>
                                            @elseif($registration->status == 'cancelled')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    Cancelled
                                                </span>
                                            @elseif($registration->status == 'queued')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200">
                                                    In Queue
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Rejected
                                                </span>
                                            @endif
                                            @if($registration->player?->player_mode === 'retained')
                                                <div class="mt-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        Retained
                                                    </span>
                                                </div>
                                                @if($registration->player->actualTeam)
                                                    <p class="text-[10px] text-purple-600 dark:text-purple-400 mt-0.5 leading-tight">{{ $registration->player->actualTeam->name }}</p>
                                                @endif
                                                @if($registration->player->retained_value)
                                                    @php
                                                        $rv = $registration->player->retained_value;
                                                        $rvFormatted = $rv >= 1000000 ? round($rv / 1000000, 1) . 'M' : ($rv >= 1000 ? round($rv / 1000, 1) . 'K' : number_format($rv));
                                                    @endphp
                                                    <p class="text-[10px] font-semibold text-purple-700 dark:text-purple-300 leading-tight">{{ $rvFormatted }}</p>
                                                @endif
                                            @endif
                                        </td>

                                        {{-- Date Column --}}
                                        <td class="px-6 py-4 hidden lg:table-cell">
                                            <div class="text-sm">{{ $registration->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $registration->created_at->format('h:i A') }}</div>
                                            @if($registration->updated_at && $registration->updated_at->ne($registration->created_at))
                                                <div class="text-[11px] text-gray-400 mt-1" title="Last modified">✎ {{ $registration->updated_at->diffForHumans() }}</div>
                                            @endif
                                        </td>

                                        {{-- Actions Column --}}
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                {{-- View Button --}}
                                                <a href="{{ route('admin.tournaments.registrations.show', [$tournament, $registration]) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View
                                                </a>

                                                {{-- 3-dot dropdown --}}
                                                <div class="relative" x-data="{ open: false }">
                                                    <button @click="open = !open" @click.outside="open = false"
                                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                                        </svg>
                                                    </button>

                                                    <div x-show="open" x-cloak x-transition
                                                         class="absolute right-0 z-50 mt-1 w-48 rounded-lg shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 py-1">

                                                        @if($registration->status == 'pending')
                                                            <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to approve this registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                    Approve
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to reject this registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                    Reject
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.tournaments.registrations.queue', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to place this registration in the queue?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-sky-600 dark:text-sky-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                    In Queue
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.tournaments.registrations.cancel', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to cancel this registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                                    Cancel
                                                                </button>
                                                            </form>
                                                        @endif

                                                        @if($registration->status == 'queued')
                                                            <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to approve this queued registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                    Approve
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to reject this queued registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                    Reject
                                                                </button>
                                                            </form>
                                                            <form action="{{ route('admin.tournaments.registrations.cancel', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to cancel this queued registration?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                                    Cancel
                                                                </button>
                                                            </form>
                                                            @if($registration->type === 'player' && $registration->player)
                                                                @if($registration->player->player_mode !== 'retained')
                                                                    <button type="button"
                                                                        @click="open = false; $dispatch('open-retain-modal', { playerId: {{ $registration->player->id }}, playerName: '{{ addslashes($registration->player->name) }}' })"
                                                                        class="w-full text-left px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                                        Retain Player
                                                                    </button>
                                                                @endif
                                                            @endif
                                                        @endif

                                                        @if($registration->status == 'approved')
                                                            <form action="{{ route('admin.tournaments.registrations.unapprove', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Revert this registration to pending?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-amber-600 dark:text-amber-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                                                    Unapprove
                                                                </button>
                                                            </form>
                                                            @if($registration->type === 'team' && $registration->actual_team_id)
                                                                <a href="{{ route('admin.actual-teams.edit', $registration->actual_team_id) }}"
                                                                   class="w-full text-left px-4 py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                    Edit Team
                                                                </a>
                                                            @endif

                                                            <form action="{{ route('admin.tournaments.registrations.send-temp-password', [$tournament, $registration]) }}" method="POST"
                                                                  onsubmit="return confirm('Are you sure you want to send a temporary password to this applicant?')">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                                                    Send Temp Password
                                                                </button>
                                                            </form>

                                                            @if($registration->type === 'player' && $registration->player)
                                                                @if($registration->player->player_mode !== 'retained')
                                                                    <button type="button"
                                                                        @click="open = false; $dispatch('open-retain-modal', { playerId: {{ $registration->player->id }}, playerName: '{{ addslashes($registration->player->name) }}' })"
                                                                        class="w-full text-left px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                                        Retain Player
                                                                    </button>
                                                                @else
                                                                    <form action="{{ route('admin.players.unretain', $registration->player->id) }}" method="POST"
                                                                          onsubmit="return confirm('Are you sure you want to unretain this player?')">
                                                                        @csrf
                                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                                            Unretain Player
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            @endif
                                                        @endif

                                                        <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

                                                        <form action="{{ route('admin.tournaments.registrations.force-delete', [$tournament, $registration]) }}" method="POST"
                                                              onsubmit="return confirm('Are you sure you want to permanently delete this registration?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $registrations->links() }}
                    </div>
                @else
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No registrations</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No registrations found with the selected filter.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

    {{-- Retain Player Modal --}}
    <div x-data="{
            showRetainModal: false,
            retainPlayerId: null,
            retainPlayerName: '',
            allTeams: @js($actualTeams->map(fn($t) => ['id' => $t->id, 'name' => $t->name])),
        }"
        @open-retain-modal.window="
            retainPlayerId = $event.detail.playerId;
            retainPlayerName = $event.detail.playerName;
            showRetainModal = true;
        "
    >
        <div x-show="showRetainModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="showRetainModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-black/50" @click="showRetainModal = false"></div>

            <div x-show="showRetainModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md z-10">
                <form method="POST" :action="'/admin/players/' + retainPlayerId + '/retain'">
                    @csrf
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Retain Player
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Retaining <span class="font-medium text-gray-700 dark:text-gray-300" x-text="retainPlayerName"></span></p>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Team <span class="text-red-500">*</span></label>
                            <select name="actual_team_id" required class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                                <option value="">-- Select Team --</option>
                                <template x-for="t in allTeams" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div x-data="{ retainVal: '' }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Retained Value <span class="text-red-500">*</span></label>
                            <input type="number" name="retained_value" required min="0" step="any" placeholder="e.g. 500000" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" x-model="retainVal">
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

@push('scripts')
<script>
function copyToClipboard(inputId, btn) {
    const input = document.getElementById(inputId);
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.remove('bg-blue-600', 'bg-purple-600', 'hover:bg-blue-700', 'hover:bg-purple-700');
        btn.classList.add('bg-green-600');
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('bg-green-600');
            if (inputId === 'player-reg-link') {
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            } else {
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
            }
        }, 2000);
    });
}
</script>
@endpush
