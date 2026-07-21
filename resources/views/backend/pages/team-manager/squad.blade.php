@extends('backend.layouts.app')

@section('title', 'My Squad')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'My Squad']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Squad</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $team->name }} &mdash; {{ $teamPlayers->count() }} player{{ $teamPlayers->count() !== 1 ? 's' : '' }}</p>
    </div>

    @if($teamPlayers->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($teamPlayers as $player)
            <a href="{{ route('team-manager.players.show', $player) }}"
               class="block rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow">
                {{-- Blue Gradient Header --}}
                <div class="bg-gradient-to-r from-blue-600 to-cyan-700 p-4">
                    <div class="flex items-center gap-3">
                        @if($player->image_path)
                            <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}"
                                 class="w-14 h-14 rounded-full object-cover border-2 border-white/30 flex-shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                                <span class="text-xl font-bold text-white">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white truncate">{{ $player->name }}</h3>
                            </div>
                            @if($player->playerType?->type)
                                <p class="text-white/70 text-xs mt-0.5">{{ $player->playerType->type }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Stats Row --}}
                <div class="bg-white dark:bg-gray-800 px-4 pt-3 pb-2">
                    <div class="flex items-center justify-around text-center">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_matches ?? 0 }}</p>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Matches</p>
                        </div>
                        <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_runs ?? 0 }}</p>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Runs</p>
                        </div>
                        <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_wickets ?? 0 }}</p>
                            <p class="text-[10px] text-gray-400 uppercase tracking-wider">Wickets</p>
                        </div>
                    </div>
                </div>

                {{-- Badges Section --}}
                <div class="bg-white dark:bg-gray-800 px-4 pb-3 pt-2">
                    <div class="flex flex-wrap gap-1.5">
                        @if($player->battingProfile?->style)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                        @endif
                        @if($player->bowlingProfile?->style)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                        @endif
                        @if($player->status === 'approved')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Verified</span>
                        @elseif($player->status === 'rejected')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">Rejected</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">Pending</span>
                        @endif
                        @if($player->player_mode === 'retained')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gradient-to-r from-purple-500 to-violet-600 text-white">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                Retained
                            </span>
                        @endif
                        @if($player->user?->hasRole('Team Manager'))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">Manager</span>
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players in your squad</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Players who register for your team will appear here.</p>
        </div>
    @endif
</div>
@endsection
