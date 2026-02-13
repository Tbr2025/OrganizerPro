@extends('backend.layouts.app')

@section('title', 'Live Auction | ' . $auction->name)

@push('styles')
<style>
    .player-card-glow {
        animation: glow 2s ease-in-out infinite alternate;
    }
    @keyframes glow {
        from { box-shadow: 0 0 20px rgba(59, 130, 246, 0.5); }
        to { box-shadow: 0 0 40px rgba(59, 130, 246, 0.8), 0 0 60px rgba(59, 130, 246, 0.4); }
    }
    .sold-overlay {
        animation: soldPulse 0.5s ease-out;
    }
    @keyframes soldPulse {
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); opacity: 1; }
    }
    .unsold-overlay {
        animation: unsoldPulse 0.5s ease-out;
    }
    @keyframes unsoldPulse {
        0% { transform: scale(0.5); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .bid-flash {
        animation: bidFlash 0.3s ease-out;
    }
    @keyframes bidFlash {
        0% { background-color: rgba(34, 197, 94, 0.3); }
        100% { background-color: transparent; }
    }
    .tumbler-spin {
        animation: tumblerSpin 0.1s linear;
    }
    @keyframes tumblerSpin {
        0% { transform: translateY(-100%); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
    .timer-critical {
        animation: timerPulse 0.5s ease-in-out infinite;
    }
    @keyframes timerPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .queue-item {
        transition: all 0.3s ease;
    }
    .queue-item:hover {
        transform: translateX(5px);
    }
</style>
@endpush

@section('admin-content')
<div class="min-h-screen bg-gray-900"
     x-data="auctionOrganizerPanel()"
     x-init="init(
         {{ $auction->id }},
         '{{ $auction->status }}',
         {{ json_encode($availablePlayers->map(fn($ap) => [
             'id' => $ap->id,
             'name' => $ap->player->name,
             'base_price' => $ap->base_price,
             'image_path' => $ap->player->image_path,
             'player_type' => $ap->player->playerType?->name ?? 'Player',
             'batting_style' => $ap->player->battingProfile?->name ?? null,
             'bowling_style' => $ap->player->bowlingProfile?->name ?? null,
         ])) }},
         {{ json_encode($teams->map(fn($t) => [
             'id' => $t->id,
             'name' => $t->name,
             'short_name' => $t->short_name ?? substr($t->name, 0, 3),
             'logo_path' => $t->logo_path,
             'players_bought' => $t->players_bought ?? 0,
             'total_spent' => $t->total_spent ?? 0,
             'remaining_budget' => $t->remaining_budget ?? $auction->max_budget_per_team,
         ])) }},
         {{ $auction->max_budget_per_team }},
         {{ json_encode($currentPlayer ? [
             'id' => $currentPlayer->id,
             'player' => [
                 'id' => $currentPlayer->player->id,
                 'name' => $currentPlayer->player->name,
                 'image_path' => $currentPlayer->player->image_path,
                 'player_type' => $currentPlayer->player->playerType?->name ?? 'Player',
                 'batting_style' => $currentPlayer->player->battingProfile?->name ?? null,
                 'bowling_style' => $currentPlayer->player->bowlingProfile?->name ?? null,
             ],
             'base_price' => $currentPlayer->base_price,
             'current_price' => $currentPlayer->current_price,
             'bids' => $currentPlayer->bids->map(fn($b) => [
                 'id' => $b->id,
                 'amount' => $b->amount,
                 'team' => ['id' => $b->team->id, 'name' => $b->team->name],
                 'user' => ['name' => $b->user->name],
                 'created_at' => $b->created_at->toISOString(),
             ]),
         ] : null) }}
     )">

    {{-- Header Bar --}}
    <div class="bg-gray-800 border-b border-gray-700 px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('admin.auctions.show', $auction) }}" class="text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-white">{{ $auction->name }}</h1>
                    <p class="text-sm text-gray-400">Live Auction Control Panel</p>
                </div>
            </div>

            {{-- Auction Status Badge --}}
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                              :class="auctionStatus === 'running' ? 'bg-green-400' : 'bg-yellow-400'"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3"
                              :class="auctionStatus === 'running' ? 'bg-green-500' : 'bg-yellow-500'"></span>
                    </span>
                    <span class="text-sm font-medium"
                          :class="auctionStatus === 'running' ? 'text-green-400' : 'text-yellow-400'"
                          x-text="auctionStatus.toUpperCase()"></span>
                </div>

                {{-- Control Buttons --}}
                <div class="flex gap-2">
                    <button @click="togglePause()" x-show="auctionStatus === 'running'"
                            class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium transition">
                        Pause
                    </button>
                    <button @click="startAuction()" x-show="auctionStatus === 'paused' || auctionStatus === 'scheduled'"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">
                        <span x-text="auctionStatus === 'paused' ? 'Resume' : 'Start Auction'"></span>
                    </button>
                    <button @click="endAuction()" x-show="auctionStatus === 'running'"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition">
                        End Auction
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex h-[calc(100vh-80px)]">

        {{-- Left Sidebar: Player Queue --}}
        <div class="w-72 bg-gray-800 border-r border-gray-700 flex flex-col">
            <div class="p-4 border-b border-gray-700">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Player Queue
                </h2>
                <p class="text-sm text-gray-400 mt-1"><span x-text="availablePlayers.length"></span> players waiting</p>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                <template x-for="(player, index) in availablePlayers.slice(0, 20)" :key="player.id">
                    <div class="queue-item bg-gray-700/50 rounded-lg p-3 cursor-pointer hover:bg-gray-700"
                         @click="selectAndPutOnBid(player)">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <img :src="player.image_path ? `/storage/${player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&size=40&background=random`"
                                     class="w-10 h-10 rounded-full object-cover">
                                <span class="absolute -top-1 -left-1 w-5 h-5 bg-blue-600 rounded-full text-xs flex items-center justify-center text-white font-bold"
                                      x-text="index + 1"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate" x-text="player.name"></p>
                                <p class="text-xs text-gray-400" x-text="player.player_type"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-green-400 font-medium" x-text="formatCurrency(player.base_price)"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="availablePlayers.length === 0" class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-sm">All players auctioned!</p>
                </div>
            </div>

            {{-- Next Player Button --}}
            <div class="p-4 border-t border-gray-700">
                <button @click="startTumbler()"
                        :disabled="isTumbling || displayState === 'bidding' || availablePlayers.length === 0"
                        class="w-full py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 disabled:from-gray-600 disabled:to-gray-600 disabled:cursor-not-allowed text-white rounded-xl font-bold text-lg transition-all transform hover:scale-[1.02] active:scale-[0.98]">
                    <span x-show="!isTumbling">Next Player</span>
                    <span x-show="isTumbling" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Selecting...
                    </span>
                </button>
            </div>
        </div>

        {{-- Center: Main Player Display --}}
        <div class="flex-1 flex flex-col items-center justify-center p-8 relative overflow-hidden">

            {{-- Background Pattern --}}
            <div class="absolute inset-0 opacity-5">
                <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
            </div>

            {{-- Waiting State --}}
            <div x-show="displayState === 'waiting'" x-transition class="text-center">
                <div class="w-32 h-32 mx-auto mb-6 rounded-full bg-gray-800 border-4 border-dashed border-gray-600 flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-400 mb-2" x-text="statusText"></h2>
                <p class="text-gray-500">Click "Next Player" or select from the queue</p>
            </div>

            {{-- Tumbling State --}}
            <div x-show="displayState === 'tumbling'" x-transition class="text-center">
                <div class="w-48 h-48 mx-auto mb-6 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center player-card-glow">
                    <span class="text-6xl font-bold text-white tumbler-spin" x-text="tumblerText.charAt(0)"></span>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2 tumbler-spin" x-text="tumblerText"></h2>
                <p class="text-blue-400">Selecting player...</p>
            </div>

            {{-- Bidding State: Main Player Card --}}
            <div x-show="displayState === 'bidding'" x-transition class="w-full max-w-2xl">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-3xl overflow-hidden border-2 border-blue-500 player-card-glow">
                    {{-- Player Image & Info --}}
                    <div class="relative">
                        <div class="h-80 bg-gradient-to-b from-blue-600/20 to-transparent flex items-end justify-center pb-4">
                            <img :src="currentPlayer?.player?.image_path ? `/storage/${currentPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(currentPlayer?.player?.name || 'P')}&size=300&background=random`"
                                 class="w-64 h-64 rounded-2xl object-cover object-top shadow-2xl border-4 border-white/20">
                        </div>

                        {{-- Base Price Badge --}}
                        <div class="absolute top-4 left-4 bg-black/50 backdrop-blur px-4 py-2 rounded-full">
                            <span class="text-sm text-gray-300">Base: </span>
                            <span class="text-lg font-bold text-white" x-text="formatCurrency(currentPlayer?.base_price)"></span>
                        </div>

                        {{-- Timer --}}
                        <div class="absolute top-4 right-4 bg-black/50 backdrop-blur px-4 py-2 rounded-full">
                            <span class="text-2xl font-bold" :class="biddingTimerSeconds <= 5 ? 'text-red-500 timer-critical' : 'text-white'"
                                  x-text="biddingTimerSeconds + 's'"></span>
                        </div>
                    </div>

                    {{-- Player Details --}}
                    <div class="p-6 text-center">
                        <h2 class="text-4xl font-bold text-white mb-2" x-text="currentPlayer?.player?.name"></h2>
                        <div class="flex items-center justify-center gap-4 text-gray-400 mb-6">
                            <span class="px-3 py-1 bg-gray-700 rounded-full text-sm" x-text="getPlayerType(currentPlayer)"></span>
                            <span x-show="getBattingStyle(currentPlayer)" class="px-3 py-1 bg-gray-700 rounded-full text-sm" x-text="getBattingStyle(currentPlayer)"></span>
                            <span x-show="getBowlingStyle(currentPlayer)" class="px-3 py-1 bg-gray-700 rounded-full text-sm" x-text="getBowlingStyle(currentPlayer)"></span>
                        </div>

                        {{-- Current Bid Display --}}
                        <div class="bg-gradient-to-r from-green-600/20 to-emerald-600/20 rounded-2xl p-6 mb-6">
                            <p class="text-gray-400 text-sm mb-1">CURRENT BID</p>
                            <p class="text-5xl font-black text-green-400 bid-flash" x-text="formatCurrency(currentBid)"></p>
                            <p class="text-lg text-gray-300 mt-2">
                                <span x-show="winningTeamName !== 'No Bids'">
                                    Highest: <span class="font-bold text-white" x-text="winningTeamName"></span>
                                </span>
                                <span x-show="winningTeamName === 'No Bids'" class="text-gray-500">Waiting for bids...</span>
                            </p>
                        </div>

                        {{-- Timer Bar --}}
                        <div class="w-full bg-gray-700 rounded-full h-3 mb-6 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 ease-linear"
                                 :class="biddingTimerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500'"
                                 :style="`width: ${timerWidth}%`"></div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex gap-4 justify-center">
                            <button @click="sellPlayer()"
                                    class="px-8 py-4 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                                <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                SELL
                            </button>
                            <button @click="passPlayer()"
                                    class="px-8 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-orange-500/30">
                                <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                PASS
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SOLD Overlay --}}
            <div x-show="displayState === 'sold'" x-transition class="absolute inset-0 flex items-center justify-center z-50">
                <div class="text-center sold-overlay">
                    {{-- Player Image --}}
                    <div class="relative mb-8">
                        <img :src="lastSoldPlayer?.player?.image_path ? `/storage/${lastSoldPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(lastSoldPlayer?.player?.name || 'P')}&size=200&background=random`"
                             class="w-48 h-48 rounded-full object-cover object-top mx-auto border-8 border-green-500 shadow-2xl shadow-green-500/50">
                        <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 bg-green-500 text-white px-6 py-2 rounded-full font-bold text-xl">
                            SOLD!
                        </div>
                    </div>

                    <h2 class="text-5xl font-black text-white mb-4" x-text="lastSoldPlayer?.player?.name"></h2>
                    <p class="text-3xl font-bold text-green-400 mb-4" x-text="formatCurrency(lastSoldPlayer?.final_price)"></p>

                    <div class="flex items-center justify-center gap-4 mb-8">
                        <span class="text-2xl text-gray-400">Sold to</span>
                        <div class="flex items-center gap-3 bg-gray-800 px-6 py-3 rounded-xl">
                            <template x-if="lastSoldPlayer?.winning_team?.logo_path">
                                <img :src="`/storage/${lastSoldPlayer.winning_team.logo_path}`" class="w-12 h-12 rounded-full object-cover">
                            </template>
                            <template x-if="!lastSoldPlayer?.winning_team?.logo_path">
                                <div class="w-12 h-12 rounded-full bg-green-600 flex items-center justify-center text-white font-bold text-xl"
                                     x-text="(lastSoldPlayer?.winning_team?.name || 'T').charAt(0)"></div>
                            </template>
                            <span class="text-2xl font-bold text-white" x-text="lastSoldPlayer?.winning_team?.name"></span>
                        </div>
                    </div>

                    <p class="text-gray-400 text-lg">Click "Next Player" to continue</p>
                </div>
            </div>

            {{-- UNSOLD Overlay --}}
            <div x-show="displayState === 'unsold'" x-transition class="absolute inset-0 flex items-center justify-center z-50">
                <div class="text-center unsold-overlay">
                    <div class="relative mb-8">
                        <img :src="lastSoldPlayer?.player?.image_path ? `/storage/${lastSoldPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(lastSoldPlayer?.player?.name || 'P')}&size=200&background=random`"
                             class="w-48 h-48 rounded-full object-cover object-top mx-auto border-8 border-red-500 shadow-2xl shadow-red-500/50 grayscale">
                        <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 bg-red-500 text-white px-6 py-2 rounded-full font-bold text-xl">
                            UNSOLD
                        </div>
                    </div>

                    <h2 class="text-5xl font-black text-white mb-4" x-text="lastSoldPlayer?.player?.name"></h2>
                    <p class="text-2xl text-red-400 mb-8">No bids received</p>
                    <p class="text-gray-400 text-lg">Click "Next Player" to continue</p>
                </div>
            </div>
        </div>

        {{-- Right Sidebar: Teams & Bids --}}
        <div class="w-80 bg-gray-800 border-l border-gray-700 flex flex-col">
            {{-- Teams Section --}}
            <div class="flex-1 overflow-hidden flex flex-col">
                <div class="p-4 border-b border-gray-700">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Team Budgets
                    </h2>
                </div>

                <div class="flex-1 overflow-y-auto p-3 space-y-3">
                    <template x-for="team in teams" :key="team.id">
                        <div class="bg-gray-700/50 rounded-xl p-4"
                             :class="winningTeamName === team.name ? 'ring-2 ring-green-500 bg-green-900/20' : ''">
                            <div class="flex items-center gap-3 mb-3">
                                <template x-if="team.logo_path">
                                    <img :src="`/storage/${team.logo_path}`" class="w-10 h-10 rounded-full object-cover">
                                </template>
                                <template x-if="!team.logo_path">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold"
                                         x-text="team.short_name"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-white truncate" x-text="team.name"></p>
                                    <p class="text-xs text-gray-400"><span x-text="team.players_bought"></span> players</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Remaining</span>
                                    <span class="font-bold" :class="team.remaining_budget < maxBudget * 0.2 ? 'text-red-400' : 'text-green-400'"
                                          x-text="formatCurrency(team.remaining_budget)"></span>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                         :class="team.remaining_budget < maxBudget * 0.2 ? 'bg-red-500' : 'bg-green-500'"
                                         :style="`width: ${(team.remaining_budget / maxBudget) * 100}%`"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Spent: <span x-text="formatCurrency(team.total_spent)"></span></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Live Bid History --}}
            <div class="h-64 border-t border-gray-700 flex flex-col">
                <div class="p-4 border-b border-gray-700">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Live Bids
                    </h2>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <template x-for="bid in bidLog.slice(0, 10)" :key="bid.id">
                        <div class="bg-gray-700/50 rounded-lg p-3 bid-flash">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-white text-sm" x-text="bid.team.name"></span>
                                <span class="font-bold text-green-400" x-text="formatCurrency(bid.amount)"></span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs text-gray-500" x-text="bid.user.name"></span>
                                <span class="text-xs text-gray-500" x-text="new Date(bid.created_at).toLocaleTimeString()"></span>
                            </div>
                        </div>
                    </template>
                    <div x-show="bidLog.length === 0" class="text-center py-6 text-gray-500">
                        <p class="text-sm">No bids yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function auctionOrganizerPanel() {
    return {
        // Constants
        BID_TIMER_DURATION: 30,
        BID_TIMER_RESET_TO: 15,

        // State
        auctionId: null,
        auctionStatus: 'scheduled',
        availablePlayers: [],
        teams: [],
        maxBudget: 0,

        // Display states: 'waiting', 'tumbling', 'bidding', 'sold', 'unsold'
        displayState: 'waiting',
        statusText: 'Click "Next Player" to begin',
        tumblerText: '',

        currentPlayer: null,
        lastSoldPlayer: null,
        currentBid: 0,
        winningTeamName: 'No Bids',
        bidLog: [],

        isTumbling: false,
        selectedPlayerId: null,

        // Timer
        biddingTimerInterval: null,
        biddingTimerSeconds: 0,
        timerWidth: 100,

        init(auctionId, status, players, teams, maxBudget, currentPlayer) {
            this.auctionId = auctionId;
            this.auctionStatus = status;
            this.availablePlayers = players;
            this.teams = teams;
            this.maxBudget = maxBudget;

            // If there's already a player on bid, restore state
            if (currentPlayer) {
                this.currentPlayer = currentPlayer;
                this.currentBid = currentPlayer.current_price || currentPlayer.base_price;
                this.displayState = 'bidding';
                this.bidLog = currentPlayer.bids || [];
                if (this.bidLog.length > 0) {
                    this.winningTeamName = this.bidLog[0].team.name;
                }
                this.startBiddingTimer();
            }

            // Setup Echo listeners
            // Private channel for player-on-bid and new-bid events
            window.Echo.private(`auction.private.${this.auctionId}`)
                .listen('.player.onbid', (e) => this.handlePlayerOnBid(e))
                .listen('.bid.new', (e) => this.handleNewBid(e));

            // Public channel for player-sold event
            window.Echo.channel(`auction.${this.auctionId}`)
                .listen('.player-on-sold', (e) => this.handlePlayerSold(e));
        },

        // Event Handlers
        handlePlayerOnBid(event) {
            this.currentPlayer = event.auctionPlayer;
            this.currentBid = event.auctionPlayer.base_price;
            this.winningTeamName = 'No Bids';
            this.bidLog = (event.auctionPlayer.bids || []).sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            this.displayState = 'bidding';
            this.statusText = `${event.auctionPlayer.player.name} is now live!`;
            this.startBiddingTimer();
        },

        handleNewBid(event) {
            if (!this.currentPlayer || event.bid.auction_player_id !== this.currentPlayer.id) return;
            this.currentBid = event.bid.amount;
            this.winningTeamName = event.bid.team.name;
            this.bidLog.unshift(event.bid);

            // Update team budget in UI
            const team = this.teams.find(t => t.id === event.bid.team.id);
            if (team) {
                // This is a simplified update - the actual remaining will be calculated on refresh
            }

            this.resetBiddingTimer();
        },

        handlePlayerSold(event) {
            this.stopBiddingTimer();

            // The event data structure from broadcastWith()
            const auctionPlayer = event.auctionPlayer;
            const winningTeam = event.winningTeam;

            this.lastSoldPlayer = {
                player: auctionPlayer.player,
                final_price: auctionPlayer.final_price || this.currentBid,
                winning_team: winningTeam
            };

            if (winningTeam) {
                this.displayState = 'sold';
                // Update team in UI
                const team = this.teams.find(t => t.id === winningTeam.id);
                if (team) {
                    team.players_bought++;
                    team.total_spent += this.lastSoldPlayer.final_price;
                    team.remaining_budget = this.maxBudget - team.total_spent;
                }
            } else {
                this.displayState = 'unsold';
            }

            // Remove from queue
            this.availablePlayers = this.availablePlayers.filter(p => p.id !== auctionPlayer.id);
            this.currentPlayer = null;
            this.bidLog = [];
        },

        // Timer Management
        startBiddingTimer() {
            this.stopBiddingTimer();
            this.biddingTimerSeconds = this.BID_TIMER_DURATION;
            this.timerWidth = 100;

            this.biddingTimerInterval = setInterval(() => {
                this.biddingTimerSeconds--;
                this.timerWidth = (this.biddingTimerSeconds / this.BID_TIMER_DURATION) * 100;

                if (this.biddingTimerSeconds <= 0) {
                    this.stopBiddingTimer();
                    this.sellPlayer();
                }
            }, 1000);
        },

        resetBiddingTimer() {
            this.biddingTimerSeconds = this.BID_TIMER_RESET_TO;
        },

        stopBiddingTimer() {
            clearInterval(this.biddingTimerInterval);
            this.biddingTimerInterval = null;
        },

        // Tumbler Logic
        startTumbler() {
            if (this.isTumbling || this.availablePlayers.length === 0) return;
            if (this.displayState === 'bidding') return;

            this.isTumbling = true;
            this.displayState = 'tumbling';
            this.statusText = 'Selecting player...';

            let shuffleCount = 0;
            const maxShuffles = 25;

            const shuffleInterval = setInterval(() => {
                const randomIndex = Math.floor(Math.random() * this.availablePlayers.length);
                this.tumblerText = this.availablePlayers[randomIndex].name;
                shuffleCount++;

                if (shuffleCount >= maxShuffles) {
                    clearInterval(shuffleInterval);
                    this.selectedPlayerId = this.availablePlayers[randomIndex].id;
                    this.statusText = `Selected: ${this.availablePlayers[randomIndex].name}`;
                    this.isTumbling = false;

                    setTimeout(() => this.putPlayerOnBid(), 1000);
                }
            }, 80);
        },

        selectAndPutOnBid(player) {
            if (this.displayState === 'bidding') {
                alert('Please finish the current player first!');
                return;
            }
            this.selectedPlayerId = player.id;
            this.tumblerText = player.name;
            this.displayState = 'tumbling';
            this.statusText = `Selected: ${player.name}`;

            setTimeout(() => this.putPlayerOnBid(), 500);
        },

        // API Calls
        async sendCommand(endpoint, body = {}) {
            try {
                const response = await fetch(`/admin/organizer/auction/${this.auctionId}/api/${endpoint}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Server error');
                }

                return data;
            } catch (error) {
                alert(`Error: ${error.message}`);
                return null;
            }
        },

        async startAuction() {
            const result = await this.sendCommand('start');
            if (result) {
                this.auctionStatus = 'running';
                this.statusText = 'Auction started! Select first player.';
                this.displayState = 'waiting';
            }
        },

        async endAuction() {
            if (!confirm('Are you sure you want to end the auction?')) return;
            const result = await this.sendCommand('end');
            if (result) {
                this.auctionStatus = 'completed';
                this.stopBiddingTimer();
            }
        },

        async togglePause() {
            const result = await this.sendCommand('toggle-pause');
            if (result) {
                this.auctionStatus = this.auctionStatus === 'running' ? 'paused' : 'running';
            }
        },

        async putPlayerOnBid() {
            if (!this.selectedPlayerId) return;
            await this.sendCommand('player-on-bid', { auction_player_id: this.selectedPlayerId });
        },

        async sellPlayer() {
            if (!this.currentPlayer) return;
            await this.sendCommand('sell-player', { auction_player_id: this.currentPlayer.id });
        },

        async passPlayer() {
            if (!this.currentPlayer) return;
            await this.sendCommand('pass-player', { auction_player_id: this.currentPlayer.id });
        },

        // Helpers
        formatCurrency(amount) {
            const num = Number(amount) || 0;
            if (num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
            if (num >= 100000) return (num / 100000).toFixed(2) + ' L';
            if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
            return num.toLocaleString();
        },

        // Handle nested objects from broadcast vs initial load
        getPlayerType(player) {
            if (!player?.player) return 'Player';
            const pt = player.player.player_type || player.player.playerType;
            return typeof pt === 'object' ? pt?.name : pt || 'Player';
        },

        getBattingStyle(player) {
            if (!player?.player) return null;
            const bs = player.player.batting_style || player.player.battingProfile;
            return typeof bs === 'object' ? bs?.name : bs;
        },

        getBowlingStyle(player) {
            if (!player?.player) return null;
            const bs = player.player.bowling_style || player.player.bowlingProfile;
            return typeof bs === 'object' ? bs?.name : bs;
        }
    }
}
</script>
@endsection
