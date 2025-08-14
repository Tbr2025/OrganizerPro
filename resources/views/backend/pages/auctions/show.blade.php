@extends('backend.layouts.app')

@section('title', 'View Auction | ' . $auction->name)

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Auction Dashboard</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Overview for: <span
                        class="font-semibold">{{ $auction->name }}</span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.auctions.edit', $auction) }}" class="btn btn-secondary">Edit Configuration</a>
                <a href="{{ route('admin.auction.organizer.panel', $auction) }}"
                    class="btn btn-success inline-flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                    Go to Live Panel
                </a>
            </div>
        </div>

        {{-- Info Bar --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 text-center">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Status</div>
                <div class="mt-1 text-xl font-semibold"><span
                        class="badge-{{ $auction->status === 'running' ? 'success' : 'secondary' }}">{{ ucfirst($auction->status) }}</span>
                </div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Tournament</div>
                <div class="mt-1 text-xl font-semibold">{{ $auction->tournament->name ?? 'N/A' }}</div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Player Pool</div>
                <div class="mt-1 text-xl font-semibold">{{ $auction->auctionPlayers->count() }} Players</div>
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">Team Budget</div>
                <div class="mt-1 text-xl font-semibold">{{ number_format($auction->max_budget_per_team) }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border" x-data="auctionPlayerPool()" x-init="init({{ $auction->id }}, {{ json_encode($auction->auctionPlayers) }})">
            <div class="p-5 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Auction Player Pool</h2>
                <span class="text-sm font-medium text-gray-500">Total: <span x-text="players.length"></span></span>
                {{-- **NEW**: Clear Pool Button --}}
                @can('auctions.edit')
                    <form action="{{ route('admin.auctions.clear-pool', $auction) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to remove ALL players from this auction? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Clear Entire Pool</button>
                    </form>
                @endcan
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="p-3 text-left">Player</th>
                            <th class="p-3 text-left">Role</th>
                            <th class="p-3 text-left">Base Price</th>
                            <th class="p-3 text-left">Status</th>{{-- **NEW**: Actions Column --}}
                            @can('auctions.edit')
                                <th class="p-3 text-right">Action</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Alpine.js will render the rows here --}}
                        <template x-for="(player, index) in players" :key="player.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <img :src="player.player.image_path ? `/storage/${player.player.image_path}` :
                                            `https://ui-avatars.com/api/?name=${encodeURIComponent(player.player.name)}`"
                                            class="w-10 h-10 rounded-full object-cover">
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-white"
                                                x-text="player.player.name"></div>
                                            <div class="text-xs text-gray-500" x-text="player.player.email"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3"
                                    x-text="player.player.player_type ? player.player.player_type.type : 'N/A'"></td>
                                <td class="p-3 font-semibold" x-text="formatCurrency(player.base_price)"></td>
                                <td class="p-3">
                                    <span class="badge"
                                        :class="{
                                            'badge-success': player.status === 'sold',
                                            'badge-info': player.status === 'on_auction',
                                            'badge-secondary': player.status === 'waiting' || player
                                                .status === 'unsold'
                                        }"
                                        x-text="player.status.charAt(0).toUpperCase() + player.status.slice(1)"></span>
                                </td>
                                {{-- **NEW**: Individual Remove Button Cell --}}
                                @can('auctions.edit')
                                    <td class="p-3 text-right">
                                        <button @click="removePlayer(player.id, index)" class="text-red-500 hover:text-red-700"
                                            title="Remove Player">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <tr x-show="players.length === 0" x-cloak>
                            <td colspan="4" class="text-center py-10 text-gray-500">
                                No players have been added to this auction pool yet.
                                @can('auctions.edit')
                                    <a href="{{ route('admin.auctions.edit', $auction) }}" class="text-blue-500 underline">Add
                                        players now</a>.
                                @endcan
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Alpine.js component for real-time updates --}}
    <script>
        function auctionPlayerPool() {
            return {
                auctionId: null,
                players: [],

                init(auctionId, initialPlayers) {
                    this.auctionId = auctionId;
                    this.players = initialPlayers;

                    const connectToEcho = () => {
                        if (window.Echo) {
                            console.log(`Dashboard connecting to Echo on channel: auction.${this.auctionId}`);

                            window.Echo.private(`auction.${this.auctionId}`)
                                .listen('.player.onbid', (e) => this.updatePlayerStatus(e.auctionPlayer.id, 'running'))
                                .listen('.player.sold', (e) => {
                                    this.updatePlayerStatus(e.auctionPlayer.id, e.auctionPlayer.status);
                                    // Optional: Add logic to remove player from pool if you have a separate "available" pool
                                });

                        } else {
                            setTimeout(connectToEcho, 100);
                        }
                    };
                    connectToEcho();
                },

                updatePlayerStatus(auctionPlayerId, newStatus) {
                    let player = this.players.find(p => p.id === auctionPlayerId);
                    if (player) {
                        player.status = newStatus;
                    } else {
                        // This handles the case where a player is added to the pool by another admin
                        // and you want this screen to update. This is a more advanced feature.
                        console.log('Received update for a player not currently in the list.');
                        // You could make an API call here to fetch the new player and add them.
                    }
                },
                async removePlayer(auctionPlayerId, index) {
                    if (!confirm('Are you sure you want to remove this player from the pool?')) {
                        return;
                    }

                    try {
                        const response = await fetch(`/admin/auctions/remove-player/${auctionPlayerId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            }
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Remove the player from the Alpine.js array to instantly update the UI
                            this.players.splice(index, 1);
                            // You could show a success toast here
                        } else {
                            alert(data.message || 'Failed to remove player.');
                        }
                    } catch (error) {
                        alert('An error occurred while trying to remove the player.');
                    }
                },
                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        minimumFractionDigits: 0
                    }).format(amount || 0);
                }
            }
        }
    </script>
@endsection
