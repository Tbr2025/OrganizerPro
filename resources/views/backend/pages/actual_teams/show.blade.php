@extends('backend.layouts.app')

@section('title', $actualTeam->name . ' | Team Details')

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="[
        ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
        ['name' => 'Teams'],
        ['name' => 'Team Details']
    ]" />

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
                    <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
                    @can('actual-team.edit')
                        <a href="{{ route('admin.actual-teams.edit', $actualTeam) }}" class="btn btn-primary btn-sm">Edit
                            Team</a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- TEAM INFO BAR --}}
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
                @php
                    // Separate players from non-player roles (Owner, Manager, Team Manager)
                    $nonPlayerRoles = ['Owner', 'Manager', 'Team Manager'];
                    $players = $actualTeam->users->filter(fn($u) => !in_array($u->pivot->role, $nonPlayerRoles));
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

        {{-- ======================================================= --}}
        {{-- START: PLAYER ROSTER (from pivot table) --}}
        {{-- ======================================================= --}}
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
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- ======================================================= --}}
        {{-- TEAM MANAGEMENT (Owner / Manager) --}}
        {{-- ======================================================= --}}
        @if ($teamOwner || $teamManager)
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
        {{-- START: LEGACY SQUAD (from actual_team_users) --}}
        {{-- ======================================================= --}}
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
        {{-- ======================================================= --}}
        {{-- END: PLAYER ROSTER --}}
        {{-- ======================================================= --}}
    </div>
@endsection
