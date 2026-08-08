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
                        {{-- Create was missing this step entirely, even though store() has
                             always validated and saved every field on it — so branding and
                             the screen templates silently took their defaults on a new
                             auction and could only be set by going back in to Edit. --}}
                        <a href="#" @click.prevent="step = 5"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step === 5,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 5
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">5. Branding</a>
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

                            {{-- Bidding Mode --}}
                            <div class="md:col-span-2">
                                <label class="form-label">Bidding Mode <span class="text-red-500">*</span></label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-2">
                                    Who enters the bids. This is a decision about the whole auction, not a phase.
                                </p>
                                <div class="grid grid-cols-2 gap-4 mt-2">
                                    <label @click="open_bid_mode = 'online'" class="cursor-pointer">
                                        <div class="p-4 rounded-lg border-2 transition-all"
                                             :class="open_bid_mode === 'online' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700'">
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                     :class="open_bid_mode === 'online' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                </div>
                                                <h4 class="font-semibold text-gray-900 dark:text-white">Online</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Team managers bid from their own dashboards, wherever they are.</p>
                                        </div>
                                    </label>
                                    <label @click="open_bid_mode = 'offline'" class="cursor-pointer">
                                        <div class="p-4 rounded-lg border-2 transition-all"
                                             :class="open_bid_mode === 'offline' ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-700'">
                                            <div class="flex items-center gap-3 mb-2">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                     :class="open_bid_mode === 'offline' ? 'bg-orange-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                </div>
                                                <h4 class="font-semibold text-gray-900 dark:text-white">Offline</h4>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Everyone is in the room. Only the admin and organizer enter bids &mdash; they tap a team's logo and the price rises by the increment.</p>
                                        </div>
                                    </label>
                                </div>
                                <input type="hidden" name="open_bid_mode" x-model="open_bid_mode">
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
                                {{-- TWO independent settings, not three stages of one. See the
                                     matching note on edit.blade.php: offline is not a phase that
                                     comes after sealed bidding, and the sealed round runs in both
                                     modes. --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Two separate rules, both driven by the price.</p>
                                <ul class="text-xs text-gray-500 dark:text-gray-400 mb-3 space-y-1 pl-4 list-disc">
                                    <li><strong>Who enters the bids</strong> — teams from their own screens (online), or the organizer on their behalf in the room (offline).</li>
                                    <li><strong>How bidding works</strong> — open, where every bid is visible as it happens, or closed, where each team submits one private amount and the highest wins.</li>
                                </ul>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    The sealed round runs in <strong>both</strong> modes. Online, the teams type their own amount; offline, the organizer enters each team's amount for them on the control panel. Either way the highest submitted amount takes the player.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="online_bid_limit_from" class="form-label text-xs">Online Bid Starts From</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="online_bid_limit_from"
                                                   :value="toM(online_bid_limit_from)"
                                                   @input="online_bid_limit_from = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 0.1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="online_bid_limit_from" :value="online_bid_limit_from">
                                        <p class="text-xs text-gray-400 mt-1">Informational only — it changes nothing on its own.</p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_starts_at" class="form-label text-xs">Closed Bid Starts At</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="closed_bid_starts_at"
                                                   :value="toM(closed_bid_starts_at)"
                                                   @input="closed_bid_starts_at = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 0.5">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_starts_at" :value="closed_bid_starts_at">
                                        <p class="text-xs text-gray-400 mt-1">Once a bid reaches this, open bidding stops and the sealed round begins — in online <em>and</em> offline mode.</p>
                                    </div>
                                    <div x-show="open_bid_mode === 'online'" x-cloak>
                                        <label for="online_bid_limit_to" class="form-label text-xs">Organizer Enters Bids From</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="online_bid_limit_to"
                                                   :value="toM(online_bid_limit_to)"
                                                   @input="online_bid_limit_to = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="online_bid_limit_to" :value="online_bid_limit_to">
                                        <p class="text-xs text-gray-400 mt-1">Above this price the organizer enters bids for the teams instead of the teams bidding themselves. It does not skip or replace the sealed round.</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Leave a field empty to switch that rule off. The organizer can also override either one by hand during the auction, and an override they make is not undone by the next player.</p>
                            </div>

                            {{-- Sealed round rules. This wizard has no auctionData object,
                                 so each field is a flat property seeded from old(). --}}
                            <div class="mt-6 p-5 bg-indigo-50 dark:bg-indigo-900/10 rounded-2xl border border-indigo-200 dark:border-indigo-800/60">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Sealed Round</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                    Once the price reaches the closed-bid threshold, teams submit a private amount.
                                    The highest wins; a tie goes to a re-bid and then to a drawn lot.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="closed_bid_step" class="form-label text-xs">Bid Step</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="closed_bid_step"
                                                   :value="toM(closed_bid_step)"
                                                   @input="closed_bid_step = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="0.1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_step" :value="closed_bid_step">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Sealed amounts must be exact multiples of this. Anything else is refused, not rounded.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_max_pct_of_budget" class="form-label text-xs">Max Spend Per Player</label>
                                        <div class="relative">
                                            <input type="number" step="1" min="1" max="100" id="closed_bid_max_pct_of_budget"
                                                   x-model.number="closed_bid_max_pct_of_budget"
                                                   class="form-control pr-9" placeholder="70">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">%</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_max_pct_of_budget" :value="closed_bid_max_pct_of_budget">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Share of a team's <strong>total</strong> budget one player may cost.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_max_rebid_rounds" class="form-label text-xs">Re-bid Rounds On A Tie</label>
                                        <input type="number" name="closed_bid_max_rebid_rounds" id="closed_bid_max_rebid_rounds" min="0" max="5"
                                               x-model.number="closed_bid_max_rebid_rounds"
                                               class="form-control" placeholder="2">
                                        <p class="text-xs text-gray-400 mt-1">Then a lot is drawn. 0 goes straight to the lot.</p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_timer_seconds" class="form-label text-xs">Sealed Round Timer</label>
                                        <div class="relative">
                                            <input type="number" name="closed_bid_timer_seconds" id="closed_bid_timer_seconds" min="5" max="600"
                                                   x-model.number="closed_bid_timer_seconds"
                                                   class="form-control pr-9" :placeholder="bid_timer_seconds || 30">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">s</span>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Blank uses the ordinary bid timer.</p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input type="hidden" name="closed_bid_requires_acceptance" value="0">
                                        <input type="checkbox" name="closed_bid_requires_acceptance" value="1"
                                               x-model="closed_bid_requires_acceptance"
                                               class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Teams must accept the purse conditions before bidding
                                        </span>
                                    </label>

                                    <div>
                                        <label for="closed_bid_tie_breaker" class="form-label text-xs">After The Last Re-bid</label>
                                        <select name="closed_bid_tie_breaker" id="closed_bid_tie_breaker"
                                                x-model="closed_bid_tie_breaker" class="form-control">
                                            <option value="lot">Draw a lot</option>
                                            <option value="manual">Organizer decides</option>
                                        </select>
                                    </div>
                                </div>
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
                                <div class="relative">
                                    <input type="number" step="any" min="0" id="max_budget_per_team"
                                           :value="toM(maxBudgetPerTeam)"
                                           @input="maxBudgetPerTeam = fromM($event.target.value)"
                                           class="form-control pr-9" placeholder="10" required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                </div>
                                <input type="hidden" name="max_budget_per_team" :value="maxBudgetPerTeam">
                                <p class="text-xs text-gray-500 mt-1 font-mono" x-text="rawLabel(maxBudgetPerTeam)"></p>
                            </div>
                            <div>
                                <label for="base_price" class="form-label">Default Player Base Price <span
                                        class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="number" step="any" min="0" id="base_price"
                                           :value="toM(defaultBasePrice)"
                                           @input="defaultBasePrice = fromM($event.target.value)"
                                           class="form-control pr-9" placeholder="0.1" required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                </div>
                                <input type="hidden" name="base_price" :value="defaultBasePrice">
                                <p class="text-xs text-gray-500 mt-1 font-mono" x-text="rawLabel(defaultBasePrice)"></p>
                            </div>

                            {{-- Squad reserve rule.
                                 store() has always validated and saved both of these, but
                                 Create never offered them — so every auction made here took
                                 the defaults (a squad of 11, reserving the base price per
                                 place) and the only way to set them was to go back in via
                                 Edit. Mirrors the block on edit.blade.php. --}}
                            <div class="md:col-span-2 p-5 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-200 dark:border-amber-800/60">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Squad Reserve Rule</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                    Teams must keep back enough to buy the places they still have to fill. A bid is
                                    refused if it would leave them unable to complete a legal squad.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="min_squad_size" class="form-label text-xs">Minimum Squad Size</label>
                                        <input type="number" name="min_squad_size" id="min_squad_size" min="1" max="50"
                                               x-model.number="minSquadSize"
                                               class="form-control" placeholder="11">
                                        <p class="text-xs text-gray-400 mt-1">Players each team must end up with.</p>
                                    </div>
                                    <div>
                                        <label for="min_price_per_player" class="form-label text-xs">Reserve Per Remaining Place</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="min_price_per_player"
                                                   :value="toM(minPricePerPlayer)"
                                                   @input="minPricePerPlayer = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="min_price_per_player" :value="minPricePerPlayer">
                                        <p class="text-xs text-gray-400 mt-1">Left blank, the base price is used.</p>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs px-3 py-2 rounded-lg"
                                   :class="reserveExceedsBudget
                                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    A squad of <span class="font-semibold" x-text="minSquadSize || 11"></span>
                                    at <span class="font-semibold" x-text="rawLabel(reservePerPlace)"></span> each needs
                                    <span class="font-semibold" x-text="rawLabel(reserveTotal)"></span>.
                                    <template x-if="reserveExceedsBudget">
                                        <span>That is more than the team budget, so no player could ever be bought —
                                        raise the budget or lower these figures.</span>
                                    </template>
                                </p>
                            </div>

                            {{-- What the money is called on every screen. --}}
                            <div class="md:col-span-2 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/60">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="amount_unit" class="form-label text-xs">Amount Unit</label>
                                        <select name="amount_unit" id="amount_unit" x-model="amountUnit" class="form-control">
                                            @foreach(\App\Models\Auction::amountUnitOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div x-show="amountUnit === 'custom'" x-transition>
                                        <label for="amount_unit_label" class="form-label text-xs">Custom Label</label>
                                        <input type="text" name="amount_unit_label" id="amount_unit_label" maxlength="30"
                                               x-model="amountUnitLabel" class="form-control" placeholder="e.g. Credits">
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                                    Amounts will read as
                                    <span class="font-bold font-mono" x-text="unitSample(10000000)"></span>
                                    and <span class="font-bold font-mono" x-text="unitSample(500000)"></span>
                                </p>
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
                                        <span class="relative w-1/4">
                                            <input type="number" step="any" min="0"
                                                   :value="toM(rule.from)"
                                                   @input="rule.from = fromM($event.target.value)"
                                                   placeholder="From" class="form-control pr-7 w-full">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                        </span>
                                        <input type="hidden" :name="`bid_rules[${index}][from]`" :value="rule.from">
                                        <span class="text-gray-500">and</span>
                                        <span class="relative w-1/4">
                                            <input type="number" step="any" min="0"
                                                   :value="toM(rule.to)"
                                                   @input="rule.to = fromM($event.target.value)"
                                                   placeholder="To" class="form-control pr-7 w-full">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                        </span>
                                        <input type="hidden" :name="`bid_rules[${index}][to]`" :value="rule.to">
                                        <span class="text-gray-500">, increment by</span>
                                        <span class="relative w-1/4">
                                            <input type="number" step="any" min="0"
                                                   :value="toM(rule.increment)"
                                                   @input="rule.increment = fromM($event.target.value)"
                                                   placeholder="Increment" class="form-control pr-7 w-full">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                        </span>
                                        <input type="hidden" :name="`bid_rules[${index}][increment]`" :value="rule.increment">
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
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Add or remove players from the auction pool and set their base prices.
                                        Only players with an approved registration for the selected tournament are listed;
                                        retained players are excluded.
                                    </p>
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

                        {{-- The eligible list depends on the tournament, which is chosen in
                             step 1 — say so rather than showing a puzzling full list. --}}
                        <div x-show="!selectedTournament"
                             class="mb-4 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-200">
                            Pick a tournament in <button type="button" @click="step = 1" class="underline font-semibold">step 1</button>
                            to narrow this list to players approved for it.
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
                                                    <span class="relative w-24 flex-shrink-0">
                                                        <input type="number" step="any" min="0" placeholder="Base"
                                                               :value="toM(player.base_price)"
                                                               @input="player.base_price = fromM($event.target.value)"
                                                               class="form-control form-control-sm w-full text-center pr-5">
                                                        <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-gray-400 pointer-events-none">M</span>
                                                    </span>
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

                    {{-- Step 5: Branding — mirrors Edit's step 5. Every field here is already
                         validated and stored by store(); Create simply never offered them. --}}
                    <div x-show="step === 5" x-transition.opacity x-cloak>
                        <div class="max-w-4xl mx-auto">
                            <div class="mb-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Branding &amp; Appearance</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">How the live auction screens look. All optional — anything left blank uses the platform default, and it can be changed later under Edit.</p>
                            </div>

                            @include('backend.pages.auctions.partials.screen-templates', [
                                'auctionId' => null,
                                'selectedDisplay' => old('auction_template_id'),
                                'selectedTicker' => old('ticker_template_id'),
                                'displayTemplates' => $displayTemplates ?? collect(),
                                'tickerTemplates' => $tickerTemplates ?? collect(),
                            ])

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Card Background Image</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Replaces the default player-card background on the live wall. Recommended 1601x910px.</p>
                                    <input type="file" name="background_image" accept="image/*" class="form-control">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Auction Logo</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Shown on the waiting screen and the ticker.</p>
                                    <input type="file" name="auction_logo" accept="image/*" class="form-control">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Waiting Screen Background</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Behind the "waiting for next player" screen.</p>
                                    <input type="file" name="waiting_background_image" accept="image/*" class="form-control">
                                </div>

                                <div>
                                    <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Primary Colour</label>
                                    <input type="color" name="primary_color" id="primary_color"
                                           value="{{ old('primary_color', '#00d4ff') }}"
                                           class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 p-1 cursor-pointer">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Accent on the wall — headings, the countdown ring, glows.</p>
                                </div>

                                <div>
                                    <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secondary Colour</label>
                                    <input type="color" name="secondary_color" id="secondary_color"
                                           value="{{ old('secondary_color', '#ff6b00') }}"
                                           class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 p-1 cursor-pointer">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Used for the current bid and the sold stamp.</p>
                                </div>
                            </div>
                        </div>
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

                        <button type="button" x-show="step < 5" @click="step++" class="btn btn-primary"
                            x-cloak>Next</button>

                        <div x-show="step === 5" x-cloak>
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
        {{-- Who enters the bids for the whole auction. Create has no auctionData object,
             so every field is a flat old()-seeded property of its own. --}}
        open_bid_mode: @json(old('open_bid_mode', 'online')),
        online_bid_limit_from: {{ old('online_bid_limit_from', 'null') }},
        online_bid_limit_to: {{ old('online_bid_limit_to', 'null') }},
        closed_bid_starts_at: {{ old('closed_bid_starts_at', 'null') }},
        // Sealed round. Blank posts null and the server's accessors supply the defaults,
        // so these must not be pre-filled with numbers here.
        closed_bid_step: {{ old('closed_bid_step', 'null') }},
        closed_bid_max_pct_of_budget: {{ old('closed_bid_max_pct_of_budget', 'null') }},
        closed_bid_max_rebid_rounds: {{ old('closed_bid_max_rebid_rounds', 'null') }},
        closed_bid_timer_seconds: {{ old('closed_bid_timer_seconds', 'null') }},
        closed_bid_requires_acceptance: {{ old('closed_bid_requires_acceptance', 1) ? 'true' : 'false' }},
        closed_bid_tie_breaker: '{{ old('closed_bid_tie_breaker', 'lot') }}',
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
        selectedTournament: null,
        amountUnit: '{{ old('amount_unit', 'points') }}',
        amountUnitLabel: '{{ old('amount_unit_label') }}',
        defaultBasePrice: {{ old('base_price', 100000) }},
        maxBudgetPerTeam: {{ old('max_budget_per_team', 10000000) }},
        // Squad reserve rule. Blank min price posts null and the server falls back to the
        // base price, which is the documented behaviour — so it must not be pre-filled.
        minSquadSize: {{ old('min_squad_size', 11) }},
        minPricePerPlayer: {{ old('min_price_per_player', 'null') }},

        get reservePerPlace() {
            return Number(this.minPricePerPlayer) || Number(this.defaultBasePrice) || 0;
        },
        get reserveTotal() {
            return (Number(this.minSquadSize) || 0) * this.reservePerPlace;
        },
        get reserveExceedsBudget() {
            return this.reserveTotal > (Number(this.maxBudgetPerTeam) || 0);
        },
        _uid: 1,

        /* ── Money entry in millions ───────────────────────────────────────────────
           Amounts are stored in whole units, but typing 100000000 is error-prone and
           unreadable, so every money field is entered in millions and converted here.
           The raw value is what gets posted, via a hidden input.                     */

        /** The chosen unit, shaped for the shared money formatter. */
        get unitConfig() {
            if (this.amountUnit === 'usd') return { label: '$', prefix: true };
            if (this.amountUnit === 'coins') return { label: 'Coins', prefix: false };
            if (this.amountUnit === 'custom') {
                return { label: (this.amountUnitLabel || '').trim() || 'Points', prefix: false };
            }
            return { label: 'Points', prefix: false };
        },

        /** Live example of how amounts will read. */
        unitSample(value) {
            return window.auctionAmount ? window.auctionAmount(value, this.unitConfig) : String(value);
        },

        /** Raw stored units → the millions figure shown in the field. */
        toM(raw) {
            if (raw === '' || raw === null || raw === undefined) return '';
            const n = Number(raw);
            if (!isFinite(n)) return '';
            return Number((n / 1e6).toFixed(6));
        },

        /** Millions typed in the field → raw stored units. */
        fromM(value) {
            if (value === '' || value === null || value === undefined) return '';
            const n = Number(value);
            if (!isFinite(n)) return '';
            // toFixed(2) first: 0.1 * 1e6 is 100000.00000000001 in floating point.
            return Number((n * 1e6).toFixed(2));
        },

        /** The exact stored figure, echoed under the field so nothing is ambiguous. */
        rawLabel(raw) {
            if (raw === '' || raw === null || raw === undefined) return '—';
            return Number(raw).toLocaleString('en-US');
        },

        init() {
            const allPlayers = @json($availablePlayers);
            this.available = allPlayers.map(p => ({
                id: p.id,
                name: p.name,
                org: p.organization_id,
                // Tournaments this player has an approved registration for.
                tournamentIds: p.tournament_ids || [],
            })).sort((a, b) => a.name.localeCompare(b.name));

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

            // Only players approved for the chosen tournament may be pooled, so narrow
            // the list as soon as one is picked and pull out anyone already placed who
            // does not qualify.
            const tournamentSelect = document.getElementById('tournament_id');
            const syncTournament = () => {
                this.selectedTournament = tournamentSelect && tournamentSelect.value
                    ? Number(tournamentSelect.value)
                    : null;
                this.dropIneligiblePlayers();
            };
            if (tournamentSelect) {
                tournamentSelect.addEventListener('change', syncTournament);
                syncTournament();
            }

            this.addPool();
            this.targetPool = ''; // no pool pre-selected on load
        },

        /** Is this player approved for the tournament currently selected? */
        isEligible(player) {
            if (!this.selectedTournament) return true; // nothing chosen yet — show all
            return (player.tournamentIds || []).includes(this.selectedTournament);
        },

        /** Remove already-pooled players who aren't approved for the chosen tournament. */
        dropIneligiblePlayers() {
            if (!this.selectedTournament) return;
            this.pools.forEach(pool => {
                pool.players = pool.players.filter(p => this.isEligible(p));
            });
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
            // Approved for the chosen tournament only.
            list = list.filter(p => this.isEligible(p));
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

        /** Shared K/M/B formatter with the chosen unit. */
        formatMoney(value) {
            return window.auctionAmount
                ? window.auctionAmount(value, this.unitConfig)
                : String(Number(value) || 0);
        }
    };
}
</script>
@endpush
