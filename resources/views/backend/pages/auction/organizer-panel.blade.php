@extends('backend.layouts.app')

@section('title', 'Live Auction | ' . $auction->name)

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<style>
    * { box-sizing: border-box; }

    /* Dot pattern background */
    .dot-bg {
        background-image: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
        background-size: 24px 24px;
    }

    /* Bid flash */
    @keyframes bidFlash { 0% { transform: scale(1.15); color: #4ade80; } 100% { transform: scale(1); } }
    .bid-flash { animation: bidFlash 0.3s ease-out; }

    /* Price up */
    @keyframes priceUp { 0% { transform: translateY(16px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }
    .price-up { animation: priceUp 0.25s ease-out; }

    /* Sold stamp */
    @keyframes soldStamp { 0% { transform: rotate(-12deg) scale(0); opacity: 0; } 70% { transform: rotate(-12deg) scale(1.15); opacity: 1; } 100% { transform: rotate(-12deg) scale(1); opacity: 1; } }
    .sold-stamp { animation: soldStamp 0.5s ease-out forwards; }

    /* Unsold stamp */
    @keyframes unsoldStamp { 0% { transform: rotate(-12deg) scale(0); opacity: 0; } 100% { transform: rotate(-12deg) scale(1); opacity: 1; } }
    .unsold-stamp { animation: unsoldStamp 0.5s ease-out forwards; }

    /* Team pulse */
    @keyframes teamPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.5); } 50% { box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); } }
    .team-pulse { animation: teamPulse 1.2s ease-in-out infinite; }

    /* Timer critical */
    @keyframes timerPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .timer-critical { animation: timerPulse 0.5s ease-in-out infinite; }

    /* Closing call — each new call punches in, then breathes. */
    @keyframes finalCallPulse {
        0%   { transform: scale(0.85); opacity: 0; }
        35%  { transform: scale(1.06); opacity: 1; }
        60%  { transform: scale(1); }
        100% { transform: scale(1.02); }
    }
    .final-call-pulse { animation: finalCallPulse 0.9s cubic-bezier(0.34, 1.56, 0.64, 1) both; }

    /* Slide panel */
    .side-panel-enter { transform: translateX(100%); }
    .side-panel-active { transform: translateX(0); transition: transform 0.25s ease-out; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }

    /* Shuffle ring */
    @keyframes shuffleRingSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes shuffleGlow { 0%, 100% { box-shadow: 0 0 20px rgba(59,130,246,0.3), 0 0 60px rgba(59,130,246,0.1); } 50% { box-shadow: 0 0 40px rgba(59,130,246,0.6), 0 0 80px rgba(59,130,246,0.2); } }
    @keyframes shuffleReveal { 0% { transform: scale(0.5); opacity: 0; } 60% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); opacity: 1; } }
    .shuffle-ring-spin { animation: shuffleRingSpin 0.6s linear infinite; }
    .shuffle-glow { animation: shuffleGlow 0.8s ease-in-out infinite; }
    .shuffle-reveal { animation: shuffleReveal 0.5s ease-out forwards; }

    /* Team card selected */
    @keyframes teamCardPulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
    .team-card-selected { animation: teamCardPulse 0.3s ease-out; }

    /* Winner glow */
    @keyframes winnerGlow { from { box-shadow: 0 0 20px rgba(34, 197, 94, 0.3); } to { box-shadow: 0 0 40px rgba(34, 197, 94, 0.6), 0 0 60px rgba(34, 197, 94, 0.3); } }
    .offline-winner-glow { animation: winnerGlow 2s ease-in-out infinite alternate; }

    /* Fullscreen wrapper */
    .organizer-panel-wrapper.is-fullscreen {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }
</style>
@endpush

