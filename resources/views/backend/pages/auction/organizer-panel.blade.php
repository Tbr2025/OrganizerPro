@extends('backend.layouts.app')

@section('title', 'Live Auction Panel | ' . $auction->name)

@section('admin-content')
<style>
    /* CSS for the countdown timer bar and tumbler effect */
    .timer-bar-inner { transition: width 0.5s linear; }
    .tumbler-text { transition: transform 0.1s ease-in-out; }
</style>

<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Live Auction Control Panel</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Managing: <span class="font-semibold">{{ $auction->name }}</span></p>
        </div>
        <a href="{{ route('admin.auctions.index') }}" class="btn btn-secondary">Back to Auctions List</a>
    </div>

    {{-- Main Alpine.js Component --}}
    <div x-data="organizerPanel()" 
         x-init="init(
            {{ $auction->id }}, 
            '{{ $auction->status }}',
            {{ json_encode($availablePlayers->map(fn($ap) => ['id' => $ap->id, 'name' => $ap->player->name, 'base_price' => $ap->base_price])) }}
         )" 
         class="space-y-6">

        <!-- 1. Auction Status Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border">
            <h2 class="text-xl font-semibold mb-4">Auction Controls</h2>
            <div class="flex items-center space-x-4">
                <button @click="startAuction()" x-show="auctionStatus === 'pending' || auctionStatus === 'scheduled'" class="btn btn-success">Start Auction</button>
                <button @click="endAuction()" x-show="auctionStatus === 'live'" class="btn btn-danger" x-cloak>End Auction</button>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500">Status:</span>
                    <span class="font-bold text-lg" :class="{ 'text-green-500': auctionStatus === 'live', 'text-yellow-500': auctionStatus === 'pending' || auctionStatus === 'scheduled', 'text-gray-500': auctionStatus === 'completed' }" x-text="auctionStatus.charAt(0).toUpperCase() + auctionStatus.slice(1)"></span>
                </div>
            </div>
        </div>

        <!-- 2. Player Control & Display Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            {{-- Player Selection & Status --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border">
                <h2 class="text-xl font-semibold mb-4">Auction Flow</h2>
                
                {{-- Tumbler Display --}}
                <div class="h-24 bg-gray-900 rounded-lg flex items-center justify-center text-center overflow-hidden relative">
                    <div x-ref="tumbler" class="text-3xl font-bold text-yellow-400 tumbler-text">
                        <div x-text="tumblerText"></div>
                    </div>
                </div>

                {{-- Status Text --}}
                <div class="text-center mt-4 text-gray-600 dark:text-gray-300 min-h-[40px]" x-text="statusText"></div>
            </div>
            
            {{-- Current Player on Bid --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border">
                <h2 class="text-xl font-semibold mb-4">Currently on the Block</h2>
                <div x-show="!currentPlayer" x-cloak class="text-center py-10 text-gray-500">Waiting for next player...</div>
                
                <div x-show="currentPlayer" x-cloak class="flex flex-col items-center text-center">
                    <img :src="currentPlayer.player.image_path ? `/storage/${currentPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(currentPlayer.player.name)}`" class="w-24 h-24 rounded-full object-cover mb-3 border-4 border-blue-500">
                    <h3 class="text-2xl font-bold" x-text="currentPlayer.player.name"></h3>
                    <p class="font-bold text-xl mt-2">Current Bid: <span class="text-green-500" x-text="formatCurrency(currentBid)"></span></p>
                    <p class="text-sm text-gray-500">Held by: <strong x-text="winningTeamName"></strong></p>

                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 mt-4">
                        <div class="bg-blue-600 h-2.5 rounded-full timer-bar-inner" :style="`width: ${timerWidth}%`"></div>
                    </div>
                    <p class="text-sm text-gray-500 mt-1"><span x-text="biddingTimerSeconds"></span>s remaining</p>

                    <div class="mt-4 flex space-x-2">
                        <button @click="sellPlayer()" class="btn btn-success btn-sm">Sell Now (Manual)</button>
                        <button @click="passPlayer()" class="btn btn-warning btn-sm">Pass Now (Manual)</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Live Bid Log -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border">
            <div class="p-5 border-b"><h2 class="text-xl font-semibold">Live Bid History</h2></div>
            <ul class="p-5 space-y-2 max-h-96 overflow-y-auto">
                <template x-for="bid in bidLog" :key="bid.id">
                    <li class="bg-gray-50 dark:bg-gray-900/50 p-3 rounded-md flex justify-between items-center">
                        <div>
                            <span class="font-semibold text-gray-800 dark:text-white" x-text="bid.team.name"></span>
                            <span class="text-sm text-gray-500 ml-2" x-text="`(by ${bid.user.name})`"></span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-lg font-bold text-green-600 dark:text-green-400" x-text="formatCurrency(bid.amount)"></span>
                            <span class="text-xs text-gray-400" x-text="new Date(bid.created_at).toLocaleTimeString()"></span>
                        </div>
                    </li>
                </template>
                <li x-show="bidLog.length === 0" x-cloak class="text-center py-10 text-gray-500">Waiting for bids...</li>
            </ul>
        </div>
    </div>
</div>

<script>
    function organizerPanel() {
        return {
            // --- Constants ---
            BID_TIMER_DURATION: 30,
            BID_TIMER_RESET_TO: 15,
            NEXT_PLAYER_DELAY: 5,

            // --- State & Data ---
            auctionId: null,
            auctionStatus: 'pending',
            availablePlayers: [],
            tumblerText: 'Auction Pending',
            statusText: 'Click "Start Auction" to begin.',
            selectedPlayerId: null, // Only used to send to the backend
            currentPlayer: null,
            currentBid: 0,
            winningTeamName: 'No Bids',
            bidLog: [],
            isTumbling: false,

            // --- Timers ---
            biddingTimerInterval: null,
            biddingTimerSeconds: 0,
            timerWidth: 100,
            nextPlayerTimeout: null,

            // --- Initialization ---
            init(auctionId, initialStatus, initialPlayers) {
                this.auctionId = auctionId;
                this.auctionStatus = initialStatus;
                this.availablePlayers = initialPlayers;
                if (this.auctionStatus === 'live') {
                    this.statusText = 'Ready to find the first player.';
                    this.tumblerText = 'Ready';
                }
                
                window.Echo.private(`auction.${this.auctionId}`)
                    .listen('.player.onbid', (e) => this.handlePlayerOnBid(e))
                    .listen('.new-bid', (e) => this.handleNewBid(e))
                    .listen('.player.sold', (e) => this.handlePlayerSold(e));
            },

            // --- Event Handlers from Soketi ---
            handlePlayerOnBid(event) {
                this.currentPlayer = event.auctionPlayer;
                this.currentBid = event.auctionPlayer.base_price;
                this.winningTeamName = 'No Bids';
                this.bidLog = [];
                this.statusText = `${event.auctionPlayer.player.name} is now live for bidding!`;
                this.startBiddingTimer();
            },
            handleNewBid(event) {
                if (!this.currentPlayer || event.bid.auction_player_id !== this.currentPlayer.id) return;
                this.currentBid = event.bid.amount;
                this.winningTeamName = event.bid.team.name;
                this.bidLog.unshift(event.bid);
                this.resetBiddingTimer();
            },
            handlePlayerSold(event) {
                this.statusText = `${event.auctionPlayer.player.name} was ${event.winningTeam ? 'SOLD' : 'UNSOLD'}.`;
                this.currentPlayer = null;
                this.bidLog = [];
                this.stopBiddingTimer();
                // **CRITICAL**: Remove the sold/passed player from the available list
                this.availablePlayers = this.availablePlayers.filter(p => p.id !== event.auctionPlayer.id);

                if (this.auctionStatus === 'live' && this.availablePlayers.length > 0) {
                    this.statusText += ` Next player in ${this.NEXT_PLAYER_DELAY} seconds...`;
                    this.tumblerText = 'Up Next...';
                    clearTimeout(this.nextPlayerTimeout);
                    this.nextPlayerTimeout = setTimeout(() => this.startTumbler(), this.NEXT_PLAYER_DELAY * 1000);
                } else if (this.availablePlayers.length === 0) {
                     this.statusText = 'All players have been auctioned! You can now end the auction.';
                     this.tumblerText = 'Finished';
                }
            },

            // --- Timer Management ---
            startBiddingTimer() {
                this.stopBiddingTimer();
                this.biddingTimerSeconds = this.BID_TIMER_DURATION;
                this.timerWidth = 100;
                this.biddingTimerInterval = setInterval(() => {
                    this.biddingTimerSeconds--;
                    this.timerWidth = (this.biddingTimerSeconds / this.BID_TIMER_DURATION) * 100;
                    if (this.biddingTimerSeconds <= 0) {
                        this.stopBiddingTimer();
                        this.sellPlayer(); // Automatically sells or passes player
                    }
                }, 1000);
            },
            resetBiddingTimer() {
                this.biddingTimerSeconds = this.BID_TIMER_RESET_TO;
            },
            stopBiddingTimer() {
                clearInterval(this.biddingTimerInterval);
                this.biddingTimerInterval = null;
                this.timerWidth = 100;
            },

            // --- Player Tumbler Logic ---
            startTumbler() {
                if (this.isTumbling || this.availablePlayers.length === 0) return;
                this.isTumbling = true;
                this.statusText = 'Randomly selecting next player...';
                const tumblerEl = this.$refs.tumbler;
                
                let shuffleCount = 0;
                const maxShuffles = 30;

                const shuffleInterval = setInterval(() => {
                    const randomIndex = Math.floor(Math.random() * this.availablePlayers.length);
                    this.tumblerText = this.availablePlayers[randomIndex].name;
                    shuffleCount++;
                    if (shuffleCount >= maxShuffles) {
                        clearInterval(shuffleInterval);
                        this.selectedPlayerId = this.availablePlayers[randomIndex].id;
                        this.tumblerText = this.availablePlayers[randomIndex].name;
                        this.statusText = `Selected: ${this.availablePlayers[randomIndex].name}. Putting on bid...`;
                        this.isTumbling = false;
                        setTimeout(() => this.putPlayerOnBid(), 1500); 
                    }
                }, 100);
            },
            
            // --- API Call Methods ---
            async sendCommand(endpoint, body = {}) {
                try {
                    const response = await fetch(`/admin/organizer/auction/${this.auctionId}/api/${endpoint}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body)
                    });
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Server responded with an error.');
                    }
                    const data = await response.json();
                    if (endpoint === 'start') {
                        this.auctionStatus = 'live';
                        this.statusText = 'Auction started! Finding first player...';
                        this.startTumbler();
                    }
                    if (endpoint === 'end') {
                        this.auctionStatus = 'completed';
                        this.statusText = 'Auction has been manually ended.';
                        clearTimeout(this.nextPlayerTimeout);
                        this.stopBiddingTimer();
                    }
                } catch (error) {
                    alert(`An error occurred: ${error.message}`);
                }
            },
            startAuction() { this.sendCommand('start'); },
            endAuction() { this.sendCommand('end'); },
            putPlayerOnBid() {
                if (!this.selectedPlayerId) return;
                this.sendCommand('player.onbid', { auction_player_id: this.selectedPlayerId });
            },
            sellPlayer() {
                if (this.currentPlayer) this.sendCommand(`sell-player`, { auction_player_id: this.currentPlayer.id });
            },
            passPlayer() {
                if (this.currentPlayer) this.sendCommand(`pass-player`, { auction_player_id: this.currentPlayer.id });
            },

            // --- Helper Functions ---
            formatCurrency(amount) {
                return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(amount || 0);
            }
        }
    }
</script>
@endsection