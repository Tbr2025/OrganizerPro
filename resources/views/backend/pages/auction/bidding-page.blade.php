@extends('backend.layouts.app')

@section('title', 'Live Auction Bidding | ' . $auction->name)

@section('admin-content')
    <style>
        /* You can reuse the same styles from the organizer panel */
        .timer-bar-inner {
            transition: width 0.5s linear;
        }

        .badge {
            @apply inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-600 text-white;
        }
    </style>

    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Live Auction: {{ $auction->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">You are bidding for: <span
                        class="font-semibold">{{ $userTeam->name }}</span></p>
            </div>
        </div>

        <div x-data="biddingPanel()" x-init="init(
            {{ $auction->id }},
            $el.dataset.userTeam,
            $el.dataset.currentPlayer,
            $el.dataset.bidRules
        )" {{-- Encode complex objects into data attributes for safety --}}
            data-user-team='@json($userTeam)' data-current-player='@json($currentPlayer)'
            data-bid-rules='@json($auction->bid_rules ?? [])' class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Player Card -->
            <div
                class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-md border min-h-[500px] flex items-center justify-center p-6">
                <div x-show="state === 'waiting'" x-transition.opacity class="text-center">
                    <h2 class="text-3xl font-bold text-gray-500 mb-4">Waiting for Next Player...</h2>
                    <p class="text-gray-400">The organizer is selecting the next player to be auctioned.</p>
                </div>

                <div x-show="state === 'bidding'" x-transition.opacity x-cloak class="w-full">
                    <div class="flex flex-col md:flex-row items-center gap-8">
                        <div class="flex-shrink-0 text-center">
                            <img :src="player.image_url" :alt="player.name"
                                class="w-48 h-48 object-cover rounded-full border-4 border-yellow-400 shadow-lg mx-auto">
                            <div class="mt-3">
                                <div class="text-sm text-gray-400">Base Price</div>
                                <div class="text-2xl font-bold" x-text="formatCurrency(player.base_price)"></div>
                            </div>
                        </div>
                        <div class="text-center md:text-left">
                            <h2 class="text-5xl font-bold" x-text="player.name"></h2>
                            <div class="flex items-center justify-center md:justify-start gap-4 mt-3">
                                <span class="badge" x-text="player.role"></span>
                                <span class="badge" x-text="player.batting_style"></span>
                                <span class="badge" x-text="player.bowling_style"></span>
                                <template x-if="player.is_wicket_keeper"><span
                                        class="badge bg-indigo-500 text-white">WK</span></template>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="state === 'sold'" x-transition.opacity x-cloak class="text-center">
                    <h2 class="text-6xl font-extrabold text-green-500 animate-pulse">SOLD!</h2>
                    <p class="text-2xl mt-4">to <strong class="text-white" x-text="winningTeamName"></strong></p>
                    <p class="text-xl mt-2">for <strong class="text-yellow-400"
                            x-text="formatCurrency(finalPrice)"></strong></p>
                </div>
            </div>

            <!-- Right Column: Bidding Controls -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border p-6">
                <div class="text-center">
                    <label class="text-sm font-medium text-gray-500">Your Remaining Budget</label>
                    <div class="text-4xl font-bold text-green-500 mt-1" x-text="formatCurrency(teamBudget)"></div>
                </div>
                <hr class="my-6 border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="text-xl font-semibold text-center mb-2">Bidding Status</h3>
                    <div x-show="state === 'bidding'" x-cloak>
                        <div class="text-center mb-4">
                            <label class="text-sm text-gray-500">Current Bid</label>
                            <p class="text-3xl font-bold text-yellow-400" x-text="formatCurrency(currentBid)"></p>
                            <p class="text-sm text-gray-500">by <strong class="dark:text-white"
                                    x-text="winningTeamName"></strong></p>
                        </div>
                        <button @click="placeBid()" :disabled="!canBid" class="w-full btn btn-primary text-xl py-4"
                            :class="{ 'opacity-50 cursor-not-allowed': !canBid }">
                            <span x-text="bidButtonText"></span>
                        </button>
                        <p x-show="bidError" class="text-red-500 text-sm mt-2 text-center" x-text="bidError" x-cloak></p>
                    </div>
                    <div x-show="state !== 'bidding'" x-cloak class="text-center text-gray-400 py-10">
                        Bidding is not active.
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
