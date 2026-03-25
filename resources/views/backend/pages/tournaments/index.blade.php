@extends('backend.layouts.app')

@section('title', 'Tournaments | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Tournaments</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Manage all cricket tournaments like IPL</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Public Tournaments Link --}}
            <a href="{{ route('public.tournaments.index') }}" target="_blank"
               class="btn btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Public Page
            </a>
            @can('tournament.create')
                <a href="{{ route('admin.tournaments.create') }}"
                    class="btn btn-primary inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                    </svg>
                    New Tournament
                </a>
            @endcan
        </div>
    </div>

    {{-- Stats Dashboard --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        {{-- Total --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'all']) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 transition-all hover:shadow-lg
                  {{ $currentStatus === 'all' ? 'border-blue-500 shadow-lg' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">All Tournaments</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </a>

        {{-- Registration Open --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'registration']) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 transition-all hover:shadow-lg
                  {{ $currentStatus === 'registration' ? 'border-green-500 shadow-lg' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['registration'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Registration Open</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                </div>
            </div>
        </a>

        {{-- Ongoing --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'ongoing']) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 transition-all hover:shadow-lg
                  {{ $currentStatus === 'ongoing' ? 'border-yellow-500 shadow-lg' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['ongoing'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ongoing</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </a>

        {{-- Completed --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'completed']) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 transition-all hover:shadow-lg
                  {{ $currentStatus === 'completed' ? 'border-purple-500 shadow-lg' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['completed'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Completed</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
            </div>
        </a>

        {{-- Draft --}}
        <a href="{{ route('admin.tournaments.index', ['status' => 'draft']) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 transition-all hover:shadow-lg
                  {{ $currentStatus === 'draft' ? 'border-gray-500 shadow-lg' : 'border-gray-200 dark:border-gray-700' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $stats['draft'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Draft</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
            </div>
        </a>

        {{-- Pending Registrations Alert --}}
        @if($stats['pending_registrations'] > 0)
        <div class="bg-gradient-to-r from-orange-500 to-red-500 rounded-xl p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold">{{ $stats['pending_registrations'] }}</p>
                    <p class="text-xs opacity-90 mt-1">Pending Approvals</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                    <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Search Bar --}}
    <div class="mb-6">
        <form method="GET" class="flex gap-3">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <div class="relative flex-1">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search tournaments by name, location, or organization..."
                       class="form-control pl-10 w-full">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            @if(request('search'))
                <a href="{{ route('admin.tournaments.index', ['status' => $currentStatus]) }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    {{-- Tournaments Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse ($tournaments as $tournament)
            @php
                $logo = $tournament->settings?->logo ? Storage::url($tournament->settings->logo) : null;
                $statusColors = [
                    'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    'registration' => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
                    'ongoing' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
                    'active' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
                    'completed' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
                ];
                $statusLabels = [
                    'draft' => 'Draft',
                    'registration' => 'Registration Open',
                    'ongoing' => 'Live',
                    'active' => 'Live',
                    'completed' => 'Completed',
                ];
                $progress = $tournament->matches_count > 0
                    ? round(($tournament->completed_matches_count / $tournament->matches_count) * 100)
                    : 0;
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700
                        overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1 group">

                {{-- Card Header with Logo/Banner --}}
                <div class="relative h-32 bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 overflow-hidden">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $tournament->name }}"
                             class="absolute inset-0 w-full h-full object-cover opacity-30 group-hover:opacity-40 transition-opacity">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>

                    {{-- Status Badge --}}
                    <div class="absolute top-3 right-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$tournament->status] ?? $statusColors['draft'] }}">
                            @if($tournament->status === 'registration')
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                </span>
                            @elseif(in_array($tournament->status, ['ongoing', 'active']))
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-yellow-500"></span>
                                </span>
                            @elseif($tournament->status === 'completed')
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/>
                                </svg>
                            @endif
                            {{ $statusLabels[$tournament->status] ?? 'Unknown' }}
                        </span>
                    </div>

                    {{-- Pending Registrations Badge --}}
                    @if($tournament->pending_registrations_count > 0)
                        <div class="absolute top-3 left-3">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-500 text-white animate-pulse">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                {{ $tournament->pending_registrations_count }} pending
                            </span>
                        </div>
                    @endif

                    {{-- Tournament Name --}}
                    <div class="absolute bottom-3 left-4 right-4">
                        <h2 class="text-xl font-bold text-white truncate" title="{{ $tournament->name }}">
                            {{ $tournament->name }}
                        </h2>
                        @if(auth()->user()->hasRole('Superadmin') && $tournament->organization)
                            <p class="text-sm text-white/70 truncate">{{ $tournament->organization->name }}</p>
                        @endif
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4">
                    {{-- Quick Stats --}}
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $tournament->teams_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Teams</p>
                        </div>
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $tournament->matches_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Matches</p>
                        </div>
                        <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $tournament->completed_matches_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Played</p>
                        </div>
                    </div>

                    {{-- Progress Bar (for ongoing tournaments) --}}
                    @if(in_array($tournament->status, ['ongoing', 'active']) && $tournament->matches_count > 0)
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span>Progress</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-gradient-to-r from-yellow-400 to-orange-500 h-2 rounded-full transition-all"
                                     style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    {{-- Champion/Runner-up for completed --}}
                    @if($tournament->status === 'completed' && ($tournament->champion || $tournament->runnerUp))
                        <div class="mb-4 p-3 bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                            @if($tournament->champion)
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-yellow-500">🏆</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $tournament->champion->name }}</span>
                                </div>
                            @endif
                            @if($tournament->runnerUp)
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-400">🥈</span>
                                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $tournament->runnerUp->name }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Date & Location --}}
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ \Carbon\Carbon::parse($tournament->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }}</span>
                        </div>
                        @if($tournament->location)
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="truncate">{{ $tournament->location }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Card Footer with Actions --}}
                <div class="px-4 pb-4 flex items-center justify-between gap-2">
                    {{-- Manage Dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false"
                                class="btn btn-secondary btn-sm inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            Manage
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition
                             class="absolute left-0 bottom-full mb-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
                            <div class="py-1">
                                <a href="{{ route('admin.tournaments.dashboard', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                    </svg>
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.tournaments.groups.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Groups & Teams
                                </a>
                                <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Fixtures
                                </a>
                                <a href="{{ route('admin.tournaments.calendar.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Calendar
                                </a>
                                <a href="{{ route('admin.tournaments.point-table.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    Point Table
                                </a>
                                <hr class="my-1 border-gray-200 dark:border-gray-700">
                                <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                    </svg>
                                    Registrations
                                    @if($tournament->pending_registrations_count > 0)
                                        <span class="ml-auto bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ $tournament->pending_registrations_count }}</span>
                                    @endif
                                </a>
                                <a href="{{ route('admin.tournaments.awards.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                    </svg>
                                    Awards
                                </a>
                                <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/>
                                    </svg>
                                    Templates
                                </a>
                                <a href="{{ route('admin.tournaments.settings.edit', $tournament) }}"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Settings
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.tournaments.dashboard', $tournament) }}"
                           class="btn btn-sm bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-200 dark:hover:bg-indigo-900/50"
                           title="View Dashboard">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </a>
                        <a href="{{ route('public.tournament.show', $tournament->slug) }}" target="_blank"
                           class="btn btn-sm bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                           title="View Public Page">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        @if(auth()->user()->hasAnyRole(['Superadmin', 'Admin']))
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                               class="btn btn-sm bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50"
                               title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form action="{{ route('admin.tournaments.destroy', $tournament) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Are you sure you want to delete this tournament? This action cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-sm bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-white dark:bg-gray-800 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">No tournaments found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">
                    @if(request('search'))
                        No tournaments match your search criteria.
                    @elseif($currentStatus !== 'all')
                        No tournaments with status "{{ $currentStatus }}".
                    @else
                        Get started by creating your first tournament.
                    @endif
                </p>
                @can('tournament.create')
                    <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Create Tournament
                    </a>
                @endcan
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($tournaments->hasPages())
        <div class="mt-8">
            {{ $tournaments->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
