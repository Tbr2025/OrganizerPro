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
                        {{-- Unsold players are held per pool for placement after the bidding. --}}
                        @php
                            $unsoldWaiting = $auction->auctionPlayers()->whereIn('status', ['unsold', 'skipped'])->count();
                        @endphp
                        <a href="{{ route('admin.auctions.allotment', $auction) }}"
                           class="{{ $ghost }} {{ $unsoldWaiting > 0 ? '!border-rose-300 dark:!border-rose-700 !text-rose-700 dark:!text-rose-300' : '' }}">
                            Final allotment
                            @if($unsoldWaiting > 0)
                                <span class="ml-0.5 px-1.5 py-0.5 rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-300 text-[10px] font-bold">{{ $unsoldWaiting }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.auctions.report', $auction) }}" class="{{ $ghost }}">Report</a>
                        {{-- Every player's card as one zip. A browser is started per card, so
                             this is seconds per player rather than milliseconds — hence a
                             deliberate click rather than anything the page does on load, and
                             hence the progress dialog: a 200-player auction is minutes of work
                             and used to be a silent tab that the gateway eventually cut off. --}}
                        <button type="button" x-data
                                @click="$store.cardExport.start({{ $auction->id }}, null, false)"
                                class="{{ $ghost }}"
                                title="Download every player's card as a zip (no SOLD badge). Takes a few seconds per player.">
                            <i class="fas fa-images"></i> All cards
                        </button>
                        <button type="button" x-data
                                @click="$store.cardExport.start({{ $auction->id }}, null, true)"
                                class="{{ $ghost }}"
                                title="Download every player's card with the SOLD badge and price">
                            <i class="fas fa-images"></i> All cards + SOLD
                        </button>
                        @if($posterTemplates->isNotEmpty())
                            {{-- The poster designs, when any have been drawn. A different job
                                 from the card above: the card is the LED wall screenshotted, so
                                 the hall and the download agree; these are drawn in the tournament
                                 template editor, in whatever shape the designer chose. --}}
                            <div x-data="{ open: false }" class="relative inline-block">
                                <button type="button" @click="open = !open" class="{{ $ghost }}"
                                        title="Render every player onto one of this tournament's auction posters">
                                    <i class="fas fa-file-image"></i> Posters
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false"
                                     class="absolute right-0 z-40 mt-1 w-64 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl overflow-hidden">
                                    @foreach($posterTemplates as $posterTemplate)
                                        <button type="button"
                                                @click="open = false; $store.cardExport.start({{ $auction->id }}, null, true, {{ $posterTemplate->id }})"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-800">
                                            <span class="font-semibold block truncate">{{ $posterTemplate->name }}</span>
                                            <span class="text-gray-400">
                                                {{ $posterTemplate->type === \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT ? 'Vertical' : 'Horizontal' }}
                                                &middot; {{ $posterTemplate->canvas_width }}&times;{{ $posterTemplate->canvas_height }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <a href="{{ route('admin.auction.organizer.offline-panel', $auction) }}" target="_blank" class="{{ $ghost }}">Offline panel</a>
                        <a href="{{ route('public.auction.live', $auction) }}" target="_blank" class="{{ $ghost }}">LED wall</a>
                        {{-- Transparent 1920x1080 overlay for a streaming mixer. --}}
                        <a href="{{ route('public.auction.ticker', $auction) }}" target="_blank" class="{{ $ghost }}"
                           title="Transparent 1920x1080 overlay — add as an OBS browser source">Stream ticker</a>
                        <a href="{{ route('team.auction.bidding.show', $auction) }}" class="{{ $ghost }}">Preview bidding</a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Held player emails. Visible so nothing sits unsent without anyone knowing —
             which is exactly what happens if the queue worker is not running. --}}
        @if(isset($isAdmin) && $isAdmin)
            @php
                $outbox = \App\Models\AuctionPendingEmail::where('auction_id', $auction->id)
                    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
                $pendingEmails = (int) ($outbox['pending'] ?? 0);
                $failedEmails = (int) ($outbox['failed'] ?? 0);
                $skippedEmails = (int) ($outbox['skipped'] ?? 0);
            @endphp

            @if($pendingEmails > 0 || $failedEmails > 0 || $skippedEmails > 0 || $auction->email_test_mode)
                <div class="mb-6 rounded-2xl border p-4
                    {{ $auction->email_test_mode
                        ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10'
                        : 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/10' }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                @if($auction->email_test_mode)
                                    Player emails are in test mode{{ $skippedEmails > 0 ? " — {$skippedEmails} recorded, none sent" : '' }}
                                @elseif($pendingEmails > 0)
                                    {{ $pendingEmails }} player email(s) waiting to send
                                @else
                                    {{ $failedEmails }} player email(s) failed
                                @endif
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                @if($auction->email_test_mode)
                                    Nothing is delivered while test mode is on. Turn it off in
                                    <a href="{{ route('admin.auctions.edit', $auction) }}" class="underline">Edit config</a>.
                                @elseif($auction->email_dispatch === 'deferred')
                                    Held until the auction ends, then sent together.
                                    @if($auction->emails_flushed_at)
                                        Last release {{ $auction->emails_flushed_at->diffForHumans() }}.
                                    @endif
                                @else
                                    Sent as each player is sold.
                                @endif
                                @if($failedEmails > 0)
                                    <span class="text-red-600 dark:text-red-400">{{ $failedEmails }} failed.</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                        <a href="{{ route('admin.auctions.emails.index', $auction) }}"
                           class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                            View outbox
                        </a>
                        @if($pendingEmails > 0 && ! $auction->email_test_mode)
                            <form action="{{ route('admin.auctions.emails.flush', $auction) }}" method="POST"
                                  onsubmit="return confirm('Send {{ $pendingEmails }} held email(s) now?')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                                    Send now
                                </button>
                            </form>
                        @endif
                        </div>
                    </div>
                </div>
            @endif
        @endif

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


        {{-- ════ Icon players ════
             Kept when the Pools & Draw Order list was removed from this page — that list
             duplicates Manage Pools, but this does not: merging an icon player into a pool is
             the one control that exists nowhere else, and deleting it made the feature
             unreachable rather than tidier. --}}
        @if(isset($isAdmin) && $isAdmin && $retainedPlayers->count())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
                {{-- ── Icon players ──
                     Listed against the auction, not inside a pool: a retained player is
                     never bid on, so they have no place in a bidding queue. Their price is
                     set on their team. Merging is the one thing that changes that — it gives
                     up the retention and puts them on the block in a pool of your choosing,
                     at that pool's base price. --}}
                @isset($retainedPlayers)
                    @if($retainedPlayers->count() && $auction->pools->count())
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-purple-200 dark:border-purple-800 p-4"
                         x-data="{ mergePool: {{ $auction->pools->first()->id }} }">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h4 class="font-semibold text-purple-700 dark:text-purple-300 text-sm">
                                Icon players ({{ $retainedPlayers->count() }})
                            </h4>
                            <a href="{{ route('admin.auctions.pools.index', $auction) }}"
                               class="text-xs text-gray-500 hover:underline">Budgets &rarr;</a>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Kept by their team and charged against its budget. Not in the draw.
                        </p>
                        <div class="space-y-1 mb-3">
                            @foreach($retainedPlayers as $ap)
                                <div class="flex items-center justify-between text-sm gap-2">
                                    <span class="truncate">{{ $ap->player->name ?? 'Player #'.$ap->player_id }}</span>
                                    <span class="text-[11px] text-gray-400 whitespace-nowrap">
                                        {{ $ap->team->name ?? 'no team' }}
                                        @if((float) $ap->retained_price > 0)
                                            &middot; {{ $auction->formatAmount($ap->retained_price) }}
                                        @else
                                            &middot; <span class="text-red-500">no price</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        @if($isAdmin)
                        <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 dark:border-gray-700 pt-3">
                            <select x-model="mergePool" class="form-control !py-1 !text-xs w-auto">
                                @foreach($auction->pools as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <button @click="mergeRetained(mergePool)"
                                    class="text-xs bg-purple-600 hover:bg-purple-700 text-white rounded px-3 py-1.5">
                                Give up retention &amp; add to pool
                            </button>
                        </div>
                        @endif
                    </div>
                    @endif
                @endisset
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

                    {{-- Export the posters for whatever the chips are showing.
                         The status chip beside this is already the selection — an operator who
                         has clicked Sold is looking at the sold players, and a second, separate
                         way to choose them would only be a way to disagree with what is on
                         screen. The button names the set it will export for the same reason. --}}
                    <div x-data="{ open: false }" class="relative w-full md:w-auto">
                        @if($posterTemplates->isNotEmpty())
                            <button type="button" @click="open = !open"
                                    class="w-full md:w-auto px-4 py-2 rounded-lg text-sm font-semibold bg-amber-500 text-white hover:bg-amber-600 transition whitespace-nowrap">
                                <i class="fas fa-file-image mr-1"></i>
                                <span x-text="statusFilter === 'sold' ? 'Export Sold posters'
                                    : (statusFilter === 'unsold' ? 'Export Unsold posters' : 'Export posters')"></span>
                                <span class="opacity-70" x-text="`(${filteredPlayers.length})`"></span>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false"
                                 class="absolute right-0 z-40 mt-1 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl overflow-hidden">
                                <p class="px-3 py-2 text-[10px] uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                    Choose a design
                                </p>
                                @foreach($posterTemplates as $posterTemplate)
                                    <button type="button"
                                            @click="open = false; $store.cardExport.start(
                                                {{ $auction->id }}, null, true, {{ $posterTemplate->id }},
                                                ['sold', 'unsold'].includes(statusFilter) ? statusFilter : 'all')"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-800">
                                        <span class="font-semibold block truncate text-gray-900 dark:text-white">{{ $posterTemplate->name }}</span>
                                        <span class="text-gray-400">
                                            {{ $posterTemplate->type === \App\Models\TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT ? 'Vertical' : 'Horizontal' }}
                                            &middot; {{ $posterTemplate->canvas_width }}&times;{{ $posterTemplate->canvas_height }}
                                        </span>
                                    </button>
                                @endforeach
                                {{-- The chips also offer On Auction and Waiting, neither of which
                                     has an outcome. Say so rather than exporting them as "all". --}}
                                <p class="px-3 py-2 text-[10px] text-gray-400 border-t border-gray-100 dark:border-gray-800"
                                   x-show="!['sold', 'unsold'].includes(statusFilter)">
                                    Every player in the auction. Pick the <b>Sold</b> or <b>Unsold</b>
                                    chip first to export just those.
                                </p>
                            </div>
                        @else
                            {{-- An operator who has never drawn a poster should be told that,
                                 not handed a menu with nothing in it. --}}
                            <a href="{{ $auction->tournament_id ? route('admin.tournaments.templates.index', $auction->tournament_id) : '#' }}"
                               class="block w-full md:w-auto text-center px-4 py-2 rounded-lg text-sm font-semibold border border-dashed border-amber-400 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition whitespace-nowrap">
                                <i class="fas fa-file-image mr-1"></i> Design a poster
                            </a>
                        @endif
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
                        <template x-if="player.live_bids && player.live_bids.length > 0">
                            <div class="mt-2" x-data="{ showBids: false }">
                                <button @click="showBids = !showBids"
                                    class="text-xs text-blue-500 hover:text-blue-400 flex items-center gap-1">
                                    <svg class="w-3 h-3 transition-transform" :class="showBids ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    <span x-text="player.live_bids.length + ' bid(s)'"></span>
                                </button>
                                <div x-show="showBids" x-transition class="mt-2 space-y-1 max-h-32 overflow-y-auto" x-cloak>
                                    <template x-for="bid in player.live_bids" :key="bid.id">
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
                                    {{-- Bidding and selling live on the control panel, which
                                         enforces the timer, the pool lock and the squad-reserve
                                         rule. Duplicating them here gave a second route to sell a
                                         player under different rules. --}}

                                    {{-- Status Change --}}
                                    <select x-model="player.status" @change="toggleStatus(player)"
                                        class="form-control form-control-sm w-full text-xs">
                                        <option value="on_auction">On Auction</option>
                                        <option value="unsold">Unsold</option>
                                        <option value="waiting">Waiting</option>
                                    </select>
                                </div>
                            </template>

                            {{-- Card download.
                                 A PNG of this player exactly as the LED wall draws them — same
                                 template, background and fonts, because the file IS a capture of
                                 that page rather than a second drawing of it.

                                 Two variants: with the SOLD badge and without. The plain one is
                                 the version wanted before the auction; the stamped one after. --}}
                            <div class="mt-2 flex gap-1">
                                {{-- Preview costs nothing: it opens the card page itself, which
                                     is the same page the download screenshots. Look first, then
                                     spend the seconds Chrome needs only if the card is right. --}}
                                <a :href="`{{ url('auction/' . $auction->id . '/live') }}?card=${player.id}&result=${player.status === 'sold' ? 1 : 0}`"
                                   target="_blank" rel="noopener"
                                   class="flex-1 px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded text-xs text-center hover:bg-indigo-100 hover:text-indigo-600 transition"
                                   title="Open this player's card in a new tab — instant, no rendering">
                                    <i class="fas fa-eye"></i> Preview
                                </a>
                                <a :href="`{{ url('auction/' . $auction->id . '/live') }}?card=${player.id}&result=1`"
                                   target="_blank" rel="noopener"
                                   class="flex-1 px-2 py-1 bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded text-xs text-center hover:bg-emerald-100 hover:text-emerald-600 transition"
                                   title="Open the card with the SOLD badge — download it from there">
                                    <i class="fas fa-stamp"></i> +SOLD
                                </a>
                            </div>

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
        // What amounts are called in this auction.
        const AMOUNT_UNIT = @json($auction->amountUnitConfig());

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

                /**
                 * Live prices on the player list, where they are available.
                 *
                 * This was broken in three ways at once and had never worked: it subscribed
                 * to the PRIVATE `auction.X` channel while every event publishes on the
                 * public one; three of the five names it listened for (`.player.added`,
                 * `.player.removed`, `.player.statusUpdated`) are broadcast by nothing at
                 * all, and `.player.sold` is really `player-on-sold`; and when
                 * `window.Echo` was undefined — which it always is on this page, since Echo
                 * is initialised nowhere in resources/js — it retried every 100 ms forever,
                 * a timer that ran for as long as the tab stayed open.
                 *
                 * Now: the public channel, only names that exist, and a single attempt that
                 * gives up quietly. This screen has no poll, so without Echo it simply shows
                 * the figures it was rendered with, exactly as it did before.
                 */
                connectToEcho() {
                    if (!window.auctionChannel) return;

                    const channel = window.auctionChannel(this.auctionId);
                    if (!channel) return;

                    const setPrice = (id, price) => {
                        const player = this.players.find(p => p.id === id);
                        if (player && price !== undefined) player.current_price = price;
                    };

                    channel
                        .listen('.bid.raised', e => setPrice(e.auction_player_id, e.current_price))
                        // Two events publish `player.onbid` with different shapes — one nests
                        // the model under `auctionPlayer`, the other sends it at the top
                        // level. Both are live, so both have to be read.
                        .listen('.player.onbid', e => {
                            const ap = e.auctionPlayer || e;
                            if (ap?.id) setPrice(ap.id, ap.current_price);
                        })
                        .listen('.player-on-sold', e => {
                            const ap = e.auctionPlayer || e;
                            const player = this.players.find(p => p.id === ap?.id);
                            if (!player) return;
                            player.status = 'sold';
                            player.sold_to_team = ap.sold_to_team ?? e.winningTeam ?? player.sold_to_team;
                            player.final_price = ap.final_price ?? player.final_price;
                            this.sortPlayers();
                        });
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

                /** Shared K/M/B formatter with this auction's unit. */
                formatCurrency(points) {
                    return window.auctionAmount
                        ? window.auctionAmount(points, AMOUNT_UNIT)
                        : String(Number(points) || 0);
                }
            }
        }
    </script>

    {{-- Provides window.auctionChannel. Optional: this page has no poll, so without it
         the list simply keeps the figures it was rendered with. --}}
    @include('backend.pages.auction.partials.echo-init')

    @include('backend.pages.auctions.partials.card-export-progress')
@endsection
