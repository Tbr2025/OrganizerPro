@extends('backend.layouts.app')

@section('title', 'Other Teams')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'Other Teams']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Other Teams</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Browse other teams in {{ $team->tournament->name ?? 'the tournament' }}</p>
    </div>

    @if($otherTeams->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($otherTeams as $otherTeam)
                <a href="{{ route('team-manager.other-teams.players', $otherTeam) }}"
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow block">
                    <div class="flex items-center gap-4 mb-4">
                        @if($otherTeam->team_logo)
                            <img src="{{ asset('storage/' . $otherTeam->team_logo) }}" alt="{{ $otherTeam->name }}" class="w-16 h-16 rounded-lg object-cover">
                        @else
                            <div class="w-16 h-16 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xl font-bold">
                                {{ strtoupper(substr($otherTeam->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">{{ $otherTeam->name }}</h3>
                            @if($otherTeam->short_name)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $otherTeam->short_name }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ $otherTeam->approved_players_count ?? 0 }} Players
                        </span>
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No other teams</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no other teams in this tournament yet.</p>
        </div>
    @endif
</div>
@endsection
