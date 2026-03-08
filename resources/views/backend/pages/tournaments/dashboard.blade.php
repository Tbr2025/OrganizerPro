@extends('backend.layouts.app')

@section('title', $tournament->name . ' - Dashboard | ' . config('app.name'))

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Breadcrumbs --}}
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {{-- Header with Tournament Info --}}
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-700 rounded-2xl p-6 mb-6 text-white relative overflow-hidden">
        @php
            $logo = $tournament->settings?->logo ? Storage::url($tournament->settings->logo) : null;
        @endphp
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $tournament->name }}"
                 class="absolute inset-0 w-full h-full object-cover opacity-20">
        @endif
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/90 via-indigo-600/90 to-purple-700/90"></div>

        <div class="relative z-10">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        @php
                            $statusColors = [
                                'draft' => 'bg-gray-500',
                                'registration' => 'bg-green-500',
                                'ongoing' => 'bg-yellow-500',
                                'active' => 'bg-yellow-500',
                                'completed' => 'bg-purple-500',
                            ];
                            $statusLabels = [
                                'draft' => 'Draft',
                                'registration' => 'Registration Open',
                                'ongoing' => 'Live',
                                'active' => 'Live',
                                'completed' => 'Completed',
                            ];
                        @endphp
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold {{ $statusColors[$tournament->status] ?? 'bg-gray-500' }} text-white">
                            @if(in_array($tournament->status, ['registration', 'ongoing', 'active']))
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                                </span>
                            @endif
                            {{ $statusLabels[$tournament->status] ?? 'Unknown' }}
                        </span>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">{{ $tournament->name }}</h1>
                    <div class="flex flex-wrap items-center gap-4 text-white/80 text-sm">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            {{ \Carbon\Carbon::parse($tournament->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($tournament->end_date)->format('M d, Y') }}
                        </span>
                        @if($tournament->location)
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                {{ $tournament->location }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('public.tournament.show', $tournament->slug) }}" target="_blank"
                       class="btn bg-white/20 hover:bg-white/30 text-white border-white/30">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        View Public Page
                    </a>
                    <a href="{{ route('admin.tournaments.settings.edit', $tournament) }}"
                       class="btn bg-white/20 hover:bg-white/30 text-white border-white/30">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </div>
            </div>

            {{-- Champion/Runner-up for completed tournaments --}}
            @if($tournament->status === 'completed' && ($tournament->champion || $tournament->runnerUp))
                <div class="mt-4 pt-4 border-t border-white/20 flex flex-wrap gap-6">
                    @if($tournament->champion)
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">🏆</span>
                            <div>
                                <p class="text-xs text-white/60">Champion</p>
                                <p class="font-bold">{{ $tournament->champion->name }}</p>
                            </div>
                        </div>
                    @endif
                    @if($tournament->runnerUp)
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">🥈</span>
                            <div>
                                <p class="text-xs text-white/60">Runner Up</p>
                                <p class="font-bold">{{ $tournament->runnerUp->name }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['teams_count'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Teams</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['groups_count'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Groups</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['completed_matches'] }}/{{ $stats['total_matches'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Matches Played</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['upcoming_matches'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Upcoming</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        @if($stats['live_matches'] > 0)
            <div class="bg-gradient-to-r from-red-500 to-orange-500 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold">{{ $stats['live_matches'] }}</p>
                        <p class="text-xs opacity-90 mt-1">Live Now</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
                        </span>
                    </div>
                </div>
            </div>
        @endif

        @if($stats['pending_registrations'] > 0)
            <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
               class="bg-gradient-to-r from-orange-500 to-red-500 rounded-xl p-4 text-white hover:shadow-lg transition">
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
            </a>
        @endif

        @if($stats['unscheduled_matches'] > 0)
            <a href="{{ route('admin.tournaments.calendar.index', $tournament) }}"
               class="bg-gradient-to-r from-amber-500 to-yellow-500 rounded-xl p-4 text-white hover:shadow-lg transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold">{{ $stats['unscheduled_matches'] }}</p>
                        <p class="text-xs opacity-90 mt-1">Unscheduled</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
            </a>
        @endif
    </div>

    {{-- Quick Actions Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
        <a href="{{ route('admin.tournaments.groups.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-blue-500 transition text-center group">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Groups & Teams</p>
        </a>

        <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-green-500 transition text-center group">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Fixtures</p>
        </a>

        <a href="{{ route('admin.tournaments.calendar.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-purple-500 transition text-center group">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Calendar</p>
        </a>

        <a href="{{ route('admin.tournaments.point-table.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-yellow-500 transition text-center group">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Point Table</p>
        </a>

        <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-orange-500 transition text-center group relative">
            @if($stats['pending_registrations'] > 0)
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $stats['pending_registrations'] }}</span>
            @endif
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Registrations</p>
        </a>

        <a href="{{ route('admin.tournaments.templates.index', $tournament) }}"
           class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-pink-500 transition text-center group">
            <div class="w-12 h-12 mx-auto mb-3 rounded-xl bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center group-hover:scale-110 transition">
                <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6z"/>
                </svg>
            </div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">Templates</p>
        </a>
    </div>

    {{-- Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Upcoming Matches --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Upcoming Matches
                </h3>
                <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($upcomingMatches as $match)
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $match->match_date ? \Carbon\Carbon::parse($match->match_date)->format('M d, Y - h:i A') : 'TBD' }}
                            </span>
                            @if($match->ground)
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $match->ground->name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamA?->name ?? 'TBD' }}</span>
                            <span class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded">vs</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamB?->name ?? 'TBD' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p>No upcoming matches</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Results --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Recent Results
                </h3>
                <a href="{{ route('admin.tournaments.fixtures.index', $tournament) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($recentMatches as $match)
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $match->match_date ? \Carbon\Carbon::parse($match->match_date)->format('M d, Y') : '' }}
                            </span>
                            @if($match->winner)
                                <span class="text-xs px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-full">
                                    {{ $match->winner->name }} won
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium {{ $match->winner_team_id === $match->team_a_id ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $match->teamA?->name ?? 'TBD' }}
                            </span>
                            <span class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded">vs</span>
                            <span class="font-medium {{ $match->winner_team_id === $match->team_b_id ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $match->teamB?->name ?? 'TBD' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No completed matches yet</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pending Registrations --}}
        @if($pendingRegistrations->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 lg:col-span-2">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        </span>
                        Pending Registrations
                    </h3>
                    <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">View All</a>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($pendingRegistrations as $registration)
                        <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $registration->type === 'team' ? $registration->team_name : $registration->player?->name }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ ucfirst($registration->type) }} Registration - {{ $registration->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <a href="{{ route('admin.tournaments.registrations.show', [$tournament, $registration]) }}"
                               class="btn btn-sm btn-primary">Review</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
