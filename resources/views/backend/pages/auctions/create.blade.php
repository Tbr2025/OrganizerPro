@extends('backend.layouts.app')

@section('title', 'Create New Auction | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto lg:p-8">
        <x-breadcrumbs :breadcrumbs="['title' => 'Create Auction', 'items' => [['label' => 'Auctions', 'url' => route('admin.auctions.index')]]]" />

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Create New Auction</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Set up a new player auction by following the steps below.
                </p>
            </div>
        </div>

        @php
            $auctionCreateLocked = false;
            if (!auth()->user()->hasRole('Superadmin') && auth()->user()->organization_id) {
                $userOrg = \App\Models\Organization::find(auth()->user()->organization_id);
                $auctionCreateLocked = $userOrg && !$userOrg->isAuctionEnabled();
            }
        @endphp

        @if($auctionCreateLocked)
            <div class="relative rounded-lg overflow-hidden">
                <div class="absolute inset-0 z-10 backdrop-blur-sm bg-white/60 dark:bg-gray-900/60 flex flex-col items-center justify-center rounded-lg">
                    <iconify-icon icon="lucide:lock" class="text-5xl text-gray-400 dark:text-gray-500 mb-3"></iconify-icon>
                    <p class="text-lg font-semibold text-gray-600 dark:text-gray-300">Auctions Not Available</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Your package does not include auction features.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Contact your administrator to upgrade.</p>
                </div>
                <div class="pointer-events-none select-none filter blur-[2px] opacity-50">
        @endif

        {{-- Main Form Card with Alpine.js for Wizard Steps --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700"
            x-data="auctionCreateForm()" x-init="init()">

            <form action="{{ route('admin.auctions.store') }}" method="POST" enctype="multipart/form-data" @submit="serializePools()">
                @csrf
                {{-- START: DEBUGGING BLOCK - To See All Errors --}}
                {{-- ======================================================= --}}
                @if ($errors->any())
                    <div
                        class="p-4 mb-4 bg-red-100 dark:bg-red-900/50 border border-red-300 dark:border-red-700 rounded-lg text-red-800 dark:text-red-200">
                        <h3 class="font-bold">Validation Errors Found:</h3>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{-- Step Navigation --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-4" aria-label="Tabs">
                        <a href="#" @click.prevent="step = 1"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step === 1,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 1
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">1. Auction Details</a>
                        <a href="#" @click.prevent="step = 2"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step === 2,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 2
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">2. Financial Rules</a>
                        <a href="#" @click.prevent="step = 3"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step === 3,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 3
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">3. Bid Increments</a>
                        <a href="#" @click.prevent="step = 4"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step === 4,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 4
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">4. Player Pool</a>
                    </nav>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Step 1: Auction Details --}}
                    {{-- Step 1: Auction Details --}}
                    <div x-show="step === 1" x-transition.opacity>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="form-label">Auction Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}"
                                    class="form-control" required>
                            </div>

                            @if (auth()->user()->hasRole('Superadmin'))
                                <div>
                                    <label for="organization_id" class="form-label">Organization <span
                                            class="text-red-500">*</span></label>
                                    <select name="organization_id" id="organization_id" class="form-control" required
                                            onchange="filterAuctionTournaments(this.value)">
                                        <option value="">Select Organization</option>
                                        @foreach ($organizations as $org)
                                            <option value="{{ $org->id }}"
                                                {{ old('organization_id') == $org->id ? 'selected' : '' }}>
                                                {{ $org->name }}</option>
                                        @endforeach
                                    </select>
                                    {{-- **FIX**: Add the error display --}}
                                    @error('organization_id')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <input type="hidden" name="organization_id" value="{{ auth()->user()->organization_id }}">
                            @endif

                            <div>
                                <label for="tournament_id" class="form-label">Tournament <span
                                        class="text-red-500">*</span></label>
                                <select name="tournament_id" id="tournament_id" class="form-control" required>
                                    <option value="">Select Tournament</option>
                                    @foreach ($tournaments as $tournament)
                                        <option value="{{ $tournament->id }}" data-org="{{ $tournament->organization_id }}"
                                            {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>
                                            {{ $tournament->name }}</option>
                                    @endforeach
                                </select>
                                <p id="tournament_hint" class="text-xs text-gray-500 mt-1" style="display:none;">Select an organization first to see its tournaments.</p>
                                {{-- **FIX**: Add the error display --}}
                                @error('tournament_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Cascade: show only the selected organization's tournaments --}}
                            <script>
                                function filterAuctionTournaments(orgId) {
                                    var sel = document.getElementById('tournament_id');
                                    if (!sel) return;
                                    var hint = document.getElementById('tournament_hint');
                                    var anyVisible = false;
                                    Array.prototype.forEach.call(sel.options, function (opt) {
                                        if (opt.value === '') return; // placeholder
                                        var match = !orgId || opt.dataset.org === String(orgId);
                                        opt.hidden = !match;
                                        opt.disabled = !match;
                                        if (match) anyVisible = true;
                                        if (!match && opt.selected) { opt.selected = false; sel.value = ''; }
                                    });
                                    if (hint) hint.style.display = (orgId && !anyVisible) ? 'block' : 'none';
                                }
                                document.addEventListener('DOMContentLoaded', function () {
                                    var org = document.getElementById('organization_id');
                                    // Org users have no org select (their tournaments are already scoped) — skip.
                                    if (org) filterAuctionTournaments(org.value);
                                });
                            </script>
                            <div>
                                <label for="start_at" class="form-label">Start Date & Time <span
                                        class="text-red-500">*</span></label>
                                <input type="datetime-local" name="start_at" id="start_at" value="{{ old('start_at') }}"
                                    class="form-control" required>
                                @error('start_at')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Add End Date Input --}}
                            <div>
                                <label for="end_at" class="form-label">End Date & Time <span
                                        class="text-red-500">*</span></label>
                                <input type="datetime-local" name="end_at" id="end_at" value="{{ old('end_at') }}"
                                    class="form-control" required>
                                @error('end_at')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="form-label">Initial Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="scheduled" @selected(old('status', 'scheduled') == 'scheduled')>Pending</option>
                                    <option value="running" @selected(old('status') == 'running')>Live</option>
                                    <option value="completed" @selected(old('status') == 'completed')>Completed</option>
                                </select>
                            </div>

                            {{-- Bid Type --}}
                            <div class="md:col-span-2">
                                <label class="form-label">Bid Type <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-4 mt-2">
                                    <label @click="bid_type = 'open'" class="cursor-pointer">
                                        <div class="p-4 rounded-lg border-2 transition-all"
                                             :class="bid_type === 'open' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'">
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                     :class="bid_type === 'open' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                </div>
                                                <h4 class="font-semibold text-gray-900 dark:text-white">Open Bid</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Real-time bidding. All teams see each other's bids live. Timer counts down per player.</p>
                                        </div>
                                    </label>
                                    <label @click="bid_type = 'closed'" class="cursor-pointer">
                                        <div class="p-4 rounded-lg border-2 transition-all"
                                             :class="bid_type === 'closed' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-200 dark:border-gray-700'">
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                     :class="bid_type === 'closed' ? 'bg-purple-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path></svg>
                                                </div>
                                                <h4 class="font-semibold text-gray-900 dark:text-white">Closed Bid</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Sealed bidding. Teams submit bids privately. Only admin sees all bids and decides the winner.</p>
                                        </div>
                                    </label>
                                </div>
                                <input type="hidden" name="bid_type" x-model="bid_type">
                            </div>

                            {{-- Phase Transition Thresholds (only for Open Bid) --}}
                            <div class="md:col-span-2" x-show="bid_type === 'open'" x-transition x-cloak>
                                <label class="form-label">Auto Phase Transitions</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Configure automatic bidding phase changes as the price increases. Phases progress: Open (raise hand) → Closed (sealed bids) → Offline (admin manual).</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="online_bid_limit_from" class="form-label text-xs">Online Bid Starts From</label>
                                        <input type="number" name="online_bid_limit_from" id="online_bid_limit_from"
                                               x-model.number="online_bid_limit_from" value="{{ old('online_bid_limit_from') }}"
                                               class="form-control" min="0" placeholder="e.g. 100000">
                                        <p class="text-xs text-gray-400 mt-1">Informational — online range start.</p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_starts_at" class="form-label text-xs">Closed Bid Starts At</label>
                                        <input type="number" name="closed_bid_starts_at" id="closed_bid_starts_at"
                                               x-model.number="closed_bid_starts_at" value="{{ old('closed_bid_starts_at') }}"
                                               class="form-control" min="0" placeholder="e.g. 500000">
                                        <p class="text-xs text-gray-400 mt-1">When price reaches this, bidding switches to sealed mode.</p>
                                    </div>
                                    <div>
                                        <label for="online_bid_limit_to" class="form-label text-xs">Offline Bid Starts At</label>
                                        <input type="number" name="online_bid_limit_to" id="online_bid_limit_to"
                                               x-model.number="online_bid_limit_to" value="{{ old('online_bid_limit_to') }}"
                                               class="form-control" min="0" placeholder="e.g. 1000000">
                                        <p class="text-xs text-gray-400 mt-1">When price reaches this, admin handles bids manually.</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Leave fields empty to skip that phase transition.</p>
                            </div>

                            {{-- Timer Settings --}}
                            <div>
                                <label for="bid_timer_seconds" class="form-label">Bid Timer (seconds) <span class="text-red-500">*</span></label>
                                <input type="number" name="bid_timer_seconds" id="bid_timer_seconds"
                                       x-model.number="bid_timer_seconds" class="form-control" min="5" max="300" required>
                                <p class="text-xs text-gray-500 mt-1" x-show="bid_type === 'open'">Countdown per player in open bid mode.</p>
                                <p class="text-xs text-gray-500 mt-1" x-show="bid_type === 'closed'" x-cloak>Timer used by admin to manage bidding rounds.</p>
                            </div>

                            <div x-show="bid_type === 'open'" x-transition>
                                <label for="bid_timer_reset_seconds" class="form-label">Timer Reset on New Bid (seconds)</label>
                                <input type="number" name="bid_timer_reset_seconds" id="bid_timer_reset_seconds"
                                       x-model.number="bid_timer_reset_seconds" class="form-control" min="5" max="300">
                                <p class="text-xs text-gray-500 mt-1">When a new bid is placed, timer resets to this value.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Financial Rules --}}
                    <div x-show="step === 2" x-transition.opacity x-cloak>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="max_budget_per_team" class="form-label">Max Budget Per Team <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="max_budget_per_team" id="max_budget_per_team"
                                    value="{{ old('max_budget_per_team', 10000000) }}" class="form-control" required>
                            </div>
                            <div>
                                <label for="base_price" class="form-label">Default Player Base Price <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="base_price" id="base_price"
                                    x-model.number="defaultBasePrice" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Bid Increment Rules --}}
                    <div x-show="step === 3" x-transition.opacity x-cloak>
                        <div>
                            <h3 class="font-semibold text-lg mb-2 text-gray-800 dark:text-white">Configure Bid Increments
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Define how much the bid increases at
                                different price points.</p>

                            <div class="space-y-3">
                                <template x-for="(rule, index) in rules" :key="index">
                                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-md">
                                        <span class="text-gray-500">If price is between</span>
                                        <input type="number" :name="`bid_rules[${index}][from]`" x-model="rule.from"
                                            placeholder="From" class="form-control w-1/4">
                                        <span class="text-gray-500">and</span>
                                        <input type="number" :name="`bid_rules[${index}][to]`" x-model="rule.to"
                                            placeholder="To" class="form-control w-1/4">
                                        <span class="text-gray-500">, increment by</span>
                                        <input type="number" :name="`bid_rules[${index}][increment]`"
                                            x-model="rule.increment" placeholder="Increment" class="form-control w-1/4">
                                        <button type="button" @click="rules.splice(index, 1)"
                                            class="btn btn-danger btn-sm">&times;</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button"
                                @click="rules.push({ from: rules[rules.length-1].to, to: '', increment: '' })"
                                class="btn btn-secondary mt-4">
                                + Add Rule
                            </button>
                        </div>
                    </div>

                    {{-- Step 4: Player Pool Management --}}
                    <div x-show="step === 4" x-transition.opacity x-cloak>
                        <div class="mb-6">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Player Pool Management</h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add or remove players from the auction pool and set their base prices.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="addAllPlayers()"
                                            class="btn btn-sm bg-green-500 hover:bg-green-600 text-white"
                                            x-show="filteredAvailable.length > 0">
                                        <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Add All (<span x-text="filteredAvailable.length"></span>)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mb-4">
                            <button type="button" @click="addPool()" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white">
                                <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Pool
                            </button>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {{-- Pools (left) --}}
                            <div class="space-y-4">
                                <template x-for="(pool, idx) in pools" :key="pool.uid">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-4 border-2 border-blue-200 dark:border-blue-800">
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="text-xs uppercase tracking-wide text-gray-400" x-text="'#' + (idx + 1)"></span>
                                            <input type="text" x-model="pool.name" placeholder="Pool name"
                                                   class="form-control form-control-sm flex-1 font-semibold bg-white dark:bg-gray-800">
                                            <button type="button" @click="removePool(idx)" class="text-red-500 text-xs hover:underline">Remove</button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mb-3">
                                            <div>
                                                <label class="text-[11px] text-gray-500">Capacity</label>
                                                <input type="number" min="1" x-model.number="pool.capacity" placeholder="e.g. 50"
                                                       class="form-control form-control-sm bg-white dark:bg-gray-800">
                                            </div>
                                            <div>
                                                <label class="text-[11px] text-gray-500">Auction order</label>
                                                <select x-model="pool.order_mode" class="form-control form-control-sm bg-white dark:bg-gray-800">
                                                    <option value="sequential">Sequential (1,2,3…)</option>
                                                    <option value="random">Random</option>
                                                    <option value="odd_even">Odd then Even</option>
                                                    <option value="manual">Custom (drag to order)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-gray-500 mb-2">
                                            <span x-text="pool.players.length"></span> players<span x-show="pool.order_mode==='manual'"> · drag ⠿ to set order</span>
                                        </p>
                                        <div class="space-y-2 max-h-80 overflow-y-auto" :data-pool-uid="pool.uid" x-init="$nextTick(() => initPoolSortable($el))">
                                            <template x-for="player in pool.players" :key="player.id">
                                                <div class="bg-white dark:bg-gray-800 rounded-lg p-2 border border-gray-200 dark:border-gray-700 flex items-center gap-2" :data-player-id="player.id">
                                                    <span class="pool-player-handle cursor-move text-gray-400 select-none" x-show="pool.order_mode==='manual'">⠿</span>
                                                    <div class="flex-1 min-w-0"><p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="player.name"></p></div>
                                                    <input type="number" min="0" x-model.number="player.base_price" placeholder="Base"
                                                           class="form-control form-control-sm w-24 text-center">
                                                    <button type="button" @click="removeFromPool(player, idx)" class="text-red-500 px-1">✕</button>
                                                </div>
                                            </template>
                                            <p x-show="pool.players.length===0" class="text-xs text-gray-400 text-center py-4">No players yet — add from the right.</p>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="pools.length===0" class="text-sm text-gray-500 text-center py-6">No pools yet. Click <strong>Add Pool</strong> to start.</p>
                            </div>

                            {{-- Available Players (right) --}}
                            <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl p-5 border-2 border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-white">Available</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="filteredAvailable.length"></span> approved players</p>
                                    </div>
                                </div>

                                <div class="mb-3" x-show="pools.length > 0">
                                    <label class="text-[11px] text-gray-500">Add to pool</label>
                                    <select x-model="targetPool" class="form-control form-control-sm bg-white dark:bg-gray-800">
                                        <option value="">— Select a pool —</option>
                                        <template x-for="(pool, idx) in pools" :key="pool.uid">
                                            <option :value="String(idx)" x-text="pool.name || ('Pool ' + (idx+1))"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="relative mb-4">
                                    <input type="text" x-model="searchAvailable" placeholder="Search available players..."
                                           class="form-control pl-10 bg-white dark:bg-gray-800">
                                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>

                                <div class="flex justify-end mb-2" x-show="filteredAvailable.length > 0 && pools.length > 0">
                                    <button type="button" @click="addAllPlayers()" class="text-xs text-green-700 dark:text-green-400 hover:underline">Add all (<span x-text="filteredAvailable.length"></span>) to selected pool</button>
                                </div>

                                <div class="space-y-2 max-h-[500px] overflow-y-auto pr-2">
                                    <template x-for="player in filteredAvailable" :key="player.id">
                                        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow group cursor-pointer"
                                             @click="addToPool(player)" :class="pools.length === 0 ? 'opacity-50 pointer-events-none' : ''">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold flex-shrink-0" x-text="player.name.charAt(0)"></div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-semibold text-gray-900 dark:text-white truncate" x-text="player.name"></p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="pools.length === 0 ? 'Add a pool first' : 'Click to add to selected pool'"></p>
                                                </div>
                                                <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="filteredAvailable.length === 0" class="text-center py-12">
                                        <p class="text-gray-500 dark:text-gray-400" x-text="searchAvailable ? 'No players found.' : 'No approved players available.'"></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Serialized pools (written on submit) --}}
                        <input type="hidden" name="pools" x-ref="poolsInput">
                    </div>
                </div>

                {{-- Form Footer with Navigation and Submit --}}
                <div
                    class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <a href="{{ route('admin.auctions.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button type="button" x-show="step > 1" @click="step--" class="btn btn-secondary"
                            x-cloak>Previous</button>

                        <button type="button" x-show="step < 4" @click="step++" class="btn btn-primary"
                            x-cloak>Next</button>

                        <div x-show="step === 4" x-cloak>
                            <button type="submit" class="btn btn-success">
                                Create Auction
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if($auctionCreateLocked ?? false)
                </div>
            </div>
        @endif
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function auctionCreateForm() {
    return {
        step: 1,
        bid_type: '{{ old('bid_type', 'open') }}',
        bid_timer_seconds: {{ old('bid_timer_seconds', 30) }},
        bid_timer_reset_seconds: {{ old('bid_timer_reset_seconds', 15) }},
        online_bid_limit_from: {{ old('online_bid_limit_from', 'null') }},
        online_bid_limit_to: {{ old('online_bid_limit_to', 'null') }},
        closed_bid_starts_at: {{ old('closed_bid_starts_at', 'null') }},
        rules: [
            { from: 100000, to: 200000, increment: 10000 },
            { from: 220000, to: 300000, increment: 20000 },
            { from: 350000, to: 600000, increment: 500000 },
            { from: 600000, to: 800000, increment: 1000000 }
        ],
        pools: [],
        available: [],
        targetPool: '', // '' = no pool chosen (user must pick before adding)
        searchAvailable: '',
        selectedOrg: null,
        defaultBasePrice: {{ old('base_price', 10000) }},
        _uid: 1,

        init() {
            const allPlayers = @json($availablePlayers);
            this.available = allPlayers.map(p => ({ id: p.id, name: p.name, org: p.organization_id }))
                .sort((a, b) => a.name.localeCompare(b.name));

            // Track the selected organization so we never show another org's players.
            const orgSelect = document.getElementById('organization_id');
            const syncOrg = () => {
                this.selectedOrg = orgSelect && orgSelect.value ? Number(orgSelect.value) : null;
                this.dropForeignOrgPlayers();
            };
            if (orgSelect) {
                orgSelect.addEventListener('change', syncOrg);
                syncOrg();
            }
            this.addPool();
            this.targetPool = ''; // no pool pre-selected on load
        },

        /** Pull any already-pooled players that don't belong to the selected org back out. */
        dropForeignOrgPlayers() {
            if (!this.selectedOrg) return;
            this.pools.forEach(pool => {
                const keep = [];
                pool.players.forEach(p => {
                    if (p.org && p.org !== this.selectedOrg) {
                        // not for this org — silently remove (it's hidden from available too)
                    } else {
                        keep.push(p);
                    }
                });
                pool.players = keep;
            });
        },

        get filteredAvailable() {
            let list = this.available;
            if (this.selectedOrg) list = list.filter(p => !p.org || p.org === this.selectedOrg);
            if (this.searchAvailable) list = list.filter(p => p.name.toLowerCase().includes(this.searchAvailable.toLowerCase()));
            return list;
        },

        addPool() {
            this.pools.push({ uid: this._uid++, name: 'Pool ' + (this.pools.length + 1), capacity: null, order_mode: 'sequential', players: [] });
            // Auto-target the pool the user just created (deliberate action).
            this.targetPool = String(this.pools.length - 1);
        },

        removePool(idx) {
            this.pools[idx].players.forEach(p => this.available.push({ id: p.id, name: p.name, org: p.org }));
            this.available.sort((a, b) => a.name.localeCompare(b.name));
            this.pools.splice(idx, 1);
            this.targetPool = ''; // force an explicit re-pick after structure change
        },

        addToPool(player) {
            if (this.targetPool === '' || this.targetPool === null || this.pools.length === 0) {
                alert('Choose a pool in "Add to pool" first.');
                return;
            }
            this.pools[Number(this.targetPool)].players.push({ id: player.id, name: player.name, org: player.org, base_price: this.defaultBasePrice });
            this.available = this.available.filter(p => p.id !== player.id);
            this.searchAvailable = '';
        },

        removeFromPool(player, idx) {
            this.available.push({ id: player.id, name: player.name, org: player.org });
            this.available.sort((a, b) => a.name.localeCompare(b.name));
            this.pools[idx].players = this.pools[idx].players.filter(p => p.id !== player.id);
        },

        addAllPlayers() {
            if (this.targetPool === '' || this.targetPool === null || this.pools.length === 0) {
                alert('Choose a pool in "Add to pool" first.');
                return;
            }
            const i = Number(this.targetPool);
            const toAdd = this.filteredAvailable; // org + search scoped
            if (!toAdd.length) return;
            if (!confirm('Add all ' + toAdd.length + ' players to ' + (this.pools[i].name || 'the pool') + '?')) return;
            const ids = new Set(toAdd.map(p => p.id));
            toAdd.forEach(p => this.pools[i].players.push({ id: p.id, name: p.name, org: p.org, base_price: this.defaultBasePrice }));
            this.available = this.available.filter(p => !ids.has(p.id));
        },

        initPoolSortable(el) {
            if (!window.Sortable || el._poolSortable) return;
            el._poolSortable = window.Sortable.create(el, {
                handle: '.pool-player-handle', animation: 150, draggable: '[data-player-id]',
                onEnd: (e) => {
                    const pool = this.pools.find(p => String(p.uid) === String(el.dataset.poolUid));
                    if (!pool || e.oldIndex === e.newIndex) return;
                    const moved = pool.players.splice(e.oldIndex, 1)[0];
                    pool.players.splice(e.newIndex, 0, moved);
                }
            });
        },

        serializePools() {
            const data = this.pools
                .filter(p => p.players.length > 0)
                .map(p => ({
                    name: p.name || 'Pool',
                    capacity: p.capacity || null,
                    order_mode: p.order_mode || 'sequential',
                    players: p.players.map(pl => ({ id: pl.id, base_price: pl.base_price || this.defaultBasePrice })),
                }));
            this.$refs.poolsInput.value = JSON.stringify(data);
        },

        formatMoney(value) {
            const num = Number(value) || 0;
            if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
            return num.toString();
        }
    };
}
</script>
@endpush
