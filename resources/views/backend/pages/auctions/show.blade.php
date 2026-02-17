@extends('backend.layouts.app')

@section('title', 'View Auction | ' . $auction->name)

@section('admin-content')
    <div class="p-4 mx-auto md:p-6 lg:p-8" x-data="auctionPlayerPool()" x-init="init(
        {{ $auction->id }},
        {{ json_encode($auction->auctionPlayers) }},
        {{ json_encode($teams) }},
        {{ json_encode($bidRules) }}
    )">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <div>
                @if(isset($isAdmin) && !$isAdmin && isset($userTeam))
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $userTeam->name }} - Acquired Players</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $auction->name }} &bull; {{ $auction->tournament->name ?? 'N/A' }}</p>
                @else
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $auction->name }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $auction->tournament->name ?? 'N/A' }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                {{-- Team Manager: Show bidding page link --}}
                @if (!isset($isAdmin) || !$isAdmin)
                    <a href="{{ route('team.auction.bidding.show', $auction) }}"
                        class="btn btn-primary inline-flex items-center gap-2">
                        <i class="fas fa-gavel"></i>
                        Join Live Bidding
                    </a>
                @else
                    {{-- Admin: Show all options --}}
                    <a href="{{ route('team.auction.bidding.show', $auction) }}"
                        class="btn btn-info inline-flex items-center gap-2">
                        <i class="fas fa-eye"></i>
                        Preview Bidding Page
                    </a>
                    <a href="{{ route('admin.auctions.edit', $auction) }}" class="btn btn-secondary">Edit Configuration</a>
                    <a href="{{ route('admin.auction.organizer.panel', $auction) }}"
                        class="btn btn-success inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                        </svg>
                        Go to Live Panel
                    </a>
                @endif
            </div>
        </div>

        {{-- Statistics Bar --}}
        @if(isset($isAdmin) && $isAdmin)
            {{-- Admin view: Full statistics --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="soldCount">0</div>
                    <div class="text-sm uppercase tracking-wide">Sold</div>
                </div>
                <div class="bg-red-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="unsoldCount">0</div>
                    <div class="text-sm uppercase tracking-wide">Unsold</div>
                </div>
                <div class="bg-blue-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="availableCount">0</div>
                    <div class="text-sm uppercase tracking-wide">Available</div>
                </div>
                <div class="bg-purple-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="players.length">0</div>
                    <div class="text-sm uppercase tracking-wide">Total Pool</div>
                </div>
            </div>
        @else
            {{-- Team Manager view: Team summary --}}
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-green-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="players.length">0</div>
                    <div class="text-sm uppercase tracking-wide">Players Acquired</div>
                </div>
                <div class="bg-blue-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="formatCurrency(totalSpent)">0</div>
                    <div class="text-sm uppercase tracking-wide">Total Spent</div>
                </div>
                <div class="bg-purple-500 text-white p-4 rounded-lg text-center shadow-lg">
                    <div class="text-3xl font-bold" x-text="formatCurrency({{ $auction->max_budget_per_team ?? 0 }} - totalSpent)">0</div>
                    <div class="text-sm uppercase tracking-wide">Remaining Budget</div>
                </div>
            </div>
        @endif

        {{-- Filters --}}
        @if(isset($isAdmin) && $isAdmin)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                    {{-- Search --}}
                    <div class="w-full md:w-1/3">
                        <input type="text" x-model="searchQuery" placeholder="Search player name..."
                            class="form-control w-full">
                    </div>

                    {{-- Status Filter --}}
                    <div class="flex gap-2 flex-wrap">
                        <button @click="statusFilter = ''"
                            :class="statusFilter === '' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition">
                            All
                        </button>
                        <button @click="statusFilter = 'sold'"
                            :class="statusFilter === 'sold' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition">
                            Sold
                        </button>
                        <button @click="statusFilter = 'unsold'"
                            :class="statusFilter === 'unsold' ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition">
                            Unsold
                        </button>
                        <button @click="statusFilter = 'on_auction'"
                            :class="statusFilter === 'on_auction' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition">
                            On Auction
                        </button>
                        <button @click="statusFilter = 'waiting'"
                            :class="statusFilter === 'waiting' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-4 py-2 rounded-full text-sm font-medium transition">
                            Waiting
                        </button>
                    </div>

                    {{-- Team Filter --}}
                    <div class="w-full md:w-1/4">
                        <select x-model="teamFilter" class="form-control w-full">
                            <option value="">All Teams</option>
                            <template x-for="team in teams" :key="team.id">
                                <option :value="team.id" x-text="team.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                @can('auctions.edit')
                    <div class="mt-4 flex justify-end">
                        <form action="{{ route('admin.auctions.clear-pool', $auction) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to remove ALL players from this auction? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Clear Entire Pool</button>
                        </form>
                    </div>
                @endcan
            </div>
        @else
            {{-- Simple search for team managers --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
                <div class="w-full md:w-1/2">
                    <input type="text" x-model="searchQuery" placeholder="Search your players..."
                        class="form-control w-full">
                </div>
            </div>
        @endif

        {{-- Player Cards Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <template x-for="player in filteredPlayers" :key="player.id">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border-2 transition-all duration-300"
                    :class="{
                        'border-green-500 bg-green-50 dark:bg-green-900/20': player.status === 'sold',
                        'border-red-500 bg-red-50 dark:bg-red-900/20': player.status === 'unsold',
                        'border-blue-500 bg-blue-50 dark:bg-blue-900/20 animate-pulse': player.status === 'on_auction',
                        'border-gray-300 dark:border-gray-600': player.status === 'waiting'
                    }">

                    {{-- Player Image --}}
                    <div class="relative">
                        <img :src="player.player.image_path ? `/storage/${player.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.player.name)}&size=200&background=random`"
                            class="w-full h-40 object-cover object-top"
                            :alt="player.player.name">

                        {{-- Status Badge --}}
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 text-xs font-bold rounded-full uppercase"
                                :class="{
                                    'bg-green-500 text-white': player.status === 'sold',
                                    'bg-red-500 text-white': player.status === 'unsold',
                                    'bg-blue-500 text-white': player.status === 'on_auction',
                                    'bg-gray-500 text-white': player.status === 'waiting'
                                }"
                                x-text="player.status === 'on_auction' ? 'LIVE' : player.status.toUpperCase()">
                            </span>
                        </div>

                        {{-- Base Price Badge --}}
                        <div class="absolute bottom-2 left-2">
                            <span class="px-2 py-1 text-xs font-bold rounded bg-black/70 text-white">
                                Base: <span x-text="formatCurrency(player.base_price)"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Player Info --}}
                    <div class="p-3">
                        <h3 class="font-bold text-gray-900 dark:text-white text-sm truncate" x-text="player.player.name"></h3>

                        {{-- Player Type / Role --}}
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1"
                           x-text="player.player.player_type?.name || 'Player'"></p>

                        {{-- Batting & Bowling Style --}}
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-0.5">
                            <p x-show="player.player.batting_profile?.name">
                                <span class="font-medium">Bat:</span> <span x-text="player.player.batting_profile?.name"></span>
                            </p>
                            <p x-show="player.player.bowling_profile?.name">
                                <span class="font-medium">Bowl:</span> <span x-text="player.player.bowling_profile?.name"></span>
                            </p>
                        </div>

                        {{-- Divider --}}
                        <hr class="my-2 border-gray-200 dark:border-gray-700">

                        {{-- Sold Info or Current Price --}}
                        <template x-if="player.status === 'sold'">
                            <div class="flex items-center gap-2">
                                <template x-if="player.sold_to_team?.logo_path">
                                    <img :src="`/storage/${player.sold_to_team.logo_path}`"
                                         class="w-8 h-8 rounded-full object-cover border-2 border-green-500"
                                         :alt="player.sold_to_team?.name">
                                </template>
                                <template x-if="!player.sold_to_team?.logo_path">
                                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center text-white text-xs font-bold"
                                         x-text="player.sold_to_team?.name?.charAt(0) || 'T'"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate" x-text="player.sold_to_team?.name || 'Team'"></p>
                                    <p class="text-sm font-bold text-green-600" x-text="formatCurrency(player.final_price || player.current_price)"></p>
                                </div>
                            </div>
                        </template>

                        <template x-if="player.status === 'unsold'">
                            <div class="flex items-center gap-2 text-red-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                                <span class="text-sm font-bold">UNSOLD</span>
                            </div>
                        </template>

                        <template x-if="player.status === 'on_auction' || player.status === 'waiting'">
                            <div>
                                <p class="text-xs text-gray-500">Current Bid</p>
                                <p class="text-lg font-bold text-blue-600" x-text="formatCurrency(player.current_price)"></p>
                            </div>
                        </template>

                        {{-- Admin Actions --}}
                        @can('auctions.edit')
                            <template x-if="player.status !== 'sold'">
                                <div class="mt-3 space-y-2">
                                    {{-- Bid Controls --}}
                                    <div class="flex items-center justify-between gap-1">
                                        <button @click="decreaseBid(player)"
                                            class="flex-1 px-2 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition">
                                            -
                                        </button>
                                        <span class="flex-1 text-center text-xs font-medium" x-text="formatCurrency(player.current_price)"></span>
                                        <button @click="increaseBid(player)"
                                            class="flex-1 px-2 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition"
                                            :disabled="player.current_price >= player.max_price">
                                            +
                                        </button>
                                    </div>

                                    {{-- Team Assignment --}}
                                    <div class="flex gap-1">
                                        <select x-model="player.selectedTeamId" class="form-control form-control-sm flex-1 text-xs">
                                            <option value="">Select Team</option>
                                            <template x-for="team in teams" :key="team.id">
                                                <option :value="team.id" x-text="team.name"></option>
                                            </template>
                                        </select>
                                        <button @click="assignToTeam(player)"
                                            class="px-2 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600 transition"
                                            :disabled="!player.selectedTeamId">
                                            Sell
                                        </button>
                                    </div>

                                    {{-- Status Change --}}
                                    <select x-model="player.status" @change="toggleStatus(player)"
                                        class="form-control form-control-sm w-full text-xs">
                                        <option value="on_auction">On Auction</option>
                                        <option value="unsold">Unsold</option>
                                        <option value="waiting">Waiting</option>
                                    </select>
                                </div>
                            </template>

                            {{-- Remove Button --}}
                            <div class="mt-2">
                                <button @click="removePlayer(player.id, filteredPlayers.indexOf(player))"
                                    class="w-full px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded text-xs hover:bg-red-100 hover:text-red-500 transition">
                                    Remove
                                </button>
                            </div>
                        @endcan
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty State --}}
        <div x-show="filteredPlayers.length === 0" x-cloak
            class="text-center py-20 bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            @if(isset($isAdmin) && $isAdmin)
                <p class="text-gray-500 dark:text-gray-400 text-lg">No players found</p>
                @can('auctions.edit')
                    <a href="{{ route('admin.auctions.edit', $auction) }}" class="text-blue-500 underline mt-2 inline-block">Add players to auction</a>
                @endcan
            @else
                <p class="text-gray-500 dark:text-gray-400 text-lg">No players acquired yet</p>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Players you win in the auction will appear here</p>
                <a href="{{ route('team.auction.bidding.show', $auction) }}" class="btn btn-primary mt-4">
                    <i class="fas fa-gavel mr-2"></i>Join Live Bidding
                </a>
            @endif
        </div>
    </div>

    <script>
        function auctionPlayerPool() {
            return {
                auctionId: null,
                players: [],
                teams: [],
                bidRules: [],
                searchQuery: '',
                statusFilter: '',
                teamFilter: '',

                init(auctionId, initialPlayers, initialTeams, initialBidRules) {
                    this.auctionId = auctionId;
                    this.players = initialPlayers.map(p => ({
                        ...p,
                        selectedTeamId: p.selectedTeamId || null
                    }));
                    this.teams = initialTeams;
                    this.bidRules = initialBidRules;

                    this.sortPlayers();
                    this.connectToEcho();
                },

                // Statistics computed properties
                get soldCount() {
                    return this.players.filter(p => p.status === 'sold').length;
                },
                get unsoldCount() {
                    return this.players.filter(p => p.status === 'unsold').length;
                },
                get availableCount() {
                    return this.players.filter(p => ['waiting', 'on_auction'].includes(p.status)).length;
                },
                get totalSpent() {
                    return this.players.reduce((sum, p) => sum + (Number(p.final_price) || Number(p.current_price) || 0), 0);
                },

                get filteredPlayers() {
                    return this.players.filter(p => {
                        const matchesSearch = this.searchQuery === '' ||
                            p.player.name.toLowerCase().includes(this.searchQuery.toLowerCase());

                        const matchesStatus = this.statusFilter === '' || p.status === this.statusFilter;

                        const matchesTeam = this.teamFilter === '' ||
                            (p.sold_to_team && p.sold_to_team.id == this.teamFilter);

                        return matchesSearch && matchesStatus && matchesTeam;
                    });
                },

                connectToEcho() {
                    const connect = () => {
                        if (window.Echo) {
                            window.Echo.private(`auction.${this.auctionId}`)
                                .listen('.player.onbid', e => {
                                    const player = this.players.find(p => p.id === e.auctionPlayer.id);
                                    if (player) player.current_price = e.auctionPlayer.current_price;
                                })
                                .listen('.player.sold', e => {
                                    const player = this.players.find(p => p.id === e.auctionPlayer.id);
                                    if (player) {
                                        player.status = 'sold';
                                        player.sold_to_team = e.auctionPlayer.sold_to_team;
                                        player.final_price = e.auctionPlayer.final_price;
                                        this.sortPlayers();
                                    }
                                })
                                .listen('.player.added', e => {
                                    const exists = this.players.find(p => p.id === e.auctionPlayer.id);
                                    if (!exists) this.players.push({
                                        ...e.auctionPlayer,
                                        selectedTeamId: null
                                    });
                                    this.sortPlayers();
                                })
                                .listen('.player.removed', e => {
                                    this.players = this.players.filter(p => p.id !== e.auctionPlayer.id);
                                })
                                .listen('.player.statusUpdated', e => {
                                    const player = this.players.find(p => p.id === e.auctionPlayer.id);
                                    if (player) player.status = e.auctionPlayer.status;
                                });
                        } else {
                            setTimeout(connect, 100);
                        }
                    };
                    connect();
                },

                sortPlayers() {
                    this.players.sort((a, b) => {
                        if (a.status === 'on_auction' && b.status !== 'on_auction') return -1;
                        if (a.status !== 'on_auction' && b.status === 'on_auction') return 1;
                        return new Date(b.updated_at) - new Date(a.updated_at);
                    });
                },

                async removePlayer(auctionPlayerId, index) {
                    if (!confirm('Are you sure you want to remove this player from the pool?')) return;
                    try {
                        const response = await fetch(`/admin/auctions/remove-player/${auctionPlayerId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.players = this.players.filter(p => p.id !== auctionPlayerId);
                        } else {
                            alert(data.message || 'Failed to remove player.');
                        }
                    } catch (error) {
                        alert('An error occurred while trying to remove the player.');
                    }
                },

                getBidIncrement(price) {
                    const current = Number(price) || 0;
                    if (!Array.isArray(this.bidRules) || this.bidRules.length === 0) return 0;
                    const rule = this.bidRules.find(r => current >= Number(r.from) && current < Number(r.to));
                    return rule ? Number(rule.increment) || 0 : 0;
                },

                async increaseBid(player) {
                    try {
                        const res = await fetch(`/admin/auctions/add-bid`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                auctionId: this.auctionId,
                                playerID: player.id,
                                teamId: player.selectedTeamId
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            player.current_price = data.current_price;
                        } else {
                            alert(data.message || 'Failed to add bid.');
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Network error while adding bid.');
                    }
                },

                async decreaseBid(player) {
                    try {
                        const res = await fetch(`/admin/auctions/decrease-bid`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                auctionId: this.auctionId,
                                playerID: player.id,
                                teamId: player.selectedTeamId
                            })
                        });
                        const data = await res.json();
                        if (data.success) player.current_price = data.current_price;
                        else alert(data.message || 'Failed to decrease bid.');
                    } catch (e) {
                        console.error(e);
                        alert('Network error while decreasing bid.');
                    }
                },

                async assignToTeam(player) {
                    if (!player.selectedTeamId) {
                        alert('Please select a team first.');
                        return;
                    }

                    try {
                        const res = await fetch(`/admin/auctions/assign-player`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                auction_player_id: player.id,
                                team_id: player.selectedTeamId
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            player.status = 'sold';
                            player.sold_to_team = this.teams.find(t => t.id == player.selectedTeamId);
                            player.final_price = player.current_price;
                            this.sortPlayers();
                        } else {
                            alert(data.message || 'Failed to assign player.');
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Network error while assigning player.');
                    }
                },

                async toggleStatus(player) {
                    try {
                        const res = await fetch(`/admin/auction/{{ $auction->id }}/player/${player.id}/toggle-status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ status: player.status })
                        });
                        const data = await res.json();
                        if (!data.success) {
                            alert('Failed to update status.');
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Error updating status.');
                    }
                },

                formatCurrency(points) {
                    points = Number(points) || 0;
                    const isNegative = points < 0;
                    const absPoints = Math.abs(points);
                    let formattedValue;
                    if (absPoints >= 1000000) formattedValue = (absPoints / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
                    else if (absPoints >= 1000) formattedValue = (absPoints / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                    else formattedValue = new Intl.NumberFormat('en-US').format(absPoints);
                    return `${isNegative ? '-' : ''}${formattedValue}`;
                }
            }
        }
    </script>
@endsection
