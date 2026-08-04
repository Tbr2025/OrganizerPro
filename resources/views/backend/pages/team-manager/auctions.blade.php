@extends('backend.layouts.app')

@section('title', 'My Auctions')

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">
    <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
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
                        <h3 class="text-lg font-bold text-white">{{ $auction->name ?? 'Auction' }}</h3>
                        <p class="text-sm text-blue-200">{{ $auction->tournament->name ?? '' }}</p>
                    </div>

                    {{-- Auction Body --}}
                    <div class="p-5 space-y-4">
                        {{-- Status Badge --}}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                            @if($auction->status === 'running')
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

                        @php $b = $auction->budget_info; @endphp

                        {{-- Purse --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Purse</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $auction->formatAmount($b['remaining']) }} left
                                </span>
                            </div>
                            @php
                                $percentage = $b['max'] > 0 ? ($b['spent'] / $b['max']) * 100 : 0;
                            @endphp
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2.5">
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                {{ format_points($b['spent'], '0') }} of {{ format_points($b['max'], '0') }} spent
                            </p>

                            {{-- Where the money went: retained up front vs won at auction. --}}
                            <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Retained</p>
                                    <p class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                                        {{ format_points($b['retained_spent'], '0') }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-gray-400">At auction</p>
                                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ format_points($b['auction_spent'], '0') }}
                                    </p>
                                </div>
                            </div>

                            {{-- The squad reserve caps what can be bid right now. --}}
                            @if($b['reserve'] > 0)
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-3">
                                    {{ $auction->formatAmount($b['reserve']) }} held back for
                                    {{ max(0, $b['squad_remaining'] - 1) }} more squad place(s) —
                                    max bid {{ $auction->formatAmount($b['max_bid_allowed']) }}.
                                </p>
                            @endif
                        </div>

                        {{-- Squad, split by how each player was acquired. --}}
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Squad</span>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ $b['squad_size'] }}<span class="text-sm text-gray-400">/{{ $b['squad_required'] }}</span>
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $b['retained_count'] }}</p>
                                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Retained</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $b['won_count'] }}</p>
                                    <p class="text-[10px] uppercase tracking-wide text-gray-400">Won</p>
                                </div>
                                <div>
                                    <p class="text-lg font-bold {{ $b['squad_remaining'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                                        {{ $b['squad_remaining'] }}
                                    </p>
                                    <p class="text-[10px] uppercase tracking-wide text-gray-400">To fill</p>
                                </div>
                            </div>
                        </div>

                        {{-- Players Won --}}
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Players Won</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $b['won_count'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Auction Footer --}}
                    <div class="p-5 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 space-y-2">
                        @if(in_array($auction->status, ['running', 'paused']))
                            <a href="{{ route('team.auction.bidding.show', $auction) }}" class="btn btn-primary w-full flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Join Live Bidding
                            </a>
                            <a href="{{ route('public.auction.live', $auction) }}" target="_blank"
                               class="btn btn-dark w-full flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Live Display
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
