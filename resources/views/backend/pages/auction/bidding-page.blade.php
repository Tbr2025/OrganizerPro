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
        width: 200px;
        height: 200px;
        border-radius: 20px;
        background: linear-gradient(135deg, #f97316, #ea580c);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 80px;
        font-weight: bold;
        color: white;
        box-shadow: 0 10px 40px rgba(249, 115, 22, 0.3);
    }

    .player-avatar-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 20px;
    }

    /* Bid display */
    .bid-display {
        background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 10px 40px rgba(4, 120, 87, 0.3);
    }

    /* Bid button */
    .bid-button {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        border: none;
        border-radius: 16px;
        font-size: 24px;
        font-weight: bold;
        padding: 20px 40px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 40px rgba(34, 197, 94, 0.4);
    }

    .bid-button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 15px 50px rgba(34, 197, 94, 0.5);
    }

    .bid-button:disabled {
        background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
        box-shadow: none;
        cursor: not-allowed;
    }

    .bid-button.winning {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 10px 40px rgba(59, 130, 246, 0.4);
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
    player: { id: null, name: "", image_url: "", base_price: 0, role: "", batting_style: "", bowling_style: "", is_wicket_keeper: false },
    bidRules: @json($auction->bid_rules ?? []),
    soldPlayers: @json($soldPlayers ?? []),
    state: "waiting",
    currentBid: 0,
    teamBudget: {{ $remainingBudget ?? 0 }},
    winningTeamName: "Waiting for bids...",
    winningTeamId: null,
    finalPrice: 0,
    bidError: "",
    isSubmitting: false,
    lastPlayerId: null,

    init() {
        console.log("[BiddingPanel] init() - auctionId:", this.auctionId);
        const initialPlayer = @json($currentPlayer);
        if (initialPlayer) this.setPlayerOnBid(initialPlayer);
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
            if (data.auctionPlayer) {
                if (data.auctionPlayer.id !== this.lastPlayerId || this.state === "waiting") {
                    this.lastPlayerId = data.auctionPlayer.id;
                    this.setPlayerOnBid(data.auctionPlayer);
                } else {
                    this.currentBid = data.auctionPlayer.current_price || this.currentBid;
                    if (data.auctionPlayer.current_bid_team) {
                        this.winningTeamName = data.auctionPlayer.current_bid_team.name;
                        this.winningTeamId = data.auctionPlayer.current_bid_team.id;
                    }
                }
            } else if (this.state === "bidding") {
                this.state = "waiting";
                this.resetPlayer();
                this.lastPlayerId = null;
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
            base_price: ap.base_price || 0,
            role: (typeof pt === "object" ? (pt.name || pt.type) : pt) || "Player",
            batting_style: (typeof bp === "object" ? (bp.style || bp.name) : bp) || "N/A",
            bowling_style: (typeof bw === "object" ? (bw.style || bw.name) : bw) || "N/A",
            is_wicket_keeper: p.is_wicket_keeper || false
        };
        this.currentBid = Number(ap.current_price) || Number(ap.base_price) || 0;
        if (ap.current_bid_team) {
            this.winningTeamName = ap.current_bid_team.name;
            this.winningTeamId = ap.current_bid_team.id;
        } else {
            this.winningTeamName = "Waiting for bids...";
            this.winningTeamId = null;
        }
        this.state = "bidding";
    },

    resetPlayer() {
        this.player = { id: null, name: "", image_url: "", base_price: 0, role: "", batting_style: "", bowling_style: "", is_wicket_keeper: false };
    },

    get canBid() {
        if (this.state !== "bidding" || this.isSubmitting) return false;
        if (this.winningTeamId === this.userTeam?.id) return false;
        if (this.nextBidAmount > this.teamBudget) return false;
        return true;
    },

    get nextBidAmount() {
        return Number(this.currentBid) + this.getIncrement(Number(this.currentBid));
    },

    get bidButtonText() {
        if (this.isSubmitting) return "Placing Bid...";
        if (this.winningTeamId === this.userTeam?.id) return "✓ You are winning!";
        if (this.nextBidAmount > this.teamBudget) return "Insufficient Budget";
        return "BID " + this.formatCurrency(this.nextBidAmount);
    },

    getIncrement(price) {
        const numPrice = Number(price) || 0;
        if (this.bidRules && this.bidRules.length > 0) {
            for (let i = 0; i < this.bidRules.length; i++) {
                const r = this.bidRules[i];
                const from = Number(r.from) || 0;
                const to = Number(r.to) || Infinity;
                const increment = Number(r.increment) || 100000;
                if (numPrice >= from && numPrice < to) return increment;
            }
        }
        if (numPrice < 1000000) return 100000;
        if (numPrice < 5000000) return 500000;
        if (numPrice < 10000000) return 1000000;
        return 2500000;
    },

    async placeBid() {
        if (!this.canBid || !this.player.id) return;
        this.isSubmitting = true;
        this.bidError = "";
        try {
            const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" },
                body: JSON.stringify({ auction_player_id: this.player.id, amount: this.nextBidAmount })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || "Failed to place bid");
            this.currentBid = this.nextBidAmount;
            this.winningTeamName = this.userTeam.name;
            this.winningTeamId = this.userTeam.id;
        } catch (e) { this.bidError = e.message; }
        finally { this.isSubmitting = false; }
    },

    formatCurrency(amt) {
        const n = Number(amt) || 0;
        if (n >= 10000000) return (n / 10000000).toFixed(2) + " Cr";
        if (n >= 100000) return (n / 100000).toFixed(2) + " L";
        if (n >= 1000) return (n / 1000).toFixed(0) + "K";
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
                        <span class="text-green-400 font-bold text-sm" x-text="formatCurrency(sp.final_price)"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Center: Active Player -->
    <div class="flex-1 flex flex-col items-center justify-center p-8 relative">
        <!-- Header -->
        <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
            <div>
                @if(isset($isPreviewMode) && $isPreviewMode)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
                        <i class="fas fa-eye mr-2"></i> Admin Preview
                    </span>
                @endif
            </div>
            <div class="live-badge flex items-center gap-2">
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
            <p class="text-gray-500">Next player coming up...</p>
        </div>

        <!-- Bidding State -->
        <div x-show="state === 'bidding'" x-transition.opacity x-cloak class="w-full max-w-xl">
            <div class="player-card p-8">
                <!-- Base Price -->
                <div class="text-center mb-6">
                    <span class="inline-flex items-center px-5 py-2 rounded-full bg-white/10 text-white">
                        Base: <span class="font-bold text-yellow-400 ml-2" x-text="formatCurrency(player.base_price)"></span>
                    </span>
                </div>

                <!-- Player Avatar -->
                <div class="flex justify-center mb-6">
                    <div class="player-avatar-wrapper">
                        <template x-if="player.image_url && !player.image_url.includes('ui-avatars')">
                            <img :src="player.image_url" :alt="player.name">
                        </template>
                        <template x-if="!player.image_url || player.image_url.includes('ui-avatars')">
                            <span x-text="player.name?.substring(0,2).toUpperCase() || 'P'"></span>
                        </template>
                    </div>
                </div>

                <!-- Player Name & Info -->
                <h2 class="text-4xl font-bold text-white text-center mb-3" x-text="player.name"></h2>
                <div class="flex justify-center gap-2 mb-8">
                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs text-gray-300" x-text="player.role"></span>
                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs text-gray-300" x-text="player.batting_style"></span>
                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs text-gray-300" x-text="player.bowling_style"></span>
                </div>

                <!-- Current Bid -->
                <div class="bid-display text-center mb-6">
                    <p class="text-green-200 text-sm uppercase tracking-widest mb-2">Current Bid</p>
                    <p class="text-5xl font-bold text-white glow-green mb-2" x-text="formatCurrency(currentBid)"></p>
                    <p class="text-green-200" x-text="winningTeamName"></p>
                </div>

                <!-- Bid Button -->
                <button @click="placeBid()"
                        :disabled="!canBid"
                        class="bid-button w-full"
                        :class="{ 'winning': winningTeamId === userTeam?.id }">
                    <span x-text="bidButtonText"></span>
                </button>
                <p x-show="bidError" class="text-red-400 text-sm mt-3 text-center" x-text="bidError" x-cloak></p>
            </div>
        </div>

        <!-- Sold State -->
        <div x-show="state === 'sold'" x-transition.opacity x-cloak class="text-center sold-overlay">
            <div class="text-8xl mb-6">🎉</div>
            <h1 class="text-6xl font-extrabold text-green-400 glow-green mb-6">SOLD!</h1>
            <p class="text-3xl text-white mb-2">to <strong class="text-cyan-400" x-text="winningTeamName"></strong></p>
            <p class="text-2xl text-yellow-400 glow-yellow">for <strong x-text="formatCurrency(finalPrice)"></strong></p>
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