@section('admin-content')
<div class="bg-gray-950 text-white overflow-hidden organizer-panel-wrapper relative rounded-lg"
     :class="{ 'is-fullscreen': isFullscreen }"
     id="organizerPanelWrapper"
     x-data="auctionOrganizerPanel()"
     x-init="init(
         {{ $auction->id }},
         '{{ $auction->status }}',
         {{ json_encode($availablePlayers->map(fn($ap) => [
             'id' => $ap->id,
             'name' => $ap->player->name,
             'base_price' => $ap->base_price,
             'image_path' => $ap->player->image_path,
             'player_type' => $ap->player->playerType?->name ?? 'Player',
             'batting_style' => $ap->player->battingProfile?->name ?? null,
             'bowling_style' => $ap->player->bowlingProfile?->name ?? null,
             'total_matches' => $ap->player->total_matches,
             'total_runs' => $ap->player->total_runs,
             'total_wickets' => $ap->player->total_wickets,
         ])) }},
         {{ json_encode($teams->map(fn($t) => [
             'id' => $t->id,
             'name' => $t->name,
             'short_name' => $t->short_name ?? substr($t->name, 0, 3),
             'logo_url' => $t->team_logo_url,
             'players_bought' => $t->players_bought ?? 0,
             'total_spent' => $t->total_spent ?? 0,
             'remaining_budget' => $t->remaining_budget ?? $auction->max_budget_per_team,
             // Squad-reserve figures. This list whitelists keys explicitly, so any
             // new field must be added here as well as in pollState.
             'max_bid_allowed' => $t->max_bid_allowed ?? null,
             'reserve_amount' => $t->reserve_amount ?? 0,
             'slots_required' => $t->slots_required ?? null,
             'slots_remaining' => $t->slots_remaining ?? null,
             'excluded' => (bool) ($t->excluded ?? false),
             'exclusion_reason' => $t->exclusion_reason ?? null,
         ])) }},
         {{ $auction->max_budget_per_team }},
         {{ json_encode($currentPlayer ? [
             'id' => $currentPlayer->id,
             'player' => [
                 'id' => $currentPlayer->player->id,
                 'name' => $currentPlayer->player->name,
                 'image_path' => $currentPlayer->player->image_path,
                 'player_type' => $currentPlayer->player->playerType?->name ?? 'Player',
                 'batting_style' => $currentPlayer->player->battingProfile?->name ?? null,
                 'bowling_style' => $currentPlayer->player->bowlingProfile?->name ?? null,
                 'total_matches' => $currentPlayer->player->total_matches,
                 'total_runs' => $currentPlayer->player->total_runs,
                 'total_wickets' => $currentPlayer->player->total_wickets,
             ],
             'base_price' => $currentPlayer->base_price,
             'current_price' => $currentPlayer->current_price,
             'bids' => $currentPlayer->bids->map(fn($b) => [
                 'id' => $b->id,
                 'amount' => $b->amount,
                 'team' => ['id' => $b->team->id, 'name' => $b->team->name],
                 'user' => ['name' => $b->user->name],
                 'created_at' => $b->created_at->toISOString(),
             ]),
         ] : null) }}
     )"
     @keydown.window="handleKeydown($event)">

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SELL PLAYER MODAL --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div x-show="showSellModal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:999999;background:rgba(0,0,0,0.85);" x-cloak>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#1f2937;border-radius:1rem;padding:2rem;max-width:28rem;width:calc(100% - 2rem);border:1px solid #374151;box-shadow:0 25px 50px rgba(0,0,0,0.5);">
            <h3 class="text-xl font-bold text-white mb-4 text-center">Sell Player</h3>
            <div class="text-center mb-6">
                <p class="text-gray-300 mb-4">Selling <span class="font-bold text-white" x-text="currentPlayer?.player?.name"></span></p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Select Team</label>
                <select x-model="sellModalData.team_id"
                        class="w-full px-4 py-3 bg-gray-900 border border-gray-600 rounded-xl text-white focus:outline-none focus:border-blue-500">
                    <option value="">-- Choose a team --</option>
                    <template x-for="team in teams" :key="team.id">
                        <option :value="team.id" x-text="team.name + ' (Balance: ' + formatCurrency(team.remaining_budget) + ')'"></option>
                    </template>
                </select>
            </div>
            <div class="mb-6">
                {{-- Entered in millions, matching the M figures shown everywhere else.
                     This was a Lakhs field while the rest of the panel read in millions,
                     so a "5" here sold the player for 500K rather than 5M. --}}
                <label class="block text-sm font-medium text-gray-300 mb-2">Sale Amount (M)</label>
                <div class="flex items-center bg-gray-900 border border-gray-600 rounded-xl focus-within:border-blue-500">
                    <input type="number"
                           :value="sellModalData.amount ? toM(sellModalData.amount) : ''"
                           @input="sellModalData.amount = fromM($event.target.value)"
                           class="w-full px-4 py-3 bg-transparent text-white focus:outline-none text-right"
                           placeholder="0" step="any" min="0">
                    <span class="pr-4 text-gray-400 font-medium">M</span>
                </div>
            </div>
            {{-- Confirmation summary: who, how much, and what it leaves the team with.
                 Showing the post-sale purse and squad count is what makes this a real
                 check rather than a rubber-stamp. --}}
            <div x-show="sellModalData.team_id && sellModalData.amount" class="bg-gray-900/50 rounded-xl p-4 mb-6">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <template x-if="saleTeam?.logo_url">
                        <img :src="saleTeam.logo_url" class="w-10 h-10 rounded-full object-cover border-2 border-green-500/50">
                    </template>
                    <div class="text-center">
                        <p class="text-gray-300">Sell to <span class="font-bold text-green-400" x-text="saleTeam?.name"></span></p>
                        <p class="text-gray-300">for <span class="font-bold text-yellow-400 text-2xl" x-text="formatCurrency(sellModalData.amount)"></span></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-700 text-sm">
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide">Purse after sale</p>
                        <p class="font-mono font-bold"
                           :class="salePurseAfter < 0 ? 'text-red-400' : 'text-white'"
                           x-text="formatCurrency(salePurseAfter)"></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs uppercase tracking-wide">Squad after sale</p>
                        <p class="font-mono font-bold text-white">
                            <span x-text="(saleTeam?.players_bought || 0) + 1"></span>
                            <span class="text-gray-500" x-text="saleTeam?.slots_required ? ('/' + saleTeam.slots_required) : ''"></span>
                        </p>
                    </div>
                </div>

                {{-- Server-side rule: the team must retain enough to fill its remaining slots. --}}
                <p x-show="saleBreachesReserve"
                   class="mt-3 text-xs text-amber-400 bg-amber-500/10 border border-amber-500/30 rounded-lg px-3 py-2">
                    This exceeds <span x-text="saleTeam?.name"></span>'s maximum allowed bid of
                    <span class="font-bold" x-text="formatCurrency(saleTeam?.max_bid_allowed)"></span>
                    under the squad-reserve rule and will be rejected.
                </p>
            </div>
            <div class="flex gap-4">
                <button @click="showSellModal = false"
                        class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-medium transition">Cancel</button>
                <button @click="executeSellToTeam()"
                        :disabled="!sellModalData.team_id || !sellModalData.amount || saleBreachesReserve"
                        class="flex-1 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 disabled:from-gray-600 disabled:to-gray-600 disabled:cursor-not-allowed text-white rounded-xl font-bold transition">Confirm Sale</button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- MAIN LAYOUT: Stage + Bottom Toolbar --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div class="flex flex-col h-[calc(100vh-120px)]" :class="isFullscreen ? '!h-screen' : ''">

        {{-- ── MAIN STAGE ── --}}
        <div class="flex-1 relative dot-bg flex items-center justify-center overflow-hidden">

            {{-- ══ CLOSING CALL ══
                 "Going once, going twice": escalates in the closing seconds so the room
                 knows the hammer is coming. Shown on every screen off the same
                 server-supplied thresholds. --}}
            <template x-if="finalCall && displayState === 'bidding'">
                <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 z-40 flex flex-col items-center pointer-events-none">
                    <div class="px-10 py-4 rounded-2xl shadow-2xl final-call-pulse"
                         :class="finalCall.is_final
                            ? 'bg-red-600 text-white ring-4 ring-red-400/60'
                            : 'bg-amber-500 text-black ring-4 ring-amber-300/50'">
                        <p class="text-5xl font-black tracking-widest uppercase" x-text="finalCall.label"></p>
                    </div>
                    <p class="mt-3 text-7xl font-black font-mono tabular-nums"
                       :class="finalCall.is_final ? 'text-red-400' : 'text-amber-300'"
                       x-text="Math.max(0, biddingTimerSeconds)"></p>
                    <p x-show="finalCall.is_final" class="mt-1 text-sm font-bold uppercase tracking-widest text-red-300"
                       x-text="timerExpiryAction === 'auto_sell' ? 'Selling automatically at zero' : 'Bidding closes at zero'"></p>
                </div>
            </template>


            {{-- Logos (top-left) --}}
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

            {{-- Timer Badge (top-right) --}}
            <div class="absolute top-4 right-4 z-30 flex items-center gap-3">
                {{-- Status dot --}}
                <div class="flex items-center gap-2 bg-gray-900/80 backdrop-blur px-3 py-1.5 rounded-full">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                              :class="auctionStatus === 'running' ? 'bg-green-400' : 'bg-yellow-400'"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5"
                              :class="auctionStatus === 'running' ? 'bg-green-500' : 'bg-yellow-500'"></span>
                    </span>
                    <span class="text-xs font-semibold uppercase"
                          :class="auctionStatus === 'running' ? 'text-green-400' : 'text-yellow-400'"
                          x-text="auctionStatus"></span>
                </div>

                {{-- Phase badge --}}
                <div class="bg-gray-900/80 backdrop-blur px-3 py-1.5 rounded-full">
                    <span class="text-xs font-bold uppercase"
                          :class="{
                              'text-blue-400': bidType === 'open' && openBidMode !== 'offline',
                              'text-purple-400': bidType === 'closed' && openBidMode !== 'offline',
                              'text-orange-400': openBidMode === 'offline'
                          }"
                          x-text="openBidMode === 'offline' ? 'OFFLINE' : (bidType === 'closed' ? 'CLOSED BID' : 'OPEN BID')"></span>
                </div>

                {{-- Timer --}}
                <div x-show="displayState === 'bidding' && openBidMode !== 'offline'"
                     class="bg-gray-900/80 backdrop-blur px-4 py-1.5 rounded-full">
                    <span class="text-xl font-bold font-mono"
                          :class="biddingTimerSeconds <= 5 ? 'text-red-500 timer-critical' : 'text-white'"
                          x-text="biddingTimerSeconds + 's'"></span>
                </div>
            </div>

            {{-- ── SHUFFLE ANIMATION OVERLAY ── --}}
            <template x-if="showShuffleOverlay">
                <div class="absolute inset-0 bg-gray-950/95 backdrop-blur-sm flex items-center justify-center z-40">
                    <div class="text-center">
                        <div class="relative w-52 h-52 mx-auto mb-8">
                            <div class="absolute inset-0 rounded-full border-4 border-transparent shuffle-glow"
                                 :class="shufflePhase === 'spinning' ? 'shuffle-ring-spin' : ''"
                                 style="border-top-color: #3b82f6; border-right-color: #8b5cf6;"></div>
                            <div class="absolute inset-3 rounded-full border-2 border-transparent"
                                 :class="shufflePhase === 'spinning' ? 'shuffle-ring-spin' : ''"
                                 style="border-bottom-color: #06b6d4; border-left-color: #10b981; animation-direction: reverse; animation-duration: 0.4s;"></div>
                            <div class="absolute inset-6 rounded-full bg-gray-800 border-2 flex items-center justify-center overflow-hidden"
                                 :class="shufflePhase === 'reveal' ? 'border-emerald-500 shuffle-reveal' : 'border-gray-600'">
                                <template x-if="shufflePhase === 'spinning'">
                                    <div class="text-center px-3">
                                        <div class="text-lg font-bold text-gray-300 truncate" x-text="shuffleDisplayName"></div>
                                    </div>
                                </template>
                                <template x-if="shufflePhase === 'reveal' && shuffleSelectedPlayer">
                                    <div class="w-full h-full">
                                        <template x-if="shuffleSelectedPlayer.image_path">
                                            <img :src="'/storage/' + shuffleSelectedPlayer.image_path" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!shuffleSelectedPlayer.image_path">
                                            <div class="w-full h-full flex items-center justify-center">
                                                <svg class="w-16 h-16 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="shufflePhase === 'spinning'" class="text-xl text-blue-400 font-semibold tracking-wider uppercase">Selecting Player...</div>
                        <div x-show="shufflePhase === 'reveal'" class="shuffle-reveal">
                            <div class="text-3xl font-black text-white mb-1" x-text="shuffleSelectedPlayer?.name || ''"></div>
                            <div class="text-gray-400" x-text="shuffleSelectedPlayer?.player_type || ''"></div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── EMPTY STATE ── --}}
            <div x-show="displayState === 'waiting'" x-transition class="text-center">
                <div class="w-32 h-32 mx-auto mb-6 rounded-full border-2 border-dashed border-gray-700 flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-gray-500 mb-2">Ready to Auction</h2>
                <p class="text-gray-600">Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd> for next player or enter ID below</p>
            </div>

            {{-- ── TUMBLING STATE (legacy compat) ── --}}
            <div x-show="displayState === 'tumbling'" x-transition class="text-center">
                <div class="w-48 h-48 mx-auto mb-6 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center shuffle-glow">
                    <span class="text-6xl font-bold text-white" x-text="tumblerText.charAt(0)"></span>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2" x-text="tumblerText"></h2>
                <p class="text-blue-400">Selecting player...</p>
            </div>

            {{-- ── ACTIVE PLAYER: HORIZONTAL LAYOUT (online/closed) ── --}}
            <div x-show="displayState === 'bidding' && openBidMode !== 'offline'" x-transition class="flex items-stretch px-12 w-full h-full">
                {{-- LEFT: Player Photo + Info --}}
                <div class="flex-1 flex items-center gap-10">
                    {{-- Player Photo --}}
                    <div class="flex-shrink-0">
                        <div class="w-64 h-80 rounded-2xl overflow-hidden bg-gray-800 border-2 border-gray-700 shadow-2xl">
                            <template x-if="currentPlayer?.player?.image_path">
                                <img :src="'/storage/' + currentPlayer.player.image_path" class="w-full h-full object-cover" :alt="currentPlayer.player?.name">
                            </template>
                            <template x-if="!currentPlayer?.player?.image_path">
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
                        <h1 class="text-5xl font-extrabold tracking-tight" x-text="currentPlayer?.player?.name || 'Unknown'"></h1>
                        <div class="flex items-center gap-3 text-lg text-gray-400">
                            <span x-text="getPlayerType(currentPlayer)"></span>
                            <template x-if="getBattingStyle(currentPlayer)">
                                <span class="flex items-center gap-1">
                                    <span class="text-gray-600">&bull;</span>
                                    <span x-text="getBattingStyle(currentPlayer)"></span>
                                </span>
                            </template>
                            <template x-if="getBowlingStyle(currentPlayer)">
                                <span class="flex items-center gap-1">
                                    <span class="text-gray-600">&bull;</span>
                                    <span x-text="getBowlingStyle(currentPlayer)"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Player Stats --}}
                        <template x-if="hasAnyStats(currentPlayer)">
                            <div class="flex gap-3 mt-1">
                                <template x-if="getPlayerStats(currentPlayer).matches != null">
                                    <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                        <div class="text-2xl font-black text-blue-400 leading-none" x-text="getPlayerStats(currentPlayer).matches"></div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Matches</div>
                                    </div>
                                </template>
                                <template x-if="getPlayerStats(currentPlayer).runs != null">
                                    <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                        <div class="text-2xl font-black text-amber-400 leading-none" x-text="getPlayerStats(currentPlayer).runs"></div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Runs</div>
                                    </div>
                                </template>
                                <template x-if="getPlayerStats(currentPlayer).wickets != null">
                                    <div class="bg-gradient-to-b from-gray-800 to-gray-800/60 border border-gray-700/50 rounded-xl px-4 py-2.5 text-center min-w-[72px]">
                                        <div class="text-2xl font-black text-emerald-400 leading-none" x-text="getPlayerStats(currentPlayer).wickets"></div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">Wickets</div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Base Price --}}
                        <div class="mt-4 inline-block border rounded-xl px-6 py-3 border-blue-500/30 bg-blue-500/10">
                            <div class="text-xs uppercase tracking-widest mb-0.5 text-blue-400">Base Price</div>
                            <div class="text-3xl font-black text-blue-400" x-text="formatCurrency(currentPlayer?.base_price)"></div>
                        </div>

                        {{-- Sealed bids count --}}
                        <div x-show="sealedBids.length > 0" class="text-gray-400 text-sm">
                            <span class="text-green-400 font-semibold" x-text="sealedBids.length + ' bid(s) received'"></span>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Current Bid + Team Details --}}
                <div class="w-80 flex items-center justify-center flex-shrink-0">
                    {{-- No bids yet --}}
                    <template x-if="!currentBid || currentBid <= (currentPlayer?.base_price || 0)">
                        <div x-show="!winningTeamName || winningTeamName === 'No Bids'" class="text-center p-8 border-2 border-dashed border-gray-700 rounded-2xl w-full">
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
                    <div x-show="winningTeamName && winningTeamName !== 'No Bids'" class="text-center w-full">
                        {{-- Find current bid team --}}
                        <template x-if="teams.find(t => t.name === winningTeamName)">
                            <div>
                                <div class="w-28 h-28 mx-auto mb-4 rounded-full overflow-hidden border-4 border-emerald-500 shadow-lg shadow-emerald-500/20 bg-gray-800 team-pulse">
                                    <template x-if="teams.find(t => t.name === winningTeamName)?.logo_url">
                                        <img :src="teams.find(t => t.name === winningTeamName).logo_url" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!teams.find(t => t.name === winningTeamName)?.logo_url">
                                        <div class="w-full h-full flex items-center justify-center text-3xl font-black text-emerald-400" x-text="winningTeamName?.substring(0,2).toUpperCase()"></div>
                                    </template>
                                </div>
                                <div class="text-xl font-bold text-emerald-300 mb-4" x-text="winningTeamName"></div>
                            </div>
                        </template>

                        {{-- Current Bid Amount --}}
                        <div class="border-2 border-emerald-500/50 bg-emerald-500/10 rounded-2xl px-6 py-5 backdrop-blur-sm">
                            <div class="text-xs uppercase tracking-widest text-emerald-400 mb-1">Current Bid</div>
                            <div class="text-5xl font-black text-emerald-400" x-text="formatCurrency(currentBid)"></div>
                        </div>

                        {{-- Team Budget --}}
                        <template x-if="teams.find(t => t.name === winningTeamName)">
                            <div>
                                <div class="mt-3 text-sm text-gray-500">
                                    Budget left: <span class="text-gray-400" x-text="formatCurrency(teams.find(t => t.name === winningTeamName)?.remaining_budget)"></span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    Players: <span class="text-gray-400" x-text="teams.find(t => t.name === winningTeamName)?.players_bought || 0"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── OFFLINE BIDDING STATE ── --}}
            <div x-show="displayState === 'bidding' && openBidMode === 'offline'" x-transition class="w-full px-6 py-4 overflow-y-auto h-full">
                {{-- Compact Player Info Bar --}}
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-3 mb-3">
                    <div class="flex items-center gap-3">
                        <img :src="currentPlayer?.player?.image_path ? `/storage/${currentPlayer.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(currentPlayer?.player?.name || 'P')}&size=80&background=random`"
                             class="w-12 h-12 rounded-lg object-cover object-top border-2 border-blue-500/50 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold text-white truncate" x-text="currentPlayer?.player?.name"></h3>
                            <div class="flex items-center gap-2 text-xs text-gray-400 flex-wrap">
                                <span class="px-1.5 py-0.5 bg-gray-700 rounded text-xs" x-text="getPlayerType(currentPlayer)"></span>
                                <span>Base: <span class="text-white font-semibold" x-text="formatCurrency(currentPlayer?.base_price)"></span></span>
                                <span x-show="currentPlayer?.current_price">Curr: <span class="text-orange-400 font-semibold" x-text="formatCurrency(currentPlayer?.current_price)"></span></span>
                                <template x-if="hasAnyStats(currentPlayer)">
                                    <span class="text-gray-500">
                                        <span x-show="getPlayerStats(currentPlayer).matches != null" class="text-blue-400" x-text="getPlayerStats(currentPlayer).matches + 'M'"></span>
                                        <span x-show="getPlayerStats(currentPlayer).matches != null && (getPlayerStats(currentPlayer).runs != null || getPlayerStats(currentPlayer).wickets != null)"> · </span>
                                        <span x-show="getPlayerStats(currentPlayer).runs != null" class="text-amber-400" x-text="getPlayerStats(currentPlayer).runs + 'R'"></span>
                                        <span x-show="getPlayerStats(currentPlayer).runs != null && getPlayerStats(currentPlayer).wickets != null"> · </span>
                                        <span x-show="getPlayerStats(currentPlayer).wickets != null" class="text-emerald-400" x-text="getPlayerStats(currentPlayer).wickets + 'W'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="bg-orange-500/80 px-2 py-1 rounded-full flex-shrink-0">
                            <span class="text-xs font-bold text-white">OFFLINE</span>
                        </div>
                    </div>
                </div>

                {{-- Phase Stepper --}}
                <div class="flex items-center justify-center gap-1 mb-3">
                    <template x-for="(step, idx) in [{label:'Select', phase:'selection'}, {label:'Bids', phase:'bidding'}, {label:'Results', phase:'results'}]" :key="idx">
                        <div class="flex items-center gap-1">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                                 :class="offlinePhase === step.phase ? 'bg-blue-600 text-white' : (['selection','bidding','results'].indexOf(offlinePhase) > idx ? 'bg-green-600/30 text-green-400' : 'bg-gray-700 text-gray-400')">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                                      :class="offlinePhase === step.phase ? 'bg-white text-blue-600' : (['selection','bidding','results'].indexOf(offlinePhase) > idx ? 'bg-green-500 text-white' : 'bg-gray-600 text-gray-300')"
                                      x-text="['selection','bidding','results'].indexOf(offlinePhase) > idx ? '✓' : (idx + 1)"></span>
                                <span x-text="step.label"></span>
                            </div>
                            <svg x-show="idx < 2" class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </template>
                </div>

                {{-- Phase 1: Team Selection --}}
                <div x-show="offlinePhase === 'selection'" x-transition>
                    <div x-show="offlineParticipants.length > 0" class="bg-gray-800/50 border border-gray-700 rounded-xl p-3 mb-4">
                        <p class="text-xs text-gray-400 mb-2">PARTICIPATING TEAMS (<span x-text="offlineParticipants.length"></span>)</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tid in offlineParticipants" :key="tid">
                                <div class="flex items-center gap-2 bg-orange-500/20 border border-orange-500/40 rounded-full px-3 py-1.5 cursor-pointer hover:bg-orange-500/30 transition-all"
                                     @click="toggleOfflineParticipant(tid)">
                                    <template x-if="getTeamById(tid)?.logo_url">
                                        <img :src="getTeamById(tid).logo_url" class="w-6 h-6 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_url">
                                        <div class="w-6 h-6 rounded-full bg-orange-600 flex items-center justify-center text-white text-xs font-bold" x-text="getTeamById(tid)?.short_name || '?'"></div>
                                    </template>
                                    <span class="text-sm text-white font-medium" x-text="getTeamById(tid)?.name"></span>
                                    <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mb-3">
                        <template x-for="team in teams" :key="team.id">
                            <div @click="toggleOfflineParticipant(team.id)"
                                 class="relative bg-gray-800 border-2 rounded-lg p-3 cursor-pointer transition-all hover:scale-[1.03] flex items-center gap-2"
                                 :class="isOfflineParticipant(team.id) ? 'border-orange-500 bg-orange-500/10 team-card-selected' : 'border-gray-700 hover:border-gray-500'">
                                <div x-show="isOfflineParticipant(team.id)" class="absolute top-1 right-1">
                                    <div class="w-5 h-5 rounded-full bg-orange-500 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </div>
                                <template x-if="team.logo_url">
                                    <img :src="team.logo_url" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                </template>
                                <template x-if="!team.logo_url">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0" x-text="team.short_name"></div>
                                </template>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate" x-text="team.name"></p>
                                    <p class="text-xs" :class="team.remaining_budget < maxBudget * 0.2 ? 'text-red-400' : 'text-gray-400'" x-text="formatCurrency(team.remaining_budget) + ' left'"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="text-center">
                        <button @click="startOfflineBidding()"
                                :disabled="offlineParticipants.length === 0"
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 disabled:from-gray-600 disabled:to-gray-700 disabled:cursor-not-allowed text-white rounded-xl font-bold text-base transition-all transform hover:scale-105 disabled:hover:scale-100 shadow-lg shadow-blue-500/30 disabled:shadow-none">
                            <span x-text="offlineParticipants.length > 0 ? 'Start Bidding (' + offlineParticipants.length + ' teams)' : 'Select teams to start'"></span>
                        </button>
                    </div>
                </div>

                {{-- Phase 2: Bid Entry --}}
                <div x-show="offlinePhase === 'bidding'" x-transition>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden mb-4">
                        <div class="p-3 bg-gray-700/50 border-b border-gray-700">
                            <p class="text-sm font-semibold text-white">Enter bid amounts for each team</p>
                        </div>
                        <div class="divide-y divide-gray-700">
                            <template x-for="tid in offlineParticipants" :key="tid">
                                <div class="flex items-center gap-4 p-4">
                                    <template x-if="getTeamById(tid)?.logo_url">
                                        <img :src="getTeamById(tid).logo_url" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_url">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm" x-text="getTeamById(tid)?.short_name || '?'"></div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate" x-text="getTeamById(tid)?.name"></p>
                                        <p class="text-xs text-gray-400">Budget: <span x-text="formatCurrency(getTeamById(tid)?.remaining_budget)"></span></p>
                                    </div>
                                    <div class="w-44">
                                        <div class="flex items-center bg-gray-700 border border-gray-600 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
                                            <input type="number"
                                                   :value="toM(offlineTeamBids[tid])"
                                                   @input="offlineTeamBids[tid] = fromM($event.target.value)"
                                                   class="w-full bg-transparent px-3 py-2 text-white text-sm text-right outline-none"
                                                   placeholder="0" min="0" step="0.5">
                                            <span class="pr-3 text-xs text-gray-400 whitespace-nowrap">L</span>
                                        </div>
                                    </div>
                                    <button @click="toggleOfflineParticipant(tid)" class="p-2 text-gray-400 hover:text-red-400 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <button @click="offlineGoBack()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">&larr; Back to Selection</button>
                        <button @click="endOfflineBidding()"
                                class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                            End Bidding &amp; Show Winner
                        </button>
                    </div>
                </div>

                {{-- Phase 3: Results --}}
                <div x-show="offlinePhase === 'results'" x-transition>
                    <div x-show="offlineHighestBidder" class="bg-gray-800 border-2 border-green-500 rounded-xl p-6 mb-4 text-center offline-winner-glow">
                        <p class="text-green-400 text-sm font-semibold mb-3 uppercase tracking-wider">Winner</p>
                        <template x-if="getTeamById(offlineHighestBidder)?.logo_url">
                            <img :src="getTeamById(offlineHighestBidder).logo_url" class="w-20 h-20 mx-auto rounded-full object-cover mb-3 border-4 border-green-500/50">
                        </template>
                        <template x-if="!getTeamById(offlineHighestBidder)?.logo_url">
                            <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white font-bold text-2xl mb-3" x-text="getTeamById(offlineHighestBidder)?.short_name || '?'"></div>
                        </template>
                        <h3 class="text-2xl font-bold text-white mb-1" x-text="getTeamById(offlineHighestBidder)?.name"></h3>
                        <p class="text-3xl font-black text-green-400 mb-4" x-text="formatCurrency(offlineHighestAmount)"></p>
                        <button @click="confirmOfflineSale(offlineHighestBidder, offlineHighestAmount)"
                                class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl font-bold text-lg transition-all transform hover:scale-105 shadow-lg shadow-green-500/30">
                            Confirm Sale to Winner
                        </button>
                    </div>
                    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden mb-4">
                        <div class="p-3 bg-gray-700/50 border-b border-gray-700">
                            <p class="text-sm font-semibold text-white">All Bids (Ranked)</p>
                        </div>
                        <div class="divide-y divide-gray-700">
                            <template x-for="(entry, idx) in Object.entries(offlineTeamBids).sort((a,b) => b[1] - a[1])" :key="entry[0]">
                                <div class="flex items-center gap-4 p-4"
                                     :class="Number(entry[0]) === offlineHighestBidder ? 'bg-green-500/10' : ''">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"
                                         :class="idx === 0 ? 'bg-green-500 text-white' : 'bg-gray-700 text-gray-400'"
                                         x-text="'#' + (idx + 1)"></div>
                                    <template x-if="getTeamById(Number(entry[0]))?.logo_url">
                                        <img :src="getTeamById(Number(entry[0])).logo_url" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(Number(entry[0]))?.logo_url">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm" x-text="getTeamById(Number(entry[0]))?.short_name || '?'"></div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate" x-text="getTeamById(Number(entry[0]))?.name"></p>
                                    </div>
                                    <p class="font-bold text-lg" :class="Number(entry[0]) === offlineHighestBidder ? 'text-green-400' : 'text-gray-300'" x-text="formatCurrency(entry[1])"></p>
                                    <button x-show="Number(entry[0]) !== offlineHighestBidder"
                                            @click="confirmOfflineSale(Number(entry[0]), entry[1])"
                                            class="px-3 py-1.5 bg-gray-700 hover:bg-green-600 text-gray-300 hover:text-white rounded-lg text-xs font-semibold transition-all">Sell</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <button @click="offlineGoBack()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">&larr; Back to Bids</button>
                        <button @click="resetOfflinePanel()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all">Reset</button>
                    </div>
                </div>
            </div>

            {{-- ── PAUSED OVERLAY ── --}}
            <div x-show="auctionStatus === 'paused'" x-transition x-cloak
                 class="absolute inset-0 bg-gray-950/85 backdrop-blur-sm flex flex-col items-center justify-center z-40 text-center">
                <div class="text-7xl mb-4">⏸️</div>
                <div class="text-4xl font-extrabold uppercase tracking-widest text-yellow-400">Auction Paused</div>
                <p class="text-gray-300 mt-3">Bidding is on hold. Click <span class="font-semibold text-white">Resume</span> to continue.</p>
            </div>

            {{-- ── SOLD OVERLAY ── --}}
            <div x-show="displayState === 'sold'" x-transition class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center z-30">
                <div class="text-center space-y-6">
                    <div class="w-40 h-40 mx-auto rounded-full overflow-hidden border-4 border-emerald-500 shadow-lg shadow-emerald-500/20">
                        <template x-if="lastSoldPlayer?.player?.image_path">
                            <img :src="'/storage/' + lastSoldPlayer.player.image_path" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!lastSoldPlayer?.player?.image_path">
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        </template>
                    </div>
                    <div class="sold-stamp inline-block bg-emerald-500 text-white px-8 py-3 text-4xl font-black tracking-wider rounded-lg uppercase" style="transform: rotate(-12deg);">SOLD!</div>
                    <h2 class="text-4xl font-bold" x-text="lastSoldPlayer?.player?.name"></h2>
                    <div class="text-5xl font-black text-emerald-400" x-text="formatCurrency(lastSoldPlayer?.final_price)"></div>
                    <div class="flex items-center justify-center gap-3">
                        <template x-if="lastSoldPlayer?.winning_team?.logo_url">
                            <img :src="lastSoldPlayer.winning_team.logo_url" class="w-12 h-12 rounded-full object-cover border-2 border-gray-600">
                        </template>
                        <span class="text-2xl text-gray-300" x-text="lastSoldPlayer?.winning_team?.name"></span>
                    </div>
                    <p class="text-gray-400 text-lg">Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd> for next player</p>
                </div>
            </div>

            {{-- ── UNSOLD OVERLAY ── --}}
            <div x-show="displayState === 'unsold'" x-transition class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm flex items-center justify-center z-30">
                <div class="text-center space-y-6">
                    <div class="w-40 h-40 mx-auto rounded-full overflow-hidden border-4 border-red-500 shadow-lg shadow-red-500/20">
                        <template x-if="lastSoldPlayer?.player?.image_path">
                            <img :src="'/storage/' + lastSoldPlayer.player.image_path" class="w-full h-full object-cover grayscale">
                        </template>
                        <template x-if="!lastSoldPlayer?.player?.image_path">
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                        </template>
                    </div>
                    <div class="unsold-stamp inline-block bg-red-500 text-white px-6 py-3 text-3xl font-black tracking-wider rounded-lg uppercase" style="transform: rotate(-12deg);">Unsold</div>
                    <h2 class="text-4xl font-bold" x-text="lastSoldPlayer?.player?.name"></h2>
                    <p class="text-gray-400 text-lg">Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd> for next player</p>
                </div>
            </div>

        </div>

        {{-- ══════════════════════════════════════════════ --}}
        {{-- BOTTOM TOOLBAR --}}
        {{-- ══════════════════════════════════════════════ --}}
        <div class="flex-shrink-0">
            {{-- Timer progress bar --}}
            <div x-show="displayState === 'bidding' && openBidMode !== 'offline' && timerEnabled" class="h-1 bg-gray-800">
                <div class="h-full transition-all duration-1000 ease-linear"
                     :class="biddingTimerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500'"
                     :style="`width: ${timerWidth}%`"></div>
            </div>

            {{-- ══ POOL CONTROL STRIP ══
                 The auction is locked to one pool at a time, so which pool is running
                 and how far through it we are belongs on screen at all times. --}}
            <div class="bg-gray-950 border-t border-gray-800 px-4 py-2 flex items-center gap-3 overflow-x-auto">
                <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold flex-shrink-0">Pool</span>

                {{-- No pool running yet. --}}
                <template x-if="!activePool">
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs text-amber-400">No pool running —</span>
                        <template x-for="p in pools.filter(p => p.is_enabled && p.waiting > 0)" :key="p.id">
                            <button @click="activatePool(p.id)"
                                    class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded transition whitespace-nowrap">
                                Start <span x-text="p.name"></span>
                                <span class="opacity-70" x-text="'(' + p.waiting + ')'"></span>
                            </button>
                        </template>
                        <span x-show="pools.filter(p => p.is_enabled && p.waiting > 0).length === 0"
                              class="text-xs text-gray-500">no enabled pool has players left</span>
                    </div>
                </template>

                {{-- A pool is running. --}}
                <template x-if="activePool">
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="px-2 py-0.5 bg-indigo-600/20 border border-indigo-500/40 rounded text-xs font-bold text-indigo-300"
                              x-text="activePool.name"></span>
                        <span x-show="activePool.category" class="text-[10px] text-gray-500" x-text="activePool.category"></span>

                        {{-- Lot progress. --}}
                        <div class="flex items-center gap-2">
                            <div class="w-28 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-500 transition-all"
                                     :style="`width: ${activePool.total ? (activePool.done / activePool.total * 100) : 0}%`"></div>
                            </div>
                            <span class="text-xs font-mono text-gray-400">
                                <span x-text="activePool.done"></span>/<span x-text="activePool.total"></span>
                            </span>
                        </div>

                        <span x-show="activePool.times_used > 1" class="text-[10px] text-gray-500"
                              x-text="'run #' + activePool.times_used"></span>

                        {{-- Exhausted: offer the next pool without auto-advancing. --}}
                        <template x-if="activePool.exhausted">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-emerald-400 font-semibold">Pool complete</span>
                                <button @click="completeActivePool()"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded transition whitespace-nowrap">
                                    Close <span x-text="activePool.name"></span>
                                </button>
                                <template x-if="nextPool">
                                    <button @click="activatePool(nextPool.id)"
                                            class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded transition whitespace-nowrap">
                                        Start <span x-text="nextPool.name"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <button x-show="!activePool.exhausted && displayState !== 'bidding'"
                                @click="completeActivePool()"
                                class="px-2 py-1 bg-gray-800 hover:bg-gray-700 text-gray-300 text-[10px] font-semibold rounded transition whitespace-nowrap"
                                title="Close this pool early — players left in it stay unsold">
                            Close early
                        </button>
                    </div>
                </template>

                <div class="flex-1"></div>

                {{-- Timer toggle. Offline only: online bidding needs the clock. --}}
                <button @click="toggleTimer()"
                        :disabled="openBidMode !== 'offline'"
                        class="px-2 py-1 rounded text-[10px] font-semibold transition whitespace-nowrap flex-shrink-0"
                        :class="timerEnabled
                            ? 'bg-blue-600/20 border border-blue-500/40 text-blue-300'
                            : 'bg-gray-800 border border-gray-700 text-gray-500'"
                        :title="openBidMode !== 'offline'
                            ? 'The timer is required while bidding is online'
                            : (timerEnabled ? 'Turn the bid timer off' : 'Turn the bid timer on')">
                    TIMER <span x-text="timerEnabled ? 'ON' : 'OFF'"></span>
                </button>
                <span x-show="timerEnabled" class="text-[10px] text-gray-500 flex-shrink-0"
                      x-text="timerExpiryAction === 'auto_sell' ? 'auto-sell at 0' : 'manual at 0'"></span>
            </div>

            {{-- ══ TEAM BID BUBBLES ══
                 Its own band above the toolbar rather than squeezed between the phase
                 buttons and UNDO, where the logos were clipped to unreadable slivers.

                 Each team is a single circular bubble — the button IS the circle, with no
                 card or panel around it. Purse rides as a pill on the bubble's lower edge
                 and squad count as a badge on its upper edge, so the figures are readable
                 without adding a box. Two rows filled column-wise with equal-width
                 columns, so any number of teams stays evenly spaced and centred. --}}
            <div class="bg-gray-900/60 border-t border-gray-800 px-4 py-3">
                <div class="grid grid-rows-2 grid-flow-col auto-cols-fr gap-x-3 gap-y-4 justify-items-center">
                    <template x-for="(team, idx) in teams" :key="team.id">
                        <button @click="bidForTeam(team.id)"
                                :disabled="isTeamBidDisabled(team)"
                                {{-- aspect-square + rounded-full: the control is a true
                                     circle, never squashed by its grid column. --}}
                                class="relative group rounded-full border-2 overflow-visible bg-gray-800 flex items-center justify-center transition-all duration-200 flex-shrink-0"
                                :class="[
                                    winningTeamName === team.name
                                        ? 'w-[62px] h-[62px] border-emerald-400 team-pulse scale-105'
                                        : 'w-[52px] h-[52px] border-gray-600 hover:border-gray-300 hover:scale-105',
                                    team.excluded ? 'border-amber-600/70' : '',
                                    isTeamBidDisabled(team) ? 'opacity-45 cursor-not-allowed hover:scale-100' : ''
                                ]"
                                :title="teamTooltip(team)">

                            {{-- Logo fills the bubble. --}}
                            <span class="absolute inset-0 rounded-full overflow-hidden flex items-center justify-center"
                                  :class="team.excluded ? 'grayscale' : ''">
                                <template x-if="team.logo_url">
                                    <img :src="team.logo_url" :alt="team.name" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!team.logo_url">
                                    <span class="text-[11px] font-bold text-gray-300 leading-none"
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

                            {{-- Keyboard shortcut hint (1-9, 0). --}}
                            <span class="absolute -top-1.5 -left-1 z-10 w-[15px] h-[15px] bg-gray-700 border border-gray-900 rounded-full text-[9px] font-mono flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                  x-text="idx < 9 ? String(idx + 1) : (idx === 9 ? '0' : '')"></span>
                        </button>
                    </template>
                </div>

                <p x-show="!teams.length" class="text-center text-xs text-gray-500 py-2">
                    No teams in this tournament yet.
                </p>
            </div>

            <div class="h-14 bg-gray-900 border-t border-gray-800 flex items-center px-4 gap-2">

                {{-- 1. Player Input --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-500 font-mono text-lg">#</span>
                    <input type="text" x-model="playerNumberInput"
                           @keydown.enter="loadPlayerByNumber()"
                           placeholder="ID"
                           class="w-14 bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                </div>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 2. NEXT button --}}
                <button @click="loadNextPlayer()"
                        :disabled="isTumbling || displayState === 'bidding' || availablePlayers.length === 0"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:cursor-not-allowed text-white text-sm font-bold rounded transition-colors whitespace-nowrap">
                    NEXT (N)
                </button>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 3. Bid Phase Buttons --}}
                <div class="flex gap-1" x-show="displayState === 'bidding'">
                    <button @click="switchBidPhase('open')"
                            class="px-2.5 py-1.5 rounded text-xs font-semibold transition"
                            :class="bidType === 'open' && openBidMode !== 'offline' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'">Open</button>
                    <button @click="switchBidPhase('closed')"
                            class="px-2.5 py-1.5 rounded text-xs font-semibold transition"
                            :class="bidType === 'closed' && openBidMode !== 'offline' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'">Closed</button>
                    <button @click="switchBidPhase('offline')"
                            class="px-2.5 py-1.5 rounded text-xs font-semibold transition"
                            :class="openBidMode === 'offline' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'">Offline</button>
                </div>

                <div class="flex-1"></div>

                {{-- Quick-bid jumps: applied to whichever team is currently leading,
                     for when the room moves faster than the standard increment. --}}
                <template x-if="quickBidSteps.length && displayState === 'bidding' && openBidMode !== 'offline'">
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <div class="w-px h-8 bg-gray-700"></div>
                        <template x-for="(step, i) in quickBidSteps" :key="i">
                            <button @click="toggleQuickStep(i)"
                                    class="px-2 py-1 text-[11px] font-bold rounded transition whitespace-nowrap border"
                                    :class="armedStepIndex === i
                                        ? 'bg-purple-500 border-purple-300 text-white ring-2 ring-purple-400'
                                        : 'bg-purple-600/25 border-purple-500/50 text-purple-200 hover:bg-purple-600/50'"
                                    :title="armedStepIndex === i
                                        ? 'Armed — click a team to jump by ' + formatCurrency(step)
                                        : 'Arm a jump of ' + formatCurrency(step)">
                                +<span x-text="formatCurrency(step)"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- Undo: recovery for a wrong-team click. --}}
                <button @click="undoLast()"
                        :disabled="!canUndo || isUndoing"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap flex items-center gap-1"
                        :class="(canUndo && !isUndoing) ? 'bg-orange-600 hover:bg-orange-500' : 'bg-gray-700 cursor-not-allowed opacity-50'"
                        :title="canUndo ? ('Undo (U): ' + (nextUndoLabel || 'last action')) : 'Nothing to undo'">
                    <span>&#8630;</span> UNDO
                </button>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 5. Action Buttons --}}
                <button @click="sellPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding') ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">SELL</button>
                <button @click="passPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding' || !!currentPlayer?.current_bid_team_id"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding' && !currentPlayer?.current_bid_team_id) ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">PASS</button>
                <button @click="rebidCurrentPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding') ? 'bg-yellow-600 hover:bg-yellow-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">RE-BID</button>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 6. Auction Controls --}}
                <button @click="togglePause()" x-show="auctionStatus === 'running'"
                        class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs font-semibold transition">Pause</button>
                <button @click="startAuction()" x-show="auctionStatus === 'paused' || auctionStatus === 'scheduled'"
                        class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-semibold transition">
                    <span x-text="auctionStatus === 'paused' ? 'Resume' : 'Start'"></span>
                </button>
                <button @click="endAuction()" x-show="auctionStatus === 'running'"
                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-semibold transition">End</button>
                <button @click="restartAuction()" x-show="auctionStatus === 'completed' || auctionStatus === 'running' || auctionStatus === 'paused'"
                        class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs font-semibold transition">Restart</button>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 7. Fullscreen Toggle --}}
                <button @click="toggleFullscreen()" class="w-8 h-8 rounded flex items-center justify-center bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition-colors" title="Toggle Fullscreen (F)">
                    <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
                    <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4"/></svg>
                </button>

                {{-- 8. Side Panel Toggles --}}
                <div class="flex items-center gap-1">
                    <button @click="showSidePanelFn('queue')" :class="sidePanel === 'queue' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Queue">Q</button>
                    <button @click="showSidePanelFn('teams')" :class="sidePanel === 'teams' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Teams">T</button>
                    <button @click="showSidePanelFn('bids')" :class="sidePanel === 'bids' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Bids">B</button>
                    <button @click="showSidePanelFn('allPlayers')" :class="sidePanel === 'allPlayers' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="All Players">A</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SIDE PANEL (slides from right) --}}
    {{-- ══════════════════════════════════════════════ --}}
    <template x-if="sidePanel">
        <div class="absolute inset-0 z-50" @click.self="sidePanel = null">
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="absolute right-0 top-0 bottom-14 w-[420px] bg-gray-900 border-l border-gray-800 overflow-y-auto shadow-2xl side-panel-active">
                {{-- Panel Header --}}
                <div class="sticky top-0 bg-gray-900 border-b border-gray-800 px-5 py-4 flex items-center justify-between z-10">
                    <h3 class="text-lg font-bold">
                        <span x-show="sidePanel === 'queue'">Player Queue</span>
                        <span x-show="sidePanel === 'teams'">Team Budgets</span>
                        <span x-show="sidePanel === 'bids'">Sealed Bids</span>
                        <span x-show="sidePanel === 'allPlayers'">All Players</span>
                    </h3>
                    <button @click="sidePanel = null" class="text-gray-500 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- ═══ QUEUE PANEL ═══ --}}
                <div x-show="sidePanel === 'queue'" class="p-4">
                    <p class="text-sm text-gray-400 mb-3"><span x-text="availablePlayers.length"></span> players waiting</p>
                    {{-- Search, because the whole queue is now listed (it used to be
                         capped at 30, which made most players unreachable by click). --}}
                    <input type="search" x-model="queueSearchQuery" placeholder="Filter by name…"
                           class="w-full mb-3 bg-gray-800 border border-gray-700 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-blue-500">
                    <div class="space-y-2">
                        <template x-for="(player, index) in filteredQueue" :key="player.id">
                            <div class="bg-gray-800 rounded-lg p-3 cursor-pointer hover:bg-gray-750 transition-colors"
                                 @click="selectAndPutOnBid(player)">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <img :src="player.image_path ? `/storage/${player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&size=40&background=random`"
                                             class="w-10 h-10 rounded-full object-cover">
                                        <span class="absolute -top-1 -left-1 w-5 h-5 bg-blue-600 rounded-full text-xs flex items-center justify-center text-white font-bold"
                                              x-text="index + 1"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate" x-text="player.name"></p>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs text-gray-400" x-text="player.player_type"></span>
                                            <template x-if="player.total_matches != null || player.total_runs != null || player.total_wickets != null">
                                                <span class="text-[10px] text-gray-500">
                                                    <span x-show="player.total_matches != null" class="text-blue-400/70" x-text="player.total_matches + 'M'"></span>
                                                    <span x-show="player.total_matches != null && (player.total_runs != null || player.total_wickets != null)">·</span>
                                                    <span x-show="player.total_runs != null" class="text-amber-400/70" x-text="player.total_runs + 'R'"></span>
                                                    <span x-show="player.total_runs != null && player.total_wickets != null">·</span>
                                                    <span x-show="player.total_wickets != null" class="text-emerald-400/70" x-text="player.total_wickets + 'W'"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-green-400 font-medium" x-text="formatCurrency(player.base_price)"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="availablePlayers.length === 0" class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm">All players auctioned!</p>
                        </div>
                    </div>
                </div>

                {{-- ═══ TEAMS PANEL ═══ --}}
                <div x-show="sidePanel === 'teams'" class="p-4 space-y-3">
                    <template x-for="team in teams" :key="team.id">
                        <div class="bg-gray-800 rounded-xl p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <template x-if="team.logo_url">
                                    <img :src="team.logo_url" class="w-10 h-10 rounded-full object-cover border border-gray-600">
                                </template>
                                <template x-if="!team.logo_url">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold"
                                         x-text="team.short_name"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-white truncate" x-text="team.name"></p>
                                    <p class="text-xs text-gray-400"><span x-text="team.players_bought"></span> players</p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Remaining</span>
                                    <span class="font-bold" :class="team.remaining_budget < maxBudget * 0.2 ? 'text-red-400' : 'text-green-400'"
                                          x-text="formatCurrency(team.remaining_budget)"></span>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                         :class="team.remaining_budget < maxBudget * 0.2 ? 'bg-red-500' : 'bg-green-500'"
                                         :style="`width: ${(team.remaining_budget / maxBudget) * 100}%`"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Spent: <span x-text="formatCurrency(team.total_spent)"></span></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ═══ BIDS PANEL ═══ --}}
                <div x-show="sidePanel === 'bids'" class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-gray-400">Current player bids</span>
                        <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-400" x-text="sealedBids.length + ' bids'"></span>
                    </div>
                    <div class="space-y-2">
                        <template x-for="bid in sealedBids" :key="bid.id">
                            <div class="bg-gray-800 rounded-xl p-4">
                                <div class="flex items-center gap-3 mb-3">
                                    <template x-if="bid.team_logo">
                                        <img :src="bid.team_logo" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!bid.team_logo">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm"
                                             x-text="bid.team_name.charAt(0)"></div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white text-sm truncate" x-text="bid.team_name"></p>
                                        <p class="text-xs text-gray-400" x-text="bid.user_name"></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-green-400 text-lg" x-text="formatCurrency(bid.amount)"></p>
                                        <p class="text-xs text-gray-500" x-text="new Date(bid.created_at).toLocaleTimeString()"></p>
                                    </div>
                                </div>
                                <button @click="confirmSellToTeam(bid)"
                                        class="w-full py-2 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-lg text-sm font-bold transition-all">
                                    Sold To This Team
                                </button>
                            </div>
                        </template>
                        <div x-show="sealedBids.length === 0" class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm">Waiting for bids...</p>
                        </div>
                    </div>
                </div>

                {{-- ═══ ALL PLAYERS PANEL ═══ --}}
                <div x-show="sidePanel === 'allPlayers'" class="p-4">
                    <input type="text" x-model="playerSearchQuery" placeholder="Search player name..."
                           class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 mb-3">
                    <div class="space-y-2 max-h-[calc(100vh-200px)] overflow-y-auto">
                        <template x-for="player in filteredAllPlayers" :key="player.id">
                            <div class="bg-gray-800 rounded-lg p-3">
                                <div class="flex items-center gap-3">
                                    <img :src="player.image_path ? `/storage/${player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.name)}&size=40&background=random`"
                                         class="w-10 h-10 rounded-full object-cover">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate" x-text="player.name"></p>
                                        <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                                            <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                                                  :class="{
                                                      'bg-green-500/20 text-green-400': player.status === 'sold',
                                                      'bg-red-500/20 text-red-400': player.status === 'unsold',
                                                      'bg-blue-500/20 text-blue-400': player.status === 'on_auction',
                                                      'bg-gray-500/20 text-gray-400': player.status === 'waiting'
                                                  }"
                                                  x-text="player.status.toUpperCase()"></span>
                                            <span x-show="player.sold_to_team" class="text-xs text-gray-400 truncate" x-text="player.sold_to_team"></span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <template x-if="player.status === 'sold' || player.status === 'unsold'">
                                            <button @click="reAuctionPlayer(player)"
                                                    class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs font-medium transition">Re-auction</button>
                                        </template>
                                        <template x-if="player.status === 'sold'">
                                            <p class="text-xs text-green-400 font-medium mt-1" x-text="formatCurrency(player.final_price)"></p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="filteredAllPlayers.length === 0" class="text-center py-8 text-gray-500">
                            <p class="text-sm">No players found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>

