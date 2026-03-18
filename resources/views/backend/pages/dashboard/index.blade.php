@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('before_vite_build')
    <script>
        var userGrowthData = @json($user_growth_data['data']);
        var userGrowthLabels = @json($user_growth_data['labels']);
    </script>
@endsection

@section('admin-content')
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

    {!! ld_apply_filters('dashboard_after_breadcrumbs', '') !!}

    @role('Player')
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-10 max-w-md w-full text-center border border-blue-100 dark:border-gray-700">
            <div class="mb-6">
                <h1 class="text-3xl font-extrabold text-blue-700 dark:text-blue-400 mb-1">Welcome to</h1>
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">{{ config('app.name') }}</h2>
            </div>

            <p class="text-lg text-gray-700 dark:text-gray-300 mb-8">
                Hello, <span class="font-semibold">{{ Auth::user()->name ?? 'Guest' }}</span>!
            </p>

            @auth
                <div class="flex flex-col gap-3">
                    <a href="{{ route('profileplayers.edit') }}"
                        class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Update Profile
                    </a>
                    <a href="{{ route('profile.edit') }}"
                        class="inline-flex items-center justify-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-xl shadow-sm transition duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Change Password
                    </a>
                </div>
            @endauth
        </div>
        {!! ld_apply_filters('dashboard_cards_after_player', '') !!}
    @endrole

    @hasanyrole('Admin|Superadmin|Organizer')
        {{-- Live Matches Alert --}}
        @if($live_matches->count() > 0)
        <div class="mb-6 bg-gradient-to-r from-red-500 to-orange-500 rounded-xl p-4 shadow-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
                        </span>
                        <span class="text-white font-bold text-lg">LIVE</span>
                    </div>
                    <span class="text-white/90">{{ $live_matches->count() }} match(es) in progress</span>
                </div>
                <div class="flex items-center gap-2">
                    @foreach($live_matches->take(2) as $match)
                        <a href="{{ route('admin.matches.show', $match->id) }}"
                           class="bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
                            {{ $match->teamA?->short_name ?? 'TBD' }} vs {{ $match->teamB?->short_name ?? 'TBD' }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Quick Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            {{-- Tournaments --}}
            <a href="{{ route('admin.tournaments.index') }}" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-500 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tournament_stats['total'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tournaments</p>
            </a>

            {{-- Active Tournaments --}}
            <a href="{{ route('admin.tournaments.index', ['status' => 'ongoing']) }}" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    @if($tournament_stats['ongoing'] > 0)
                        <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full">Active</span>
                    @endif
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tournament_stats['ongoing'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Ongoing</p>
            </a>

            {{-- Teams --}}
            <a href="{{ route('admin.actual-teams.index') }}" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $team_count }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Teams</p>
            </a>

            {{-- Players --}}
            <a href="{{ route('admin.players.index') }}" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $player_count }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Players</p>
            </a>

            {{-- Matches --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-red-500 to-pink-600 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $match_stats['total'] }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Matches</p>
            </div>

            {{-- Pending Registrations --}}
            <a href="#" class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition group relative">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-yellow-500 to-orange-500 flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    @if($pending_registrations > 0)
                        <span class="absolute top-2 right-2 px-2 py-0.5 text-xs font-bold bg-red-500 text-white rounded-full animate-pulse">{{ $pending_registrations }}</span>
                    @endif
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $pending_registrations }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
            </a>
        </div>

        {{-- Tournament Status Overview --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <a href="{{ route('admin.tournaments.index', ['status' => 'draft']) }}" class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Draft</span>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-2">{{ $tournament_stats['draft'] }}</p>
            </a>
            <a href="{{ route('admin.tournaments.index', ['status' => 'registration']) }}" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-blue-500 animate-pulse"></div>
                    <span class="text-sm font-medium text-blue-600 dark:text-blue-400">Registration Open</span>
                </div>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300 mt-2">{{ $tournament_stats['registration'] }}</p>
            </a>
            <a href="{{ route('admin.tournaments.index', ['status' => 'ongoing']) }}" class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900/30 transition">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400">Ongoing</span>
                </div>
                <p class="text-xl font-bold text-green-700 dark:text-green-300 mt-2">{{ $tournament_stats['ongoing'] }}</p>
            </a>
            <a href="{{ route('admin.tournaments.index', ['status' => 'completed']) }}" class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 border border-purple-200 dark:border-purple-800 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition">
                <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                    <span class="text-sm font-medium text-purple-600 dark:text-purple-400">Completed</span>
                </div>
                <p class="text-xl font-bold text-purple-700 dark:text-purple-300 mt-2">{{ $tournament_stats['completed'] }}</p>
            </a>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Upcoming Matches --}}
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Upcoming Matches
                    </h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Next 5 matches</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($upcoming_matches as $match)
                        <a href="{{ route('admin.matches.show', $match->id) }}" class="block px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $match->match_date?->format('M d') }}</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $match->start_time ?? 'TBD' }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @if($match->teamA?->team_logo)
                                            <img src="{{ asset('storage/' . $match->teamA->team_logo) }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xs font-bold">{{ substr($match->teamA?->name ?? 'A', 0, 2) }}</div>
                                        @endif
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'TBD' }}</span>
                                        <span class="text-gray-400 text-sm">vs</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'TBD' }}</span>
                                        @if($match->teamB?->team_logo)
                                            <img src="{{ asset('storage/' . $match->teamB->team_logo) }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xs font-bold">{{ substr($match->teamB?->name ?? 'B', 0, 2) }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $match->tournament?->name }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $match->ground?->name ?? $match->venue ?? 'Venue TBD' }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">No upcoming matches scheduled</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Tournaments --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Recent Tournaments
                    </h3>
                    <a href="{{ route('admin.tournaments.index') }}" class="text-xs text-blue-500 hover:text-blue-600">View All</a>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($recent_tournaments as $tournament)
                        <a href="{{ route('admin.tournaments.dashboard', $tournament) }}" class="block px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="flex items-center gap-3">
                                @if($tournament->logo)
                                    <img src="{{ asset('storage/' . $tournament->logo) }}" class="w-10 h-10 rounded-lg object-cover" alt="">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                                        {{ substr($tournament->name, 0, 2) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate">{{ $tournament->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tournament->start_date?->format('M d, Y') ?? 'No date' }}</p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($tournament->status === 'ongoing' || $tournament->status === 'in_progress') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
                                    @elseif($tournament->status === 'registration') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400
                                    @elseif($tournament->status === 'completed') bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400
                                    @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-400
                                    @endif">
                                    {{ ucfirst($tournament->status) }}
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center">
                            <p class="text-gray-500 dark:text-gray-400">No tournaments yet</p>
                            <a href="{{ route('admin.tournaments.create') }}" class="mt-2 inline-flex items-center text-sm text-blue-500 hover:text-blue-600">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create Tournament
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Quick Actions & Public Link --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Quick Actions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <a href="{{ route('admin.tournaments.create') }}" class="flex flex-col items-center justify-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl hover:bg-purple-100 dark:hover:bg-purple-900/30 transition group">
                        <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-purple-700 dark:text-purple-300 text-center">New Tournament</span>
                    </a>
                    <a href="{{ route('admin.actual-teams.create') }}" class="flex flex-col items-center justify-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl hover:bg-blue-100 dark:hover:bg-blue-900/30 transition group">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-blue-700 dark:text-blue-300 text-center">Add Team</span>
                    </a>
                    <a href="{{ route('admin.players.create') }}" class="flex flex-col items-center justify-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-xl hover:bg-orange-100 dark:hover:bg-orange-900/30 transition group">
                        <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-orange-700 dark:text-orange-300 text-center">Add Player</span>
                    </a>
                    <a href="{{ route('admin.grounds.create') }}" class="flex flex-col items-center justify-center p-4 bg-green-50 dark:bg-green-900/20 rounded-xl hover:bg-green-100 dark:hover:bg-green-900/30 transition group">
                        <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-green-700 dark:text-green-300 text-center">Add Ground</span>
                    </a>
                </div>
            </div>

            {{-- Public Tournaments Link --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Public Tournaments Page</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Share for public browsing & registration</p>
                        </div>
                    </div>
                    <a href="{{ route('public.tournaments.index') }}" target="_blank"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        Open
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ route('public.tournaments.index') }}"
                           id="public-tournaments-link"
                           class="flex-1 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 font-mono">
                    <button type="button" onclick="copyPublicTournamentsLink()"
                            id="copy-tournaments-btn"
                            class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition border border-gray-300 dark:border-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Copy
                    </button>
                </div>
            </div>
        </div>

        {{-- Recent Registrations --}}
        @if($recent_registrations->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Recent Registrations
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tournament</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($recent_registrations as $reg)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-5 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $reg->type === 'team' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' }}">
                                    {{ ucfirst($reg->type) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $reg->type === 'team' ? ($reg->team?->name ?? $reg->team_name) : ($reg->player?->name ?? $reg->player_name) }}
                            </td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $reg->tournament?->name }}</td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($reg->status === 'approved') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
                                    @elseif($reg->status === 'pending') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400
                                    @else bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400
                                    @endif">
                                    {{ ucfirst($reg->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $reg->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {!! ld_apply_filters('dashboard_after', '') !!}
    @endhasanyrole

    @hasanyrole('Team Manager|Coach|Captain|Viewer|Scorer')
        {{-- Simple Dashboard for other roles --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome, {{ Auth::user()->name }}!</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-1">{{ Auth::user()->roles->pluck('name')->join(', ') }}</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @can('tournament.view')
                <a href="{{ route('admin.tournaments.index') }}" class="flex flex-col items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl hover:bg-purple-100 dark:hover:bg-purple-900/30 transition">
                    <svg class="w-8 h-8 text-purple-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="text-sm font-medium text-purple-700 dark:text-purple-300">Tournaments</span>
                </a>
                @endcan

                @can('team.view')
                <a href="{{ route('admin.actual-teams.index') }}" class="flex flex-col items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                    <svg class="w-8 h-8 text-blue-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Teams</span>
                </a>
                @endcan

                @can('player.view')
                <a href="{{ route('admin.players.index') }}" class="flex flex-col items-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-xl hover:bg-orange-100 dark:hover:bg-orange-900/30 transition">
                    <svg class="w-8 h-8 text-orange-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="text-sm font-medium text-orange-700 dark:text-orange-300">Players</span>
                </a>
                @endcan

                @can('match.view')
                <a href="{{ route('public.tournaments.index') }}" class="flex flex-col items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-xl hover:bg-green-100 dark:hover:bg-green-900/30 transition">
                    <svg class="w-8 h-8 text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm font-medium text-green-700 dark:text-green-300">Matches</span>
                </a>
                @endcan
            </div>
        </div>
    @endhasanyrole
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    function copyPublicTournamentsLink() {
        const input = document.getElementById('public-tournaments-link');
        const btn = document.getElementById('copy-tournaments-btn');
        const originalContent = btn.innerHTML;

        navigator.clipboard.writeText(input.value).then(() => {
            btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg><span class="text-green-600">Copied!</span>`;
            setTimeout(() => {
                btn.innerHTML = originalContent;
            }, 2000);
        });
    }
</script>
@endpush
