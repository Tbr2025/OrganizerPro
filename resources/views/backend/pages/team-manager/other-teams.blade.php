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
        <div class="space-y-3">
            @foreach($otherTeams as $otherTeam)
                <a href="{{ route('team-manager.other-teams.players', $otherTeam) }}" class="block group">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-transparent transition-all duration-300 ease-in-out group-hover:shadow-xl group-hover:border-blue-500 group-hover:scale-[1.02]">
                        <div class="flex items-center p-3 gap-4">
                            {{-- Team Logo --}}
                            <div class="flex-shrink-0">
                                @if($otherTeam->team_logo)
                                    <img src="{{ asset('storage/' . $otherTeam->team_logo) }}" alt="{{ $otherTeam->name }}" class="h-14 w-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700">
                                @else
                                    <div class="h-14 w-14 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center border-2 border-gray-200 dark:border-gray-700">
                                        <span class="text-xl font-bold text-white">{{ strtoupper(substr($otherTeam->name, 0, 2)) }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Team Name & Short Name --}}
                            <div class="min-w-0" style="flex-basis: 50%;">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white truncate">{{ $otherTeam->name }}</h3>
                                @if($otherTeam->short_name)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $otherTeam->short_name }}</p>
                                @endif
                            </div>

                            {{-- Squad and purse.
                                 A manager looking at the other teams is asking one thing: who can
                                 still outbid me, and for how many players. This answered it with a
                                 headcount alone, which is the least useful half. --}}
                            @php $purse = $purses[$otherTeam->id] ?? null; @endphp
                            <div class="hidden sm:flex items-center gap-2 flex-wrap" style="flex-basis: 30%;">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ $otherTeam->approved_players_count ?? 0 }} Players
                                </span>

                                @if($purse && $auction)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                          title="Purse left after everything they have bought and retained">
                                        {{ $auction->formatAmount($purse['remaining'] ?? 0) }} left
                                    </span>

                                    @if(($purse['slots_remaining'] ?? null) !== null)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                                              title="Places still to fill before their squad is complete">
                                            {{ $purse['slots_remaining'] }} to fill
                                        </span>
                                    @endif

                                    @if($purse['squad_full'] ?? false)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            Squad full
                                        </span>
                                    @endif
                                @endif
                            </div>

                            {{-- Arrow --}}
                            <div class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
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
