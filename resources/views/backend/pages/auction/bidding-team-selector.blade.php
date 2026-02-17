@extends('backend.layouts.app')

@section('title', 'Select Team - ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-4xl md:p-6 lg:p-8">
    {{-- Header --}}
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-500/20 mb-4">
            <i class="fas fa-gavel text-3xl text-blue-500"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $auction->name }}</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-2">Admin Preview Mode - Select a team to view bidding page</p>
    </div>

    {{-- Team Selection --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-users mr-2 text-blue-500"></i>Select Team to Preview
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Choose a team to view the bidding page from their perspective
            </p>
        </div>

        <div class="p-5">
            @if($allTeams->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($allTeams as $team)
                        <a href="{{ route('team.auction.bidding.show', $auction->id) }}?team_id={{ $team->id }}"
                           class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border-2 border-transparent hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all">
                            <div class="flex-shrink-0">
                                @if($team->team_logo)
                                    <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200 dark:border-gray-600">
                                @else
                                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl">
                                        {{ strtoupper(substr($team->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-white truncate">{{ $team->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $team->players_count ?? $team->players()->count() }} players
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                        <i class="fas fa-users-slash text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">No Teams Found</h3>
                    <p class="text-gray-500 dark:text-gray-400">
                        No teams are registered for this tournament yet.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- Back Link --}}
    <div class="mt-6 text-center">
        <a href="{{ route('admin.auction.organizer.panel', $auction->id) }}" class="text-blue-500 hover:text-blue-600">
            <i class="fas fa-arrow-left mr-2"></i>Back to Auction Panel
        </a>
    </div>
</div>
@endsection
