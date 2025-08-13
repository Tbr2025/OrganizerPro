@extends('backend.layouts.app')

@section('title', $actualTeam->name . ' | Team Details')

@section('admin-content')
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
                        Playing in: <span class="font-semibold">{{ $actualTeam->tournament->name ?? 'N/A' }}</span>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 text-center">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Organization</div>
                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ $actualTeam->organization->name ?? 'N/A' }}</div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Squad Size</div>
                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ count($actualTeam->users) }}
                    Players</div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Tournament</div>
                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ $actualTeam->tournament->name ?? 'N/A' }}</div>
            </div>
        </div>


        {{-- PLAYER ROSTER --}}
        {{-- PLAYER ROSTER --}}
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">The Squad</h2>
        <div class="space-y-3">
            @forelse($actualTeam->users as $player)

                {{-- This is the new, IPL-style Player List Row --}}

                @can('player.view')
                    {{-- If user can view details, the whole row is a link --}}
                    <a href="{{ route('admin.players.show', $player->id) }}" class="block group">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent 
                            transition-all duration-300 ease-in-out 
                            group-hover:shadow-xl group-hover:border-blue-500 group-hover:scale-[1.02]">
                            <div class="flex items-center p-3 gap-4">

                                {{-- 1. Player Image and Name --}}
                                <div class="flex items-center gap-4 flex-grow w-1/3">
                                    <img class="h-14 w-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700"
                                        src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) . '&background=EBF4FF&color=7F9CF5' }}"
                                        alt="{{ $player->name }}">
                                    <div>
                                        <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $player->name }}</h3>
                                        @if ($player->pivot->role)
                                            <span
                                                class="text-xs font-semibold px-2 py-0.5 rounded-full
                                        {{ $player->pivot->role === 'Captain' ? 'bg-yellow-400 text-yellow-900' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                                                {{ $player->pivot->role }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- 2. Player Stats --}}
                                <div class="hidden md:flex items-center justify-around flex-grow w-1/3 text-center">
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                            {{ $player->total_matches ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Matches
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                            {{ $player->total_runs ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Runs
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                            {{ $player->total_wickets ?? 0 }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Wickets
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. Action / Link Indicator --}}
                                <div class="flex items-center justify-end flex-grow w-1/3 text-right">
                                    <div
                                        class="flex items-center gap-2 text-blue-600 dark:text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <span class="text-sm font-semibold">View Profile</span>
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
                    {{-- Fallback: Non-clickable row for users without permission --}}
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent">
                        <div class="flex items-center p-3 gap-4">
                            {{-- Content is identical, just without the <a> wrapper and hover effects --}}
                            <div class="flex items-center gap-4 flex-grow w-1/3">
                                <img class="h-14 w-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700"
                                    src="{{ $player->image_path ? Storage::url($player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($player->name) . '&background=EBF4FF&color=7F9CF5' }}"
                                    alt="{{ $player->name }}">
                                <div>
                                    <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ $player->name }}</h3>
                                    @if ($player->pivot->role)
                                        <span
                                            class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $player->pivot->role === 'Captain' ? 'bg-yellow-400 text-yellow-900' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                                            {{ $player->pivot->role }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="hidden md:flex items-center justify-around flex-grow w-1/3 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-white">

                                        {{ $player->total_matches ?? 0 }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Matches</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                        {{ $player->total_runs ?? 0 }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Runs</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                        {{ $player->total_wickets ?? 0 }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Wickets</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

            @empty
                {{-- Same great "empty" state as before --}}
                <div
                    class="col-span-full p-8 text-center bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">This team has no players yet.</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You can add players by editing the team.</p>
                    @can('actual-team.edit')
                        <div class="mt-6">
                            <a href="{{ route('admin.actual-teams.edit', $actualTeam) }}" class="btn btn-primary">
                                Add Players Now
                            </a>
                        </div>
                    @endcan
                </div>
            @endforelse
        </div>
    </div>
@endsection
