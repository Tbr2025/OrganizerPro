@extends('backend.layouts.app')

@section('title', 'My Auctions')

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <a href="{{ route('team-manager.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Dashboard
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Auctions</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">View and participate in auctions for {{ $team->name }}</p>
    </div>

    @if($auctions->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($auctions as $auction)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    {{-- Auction Header --}}
                    <div class="p-5 bg-gradient-to-r from-blue-600 to-indigo-700 dark:from-blue-800 dark:to-indigo-900">
                        <h3 class="text-lg font-bold text-white">{{ $auction->title ?? 'Auction' }}</h3>
                        <p class="text-sm text-blue-200">{{ $auction->tournament->name ?? '' }}</p>
                    </div>

                    {{-- Auction Body --}}
                    <div class="p-5 space-y-4">
                        {{-- Status Badge --}}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                            @if($auction->status === 'active')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                    Live Now
                                </span>
                            @elseif($auction->status === 'paused')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    Paused
                                </span>
                            @elseif($auction->status === 'completed')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    Completed
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    Scheduled
                                </span>
                            @endif
                        </div>

                        {{-- Budget Info --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Budget</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ number_format($auction->budget_info['remaining']) }} remaining
                                </span>
                            </div>
                            @php
                                $percentage = $auction->budget_info['max'] > 0
                                    ? ($auction->budget_info['spent'] / $auction->budget_info['max']) * 100
                                    : 0;
                            @endphp
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                {{ number_format($auction->budget_info['spent']) }} / {{ number_format($auction->budget_info['max']) }} spent
                            </p>
                        </div>

                        {{-- Players Won --}}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Players Won</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $auction->auctionPlayers->count() }}
                            </span>
                        </div>
                    </div>

                    {{-- Auction Footer --}}
                    <div class="p-5 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700">
                        @if($auction->status === 'active')
                            <a href="{{ route('team.auction.bidding.show', $auction) }}" class="btn btn-primary w-full flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Join Live Bidding
                            </a>
                        @elseif($auction->status === 'completed')
                            <button disabled class="btn btn-secondary w-full opacity-50 cursor-not-allowed">
                                Auction Ended
                            </button>
                        @else
                            <button disabled class="btn btn-secondary w-full opacity-50 cursor-not-allowed">
                                Not Started Yet
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No Auctions Found</h3>
            <p class="mt-2 text-gray-500 dark:text-gray-400">
                There are no auctions scheduled for your tournament yet.
            </p>
        </div>
    @endif
</div>
@endsection