<script>
function auctionOrganizerPanel() {
    return {
        // Constants from DB
        BID_TIMER_DURATION: {{ $auction->bid_timer_seconds ?? 30 }},
        BID_TIMER_RESET_TO: {{ $auction->bid_timer_reset_seconds ?? 15 }},

        // State
        auctionId: null,
        auctionStatus: 'scheduled',
        availablePlayers: [],
        teams: [],
        maxBudget: 0,

        // Display states: 'waiting', 'tumbling', 'bidding', 'sold', 'unsold'
        displayState: 'waiting',
        statusText: 'Click "Next Player" to begin',
        tumblerText: '',

        currentPlayer: null,
        lastSoldPlayer: null,
        currentBid: 0,
        winningTeamName: 'No Bids',
        bidLog: [],

        // Closed bid state
        sealedBids: [],
        sealedBidPollInterval: null,
        biddingClosed: false,
        showSellModal: false,
        sellModalData: { team_id: '', amount: '' },

        // Online/Offline mode state
        bidType: '{{ $auction->bid_type ?? 'open' }}',
        openBidMode: '{{ $auction->open_bid_mode ?? 'online' }}',
        hasOnlineOfflineMode: {{ ($auction->online_bid_limit_from !== null && $auction->online_bid_limit_to !== null) ? 'true' : 'false' }},
        hasAutoPhaseTransition: {{ ($auction->closed_bid_starts_at !== null) ? 'true' : 'false' }},
        modeManuallyOverridden: {{ $auction->mode_manually_overridden ? 'true' : 'false' }},
        offlineSaleTeamId: '',
        offlineSaleAmount: '',

        // Offline bidding panel state
        offlinePhase: 'selection',
        offlineParticipants: [],
        offlineTeamBids: {},
        offlineHighestBidder: null,
        offlineHighestAmount: 0,

        // Shuffle animation
        showShuffleOverlay: false,
        shufflePhase: 'spinning',
        shuffleDisplayName: '',
        shuffleSelectedPlayer: null,
        _shuffleInterval: null,

        isTumbling: false,
        selectedPlayerId: null,

        // All Players tab
        playerListTab: 'queue',
        playerSearchQuery: '',
        allPlayers: [],

        // Jump-to-player by id. These were bound in the toolbar but never declared
        // here, so the "#" box and its Enter handler threw on every keystroke.
        playerNumberInput: '',

        // Queue filter — the full waiting list is rendered, so it needs a search.
        queueSearchQuery: '',

        // Undo stack
        canUndo: false,
        nextUndoLabel: null,
        isUndoing: false,

        // Increment ladder, resolved server-side.
        nextBidAmount: null,
        bidIncrement: null,
        maxBidReached: false,
        quickBidSteps: [],
        // Armed jump amount: the next team click uses this instead of the increment.
        armedStepIndex: null,

        // Pool lock: the auction runs one pool at a time.
        activePool: null,
        nextPool: null,
        pools: [],

        // Timer, driven off the server clock rather than a local countdown.
        timerEnabled: {{ $auction->timerApplies() ? 'true' : 'false' }},
        timerExpiryAction: '{{ $auction->timer_expiry_action ?? 'manual' }}',
        timerExpired: false,
        _timerFiring: false,
        // Which player we've already announced time-up for, so it fires once.
        _timerFiredForPlayer: null,

        // What amounts are called, from the auction's settings.
        amountUnit: @json($auction->amountUnitConfig()),

        // Closing calls ("going once, going twice"). Thresholds come from the server.
        finalCall: null,
        finalCallStages: @json($auction->finalCallStages()),

        // Squad-reserve rule, mirrored from the server so exclusions can be explained.
        minSquadSize: {{ (int) $auction->minSquadSize() }},
        minPricePerPlayer: {{ (float) $auction->minPricePerPlayer() }},

        // Side panel
        sidePanel: null,
        isFullscreen: false,

        // Timer
        biddingTimerInterval: null,
        biddingTimerSeconds: 0,
        timerWidth: 100,
        _lastKnownBid: 0,

        init(auctionId, status, players, teams, maxBudget, currentPlayer) {
            this.auctionId = auctionId;
            this.auctionStatus = status;
            this.availablePlayers = players;
            this.teams = teams;
            this.maxBudget = maxBudget;

            if (currentPlayer) {
                this.currentPlayer = currentPlayer;
                this.currentBid = currentPlayer.current_price || currentPlayer.base_price;
                this._lastKnownBid = this.currentBid;
                this.displayState = 'bidding';
                this.sealedBids = [];
                this.startBiddingTimer();
            }

            this.startStatePolling();

            // Listen for fullscreen changes (e.g. user presses Esc to exit)
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
        },

        toggleFullscreen() {
            const el = document.getElementById('organizerPanelWrapper');
            if (!document.fullscreenElement) {
                (el || document.documentElement).requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        },

        // ---- Timer logic ----
        startBiddingTimer(duration) {
            this.stopBiddingTimer();
            this.biddingTimerSeconds = duration || this.BID_TIMER_DURATION;
            this.timerWidth = 100;
            const maxSeconds = this.biddingTimerSeconds;
            this.biddingTimerInterval = setInterval(() => {
                this.biddingTimerSeconds--;
                this.timerWidth = Math.max(0, (this.biddingTimerSeconds / maxSeconds) * 100);
                // Escalate the closing call locally — polls are 2s apart, and the calls
                // land on exact seconds.
                this.refreshFinalCall();
                if (this.biddingTimerSeconds <= 0) {
                    this.stopBiddingTimer();
                }
            }, 1000);
        },

        resetBiddingTimer() {
            const resetTo = this.BID_TIMER_RESET_TO || this.BID_TIMER_DURATION;
            // A new bid restarts the clock, which clears any call already showing.
            this.finalCall = null;
            this.startBiddingTimer(resetTo);
        },

        stopBiddingTimer() {
            if (this.biddingTimerInterval) {
                clearInterval(this.biddingTimerInterval);
                this.biddingTimerInterval = null;
            }
        },

        /**
         * Re-derive the closing call from the ticking countdown, using the thresholds
         * the server supplied. Announces each new stage once.
         */
        refreshFinalCall() {
            if (!this.timerEnabled || this.displayState !== 'bidding') {
                this.finalCall = null;
                return;
            }

            const call = window.auctionFinalCallFor
                ? window.auctionFinalCallFor(this.biddingTimerSeconds, this.finalCallStages)
                : null;

            const previousStage = this.finalCall?.stage ?? 0;
            this.finalCall = call;

            if (call && call.stage > previousStage) {
                this.statusText = `${call.label}${call.is_final ? ' — going to the hammer!' : ''}`;
            }
        },

        // ---- Polling-based live updates ----
        _lastCurrentPlayerId: null,
        _pollInterval: null,

        startStatePolling() {
            this._lastCurrentPlayerId = this.currentPlayer?.id || null;
            this._pollInterval = setInterval(() => this.pollAuctionState(), 2000);
        },

        async pollAuctionState() {
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/poll-state`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();

                this.auctionStatus = data.auction_status;

                // Freeze the bidding countdown while paused; it resumes on the next
                // bid/poll once the auction is running again.
                if (data.auction_status === 'paused') {
                    this.stopBiddingTimer();
                }

                if (data.open_bid_mode !== undefined) {
                    this.openBidMode = data.open_bid_mode;
                }
                if (data.mode_manually_overridden !== undefined) {
                    this.modeManuallyOverridden = data.mode_manually_overridden;
                }
                if (data.bid_type !== undefined) {
                    this.bidType = data.bid_type;
                    this.hasOnlineOfflineMode = data.online_bid_limit_from !== null && data.online_bid_limit_to !== null;
                    this.hasAutoPhaseTransition = data.closed_bid_starts_at !== null;
                }

                this.availablePlayers = (data.available_players || []).map(ap => ({
                    id: ap.id,
                    name: ap.player?.name || 'Unknown',
                    base_price: ap.base_price,
                    image_path: ap.player?.image_path || null,
                    player_type: ap.player?.player_type?.name || ap.player?.player_type?.type || 'Player',
                    batting_style: ap.player?.batting_profile?.name || ap.player?.batting_profile?.style || null,
                    bowling_style: ap.player?.bowling_profile?.name || ap.player?.bowling_profile?.style || null,
                    total_matches: ap.player?.total_matches || null,
                    total_runs: ap.player?.total_runs || null,
                    total_wickets: ap.player?.total_wickets || null,
                }));

                // Purse figures come from the server (AuctionPoolService) — no local
                // fallback formula, which used to be able to disagree with what the
                // server would actually accept at SELL.
                this.teams = data.teams || [];

                this.canUndo = !!data.can_undo;
                this.nextUndoLabel = data.next_undo || null;
                this.activePool = data.active_pool || null;
                this.nextPool = data.next_pool || null;
                this.pools = data.pools || [];
                this.quickBidSteps = data.quick_bid_steps || [];
                this.timerEnabled = !!data.timer_enabled;
                this.timerExpiryAction = data.timer_expiry_action || 'manual';

                // The server owns the clock; the local countdown only renders it.
                if (data.timer_seconds_remaining !== null && data.timer_seconds_remaining !== undefined) {
                    this.biddingTimerSeconds = data.timer_seconds_remaining;
                    const limit = data.bid_timer_seconds || 30;
                    this.timerWidth = limit > 0 ? (data.timer_seconds_remaining / limit) * 100 : 0;
                }
                this.timerExpired = !!data.timer_expired;

                // Re-sync the closing call against the server's clock, so a drifting or
                // backgrounded tab still shows the right stage.
                if (data.amount_unit) this.amountUnit = data.amount_unit;
                if (data.final_call_stages) this.finalCallStages = data.final_call_stages;
                this.refreshFinalCall();

                // Time up: hand it to the server, which decides auto-sell vs lock.
                if (this.timerExpired && this.timerEnabled && data.current_player) {
                    this.handleTimerExpiry(data.current_player.id);
                }
                if (data.min_squad_size) this.minSquadSize = data.min_squad_size;
                if (data.min_price_per_player !== undefined) this.minPricePerPlayer = Number(data.min_price_per_player);
                this.nextBidAmount = data.next_bid_amount ?? null;
                this.bidIncrement = data.bid_increment ?? null;
                this.maxBidReached = !!data.max_bid_reached;

                const newPlayer = data.current_player;
                const prevId = this._lastCurrentPlayerId;

                if (newPlayer) {
                    if (newPlayer.id !== prevId) {
                        // New player on the block — allow a fresh time-up announcement.
                        this._timerFiredForPlayer = null;
                        this.currentPlayer = newPlayer;
                        this.currentBid = newPlayer.current_price || newPlayer.base_price;
                        this._lastKnownBid = this.currentBid;
                        this.displayState = 'bidding';
                        this.biddingClosed = false;
                        this.sealedBids = [];
                        this.resetOfflinePanel();
                        this.statusText = `${newPlayer.player?.name} is now live!`;
                        this._lastCurrentPlayerId = newPlayer.id;
                        this.startBiddingTimer();
                    } else {
                        const newBid = newPlayer.current_price || this.currentBid;
                        if (newBid !== this._lastKnownBid) {
                            this._lastKnownBid = newBid;
                            this.resetBiddingTimer();
                        }
                        this.currentBid = newBid;
                        this.currentPlayer = newPlayer;
                    }

                    // Update winning team from current player data
                    if (newPlayer.current_bid_team_id) {
                        const bidTeam = this.teams.find(t => t.id == newPlayer.current_bid_team_id);
                        if (bidTeam) this.winningTeamName = bidTeam.name;
                    } else {
                        this.winningTeamName = 'No Bids';
                    }

                    this.fetchSealedBids();
                } else if (prevId && !newPlayer) {
                    this.stopBiddingTimer();
                    this.biddingTimerSeconds = 0;
                    this.timerWidth = 0;

                    const soldPlayers = data.sold_players || [];
                    const justSold = soldPlayers.find(sp => sp.id === prevId);

                    if (justSold) {
                        this.lastSoldPlayer = {
                            player: justSold.player,
                            final_price: justSold.final_price || this.currentBid,
                            winning_team: justSold.sold_to_team
                        };
                        this.displayState = justSold.sold_to_team ? 'sold' : 'unsold';
                    } else {
                        this.displayState = 'unsold';
                    }

                    this.currentPlayer = null;
                    this.currentBid = 0;
                    this.winningTeamName = 'No Bids';
                    this.sealedBids = [];
                    this.biddingClosed = false;
                    this._lastCurrentPlayerId = null;
                    this._lastKnownBid = 0;
                }
            } catch (e) {
                console.error('[OrganizerPanel] Poll error:', e);
            }
        },

        async fetchSealedBids() {
            if (!this.currentPlayer) return;
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/sealed-bids?auction_player_id=${this.currentPlayer.id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.bids) this.sealedBids = data.bids;
            } catch (e) { console.error('Error fetching sealed bids:', e); }
        },

        async closeBidding() {
            if (!this.currentPlayer) return;
            const result = await this.sendCommand('close-bidding', { auction_player_id: this.currentPlayer.id });
            if (result && result.success) {
                this.biddingClosed = true;
            }
        },

        confirmSellToTeam(bid) {
            this.sellModalData = bid;
            this.showSellModal = true;
        },

        async executeSellToTeam() {
            if (!this.sellModalData || !this.currentPlayer) return;
            this.showSellModal = false;
            const result = await this.sendCommand('sell-to-team', {
                auction_player_id: this.currentPlayer.id,
                team_id: this.sellModalData.team_id,
                amount: this.sellModalData.amount
            });
            if (result && result.success) {
                this._fireConfetti();
                await this.pollAuctionState();
            }
            this.sellModalData = { team_id: '', amount: '' };
        },

        async switchBidPhase(phase) {
            let confirmMsg = '';
            if (phase === 'open') {
                confirmMsg = 'Switch to OPEN bid mode? Teams will see live bids and use raise-hand.';
            } else if (phase === 'closed') {
                confirmMsg = 'Switch to CLOSED bid mode? Teams will submit sealed bids privately.';
            } else if (phase === 'offline') {
                confirmMsg = 'Switch to OFFLINE mode? You will handle bids manually.';
            }
            if (!confirm(confirmMsg)) return;

            if (phase === 'offline') {
                const result = await this.sendCommand('switch-mode', { mode: 'offline' });
                if (result && result.success) {
                    this.openBidMode = 'offline';
                    this.modeManuallyOverridden = true;
                }
            } else {
                this.resetOfflinePanel();
                const modeResult = await this.sendCommand('switch-mode', { mode: 'online' });
                if (modeResult && modeResult.success) {
                    this.openBidMode = 'online';
                    this.modeManuallyOverridden = true;
                }
                const typeResult = await this.sendCommand('switch-bid-type', { bid_type: phase });
                if (typeResult && typeResult.success) {
                    this.bidType = phase;
                }
            }
        },

        async toggleBidMode() {
            const newMode = this.openBidMode === 'online' ? 'offline' : 'online';
            const confirmMsg = newMode === 'offline'
                ? 'Switch to OFFLINE mode? Teams will no longer be able to bid through the platform.'
                : 'Switch back to ONLINE mode? Teams will be able to bid through the platform again.';
            if (!confirm(confirmMsg)) return;
            const result = await this.sendCommand('switch-mode', { mode: newMode });
            if (result && result.success) {
                this.openBidMode = result.open_bid_mode;
                this.modeManuallyOverridden = result.mode_manually_overridden;
            }
        },

        // Bid for team (from toolbar buttons)
        /**
         * Raise for a team. `stepIndex` picks a configured quick-bid jump instead of
         * the standard increment — an index, never an amount, so the server stays the
         * only thing that decides how much a jump is worth.
         */
        async bidForTeam(teamId, stepIndex = null) {
            if (!this.currentPlayer || this.displayState !== 'bidding' || this.openBidMode === 'offline') return;
            if (this.currentPlayer?.current_bid_team_id == teamId) return;

            const team = this.teams.find(t => t.id == teamId);
            if (team?.excluded) {
                this.statusText = `${team.name}: ${team.exclusion_reason || 'cannot bid on this player.'}`;
                return;
            }

            // An armed jump applies to this one bid, then disarms.
            if (stepIndex === null && this.armedStepIndex !== null) {
                stepIndex = this.armedStepIndex;
            }
            this.armedStepIndex = null;

            try {
                const response = await fetch('/admin/auctions/add-bid', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        auctionId: this.auctionId,
                        playerID: this.currentPlayer.id,
                        teamId: teamId,
                        stepIndex: stepIndex,
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.currentBid = data.current_price;
                    const team = this.teams.find(t => t.id == teamId);
                    if (team) this.winningTeamName = team.name;
                    this.currentPlayer.current_bid_team_id = teamId;
                    this.resetBiddingTimer();
                } else {
                    alert(data.message || 'Bid failed');
                }
            } catch (e) {
                console.error('Bid error:', e);
            }
        },

        // Offline bidding methods
        toggleOfflineParticipant(teamId) {
            const idx = this.offlineParticipants.indexOf(teamId);
            if (idx === -1) {
                this.offlineParticipants.push(teamId);
            } else {
                this.offlineParticipants.splice(idx, 1);
                delete this.offlineTeamBids[teamId];
            }
        },

        isOfflineParticipant(teamId) {
            return this.offlineParticipants.includes(teamId);
        },

        startOfflineBidding() {
            if (this.offlineParticipants.length === 0) return;
            const basePrice = this.currentPlayer?.base_price || 0;
            this.offlineParticipants.forEach(tid => {
                if (!this.offlineTeamBids[tid]) {
                    this.offlineTeamBids[tid] = basePrice;
                }
            });
            this.offlinePhase = 'bidding';
        },

        endOfflineBidding() {
            let highestId = null;
            let highestAmt = 0;
            for (const tid of this.offlineParticipants) {
                const amt = Number(this.offlineTeamBids[tid]) || 0;
                if (amt > highestAmt) {
                    highestAmt = amt;
                    highestId = Number(tid);
                }
            }
            this.offlineHighestBidder = highestId;
            this.offlineHighestAmount = highestAmt;
            this.offlinePhase = 'results';
        },

        async confirmOfflineSale(teamId, amount) {
            if (!this.currentPlayer) return;
            const team = this.getTeamById(teamId);
            const teamName = team ? team.name : 'selected team';
            if (!confirm(`Sell ${this.currentPlayer?.player?.name} to ${teamName} for ${this.formatCurrency(amount)}?`)) return;
            const result = await this.sendCommand('sell-to-team', {
                auction_player_id: this.currentPlayer.id,
                team_id: teamId,
                amount: amount
            });
            if (result && result.success) {
                this._fireConfetti();
                this.resetOfflinePanel();
                await this.pollAuctionState();
            }
        },

        resetOfflinePanel() {
            this.offlinePhase = 'selection';
            this.offlineParticipants = [];
            this.offlineTeamBids = {};
            this.offlineHighestBidder = null;
            this.offlineHighestAmount = 0;
        },

        offlineGoBack() {
            if (this.offlinePhase === 'bidding') {
                this.offlinePhase = 'selection';
            } else if (this.offlinePhase === 'results') {
                this.offlinePhase = 'bidding';
            }
        },

        getTeamById(teamId) {
            return this.teams.find(t => t.id === teamId || t.id === Number(teamId));
        },

        // ── SHUFFLE / NEXT PLAYER ──
        async loadNextPlayer() {
            if (this.displayState === 'bidding') {
                if (!confirm('Pass current player and load next?')) return;
                await this.sendCommand('pass-player', { auction_player_id: this.currentPlayer.id });
                this.currentPlayer = null;
                this.currentBid = 0;
                this.winningTeamName = 'No Bids';
            }
            if (this.displayState === 'sold' || this.displayState === 'unsold') {
                this.displayState = 'waiting';
            }

            await this.pollAuctionState();
            if (this.availablePlayers.length === 0) {
                alert('No more players waiting.');
                return;
            }

            // Next player in drawn lot order — the server returns availablePlayers
            // sorted by pool sequence then lot number, scoped to the active pool.
            // This used to pick at random, discarding the draw entirely.
            const chosenPlayer = this.availablePlayers[0];

            // The animation is theatre; the player is already decided.
            await this._runShuffleAnimation(chosenPlayer);

            this.selectedPlayerId = chosenPlayer.id;
            await this.putPlayerOnBid();
        },

        _runShuffleAnimation(chosenPlayer) {
            return new Promise((resolve) => {
                this.shufflePhase = 'spinning';
                this.showShuffleOverlay = true;
                this.shuffleSelectedPlayer = null;
                this.shuffleDisplayName = '';

                const players = this.availablePlayers;
                if (players.length <= 1) {
                    this.shuffleSelectedPlayer = chosenPlayer;
                    this.shufflePhase = 'reveal';
                    setTimeout(() => {
                        this.showShuffleOverlay = false;
                        resolve();
                    }, 1200);
                    return;
                }

                let tick = 0;
                const totalTicks = 30;

                this._shuffleInterval = setInterval(() => {
                    tick++;
                    const currentIdx = Math.floor(Math.random() * players.length);
                    this.shuffleDisplayName = players[currentIdx].name || 'Player ' + players[currentIdx].id;

                    if (tick >= totalTicks) {
                        clearInterval(this._shuffleInterval);
                        this._shuffleInterval = null;
                        this.shuffleDisplayName = chosenPlayer.name || 'Player ' + chosenPlayer.id;
                        setTimeout(() => {
                            this.shuffleSelectedPlayer = chosenPlayer;
                            this.shufflePhase = 'reveal';
                            setTimeout(() => {
                                this.showShuffleOverlay = false;
                                resolve();
                            }, 1500);
                        }, 300);
                    }
                }, 80);
            });
        },

        // Tumbler (legacy) + select from queue
        startTumbler() {
            if (this.isTumbling || this.availablePlayers.length === 0) return;
            if (this.displayState === 'bidding') return;

            this.isTumbling = true;
            this.displayState = 'tumbling';
            this.statusText = 'Selecting player...';

            let shuffleCount = 0;
            const maxShuffles = 25;
            // Decide up front (lot order), then spin names purely for effect and land
            // on the real pick.
            const chosenPlayer = this.availablePlayers[0];

            const shuffleInterval = setInterval(() => {
                const randomIndex = Math.floor(Math.random() * this.availablePlayers.length);
                this.tumblerText = this.availablePlayers[randomIndex].name;
                shuffleCount++;

                if (shuffleCount >= maxShuffles) {
                    clearInterval(shuffleInterval);
                    this.tumblerText = chosenPlayer.name;
                    this.selectedPlayerId = chosenPlayer.id;
                    this.statusText = `Selected: ${chosenPlayer.name}`;
                    this.isTumbling = false;
                    setTimeout(() => this.putPlayerOnBid(), 1000);
                }
            }, 80);
        },

        selectAndPutOnBid(player) {
            if (this.displayState === 'bidding') {
                alert('Please finish the current player first!');
                return;
            }
            this.selectedPlayerId = player.id;
            this.tumblerText = player.name;
            this.displayState = 'tumbling';
            this.statusText = `Selected: ${player.name}`;
            setTimeout(() => this.putPlayerOnBid(), 500);
        },

        /**
         * A team cannot be bid for when there's no live player, when it already leads,
         * in offline mode, or when the squad-reserve rule prices it out of this player.
         */
        isTeamBidDisabled(team) {
            return !this.currentPlayer
                || this.displayState !== 'bidding'
                || this.openBidMode === 'offline'
                || this.currentPlayer?.current_bid_team_id == team.id
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

        /** Jump straight to a waiting player by their id (the "#" toolbar box). */
        loadPlayerByNumber() {
            const id = parseInt(this.playerNumberInput, 10);
            if (!id) return;

            const player = this.availablePlayers.find(p => p.id === id);
            if (!player) {
                alert(`No waiting player with id ${id} in this queue.`);
                return;
            }

            this.playerNumberInput = '';
            this.selectAndPutOnBid(player);
        },

        /**
         * Reverse the last bid / sale / pass / skip. This is the recovery path for
         * clicking the wrong team mid-auction.
         */
        async undoLast() {
            if (this.isUndoing) return;

            const label = this.nextUndoLabel ? `\n\nWill undo: ${this.nextUndoLabel}` : '';
            if (!confirm(`Undo the last action?${label}`)) return;

            this.isUndoing = true;
            try {
                const result = await this.sendCommand('undo');
                if (result?.success) {
                    this.statusText = result.message;
                    if (window.showToast) {
                        window.showToast('success', 'Undone', result.message);
                    }
                    // Pull fresh state rather than guessing what changed.
                    await this.pollAuctionState();
                }
            } finally {
                this.isUndoing = false;
            }
        },

        // Side panel
        showSidePanelFn(name) {
            if (this.sidePanel === name) {
                this.sidePanel = null;
                return;
            }
            this.sidePanel = name;
            if (name === 'allPlayers') this.fetchAllPlayers();
        },

        // API Calls
        async sendCommand(endpoint, body = {}) {
            try {
                const readOnly = ['sealed-bids', 'all-players', 'action-log'].includes(endpoint);
                const response = await fetch(`/admin/organizer/auction/${this.auctionId}/api/${endpoint}`, {
                    method: readOnly ? 'GET' : 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: readOnly ? undefined : JSON.stringify(body)
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Server error');
                }
                return data;
            } catch (error) {
                alert(`Error: ${error.message}`);
                return null;
            }
        },

        async startAuction() {
            const result = await this.sendCommand('start');
            if (result) {
                this.auctionStatus = 'running';
                this.statusText = 'Auction started! Select first player.';
                this.displayState = 'waiting';
            }
        },

        async endAuction() {
            if (!confirm('Are you sure you want to end the auction?')) return;
            const result = await this.sendCommand('end');
            if (result) {
                this.auctionStatus = 'completed';
            }
        },

        async restartAuction() {
            const isRunning = this.auctionStatus === 'running' || this.auctionStatus === 'paused';
            const msg = isRunning
                ? 'WARNING: This will reset ALL players and bids! The auction is still in progress. Are you sure you want to restart from scratch?'
                : 'Are you sure you want to restart this auction? All players and bids will be reset.';
            if (!confirm(msg)) return;
            const result = await this.sendCommand('restart');
            if (result && result.success) {
                this.auctionStatus = 'running';
                this.displayState = 'waiting';
                this.currentPlayer = null;
                this.stopBiddingTimer();
                this.statusText = 'Auction restarted! All players reset.';
                await this.pollAuctionState();
            }
        },

        async togglePause() {
            const result = await this.sendCommand('toggle-pause');
            if (result) {
                this.auctionStatus = this.auctionStatus === 'running' ? 'paused' : 'running';
            }
        },

        async putPlayerOnBid() {
            if (!this.selectedPlayerId) return;
            const result = await this.sendCommand('player-on-bid', { auction_player_id: this.selectedPlayerId });
            if (result) await this.pollAuctionState();
        },

        /**
         * SELL.
         *
         * In open bidding the winner is already known — the leading bidder — so the
         * hammer falls straight away; asking the organizer to pick a team from a dropdown
         * was both redundant and a chance to award the wrong one. With no bids at all the
         * player goes unsold. The team picker is only for sealed bids and offline mode,
         * where the organizer genuinely decides.
         */
        async sellPlayer() {
            if (!this.currentPlayer) return;

            const highestBid = this.currentPlayer.bids?.length
                ? this.currentPlayer.bids.reduce((a, b) => (a.amount > b.amount ? a : b), this.currentPlayer.bids[0])
                : null;

            const leadingTeamId = this.currentPlayer.current_bid_team_id || highestBid?.team_id || null;
            const isOpenLive = this.bidType === 'open' && this.openBidMode !== 'offline';

            // Nobody bid — this is a PASS, not a sale.
            if (!leadingTeamId) {
                const name = this.currentPlayer.player?.name || 'this player';
                if (!confirm(`No bids for ${name}.\n\nMark them UNSOLD and set them aside for final allotment?`)) return;
                await this.passPlayer();
                return;
            }

            // Open bidding: award the leading bidder directly.
            if (isOpenLive) {
                const team = this.getTeamById(leadingTeamId);
                const amount = highestBid?.amount || this.currentPlayer.current_price;
                if (!confirm(`Sell ${this.currentPlayer.player?.name} to ${team?.name || 'the leading team'} for ${this.formatCurrency(amount)}?`)) return;

                const result = await this.sendCommand('sell-player', { auction_player_id: this.currentPlayer.id });
                if (result?.success !== false) {
                    this._fireConfetti();
                    await this.pollAuctionState();
                }
                return;
            }

            // Sealed bids / offline: the organizer picks the winner.
            this.sellModalData = {
                team_id: leadingTeamId || '',
                amount: highestBid?.amount || this.currentPlayer.current_price || this.currentPlayer.base_price,
            };
            this.showSellModal = true;
        },

        async passPlayer() {
            if (!this.currentPlayer || this.currentPlayer?.current_bid_team_id) return;
            const result = await this.sendCommand('pass-player', { auction_player_id: this.currentPlayer.id });
            if (result) await this.pollAuctionState();
        },

        // All Players
        get filteredAllPlayers() {
            if (!this.playerSearchQuery) return this.allPlayers;
            const q = this.playerSearchQuery.toLowerCase();
            return this.allPlayers.filter(p => p.name.toLowerCase().includes(q));
        },

        async fetchAllPlayers() {
            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/all-players`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.allPlayers = data.players || [];
            } catch (e) {
                console.error('Failed to fetch all players:', e);
            }
        },

        async rebidCurrentPlayer() {
            if (!this.currentPlayer) return;
            if (!confirm('Reset this player\'s bids and start fresh? All current bids will be cleared.')) return;
            const result = await this.sendCommand('re-bid-player', { auction_player_id: this.currentPlayer.id });
            if (result && result.success) {
                this.statusText = 'Player re-bid started!';
                this.winningTeamName = 'No Bids';
                await this.pollAuctionState();
            }
        },

        async reAuctionPlayer(player) {
            if (!confirm(`Re-auction ${player.name}? This will put them back on bid with base price.`)) return;
            const result = await this.sendCommand('re-auction-player', { auction_player_id: player.id });
            if (result && result.success) {
                this.statusText = `${player.name} is back on auction!`;
                await this.pollAuctionState();
            }
        },

        // ── KEYBOARD SHORTCUTS ──
        handleKeydown(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

            const key = e.key.toUpperCase();

            if (e.key === 'Escape') {
                if (this.sidePanel) { this.sidePanel = null; return; }
                if (this.showSellModal) { this.showSellModal = false; return; }
                return;
            }

            if (key === 'F' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); this.toggleFullscreen(); return; }
            if (key === 'N') { e.preventDefault(); this.loadNextPlayer(); return; }
            if (key === 'S' && !e.ctrlKey && !e.metaKey && this.currentPlayer && this.displayState === 'bidding') {
                e.preventDefault(); this.sellPlayer(); return;
            }
            if (key === 'P' && this.currentPlayer && this.displayState === 'bidding' && !this.currentPlayer?.current_bid_team_id) {
                e.preventDefault(); this.passPlayer(); return;
            }
            // Undo the last action. Ctrl/Cmd+Z works too, for muscle memory.
            if (key === 'U' || ((e.ctrlKey || e.metaKey) && key === 'Z')) {
                e.preventDefault(); this.undoLast(); return;
            }

            // Number keys 1-9, 0 — bid for team
            if (this.currentPlayer && this.displayState === 'bidding' && this.openBidMode !== 'offline') {
                let teamIdx = -1;
                if (e.key >= '1' && e.key <= '9') teamIdx = parseInt(e.key) - 1;
                else if (e.key === '0') teamIdx = 9;

                if (teamIdx >= 0 && teamIdx < this.teams.length) {
                    const team = this.teams[teamIdx];
                    e.preventDefault();
                    // Respect the same exclusion the buttons do, so a keyboard
                    // shortcut can't bypass the reserve rule.
                    if (this.isTeamBidDisabled(team)) {
                        this.statusText = team.exclusion_reason
                            ? `${team.name}: ${team.exclusion_reason}`
                            : `${team.name} cannot bid right now.`;
                        return;
                    }
                    this.bidForTeam(team.id);
                }
            }
        },

        /**
         * Arm (or disarm) a quick-bid jump. A jump is placed *by a team*, exactly like a
         * normal raise — so the organizer arms the amount, then clicks the team logo,
         * and that one bid uses the jump instead of the standard increment.
         */
        toggleQuickStep(stepIndex) {
            this.armedStepIndex = this.armedStepIndex === stepIndex ? null : stepIndex;
            this.statusText = this.armedStepIndex === null
                ? 'Standard increment.'
                : `Next bid jumps by ${this.formatCurrency(this.quickBidSteps[stepIndex])} — click a team.`;
        },

        /** Money entry in millions, shared with every other screen. */
        toM(raw) { return window.auctionToM ? window.auctionToM(raw) : raw; },
        fromM(value) { return window.auctionFromM ? window.auctionFromM(value) : value; },

        /* ── Pool control ── */

        async activatePool(poolId) {
            const result = await this.sendCommand(`pools/${poolId}/activate`);
            if (result?.success) {
                this.statusText = result.message;
                if (window.showToast) window.showToast('success', 'Pool started', result.message);
                await this.pollAuctionState();
            }
        },

        async completeActivePool() {
            if (!this.activePool) return;

            // Closing early leaves players in the pool unsold, so say so.
            if (!this.activePool.exhausted) {
                const left = this.activePool.waiting;
                if (!confirm(`Close ${this.activePool.name} now?\n\n${left} player(s) still in it will be left unsold.`)) return;
            }

            const result = await this.sendCommand(`pools/${this.activePool.id}/complete`);
            if (result?.success) {
                this.statusText = result.message;
                if (window.showToast) window.showToast('success', 'Pool closed', result.message);
                await this.pollAuctionState();
            }
        },

        /* ── Timer ── */

        async toggleTimer() {
            const result = await this.sendCommand('toggle-timer', { timer_enabled: !this.timerEnabled });
            if (result?.success) {
                this.timerEnabled = result.timer_enabled;
                this.statusText = result.message;
            }
        },

        /**
         * Report expiry to the server, which re-checks its own clock before acting.
         *
         * Latched per player rather than on a cooldown: in manual mode the clock stays
         * expired until the organizer presses SELL, so a timed cooldown re-announced
         * "time up" every few seconds for as long as the player sat there.
         */
        async handleTimerExpiry(auctionPlayerId) {
            if (this._timerFiring || this._timerFiredForPlayer === auctionPlayerId) return;

            this._timerFiring = true;
            this._timerFiredForPlayer = auctionPlayerId;

            try {
                const result = await this.sendCommand('timer-expired', { auction_player_id: auctionPlayerId });
                if (result?.handled) {
                    this.statusText = result.message;
                    if (window.showToast) {
                        window.showToast(result.action === 'sold' ? 'success' : 'warning', 'Time up', result.message);
                    }
                    if (result.action === 'sold') this._fireConfetti();
                    await this.pollAuctionState();
                } else {
                    // Server said the clock is still running (or already resolved) — let a
                    // later tick try again rather than latching on a false alarm.
                    this._timerFiredForPlayer = null;
                }
            } finally {
                this._timerFiring = false;
            }
        },

        /* ── Sell-confirmation summary ── */

        get saleTeam() {
            return this.teams.find(t => t.id == this.sellModalData.team_id) || null;
        },

        get salePurseAfter() {
            if (!this.saleTeam) return 0;
            return Number(this.saleTeam.remaining_budget || 0) - Number(this.sellModalData.amount || 0);
        },

        /** Mirrors the server's squad-reserve check so the operator sees it before clicking. */
        get saleBreachesReserve() {
            const team = this.saleTeam;
            if (!team || team.max_bid_allowed === null || team.max_bid_allowed === undefined) return false;

            return Number(this.sellModalData.amount || 0) > Number(team.max_bid_allowed);
        },

        /** Waiting players matching the queue filter. */
        get filteredQueue() {
            const q = (this.queueSearchQuery || '').trim().toLowerCase();
            if (!q) return this.availablePlayers;

            return this.availablePlayers.filter(p => (p.name || '').toLowerCase().includes(q));
        },

        // Helpers
        _fireConfetti() {
            if (typeof confetti !== 'function') return;
            confetti({ particleCount: 80, spread: 70, origin: { x: 0.1, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
            confetti({ particleCount: 80, spread: 70, origin: { x: 0.9, y: 0.6 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#ffffff'] });
            setTimeout(() => {
                confetti({ particleCount: 120, spread: 100, origin: { x: 0.5, y: 0.3 }, colors: ['#22c55e', '#4ade80', '#fbbf24', '#f59e0b', '#ffffff'] });
            }, 300);
        },

        formatCurrency(amount) {
            // Shared K/M/B formatter with this auction's unit — no local Lakh/Crore
            // ladder, which was wrong for an auction run in points, coins or dollars.
            return window.auctionAmount
                ? window.auctionAmount(amount, this.amountUnit)
                : String(Number(amount) || 0);
        },

        getPlayerType(player) {
            if (!player?.player) return 'Player';
            const pt = player.player.player_type || player.player.playerType;
            return typeof pt === 'object' ? pt?.name : pt || 'Player';
        },

        getBattingStyle(player) {
            if (!player?.player) return null;
            const bs = player.player.batting_style || player.player.battingProfile;
            return typeof bs === 'object' ? bs?.name : bs;
        },

        getBowlingStyle(player) {
            if (!player?.player) return null;
            const bs = player.player.bowling_style || player.player.bowlingProfile;
            return typeof bs === 'object' ? bs?.name : bs;
        },

        hasAnyStats(player) {
            const p = player?.player || player;
            return p?.total_matches != null || p?.total_runs != null || p?.total_wickets != null;
        },

        getPlayerStats(player) {
            const p = player?.player || player;
            return {
                matches: p?.total_matches,
                runs: p?.total_runs,
                wickets: p?.total_wickets,
            };
        }
    }
}
</script>
@endsection
