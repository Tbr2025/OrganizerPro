@extends('backend.layouts.app')

@section('title', 'Auction Report | ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto md:p-6 lg:p-8" x-data="auctionReport()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Auction Report</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $auction->name }} &bull; {{ $auction->tournament->name ?? 'N/A' }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.auctions.show', $auction) }}" class="btn btn-secondary inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Auction
            </a>
            <button onclick="window.print()" class="btn btn-primary inline-flex items-center gap-2">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    {{-- Summary Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="bg-green-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold">{{ $summary['sold_count'] }}</div>
            <div class="text-xs uppercase tracking-wide">Sold</div>
        </div>
        <div class="bg-red-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold">{{ $summary['unsold_count'] }}</div>
            <div class="text-xs uppercase tracking-wide">Unsold</div>
        </div>
        <div class="bg-blue-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold">{{ $summary['total_bids'] }}</div>
            <div class="text-xs uppercase tracking-wide">Total Bids</div>
        </div>
        <div class="bg-purple-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold">{{ $summary['total_players'] }}</div>
            <div class="text-xs uppercase tracking-wide">Total Players</div>
        </div>
        <div class="bg-indigo-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold" x-text="formatCurrency({{ $summary['total_revenue'] }})"></div>
            <div class="text-xs uppercase tracking-wide">Revenue</div>
        </div>
        <div class="bg-amber-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold" x-text="formatCurrency({{ $summary['highest_sale'] ?? 0 }})"></div>
            <div class="text-xs uppercase tracking-wide">Highest Sale</div>
        </div>
        <div class="bg-teal-500 text-white p-4 rounded-lg text-center shadow-lg">
            <div class="text-2xl font-bold" x-text="formatCurrency({{ round($summary['avg_price']) }})"></div>
            <div class="text-xs uppercase tracking-wide">Avg Price</div>
        </div>
    </div>

    {{-- Most Expensive Player --}}
    @if($summary['most_expensive_player'])
        @php $mep = $summary['most_expensive_player']; @endphp
        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 mb-6 flex items-center gap-4">
            <div class="flex-shrink-0">
                <img src="{{ $mep->player->image_path ? asset('storage/' . $mep->player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($mep->player->name) . '&size=80&background=random' }}"
                     class="w-16 h-16 rounded-full object-cover border-2 border-amber-400" alt="{{ $mep->player->name }}">
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-amber-600 dark:text-amber-400 font-semibold">Most Expensive Player</div>
                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $mep->player->name }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    Sold to <span class="font-semibold">{{ $mep->soldToTeam->name ?? 'N/A' }}</span>
                    for <span class="font-bold text-amber-600" x-text="formatCurrency({{ $mep->final_price }})"></span>
                </div>
            </div>
        </div>
    @endif

    {{-- Legend --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6 flex flex-wrap items-center gap-4 text-sm">
        <span class="font-semibold text-gray-700 dark:text-gray-300">Legend:</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-4 rounded bg-yellow-100 border border-yellow-400"></span>
            <span class="text-gray-600 dark:text-gray-400">Tie Bid</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-4 rounded bg-red-100 border border-red-400"></span>
            <span class="text-gray-600 dark:text-gray-400">Close-time Bid (&le;2s)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-4 rounded bg-orange-100 border border-orange-400"></span>
            <span class="text-gray-600 dark:text-gray-400">Both (Tie + Close)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="px-1.5 py-0.5 text-xs rounded bg-green-100 text-green-700 border border-green-300">online</span>
            <span class="text-gray-600 dark:text-gray-400">Online Bid</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="px-1.5 py-0.5 text-xs rounded bg-gray-200 text-gray-700 border border-gray-300">offline</span>
            <span class="text-gray-600 dark:text-gray-400">Offline Bid</span>
        </span>
    </div>

    {{-- Tabs --}}
    <div class="mb-6">
        <div class="flex border-b border-gray-200 dark:border-gray-700">
            <button @click="activeTab = 'auction'"
                :class="activeTab === 'auction' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition">
                Auction Timeline
            </button>
            <button @click="activeTab = 'team'"
                :class="activeTab === 'team' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                class="px-4 py-3 text-sm font-medium border-b-2 transition">
                Team Breakdown
            </button>
        </div>
    </div>

    {{-- ==================== TAB 1: Auction Timeline ==================== --}}
    <div x-show="activeTab === 'auction'" x-cloak>

        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center">
                <div class="w-full md:w-1/3">
                    <input type="text" x-model="searchQuery" placeholder="Search player name..."
                        class="form-control w-full">
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button @click="statusFilter = ''"
                        :class="statusFilter === '' ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-800' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-sm font-medium transition">All</button>
                    <button @click="statusFilter = 'sold'"
                        :class="statusFilter === 'sold' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-sm font-medium transition">Sold</button>
                    <button @click="statusFilter = 'unsold'"
                        :class="statusFilter === 'unsold' ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-full text-sm font-medium transition">Unsold</button>
                </div>
                <div class="w-full md:w-1/4">
                    <select x-model="teamFilter" class="form-control w-full">
                        <option value="">All Teams</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Per-player collapsible sections --}}
        @foreach($auction->auctionPlayers->sortBy(fn($ap) => $ap->bids->min('created_at') ?? now()) as $ap)
            @php
                $bids = $playerBidData[$ap->id] ?? [];
                $isSold = $ap->status === 'sold';
                $statusLabel = $isSold ? 'SOLD' : strtoupper($ap->status);
                $statusColor = $isSold ? 'green' : ($ap->status === 'unsold' ? 'red' : 'gray');
                $playerRole = $ap->player->playerType->type ?? 'Player';
                $soldTeamId = $ap->sold_to_team_id;
            @endphp

            <div class="mb-4"
                 x-show="matchesFilter('{{ addslashes($ap->player->name) }}', '{{ $ap->status }}', '{{ $soldTeamId }}', {{ json_encode(collect($bids)->pluck('team_id')->unique()->filter()->values()) }})"
                 x-data="{ open: false }">

                {{-- Player Header --}}
                <div @click="open = !open"
                     class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-750 transition flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="{{ $ap->player->image_path ? asset('storage/' . $ap->player->image_path) : 'https://ui-avatars.com/api/?name=' . urlencode($ap->player->name) . '&size=40&background=random' }}"
                             class="w-10 h-10 rounded-full object-cover" alt="{{ $ap->player->name }}">
                        <div>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $ap->player->name }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">{{ $playerRole }}</span>
                        </div>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full text-white bg-{{ $statusColor }}-500">{{ $statusLabel }}</span>
                        @if($isSold && $ap->soldToTeam)
                            <span class="text-sm text-gray-600 dark:text-gray-300">
                                &rarr; {{ $ap->soldToTeam->name }} for <span class="font-bold" x-text="formatCurrency({{ $ap->final_price }})"></span>
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Base: <span x-text="formatCurrency({{ $ap->base_price }})"></span>
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($bids) }} bid(s)</span>
                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>

                {{-- Bids Table --}}
                <div x-show="open" x-transition x-cloak class="mt-1">
                    @if(count($bids) > 0)
                        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-b-lg shadow-md">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Team</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bidder</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Timestamp</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Gap</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($bids as $i => $bid)
                                        @php
                                            $isTie = in_array($bid['id'], $tieBidIds);
                                            $isClose = in_array($bid['id'], $closeBidIds);
                                            $isBoth = $isTie && $isClose;

                                            if ($isBoth) {
                                                $rowClass = 'bg-orange-50 dark:bg-orange-900/20';
                                            } elseif ($isTie) {
                                                $rowClass = 'bg-yellow-50 dark:bg-yellow-900/20';
                                            } elseif ($isClose) {
                                                $rowClass = 'bg-red-50 dark:bg-red-900/20';
                                            } else {
                                                $rowClass = '';
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $i + 1 }}</td>
                                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $bid['team_name'] }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $bid['user_name'] }}</td>
                                            <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                                <span x-text="formatCurrency({{ $bid['amount'] }})"></span>
                                                @if($isTie)
                                                    <span class="ml-1 px-1 py-0.5 text-[10px] font-bold rounded bg-yellow-200 text-yellow-800">TIE</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($bid['bid_source'] === 'online')
                                                    <span class="px-1.5 py-0.5 text-xs rounded bg-green-100 text-green-700 border border-green-300">online</span>
                                                @elseif($bid['bid_source'] === 'offline')
                                                    <span class="px-1.5 py-0.5 text-xs rounded bg-gray-200 text-gray-700 border border-gray-300">offline</span>
                                                @else
                                                    <span class="text-xs text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $bid['created_at'] }}</td>
                                            <td class="px-3 py-2 text-right">
                                                @if($bid['gap'] !== null)
                                                    <span class="{{ $bid['gap'] <= 2 ? 'text-red-600 font-bold' : 'text-gray-500 dark:text-gray-400' }}">
                                                        +{{ $bid['gap'] }}s
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="bg-white dark:bg-gray-800 rounded-b-lg shadow-md p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No bids recorded for this player.
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Empty state --}}
        @if($auction->auctionPlayers->isEmpty())
            <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                <p class="text-lg">No players in this auction.</p>
            </div>
        @endif
    </div>

    {{-- ==================== TAB 2: Team Breakdown ==================== --}}
    <div x-show="activeTab === 'team'" x-cloak>
        @foreach($teamSummaries as $ts)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md mb-6 overflow-hidden">
                {{-- Team Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        @if($ts['team']->logo_path)
                            <img src="{{ asset('storage/' . $ts['team']->logo_path) }}"
                                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-300" alt="{{ $ts['team']->name }}">
                        @else
                            <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white text-lg font-bold">
                                {{ substr($ts['team']->name, 0, 1) }}
                            </div>
                        @endif
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $ts['team']->name }}</h3>
                            <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400 mt-1">
                                <span><strong>{{ $ts['players_bought'] }}</strong> players</span>
                                <span>Spent: <strong x-text="formatCurrency({{ $ts['total_spent'] }})"></strong></span>
                                <span>Remaining: <strong x-text="formatCurrency({{ $ts['remaining_budget'] }})"></strong></span>
                                <span>{{ $ts['total_bids'] }} bids</span>
                                <span>Avg: <strong x-text="formatCurrency({{ round($ts['avg_price']) }})"></strong></span>
                            </div>
                        </div>
                    </div>

                    {{-- Budget bar --}}
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Budget Utilization</span>
                            <span>{{ $ts['budget_utilization'] }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="h-2.5 rounded-full transition-all {{ $ts['budget_utilization'] > 90 ? 'bg-red-500' : ($ts['budget_utilization'] > 70 ? 'bg-amber-500' : 'bg-green-500') }}"
                                 style="width: {{ min($ts['budget_utilization'], 100) }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Acquired players table --}}
                @if($ts['acquired_players']->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Player</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Role</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Base Price</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sold Price</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Premium %</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bids</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($ts['acquired_players'] as $idx => $ap)
                                    @php
                                        $basePrice = (float) $ap->base_price;
                                        $finalPrice = (float) $ap->final_price;
                                        $premium = $basePrice > 0 ? round((($finalPrice - $basePrice) / $basePrice) * 100, 1) : 0;
                                        $bidCount = isset($playerBidData[$ap->id]) ? count($playerBidData[$ap->id]) : 0;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $idx + 1 }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $ap->player->name }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $ap->player->playerType->type ?? 'Player' }}</td>
                                        <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">
                                            <span x-text="formatCurrency({{ $basePrice }})"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right font-semibold text-gray-900 dark:text-white">
                                            <span x-text="formatCurrency({{ $finalPrice }})"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <span class="{{ $premium > 0 ? 'text-green-600' : ($premium < 0 ? 'text-red-600' : 'text-gray-500') }}">
                                                {{ $premium > 0 ? '+' : '' }}{{ $premium }}%
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ $bidCount }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <td colspan="4" class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">Total Spent:</td>
                                    <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-white">
                                        <span x-text="formatCurrency({{ $ts['total_spent'] }})"></span>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        No players acquired.
                    </div>
                @endif
            </div>
        @endforeach

        @if(empty($teamSummaries))
            <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                <p class="text-lg">No teams found for this tournament.</p>
            </div>
        @endif
    </div>
