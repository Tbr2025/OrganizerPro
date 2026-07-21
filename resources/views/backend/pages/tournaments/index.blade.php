@extends('backend.layouts.app')

@section('title', 'Tournaments | ' . config('app.name'))

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[['name' => 'Dashboard', 'route' => route('admin.dashboard')], ['name' => 'Tournaments']]" />
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white tracking-tight">Tournaments</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Manage all cricket tournaments</p>
        </div>
        <div class="flex items-center gap-2.5">
            {{-- Public Tournaments Link --}}
            <a href="{{ route('public.tournaments.index') }}" target="_blank"
               class="btn btn-secondary inline-flex items-center gap-1.5 text-sm">
                <iconify-icon icon="lucide:external-link" width="15"></iconify-icon>
                Public Page
            </a>
            @can('tournament.create')
                <a href="{{ route('admin.tournaments.create') }}"
                    class="btn btn-primary inline-flex items-center gap-1.5 text-sm">
                    <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                    New Tournament
                </a>
            @endcan
        </div>
    </div>

    {{-- Stats Dashboard --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        {{-- Total --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'all']) }}"
           class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 p-4 transition-all hover:shadow-md
                  {{ $currentStatus === 'all' ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">All Tournaments</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                    <iconify-icon icon="lucide:trophy" width="18" class="text-blue-600 dark:text-blue-400"></iconify-icon>
                </div>
            </div>
        </a>

        {{-- Registration Open --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'registration']) }}"
           class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 p-4 transition-all hover:shadow-md
                  {{ $currentStatus === 'registration' ? 'border-green-500 ring-2 ring-green-500/20 shadow-md' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['registration'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Registration Open</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                    </span>
                </div>
            </div>
        </a>

        {{-- Ongoing --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'ongoing']) }}"
           class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 p-4 transition-all hover:shadow-md
                  {{ $currentStatus === 'ongoing' ? 'border-yellow-500 ring-2 ring-yellow-500/20 shadow-md' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['ongoing'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Ongoing</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 flex items-center justify-center">
                    <iconify-icon icon="lucide:play-circle" width="18" class="text-yellow-600 dark:text-yellow-400"></iconify-icon>
                </div>
            </div>
        </a>

        {{-- Completed --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'completed']) }}"
           class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 p-4 transition-all hover:shadow-md
                  {{ $currentStatus === 'completed' ? 'border-purple-500 ring-2 ring-purple-500/20 shadow-md' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['completed'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Completed</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
                    <iconify-icon icon="lucide:award" width="18" class="text-purple-600 dark:text-purple-400"></iconify-icon>
                </div>
            </div>
        </a>

        {{-- Draft --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'draft']) }}"
           class="rounded-xl border bg-white shadow-sm dark:bg-gray-900 p-4 transition-all hover:shadow-md
                  {{ $currentStatus === 'draft' ? 'border-gray-500 ring-2 ring-gray-500/20 shadow-md' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $stats['draft'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">Draft</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <iconify-icon icon="lucide:file-edit" width="18" class="text-gray-500 dark:text-gray-400"></iconify-icon>
                </div>
            </div>
        </a>

        {{-- Pending Registrations Alert --}}
        @if($stats['pending_registrations'] > 0)
        <div class="rounded-xl bg-gradient-to-br from-orange-500 to-red-500 p-4 text-white shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold">{{ $stats['pending_registrations'] }}</p>
                    <p class="text-xs font-medium opacity-90 mt-1">Pending Approvals</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center">
                    <iconify-icon icon="lucide:bell-ring" width="18" class="animate-pulse"></iconify-icon>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Search & Filters --}}
    <div class="mb-6 space-y-3">
        <form method="GET" class="flex flex-wrap gap-2.5">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <div class="relative flex-1 min-w-[200px]">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                    <iconify-icon icon="lucide:search" width="16" class="text-gray-400"></iconify-icon>
                </div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by name, location, or organization..."
                       class="form-control pl-10 w-full rounded-xl">
            </div>
            <select name="type" class="form-control rounded-xl w-auto min-w-[140px]">
                <option value="">All Types</option>
                <option value="open" @selected(request('type') === 'open')>Open</option>
                <option value="auction" @selected(request('type') === 'auction')>Auction</option>
            </select>
            <select name="sort_by" class="form-control rounded-xl w-auto min-w-[170px]">
                <option value="default" @selected(request('sort_by', 'default') === 'default')>Sort: Default</option>
                <option value="created_newest" @selected(request('sort_by') === 'created_newest')>Created: Newest</option>
                <option value="created_oldest" @selected(request('sort_by') === 'created_oldest')>Created: Oldest</option>
                <option value="updated_newest" @selected(request('sort_by') === 'updated_newest')>Updated: Newest</option>
                <option value="updated_oldest" @selected(request('sort_by') === 'updated_oldest')>Updated: Oldest</option>
                <option value="start_date" @selected(request('sort_by') === 'start_date')>Start Date</option>
            </select>
            <button type="submit" class="btn btn-primary rounded-xl inline-flex items-center gap-1.5">
                <iconify-icon icon="lucide:search" width="15"></iconify-icon>
                Search
            </button>
            @if(request('search') || request('type') || (request('sort_by') && request('sort_by') !== 'default'))
                <a href="{{ route('admin.tournaments.index', ['status' => $currentStatus]) }}" class="btn btn-secondary rounded-xl inline-flex items-center gap-1.5">
                    <iconify-icon icon="lucide:x" width="15"></iconify-icon>
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Tournaments Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse ($tournaments as $tournament)
            @php
                $logo = $tournament->settings?->logo ? Storage::url($tournament->settings->logo) : null;

                // Compute effective display status
                $displayStatus = $tournament->status;
                if ($displayStatus === 'registration') {
                    $regOpen = $tournament->settings?->isRegistrationOpen() ?? false;
                    if (!$regOpen) {
                        // Registration closed but status not updated — show as "ongoing"
                        $displayStatus = 'ongoing';
                    }
                }

                $statusColors = [
                    'draft' => 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600',
                    'registration' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/40 dark:text-green-300 dark:ring-green-500/30',
                    'reg_closed' => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-900/40 dark:text-orange-300 dark:ring-orange-500/30',
                    'ongoing' => 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20 dark:bg-yellow-900/40 dark:text-yellow-300 dark:ring-yellow-500/30',
                    'active' => 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20 dark:bg-yellow-900/40 dark:text-yellow-300 dark:ring-yellow-500/30',
                    'completed' => 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-900/40 dark:text-purple-300 dark:ring-purple-500/30',
                ];
                $statusLabels = [
                    'draft' => 'Draft',
                    'registration' => 'Registration Open',
                    'reg_closed' => 'Registration Closed',
                    'ongoing' => 'Live',
                    'active' => 'Live',
                    'completed' => 'Completed',
                ];
                $progress = $tournament->matches_count > 0
                    ? round(($tournament->completed_matches_count / $tournament->matches_count) * 100)
                    : 0;
            @endphp

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900
                        hover:shadow-md transition-shadow duration-200 group">

                {{-- Card Header with Logo/Banner --}}
                <div class="relative h-32 bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 overflow-hidden rounded-t-xl">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $tournament->name }}"
                             class="absolute inset-0 w-full h-full object-cover opacity-30 group-hover:opacity-40 transition-opacity">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>

                    {{-- Status Badge --}}
                    <div class="absolute top-3 right-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$displayStatus] ?? $statusColors['draft'] }}">
                            @if($displayStatus === 'registration')
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-green-500"></span>
                                </span>
                            @elseif(in_array($displayStatus, ['ongoing', 'active']))
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-yellow-500"></span>
                                </span>
                            @elseif($displayStatus === 'completed')
                                <iconify-icon icon="lucide:check-circle" width="12"></iconify-icon>
                            @endif
                            {{ $statusLabels[$displayStatus] ?? 'Unknown' }}
                        </span>
                    </div>

                    {{-- Pending Registrations Badge --}}
                    @if($tournament->pending_registrations_count > 0)
                        <div class="absolute top-3 left-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-500 text-white ring-1 ring-inset ring-red-400/50 animate-pulse">
                                <iconify-icon icon="lucide:bell" width="12"></iconify-icon>
                                {{ $tournament->pending_registrations_count }} pending
                            </span>
                        </div>
                    @endif

                    {{-- Tournament Name --}}
                    <div class="absolute bottom-3 left-4 right-4">
                        <h2 class="text-lg font-semibold text-white truncate leading-tight" title="{{ $tournament->name }}">
                            {{ $tournament->name }}
                        </h2>
                        @if(auth()->user()->hasRole('Superadmin') && $tournament->organization)
                            <p class="text-xs text-white/70 truncate mt-0.5">{{ $tournament->organization->name }}</p>
                        @endif
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4">
                    {{-- Quick Stats --}}
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="text-center py-2 px-1 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-base font-bold text-gray-900 dark:text-white leading-tight">{{ $tournament->teams_count }}</p>
                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">Teams</p>
                        </div>
                        <div class="text-center py-2 px-1 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-base font-bold text-gray-900 dark:text-white leading-tight">{{ $tournament->matches_count }}</p>
                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">Matches</p>
                        </div>
                        <div class="text-center py-2 px-1 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <p class="text-base font-bold text-gray-900 dark:text-white leading-tight">{{ $tournament->completed_matches_count }}</p>
                            <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 mt-0.5">Played</p>
                        </div>
                    </div>

                    {{-- Progress Bar (for ongoing tournaments) --}}
                    @if(in_array($tournament->status, ['ongoing', 'active']) && $tournament->matches_count > 0)
                        <div class="mb-3">
                            <div class="flex justify-between text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">
                                <span>Progress</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 h-1.5 rounded-full transition-all"
                                     style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    {{-- Champion/Runner-up for completed --}}
                    @if($tournament->status === 'completed' && ($tournament->champion || $tournament->runnerUp))
                        <div class="mb-3 p-2.5 bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-lg border border-yellow-200/80 dark:border-yellow-800/50">
                            @if($tournament->champion)
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-yellow-500 text-sm">🏆</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $tournament->champion->name }}</span>
                                </div>
                            @endif
                            @if($tournament->runnerUp)
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400 text-sm">🥈</span>
                                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ $tournament->runnerUp->name }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Date & Location --}}
                    <div class="space-y-1.5 text-sm text-gray-600 dark:text-gray-400 mb-3">
                        <div class="flex items-center gap-2">
                            <iconify-icon icon="lucide:calendar" width="14" class="text-gray-400 shrink-0"></iconify-icon>
                            <span class="text-xs">{{ \Carbon\Carbon::parse($tournament->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }}</span>
                        </div>
                        @if($tournament->location)
                            <div class="flex items-center gap-2">
                                <iconify-icon icon="lucide:map-pin" width="14" class="text-gray-400 shrink-0"></iconify-icon>
                                <span class="truncate text-xs">{{ $tournament->location }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Card Footer with Actions --}}
                <div class="px-4 pb-4 pt-0 flex items-center justify-between gap-2 flex-wrap">
                    {{-- Manage Dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false"
                                class="btn btn-secondary btn-sm inline-flex items-center gap-1 text-xs">
                            <iconify-icon icon="lucide:menu" width="14"></iconify-icon>
                            Manage
                            <iconify-icon icon="lucide:chevron-down" width="14"></iconify-icon>
                        </button>
                        <div x-show="open" x-transition
                             class="absolute left-0 bottom-full mb-2 w-52 bg-white dark:bg-gray-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
                            <div class="py-1">
                                <a href="{{ route('admin.tournaments.dashboard', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:layout-dashboard" width="15" class="text-gray-400"></iconify-icon>
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.tournaments.groups.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:users" width="15" class="text-gray-400"></iconify-icon>
                                    Groups & Teams
                                </a>
                                <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:calendar-days" width="15" class="text-gray-400"></iconify-icon>
                                    Fixtures
                                </a>
                                <a href="{{ route('admin.tournaments.calendar.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:calendar" width="15" class="text-gray-400"></iconify-icon>
                                    Calendar
                                </a>
                                <a href="{{ route('admin.tournaments.point-table.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:bar-chart-3" width="15" class="text-gray-400"></iconify-icon>
                                    Point Table
                                </a>
                                <hr class="my-1 border-gray-100 dark:border-gray-700">
                                <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:user-plus" width="15" class="text-gray-400"></iconify-icon>
                                    Registrations
                                    @if($tournament->pending_registrations_count > 0)
                                        <span class="ml-auto inline-flex items-center justify-center min-w-[18px] h-[18px] text-[10px] font-bold bg-red-500 text-white rounded-full px-1">{{ $tournament->pending_registrations_count }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('admin.pending-approvals.index', ['tournament_id' => $tournament->id]) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:clock" width="15" class="text-amber-500"></iconify-icon>
                                    Pending Approvals
                                </a>
                                <a href="{{ route('admin.tournaments.awards.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:star" width="15" class="text-gray-400"></iconify-icon>
                                    Awards
                                </a>
                                <a href="{{ route('admin.tournaments.templates.generate', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:image" width="15" class="text-gray-400"></iconify-icon>
                                    Generate Poster
                                </a>
                                @role('Superadmin')
                                <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:layout-template" width="15" class="text-gray-400"></iconify-icon>
                                    Manage Templates
                                </a>
                                @endrole
                                <a href="{{ route('admin.tournaments.banners.index', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:megaphone" width="15" class="text-gray-400"></iconify-icon>
                                    Banners / Ads
                                </a>
                                <a href="{{ route('admin.tournaments.settings.edit', $tournament) }}"
                                   class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <iconify-icon icon="lucide:settings" width="15" class="text-gray-400"></iconify-icon>
                                    Settings
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('admin.tournaments.dashboard', $tournament) }}"
                           class="btn btn-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors"
                           title="View Dashboard">
                            <iconify-icon icon="lucide:eye" width="15"></iconify-icon>
                        </a>
                        <a href="{{ route('public.tournament.show', $tournament->slug) }}" target="_blank"
                           class="btn btn-sm bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                           title="View Public Page">
                            <iconify-icon icon="lucide:external-link" width="15"></iconify-icon>
                        </a>
                        @if(auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                               class="btn btn-sm bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
                               title="Edit">
                                <iconify-icon icon="lucide:pencil" width="15"></iconify-icon>
                            </a>
                            <form action="{{ route('admin.tournaments.destroy', $tournament) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Are you sure you want to delete this tournament? This action cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-sm bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors"
                                        title="Delete">
                                    <iconify-icon icon="lucide:trash-2" width="15"></iconify-icon>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    <iconify-icon icon="lucide:trophy" width="24" class="text-gray-400"></iconify-icon>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">No tournaments found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5 max-w-sm mx-auto">
                    @if(request('search'))
                        No tournaments match your search criteria.
                    @elseif($currentStatus !== 'all')
                        No tournaments with status "{{ $currentStatus }}".
                    @else
                        Get started by creating your first tournament.
                    @endif
                </p>
                @can('tournament.create')
                    <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary inline-flex items-center gap-1.5">
                        <iconify-icon icon="lucide:plus" width="16"></iconify-icon>
                        Create Tournament
                    </a>
                @endcan
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($tournaments->hasPages())
        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-800">
            {{ $tournaments->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
