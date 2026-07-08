@extends('backend.layouts.app')

@section('title', 'View Auction | ' . $auction->name)

@section('admin-content')
    <div class="p-4 mx-auto md:p-6 lg:p-8">
        <x-breadcrumbs :breadcrumbs="['title' => $auction->name, 'items' => [['label' => 'Auctions', 'url' => route('admin.auctions.index')]]]" />
    </div>
    <div class="p-4 mx-auto md:p-6 lg:p-8" x-data="auctionPlayerPool()" x-init="init(
        {{ $auction->id }},
        {{ json_encode($auction->auctionPlayers) }},
        {{ json_encode($teams) }},
        {{ json_encode($bidRules) }}
    )">

        {{-- Header --}}
        @php
            $statusBadge = [
                'scheduled' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                'running'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                'paused'    => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                'completed' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            ];
        @endphp
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/50 shadow-sm p-5 mb-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                {{-- Title + meta --}}
                <div class="min-w-0">
                    @if(isset($isAdmin) && !$isAdmin && isset($userTeam))
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white truncate">{{ $userTeam->name }} — Acquired Players</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $auction->name }} &bull; {{ $auction->tournament->name ?? 'N/A' }}</p>
                    @else
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white truncate">{{ $auction->name }}</h1>
                            <span class="text-[11px] font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full {{ $statusBadge[$auction->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $auction->status }}</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $auction->tournament->name ?? 'N/A' }}
                            <span class="mx-1 text-gray-300 dark:text-gray-600">|</span> Base <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $auction->base_price) }}</span>
                            <span class="mx-1 text-gray-300 dark:text-gray-600">|</span> Budget/team <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $auction->max_budget_per_team) }}</span>
                        </p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    @if (!isset($isAdmin) || !$isAdmin)
                        <a href="{{ route('team.auction.bidding.show', $auction) }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">
                            <i class="fas fa-gavel"></i> Join Live Bidding
                        </a>
                    @else
                        {{-- Primary actions --}}
                        <a href="{{ route('admin.auction.organizer.panel', $auction) }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
                            Live Panel
                        </a>
                        <a href="{{ route('admin.auctions.pools.index', $auction) }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H5m14 14H5"/></svg>
                            Manage Pools
                        </a>

                        {{-- Secondary actions (outline) --}}
                        @php $ghost = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium'; @endphp
                        <a href="{{ route('admin.auctions.edit', $auction) }}" class="{{ $ghost }}">Edit config</a>
                        <a href="{{ route('admin.auctions.report', $auction) }}" class="{{ $ghost }}">Report</a>
                        <a href="{{ route('admin.auction.organizer.offline-panel', $auction) }}" target="_blank" class="{{ $ghost }}">Offline panel</a>
                        <a href="{{ route('public.auction.live', $auction) }}" target="_blank" class="{{ $ghost }}">LED wall</a>
                        <a href="{{ route('team.auction.bidding.show', $auction) }}" class="{{ $ghost }}">Preview bidding</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Statistics Bar --}}
        @if(isset($isAdmin) && $isAdmin)
            {{-- Admin view: Full statistics --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/50 p-4 shadow-sm">
                    <div class="absolute left-0 top-0 h-full w-1 bg-emerald-500"></div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="soldCount">0</div>
                    <div class="text-xs font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400 mt-1">Sold</div>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/50 p-4 shadow-sm">
                    <div class="absolute left-0 top-0 h-full w-1 bg-red-500"></div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="unsoldCount">0</div>
                    <div class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400 mt-1">Unsold</div>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/50 p-4 shadow-sm">
                    <div class="absolute left-0 top-0 h-full w-1 bg-blue-500"></div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="availableCount">0</div>
                    <div class="text-xs font-medium uppercase tracking-wide text-blue-600 dark:text-blue-400 mt-1">Available</div>
                </div>
                <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800/50 p-4 shadow-sm">
                    <div class="absolute left-0 top-0 h-full w-1 bg-indigo-500"></div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white" x-text="players.length">0</div>
                    <div class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400 mt-1">Total Pool</div>
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

        {{-- ════ Pools & Draw Order (Pool Control Center) ════ --}}
        @if(isset($isAdmin) && $isAdmin)
        @php
            $poolModeLabels = ['sequential' => 'Sequential', 'random' => 'Random', 'odd_even' => 'Odd-Even', 'manual' => 'Custom'];
            $statusChip = [
                'waiting' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                'on_auction' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                'sold' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                'unsold' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                'skipped' => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            ];
        @endphp
        <div class="mb-8" x-data="auctionPoolCenter({{ $auction->id }})" x-init="init()">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H5m14 14H5"/></svg>
                        Pools &amp; Draw Order
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag pools to reorder. Build &amp; assign players in the full pool manager.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.auctions.pools.index', $auction) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">Manage Pools</a>
                    <button @click="startAuction()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">Start auction</button>
                    <a href="{{ route('admin.auction.organizer.panel', $auction) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium">Live Panel</a>
                    <button @click="reAuctionRound()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">Re-auction round</button>
                </div>
            </div>

            <div id="poolList" class="space-y-4">
                @forelse($auction->pools as $pool)
                    @php
                        $biddable = $pool->players->where('is_retained', false)->sortBy('lot_number')->values();
                        $retained = $pool->players->where('is_retained', true)->values();
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4"
                         data-pool-id="{{ $pool->id }}" x-data="{ showRetained: false }">
                        <div class="flex items-center justify-between mb-3 gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="pool-drag cursor-move text-gray-400 select-none" title="Drag to reorder">⠿</span>
                                <span class="font-semibold text-gray-900 dark:text-white truncate">{{ $pool->name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $poolModeLabels[$pool->order_mode] ?? $pool->order_mode }}</span>
                                <span class="text-xs text-gray-400">{{ $biddable->count() }}{{ $pool->capacity ? '/'.$pool->capacity : '' }} players</span>
                            </div>
                            <button @click="redraw({{ $pool->id }})" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded px-2 py-1 hover:bg-gray-200">Re-draw lots</button>
                        </div>

                        <ol class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($biddable as $ap)
                                <li class="py-1.5 flex items-center justify-between gap-2">
                                    <span class="truncate">
                                        <span class="text-gray-400">{{ $ap->lot_number ? '#'.$ap->lot_number : '—' }}</span>
                                        {{ $ap->player->name ?? 'Player #'.$ap->player_id }}
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span class="text-[11px] px-2 py-0.5 rounded-full {{ $statusChip[$ap->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $ap->status }}</span>
                                        @if($ap->status === 'waiting')
                                            <button @click="skip({{ $ap->id }})" class="text-[11px] text-amber-600 hover:underline">Skip</button>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                            @if($biddable->isEmpty())
                                <li class="py-2 text-xs text-gray-400">No biddable players in this pool.</li>
                            @endif
                        </ol>

                        @if($retained->count())
                        <div class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-2">
                            <button type="button" @click="showRetained = !showRetained" class="text-xs font-medium text-purple-700 dark:text-purple-300">
                                <span x-text="showRetained ? '▾' : '▸'"></span> Retained ({{ $retained->count() }})
                            </button>
                            <div x-show="showRetained" x-cloak class="mt-2 space-y-1">
                                @foreach($retained as $ap)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="truncate">{{ $ap->player->name ?? 'Player #'.$ap->player_id }}</span>
                                        <span class="text-[11px] text-gray-400">{{ optional($ap->soldToTeam)->name ?? 'retained' }}</span>
                                    </div>
                                @endforeach
                                <button @click="mergeRetained({{ $pool->id }})" class="mt-2 text-xs bg-purple-600 hover:bg-purple-700 text-white rounded px-3 py-1">Merge into auction</button>
                            </div>
                        </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No pools configured.
                        <a href="{{ route('admin.auctions.pools.index', $auction) }}" class="text-indigo-600 underline">Manage pools</a>.</p>
                @endforelse
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

                        {{-- Bid Details (Admin only) --}}
                        @if(isset($isAdmin) && $isAdmin)
                        <template x-if="player.bids && player.bids.length > 0">
                            <div class="mt-2" x-data="{ showBids: false }">
                                <button @click="showBids = !showBids"
                                    class="text-xs text-blue-500 hover:text-blue-400 flex items-center gap-1">
                                    <svg class="w-3 h-3 transition-transform" :class="showBids ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <span x-text="player.bids.length + ' bid(s)'"></span>
                                </button>
                                <div x-show="showBids" x-transition class="mt-2 space-y-1 max-h-32 overflow-y-auto" x-cloak>
                                    <template x-for="bid in player.bids" :key="bid.id">
                                        <div class="flex justify-between items-center text-xs bg-gray-50 dark:bg-gray-700/50 rounded px-2 py-1">
                                            <span class="text-gray-600 dark:text-gray-300 truncate" x-text="bid.team?.name || 'N/A'"></span>
                                            <span class="font-bold text-green-600 dark:text-green-400" x-text="formatCurrency(bid.amount)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        @endif

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

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        function auctionPoolCenter(auctionId) {
            return {
                auctionId,
                token: document.querySelector('meta[name="csrf-token"]').content,
                init() {
                    const el = document.getElementById('poolList');
                    if (window.Sortable && el) {
                        window.Sortable.create(el, { handle: '.pool-drag', animation: 150, onEnd: () => this.saveOrder() });
                    }
                },
                _post(url, body) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.token, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: body ? JSON.stringify(body) : null,
                    }).then(r => r.json().catch(() => ({})));
                },
                saveOrder() {
                    const ids = [...document.querySelectorAll('#poolList [data-pool-id]')].map(e => parseInt(e.dataset.poolId));
                    this._post(`/admin/auctions/${this.auctionId}/pools/reorder`, { order: ids });
                },
                redraw(poolId) {
                    this._post(`/admin/auctions/${this.auctionId}/pools/${poolId}/redraw`).then(r => r.success && location.reload());
                },
                mergeRetained(poolId) {
                    if (!confirm('Merge this pool\'s retained players into the auction? They will become biddable.')) return;
                    this._post(`/admin/auctions/${this.auctionId}/pools/${poolId}/merge-retained`).then(r => r.success && location.reload());
                },
                startAuction() {
                    this._post(`/admin/organizer/auction/${this.auctionId}/api/start`).then(() => {
                        window.location = `/admin/organizer/auction/${this.auctionId}/panel`;
                    });
                },
                skip(apId) {
                    this._post(`/admin/organizer/auction/${this.auctionId}/api/skip-player`, { auction_player_id: apId })
                        .then(r => r.success && location.reload());
                },
                reAuctionRound() {
                    this._post(`/admin/organizer/auction/${this.auctionId}/api/start-reauction-round`)
                        .then(r => { if (r.success) location.reload(); else alert(r.message || 'Nothing to re-auction.'); });
                },
            };
        }

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
