@extends('backend.layouts.app')
@section('title', 'Offline Auction Panel — ' . $auction->name)

@push('before-alpine')
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
@endpush

@push('styles')
<style>
    .offline-panel-wrapper {
        background: #030712;
        color: #fff;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    .offline-panel-wrapper.is-fullscreen {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }

    /* Dot pattern background */
    .dot-bg {
        background-image: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 24px 24px;
    }

    /* Bid flash animation */
    @keyframes bidFlash {
        0% { transform: scale(1.15); color: #4ade80; }
        100% { transform: scale(1); }
    }
    .bid-flash { animation: bidFlash 0.3s ease-out; }

    /* Closing call — punches in as each call fires. */
    @keyframes finalCallPulse {
        0%   { transform: scale(0.9); opacity: 0; }
        40%  { transform: scale(1.04); opacity: 1; }
        100% { transform: scale(1); }
    }
    .final-call-pulse { animation: finalCallPulse 0.7s cubic-bezier(0.34,1.56,0.64,1) both; }

    /* Price counter animation */
    @keyframes priceUp {
        0% { transform: translateY(16px); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
    .price-up { animation: priceUp 0.25s ease-out; }

    /* Sold stamp */
    @keyframes soldStamp {
        0% { transform: rotate(-12deg) scale(0); opacity: 0; }
        70% { transform: rotate(-12deg) scale(1.15); opacity: 1; }
        100% { transform: rotate(-12deg) scale(1); opacity: 1; }
    }
    .sold-stamp { animation: soldStamp 0.5s ease-out forwards; }

    /* Unsold stamp */
    @keyframes unsoldStamp {
        0% { transform: rotate(-12deg) scale(0); opacity: 0; }
        100% { transform: rotate(-12deg) scale(1); opacity: 1; }
    }
    .unsold-stamp { animation: unsoldStamp 0.5s ease-out forwards; }

    /* Team pulse for active bidder */
    @keyframes teamPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.5); }
        50% { box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); }
    }
    .team-pulse { animation: teamPulse 1.2s ease-in-out infinite; }

    /* Slide panel */
    .side-panel-enter { transform: translateX(100%); }
    .side-panel-active { transform: translateX(0); transition: transform 0.25s ease-out; }

    /* Scrollbar */
    .offline-panel-wrapper ::-webkit-scrollbar { width: 6px; }
    .offline-panel-wrapper ::-webkit-scrollbar-track { background: transparent; }
    .offline-panel-wrapper ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }

    /* Shuffle ring */
    @keyframes shuffleRingSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes shuffleGlow {
        0%, 100% { box-shadow: 0 0 20px rgba(59,130,246,0.3), 0 0 60px rgba(59,130,246,0.1); }
        50% { box-shadow: 0 0 40px rgba(59,130,246,0.6), 0 0 80px rgba(59,130,246,0.2); }
    }
    @keyframes shuffleReveal {
        0% { transform: scale(0.5); opacity: 0; }
        60% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    .shuffle-ring-spin { animation: shuffleRingSpin 0.6s linear infinite; }
    .shuffle-glow { animation: shuffleGlow 0.8s ease-in-out infinite; }
    .shuffle-reveal { animation: shuffleReveal 0.5s ease-out forwards; }

    .shuffle-name-cycle {
        animation: nameCycle 0.08s steps(1) infinite;
    }
</style>
@endpush

@section('admin-content')
<div class="offline-panel-wrapper rounded-lg overflow-hidden"
     :class="{ 'is-fullscreen': isFullscreen }"
     x-data="offlineAuctionPanel()"
     x-init="init()"
     @keydown.window="handleKeydown($event)"
     id="offlinePanelWrapper">

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- MAIN CONTENT AREA --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <div class="flex flex-col h-[calc(100vh-120px)] relative" :class="isFullscreen ? '!h-screen' : ''">

        {{-- ROW 1: Main Content --}}
        <div class="flex-1 relative dot-bg flex items-center justify-center overflow-hidden">

            {{-- Auction/Tournament Logos --}}
            @if($auction->auction_logo_url || ($auction->tournament && $auction->tournament->logo_url))
            <div class="absolute top-4 left-4 z-30 flex items-center gap-3">
                @if($auction->auction_logo_url)
                    <img src="{{ $auction->auction_logo_url }}" alt="Auction Logo" class="h-10 object-contain">
                @endif
                @if($auction->tournament && $auction->tournament->logo_url)
                    <img src="{{ $auction->tournament->logo_url }}" alt="Tournament Logo" class="h-10 object-contain">
                @endif
            </div>
            @endif

            {{-- ── SHUFFLE ANIMATION OVERLAY ── --}}
            <template x-if="showShuffleOverlay">
                <div class="absolute inset-0 bg-gray-950/95 backdrop-blur-sm flex items-center justify-center z-40">
                    <div class="text-center">
                        {{-- Spinning ring --}}
                        <div class="relative w-52 h-52 mx-auto mb-8">
                            {{-- Outer spinning ring --}}
                            <div class="absolute inset-0 rounded-full border-4 border-transparent shuffle-glow"
                                 :class="shufflePhase === 'spinning' ? 'shuffle-ring-spin' : ''"
                                 style="border-top-color: #3b82f6; border-right-color: #8b5cf6;"></div>
                            {{-- Second ring --}}
                            <div class="absolute inset-3 rounded-full border-2 border-transparent"
                                 :class="shufflePhase === 'spinning' ? 'shuffle-ring-spin' : ''"
                                 style="border-bottom-color: #06b6d4; border-left-color: #10b981; animation-direction: reverse; animation-duration: 0.4s;"></div>
                            {{-- Center circle with player photo/name --}}
                            <div class="absolute inset-6 rounded-full bg-gray-800 border-2 flex items-center justify-center overflow-hidden"
                                 :class="shufflePhase === 'reveal' ? 'border-emerald-500 shuffle-reveal' : 'border-gray-600'">
                                <template x-if="shufflePhase === 'spinning'">
                                    <div class="text-center px-3">
                                        <div class="text-lg font-bold text-gray-300 truncate" x-text="shuffleDisplayName"></div>
                                    </div>
                                </template>
                                <template x-if="shufflePhase === 'reveal' && shuffleSelectedPlayer">
                                    <div class="w-full h-full">
                                        <template x-if="shuffleSelectedPlayer.player?.image_path">
                                            <img :src="'/storage/' + shuffleSelectedPlayer.player.image_path" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!shuffleSelectedPlayer.player?.image_path">
                                            <div class="w-full h-full flex items-center justify-center">
                                                <svg class="w-16 h-16 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Status text --}}
                        <div x-show="shufflePhase === 'spinning'" class="text-xl text-blue-400 font-semibold tracking-wider uppercase">
                            Selecting Player...
                        </div>
                        <div x-show="shufflePhase === 'reveal'" class="shuffle-reveal">
                            <div class="text-3xl font-black text-white mb-1" x-text="shuffleSelectedPlayer?.player?.name || ''"></div>
                            <div class="text-gray-400" x-text="shuffleSelectedPlayer?.player?.player_type?.name || ''"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── EMPTY STATE ── --}}
            <template x-if="!currentPlayer && !showSoldOverlay && !showUnsoldOverlay && !showSkippedOverlay && !showShuffleOverlay">
                <div class="text-center">
                    <div class="w-32 h-32 mx-auto mb-6 rounded-full border-2 border-dashed border-gray-700 flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-500 mb-2">Ready to Auction</h2>
                    <p class="text-gray-600">Enter player number or press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd></p>
                </div>
            </template>

            {{-- ══ SEALED ROUND BOARD ══
                 In offline mode the teams are in the room, not on their own screens — so
                 nobody can type a sealed amount and the round could only ever end in
                 "no entries". The organizer enters each team's amount here, exactly as they
                 already enter open bids for them.

                 Amounts are never shown before the reveal, even to the organizer: this
                 panel is usually on a projector. --}}
            <template x-if="sealed.active">
                <div class="absolute inset-0 z-20 bg-gray-950/95 backdrop-blur-sm overflow-y-auto px-8 py-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <div>
                            <div class="text-[11px] font-black uppercase tracking-[0.25em] text-purple-300">Sealed Round</div>
                            <h2 class="text-2xl font-extrabold text-white"
                                x-text="currentPlayer?.player?.name || 'Sealed bidding'"></h2>
                            <p class="text-sm text-gray-400">
                                Round <span x-text="sealed.round_number"></span> of <span x-text="sealed.total_rounds"></span>
                                &middot; minimum <span class="font-bold text-white" x-text="formatCurrency(sealed.floor)"></span>
                                &middot; steps of <span x-text="formatCurrency(sealed.step)"></span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div x-show="sealed.timer?.remaining !== null && sealed.timer?.remaining !== undefined"
                                 class="px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-gray-500">Time</div>
                                <div class="text-xl font-black text-white" x-text="sealed.timer?.remaining ?? '—'"></div>
                            </div>
                            <button x-show="sealed.state === 'pending'" @click="sealedCommand('open-entry')"
                                    class="px-3 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">Open Entry</button>
                            <button x-show="['pending','entry_open'].includes(sealed.state)" @click="sealedCommand('start')"
                                    class="px-3 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold">Start Round</button>
                            <button x-show="sealed.state === 'collecting'" @click="sealedCommand('lock')"
                                    class="px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">Lock &amp; Reveal</button>
                            <button x-show="sealed.state === 'revealed' && sealed.winner_team_id" @click="sealedCommand('award')"
                                    class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">Award</button>
                            <button x-show="sealed.state === 'tie'" @click="sealedCommand('start-rebid')"
                                    class="px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold">Start Re-bid</button>
                            <button x-show="sealed.state === 'awaiting_lot'" @click="sealedCommand('lot')"
                                    class="px-3 py-2 rounded-lg bg-gradient-to-r from-amber-500 to-orange-600 text-white text-xs font-black">Draw Lot</button>
                        </div>
                    </div>

                    {{-- Nobody entered. Both answers are defensible, so the organizer picks. --}}
                    <div x-show="sealed.state === 'no_entries'"
                         class="mb-5 p-4 rounded-xl bg-amber-900/30 border border-amber-700">
                        <p class="text-sm text-amber-200 font-semibold">No team entered this round.</p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button x-show="sealed.leader?.team_name"
                                    @click="sealedCommand('no-entries-decision', { choice: 'award_leader' })"
                                    class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                                Award to <span x-text="sealed.leader?.team_name"></span> at the threshold
                            </button>
                            <button @click="sealedCommand('no-entries-decision', { choice: 'unsold' })"
                                    class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-500 text-white text-xs font-bold">Mark unsold</button>
                        </div>
                    </div>

                    <div x-show="sealed.state === 'tie'" class="mb-5 p-4 rounded-xl bg-amber-900/30 border border-amber-700">
                        <p class="text-sm text-amber-200 font-semibold">
                            Tie at <span x-text="formatCurrency(sealed.tie_amount)"></span> —
                            the tied teams must bid again above it.
                        </p>
                    </div>

                    {{-- The board. One row per team. --}}
                    <div class="space-y-2">
                        <template x-for="row in (sealed.entries || [])" :key="row.entry_id">
                            <div class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl border transition-colors"
                                 :class="row.withdrawn
                                    ? 'border-gray-800 bg-gray-900/60 opacity-50'
                                    : (row.is_tied ? 'border-amber-500 bg-amber-900/20 ring-2 ring-amber-400/40'
                                                   : 'border-gray-700 bg-gray-900')">
                                <template x-if="row.team_logo">
                                    <img :src="row.team_logo" class="w-10 h-10 rounded-full object-cover border border-gray-700">
                                </template>
                                <template x-if="!row.team_logo">
                                    <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-400"
                                         x-text="(row.team_name || '??').substring(0,2).toUpperCase()"></div>
                                </template>

                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-white truncate" x-text="row.team_name"></div>
                                    <div class="text-[11px] text-gray-500">
                                        Max on this player
                                        <span class="font-semibold text-amber-400" x-text="formatCurrency(row.binding_ceiling)"></span>
                                        <span x-show="row.adjusted_count > 0" class="text-gray-600">
                                            &middot; <span x-text="row.adjusted_count"></span> admin edit(s)
                                        </span>
                                    </div>
                                </div>

                                {{-- Status, never an amount, until the reveal. --}}
                                <div class="text-center min-w-[7rem]">
                                    <template x-if="!sealed.revealed">
                                        <span class="px-2 py-1 rounded text-[10px] font-black uppercase tracking-wider"
                                              :class="row.withdrawn ? 'bg-gray-800 text-gray-500'
                                                    : (row.submitted ? 'bg-emerald-600/20 text-emerald-300'
                                                                     : 'bg-gray-800 text-gray-400')"
                                              x-text="row.withdrawn ? 'Withdrawn' : (row.submitted ? 'Submitted' : 'Not in')"></span>
                                    </template>
                                    <template x-if="sealed.revealed">
                                        <div>
                                            <div class="text-lg font-black"
                                                 :class="row.is_tied ? 'text-amber-300' : 'text-white'"
                                                 x-text="row.amount !== null && row.amount !== undefined ? formatCurrency(row.amount) : '—'"></div>
                                            <div x-show="row.withdrawn" class="text-[10px] uppercase text-gray-500">Withdrawn</div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Amount entry. Hidden once revealed: editing a revealed
                                     board is rewriting history, and the tool for that is a
                                     recorded manual resolution. --}}
                                <div x-show="!sealed.revealed && !row.withdrawn && sealed.state === 'collecting'"
                                     class="flex items-center gap-1">
                                    <button @click="sealedEntry(row.entry_id, 'adjust', { direction: 'down' })"
                                            class="w-8 h-8 rounded bg-gray-800 hover:bg-gray-700 text-white font-bold">&minus;</button>
                                    <input type="number" step="any" min="0"
                                           :placeholder="toM(sealed.floor)"
                                           x-model="sealedCustom[row.entry_id]"
                                           @keydown.enter.prevent="sealedSubmitCustom(row.entry_id)"
                                           class="w-24 px-2 py-1.5 rounded bg-gray-800 border border-gray-700 text-white text-sm text-center"
                                           title="Amount in millions">
                                    <button @click="sealedEntry(row.entry_id, 'adjust', { direction: 'up' })"
                                            class="w-8 h-8 rounded bg-gray-800 hover:bg-gray-700 text-white font-bold">+</button>
                                    <button @click="sealedSubmitCustom(row.entry_id)"
                                            class="px-2.5 py-1.5 rounded bg-cyan-600 hover:bg-cyan-500 text-white text-[11px] font-bold">Set</button>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button x-show="!row.withdrawn && !sealed.revealed"
                                            @click="sealedEntry(row.entry_id, 'withdraw')"
                                            class="px-2 py-1 rounded text-[11px] font-semibold text-red-400 hover:bg-red-900/30">Withdraw</button>
                                    <button x-show="row.withdrawn"
                                            @click="sealedEntry(row.entry_id, 'reinstate')"
                                            class="px-2 py-1 rounded text-[11px] font-semibold text-emerald-400 hover:bg-emerald-900/30">Restore</button>
                                </div>
                            </div>
                        </template>
                        <p x-show="!(sealed.entries || []).length" class="text-sm text-gray-500 py-6 text-center">
                            Press <strong>Open Entry</strong> to bring the teams into this round.
                        </p>
                    </div>
                </div>
            </template>

            {{-- ── ACTIVE PLAYER (stays visible behind overlays) ── --}}
            <template x-if="currentPlayer">
                <div class="flex items-stretch px-12 w-full h-full">
                    {{-- LEFT: Player Photo + Info --}}
                    <div class="flex-1 flex items-center gap-10">
                        {{-- Player Photo --}}
                        <div class="flex-shrink-0">
                            <div class="w-64 h-80 rounded-2xl overflow-hidden bg-gray-800 border-2 border-gray-700 shadow-2xl">
                                <template x-if="currentPlayer.player?.image_path">
                                    <img :src="'/storage/' + currentPlayer.player.image_path" class="w-full h-full object-cover" :alt="currentPlayer.player?.name">
                                </template>
                                <template x-if="!currentPlayer.player?.image_path">
                                    <div class="w-full h-full flex items-center justify-center bg-gray-800">
                                        <svg class="w-24 h-24 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Player Details --}}
                        <div class="space-y-4">
                            <h1 class="text-5xl font-extrabold tracking-tight" x-text="currentPlayer.player?.name || 'Unknown'"></h1>
                            <div class="flex items-center gap-3 text-lg text-gray-400">
                                <span x-text="currentPlayer.player?.player_type?.name || ''"></span>
                                <template x-if="currentPlayer.player?.batting_profile?.batting_style">
                                    <span class="flex items-center gap-1">
                                        <span class="text-gray-600">&bull;</span>
                                        <span x-text="currentPlayer.player.batting_profile.batting_style"></span>
                                    </span>
                                </template>
                                <template x-if="currentPlayer.player?.bowling_profile?.bowling_style">
                                    <span class="flex items-center gap-1">
                                        <span class="text-gray-600">&bull;</span>
                                        <span x-text="currentPlayer.player.bowling_profile.bowling_style"></span>
                                    </span>
                                </template>
                            </div>
                            <div class="text-gray-600 text-sm">
                                ID: <span x-text="currentPlayer.id"></span>
                            </div>

                            {{-- Player Stats --}}
                            <template x-if="hasAnyStats()">
                                <div class="flex gap-3 mt-1">
                                    <template x-if="currentPlayer.player?.total_matches != null">
                                        <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                            <div class="text-2xl font-black text-blue-400 leading-none" x-text="currentPlayer.player.total_matches"></div>
                                            <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Matches</div>
                                        </div>
                                    </template>
                                    <template x-if="currentPlayer.player?.total_runs != null">
                                        <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                            <div class="text-2xl font-black text-amber-400 leading-none" x-text="currentPlayer.player.total_runs"></div>
                                            <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Runs</div>
                                        </div>
                                    </template>
                                    <template x-if="currentPlayer.player?.total_wickets != null">
                                        <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                            <div class="text-2xl font-black text-emerald-400 leading-none" x-text="currentPlayer.player.total_wickets"></div>
                                            <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Wickets</div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- Base Price (editable) --}}
                            <div class="mt-4 inline-block border rounded-xl px-6 py-3 cursor-pointer transition-colors"
                                 :class="editingBasePrice ? 'border-yellow-500/50 bg-yellow-500/10' : 'border-blue-500/30 bg-blue-500/10'"
                                 @dblclick="startEditBasePrice()">
                                <div class="text-xs uppercase tracking-widest mb-0.5 flex items-center gap-2"
                                     :class="editingBasePrice ? 'text-yellow-400' : 'text-blue-400'">
                                    Base Price
                                    <span x-show="!editingBasePrice" class="text-gray-600 text-[10px] normal-case tracking-normal">(dbl-click to edit)</span>
                                </div>
                                <template x-if="!editingBasePrice">
                                    <div class="text-3xl font-black text-blue-400" x-text="formatCurrency(basePrice)"></div>
                                </template>
                                <template x-if="editingBasePrice">
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model.number="editBasePriceValue"
                                               @focus="shortcutsEnabled = false" @blur="shortcutsEnabled = true"
                                               @keydown.enter="saveBasePrice()" @keydown.escape="editingBasePrice = false; shortcutsEnabled = true"
                                               class="w-32 bg-gray-800 border border-yellow-500/50 rounded px-3 py-1.5 text-2xl font-black text-yellow-400 focus:outline-none font-mono"
                                               min="0" step="1000">
                                        <button @click="saveBasePrice()" class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-500 text-white text-sm font-semibold rounded transition-colors">Save</button>
                                        <button @click="editingBasePrice = false; shortcutsEnabled = true" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded transition-colors">Cancel</button>
                                    </div>
                                </template>
                            </div>

                            {{-- Next increment --}}
                            <div class="text-gray-500 text-sm">
                                Next bid: <span class="text-gray-300 font-semibold" x-text="formatCurrency((currentBidAmount || basePrice) + getIncrementForPrice(currentBidAmount || basePrice))"></span>
                                <span class="text-gray-600">(+<span x-text="formatCurrency(getIncrementForPrice(currentBidAmount || basePrice))"></span>)</span>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: Current Bid + Team Details --}}
                    <div class="w-80 flex items-center justify-center flex-shrink-0">
                        {{-- No bids yet --}}
                        <template x-if="!currentBidTeamId">
                            <div class="text-center p-8 border-2 border-dashed border-gray-700 rounded-2xl w-full">
                                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-800 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="text-gray-500 text-sm">Waiting for bids</div>
                                <div class="text-gray-600 text-xs mt-1">Click a team or press 1-9</div>
                            </div>
                        </template>

                        {{-- Has bids: Team + Amount --}}
                        <template x-if="currentBidTeamId">
                            <div class="text-center w-full">
                                {{-- Team Logo --}}
                                <div class="w-28 h-28 mx-auto mb-4 rounded-full overflow-hidden border-4 border-emerald-500 shadow-lg shadow-emerald-500/20 bg-gray-800 team-pulse">
                                    <template x-if="getTeamLogo(getCurrentBidTeam())">
                                        <img :src="getTeamLogo(getCurrentBidTeam())" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!getTeamLogo(getCurrentBidTeam())">
                                        <div class="w-full h-full flex items-center justify-center text-3xl font-black text-emerald-400" x-text="getCurrentBidTeam()?.name?.substring(0,2).toUpperCase()"></div>
                                    </template>
                                </div>

                                {{-- Team Name --}}
                                <div class="text-xl font-bold text-emerald-300 mb-4" x-text="getCurrentBidTeam()?.name"></div>

                                {{-- Current Bid Amount --}}
                                <div class="border-2 border-emerald-500/50 bg-emerald-500/10 rounded-2xl px-6 py-5 backdrop-blur-sm">
                                    <div class="text-xs uppercase tracking-widest text-emerald-400 mb-1">Current Bid</div>
                                    <div class="text-5xl font-black text-emerald-400"
                                         :class="bidJustChanged ? 'bid-flash' : ''"
                                         x-text="formatCurrency(currentBidAmount)"></div>
                                </div>

                                {{-- Team Budget --}}
                                <div class="mt-3 text-sm text-gray-500">
                                    Budget left: <span class="text-gray-400" x-text="formatCurrency(getCurrentBidTeam()?.remaining_budget)"></span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    Players: <span class="text-gray-400" x-text="getCurrentBidTeam()?.players_bought || 0"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ── SOLD OVERLAY ── --}}
            <template x-if="showSoldOverlay">
                <div class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center z-30" @click.self="dismissOverlays()">
                    <div class="text-center space-y-6">
                        {{-- Player photo --}}
                        <div class="w-40 h-40 mx-auto rounded-full overflow-hidden border-4 border-emerald-500 shadow-lg shadow-emerald-500/20">
                            <template x-if="soldPlayerImage">
                                <img :src="'/storage/' + soldPlayerImage" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!soldPlayerImage">
                                <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            </template>
                        </div>

                        {{-- SOLD stamp --}}
                        <div class="sold-stamp inline-block bg-emerald-500 text-white px-8 py-3 text-4xl font-black tracking-wider rounded-lg uppercase" style="transform: rotate(-12deg);">
                            SOLD!
                        </div>

                        <h2 class="text-4xl font-bold" x-text="soldPlayerName"></h2>
                        <div class="text-5xl font-black text-emerald-400" x-text="formatCurrency(soldPrice)"></div>

                        {{-- Team info --}}
                        <div class="flex items-center justify-center gap-3">
                            <template x-if="soldTeamLogo">
                                <img :src="'/storage/' + soldTeamLogo" class="w-12 h-12 rounded-full object-cover border-2 border-gray-600">
                            </template>
                            <span class="text-2xl text-gray-300" x-text="soldTeamName"></span>
                        </div>
                        <div class="text-gray-500" x-text="soldTeamPlayerCount + ' player(s) in team'"></div>

                        {{-- Reauction button --}}
                        <button @click="reAuctionLastPlayer()" class="mt-4 px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg font-semibold transition-colors">
                            Re-Auction (R)
                        </button>
                    </div>
                </div>
            </template>

            {{-- ── UNSOLD OVERLAY ── --}}
            <template x-if="showUnsoldOverlay">
                <div class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center z-30" @click.self="dismissOverlays()">
                    <div class="text-center space-y-6">
                        {{-- Player photo --}}
                        <div class="w-40 h-40 mx-auto rounded-full overflow-hidden border-4 border-red-500 shadow-lg shadow-red-500/20">
                            <template x-if="unsoldPlayerImage">
                                <img :src="'/storage/' + unsoldPlayerImage" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!unsoldPlayerImage">
                                <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            </template>
                        </div>

                        {{-- UNSOLD stamp --}}
                        <div class="unsold-stamp inline-block bg-red-500 text-white px-6 py-3 text-3xl font-black tracking-wider rounded-lg uppercase" style="transform: rotate(-12deg);">
                            Remained Unsold
                        </div>

                        <h2 class="text-4xl font-bold" x-text="unsoldPlayerName"></h2>

                        {{-- Reauction button --}}
                        <button @click="reAuctionLastPlayer()" class="mt-4 px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg font-semibold transition-colors">
                            Re-Auction (R)
                        </button>
                    </div>
                </div>
            </template>

            {{-- ── SKIPPED OVERLAY ── --}}
            <template x-if="showSkippedOverlay">
                <div class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center z-30" @click.self="dismissOverlays()">
                    <div class="text-center space-y-6">
                        {{-- Player photo --}}
                        <div class="w-40 h-40 mx-auto rounded-full overflow-hidden border-4 border-orange-500 shadow-lg shadow-orange-500/20">
                            <template x-if="skippedPlayerImage">
                                <img :src="'/storage/' + skippedPlayerImage" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!skippedPlayerImage">
                                <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            </template>
                        </div>

                        {{-- SKIPPED stamp --}}
                        <div class="unsold-stamp inline-block bg-orange-500 text-white px-6 py-3 text-3xl font-black tracking-wider rounded-lg uppercase" style="transform: rotate(-12deg);">
                            Skipped
                        </div>

                        <h2 class="text-4xl font-bold" x-text="skippedPlayerName"></h2>

                        {{-- Reauction button --}}
                        <button @click="reAuctionLastPlayer()" class="mt-4 px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg font-semibold transition-colors">
                            Re-Auction (R)
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ROW 2: TEAM BID BUBBLES --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- One click on a logo = one increment for that team. Its own band above the
             toolbar rather than wedged inside it, so logos are big enough to hit
             reliably in a live room. Two rows, column-filled, equal-width columns. --}}
        <div class="bg-gray-900/60 border-t border-gray-800 px-4 py-3 flex-shrink-0">
            <div class="grid grid-rows-2 grid-flow-col auto-cols-fr gap-x-3 gap-y-4 justify-items-center">
                <template x-for="(team, idx) in teams" :key="team.id">
                    <button @click="bidForTeam(team.id)"
                            :disabled="isTeamBidDisabled(team)"
                            class="relative group rounded-full border-2 bg-gray-800 flex items-center justify-center transition-all duration-200 flex-shrink-0"
                            :class="[
                                currentBidTeamId == team.id
                                    ? 'w-[62px] h-[62px] border-emerald-400 team-pulse scale-105'
                                    : 'w-[52px] h-[52px] border-gray-600 hover:border-gray-300 hover:scale-105',
                                team.excluded ? 'border-amber-600/70' : '',
                                isTeamBidDisabled(team) ? 'opacity-45 cursor-not-allowed hover:scale-100' : ''
                            ]"
                            :title="teamTooltip(team)">

                        {{-- Logo fills the bubble. --}}
                        <span class="absolute inset-0 rounded-full overflow-hidden flex items-center justify-center"
                              :class="team.excluded ? 'grayscale' : ''">
                            <template x-if="getTeamLogo(team)">
                                <img :src="getTeamLogo(team)" :alt="team.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!getTeamLogo(team)">
                                <span class="text-[11px] font-bold leading-none"
                                      :style="'color:' + getTeamColor(idx)"
                                      x-text="(team.short_name || team.name).substring(0, 3).toUpperCase()"></span>
                            </template>

                            {{-- Priced out of this player under the squad-reserve rule. --}}
                            <template x-if="team.excluded">
                                <span class="absolute inset-0 flex items-center justify-center bg-black/65 text-amber-400 text-sm">&#128274;</span>
                            </template>
                        </span>

                        {{-- Squad count, riding the top edge. --}}
                        <span x-show="team.slots_required"
                              class="absolute -top-1.5 -right-1 z-10 px-1.5 rounded-full text-[9px] font-mono font-bold leading-[14px] border border-gray-900"
                              :class="(team.slots_remaining || 0) > 0 ? 'bg-gray-700 text-gray-200' : 'bg-emerald-600 text-white'"
                              x-text="(team.players_bought || 0) + '/' + team.slots_required"></span>

                        {{-- Purse, riding the bottom edge. --}}
                        <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 z-10 px-1.5 rounded-full text-[10px] font-mono font-bold leading-[15px] whitespace-nowrap border border-gray-900"
                              :class="team.excluded ? 'bg-amber-500 text-black' : 'bg-emerald-600 text-white'"
                              x-text="formatCurrency(team.remaining_budget)"></span>

                        {{-- Keyboard shortcut hint. --}}
                        <span class="absolute -top-1.5 -left-1 z-10 w-[15px] h-[15px] bg-gray-700 border border-gray-900 rounded-full text-[9px] font-mono flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                              x-text="getTeamShortcut(idx)"></span>
                    </button>
                </template>
            </div>

            <p x-show="!teams.length" class="text-center text-xs text-gray-500 py-2">
                No teams in this tournament yet.
            </p>
        </div>

        {{-- ═══════════════════════════════════════════════════ --}}
        {{-- ROW 3: BOTTOM TOOLBAR --}}
        {{-- ═══════════════════════════════════════════════════ --}}
        <div class="h-16 bg-gray-900 border-t border-gray-800 flex items-center px-4 gap-3 flex-shrink-0">

            {{-- 1. Player Input --}}
            <div class="flex items-center gap-1.5">
                <span class="text-gray-500 font-mono text-lg">#</span>
                <input type="text" x-model="playerNumberInput"
                       @focus="shortcutsEnabled = false" @blur="shortcutsEnabled = true"
                       @keydown.enter="loadPlayerByNumber()"
                       placeholder="ID"
                       class="w-16 bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                <button @click="loadPlayerByNumber()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded transition-colors whitespace-nowrap">
                    LOAD
                </button>
            </div>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- NEW button --}}
            <button @click="loadNextPlayer()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded transition-colors whitespace-nowrap">
                NEW (N)
            </button>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- Pool lock + timer. The auction serves one pool at a time. --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                <template x-if="!activePool">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] uppercase text-amber-400 font-semibold">No pool</span>
                        <template x-for="p in pools.filter(p => p.is_enabled && p.waiting > 0)" :key="p.id">
                            <button @click="activatePool(p.id)"
                                    class="px-2 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-semibold rounded transition whitespace-nowrap">
                                Start <span x-text="p.name"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="activePool">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 bg-indigo-600/25 border border-indigo-500/50 rounded text-[11px] font-bold text-indigo-300 whitespace-nowrap"
                              x-text="activePool.name"></span>
                        <span class="text-[11px] font-mono text-gray-400 whitespace-nowrap">
                            <span x-text="activePool.done"></span>/<span x-text="activePool.total"></span>
                        </span>
                        <template x-if="activePool.exhausted">
                            <div class="flex items-center gap-1.5">
                                <button @click="completeActivePool()"
                                        class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-semibold rounded transition whitespace-nowrap">Close</button>
                                <template x-if="nextPool">
                                    <button @click="activatePool(nextPool.id)"
                                            class="px-2 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-semibold rounded transition whitespace-nowrap">
                                        Start <span x-text="nextPool.name"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Offline mode may run without a clock. --}}
                <button @click="toggleTimer()"
                        class="px-2 py-1 rounded text-[10px] font-semibold transition whitespace-nowrap"
                        :class="timerEnabled ? 'bg-blue-600/25 border border-blue-500/50 text-blue-300' : 'bg-gray-800 border border-gray-700 text-gray-500'"
                        :title="timerEnabled ? 'Turn the bid timer off' : 'Turn the bid timer on'">
                    TIMER <span x-text="timerEnabled ? 'ON' : 'OFF'"></span>
                </button>
                <span x-show="timerEnabled && timerSecondsRemaining !== null"
                      class="font-mono whitespace-nowrap font-bold"
                      :class="finalCall
                        ? (finalCall.is_final ? 'text-red-400 text-lg' : 'text-amber-300 text-base')
                        : (timerSecondsRemaining <= 5 ? 'text-red-400 text-[11px]' : 'text-gray-400 text-[11px]')"
                      x-text="timerSecondsRemaining + 's'"></span>

                {{-- Closing call, matching the audience display. --}}
                <template x-if="finalCall">
                    <span class="px-2 py-1 rounded text-[11px] font-black tracking-widest uppercase whitespace-nowrap final-call-pulse"
                          :class="finalCall.is_final ? 'bg-red-600 text-white' : 'bg-amber-500 text-black'"
                          x-text="finalCall.label"></span>
                </template>
            </div>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- 2. Base Price Badge (editable) --}}
            <template x-if="!editingAuctionBase">
                <div @click="startEditAuctionBase()"
                     class="px-3 py-1 bg-yellow-500/20 border border-yellow-500/40 rounded text-yellow-400 text-sm font-semibold whitespace-nowrap cursor-pointer hover:bg-yellow-500/30 transition-colors"
                     title="Click to edit base price">
                    BASE: <span x-text="formatCurrency(auctionBasePrice)"></span>
                </div>
            </template>
            <template x-if="editingAuctionBase">
                <div class="flex items-center gap-1.5 bg-yellow-500/10 border border-yellow-500/50 rounded px-2 py-0.5">
                    <span class="text-yellow-400 text-xs font-semibold">BASE:</span>
                    <input type="number" x-model.number="editAuctionBaseValue"
                           x-ref="auctionBaseInput"
                           @focus="shortcutsEnabled = false" @blur="shortcutsEnabled = true"
                           @keydown.enter="saveAuctionBasePrice()" @keydown.escape="editingAuctionBase = false; shortcutsEnabled = true"
                           class="w-24 bg-gray-800 border border-yellow-500/50 rounded px-2 py-1 text-sm font-bold text-yellow-400 focus:outline-none font-mono"
                           min="0" step="1000">
                    <span class="text-yellow-300 text-xs font-mono" x-text="formatCurrency(editAuctionBaseValue)"></span>
                    <button @click="saveAuctionBasePrice()" class="px-2 py-1 bg-yellow-600 hover:bg-yellow-500 text-white text-xs font-semibold rounded transition-colors">OK</button>
                    <button @click="editingAuctionBase = false; shortcutsEnabled = true" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-white text-xs rounded transition-colors">X</button>
                </div>
            </template>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- Sealed round. Offline bidding reaches the closed-bid threshold exactly as
                 online bidding does, and this panel previously had no way to run the
                 round — the price simply froze with no explanation. --}}
            <template x-if="sealed.active">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-purple-500/15 border border-purple-500/40">
                    <div class="text-left leading-tight">
                        <div class="text-[10px] font-black uppercase tracking-[0.2em] text-purple-300">Sealed Round</div>
                        <div class="text-[11px] text-gray-300">
                            Round <span x-text="sealed.round_number"></span>/<span x-text="sealed.total_rounds"></span>
                            &middot; floor <span x-text="formatCurrency(sealed.floor)"></span>
                            &middot; <span x-text="sealed.counts?.submitted ?? 0"></span> in
                        </div>
                    </div>

                    <button x-show="sealed.state === 'pending'" @click="sealedCommand('open-entry')"
                            class="px-2.5 py-1 rounded bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-bold">Open Entry</button>
                    <button x-show="['pending','entry_open'].includes(sealed.state)" @click="sealedCommand('start')"
                            class="px-2.5 py-1 rounded bg-cyan-600 hover:bg-cyan-500 text-white text-[11px] font-bold">Start</button>
                    <button x-show="sealed.state === 'collecting'" @click="sealedCommand('lock')"
                            class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-[11px] font-bold">Lock &amp; Reveal</button>
                    <button x-show="sealed.state === 'revealed' && sealed.winner_team_id" @click="sealedCommand('award')"
                            class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold">Award</button>
                    <button x-show="sealed.state === 'tie'" @click="sealedCommand('start-rebid')"
                            class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-[11px] font-bold">Re-bid</button>
                    <button x-show="sealed.state === 'awaiting_lot'" @click="sealedCommand('lot')"
                            class="px-2.5 py-1 rounded bg-gradient-to-r from-amber-500 to-orange-600 text-white text-[11px] font-black">Draw Lot</button>

                    <a :href="`/admin/organizer/auction/{{ $auction->id }}/panel`"
                       class="px-2.5 py-1 rounded bg-gray-700 hover:bg-gray-600 text-gray-200 text-[11px] font-bold"
                       title="The full board, with per-team controls">Full board</a>
                </div>
            </template>

            {{-- Teams live in their own strip below, so the toolbar just spaces out. --}}
            <div class="flex-1"></div>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- 4. Sell & Unsold --}}
            <button @click="sellCurrentPlayer()"
                    :disabled="!currentBidTeamId || showSoldOverlay || showUnsoldOverlay || showSkippedOverlay"
                    :class="(currentBidTeamId && !showSoldOverlay && !showUnsoldOverlay && !showSkippedOverlay) ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-700 cursor-not-allowed opacity-50'"
                    class="px-4 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap">
                SOLD (S)
            </button>
            <button @click="passCurrentPlayer()"
                    :disabled="!currentPlayer || showSoldOverlay || showUnsoldOverlay || showSkippedOverlay || !!currentBidTeamId"
                    :class="(currentPlayer && !showSoldOverlay && !showUnsoldOverlay && !showSkippedOverlay && !currentBidTeamId) ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 cursor-not-allowed opacity-50'"
                    class="px-4 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap">
                UNSOLD (U)
            </button>
            <button @click="skipCurrentPlayer()"
                    :disabled="!currentPlayer || showSoldOverlay || showUnsoldOverlay || showSkippedOverlay"
                    :class="(currentPlayer && !showSoldOverlay && !showUnsoldOverlay && !showSkippedOverlay) ? 'bg-orange-600 hover:bg-orange-500' : 'bg-gray-700 cursor-not-allowed opacity-50'"
                    class="px-4 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap">
                SKIP (K)
            </button>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- Re-auction round button (shown when no waiting players but unsold/skipped exist) --}}
            <template x-if="availablePlayers.length === 0 && !currentPlayer && ((stats.unsold_count || 0) + (stats.skipped_count || 0)) > 0">
                <button @click="startReAuctionRound()"
                        class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 text-white text-sm font-bold rounded transition-colors whitespace-nowrap animate-pulse">
                    RE-AUCTION ROUND (<span x-text="(stats.unsold_count || 0) + (stats.skipped_count || 0)"></span>)
                </button>
            </template>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- 5. Navigation buttons --}}
            <div class="flex items-center gap-1">
                <button @click="showSidePanelFn('summary')" :class="sidePanel === 'summary' ? 'bg-gray-700' : 'bg-gray-800 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold text-gray-400 transition-colors" title="Team Summary">S</button>
                <button @click="showSidePanelFn('players')" :class="sidePanel === 'players' ? 'bg-gray-700' : 'bg-gray-800 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold text-gray-400 transition-colors" title="All Players">P</button>
                <button @click="showSidePanelFn('teams')" :class="sidePanel === 'teams' ? 'bg-gray-700' : 'bg-gray-800 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold text-gray-400 transition-colors" title="Team Details">T</button>
                <button @click="showSidePanelFn('auction')" :class="sidePanel === 'auction' ? 'bg-gray-700' : 'bg-gray-800 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold text-gray-400 transition-colors" title="Auction Stats">A</button>

                {{-- Rescue hatch, same as the main panel. A plain link rather than a fetch:
                     a download must work even if polling has fallen over, which is exactly
                     when it is wanted. Read-only, so it is always safe to press. --}}
                <a href="/admin/organizer/auction/{{ $auction->id }}/api/export"
                   class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors bg-gray-800 text-gray-400 hover:bg-emerald-600 hover:text-white"
                   title="Download this auction as a spreadsheet (players, teams, spend and what is left)">&darr;</a>
            </div>

            <div class="w-px h-8 bg-gray-700"></div>

            {{-- 6. Fullscreen toggle --}}
            <button @click="toggleFullscreen()" class="w-8 h-8 rounded flex items-center justify-center text-gray-400 hover:text-white transition-colors" :class="isFullscreen ? 'bg-blue-600 text-white' : 'bg-gray-800 hover:bg-gray-700'" title="Fullscreen (F)">
                <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
                <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4"/></svg>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- SIDE PANEL (slides from right) --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <template x-if="sidePanel">
        <div class="absolute inset-0 z-40" @click.self="sidePanel = null">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40"></div>

            {{-- Panel --}}
            <div class="absolute right-0 top-0 bottom-16 w-[500px] bg-gray-900 border-l border-gray-800 overflow-y-auto shadow-2xl side-panel-active">
                {{-- Panel Header --}}
                <div class="sticky top-0 bg-gray-900 border-b border-gray-800 px-5 py-4 flex items-center justify-between z-10">
                    <h3 class="text-lg font-bold">
                        <span x-show="sidePanel === 'summary'">Team Summary</span>
                        <span x-show="sidePanel === 'players'">All Players</span>
                        <span x-show="sidePanel === 'teams'">Team Details</span>
                        <span x-show="sidePanel === 'auction'">Auction Stats</span>
                    </h3>
                    <button @click="sidePanel = null" class="text-gray-500 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- ═══ SUMMARY PANEL ═══ --}}
                <div x-show="sidePanel === 'summary'" class="p-5 space-y-3">
                    <template x-for="team in teams" :key="team.id">
                        <div class="bg-gray-800 rounded-xl p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <template x-if="getTeamLogo(team)">
                                    <img :src="getTeamLogo(team)" class="w-10 h-10 rounded-full object-cover border border-gray-600">
                                </template>
                                <template x-if="!getTeamLogo(team)">
                                    <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-sm font-bold text-gray-400" x-text="team.name.substring(0,2).toUpperCase()"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold truncate" x-text="team.name"></div>
                                    <div class="text-sm text-gray-400">
                                        <span x-text="team.players_bought || 0"></span> players &bull;
                                        <span x-text="formatCurrency(team.total_spent || 0)"></span> spent
                                        <span x-show="team.retained_expected > 0"
                                              :class="team.retained_count !== team.retained_expected ? 'text-amber-400' : 'text-gray-500'"
                                              :title="`${team.retained_count} retained; ${team.retained_expected} expected.`">
                                            &bull; <span x-text="team.retained_count"></span>/<span x-text="team.retained_expected"></span> kept
                                        </span>
                                    </div>
                                </div>
                            </div>
                            {{-- Budget progress bar, against THIS team's allocation rather
                                 than the auction-wide cap it used to divide by. --}}
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all"
                                     :style="'width:' + Math.max(0, Math.min(100, ((team.total_spent || 0) / (team.allocated || 1) * 100))) + '%'"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <span x-text="formatCurrency(team.remaining_budget)"></span> remaining
                                of <span x-text="formatCurrency(team.allocated)"></span>
                                <span x-show="team.retained_spent > 0" class="text-amber-500/80">
                                    (<span x-text="formatCurrency(team.retained_spent)"></span> retained)
                                </span>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ═══ PLAYERS PANEL ═══ --}}
                <div x-show="sidePanel === 'players'" class="p-5">
                    {{-- Filter tabs --}}
                    <div class="flex gap-1 mb-4">
                        <template x-for="f in ['all','waiting','sold','unsold','skipped']" :key="f">
                            <button @click="playerFilterStatus = f"
                                    :class="playerFilterStatus === f ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'"
                                    class="px-3 py-1 rounded text-sm font-medium capitalize transition-colors"
                                    x-text="f"></button>
                        </template>
                    </div>
                    {{-- Player list --}}
                    <div class="space-y-2 max-h-[calc(100vh-180px)] overflow-y-auto">
                        <template x-for="p in filteredPlayers()" :key="p.id">
                            <div class="bg-gray-800 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-gray-750 transition-colors"
                                 @click="loadPlayerById(p.id)">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gray-700 overflow-hidden flex-shrink-0">
                                        <template x-if="p.image_path">
                                            <img :src="'/storage/' + p.image_path" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!p.image_path">
                                            <div class="w-full h-full flex items-center justify-center text-gray-500 text-xs font-bold" x-text="p.name?.substring(0,2).toUpperCase()"></div>
                                        </template>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold truncate" x-text="p.name"></span>
                                            <span x-show="p.player_type" class="text-[10px] text-gray-500 bg-gray-700/50 px-1.5 py-0.5 rounded" x-text="p.player_type"></span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-0.5">
                                            <template x-if="p.sold_to_team">
                                                <span class="text-xs text-emerald-400" x-text="p.sold_to_team + ' — ' + formatCurrency(p.final_price)"></span>
                                            </template>
                                            <template x-if="!p.sold_to_team && p.base_price">
                                                <span class="text-xs text-gray-500">Base: <span class="text-gray-400" x-text="formatCurrency(p.base_price)"></span></span>
                                            </template>
                                        </div>
                                    </div>
                                    <span :class="{
                                            'bg-emerald-500/20 text-emerald-400': p.status === 'sold',
                                            'bg-red-500/20 text-red-400': p.status === 'unsold',
                                            'bg-blue-500/20 text-blue-400': p.status === 'on_auction',
                                            'bg-orange-500/20 text-orange-400': p.status === 'skipped',
                                            'bg-gray-700 text-gray-400': p.status === 'waiting'
                                          }"
                                          class="text-[10px] px-2 py-0.5 rounded-full font-medium uppercase flex-shrink-0" x-text="p.status"></span>
                                </div>
                                {{-- Compact stats row --}}
                                <template x-if="p.total_matches || p.total_runs || p.total_wickets">
                                    <div class="flex items-center gap-3 mt-1.5 ml-12 text-[10px]">
                                        <template x-if="p.total_matches != null">
                                            <span class="text-gray-500"><span class="text-blue-400 font-semibold" x-text="p.total_matches"></span> M</span>
                                        </template>
                                        <template x-if="p.total_runs != null">
                                            <span class="text-gray-500"><span class="text-amber-400 font-semibold" x-text="p.total_runs"></span> R</span>
                                        </template>
                                        <template x-if="p.total_wickets != null">
                                            <span class="text-gray-500"><span class="text-emerald-400 font-semibold" x-text="p.total_wickets"></span> W</span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ═══ TEAMS PANEL ═══ --}}
                <div x-show="sidePanel === 'teams'" class="p-5">
                    <template x-if="!selectedTeamDetail">
                        <div class="space-y-2">
                            <template x-for="team in teams" :key="team.id">
                                <button @click="selectTeamDetail(team)" class="w-full flex items-center gap-3 bg-gray-800 hover:bg-gray-750 rounded-lg px-4 py-3 text-left transition-colors">
                                    <template x-if="getTeamLogo(team)">
                                        <img :src="getTeamLogo(team)" class="w-10 h-10 rounded-full object-cover border border-gray-600">
                                    </template>
                                    <template x-if="!getTeamLogo(team)">
                                        <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-sm font-bold text-gray-400" x-text="team.name.substring(0,2).toUpperCase()"></div>
                                    </template>
                                    <div class="flex-1">
                                        <div class="font-semibold" x-text="team.name"></div>
                                        <div class="text-sm text-gray-500"><span x-text="team.players_bought || 0"></span> players</div>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </template>
                        </div>
                    </template>
                    <template x-if="selectedTeamDetail">
                        <div>
                            <button @click="selectedTeamDetail = null" class="flex items-center gap-1 text-sm text-gray-400 hover:text-white mb-4 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <div class="flex items-center gap-3 mb-4">
                                <template x-if="getTeamLogo(selectedTeamDetail)">
                                    <img :src="getTeamLogo(selectedTeamDetail)" class="w-12 h-12 rounded-full object-cover border border-gray-600">
                                </template>
                                <div>
                                    <div class="text-lg font-bold" x-text="selectedTeamDetail.name"></div>
                                    <div class="text-sm text-gray-400">Budget: <span x-text="formatCurrency(selectedTeamDetail.remaining_budget)"></span> remaining</div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <template x-for="p in teamDetailPlayers" :key="p.id">
                                    <div class="flex items-center gap-3 bg-gray-800 rounded-lg px-3 py-2">
                                        <div class="w-8 h-8 rounded-full bg-gray-700 overflow-hidden flex-shrink-0">
                                            <template x-if="p.image_path">
                                                <img :src="'/storage/' + p.image_path" class="w-full h-full object-cover">
                                            </template>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium truncate" x-text="p.name"></div>
                                        </div>
                                        <span class="text-sm text-emerald-400 font-semibold" x-text="formatCurrency(p.final_price)"></span>
                                    </div>
                                </template>
                                <div x-show="teamDetailPlayers.length === 0" class="text-gray-500 text-sm text-center py-4">No players bought yet</div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ═══ AUCTION STATS PANEL ═══ --}}
                <div x-show="sidePanel === 'auction'" class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-800 rounded-xl p-4 text-center">
                            <div class="text-3xl font-black text-white" x-text="stats.total_players || 0"></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Total Players</div>
                        </div>
                        <div class="bg-gray-800 rounded-xl p-4 text-center">
                            <div class="text-3xl font-black text-emerald-400" x-text="stats.sold_count || 0"></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Sold</div>
                        </div>
                        <div class="bg-gray-800 rounded-xl p-4 text-center">
                            <div class="text-3xl font-black text-red-400" x-text="stats.unsold_count || 0"></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Unsold</div>
                        </div>
                        <div class="bg-gray-800 rounded-xl p-4 text-center">
                            <div class="text-3xl font-black text-orange-400" x-text="stats.skipped_count || 0"></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Skipped</div>
                        </div>
                        <div class="bg-gray-800 rounded-xl p-4 text-center col-span-2">
                            <div class="text-3xl font-black text-blue-400" x-text="stats.waiting_count || 0"></div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">Waiting</div>
                        </div>
                    </div>

                    {{-- Revenue --}}
                    <div class="bg-gray-800 rounded-xl p-4">
                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Revenue</div>
                        <div class="text-2xl font-black text-yellow-400" x-text="formatCurrency(totalRevenue())"></div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4">
                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Average Price</div>
                        <div class="text-2xl font-black text-gray-300" x-text="stats.sold_count > 0 ? formatCurrency(totalRevenue() / stats.sold_count) : '—'"></div>
                    </div>

                    {{-- Completion --}}
                    <div class="bg-gray-800 rounded-xl p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs text-gray-500 uppercase tracking-wider">Completion</span>
                            <span class="text-sm font-bold" x-text="stats.total_players > 0 ? Math.round(((stats.sold_count + stats.unsold_count + (stats.skipped_count || 0)) / stats.total_players) * 100) + '%' : '0%'"></span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all"
                                 :style="'width:' + (stats.total_players > 0 ? ((stats.sold_count + stats.unsold_count + (stats.skipped_count || 0)) / stats.total_players * 100) : 0) + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Toasts and confirmations ──
         Same reason as the organizer panel: a native alert()/confirm() forces the browser
         out of fullscreen to display itself, so on a hall projector every confirmation
         dropped the screen back to a window. See that file for the full note. --}}
    <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-start gap-3 max-w-md px-4 py-3 rounded-xl shadow-2xl bg-gray-900 border border-white/10 border-l-4"
                 :class="{
                     'border-l-red-500': t.type === 'error',
                     'border-l-emerald-500': t.type === 'success',
                     'border-l-blue-500': t.type !== 'error' && t.type !== 'success',
                 }"
                 x-transition.opacity>
                <div>
                    <p x-show="t.title" x-text="t.title"
                       class="text-xs font-bold uppercase tracking-wide text-gray-400"></p>
                    <p class="text-sm text-white whitespace-pre-line" x-text="t.message"></p>
                </div>
                <button type="button" @click="toasts = toasts.filter(x => x.id !== t.id)"
                        class="text-gray-400 hover:text-white transition text-xs font-bold">✕</button>
            </div>
        </template>
    </div>

    {{-- click.self, not click.outside: the click that OPENS this dialog is still bubbling
         to document when Alpine registers an outside-click listener, so click.outside can
         dismiss the dialog with the very click that asked for it. A backdrop that only
         answers to clicks landing on itself has no such ordering to get wrong.
         Escape/Enter are handled in handleKeydown(), which also swallows the shortcuts. --}}
    <div x-show="confirmBox.open" x-cloak
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 p-4"
         @click.self="_settleConfirm(false)"
         x-transition.opacity>
        <div class="w-full max-w-md rounded-2xl bg-gray-900 border border-white/10 shadow-2xl p-6">
            <p class="text-sm font-bold uppercase tracking-wide"
               :class="confirmBox.danger ? 'text-red-400' : 'text-gray-400'"
               x-text="confirmBox.title"></p>
            <p class="mt-3 text-white text-sm whitespace-pre-line" x-text="confirmBox.message"></p>

            {{-- Plain yes/no: every action button on this panel. --}}
            <div class="mt-6 flex items-center justify-end gap-3" x-show="!confirmBox.choices">
                <button type="button" @click="_settleConfirm(false)"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-white/10 hover:bg-white/20 transition">
                    Cancel
                </button>
                <button type="button" @click="_settleConfirm(true)"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition"
                        :class="confirmBox.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'">
                    Confirm
                </button>
            </div>

            {{-- More than two ways out — the sealed-threshold question. Stacked full-width
                 because three answers with real sentences on them truncate to nothing side
                 by side at this panel's width. --}}
            <div class="mt-6 flex flex-col gap-2" x-show="confirmBox.choices" x-cloak>
                <template x-for="choice in (confirmBox.choices || [])" :key="choice.value">
                    <button type="button" @click="_settleConfirm(choice.value)"
                            class="w-full px-5 py-3 rounded-xl text-sm font-bold text-white text-left transition"
                            :class="choice.class || 'bg-white/10 hover:bg-white/20'">
                        <span x-text="choice.label"></span>
                        <span class="block text-xs font-normal opacity-75 mt-0.5"
                              x-show="choice.hint" x-text="choice.hint"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- ALPINE.JS COMPONENT --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <script>
    function offlineAuctionPanel() {
        return {
            /* ── In-page toast and confirm ──
               Replaces alert()/confirm(), which drop the browser out of fullscreen. */
            toasts: [],
            _toastSeq: 0,
            confirmBox: { open: false, title: '', message: '', danger: false, choices: null, _resolve: null },

            toast(message, type = 'info', title = null) {
                const id = ++this._toastSeq;
                this.toasts.push({ id, message, type, title });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, type === 'error' ? 6000 : 3600);
            },

            /**
             * Awaitable replacement for confirm(). Resolves true/false, or — when `choices`
             * is given — the chosen value, for a question with more than two answers.
             */
            askConfirm(message, { title = 'Confirm', danger = false, choices = null } = {}) {
                return new Promise((resolve) => {
                    this.confirmBox = { open: true, title, message, danger, choices, _resolve: resolve };
                });
            },

            _settleConfirm(answer) {
                const resolve = this.confirmBox._resolve;
                this.confirmBox = { open: false, title: '', message: '', danger: false, choices: null, _resolve: null };
                if (resolve) resolve(answer);
            },

            // Config
            auctionId: {{ $auction->id }},
            auctionBasePrice: {{ $auction->base_price ?? 0 }},
            bidRules: @json($bidRules ?? []),
            apiBase: '/admin/organizer/auction/{{ $auction->id }}',

            // Pool lock (server-owned; refreshed on every poll).
            activePool: null,
            nextPool: null,
            pools: [],

            // Timer (server-owned).
            timerEnabled: {{ $auction->timerApplies() ? 'true' : 'false' }},
            timerExpiryAction: '{{ $auction->timer_expiry_action ?? 'manual' }}',
            timerSecondsRemaining: null,
            timerExpired: false,
            _timerFiring: false,
            _timerFiredForPlayer: null,

            // What amounts are called, from the auction's settings.
            amountUnit: @json($auction->amountUnitConfig()),

            // Closing calls, thresholds from the server.
            finalCall: null,
            finalCallStages: @json($auction->finalCallStages()),
            _clockTick: null,

            // State
            auctionStatus: '{{ $auction->status }}',
            teams: @json($teams),
            availablePlayers: @json($availablePlayersCompact),

            currentPlayer: @json($currentPlayer),

            // Sealed round. This panel had no sealed support at all, so an offline
            // auction that crossed its threshold left the organizer with a frozen price
            // and no way to run the round.
            sealed: { active: false },
            currentBidAmount: {{ $currentPlayer?->current_price ?? 0 }},
            currentBidTeamId: {{ $currentPlayer?->current_bid_team_id ?? 'null' }},
            basePrice: {{ $currentPlayer?->base_price ?? 0 }},

            // Base price editing (per-player)
            editingBasePrice: false,
            editBasePriceValue: 0,

            // Auction base price editing (toolbar)
            editingAuctionBase: false,
            editAuctionBaseValue: 0,

            // Shuffle animation
            showShuffleOverlay: false,
            shufflePhase: 'spinning', // 'spinning' | 'reveal'
            shuffleDisplayName: '',
            shuffleSelectedPlayer: null,
            _shuffleInterval: null,

            // Overlays
            showSoldOverlay: false,
            showUnsoldOverlay: false,
            showSkippedOverlay: false,
            soldPlayerName: '', soldPlayerImage: null, soldPrice: 0,
            soldTeamName: '', soldTeamLogo: null, soldTeamPlayerCount: 0,
            unsoldPlayerName: '', unsoldPlayerImage: null,
            skippedPlayerName: '', skippedPlayerImage: null,
            lastActionPlayerId: null,

            // Fullscreen
            isFullscreen: false,

            // UI
            playerNumberInput: '',
            shortcutsEnabled: true,
            sidePanel: null,
            selectedTeamDetail: null,
            playerFilterStatus: 'all',
            allPlayersList: [],
            teamDetailPlayers: [],
            stats: @json($stats),

            // Internal
            _pollInterval: null,
            _lastCurrentPlayerId: {{ $currentPlayer?->id ?? 'null' }},
            _isBidding: false,
            bidJustChanged: false,
            _bidFlashTimeout: null,

            init() {
                this._pollInterval = setInterval(() => this.pollAuctionState(), 2000);
                document.addEventListener('fullscreenchange', () => {
                    this.isFullscreen = !!document.fullscreenElement;
                });
            },

            toggleFullscreen() {
                const el = document.getElementById('offlinePanelWrapper');
                if (!document.fullscreenElement) {
                    (el || document.documentElement).requestFullscreen().catch(() => {});
                } else {
                    document.exitFullscreen().catch(() => {});
                }
            },

            // ─── TEAM HELPERS ───
            getCurrentBidTeam() {
                if (!this.currentBidTeamId) return null;
                return this.teams.find(t => t.id == this.currentBidTeamId) || null;
            },

            getTeamLogo(team) {
                // logo_url is the resolved accessor from the server; team_logo is the raw
                // column, kept as a fallback for any payload that predates it.
                return team?.logo_url || (team?.team_logo ? '/storage/' + team.team_logo : null);
            },

            /**
             * A team cannot be bid for with no live player, while an overlay is showing,
             * when it already leads, or when the squad-reserve rule prices it out.
             */
            isTeamBidDisabled(team) {
                return !this.currentPlayer
                    || this.showSoldOverlay || this.showUnsoldOverlay || this.showSkippedOverlay
                    || this.currentBidTeamId == team.id
                    || !!team.excluded;
            },

            teamTooltip(team) {
                if (team.excluded && team.exclusion_reason) {
                    return `${team.name} — ${team.exclusion_reason}`;
                }
                const parts = [`${this.formatCurrency(team.remaining_budget)} left`];
                if (team.max_bid_allowed !== null && team.max_bid_allowed !== undefined
                    && team.max_bid_allowed < team.remaining_budget) {
                    parts.push(`max bid ${this.formatCurrency(team.max_bid_allowed)}`);
                }
                if (team.slots_required) {
                    parts.push(`${team.players_bought || 0}/${team.slots_required} squad`);
                }
                return `${team.name} (${parts.join(' · ')})`;
            },

            // ─── PLAYER STATS HELPER ───
            hasAnyStats() {
                const p = this.currentPlayer?.player;
                if (!p) return false;
                return p.total_matches != null || p.total_runs != null || p.total_wickets != null;
            },

            getTeamColor(idx) {
                const colors = ['#f87171','#fb923c','#facc15','#4ade80','#22d3ee','#818cf8','#c084fc','#f472b6','#a78bfa','#34d399'];
                return colors[idx % colors.length];
            },

            getTeamShortcut(idx) {
                if (idx < 9) return String(idx + 1);
                if (idx === 9) return '0';
                return '';
            },

            // ─── CURRENCY ───
            formatCurrency(amount) {
                // Shared K/M/B formatter with this auction's unit.
                return window.auctionAmount
                    ? window.auctionAmount(amount, this.amountUnit)
                    : String(Number(amount) || 0);
            },

            // ─── BID RULES ───
            getIncrementForPrice(price) {
                price = parseFloat(price) || 0;
                let increment = 0;
                for (const r of this.bidRules) {
                    const from = parseFloat(r.from) || 0;
                    const to = parseFloat(r.to) || Infinity;
                    const inc = parseFloat(r.increment) || 0;
                    if (price >= from && price <= to) { increment = inc; break; }
                }
                if (increment === 0) {
                    for (const r of this.bidRules) {
                        const from = parseFloat(r.from) || 0;
                        const inc = parseFloat(r.increment) || 0;
                        if (price < from) { increment = inc; break; }
                    }
                }
                return increment;
            },

            // ─── BASE PRICE EDITING ───
            startEditBasePrice() {
                if (!this.currentPlayer) return;
                this.editBasePriceValue = this.basePrice;
                this.editingBasePrice = true;
            },

            async saveBasePrice() {
                if (!this.currentPlayer || !this.editBasePriceValue) return;
                try {
                    const res = await fetch(this.apiBase + '/api/update-base-price', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auction_player_id: this.currentPlayer.id, base_price: this.editBasePriceValue })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.basePrice = data.base_price;
                        // If no bids yet, also update current bid amount
                        if (!this.currentBidTeamId) {
                            this.currentBidAmount = data.base_price;
                        }
                        this.editingBasePrice = false;
                        this.shortcutsEnabled = true;
                    } else {
                        this.toast(data.message || 'Failed to update base price', 'error');
                    }
                } catch (e) {
                    console.error('Update base price error:', e);
                }
            },

            // ─── BID FOR TEAM ───
            async bidForTeam(teamId) {
                if (!this.currentPlayer || this._isBidding) return;
                if (this.currentBidTeamId == teamId) return;
                this._isBidding = true;
                try {
                    const res = await fetch('/admin/auctions/add-bid', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auctionId: this.auctionId, playerID: this.currentPlayer.id, teamId: teamId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.currentBidAmount = data.current_price;
                        this.currentBidTeamId = teamId;
                        this._flashBid();
                    } else {
                        this.toast(data.message || 'Bid failed', 'error');
                    }
                } catch (e) {
                    console.error('Bid error:', e);
                } finally {
                    this._isBidding = false;
                }
            },

            // ─── LOAD NEXT PLAYER (RANDOM WITH SHUFFLE) ───
            async loadNextPlayer() {
                this.dismissOverlays();
                if (this.currentPlayer) {
                    if (! await this.askConfirm('Pass current player and load next?', { title: 'Pass player' })) return;
                    await this._passPlayerRequest();
                    this.currentPlayer = null;
                    this.currentBidAmount = 0;
                    this.currentBidTeamId = null;
                }
                // Refresh available players
                await this.pollAuctionState();
                if (this.availablePlayers.length === 0) {
                    const reAuctionCount = (this.stats.unsold_count || 0) + (this.stats.skipped_count || 0);
                    if (reAuctionCount > 0) {
                        if (await this.askConfirm('No more waiting players. Start a re-auction round with ' + reAuctionCount + ' unsold/skipped player(s)?', { title: 'Re-auction round' })) {
                            await this.startReAuctionRound();
                        }
                    } else {
                        this.toast('No more players waiting.', 'info');
                    }
                    return;
                }
                // Pick the winner before animation
                // Take the next player in drawn lot order (the server already sorted
                // availablePlayers by pool sequence then lot number). Picking at random
                // here threw away the draw the organizer configured.
                const chosenPlayer = this.availablePlayers[0];

                // Shuffle animation is theatre only — the player is already decided.
                await this._runShuffleAnimation(chosenPlayer);

                // NOTE: deliberately does NOT overwrite base_price. This used to stamp
                // the auction-wide default over every player right before putting them
                // on the block, erasing both per-player and per-pool prices.
                await this.putPlayerOnBid(chosenPlayer.id);
            },

            // ─── SHUFFLE ANIMATION ───
            _runShuffleAnimation(chosenPlayer) {
                return new Promise((resolve) => {
                    this.shufflePhase = 'spinning';
                    this.showShuffleOverlay = true;
                    this.shuffleSelectedPlayer = null;
                    this.shuffleDisplayName = '';

                    const players = this.availablePlayers;
                    if (players.length <= 1) {
                        // Skip animation for single player
                        this.shuffleSelectedPlayer = chosenPlayer;
                        this.shufflePhase = 'reveal';
                        setTimeout(() => {
                            this.showShuffleOverlay = false;
                            resolve();
                        }, 1200);
                        return;
                    }

                    let tick = 0;
                    const totalTicks = 30; // ~2.4s total
                    let currentIdx = 0;

                    this._shuffleInterval = setInterval(() => {
                        tick++;
                        // Cycle through names randomly
                        currentIdx = Math.floor(Math.random() * players.length);
                        this.shuffleDisplayName = players[currentIdx].player?.name || 'Player ' + players[currentIdx].id;

                        // Slow down toward the end
                        if (tick >= totalTicks) {
                            clearInterval(this._shuffleInterval);
                            this._shuffleInterval = null;

                            // Reveal the chosen player
                            this.shuffleDisplayName = chosenPlayer.player?.name || 'Player ' + chosenPlayer.id;
                            setTimeout(() => {
                                this.shuffleSelectedPlayer = chosenPlayer;
                                this.shufflePhase = 'reveal';
                                // Hold reveal for a moment then dismiss
                                setTimeout(() => {
                                    this.showShuffleOverlay = false;
                                    resolve();
                                }, 1500);
                            }, 300);
                        }
                    }, 80);
                });
            },

            // ─── LOAD PLAYER BY ID (from player list) ───
            async loadPlayerById(auctionPlayerId) {
                if (this.currentPlayer) {
                    if (! await this.askConfirm('Pass current player and load this one?', { title: 'Pass player' })) return;
                    await this._passPlayerRequest();
                    this.currentPlayer = null;
                    this.currentBidAmount = 0;
                    this.currentBidTeamId = null;
                }
                this.sidePanel = null;
                this.dismissOverlays();
                // Keeps the player's own base price (per-player, else pool, else auction).
                await this.putPlayerOnBid(auctionPlayerId);
            },

            // ─── LOAD PLAYER BY NUMBER ───
            async loadPlayerByNumber() {
                const id = parseInt(this.playerNumberInput);
                if (!id) return;
                if (this.currentPlayer) {
                    if (! await this.askConfirm('Pass current player and load #' + id + '?', { title: 'Pass player' })) return;
                    await this._passPlayerRequest();
                    this.currentPlayer = null;
                    this.currentBidAmount = 0;
                    this.currentBidTeamId = null;
                }
                this.playerNumberInput = '';

                // Keeps the player's own base price (per-player, else pool, else auction).
                await this.putPlayerOnBid(id);
            },

            // ─── AUCTION BASE PRICE EDITING (TOOLBAR) ───
            startEditAuctionBase() {
                this.editAuctionBaseValue = this.auctionBasePrice;
                this.editingAuctionBase = true;
                this.$nextTick(() => {
                    if (this.$refs.auctionBaseInput) this.$refs.auctionBaseInput.focus();
                });
            },

            async saveAuctionBasePrice() {
                if (!this.editAuctionBaseValue || this.editAuctionBaseValue <= 0) return;
                try {
                    const res = await fetch(this.apiBase + '/api/update-auction-base-price', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ base_price: this.editAuctionBaseValue })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.auctionBasePrice = data.base_price;
                        this.editingAuctionBase = false;
                        this.shortcutsEnabled = true;
                    } else {
                        this.toast(data.message || 'Failed to update base price', 'error');
                    }
                } catch (e) {
                    console.error('Update auction base price error:', e);
                }
            },

            // ─── PUT PLAYER ON BID ───
            async putPlayerOnBid(auctionPlayerId) {
                try {
                    const res = await fetch(this.apiBase + '/api/player-on-bid', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auction_player_id: auctionPlayerId })
                    });
                    const data = await res.json();
                    if (!data.success && res.status !== 200) {
                        this.toast(data.message || 'Failed to load player', 'error');
                    }
                    // Poll will pick up the new state
                    await this.pollAuctionState();
                } catch (e) {
                    console.error('Load player error:', e);
                }
            },

            // ─── SELL CURRENT PLAYER ───
            async sellCurrentPlayer() {
                if (!this.currentPlayer || !this.currentBidTeamId) return;
                const team = this.getCurrentBidTeam();
                const teamName = team?.name || 'Team #' + this.currentBidTeamId;

                // Block client-side on the squad-reserve rule, so the operator sees why
                // rather than getting a rejection after confirming.
                if (team && team.max_bid_allowed !== null && team.max_bid_allowed !== undefined
                    && Number(this.currentBidAmount) > Number(team.max_bid_allowed)) {
                    this.toast(`${teamName} cannot go above ${this.formatCurrency(team.max_bid_allowed)} on this player.`
                        + `\n\n${team.exclusion_reason || 'Squad-reserve rule: purse must cover the remaining squad slots.'}`,
                        'error', 'Over the limit');
                    return;
                }

                // Show what the sale leaves the team with, not just the price.
                const purseAfter = Number(team?.remaining_budget || 0) - Number(this.currentBidAmount || 0);
                const squadAfter = (team?.players_bought || 0) + 1;
                const squadLabel = team?.slots_required ? `${squadAfter}/${team.slots_required}` : String(squadAfter);
                const summary = `Sell ${this.currentPlayer.player?.name || 'this player'} to ${teamName}`
                    + ` for ${this.formatCurrency(this.currentBidAmount)}?`
                    + `\n\nPurse after sale: ${this.formatCurrency(purseAfter)}`
                    + `\nSquad after sale: ${squadLabel}`;

                if (! await this.askConfirm(summary, { title: 'Confirm sale' })) return;

                try {
                    const res = await fetch(this.apiBase + '/api/sell-to-team', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({
                            auction_player_id: this.currentPlayer.id,
                            team_id: this.currentBidTeamId,
                            amount: this.currentBidAmount
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.soldPlayerName = this.currentPlayer.player?.name || 'Unknown';
                        this.soldPlayerImage = this.currentPlayer.player?.image_path || null;
                        this.soldPrice = this.currentBidAmount;
                        this.soldTeamName = team?.name || '';
                        this.soldTeamLogo = team?.team_logo || null;
                        this.soldTeamPlayerCount = (team?.players_bought || 0) + 1;
                        this.lastActionPlayerId = this.currentPlayer.id;
                        // Keep currentPlayer, currentBidAmount, currentBidTeamId visible behind overlay
                        this.showSoldOverlay = true;
                        this._fireConfetti();
                        await this.pollAuctionState();
                    } else {
                        this.toast(data.message || 'Sell failed', 'error');
                    }
                } catch (e) {
                    console.error('Sell error:', e);
                }
            },

            // ─── PASS PLAYER ───
            async passCurrentPlayer() {
                if (!this.currentPlayer || this.currentBidTeamId) return;
                if (! await this.askConfirm('Mark this player UNSOLD?', { title: 'Unsold', danger: true })) return;

                this.unsoldPlayerName = this.currentPlayer.player?.name || 'Unknown';
                this.unsoldPlayerImage = this.currentPlayer.player?.image_path || null;
                this.lastActionPlayerId = this.currentPlayer.id;

                await this._passPlayerRequest();

                // Keep currentPlayer visible behind overlay
                this.showUnsoldOverlay = true;
                await this.pollAuctionState();
            },

            async _passPlayerRequest() {
                if (!this.currentPlayer) return;
                try {
                    await fetch(this.apiBase + '/api/pass-player', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auction_player_id: this.currentPlayer.id })
                    });
                } catch (e) { console.error('Pass error:', e); }
            },

            // ─── SKIP PLAYER ───
            async skipCurrentPlayer() {
                if (!this.currentPlayer) return;
                this.skippedPlayerName = this.currentPlayer.player?.name || 'Unknown';
                this.skippedPlayerImage = this.currentPlayer.player?.image_path || null;
                this.lastActionPlayerId = this.currentPlayer.id;

                try {
                    await fetch(this.apiBase + '/api/skip-player', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auction_player_id: this.currentPlayer.id })
                    });
                } catch (e) { console.error('Skip error:', e); }

                // Keep currentPlayer visible behind overlay
                this.showSkippedOverlay = true;
                await this.pollAuctionState();
            },

            // ─── START RE-AUCTION ROUND ───
            async startReAuctionRound() {
                const count = (this.stats.unsold_count || 0) + (this.stats.skipped_count || 0);
                if (! await this.askConfirm('Move ' + count + ' unsold/skipped player(s) back to waiting for re-auction?', { title: 'Re-auction round' })) return;
                try {
                    const res = await fetch(this.apiBase + '/api/start-reauction-round', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.dismissOverlays();
                        await this.pollAuctionState();
                    } else {
                        this.toast(data.message || 'Failed to start re-auction round', 'error');
                    }
                } catch (e) {
                    console.error('Re-auction round error:', e);
                }
            },

            // ─── RE-AUCTION ───
            async reAuctionLastPlayer() {
                if (!this.lastActionPlayerId) return;
                try {
                    const res = await fetch(this.apiBase + '/api/re-auction-player', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ auction_player_id: this.lastActionPlayerId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.dismissOverlays();
                        await this.pollAuctionState();
                    } else {
                        this.toast(data.message || 'Re-auction failed', 'error');
                    }
                } catch (e) {
                    console.error('Re-auction error:', e);
                }
            },

            // ─── POLL ───
            /** POST to an organizer API endpoint and return the decoded body. */
            async _post(endpoint, body = {}) {
                try {
                    const res = await fetch(this.apiBase + '/api/' + endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(body),
                    });
                    return await res.json();
                } catch (e) {
                    console.error(endpoint + ' error:', e);
                    return null;
                }
            },

            async activatePool(poolId) {
                const result = await this._post(`pools/${poolId}/activate`);
                if (result?.success) {
                    await this.pollAuctionState();
                } else if (result?.message) {
                    this.toast(result.message, 'error');
                }
            },

            async completeActivePool() {
                if (!this.activePool) return;
                if (! this.activePool.exhausted
                    && ! await this.askConfirm(`Close ${this.activePool.name} now?\n\n${this.activePool.waiting} player(s) still in it will be left unsold.`, { title: 'Close pool', danger: true })) {
                    return;
                }
                const result = await this._post(`pools/${this.activePool.id}/complete`);
                if (result?.success) await this.pollAuctionState();
                else if (result?.message) this.toast(result.message, 'error');
            },

            async toggleTimer() {
                const result = await this._post('toggle-timer', { timer_enabled: !this.timerEnabled });
                if (result?.success) this.timerEnabled = result.timer_enabled;
                else if (result?.message) this.toast(result.message, 'error');
            },

            /**
             * Tick the clock locally between 2-second polls, so the closing calls land
             * on exact seconds rather than whenever the next poll happens to arrive.
             */
            startClockTick() {
                if (this._clockTick) return;
                this._clockTick = setInterval(() => {
                    if (this.timerSecondsRemaining !== null && this.timerSecondsRemaining > 0) {
                        this.timerSecondsRemaining--;
                    }
                    this.refreshFinalCall();
                }, 1000);
            },

            refreshFinalCall() {
                if (!this.timerEnabled || !this.currentPlayer) {
                    this.finalCall = null;
                    return;
                }
                this.finalCall = window.auctionFinalCallFor
                    ? window.auctionFinalCallFor(this.timerSecondsRemaining, this.finalCallStages)
                    : null;
            },

            /**
             * Report expiry; the server re-checks its own clock before acting.
             * Latched per player — in manual mode the clock stays expired until the
             * organizer acts, so a timed cooldown would re-fire indefinitely.
             */
            async handleTimerExpiry(auctionPlayerId) {
                if (this._timerFiring || this._timerFiredForPlayer === auctionPlayerId) return;

                this._timerFiring = true;
                this._timerFiredForPlayer = auctionPlayerId;
                try {
                    const result = await this._post('timer-expired', { auction_player_id: auctionPlayerId });
                    if (result?.handled) {
                        if (result.action === 'sold') this._fireConfetti();
                        await this.pollAuctionState();
                    } else {
                        this._timerFiredForPlayer = null;
                    }
                } finally {
                    this._timerFiring = false;
                }
            },

            /* ── Sealed round ──
               The offline panel drives the round from here rather than sending the
               organizer to the other panel mid-auction. */

            async fetchSealedState() {
                if (!this.currentPlayer) {
                    if (this.sealed.active) this.sealed = { active: false };
                    return;
                }
                try {
                    const res = await fetch(
                        `${this.apiBase}/api/closed-bid/state?auction_player_id=${this.currentPlayer.id}`,
                        { headers: { Accept: 'application/json' } }
                    );
                    if (!res.ok) return;
                    const data = await res.json();
                    this.sealed = data.closed_bid || { active: false };
                } catch (e) { /* a dropped poll is not worth surfacing */ }
            },

            /*
             * "The bidding has reached the sealed threshold — what now?"
             *
             * The same question the organizer panel asks, and it has to exist here too:
             * crossing the threshold no longer flips the room by itself, so without this
             * an offline auction could never enter a sealed round at all — and this panel
             * carries a full sealed board.
             *
             * Asked once per player. Dismissing means "not yet", so it does not come back
             * on the next 2-second poll and interrupt a room being called by hand.
             */
            _sealedPromptAskedFor: null,
            _sealedPromptOpen: false,

            async maybeAskSealedThreshold(data) {
                if (! data?.sealed_threshold_pending) return;
                if (this._sealedPromptOpen) return;

                const playerId = this.currentPlayer?.id ?? data.current_player?.id ?? null;
                if (playerId === null || this._sealedPromptAskedFor === playerId) return;

                this._sealedPromptAskedFor = playerId;
                this._sealedPromptOpen = true;

                const name = this.currentPlayer?.player?.name || 'This player';
                const amount = this.formatCurrency(data.sealed_threshold_amount ?? 0);
                const threshold = this.formatCurrency(data.closed_bid_starts_at ?? 0);
                const leader = data.sealed_threshold_leader;

                let answer = false;
                try {
                    answer = await this.askConfirm(
                        `${name} has reached ${amount}, the sealed-bid threshold of ${threshold}.`
                        + (leader ? `\n\nThe leading team is ${leader}.` : '\n\nNo team is currently leading.'),
                        {
                            title: 'Sealed bid threshold',
                            choices: [
                                {
                                    value: 'sealed',
                                    label: 'Move to sealed bid',
                                    hint: 'Teams submit written amounts. Highest wins.',
                                    class: 'bg-indigo-600 hover:bg-indigo-700',
                                },
                                // Only when there IS somebody to sell to.
                                ...(leader ? [{
                                    value: 'sell',
                                    label: `Sell to ${leader} for ${amount}`,
                                    hint: 'Ends the bidding here and moves to the next player.',
                                    class: 'bg-emerald-600 hover:bg-emerald-700',
                                }] : []),
                                {
                                    value: 'keep',
                                    label: 'Keep open bidding',
                                    hint: 'Carry on as normal. You will not be asked again for this player.',
                                },
                            ],
                        }
                    );
                } finally {
                    this._sealedPromptOpen = false;
                }

                if (answer === 'sealed') {
                    const result = await this.sealedCommand('confirm-threshold');
                    if (! result || result.success === false) {
                        this._sealedPromptAskedFor = null;   // nothing happened; ask again
                        return;
                    }
                    await this.pollAuctionState();
                    return;
                }

                if (answer === 'sell') {
                    // sellCurrentPlayer() runs its own confirmation with the purse summary
                    // on it, which is the more informative screen — so it is not skipped
                    // here even though this dialog has already been answered.
                    await this.sellCurrentPlayer();
                    return;
                }

                // 'keep', or dismissed. Open bidding continues untouched.
            },

            async sealedCommand(path, body = {}) {
                try {
                    const res = await fetch(`${this.apiBase}/api/closed-bid/${path}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ auction_player_id: this.currentPlayer?.id, ...body }),
                    });
                    const data = await res.json();
                    if (data.closed_bid) this.sealed = data.closed_bid;
                    if (data.message) {
                        this.toast(data.message, data.handled ? 'success' : 'info', 'Sealed round');
                    }
                    return data;
                } catch (e) {
                    this.toast('That did not go through.', 'error', 'Sealed round');
                    return null;
                }
            },

            /* ── Entering a team's sealed amount ──
               Offline means the teams are in the room rather than on their own screens, so
               the organizer submits for them. The server records ROLE_ADMIN on every one of
               these, and the acceptance gate only applies to a team's own submission — so
               an offline round does not stall waiting for teams to click Accept. */
            sealedCustom: {},

            async sealedEntry(entryId, action, body = {}) {
                const data = await this.sealedCommand(`entries/${entryId}/${action}`, body);
                // The board comes back from the same response, so no extra poll is needed.
                if (data?.closed_bid) this.sealed = data.closed_bid;
                return data;
            },

            async sealedSubmitCustom(entryId) {
                const typed = this.sealedCustom[entryId];
                if (typed === '' || typed === null || typed === undefined) {
                    this.toast('Enter an amount first.', 'error', 'Sealed round');
                    return;
                }

                // The box is in millions like every other money field on this screen; the
                // server wants base units.
                const amount = this.fromM(typed);
                const data = await this.sealedEntry(entryId, 'adjust', { amount });

                // Only clear the box on success, so a rejected amount stays visible to fix
                // rather than vanishing under an error toast.
                if (data?.handled) this.sealedCustom[entryId] = '';
            },

            /** Money entry in millions, shared with every other screen. */
            toM(raw) { return window.auctionToM ? window.auctionToM(raw) : raw; },
            fromM(value) { return window.auctionFromM ? window.auctionFromM(value) : value; },

            async pollAuctionState() {
                try {
                    const res = await fetch(this.apiBase + '/api/poll-state');
                    const data = await res.json();

                    this.auctionStatus = data.auction_status;
                    this.teams = data.teams;
                    this.stats = data.stats;

                    this.fetchSealedState();
                    this.maybeAskSealedThreshold(data);

                    // Pool lock + timer, both owned by the server.
                    this.activePool = data.active_pool || null;
                    this.nextPool = data.next_pool || null;
                    this.pools = data.pools || [];
                    this.timerEnabled = !!data.timer_enabled;
                    this.timerExpiryAction = data.timer_expiry_action || 'manual';
                    this.timerSecondsRemaining = data.timer_seconds_remaining;
                    this.timerExpired = !!data.timer_expired;
                    if (data.amount_unit) this.amountUnit = data.amount_unit;
                    if (Array.isArray(data.final_call_stages)) this.finalCallStages = data.final_call_stages;
                    this.startClockTick();

                    if (this.timerExpired && this.timerEnabled && data.current_player) {
                        this.handleTimerExpiry(data.current_player.id);
                    }

                    // Already ordered by pool sequence then lot number, and scoped to the
                    // active pool — the list is taken as given rather than re-sorted or
                    // sampled at random.
                    this.availablePlayers = (data.available_players || []).map(ap => ({
                        id: ap.id,
                        player_id: ap.player_id,
                        base_price: ap.base_price,
                        player: ap.player,
                    }));

                    const cp = data.current_player;
                    if (cp) {
                        // A player is on auction
                        if (this._lastCurrentPlayerId !== cp.id) {
                            // New player detected
                            this.dismissOverlays();
                            // Allow a fresh time-up announcement for this player.
                            this._timerFiredForPlayer = null;
                        }
                        this.currentPlayer = cp;
                        this.currentBidAmount = parseFloat(cp.current_price) || cp.base_price;
                        this.currentBidTeamId = cp.current_bid_team_id;
                        this.basePrice = parseFloat(cp.base_price) || 0;
                        this._lastCurrentPlayerId = cp.id;
                    } else if (this._lastCurrentPlayerId && !this.showSoldOverlay && !this.showUnsoldOverlay && !this.showSkippedOverlay) {
                        // Player disappeared — was sold/unsold by another admin
                        this.currentPlayer = null;
                        this.currentBidAmount = 0;
                        this.currentBidTeamId = null;
                        this._lastCurrentPlayerId = null;
                    }
                } catch (e) {
                    console.error('Poll error:', e);
                }
            },

            // ─── SIDE PANELS ───
            showSidePanelFn(name) {
                if (this.sidePanel === name) {
                    this.sidePanel = null;
                    return;
                }
                this.sidePanel = name;
                this.selectedTeamDetail = null;
                if (name === 'players') this.fetchAllPlayers();
            },

            async fetchAllPlayers() {
                try {
                    const res = await fetch(this.apiBase + '/api/all-players');
                    const data = await res.json();
                    this.allPlayersList = data.players || [];
                } catch (e) { console.error('Fetch players error:', e); }
            },

            filteredPlayers() {
                if (this.playerFilterStatus === 'all') return this.allPlayersList;
                return this.allPlayersList.filter(p => p.status === this.playerFilterStatus);
            },

            async selectTeamDetail(team) {
                this.selectedTeamDetail = team;
                // Filter sold players for this team from allPlayersList
                if (this.allPlayersList.length === 0) await this.fetchAllPlayers();
                this.teamDetailPlayers = this.allPlayersList.filter(p => p.status === 'sold' && p.sold_to_team === team.name);
            },

            totalRevenue() {
                return this.teams.reduce((sum, t) => sum + parseFloat(t.total_spent || 0), 0);
            },

            _flashBid() {
                this.bidJustChanged = false;
                if (this._bidFlashTimeout) clearTimeout(this._bidFlashTimeout);
                this.$nextTick(() => {
                    this.bidJustChanged = true;
                    this._bidFlashTimeout = setTimeout(() => { this.bidJustChanged = false; }, 400);
                });
            },

            _fireConfetti() {
                if (typeof confetti !== 'function') return;
                // Left burst
                confetti({ particleCount: 80, spread: 70, origin: { x: 0.1, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
                // Right burst
                confetti({ particleCount: 80, spread: 70, origin: { x: 0.9, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
                // Center shower
                setTimeout(() => {
                    confetti({ particleCount: 120, spread: 100, origin: { x: 0.5, y: 0.3 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#f59e0b', '#ffffff'] });
                }, 300);
            },

            dismissOverlays() {
                const hadOverlay = this.showSoldOverlay || this.showUnsoldOverlay || this.showSkippedOverlay;
                this.showSoldOverlay = false;
                this.showUnsoldOverlay = false;
                this.showSkippedOverlay = false;
                // Clear stale player data when dismissing an overlay
                // (player was already sold/unsold/skipped on server)
                if (hadOverlay) {
                    this.currentPlayer = null;
                    this.currentBidAmount = 0;
                    this.currentBidTeamId = null;
                    this._lastCurrentPlayerId = null;
                }
            },

            // ─── KEYBOARD SHORTCUTS ───
            handleKeydown(e) {
                /* A confirmation is modal and must swallow the shortcuts — see the same
                   guard on the organizer panel. Enter confirms, Escape cancels. */
                if (this.confirmBox.open) {
                    e.preventDefault();
                    if (e.key === 'Enter') this._settleConfirm(true);
                    if (e.key === 'Escape') this._settleConfirm(false);
                    return;
                }

                if (!this.shortcutsEnabled) return;
                // Ignore when typing in inputs
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                const key = e.key.toUpperCase();

                // F — Fullscreen toggle
                if (key === 'F' && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    this.toggleFullscreen();
                    return;
                }

                // Escape — close panel / dismiss overlays
                if (e.key === 'Escape') {
                    if (this.sidePanel) { this.sidePanel = null; return; }
                    this.dismissOverlays();
                    return;
                }

                // R — Reauction
                if (key === 'R' && (this.showSoldOverlay || this.showUnsoldOverlay || this.showSkippedOverlay)) {
                    e.preventDefault();
                    this.reAuctionLastPlayer();
                    return;
                }

                // N — Next player
                if (key === 'N') {
                    e.preventDefault();
                    this.loadNextPlayer();
                    return;
                }

                // S, U, K — only when no overlay is active
                const hasOverlay = this.showSoldOverlay || this.showUnsoldOverlay || this.showSkippedOverlay;

                // S — Sell (only when not typing and a bid exists)
                if (key === 'S' && !e.ctrlKey && !e.metaKey && !hasOverlay) {
                    if (this.currentBidTeamId) {
                        e.preventDefault();
                        this.sellCurrentPlayer();
                        return;
                    }
                }

                // U — Unsold
                if (key === 'U' && this.currentPlayer && !hasOverlay && !this.currentBidTeamId) {
                    e.preventDefault();
                    this.passCurrentPlayer();
                    return;
                }

                // K — Skip
                if (key === 'K' && this.currentPlayer && !hasOverlay) {
                    e.preventDefault();
                    this.skipCurrentPlayer();
                    return;
                }

                // Number keys 1-9, 0 — bid for team
                if (this.currentPlayer && !hasOverlay) {
                    let teamIdx = -1;
                    if (e.key >= '1' && e.key <= '9') teamIdx = parseInt(e.key) - 1;
                    else if (e.key === '0') teamIdx = 9;

                    if (teamIdx >= 0 && teamIdx < this.teams.length) {
                        e.preventDefault();
                        this.bidForTeam(this.teams[teamIdx].id);
                    }
                }
            },

            destroy() {
                if (this._pollInterval) clearInterval(this._pollInterval);
                if (this._shuffleInterval) clearInterval(this._shuffleInterval);
            }
        };
    }
    </script>
@endpush
