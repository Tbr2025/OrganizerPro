@extends('backend.layouts.app')

@section('title', 'Live Auction | ' . $auction->name)

@section('admin-content')
<style>
    .auction-container {
        background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0a0a0a 100%);
        min-height: calc(100vh - 80px);
    }

    /* Glow effects */
    .glow-cyan {
        text-shadow: 0 0 30px rgba(0, 188, 212, 0.5);
    }

    .glow-green {
        text-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
    }

    .glow-yellow {
        text-shadow: 0 0 20px rgba(234, 179, 8, 0.5);
    }

    /* Loading spinner */
    .led-loader {
        width: 60px;
        height: 60px;
        border: 4px solid rgba(0, 188, 212, 0.2);
        border-top-color: #00bcd4;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Pulse animation */
    .pulse-glow {
        animation: pulse-glow 2s ease-in-out infinite;
    }

    @keyframes pulse-glow {
        0%, 100% { opacity: 0.6; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.02); }
    }

    /* Glow dots */
    .glow-dot {
        width: 10px;
        height: 10px;
        background: #00bcd4;
        border-radius: 50%;
        animation: dot-pulse 1.5s ease-in-out infinite;
        box-shadow: 0 0 10px #00bcd4;
    }

    .glow-dot:nth-child(2) { animation-delay: 0.2s; }
    .glow-dot:nth-child(3) { animation-delay: 0.4s; }

    @keyframes dot-pulse {
        0%, 100% { opacity: 0.3; transform: scale(0.8); }
        50% { opacity: 1; transform: scale(1.2); }
    }

    /* Player card */
    .player-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .player-avatar-wrapper {
        width: 100px;
        height: 100px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f97316, #ea580c);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: bold;
        color: white;
        box-shadow: 0 6px 20px rgba(249, 115, 22, 0.3);
    }

    .player-avatar-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top;
        border-radius: 14px;
    }

    /* Bid display */
    .bid-display {
        background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        border-radius: 12px;
        padding: 14px;
        box-shadow: 0 6px 20px rgba(4, 120, 87, 0.3);
    }

    /* Bid button */
    .bid-button {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: bold;
        padding: 12px 24px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
    }

    .bid-button:hover:not(:disabled) {
        transform: translateY(-1px);
        box-shadow: 0 8px 30px rgba(34, 197, 94, 0.5);
    }

    .bid-button:disabled {
        background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
        box-shadow: none;
        cursor: not-allowed;
    }

    .bid-button.winning {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    /* Sidebar styles */
    .sidebar-dark {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-scroll {
        max-height: calc(100vh - 300px);
        overflow-y: auto;
    }

    .sidebar-scroll::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 2px;
    }

    /* Sold player item */
    .sold-player-item {
        background: rgba(255, 255, 255, 0.05);
        border-left: 3px solid #22c55e;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .sold-player-item:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    /* Budget card */
    .budget-card {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(4, 120, 87, 0.1) 100%);
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 16px;
    }

    /* SOLD animation */
    .sold-overlay {
        animation: sold-pop 0.5s ease-out;
    }

    @keyframes sold-pop {
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); opacity: 1; }
    }

    /* Live badge */
    .live-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 1px;
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
    }

    .live-dot {
        width: 8px;
        height: 8px;
        background: #fff;
        border-radius: 50%;
        animation: live-blink 1s ease-in-out infinite;
    }

    @keyframes live-blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }
</style>