</div>

{{-- Print styles --}}
<style>
    @media print {
        nav, .sidebar, header, footer, button, a.btn, select, input { display: none !important; }
        [x-cloak] { display: block !important; }
        body { background: white !important; }
        .shadow-md, .shadow-lg { box-shadow: none !important; }
    }
</style>

<script>
    function auctionReport() {
        return {
            activeTab: 'auction',
            searchQuery: '',
            statusFilter: '',
            teamFilter: '',

            matchesFilter(playerName, status, soldTeamId, bidTeamIds) {
                const matchesSearch = this.searchQuery === '' ||
                    playerName.toLowerCase().includes(this.searchQuery.toLowerCase());

                let matchesStatus = true;
                if (this.statusFilter === 'sold') matchesStatus = status === 'sold';
                else if (this.statusFilter === 'unsold') matchesStatus = status !== 'sold';

                let matchesTeam = true;
                if (this.teamFilter !== '') {
                    const teamId = parseInt(this.teamFilter);
                    matchesTeam = parseInt(soldTeamId) === teamId || bidTeamIds.includes(teamId);
                }

                return matchesSearch && matchesStatus && matchesTeam;
            },

            formatCurrency(points) {
                points = Number(points) || 0;
                const isNegative = points < 0;
                const absPoints = Math.abs(points);
                let formattedValue;
                if (absPoints >= 10000000) formattedValue = (absPoints / 10000000).toFixed(2).replace(/\.00$/, '') + ' Cr';
                else if (absPoints >= 100000) formattedValue = (absPoints / 100000).toFixed(2).replace(/\.00$/, '') + ' L';
                else if (absPoints >= 1000) formattedValue = (absPoints / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                else formattedValue = new Intl.NumberFormat('en-IN').format(absPoints);
                return `${isNegative ? '-' : ''}${formattedValue}`;
            }
        }
    }
</script>
@endsection
