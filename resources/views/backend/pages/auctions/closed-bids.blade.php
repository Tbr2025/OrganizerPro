@extends('backend.layouts.app')

@section('title', 'Closed Bids | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8" x-data="closedBidDashboard()" x-init="init()">
        <h1 class="text-2xl font-bold mb-4">Closed Bids</h1>

        {{-- Filters --}}
        <div class="flex gap-4 mb-4">

            <select x-model="filterAuction" @change="fetchBids()" class="form-control">
                <option value="">All Auctions</option>
                @foreach ($auctions as $auction)
                    <option value="{{ $auction->id }}">{{ $auction->name }}</option>
                @endforeach
            </select>

            <select x-model="filterTeam" @change="fetchBids()" class="form-control">
                <option value="">All Teams</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>

        </div>

        {{-- Table --}}
        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow-md border">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="p-3 text-left">Auction</th>
                        <th class="p-3 text-left">Player</th>
                        <th class="p-3 text-left">Bids</th>
                        <th class="p-3 text-left">Team</th>
                        <th class="p-3 text-left">Final Price</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <template x-for="bid in bids" :key="bid.id">
                        <tr>
                            <td class="p-3" x-text="bid.auction?.name ?? 'N/A'"></td>
                            <td class="p-3" x-text="bid.player?.name ?? 'N/A'"></td>
                            <td class="p-3">
                                <template x-if="bid.bids && bid.bids.length > 0">
                                    <div class="space-y-1">
                                        <template x-for="b in bid.bids" :key="b.id">
                                            <div class="flex justify-between items-center text-xs bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                                <span class="text-gray-600 dark:text-gray-300" x-text="b.team?.name ?? 'N/A'"></span>
                                                <span class="font-bold text-green-600" x-text="formatPoints(b.amount / 1000000)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!bid.bids || bid.bids.length === 0">
                                    <span class="text-gray-400 text-xs">No bids</span>
                                </template>
                            </td>
                            <td class="p-3" x-text="bid.sold_to_team?.name ?? 'N/A'"></td>
                            <td class="p-3 font-semibold">
                                <template x-if="bid.canEdit">
                                    <input type="number" class="form-control" x-model.number="bid.final_price_display"
                                        step="0.1" min="0" @change="updateFinalPrice(bid)">
                                </template>

                                <template x-if="!bid.canEdit">
                                    <span x-text="formatPoints(bid.final_price_display)"></span>
                                </template>
                            </td>

                            <td class="p-3">
                                <span class="badge badge-dark" x-text="capitalize(bid.status)"></span>
                            </td>
                            <td class="p-3">
                                <template x-if="bid.status === 'closed' && bid.canEdit">
                                    <div class="flex gap-1">
                                        <select x-model="bid.sellToTeamId" class="form-control form-control-sm text-xs">
                                            <option value="">Select Team</option>
                                            <template x-if="bid.bids && bid.bids.length > 0">
                                                <template x-for="b in bid.bids" :key="b.team_id || b.team?.id">
                                                    <option :value="b.team_id || b.team?.id" x-text="(b.team?.name ?? 'Team') + ' (' + formatPoints(b.amount / 1000000) + ')'"></option>
                                                </template>
                                            </template>
                                        </select>
                                        <button @click="sellToTeam(bid)"
                                            :disabled="!bid.sellToTeamId"
                                            class="px-2 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600 disabled:opacity-50 transition">
                                            Sell
                                        </button>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="bids.length === 0" x-cloak>
                        <td colspan="7" class="text-center py-6 text-gray-500">No closed bids found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function closedBidDashboard() {
            return {
                bids: [],
                auctions: [],
                teams: [],
                filterAuction: '',
                filterTeam: '',

                // User can edit if they have any of these roles
                canEditRole: @json(auth()->user()->hasAnyRole(['Team Manager', 'Admin', 'Superadmin'])),
                userTeamId: @json(auth()->user()->team_id ?? null),

                init() {
                    this.fetchBids();
                    setInterval(() => this.fetchBids(), 5000);
                },

                fetchBids() {
                    fetch(`/admin/auctions-closed-bids/fetch`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            this.bids = data.closedBids.map(bid => {
                                bid.canEdit = this.canEditRole &&
                                    (this.userTeamId ? bid.sold_to_team?.id === this.userTeamId : true);
                                bid.final_price_display = (bid.final_price / 1000000).toFixed(1);
                                bid.sellToTeamId = bid.sellToTeamId || '';
                                return bid;
                            });

                            this.auctions = data.auctions ?? [];
                            this.teams = data.teams ?? [];
                        });
                },

                updateFinalPrice(bid) {
                    let newPrice = bid.final_price_display * 1000000;

                    fetch(`/admin/auction/${bid.auction_id}/player/${bid.id}/final-price`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                final_price: newPrice
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                bid.final_price = data.final_price;
                                bid.final_price_display = (data.final_price / 1000000).toFixed(1);
                            } else {
                                alert(data.error || 'Insufficient balance');
                                bid.final_price_display = (bid.final_price / 1000000).toFixed(1);
                            }
                        });
                },

                async sellToTeam(bid) {
                    if (!bid.sellToTeamId) return;
                    const selectedBid = (bid.bids || []).find(b => (b.team_id || b.team?.id) == bid.sellToTeamId);
                    const amount = selectedBid ? selectedBid.amount : bid.final_price;

                    if (!confirm(`Sell ${bid.player?.name} to the selected team for ${this.formatPoints(amount / 1000000)}?`)) return;

                    try {
                        const res = await fetch(`/admin/organizer/auction/${bid.auction_id}/api/sell-to-team`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                auction_player_id: bid.id,
                                team_id: bid.sellToTeamId,
                                amount: amount
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.fetchBids();
                            alert(data.message || 'Player sold successfully!');
                        } else {
                            alert(data.message || 'Failed to sell player.');
                        }
                    } catch (e) {
                        alert('Error selling player: ' + e.message);
                    }
                },

                formatPoints(points) {
                    points = Number(points) || 0;
                    if (points >= 1000000) return (points / 1000000).toFixed(1) + 'M';
                    if (points >= 1000) return (points / 1000).toFixed(1) + 'K';
                    return points + ' Points';
                },

                capitalize(str) {
                    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
                }
            }
        }
    </script>

@endsection