<div x-data='{
    auctionId: {{ $auction->id }},
    userTeam: @json($userTeam),
    player: { id: null, name: "", image_url: "", base_price: 0, current_price: 0, current_bid_team: null, role: "", batting_style: "", bowling_style: "", is_wicket_keeper: false },
    soldPlayers: @json($soldPlayers ?? []),
    state: "waiting",
    auctionStatus: "{{ $auction->status }}",
    bidType: "{{ $auction->bid_type ?? 'open' }}",
    teamBudget: {{ $remainingBudget ?? 0 }},
    bidError: "",
    bidSuccess: "",
    isSubmitting: false,
    lastPlayerId: null,
    customAmount: "",
    myBidAmount: {{ isset($myBid) && $myBid ? $myBid->amount : 0 }},
    auctionMode: "{{ $auction->open_bid_mode ?? 'online' }}",
    bidRules: @json($auction->bid_rules ?? []),

    // Timer state
    BID_TIMER_DURATION: {{ $auction->bid_timer_seconds ?? 30 }},
    BID_TIMER_RESET_TO: {{ $auction->bid_timer_reset_seconds ?? 15 }},
    timerSeconds: 0,
    timerWidth: 100,
    timerInterval: null,
    timerExpired: false,
    lastKnownPrice: 0,

    init() {
        if (this.auctionStatus === "completed") {
            this.state = "completed";
        } else {
            const initialPlayer = @json($currentPlayer);
            if (initialPlayer) {
                this.setPlayerOnBid(initialPlayer);
                this.lastPlayerId = initialPlayer.id;
                this.lastKnownPrice = Number(initialPlayer.current_price) || Number(initialPlayer.base_price) || 0;
            }
        }
        this.startPolling();
    },

    startPolling() {
        this.fetchCurrentPlayer();
        setInterval(() => {
            this.fetchCurrentPlayer();
            this.fetchSoldPlayers();
        }, 2000);
    },

    async fetchCurrentPlayer() {
        try {
            const res = await fetch("/auction/" + this.auctionId + "/active-player");
            const data = await res.json();
            if (data.auction_status) this.auctionStatus = data.auction_status;
            if (data.open_bid_mode) this.auctionMode = data.open_bid_mode;
            if (data.bid_type) this.bidType = data.bid_type;
            if (data.bid_rules) this.bidRules = data.bid_rules;
            if (data.auction_status === "completed") {
                this.state = "completed";
                this.resetPlayer();
                return;
            }
            if (data.auctionPlayer) {
                const isNewPlayer = data.auctionPlayer.id !== this.lastPlayerId;
                if (isNewPlayer || this.state === "waiting") {
                    this.lastPlayerId = data.auctionPlayer.id;
                    this.setPlayerOnBid(data.auctionPlayer);
                    this.myBidAmount = 0;
                    this.customAmount = "";
                    this.bidSuccess = "";
                }
                // Same player — update current price and bid team live
                const newPrice = Number(data.auctionPlayer.current_price) || this.player.current_price;
                this.player.current_price = newPrice;
                if (data.auctionPlayer.current_bid_team !== undefined) {
                    this.player.current_bid_team = data.auctionPlayer.current_bid_team;
                }
                // Sync timer from server
                this.syncTimerFromServer(data);
            } else if (this.state === "bidding") {
                this.state = "waiting";
                this.resetPlayer();
                this.lastPlayerId = null;
                this.stopTimer();
                this.timerSeconds = 0;
                this.timerWidth = 0;
            }
        } catch (e) { console.error("[BiddingPanel] Error:", e); }
    },

    async fetchSoldPlayers() {
        try {
            const res = await fetch("/auction/" + this.auctionId + "/sold-players");
            const data = await res.json();
            if (data.soldPlayers) this.soldPlayers = data.soldPlayers;
        } catch (e) {}
    },

    setPlayerOnBid(ap) {
        const p = ap.player;
        if (!p) return;
        let img = "https://ui-avatars.com/api/?name=" + encodeURIComponent(p.name || "P") + "&size=200&background=random";
        if (p.image_path) img = "/storage/" + p.image_path;
        const pt = p.player_type || p.playerType;
        const bp = p.batting_profile || p.battingProfile;
        const bw = p.bowling_profile || p.bowlingProfile;

        this.player = {
            id: ap.id,
            name: p.name || "Unknown",
            image_url: img,
            base_price: Number(ap.base_price) || 0,
            current_price: Number(ap.current_price) || Number(ap.base_price) || 0,
            current_bid_team: ap.current_bid_team || null,
            role: (typeof pt === "object" ? (pt.name || pt.type) : pt) || "Player",
            batting_style: (typeof bp === "object" ? (bp.style || bp.name) : bp) || "N/A",
            bowling_style: (typeof bw === "object" ? (bw.style || bw.name) : bw) || "N/A",
            is_wicket_keeper: p.is_wicket_keeper || false
        };
        this.state = "bidding";
    },

    resetPlayer() {
        this.player = { id: null, name: "", image_url: "", base_price: 0, current_price: 0, current_bid_team: null, role: "", batting_style: "", bowling_style: "", is_wicket_keeper: false };
        this.myBidAmount = 0;
        this.customAmount = "";
        this.bidSuccess = "";
    },

    // Calculate next bid amount from bid rules
    get nextBidAmount() {
        const current = this.player.current_price || this.player.base_price || 0;
        const rules = this.bidRules || [];
        let increment = 0;

        for (const r of rules) {
            const from = Number(r.from) || 0;
            const to = Number(r.to) || Infinity;
            const inc = Number(r.increment) || 0;
            if (current >= from && current <= to) {
                increment = inc;
                break;
            }
        }

        // Fallback: find next applicable rule
        if (increment === 0) {
            for (const r of rules) {
                const from = Number(r.from) || 0;
                const inc = Number(r.increment) || 0;
                if (current < from) {
                    increment = inc;
                    break;
                }
            }
        }

        return increment > 0 ? current + increment : 0;
    },

    get canRaiseHand() {
        if (this.auctionMode === "offline") return false;
        if (this.state !== "bidding" || this.isSubmitting) return false;
        if (this.timerExpired) return false;
        if (this.nextBidAmount <= 0) return false;
        if (this.nextBidAmount > this.teamBudget) return false;
        return true;
    },

    // Minimum bid for closed mode: current price + increment from rules
    get closedBidMinimum() {
        return this.nextBidAmount > 0 ? this.nextBidAmount : this.player.base_price;
    },

    // Increment amount from bid rules (for +/- buttons in closed bid)
    get currentIncrement() {
        const current = this.player.current_price || this.player.base_price || 0;
        const rules = this.bidRules || [];
        for (const r of rules) {
            const from = Number(r.from) || 0;
            const to = Number(r.to) || Infinity;
            const inc = Number(r.increment) || 0;
            if (current >= from && current <= to) return inc;
        }
        for (const r of rules) {
            const from = Number(r.from) || 0;
            const inc = Number(r.increment) || 0;
            if (current < from) return inc;
        }
        return 10000;
    },

    // For closed bid: custom amount validation
    get canBidCustom() {
        if (this.auctionMode === "offline") return false;
        if (this.state !== "bidding" || this.isSubmitting) return false;
        if (this.timerExpired) return false;
        const amt = Number(this.customAmount) || 0;
        if (amt <= 0) return false;
        if (amt > this.teamBudget) return false;
        if (amt < this.closedBidMinimum) return false;
        return true;
    },

    // IPL-style: raise hand (auto-increment)
    async raiseHand() {
        if (!this.canRaiseHand || !this.player.id) return;
        this.isSubmitting = true;
        this.bidError = "";
        this.bidSuccess = "";
        try {
            const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" },
                body: JSON.stringify({ auction_player_id: this.player.id })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || "Failed to place bid");
            this.myBidAmount = data.new_price || this.nextBidAmount;
            this.player.current_price = data.new_price || this.nextBidAmount;
            this.player.current_bid_team = { id: this.userTeam.id, name: this.userTeam.name };
            this.bidSuccess = "Bid placed! " + this.formatCurrency(this.myBidAmount);
        } catch (e) { this.bidError = e.message; }
        finally { this.isSubmitting = false; }
    },

    // Offline mode: raise hand with optional amount
    async raiseHandOffline() {
        if (this.isSubmitting || !this.player.id) return;
        const amt = Number(this.customAmount) * 100000;
        this.isSubmitting = true;
        this.bidError = "";
        this.bidSuccess = "";
        try {
            const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" },
                body: JSON.stringify({ auction_player_id: this.player.id, amount: amt > 0 ? amt : undefined })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || "Failed to raise hand");
            this.myBidAmount = amt > 0 ? amt : (data.new_price || this.player.current_price);
            this.bidSuccess = "Hand raised! " + (amt > 0 ? this.formatCurrency(amt) : "");
        } catch (e) { this.bidError = e.message; }
        finally { this.isSubmitting = false; }
    },

    // Closed bid: place custom amount
    async placeCustomBid() {
        if (!this.canBidCustom || !this.player.id) return;
        const amt = Number(this.customAmount);
        this.isSubmitting = true;
        this.bidError = "";
        this.bidSuccess = "";
        try {
            const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" },
                body: JSON.stringify({ auction_player_id: this.player.id, amount: amt })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || "Failed to place bid");
            this.myBidAmount = amt;
            this.bidSuccess = "Bid placed successfully!";
            this.customAmount = "";
        } catch (e) { this.bidError = e.message; }
        finally { this.isSubmitting = false; }
    },

    // Timer methods — synced from admin panel via server timestamps
    lastServerUpdatedAt: 0,

    syncTimerFromServer(data) {
        const serverTime = data.server_time || 0;
        const playerUpdatedAt = data.player_updated_at || 0;
        const timerDuration = data.bid_timer_seconds || this.BID_TIMER_DURATION;

        if (!serverTime || !playerUpdatedAt) return;

        // Detect if admin reset the timer (updated_at changed)
        const isTimerReset = playerUpdatedAt !== this.lastServerUpdatedAt;
        this.lastServerUpdatedAt = playerUpdatedAt;

        // Calculate remaining seconds from server
        const elapsed = serverTime - playerUpdatedAt;
        const remaining = Math.max(0, timerDuration - elapsed);

        if (remaining <= 0) {
            this.stopTimer();
            this.timerSeconds = 0;
            this.timerWidth = 0;
            this.timerExpired = true;
        } else if (isTimerReset || !this.timerInterval) {
            // Start/restart local countdown from server-calculated remaining
            this.startTimer(remaining, timerDuration);
        }
    },

    startTimer(remaining, maxDuration) {
        this.stopTimer();
        this.timerSeconds = Math.ceil(remaining);
        const maxSeconds = maxDuration || remaining;
        this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
        this.timerExpired = false;
        this.timerInterval = setInterval(() => {
            this.timerSeconds--;
            this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
            if (this.timerSeconds <= 0) {
                this.stopTimer();
                this.timerExpired = true;
            }
        }, 1000);
    },

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    },

    get isMyTeamHighest() {
        return this.player.current_bid_team && this.player.current_bid_team.id === this.userTeam.id;
    },

    formatCurrency(amt) {
        const n = Number(amt) || 0;
        if (n >= 10000000) {
            const val = n / 10000000;
            return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, "")) + " Cr";
        }
        if (n >= 100000) {
            const val = n / 100000;
            return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, "")) + " L";
        }
        if (n >= 1000) {
            const val = n / 1000;
            return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(1).replace(/\.?0+$/, "")) + "K";
        }
        return n.toLocaleString();
    }
}' class="auction-container flex">
    <!-- Left Sidebar: Sold Players -->
    <div class="w-72 sidebar-dark flex flex-col">
        <div class="p-5 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                    <i class="fas fa-gavel text-green-400"></i>
                </div>
                <div>
                    <h2 class="text-white font-bold">Sold Players</h2>
                    <p class="text-xs text-gray-400" x-text="soldPlayers.length + ' players'"></p>
                </div>
            </div>
        </div>

        <div class="sidebar-scroll flex-1 p-3 space-y-2">
            <template x-if="soldPlayers.length === 0">
                <div class="text-center py-10">
                    <div class="text-gray-600 text-4xl mb-3"><i class="fas fa-inbox"></i></div>
                    <p class="text-gray-500 text-sm">No players sold yet</p>
                </div>
            </template>
            <template x-for="sp in soldPlayers" :key="sp.id">
                <div class="sold-player-item p-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-500 to-orange-700 flex items-center justify-center text-white font-bold text-xs"
                             x-text="sp.player?.name?.substring(0,2).toUpperCase() || 'P'"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium text-sm truncate" x-text="sp.player?.name"></p>
                            <p class="text-xs text-cyan-400 truncate" x-text="sp.sold_to_team?.name"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Center: Active Player -->
    <div class="flex-1 flex flex-col items-center justify-center p-4 relative">
        <!-- Header -->
        <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
            <div>
                @if(isset($isPreviewMode) && $isPreviewMode)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                        <i class="fas fa-eye mr-2"></i> Admin Preview
                    </span>
                @endif
            </div>
            <div x-show="state !== 'completed'" class="live-badge flex items-center gap-2">
                <span class="live-dot"></span>
                <span>LIVE</span>
            </div>
        </div>

        <!-- Waiting State -->
        <div x-show="state === 'waiting'" x-transition.opacity class="text-center">
            <div class="led-loader mx-auto mb-8"></div>
            <h1 class="text-4xl font-bold text-cyan-400 glow-cyan pulse-glow mb-4">WAITING FOR AUCTION</h1>
            <p class="text-xl text-gray-400 mb-6">{{ $auction->name }}</p>
            <div class="flex justify-center gap-3 mb-6">
                <div class="glow-dot"></div>
                <div class="glow-dot"></div>
                <div class="glow-dot"></div>
            </div>
        </div>

        <!-- Auction Completed State -->
        <div x-show="state === 'completed'" x-transition.opacity x-cloak class="text-center">
            <div class="text-8xl mb-6"><i class="fas fa-trophy text-yellow-400" style="text-shadow: 0 0 30px rgba(234,179,8,0.5);"></i></div>
            <h1 class="text-5xl font-extrabold text-yellow-400 glow-yellow mb-6">AUCTION COMPLETED</h1>
            <p class="text-xl text-gray-400 mb-2">{{ $auction->name }}</p>
            <p class="text-gray-500">Thank you for participating!</p>
        </div>

        <!-- Bidding State -->
        <div x-show="state === 'bidding'" x-transition.opacity x-cloak class="w-full max-w-md">
            <div class="player-card p-5">
                <!-- Timer Bar -->
                <div x-show="timerSeconds > 0 || timerExpired" class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs uppercase tracking-wider"
                              :class="timerExpired ? 'text-red-400' : (timerSeconds <= 5 ? 'text-red-400' : 'text-gray-400')"
                              x-text="timerExpired ? 'TIME UP' : 'Time Left'"></span>
                        <span class="text-sm font-bold"
                              :class="timerExpired ? 'text-red-500' : (timerSeconds <= 5 ? 'text-red-500 animate-pulse' : 'text-white')"
                              x-text="timerExpired ? '0s' : timerSeconds + 's'"></span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full transition-all duration-1000"
                             :class="timerExpired ? 'bg-red-500' : (timerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500')"
                             :style="`width: ${timerWidth}%`"></div>
                    </div>
                </div>

                <!-- Timer Expired Banner -->
                <div x-show="timerExpired" class="mb-3 bg-red-500/20 border border-red-500/30 rounded-lg p-2 text-center">
                    <p class="text-red-400 font-semibold text-sm">Bidding Time Expired</p>
                </div>

                <!-- Player Info Row: Avatar + Name + Base Price -->
                <div class="flex items-center gap-4 mb-4">
                    <div class="player-avatar-wrapper flex-shrink-0">
                        <template x-if="player.image_url && !player.image_url.includes('ui-avatars')">
                            <img :src="player.image_url" :alt="player.name">
                        </template>
                        <template x-if="!player.image_url || player.image_url.includes('ui-avatars')">
                            <span x-text="player.name?.substring(0,2).toUpperCase() || 'P'"></span>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl font-bold text-white truncate" x-text="player.name"></h2>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <span class="px-2 py-0.5 bg-white/10 rounded text-xs text-gray-300" x-text="player.role"></span>
                            <span class="px-2 py-0.5 bg-white/10 rounded text-xs text-gray-300" x-text="player.batting_style"></span>
                            <span class="px-2 py-0.5 bg-white/10 rounded text-xs text-gray-300" x-text="player.bowling_style"></span>
                        </div>
                        <p class="text-yellow-400 font-semibold text-sm mt-1">Base: <span x-text="formatCurrency(player.base_price)"></span></p>
                    </div>
                </div>

                <!-- Offline Mode: Hand Raise + Amount Entry -->
                <div x-show="auctionMode === 'offline'" x-cloak>
                    <div class="flex items-center justify-center gap-2 mb-3">
                        <div class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></div>
                        <p class="text-orange-300 text-xs font-bold uppercase tracking-widest">Offline Bidding</p>
                    </div>

                    <!-- Current Price -->
                    <div class="bid-display mb-3 text-center">
                        <p class="text-emerald-200 text-xs uppercase tracking-widest mb-1">Current Price</p>
                        <p class="text-3xl font-black text-white" x-text="formatCurrency(player.current_price)"></p>
                    </div>

                    <!-- Amount Input (Lakhs) -->
                    <div class="bg-white/5 rounded-lg p-4 border border-white/10 mb-3">
                        <label class="text-gray-400 text-xs uppercase tracking-wide mb-2 block text-center">Your Bid (Lakhs)</label>
                        <div class="flex items-center gap-2 mb-2">
                            <button @click="customAmount = Math.max(0, (Number(customAmount) || (player.current_price / 100000)) - (currentIncrement / 100000))"
                                    class="w-10 h-10 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-lg font-bold flex items-center justify-center hover:bg-red-500/30 transition shrink-0">
                                &minus;
                            </button>
                            <div class="flex-1">
                                <div class="flex items-center bg-white/10 border border-white/20 rounded-lg focus-within:border-cyan-500">
                                    <input type="number" x-model.number="customAmount"
                                           class="w-full px-3 py-2 bg-transparent text-white text-lg text-center focus:outline-none"
                                           placeholder="0" step="0.5" min="0">
                                    <span class="pr-3 text-gray-400 font-bold text-sm">L</span>
                                </div>
                            </div>
                            <button @click="customAmount = (Number(customAmount) || (player.current_price / 100000)) + (currentIncrement / 100000)"
                                    class="w-10 h-10 rounded-lg bg-green-500/20 border border-green-500/30 text-green-400 text-lg font-bold flex items-center justify-center hover:bg-green-500/30 transition shrink-0">
                                +
                            </button>
                        </div>
                        <p class="text-center text-gray-500 text-xs">Budget: <span class="text-green-400 font-bold" x-text="formatCurrency(teamBudget)"></span></p>
                    </div>

                    <button @click="raiseHandOffline()"
                            :disabled="isSubmitting || !player.id || timerExpired"
                            class="bid-button w-full mb-2">
                        <span x-show="!isSubmitting" x-text="Number(customAmount) > 0 ? 'RAISE HAND — ' + formatCurrency(customAmount * 100000) : 'RAISE HAND'"></span>
                        <span x-show="isSubmitting">Raising Hand...</span>
                    </button>

                    <div x-show="bidSuccess" class="mt-2 p-2 bg-green-500/20 border border-green-500/30 rounded-lg text-center" x-cloak>
                        <p class="text-green-400 text-xs font-medium" x-text="bidSuccess"></p>
                    </div>
                </div>

                <!-- ===== OPEN BID: IPL-Style Raise Hand ===== -->
                <div x-show="bidType === 'open' && auctionMode !== 'offline'">

                    <!-- Current Price -->
                    <div class="bid-display mb-3 text-center">
                        <p class="text-emerald-200 text-xs uppercase tracking-widest mb-1">Current Price</p>
                        <p class="text-3xl font-black text-white" x-text="formatCurrency(player.current_price)"></p>
                        <template x-if="player.current_bid_team">
                            <p class="text-emerald-300 text-xs mt-1">
                                <span class="font-bold text-white" x-text="player.current_bid_team.name"></span>
                                <template x-if="isMyTeamHighest">
                                    <span class="ml-1 px-1.5 py-0.5 bg-green-500 text-white text-xs rounded font-bold">YOU</span>
                                </template>
                            </p>
                        </template>
                    </div>

                    <!-- Next Bid + Raise Hand -->
                    <div class="bg-white/5 rounded-lg p-3 mb-3 text-center border border-white/10" x-show="nextBidAmount > 0">
                        <p class="text-gray-400 text-xs mb-1">Your Bid</p>
                        <p class="text-xl font-bold text-cyan-400" x-text="formatCurrency(nextBidAmount)"></p>
                    </div>

                    <button @click="raiseHand()"
                            :disabled="!canRaiseHand"
                            class="bid-button w-full mb-3"
                            :class="{ 'winning': isMyTeamHighest }">
                        <span x-show="!isSubmitting" x-text="isMyTeamHighest ? 'YOU ARE HIGHEST' : 'RAISE HAND — ' + formatCurrency(nextBidAmount)"></span>
                        <span x-show="isSubmitting">Placing Bid...</span>
                    </button>

                    <p class="text-center text-gray-500 text-xs">
                        Budget: <span class="text-green-400 font-bold" x-text="formatCurrency(teamBudget)"></span>
                    </p>

                    <div x-show="bidSuccess" class="mt-2 p-2 bg-green-500/20 border border-green-500/30 rounded-lg text-center" x-cloak>
                        <p class="text-green-400 text-xs font-medium" x-text="bidSuccess"></p>
                    </div>
                </div>

                <!-- ===== CLOSED BID: Custom Amount Entry ===== -->
                <div x-show="bidType === 'closed' && auctionMode !== 'offline'">

                    <!-- Current Price -->
                    <div class="bid-display mb-3 text-center">
                        <p class="text-emerald-200 text-xs uppercase tracking-widest mb-1">Current Price</p>
                        <p class="text-3xl font-black text-white" x-text="formatCurrency(player.current_price)"></p>
                    </div>

                    <!-- Your Current Bid -->
                    <div x-show="myBidAmount > 0" class="mb-3 bg-green-600/20 rounded-lg p-3 text-center border border-green-500/30">
                        <p class="text-green-200 text-xs uppercase mb-0.5">Your Bid</p>
                        <p class="text-xl font-bold text-green-400" x-text="formatCurrency(myBidAmount)"></p>
                    </div>

                    <!-- Bid Input (Lakhs) -->
                    <div class="bg-white/5 rounded-lg p-4 border border-white/10 mb-3">
                        <label class="text-gray-400 text-xs uppercase tracking-wide mb-2 block text-center">Bid Amount (Lakhs) &middot; Min: <span x-text="(closedBidMinimum / 100000)"></span> L</label>
                        <div class="flex items-center gap-2 mb-3">
                            <button @click="customAmount = Math.max(closedBidMinimum, (Number(customAmount) || closedBidMinimum) - currentIncrement)"
                                    class="w-10 h-10 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-lg font-bold flex items-center justify-center hover:bg-red-500/30 transition shrink-0">
                                &minus;
                            </button>
                            <div class="flex-1">
                                <div class="flex items-center bg-white/10 border border-white/20 rounded-lg focus-within:border-cyan-500">
                                    <input type="number"
                                           :value="Number(customAmount) > 0 ? (customAmount / 100000) : ''"
                                           @input="customAmount = Number($event.target.value) * 100000"
                                           :min="closedBidMinimum / 100000"
                                           class="w-full px-3 py-2 bg-transparent text-white text-lg text-center focus:outline-none"
                                           :placeholder="(closedBidMinimum / 100000)"
                                           step="0.5">
                                    <span class="pr-3 text-gray-400 font-bold text-sm">L</span>
                                </div>
                            </div>
                            <button @click="customAmount = (Number(customAmount) || closedBidMinimum) + currentIncrement"
                                    class="w-10 h-10 rounded-lg bg-green-500/20 border border-green-500/30 text-green-400 text-lg font-bold flex items-center justify-center hover:bg-green-500/30 transition shrink-0">
                                +
                            </button>
                        </div>
                        <button @click="placeCustomBid()"
                                :disabled="!canBidCustom"
                                class="bid-button w-full"
                                :class="{ 'winning': myBidAmount > 0 }">
                            <span x-text="isSubmitting ? 'Submitting...' : (myBidAmount > 0 ? 'Update Bid' : 'Place Bid')"></span>
                        </button>
                    </div>
                    <p class="text-center text-gray-500 text-xs">Budget: <span class="text-green-400 font-bold" x-text="formatCurrency(teamBudget)"></span></p>

                    <div x-show="bidSuccess" class="mt-2 p-2 bg-green-500/20 border border-green-500/30 rounded-lg text-center" x-cloak>
                        <p class="text-green-400 text-xs font-medium" x-text="bidSuccess"></p>
                    </div>
                </div>

                <p x-show="bidError" class="text-red-400 text-sm mt-3 text-center" x-text="bidError" x-cloak></p>
            </div>
        </div>

        <!-- Sold State -->
        <div x-show="state === 'sold'" x-transition.opacity x-cloak class="text-center sold-overlay">
            <div class="text-8xl mb-6">🎉</div>
            <h1 class="text-6xl font-extrabold text-green-400 glow-green mb-6">SOLD!</h1>
            <p class="text-2xl text-gray-400">Player has been sold</p>
        </div>
    </div>

    <!-- Right Sidebar: Your Team Budget -->
    <div class="w-80 sidebar-dark flex flex-col border-l border-white/10">
        <!-- Team Header -->
        <div class="p-5 border-b border-white/10">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold">
                    {{ substr($userTeam->name, 0, 2) }}
                </div>
                <div>
                    <h2 class="text-white font-bold">{{ $userTeam->name }}</h2>
                    <p class="text-xs text-gray-400">Your Team</p>
                </div>
            </div>
        </div>

        <!-- Budget Display -->
        <div class="p-5">
            <div class="budget-card p-6 text-center">
                <p class="text-gray-400 text-sm uppercase tracking-wide mb-2">Remaining Budget</p>
                @php
                    $budget = $remainingBudget ?? 0;
                    if ($budget >= 10000000) {
                        $budgetDisplay = number_format($budget / 10000000, 2) . ' Cr';
                    } elseif ($budget >= 100000) {
                        $budgetDisplay = number_format($budget / 100000, 2) . ' L';
                    } else {
                        $budgetDisplay = number_format($budget);
                    }
                @endphp
                <p class="text-4xl font-bold text-green-400 glow-green">{{ $budgetDisplay }}</p>
                @php
                    $budgetPercent = (($remainingBudget ?? 0) / ($auction->max_budget_per_team ?? 10000000)) * 100;
                @endphp
                <div class="w-full bg-gray-700 rounded-full h-2 mt-4">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-400 h-2 rounded-full transition-all duration-500"
                         style="width: {{ $budgetPercent }}%"></div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        @php
            $playersBought = $soldPlayers->where('sold_to_team_id', $userTeam->id)->count();
            $totalSpent = ($auction->max_budget_per_team ?? 10000000) - ($remainingBudget ?? 0);
            if ($totalSpent >= 10000000) {
                $spentDisplay = number_format($totalSpent / 10000000, 2) . ' Cr';
            } elseif ($totalSpent >= 100000) {
                $spentDisplay = number_format($totalSpent / 100000, 2) . ' L';
            } else {
                $spentDisplay = number_format($totalSpent);
            }
        @endphp
        <div class="p-5 border-t border-white/10">
            <h3 class="text-white font-semibold mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-cyan-400"></i> Quick Stats
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Players Bought</span>
                    <span class="text-white font-bold">{{ $playersBought }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Spent</span>
                    <span class="text-yellow-400 font-bold">{{ $spentDisplay }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Auction Status</span>
                    <span class="text-green-400 font-bold text-xs uppercase" x-text="state">{{ $auction->status }}</span>
                </div>
            </div>
        </div>

        @if(isset($isPreviewMode) && $isPreviewMode)
            <div class="mt-auto p-5 border-t border-white/10 space-y-2">
                <a href="{{ route('team.auction.bidding.show', $auction->id) }}" class="block w-full text-center py-2 px-4 rounded-lg bg-white/10 text-white text-sm hover:bg-white/20 transition">
                    <i class="fas fa-exchange-alt mr-2"></i> Switch Team
                </a>
                <a href="{{ route('admin.auction.organizer.panel', $auction->id) }}" class="block w-full text-center py-2 px-4 rounded-lg bg-cyan-500/20 text-cyan-400 text-sm hover:bg-cyan-500/30 transition">
                    <i class="fas fa-cog mr-2"></i> Organizer Panel
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
