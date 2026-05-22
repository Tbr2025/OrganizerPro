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
    .team-card-selected {
        animation: teamCardPulse 0.3s ease-out;
    }
    @keyframes teamCardPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .offline-winner-glow {
        animation: winnerGlow 2s ease-in-out infinite alternate;
    }
    @keyframes winnerGlow {
        from { box-shadow: 0 0 20px rgba(34, 197, 94, 0.3); }
        to { box-shadow: 0 0 40px rgba(34, 197, 94, 0.6), 0 0 60px rgba(34, 197, 94, 0.3); }
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

    {{-- Sell Player Modal --}}
    <div x-show="showSellModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:999999;background:rgba(0,0,0,0.85);" x-cloak>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#1f2937;border-radius:1rem;padding:2rem;max-width:28rem;width:calc(100% - 2rem);border:1px solid #374151;box-shadow:0 25px 50px rgba(0,0,0,0.5);">
            <h3 class="text-xl font-bold text-white mb-4 text-center">Sell Player</h3>
            <div class="text-center mb-6">
                <p class="text-gray-300 mb-4">Selling <span class="font-bold text-white" x-text="currentPlayer?.player?.name"></span></p>
            </div>

            {{-- Team Selection --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Select Team</label>
                <select x-model="sellModalData.team_id"
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:border-blue-500">
                    <option value="">-- Choose a team --</option>
                    <template x-for="team in teams" :key="team.id">
                        <option :value="team.id" x-text="team.name + ' (Balance: ' + formatCurrency(team.remaining_budget) + ')'"></option>
                    </template>
                </select>
            </div>

            {{-- Amount Input (in Lakhs) --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Sale Amount (in Lakhs)</label>
                <div class="flex items-center bg-gray-900 border border-gray-600 rounded-xl focus-within:border-blue-500">
                    <input type="number"
                           :value="sellModalData.amount ? (sellModalData.amount / 100000) : ''"
                           @input="sellModalData.amount = Number($event.target.value) * 100000"
                           class="w-full px-4 py-3 bg-transparent text-white focus:outline-none text-right"
                           placeholder="0" step="0.5" min="0">
                    <span class="pr-4 text-gray-400 font-medium">L</span>
                </div>
            </div>

            {{-- Summary --}}
            <div x-show="sellModalData.team_id && sellModalData.amount" class="bg-gray-900/50 rounded-xl p-4 mb-6 text-center">
                <p class="text-gray-300">Sell to <span class="font-bold text-green-400" x-text="teams.find(t => t.id == sellModalData.team_id)?.name"></span></p>
                <p class="text-gray-300">for <span class="font-bold text-yellow-400 text-2xl" x-text="formatCurrency(sellModalData.amount)"></span></p>
            </div>

            <div class="flex gap-4">
                <button @click="showSellModal = false"
                        class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-medium transition">
                    Cancel
                </button>
                <button @click="executeSellToTeam()"
                        :disabled="!sellModalData.team_id || !sellModalData.amount"
                        class="flex-1 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:from-gray-600 disabled:to-gray-600 disabled:cursor-not-allowed text-white rounded-xl font-bold transition">
                    Confirm Sale
                </button>
            </div>
        </div>
    </div>

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
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-blue-500/20 text-blue-400 border border-blue-500/30">
                    Live Auction
                </span>

                {{-- Current Phase Badge --}}
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase transition-all"
                      :class="{
                          'bg-blue-500/20 text-blue-400 border border-blue-500/30': bidType === 'open' && openBidMode === 'online',
                          'bg-purple-500/20 text-purple-400 border border-purple-500/30': bidType === 'closed' && openBidMode !== 'offline',
                          'bg-orange-500/20 text-orange-400 border border-orange-500/30': openBidMode === 'offline'
                      }"
                      x-text="openBidMode === 'offline' ? 'OFFLINE' : (bidType === 'closed' ? 'CLOSED BID' : 'OPEN BID')">
                </span>

                {{-- Online/Offline Mode Badge --}}
                <template x-if="hasOnlineOfflineMode">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase transition-all"
                          :class="openBidMode === 'online'
                              ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                              : 'bg-orange-500/20 text-orange-400 border border-orange-500/30'"
                          x-text="openBidMode === 'online' ? 'ONLINE MODE' : 'OFFLINE MODE'">
                    </span>
                </template>
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
                    {{-- Switch Bid Phase Button --}}
                    <template x-if="displayState === 'bidding'">
                        <div class="flex gap-1">
                            <button @click="switchBidPhase('open')"
                                    :disabled="bidType === 'open' && openBidMode === 'online'"
                                    class="px-3 py-2 rounded-lg text-xs font-medium transition"
                                    :class="bidType === 'open' && openBidMode !== 'offline' ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'">
                                Open
                            </button>
                            <button @click="switchBidPhase('closed')"
                                    :disabled="bidType === 'closed' && openBidMode !== 'offline'"
                                    class="px-3 py-2 rounded-lg text-xs font-medium transition"
                                    :class="bidType === 'closed' && openBidMode !== 'offline' ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'">
                                Closed
                            </button>
                            <button @click="switchBidPhase('offline')"
                                    :disabled="openBidMode === 'offline'"
                                    class="px-3 py-2 rounded-lg text-xs font-medium transition"
                                    :class="openBidMode === 'offline' ? 'bg-orange-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'">
                                Offline
                            </button>
                        </div>
                    </template>

                    {{-- Switch Mode Button (legacy) --}}
                    <template x-if="hasOnlineOfflineMode && displayState === 'bidding'">
                        <button @click="toggleBidMode()"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition"
                                :class="openBidMode === 'online'
                                    ? 'bg-orange-600 hover:bg-orange-700 text-white'
                                    : 'bg-green-600 hover:bg-green-700 text-white'"
                                x-text="openBidMode === 'online' ? 'Switch to Offline' : 'Switch to Online'">
                        </button>
                    </template>

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
                    <button @click="restartAuction()" x-show="auctionStatus === 'completed' || auctionStatus === 'running' || auctionStatus === 'paused'"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition">
                        Restart Auction
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex h-[calc(100vh-80px)]">

        {{-- Left Sidebar: Player Queue --}}
        <div class="w-72 bg-gray-800 border-r border-gray-700 flex flex-col">
            {{-- Tab Toggle --}}
            <div class="p-4 border-b border-gray-700">
                <div class="flex rounded-lg bg-gray-900 p-1 mb-3">
                    <button @click="playerListTab = 'queue'"
                            :class="playerListTab === 'queue' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                            class="flex-1 py-1.5 text-sm font-medium rounded-md transition">
                        Queue
                    </button>
                    <button @click="playerListTab = 'all'; fetchAllPlayers()"
                            :class="playerListTab === 'all' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                            class="flex-1 py-1.5 text-sm font-medium rounded-md transition">
                        All Players
                    </button>
                </div>
                <template x-if="playerListTab === 'queue'">
                    <div>
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Player Queue
                        </h2>
                        <p class="text-sm text-gray-400 mt-1"><span x-text="availablePlayers.length"></span> players waiting</p>
                    </div>
                </template>
                <template x-if="playerListTab === 'all'">
                    <div>
                        <input type="text" x-model="playerSearchQuery" placeholder="Search player name..."
                               class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500">
                    </div>
                </template>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                {{-- Queue Tab --}}
                <template x-if="playerListTab === 'queue'">
                    <div class="space-y-2">
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
                </template>

                {{-- All Players Tab --}}
                <template x-if="playerListTab === 'all'">
                    <div class="space-y-2">
                        <template x-for="player in filteredAllPlayers" :key="player.id">
                            <div class="bg-gray-700/50 rounded-lg p-3">
                                <div class="flex items-center gap-3">
                                    <img :src="player.image_path ? `/storage/${player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&size=40&background=random`"
                                         class="w-10 h-10 rounded-full object-cover">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate" x-text="player.name"></p>
                                        <div class="flex items-center gap-1 mt-0.5">
                                            <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                                                  :class="{
                                                      'bg-green-500/20 text-green-400': player.status === 'sold',
                                                      'bg-red-500/20 text-red-400': player.status === 'unsold',
                                                      'bg-blue-500/20 text-blue-400': player.status === 'on_auction',
                                                      'bg-gray-500/20 text-gray-400': player.status === 'waiting'
                                                  }"
                                                  x-text="player.status.toUpperCase()"></span>
                                            <span x-show="player.sold_to_team" class="text-xs text-gray-400 truncate" x-text="player.sold_to_team"></span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <template x-if="player.status === 'sold' || player.status === 'unsold'">
                                            <button @click="reAuctionPlayer(player)"
                                                    class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs font-medium transition">
                                                Re-auction
                                            </button>
                                        </template>
                                        <template x-if="player.status === 'sold'">
                                            <p class="text-xs text-green-400 font-medium mt-1" x-text="formatCurrency(player.final_price)"></p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredAllPlayers.length === 0" class="text-center py-8 text-gray-500">
                            <p class="text-sm">No players found.</p>
                        </div>
                    </div>
                </template>
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
        <div class="flex-1 flex flex-col items-center justify-center relative overflow-hidden"
             :class="openBidMode === 'offline' && displayState === 'bidding' ? 'p-3 justify-start overflow-y-auto' : 'p-8'">

            {{-- Background Pattern --}}
            <div class="absolute inset-0 opacity-5 pointer-events-none">
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

            {{-- Bidding State: Main Player Card (Online/Normal mode) --}}
            <div x-show="displayState === 'bidding' && openBidMode !== 'offline'" x-transition class="w-full max-w-2xl relative z-10">
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

                        {{-- Timer (hidden during offline mode) --}}
                        <div class="absolute top-4 right-4 bg-black/50 backdrop-blur px-4 py-2 rounded-full"
                             x-show="openBidMode === 'online' || !hasOnlineOfflineMode">
                            <span class="text-2xl font-bold" :class="biddingTimerSeconds <= 5 ? 'text-red-500 timer-critical' : 'text-white'"
                                  x-text="biddingTimerSeconds + 's'"></span>
                        </div>
                        {{-- Offline Mode Badge on player card --}}
                        <div class="absolute top-4 right-4 bg-orange-500/80 backdrop-blur px-4 py-2 rounded-full"
                             x-show="openBidMode === 'offline' && hasOnlineOfflineMode">
                            <span class="text-lg font-bold text-white">OFFLINE</span>
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

                        {{-- Base Price & Bid Count Display --}}
                        <div class="bg-gradient-to-r from-green-600/20 to-emerald-600/20 rounded-2xl p-6 mb-6">
                            <p class="text-gray-400 text-sm mb-1">BASE PRICE</p>
                            <p class="text-5xl font-black text-green-400 bid-flash" x-text="formatCurrency(currentPlayer?.base_price)"></p>
                            <p class="text-lg text-gray-300 mt-2">
                                <span class="text-green-300" x-text="sealedBids.length + ' bid(s) received'"></span>
                            </p>
                        </div>

                        {{-- Current Price Display (prominent in offline mode) --}}
                        <div x-show="openBidMode === 'offline' && hasOnlineOfflineMode && currentPlayer?.current_price"
                             class="bg-gradient-to-r from-orange-600/20 to-amber-600/20 rounded-2xl p-6 mb-6 border border-orange-500/30">
                            <p class="text-orange-300 text-sm mb-1">CURRENT PRICE</p>
                            <p class="text-5xl font-black text-orange-400" x-text="formatCurrency(currentPlayer?.current_price)"></p>
                        </div>

                        {{-- Timer Bar --}}
                        <div class="w-full bg-gray-700 rounded-full h-3 mb-6 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 ease-linear"
                                 :class="biddingTimerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500'"
                                 :style="`width: ${timerWidth}%`"></div>
                        </div>

                        {{-- Action Buttons (same for both open & closed since all bids are sealed) --}}
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-3 justify-center">
                                <button @click="sellPlayer()"
                                        class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    SELL
                                </button>
                                <button @click="passPlayer()"
                                        class="px-6 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-orange-500/30">
                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    PASS
                                </button>
                                <button @click="rebidCurrentPlayer()"
                                        class="relative z-10 px-6 py-3 bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-600 hover:to-amber-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-yellow-500/30 cursor-pointer">
                                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    RE-BID
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Offline Bidding State: Dedicated Panel --}}
            <div x-show="displayState === 'bidding' && openBidMode === 'offline'" x-transition class="w-full relative z-10">

                {{-- A. Compact Player Info Bar --}}
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-3">
                        <img :src="currentPlayer?.player?.image_path ? `/storage/${currentPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(currentPlayer?.player?.name || 'P')}&size=80&background=random`"
                             class="w-12 h-12 rounded-lg object-cover object-top border-2 border-blue-500/50 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold text-white truncate" x-text="currentPlayer?.player?.name"></h3>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                <span class="px-1.5 py-0.5 bg-gray-700 rounded text-xs" x-text="getPlayerType(currentPlayer)"></span>
                                <span>Base: <span class="text-white font-semibold" x-text="formatCurrency(currentPlayer?.base_price)"></span></span>
                                <span x-show="currentPlayer?.current_price">Curr: <span class="text-orange-400 font-semibold" x-text="formatCurrency(currentPlayer?.current_price)"></span></span>
                            </div>
                        </div>
                        <div class="bg-orange-500/80 px-2 py-1 rounded-full flex-shrink-0">
                            <span class="text-xs font-bold text-white">OFFLINE</span>
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                            <button @click="sellPlayer()" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-xs transition-all">SELL</button>
                            <button @click="passPlayer()" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold text-xs transition-all">PASS</button>
                            <button @click="rebidCurrentPlayer()" class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-semibold text-xs transition-all">RE-BID</button>
                        </div>
                    </div>
                </div>

                {{-- B. Phase Stepper --}}
                <div class="flex items-center justify-center gap-1 mb-3">
                    <template x-for="(step, idx) in [{label:'Select', phase:'selection'}, {label:'Bids', phase:'bidding'}, {label:'Results', phase:'results'}]" :key="idx">
                        <div class="flex items-center gap-1">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                                 :class="offlinePhase === step.phase ? 'bg-blue-600 text-white' : (['selection','bidding','results'].indexOf(offlinePhase) > idx ? 'bg-green-600/30 text-green-400' : 'bg-gray-700 text-gray-400')">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                                      :class="offlinePhase === step.phase ? 'bg-white text-blue-600' : (['selection','bidding','results'].indexOf(offlinePhase) > idx ? 'bg-green-500 text-white' : 'bg-gray-600 text-gray-300')"
                                      x-text="['selection','bidding','results'].indexOf(offlinePhase) > idx ? '✓' : (idx + 1)"></span>
                                <span x-text="step.label"></span>
                            </div>
                            <svg x-show="idx < 2" class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </template>
                </div>

                {{-- C. Phase 1: Team Selection --}}
                <div x-show="offlinePhase === 'selection'" x-transition>
                    {{-- Participating teams bar --}}
                    <div x-show="offlineParticipants.length > 0" class="bg-gray-800/50 border border-gray-700 rounded-xl p-3 mb-4">
                        <p class="text-xs text-gray-400 mb-2">PARTICIPATING TEAMS (<span x-text="offlineParticipants.length"></span>)</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tid in offlineParticipants" :key="tid">
                                <div class="flex items-center gap-2 bg-orange-500/20 border border-orange-500/40 rounded-full px-3 py-1.5 cursor-pointer hover:bg-orange-500/30 transition-all"
                                     @click="toggleOfflineParticipant(tid)">
                                    <template x-if="getTeamById(tid)?.logo_path">
                                        <img :src="`/storage/${getTeamById(tid).logo_path}`" class="w-6 h-6 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_path">
                                        <div class="w-6 h-6 rounded-full bg-orange-600 flex items-center justify-center text-white text-xs font-bold" x-text="getTeamById(tid)?.short_name || '?'"></div>
                                    </template>
                                    <span class="text-sm text-white font-medium" x-text="getTeamById(tid)?.name"></span>
                                    <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Team Logo Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mb-3">
                        <template x-for="team in teams" :key="team.id">
                            <div @click="toggleOfflineParticipant(team.id)"
                                 class="relative bg-gray-800 border-2 rounded-lg p-3 cursor-pointer transition-all hover:scale-[1.03] flex items-center gap-2"
                                 :class="isOfflineParticipant(team.id) ? 'border-orange-500 bg-orange-500/10 team-card-selected' : 'border-gray-700 hover:border-gray-500'">
                                {{-- Checkmark --}}
                                <div x-show="isOfflineParticipant(team.id)" class="absolute top-1 right-1">
                                    <div class="w-5 h-5 rounded-full bg-orange-500 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </div>
                                {{-- Logo --}}
                                <template x-if="team.logo_path">
                                    <img :src="`/storage/${team.logo_path}`" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                </template>
                                <template x-if="!team.logo_path">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0" x-text="team.short_name"></div>
                                </template>
                                {{-- Name & Budget --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate" x-text="team.name"></p>
                                    <p class="text-xs" :class="team.remaining_budget < maxBudget * 0.2 ? 'text-red-400' : 'text-gray-400'" x-text="formatCurrency(team.remaining_budget) + ' left'"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Start Bidding Button --}}
                    <div class="text-center">
                        <button @click="startOfflineBidding()"
                                :disabled="offlineParticipants.length === 0"
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white rounded-xl font-bold text-base transition-all transform hover:scale-105 disabled:hover:scale-100 shadow-lg shadow-blue-500/30 disabled:shadow-none">
                            <span x-text="offlineParticipants.length > 0 ? 'Start Bidding (' + offlineParticipants.length + ' teams)' : 'Select teams to start'"></span>
                        </button>
                    </div>
                </div>

                {{-- D. Phase 2: Bid Entry --}}
                <div x-show="offlinePhase === 'bidding'" x-transition>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden mb-4">
                        <div class="p-3 bg-gray-700/50 border-b border-gray-700">
                            <p class="text-sm font-semibold text-white">Enter bid amounts for each team</p>
                        </div>
                        <div class="divide-y divide-gray-700">
                            <template x-for="tid in offlineParticipants" :key="tid">
                                <div class="flex items-center gap-4 p-4">
                                    {{-- Team logo --}}
                                    <template x-if="getTeamById(tid)?.logo_path">
                                        <img :src="`/storage/${getTeamById(tid).logo_path}`" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_path">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm" x-text="getTeamById(tid)?.short_name || '?'"></div>
                                    </template>
                                    {{-- Team info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate" x-text="getTeamById(tid)?.name"></p>
                                        <p class="text-xs text-gray-400">Budget: <span x-text="formatCurrency(getTeamById(tid)?.remaining_budget)"></span></p>
                                    </div>
                                    {{-- Bid input (in Lakhs) --}}
                                    <div class="w-44">
                                        <div class="flex items-center bg-gray-700 border border-gray-600 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
                                            <input type="number"
                                                   :value="offlineTeamBids[tid] / 100000"
                                                   @input="offlineTeamBids[tid] = Number($event.target.value) * 100000"
                                                   class="w-full bg-transparent px-3 py-2 text-white text-sm text-right outline-none"
                                                   placeholder="0" min="0" step="0.5">
                                            <span class="pr-3 text-xs text-gray-400 whitespace-nowrap">L</span>
                                        </div>
                                    </div>
                                    {{-- Remove button --}}
                                    <button @click="toggleOfflineParticipant(tid)" class="p-2 text-gray-400 hover:text-red-400 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button @click="offlineGoBack()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">
                            &larr; Back to Selection
                        </button>
                        <button @click="endOfflineBidding()"
                                class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                            End Bidding &amp; Show Winner
                        </button>
                    </div>
                </div>

                {{-- E. Phase 3: Results --}}
                <div x-show="offlinePhase === 'results'" x-transition>
                    {{-- Winner Card --}}
                    <div x-show="offlineHighestBidder" class="bg-gray-800 border-2 border-green-500 rounded-xl p-6 mb-4 text-center offline-winner-glow">
                        <p class="text-green-400 text-sm font-semibold mb-3 uppercase tracking-wider">Winner</p>
                        <template x-if="getTeamById(offlineHighestBidder)?.logo_path">
                            <img :src="`/storage/${getTeamById(offlineHighestBidder).logo_path}`" class="w-20 h-20 mx-auto rounded-full object-cover mb-3 border-4 border-green-500/50">
                        </template>
                        <template x-if="!getTeamById(offlineHighestBidder)?.logo_path">
                            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-bold text-2xl mb-3" x-text="getTeamById(offlineHighestBidder)?.short_name || '?'"></div>
                        </template>
                        <h3 class="text-2xl font-bold text-white mb-1" x-text="getTeamById(offlineHighestBidder)?.name"></h3>
                        <p class="text-3xl font-black text-green-400 mb-4" x-text="formatCurrency(offlineHighestAmount)"></p>
                        <button @click="confirmOfflineSale(offlineHighestBidder, offlineHighestAmount)"
                                class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                            Confirm Sale to Winner
                        </button>
                    </div>

                    {{-- All Bids Ranked --}}
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden mb-4">
                        <div class="p-3 bg-gray-700/50 border-b border-gray-700">
                            <p class="text-sm font-semibold text-white">All Bids (Ranked)</p>
                        </div>
                        <div class="divide-y divide-gray-700">
                            <template x-for="(entry, idx) in Object.entries(offlineTeamBids).sort((a,b) => b[1] - a[1])" :key="entry[0]">
                                <div class="flex items-center gap-4 p-4"
                                     :class="Number(entry[0]) === offlineHighestBidder ? 'bg-green-500/10' : ''">
                                    {{-- Rank --}}
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                                         :class="idx === 0 ? 'bg-green-500 text-white' : 'bg-gray-700 text-gray-400'"
                                         x-text="'#' + (idx + 1)"></div>
                                    {{-- Team logo --}}
                                    <template x-if="getTeamById(Number(entry[0]))?.logo_path">
                                        <img :src="`/storage/${getTeamById(Number(entry[0])).logo_path}`" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(Number(entry[0]))?.logo_path">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm" x-text="getTeamById(Number(entry[0]))?.short_name || '?'"></div>
                                    </template>
                                    {{-- Team info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate" x-text="getTeamById(Number(entry[0]))?.name"></p>
                                    </div>
                                    {{-- Amount --}}
                                    <p class="font-bold text-lg" :class="Number(entry[0]) === offlineHighestBidder ? 'text-green-400' : 'text-gray-300'" x-text="formatCurrency(entry[1])"></p>
                                    {{-- Override sell button (non-winners) --}}
                                    <button x-show="Number(entry[0]) !== offlineHighestBidder"
                                            @click="confirmOfflineSale(Number(entry[0]), entry[1])"
                                            class="px-3 py-1.5 bg-gray-700 hover:bg-green-600 text-gray-300 hover:text-white rounded-lg text-xs font-semibold transition-all">
                                        Sell
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button @click="offlineGoBack()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">
                            &larr; Back to Bids
                        </button>
                        <button @click="resetOfflinePanel()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">
                            Reset
                        </button>
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

            {{-- Bids Panel (visible to admin only) --}}
            <div class="flex-1 border-t border-gray-700 flex flex-col">
                <div class="p-4 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Bids
                        </h2>
                        <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-400" x-text="sealedBids.length + ' bids'"></span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <template x-for="bid in sealedBids" :key="bid.id">
                        <div class="bg-gray-700/50 rounded-xl p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <template x-if="bid.team_logo">
                                    <img :src="`/storage/${bid.team_logo}`" class="w-10 h-10 rounded-full object-cover">
                                </template>
                                <template x-if="!bid.team_logo">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm"
                                         x-text="bid.team_name.charAt(0)"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-white text-sm truncate" x-text="bid.team_name"></p>
                                    <p class="text-xs text-gray-400" x-text="bid.user_name"></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-green-400 text-lg" x-text="formatCurrency(bid.amount)"></p>
                                    <p class="text-xs text-gray-500" x-text="new Date(bid.created_at).toLocaleTimeString()"></p>
                                </div>
                            </div>
                            <button @click="confirmSellToTeam(bid)"
                                    class="w-full py-2 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-lg text-sm font-bold transition-all">
                                Sold To This Team
                            </button>
                        </div>
                    </template>
                    <div x-show="sealedBids.length === 0" class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm">Waiting for bids...</p>
                    </div>
                </div>
            </div>

        </div>
    </div>


</div>

<script>
function auctionOrganizerPanel() {
    return {
        // Constants from DB
        BID_TIMER_DURATION: {{ $auction->bid_timer_seconds ?? 30 }},
        BID_TIMER_RESET_TO: {{ $auction->bid_timer_reset_seconds ?? 15 }},

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

        // Closed bid state
        sealedBids: [],
        sealedBidPollInterval: null,
        biddingClosed: false,
        showSellModal: false,
        sellModalData: { team_id: '', amount: '' },

        // Online/Offline mode state
        bidType: '{{ $auction->bid_type ?? 'open' }}',
        openBidMode: '{{ $auction->open_bid_mode ?? 'online' }}',
        hasOnlineOfflineMode: {{ ($auction->online_bid_limit_from !== null && $auction->online_bid_limit_to !== null) ? 'true' : 'false' }},
        hasAutoPhaseTransition: {{ ($auction->closed_bid_starts_at !== null) ? 'true' : 'false' }},
        modeManuallyOverridden: {{ $auction->mode_manually_overridden ? 'true' : 'false' }},
        offlineSaleTeamId: '',
        offlineSaleAmount: '',

        // Offline bidding panel state
        offlinePhase: 'selection',
        offlineParticipants: [],
        offlineTeamBids: {},
        offlineHighestBidder: null,
        offlineHighestAmount: 0,

        isTumbling: false,
        selectedPlayerId: null,

        // All Players tab
        playerListTab: 'queue',
        playerSearchQuery: '',
        allPlayers: [],

        // Timer
        biddingTimerInterval: null,
        biddingTimerSeconds: 0,
        timerWidth: 100,
        _lastKnownBid: 0,

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
                this._lastKnownBid = this.currentBid;
                this.displayState = 'bidding';
                this.sealedBids = [];
                this.startBiddingTimer();
            }

            // Start polling for live updates (replaces Echo which requires Pusher)
            this.startStatePolling();
        },

        // ---- Timer logic ----
        startBiddingTimer(duration) {
            this.stopBiddingTimer();
            this.biddingTimerSeconds = duration || this.BID_TIMER_DURATION;
            this.timerWidth = 100;
            const maxSeconds = this.biddingTimerSeconds;
            this.biddingTimerInterval = setInterval(() => {
                this.biddingTimerSeconds--;
                this.timerWidth = Math.max(0, (this.biddingTimerSeconds / maxSeconds) * 100);
                if (this.biddingTimerSeconds <= 0) {
                    this.stopBiddingTimer();
                }
            }, 1000);
        },

        resetBiddingTimer() {
            const resetTo = this.BID_TIMER_RESET_TO || this.BID_TIMER_DURATION;
            this.startBiddingTimer(resetTo);
        },

        stopBiddingTimer() {
            if (this.biddingTimerInterval) {
                clearInterval(this.biddingTimerInterval);
                this.biddingTimerInterval = null;
            }
        },

        // ---- Polling-based live updates ----
        _lastCurrentPlayerId: null,
        _pollInterval: null,

        startStatePolling() {
            this._lastCurrentPlayerId = this.currentPlayer?.id || null;
            this._pollInterval = setInterval(() => this.pollAuctionState(), 2000);
        },

        async pollAuctionState() {
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/poll-state`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();

                // Update auction status
                this.auctionStatus = data.auction_status;

                // Update online/offline mode from server
                if (data.open_bid_mode !== undefined) {
                    this.openBidMode = data.open_bid_mode;
                }
                if (data.mode_manually_overridden !== undefined) {
                    this.modeManuallyOverridden = data.mode_manually_overridden;
                }
                if (data.bid_type !== undefined) {
                    this.bidType = data.bid_type;
                    this.hasOnlineOfflineMode = data.online_bid_limit_from !== null && data.online_bid_limit_to !== null;
                    this.hasAutoPhaseTransition = data.closed_bid_starts_at !== null;
                }

                // Update available players — normalize nested structure to flat
                this.availablePlayers = (data.available_players || []).map(ap => ({
                    id: ap.id,
                    name: ap.player?.name || 'Unknown',
                    base_price: ap.base_price,
                    image_path: ap.player?.image_path || null,
                    player_type: ap.player?.player_type?.name || ap.player?.player_type?.type || 'Player',
                    batting_style: ap.player?.batting_profile?.name || ap.player?.batting_profile?.style || null,
                    bowling_style: ap.player?.bowling_profile?.name || ap.player?.bowling_profile?.style || null,
                }));

                // Update teams
                this.teams = (data.teams || []).map(t => {
                    t.remaining_budget = t.remaining_budget ?? (this.maxBudget - (t.total_spent || 0));
                    return t;
                });

                // Update stats
                if (data.stats) {
                    this.stats = data.stats;
                }

                const newPlayer = data.current_player;
                const prevId = this._lastCurrentPlayerId;

                if (newPlayer) {
                    // A player is on auction
                    if (newPlayer.id !== prevId) {
                        // New player just came on bid
                        this.currentPlayer = newPlayer;
                        this.currentBid = newPlayer.current_price || newPlayer.base_price;
                        this._lastKnownBid = this.currentBid;
                        this.displayState = 'bidding';
                        this.biddingClosed = false;
                        this.sealedBids = [];
                        this.resetOfflinePanel();
                        this.statusText = `${newPlayer.player?.name} is now live!`;
                        this._lastCurrentPlayerId = newPlayer.id;
                        // Start timer for new player
                        this.startBiddingTimer();
                    } else {
                        // Same player — update bids/price
                        const newBid = newPlayer.current_price || this.currentBid;
                        if (newBid !== this._lastKnownBid) {
                            // New bid detected — reset timer
                            this._lastKnownBid = newBid;
                            this.resetBiddingTimer();
                        }
                        this.currentBid = newBid;
                        this.currentPlayer = newPlayer;
                    }
                    // Always fetch sealed bids when a player is live
                    this.fetchSealedBids();
                } else if (prevId && !newPlayer) {
                    // Player was on auction but now gone — sold or passed
                    this.stopBiddingTimer();
                    this.biddingTimerSeconds = 0;
                    this.timerWidth = 0;

                    // Check sold players to determine outcome
                    const soldPlayers = data.sold_players || [];
                    const justSold = soldPlayers.find(sp => sp.id === prevId);

                    if (justSold) {
                        this.lastSoldPlayer = {
                            player: justSold.player,
                            final_price: justSold.final_price || this.currentBid,
                            winning_team: justSold.sold_to_team
                        };
                        this.displayState = justSold.sold_to_team ? 'sold' : 'unsold';
                    } else {
                        this.displayState = 'unsold';
                    }

                    this.currentPlayer = null;
                    this.sealedBids = [];
                    this.biddingClosed = false;
                    this._lastCurrentPlayerId = null;
                    this._lastKnownBid = 0;
                }
            } catch (e) {
                console.error('[OrganizerPanel] Poll error:', e);
            }
        },

        async fetchSealedBids() {
            if (!this.currentPlayer) return;
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/sealed-bids?auction_player_id=${this.currentPlayer.id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.bids) this.sealedBids = data.bids;
            } catch (e) { console.error('Error fetching sealed bids:', e); }
        },

        async closeBidding() {
            if (!this.currentPlayer) return;
            const result = await this.sendCommand('close-bidding', { auction_player_id: this.currentPlayer.id });
            if (result && result.success) {
                this.biddingClosed = true;
            }
        },

        // Closed Bid: Confirm sell to specific team
        confirmSellToTeam(bid) {
            this.sellModalData = bid;
            this.showSellModal = true;
        },

        async executeSellToTeam() {
            if (!this.sellModalData || !this.currentPlayer) return;
            this.showSellModal = false;
            const result = await this.sendCommand('sell-to-team', {
                auction_player_id: this.currentPlayer.id,
                team_id: this.sellModalData.team_id,
                amount: this.sellModalData.amount
            });
            if (result && result.success) {
                await this.pollAuctionState();
            }
            this.sellModalData = { team_id: '', amount: '' };
        },

        // Manual Phase Switch (open / closed / offline)
        async switchBidPhase(phase) {
            let confirmMsg = '';
            if (phase === 'open') {
                confirmMsg = 'Switch to OPEN bid mode? Teams will see live bids and use raise-hand.';
            } else if (phase === 'closed') {
                confirmMsg = 'Switch to CLOSED bid mode? Teams will submit sealed bids privately.';
            } else if (phase === 'offline') {
                confirmMsg = 'Switch to OFFLINE mode? You will handle bids manually.';
            }
            if (!confirm(confirmMsg)) return;

            if (phase === 'offline') {
                // Switch to offline mode
                const result = await this.sendCommand('switch-mode', { mode: 'offline' });
                if (result && result.success) {
                    this.openBidMode = 'offline';
                    this.modeManuallyOverridden = true;
                }
            } else {
                // Switch bid type and ensure online mode
                this.resetOfflinePanel();
                const modeResult = await this.sendCommand('switch-mode', { mode: 'online' });
                if (modeResult && modeResult.success) {
                    this.openBidMode = 'online';
                    this.modeManuallyOverridden = true;
                }
                // Update bid_type via a separate call
                const typeResult = await this.sendCommand('switch-bid-type', { bid_type: phase });
                if (typeResult && typeResult.success) {
                    this.bidType = phase;
                }
            }
        },

        // Online/Offline Mode Toggle
        async toggleBidMode() {
            const newMode = this.openBidMode === 'online' ? 'offline' : 'online';
            const confirmMsg = newMode === 'offline'
                ? 'Switch to OFFLINE mode? Teams will no longer be able to bid through the platform.'
                : 'Switch back to ONLINE mode? Teams will be able to bid through the platform again.';

            if (!confirm(confirmMsg)) return;

            const result = await this.sendCommand('switch-mode', { mode: newMode });
            if (result && result.success) {
                this.openBidMode = result.open_bid_mode;
                this.modeManuallyOverridden = result.mode_manually_overridden;
            }
        },

        // Offline: Add bid for a team (hand raise — uses admin increment rules endpoint)
        async executeOfflineBid() {
            if (!this.offlineSaleTeamId || !this.currentPlayer) return;

            try {
                const response = await fetch('/admin/auctions/add-bid', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        auctionPlayerId: this.currentPlayer.id,
                        teamId: this.offlineSaleTeamId
                    })
                });

                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Server error');

                if (data.success) {
                    await this.pollAuctionState();
                }
            } catch (e) {
                console.error('Offline bid error:', e);
                alert('Error: ' + e.message);
            }
        },

        // Offline: Sell player to selected team
        async executeOfflineSale() {
            if (!this.offlineSaleTeamId || !this.currentPlayer) return;

            // Default to current price if no amount entered
            const saleAmount = this.offlineSaleAmount > 0
                ? this.offlineSaleAmount
                : (this.currentPlayer?.current_price || this.currentPlayer?.base_price);

            const team = this.teams.find(t => t.id == this.offlineSaleTeamId);
            const teamName = team ? team.name : 'selected team';

            if (!confirm(`Sell ${this.currentPlayer?.player?.name} to ${teamName} for ${this.formatCurrency(saleAmount)}?`)) return;

            const result = await this.sendCommand('sell-to-team', {
                auction_player_id: this.currentPlayer.id,
                team_id: this.offlineSaleTeamId,
                amount: saleAmount
            });

            if (result && result.success) {
                this.offlineSaleTeamId = '';
                this.offlineSaleAmount = '';
                await this.pollAuctionState();
            }
        },

        // Offline Panel Methods
        toggleOfflineParticipant(teamId) {
            const idx = this.offlineParticipants.indexOf(teamId);
            if (idx === -1) {
                this.offlineParticipants.push(teamId);
            } else {
                this.offlineParticipants.splice(idx, 1);
                delete this.offlineTeamBids[teamId];
            }
        },

        isOfflineParticipant(teamId) {
            return this.offlineParticipants.includes(teamId);
        },

        startOfflineBidding() {
            if (this.offlineParticipants.length === 0) return;
            const basePrice = this.currentPlayer?.base_price || 0;
            this.offlineParticipants.forEach(tid => {
                if (!this.offlineTeamBids[tid]) {
                    this.offlineTeamBids[tid] = basePrice;
                }
            });
            this.offlinePhase = 'bidding';
        },

        endOfflineBidding() {
            let highestId = null;
            let highestAmt = 0;
            for (const tid of this.offlineParticipants) {
                const amt = Number(this.offlineTeamBids[tid]) || 0;
                if (amt > highestAmt) {
                    highestAmt = amt;
                    highestId = Number(tid);
                }
            }
            this.offlineHighestBidder = highestId;
            this.offlineHighestAmount = highestAmt;
            this.offlinePhase = 'results';
        },

        async confirmOfflineSale(teamId, amount) {
            if (!this.currentPlayer) return;
            const team = this.getTeamById(teamId);
            const teamName = team ? team.name : 'selected team';
            if (!confirm(`Sell ${this.currentPlayer?.player?.name} to ${teamName} for ${this.formatCurrency(amount)}?`)) return;

            const result = await this.sendCommand('sell-to-team', {
                auction_player_id: this.currentPlayer.id,
                team_id: teamId,
                amount: amount
            });
            if (result && result.success) {
                this.resetOfflinePanel();
                await this.pollAuctionState();
            }
        },

        resetOfflinePanel() {
            this.offlinePhase = 'selection';
            this.offlineParticipants = [];
            this.offlineTeamBids = {};
            this.offlineHighestBidder = null;
            this.offlineHighestAmount = 0;
        },

        offlineGoBack() {
            if (this.offlinePhase === 'bidding') {
                this.offlinePhase = 'selection';
            } else if (this.offlinePhase === 'results') {
                this.offlinePhase = 'bidding';
            }
        },

        getTeamById(teamId) {
            return this.teams.find(t => t.id === teamId || t.id === Number(teamId));
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
                    method: (endpoint === 'sealed-bids' || endpoint === 'all-players') ? 'GET' : 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: (endpoint === 'sealed-bids' || endpoint === 'all-players') ? undefined : JSON.stringify(body)
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
            }
        },

        async restartAuction() {
            const isRunning = this.auctionStatus === 'running' || this.auctionStatus === 'paused';
            const msg = isRunning
                ? 'WARNING: This will reset ALL players and bids! The auction is still in progress. Are you sure you want to restart from scratch?'
                : 'Are you sure you want to restart this auction? All players and bids will be reset.';
            if (!confirm(msg)) return;
            const result = await this.sendCommand('restart');
            if (result && result.success) {
                this.auctionStatus = 'running';
                this.displayState = 'waiting';
                this.currentPlayer = null;
                this.stopBiddingTimer();
                this.statusText = 'Auction restarted! All players reset.';
                await this.pollAuctionState();
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
            const result = await this.sendCommand('player-on-bid', { auction_player_id: this.selectedPlayerId });
            if (result) await this.pollAuctionState();
        },

        async sellPlayer() {
            if (!this.currentPlayer) return;
            // Pre-fill with highest bidder if available
            const highestBid = this.currentPlayer.bids?.length
                ? this.currentPlayer.bids.reduce((a, b) => (a.amount > b.amount ? a : b), this.currentPlayer.bids[0])
                : null;
            this.sellModalData = {
                team_id: highestBid?.team_id || '',
                amount: highestBid?.amount || this.currentPlayer.current_price || this.currentPlayer.base_price,
            };
            this.showSellModal = true;
        },

        async passPlayer() {
            if (!this.currentPlayer) return;
            const result = await this.sendCommand('pass-player', { auction_player_id: this.currentPlayer.id });
            if (result) await this.pollAuctionState();
        },

        // All Players tab
        get filteredAllPlayers() {
            if (!this.playerSearchQuery) return this.allPlayers;
            const q = this.playerSearchQuery.toLowerCase();
            return this.allPlayers.filter(p => p.name.toLowerCase().includes(q));
        },

        async fetchAllPlayers() {
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/all-players`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.allPlayers = data.players || [];
            } catch (e) {
                console.error('Failed to fetch all players:', e);
            }
        },

        async rebidCurrentPlayer() {
            if (!this.currentPlayer) return;
            if (!confirm('Reset this player\'s bids and start fresh? All current bids will be cleared.')) return;
            const result = await this.sendCommand('re-bid-player', { auction_player_id: this.currentPlayer.id });
            if (result && result.success) {
                this.statusText = 'Player re-bid started!';
                await this.pollAuctionState();
            }
        },

        async reAuctionPlayer(player) {
            if (!confirm(`Re-auction ${player.name}? This will put them back on bid with base price.`)) return;
            const result = await this.sendCommand('re-auction-player', { auction_player_id: player.id });
            if (result && result.success) {
                this.statusText = `${player.name} is back on auction!`;
                this.playerListTab = 'queue';
                await this.pollAuctionState();
            }
        },

        // Helpers
        formatCurrency(amount) {
            const num = Number(amount) || 0;
            if (num >= 10000000) {
                const val = num / 10000000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' Cr';
            }
            if (num >= 100000) {
                const val = num / 100000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' L';
            }
            if (num >= 1000) {
                const val = num / 1000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(1).replace(/\.?0+$/, '')) + 'K';
            }
            return num.toLocaleString();
        },

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
