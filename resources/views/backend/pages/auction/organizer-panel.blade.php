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
             // Fall back to this team's own allocation, never the auction-wide cap —
             // that fallback was itself blind to per-team budgets.
             'remaining_budget' => $t->remaining_budget ?? $t->allocated ?? 0,
             // Squad-reserve figures. This list whitelists keys explicitly, so any
             // new field must be added here as well as in pollState.
             'max_bid_allowed' => $t->max_bid_allowed ?? null,
             'reserve_amount' => $t->reserve_amount ?? 0,
             'slots_required' => $t->slots_required ?? null,
             'slots_remaining' => $t->slots_remaining ?? null,
             'excluded' => (bool) ($t->excluded ?? false),
             'exclusion_reason' => $t->exclusion_reason ?? null,
             // "Show both": the configured total, and the purse left after retentions.
             'allocated' => $t->allocated ?? 0,
             'auction_purse' => $t->auction_purse ?? 0,
             'retained_spent' => $t->retained_spent ?? 0,
             'auction_spent' => $t->auction_spent ?? 0,
             'retained_count' => $t->retained_count ?? 0,
             'retained_expected' => $t->retained_expected ?? 0,
         ])) }},
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
                    {{-- Only teams that can actually take a player. Selling to a full squad is
                         refused server-side (openBidCeiling is 0 for one), so offering it here
                         is offering a choice that ends in an error. --}}
                    <template x-for="team in biddableTeams" :key="team.id">
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
            {{-- No centred call banner here.
                 The closing call is for the audience; this is the operator's control screen.
                 A full-width layer dead-centre covered the sealed board — the very thing the
                 organizer has to read and act on while the call is running — so the call is
                 now a compact chip beside the header timer instead. The big treatment stays
                 on the LED wall, where it belongs. --}}


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

                {{-- Closing call, compact. Shown in every mode: a sealed round has a clock
                     too, and the operator needs to know it is running. --}}
                <div x-show="finalCall && displayState === 'bidding' && !timerPaused" x-cloak
                     class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-widest"
                     :class="finalCall && finalCall.is_final
                        ? 'bg-red-600 text-white'
                        : 'bg-amber-500 text-black'"
                     x-text="finalCall ? finalCall.label : ''"></div>

                <div x-show="timerPaused" x-cloak
                     class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-widest bg-amber-500 text-black">
                    Timer paused
                </div>

                {{-- Timer --}}
                {{-- `timerEnabled` matters here now. This badge used to be hidden for the
                     whole of offline mode, so TIMER OFF had nothing to contradict it. Once
                     offline started showing the live stage the countdown came with it and
                     kept ticking after the timer was switched off — the button flipped to
                     OFF while the big number carried on, which reads as a dead button. The
                     progress bar below the stage already checks this. --}}
                <div x-show="displayState === 'bidding' && showLiveStage && timerEnabled"
                     class="bg-gray-900/80 backdrop-blur px-4 py-1.5 rounded-full">
                    <span class="text-xl font-bold font-mono"
                          :class="biddingTimerSeconds <= 5 ? 'text-red-500 timer-critical' : 'text-white'"
                          x-text="biddingTimerSeconds + 's'"></span>
                </div>
            </div>

            {{-- ── SHUFFLE ANIMATION OVERLAY ── --}}
            {{-- ═══ SEALED ROUND CONSOLE ═══
                 Sits over the stage while a sealed round is running, because this is what
                 the organizer is actually driving. Amounts are absent from the payload
                 until the round is revealed — this screen is routinely on a projector. --}}
            <template x-if="sealed.active">
                <div class="absolute inset-0 bg-gray-950/95 backdrop-blur-sm z-30 overflow-y-auto p-6">
                    <div class="max-w-5xl mx-auto">

                        {{-- Header: phase, round, clock --}}
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-purple-400 text-xs font-bold uppercase tracking-[0.2em]">Sealed Round</div>
                                <div class="text-white text-2xl font-black" x-text="currentPlayer?.player?.name || 'Player'"></div>
                            </div>
                            <div class="text-right">
                                <div class="text-gray-500 text-[11px] uppercase tracking-wider">
                                    Round <span x-text="sealed.round_number"></span> of <span x-text="sealed.total_rounds"></span>
                                </div>
                                <div class="text-white text-sm font-bold">
                                    Floor <span x-text="formatCurrency(sealed.floor)"></span>
                                    <span class="text-gray-600">&middot; steps of <span x-text="formatCurrency(sealed.step)"></span></span>
                                </div>
                                {{-- A frozen 0 reads as a broken clock. Once time is up the round is HELD,
                                     and saying so is the difference between waiting for something and
                                     realising it is waiting for you. --}}
                                <div x-show="sealed.timer?.remaining !== null && sealed.state === 'collecting'"
                                     class="font-black tabular-nums"
                                     :class="(sealed.timer?.remaining ?? 99) <= 0
                                        ? 'text-amber-400 text-sm uppercase tracking-wider'
                                        : ((sealed.timer?.remaining ?? 99) <= 5 ? 'text-red-400 text-2xl' : 'text-cyan-400 text-2xl')"
                                     x-text="(sealed.timer?.remaining ?? 99) <= 0 ? 'Time up — held' : sealed.timer?.remaining"></div>
                            </div>
                        </div>

                        {{-- WHO is entering these amounts.
                             In offline mode a team's sealed controls are hidden on their own
                             screen and the endpoint refuses their submissions — the organizer
                             types every amount here. That is deliberate, but nothing said it, so
                             a full board of AWAITING looked like teams ignoring the round or the
                             page being broken. Both states are named rather than left to be
                             inferred from an absence. --}}
                        <div class="mb-4 px-4 py-2.5 rounded-xl flex items-start gap-2.5 bg-cyan-500/10 border border-cyan-500/25">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-cyan-400"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-[12px] leading-relaxed text-cyan-200/90">
                                <span>
                                    <span class="font-bold">Teams enter their own sealed amounts</span>
                                    — on their own screens, in offline mode too, because a sealed bid is a
                                    private number rather than a raise called across the room.
                                    <span x-show="sealed.requires_acceptance">
                                        Each team must accept the round first: one still reading
                                        <span class="font-semibold">Awaiting</span> has not accepted yet.
                                    </span>
                                    You can also enter on a team's behalf below.
                                </span>
                            </p>
                        </div>

                        {{-- Tie banner --}}
                        <template x-if="sealed.tie_amount && ['tie','awaiting_lot'].includes(sealed.state)">
                            <div class="mb-4 px-4 py-3 rounded-xl bg-amber-500/15 border border-amber-500/40 text-amber-300 text-center font-bold">
                                TIE — <span x-text="(sealed.tied_team_ids || []).length"></span> teams at
                                <span x-text="formatCurrency(sealed.tie_amount)"></span>.
                                <span x-show="sealed.state === 'tie'">Round <span x-text="sealed.round_number + 1"></span> of <span x-text="sealed.total_rounds"></span> available.</span>
                                <span x-show="sealed.state === 'awaiting_lot'">The re-bid rounds are used up.</span>
                            </div>
                        </template>

                        {{-- Nobody entered --}}
                        <template x-if="sealed.state === 'no_entries'">
                            <div class="mb-4 px-4 py-4 rounded-xl bg-gray-900 border border-gray-700 text-center">
                                <p class="text-gray-300 text-sm mb-3">No team entered the sealed round.</p>
                                {{-- No "send to unsold" here.
                                     A sealed round is only reached because the OPEN bidding had
                                     already climbed past the threshold — so there is a leading team
                                     at the floor price, and the player is not unsold by any
                                     reasonable reading. Offering it invited an organizer to throw
                                     away a live bid with one click. A player who genuinely should
                                     not sell is passed with PASS on the toolbar, which is the
                                     control that means that. --}}
                                <div class="flex gap-2 justify-center">
                                    <button @click="sealedNoEntries('award_leader')" x-show="sealed.leader"
                                            class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold">
                                        Award <span x-text="sealed.leader?.team_name"></span> at <span x-text="formatCurrency(sealed.floor)"></span>
                                    </button>
                                </div>
                                <p x-show="!sealed.leader" class="text-gray-500 text-xs mt-2">
                                    No leading team to award — use <span class="font-semibold text-gray-400">PASS</span>
                                    on the toolbar if this player should not sell.
                                </p>
                                {{-- Awarding was the only way out of here, so a round that ran out with
                                     the wrong teams in it had to be resolved rather than corrected. --}}
                                <p class="text-gray-500 text-xs mt-2">
                                    Or press <span class="font-semibold text-gray-400">Back</span> below to choose
                                    the teams again.
                                </p>
                            </div>
                        </template>

                        {{-- The board --}}
                        <div class="rounded-xl border border-gray-800 overflow-hidden mb-4">
                            <div class="grid grid-cols-12 bg-gray-900 px-4 py-2 text-[10px] uppercase tracking-wider text-gray-500 font-bold">
                                <div class="col-span-4">Team</div>
                                <div class="col-span-3">Ceilings</div>
                                <div class="col-span-2 text-center">Status</div>
                                <div class="col-span-3 text-right">Controls</div>
                            </div>

                            <template x-for="entry in (sealed.entries || [])" :key="entry.entry_id">
                                <div class="grid grid-cols-12 items-center px-4 py-2.5 border-t border-gray-800/60 text-sm"
                                     :class="[
                                         entry.withdrawn ? 'opacity-50' : '',
                                         entry.is_tied ? 'ring-4 ring-amber-300/50 bg-amber-500/5' : 'bg-gray-950/40'
                                     ]">
                                    <div class="col-span-4 flex items-center gap-2 min-w-0">
                                        <template x-if="entry.team_logo">
                                            <img :src="entry.team_logo" class="w-7 h-7 rounded-full object-cover shrink-0" alt="">
                                        </template>
                                        <template x-if="!entry.team_logo">
                                            <span class="w-7 h-7 rounded-full bg-gray-700 flex items-center justify-center text-[10px] font-black shrink-0"
                                                  x-text="(entry.team_name || '?').substring(0,2).toUpperCase()"></span>
                                        </template>
                                        <span class="text-white font-semibold truncate" x-text="entry.team_name"></span>
                                        {{-- The revealed amount, kept clear of the next column.
                                             It had ml-auto alone, so it ran to the very edge of
                                             the team cell and printed over "cap 50M / reserve
                                             43M" beside it — two figures on top of each other on
                                             the board an organizer awards from. shrink-0 stops it
                                             being compressed by a long team name, and the padding
                                             keeps a gap the next column cannot be dragged into. --}}
                                        <template x-if="sealed.revealed && entry.amount">
                                            <span class="ml-auto shrink-0 whitespace-nowrap pl-3 pr-4 text-emerald-400 font-black tabular-nums"
                                                  x-text="formatCurrency(entry.amount)"></span>
                                        </template>
                                    </div>

                                    <div class="col-span-3 text-[10px] leading-tight pl-2">
                                        <div :class="entry.per_player_cap <= entry.reserve_max ? 'text-amber-400 font-bold' : 'text-gray-500'">
                                            cap <span x-text="formatCurrency(entry.per_player_cap)"></span>
                                        </div>
                                        <div :class="entry.reserve_max < entry.per_player_cap ? 'text-amber-400 font-bold' : 'text-gray-500'">
                                            reserve <span x-text="formatCurrency(entry.reserve_max)"></span>
                                        </div>
                                    </div>

                                    <div class="col-span-2 text-center">
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide"
                                              :class="{
                                                  'bg-amber-500/20 text-amber-300': entry.withdrawn,
                                                  'bg-emerald-500/20 text-emerald-300': !entry.withdrawn && entry.submitted,
                                                  'bg-blue-500/20 text-blue-300': !entry.withdrawn && !entry.submitted && entry.state === 'accepted',
                                                  'bg-gray-700/50 text-gray-400': !entry.withdrawn && !entry.submitted && entry.state !== 'accepted'
                                              }"
                                              x-text="entry.withdrawn ? 'Withdrawn'
                                                    : (entry.submitted ? 'Submitted' : (entry.state === 'accepted' ? 'Accepted'
                                                    : (entry.required ? 'Must re-bid' : 'Awaiting')))"></span>
                                        <div x-show="entry.adjusted_count > 0" class="text-[9px] text-gray-600 mt-0.5">
                                            adjusted &times;<span x-text="entry.adjusted_count"></span>
                                        </div>
                                    </div>

                                    <div class="col-span-3 flex items-center justify-end gap-1">
                                        {{-- Amount fields only when the organizer is the one entering.
                                             With Open Entry the teams type their own, and this row is
                                             something the organizer READS — a stepper and a text box
                                             over it are clutter, and one stray keystroke writes a bid
                                             for somebody else's team. What replaces them is below:
                                             the amount if it is in, "pending" if it is not. --}}
                                        <template x-if="sealed.state === 'collecting' && !entry.withdrawn && !sealed.entry_opened">
                                            <div class="flex items-center gap-1">
                                                <button @click="sealedAdjust(entry, 'down')"
                                                        class="w-6 h-6 rounded bg-red-500/15 border border-red-500/25 text-red-400 text-xs font-bold">&minus;</button>
                                                {{-- step="any" here meant the box accepted anything at all: the
                                                     round's configured step was printed in the header and then
                                                     ignored by the one control that types an amount. Both bounds
                                                     come from the round's own snapshot, so a round keeps the rules
                                                     it opened under even if the auction is reconfigured. --}}
                                                <input type="number"
                                                       {{-- toM() gives '' for a missing figure, and an EMPTY step
                                                            attribute is not "no constraint" — the browser falls back
                                                            to step=1, which would refuse 8.1M outright. Fall back to
                                                            'any', and drop min entirely rather than binding ''. --}}
                                                       :step="sealed.step ? toM(sealed.step) : 'any'"
                                                       :min="sealed.floor ? toM(sealed.floor) : null"
                                                       {{-- The team's own ceiling, so the box refuses what the server
                                                            would refuse instead of accepting it and failing later. --}}
                                                       :max="entry.binding_ceiling ? toM(entry.binding_ceiling) : null"
                                                       inputmode="decimal"
                                                       x-model="sealedAdjustAmount[entry.entry_id]"
                                                       {{-- Enter was the ONLY way to commit a typed amount, so typing
                                                            one and clicking away lost it silently — the +/- buttons
                                                            looked like the only thing that worked. Blur commits it
                                                            too, and Set is there for anyone who wants a button. --}}
                                                       @keydown.enter.prevent="sealedAdjustCustom(entry)"
                                                       @change="sealedAdjustCustom(entry)"
                                                       class="w-16 px-1 py-0.5 bg-gray-800 border border-gray-700 rounded text-white text-[11px] text-center"
                                                       :placeholder="sealed.floor ? toM(sealed.floor) : 'M'">
                                                <button @click="sealedAdjust(entry, 'up')"
                                                        class="w-6 h-6 rounded bg-green-500/15 border border-green-500/25 text-green-400 text-xs font-bold">+</button>
                                                <button @click="sealedAdjustCustom(entry)"
                                                        class="px-1.5 h-6 rounded bg-cyan-600/80 hover:bg-cyan-500 text-white text-[10px] font-bold"
                                                        title="Record the typed amount">Set</button>
                                            </div>
                                        </template>
                                        {{-- Whether the bid is in, and — if the organizer asks — what it is.
                                             Masked by default because this panel is routinely on a
                                             projector, which is a question of what to PAINT rather than
                                             of what the person running the auction may know. Without any
                                             way to look, an organizer could not check a bid a team had
                                             queried, or confirm that an amount they entered on a team's
                                             behalf had actually landed. --}}
                                        <template x-if="sealed.state === 'collecting' && !entry.withdrawn && sealed.entry_opened">
                                            <span class="text-[11px] font-semibold tabular-nums"
                                                  :class="entry.submitted ? 'text-emerald-400' : 'text-gray-500'"
                                                  x-text="! entry.submitted
                                                        ? 'Pending'
                                                        : (sealedAmountsVisible ? formatCurrency(entry.amount) : 'Bid in')"></span>
                                        </template>

                                        <button x-show="!entry.withdrawn && !sealed.revealed"
                                                @click="sealedEntryCommand(entry.entry_id, 'withdraw')"
                                                class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-gray-400 text-[10px] hover:text-red-400">Withdraw</button>
                                        <button x-show="entry.withdrawn && !sealed.revealed"
                                                @click="sealedEntryCommand(entry.entry_id, 'reinstate')"
                                                class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-gray-400 text-[10px] hover:text-emerald-400">Restore</button>
                                        {{-- Says why it is refused BEFORE the click.
                                             This was always clickable and answered with a toast —
                                             "an unexplained override cannot be defended later" —
                                             which names the rule but not the field, and the field is
                                             in a different panel below the rows. Mid-tie, with the
                                             hall watching, that reads as Pick being broken. The
                                             offline panel had already been given the disabled state;
                                             this one had not. --}}
                                        <button x-show="sealed.state === 'awaiting_lot' && (sealed.tied_team_ids || []).includes(entry.team_id)"
                                                @click="sealedResolveManual(entry.team_id)"
                                                :disabled="!sealedManualReason.trim()"
                                                :title="sealedManualReason.trim()
                                                    ? 'Record this team as the winner of the physical draw'
                                                    : 'Give the reason for the physical draw first — the box is below'"
                                                class="px-2 py-0.5 rounded bg-amber-600/30 border border-amber-500/40 text-amber-200 text-[10px] disabled:opacity-40 disabled:cursor-not-allowed">Pick</button>
                                    </div>
                                </div>
                            </template>

                            {{-- Which teams take part, chosen BEFORE Open Entry rather than after
                                 every eligible team is already invited. An expensive player does
                                 not always need every team weighing in, and there was no way to
                                 leave one out before the board was already built around it. --}}
                            <template x-if="sealed.state === 'pending'">
                                <div class="px-4 py-4">
                                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-bold mb-2">
                                        Select which teams take part in this round
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                        <template x-for="team in (teams || [])" :key="team.id">
                                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                                   :class="isSealedTeamSelected(team.id)
                                                       ? 'border-purple-500 bg-purple-500/10'
                                                       : 'border-gray-800 bg-gray-900/40'">
                                                <input type="checkbox" class="accent-purple-500"
                                                       :checked="isSealedTeamSelected(team.id)"
                                                       @change="toggleSealedTeam(team.id)">
                                                <template x-if="team.logo_url">
                                                    <img :src="team.logo_url" class="w-5 h-5 rounded-full object-cover shrink-0" alt="">
                                                </template>
                                                <span class="text-white text-xs font-semibold truncate" x-text="team.name"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <p x-show="!(teams || []).length" class="text-gray-500 text-sm text-center py-2">
                                        No teams available.
                                    </p>
                                </div>
                            </template>

                            <div x-show="sealed.state !== 'pending' && !(sealed.entries || []).length"
                                 class="px-4 py-6 text-center text-gray-500 text-sm">
                                No teams invited yet.
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <div class="text-[11px] text-gray-500">
                                <span x-text="sealed.counts?.accepted ?? 0"></span> accepted &middot;
                                <span x-text="sealed.counts?.submitted ?? 0"></span> submitted &middot;
                                <span x-text="sealed.counts?.withdrawn ?? 0"></span> withdrawn
                            </div>

                            {{-- One switch for the whole board, not one per row: an organizer who
                                 wants to see the amounts wants to see them, and a projector in the
                                 room is the reason they are hidden to begin with. --}}
                            <button type="button" x-show="sealed.state === 'collecting'"
                                    @click="sealedAmountsVisible = !sealedAmountsVisible"
                                    class="mr-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold transition"
                                    :class="sealedAmountsVisible
                                        ? 'bg-amber-500/15 border border-amber-500/30 text-amber-300'
                                        : 'bg-gray-800 border border-gray-700 text-gray-400 hover:text-gray-200'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <span x-text="sealedAmountsVisible ? 'Hide amounts' : 'Show amounts'"></span>
                            </button>

                            <div class="flex gap-2">
                                {{-- Back to team selection.
                                     Changing who is in a round is an ordinary correction — the wrong
                                     team ticked, or one that should not be here — and the only ways
                                     back were UNDO (which reverts by action, not by step) or
                                     withdrawing invitations one at a time. Neither reads as "go
                                     back". Refused server-side once a team has actually responded,
                                     because stepping back out then would discard their act. --}}
                                <button x-show="['entry_open','collecting','no_entries'].includes(sealed.state)" @click="sealedReopenSelection()"
                                        class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm font-bold inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    Back
                                </button>
                                <button x-show="sealed.state === 'pending'" @click="sealedOpenEntry()"
                                        class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-sm font-bold">
                                    Open Entry (<span x-text="sealedSelectedCount"></span>)
                                </button>
                                {{-- Same action, named for the round the organizer just set up: once
                                     Open Entry has been pressed the teams are entering their own
                                     amounts, and calling it "Start Closed Bid" described the
                                     mechanism rather than what was about to happen. --}}
                                <button x-show="['pending','entry_open'].includes(sealed.state)" @click="sealedStart()"
                                        class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-bold"
                                        x-text="sealed.entry_opened ? 'Start Open Bid' : 'Start Closed Bid'"></button>
                                {{-- Time up holds the round rather than ending it, so there has to be a
                                     way to give the room longer — otherwise "held" is simply stuck. --}}
                                <button x-show="sealed.state === 'collecting' && (sealed.timer?.expired || (sealed.timer?.remaining ?? 99) <= 0)"
                                        @click="sealedExtend()"
                                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold">
                                    Extend Clock
                                </button>
                                <button x-show="sealed.state === 'collecting'" @click="sealedLock()"
                                        class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-bold">Lock &amp; Reveal</button>
                                <button x-show="sealed.state === 'revealed' && sealed.winner_team_id" @click="sealedAward()"
                                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold">
                                    Award <span x-text="formatCurrency(sealed.winning_amount)"></span>
                                </button>
                                <button x-show="sealed.state === 'tie'" @click="sealedStartRebid()"
                                        class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-bold">Start Re-bid</button>
                            </div>
                        </div>

                        {{-- ── HOW THE TIE GETS SETTLED ──
                             Both ways were already here, but only as a DRAW LOT button beside
                             a per-team Pick and an unlabelled reason box — so which one you
                             were choosing, and what each meant, had to be inferred. They are
                             two deliberate answers to the same question and now say so. --}}
                        <div x-show="sealed.state === 'awaiting_lot'" class="mt-4 grid gap-3 md:grid-cols-2">

                            {{-- 1. On screen, for the hall to watch. --}}
                            <div class="px-4 py-4 rounded-xl bg-gray-900/60 border border-amber-500/25">
                                <div class="text-amber-300 text-[10px] uppercase tracking-[0.15em] font-bold mb-1">
                                    Live draw &middot; on screen
                                </div>
                                <p class="text-gray-400 text-[11px] leading-relaxed mb-3">
                                    Cycles the tied teams for about
                                    <span x-text="Math.round(LOT_SPIN_MS / 1000)"></span> seconds, then lands on the
                                    winner. Drawn on the server before the spin starts, from a random seed that is
                                    recorded with the result — so it cannot be predicted, and it can be checked
                                    afterwards.
                                </p>
                                <button @click="sealedDrawLot()"
                                        class="w-full px-4 py-2.5 rounded-lg bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-white text-sm font-black">
                                    DRAW LOT
                                </button>
                            </div>

                            {{-- 2. Drawn at the desk; the organizer records the outcome. --}}
                            <div class="px-4 py-4 rounded-xl bg-gray-900/60 border border-gray-700/50">
                                <div class="text-gray-300 text-[10px] uppercase tracking-[0.15em] font-bold mb-1">
                                    Physical draw &middot; at the desk
                                </div>
                                <p class="text-gray-400 text-[11px] leading-relaxed mb-3">
                                    Slips, a coin, a toss — done in the room. Give the reason, then press
                                    <span class="text-amber-200 font-semibold">Pick</span> on the winning team's row
                                    above. Recorded as an organizer decision rather than a draw.
                                </p>
                                <input type="text" x-model="sealedManualReason" x-ref="sealedManualReason"
                                       class="w-full px-3 py-2 bg-gray-950 border border-gray-700 rounded-lg text-white text-xs placeholder-gray-600 transition-colors"
                                       :class="sealedReasonFlash ? 'border-amber-400 ring-2 ring-amber-500/40' : ''"
                                       placeholder="e.g. slips drawn at the desk by the tournament referee">

                                {{-- The three answers this box almost always gets, as one tap each.
                                     The reason is required so the decision can be defended later,
                                     but typing a sentence on a phone while a tied board is on the
                                     wall is what made the requirement feel like an obstruction
                                     rather than a record. Still free text — these are a shortcut,
                                     not the only permitted answers. --}}
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <template x-for="reason in LOT_REASONS" :key="reason">
                                        <button type="button" @click="sealedManualReason = reason"
                                                class="px-2 py-1 rounded-md border text-[10px] font-semibold transition-colors"
                                                :class="sealedManualReason === reason
                                                    ? 'bg-amber-500/20 border-amber-500/50 text-amber-200'
                                                    : 'bg-gray-950 border-gray-700 text-gray-400 hover:text-amber-200 hover:border-amber-500/40'"
                                                x-text="reason"></button>
                                    </template>
                                </div>

                                <p x-show="!sealedManualReason.trim()" class="text-gray-600 text-[10px] mt-1.5">
                                    Pick stays refused until this is filled in.
                                </p>
                            </div>
                        </div>

                        {{-- Summary: how the player was won, not just who won. --}}
                        <template x-if="sealed.state === 'awarded'">
                            <div class="mt-4 px-4 py-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-center">
                                <div class="text-emerald-400 text-[10px] uppercase tracking-[0.2em] font-bold mb-1"
                                     x-text="sealed.resolution === 'lot' ? 'Sealed — Lot Draw'
                                           : (sealed.resolution === 'manual' ? 'Sealed — Organizer Decision'
                                           : (sealed.resolution === 'leader_at_threshold' ? 'Open-bid leader at threshold'
                                           : 'Sealed — Round ' + sealed.round_number))"></div>
                                <div class="text-white text-xl font-black" x-text="formatCurrency(sealed.winning_amount)"></div>
                                <div class="text-gray-400 text-xs mt-1">
                                    Won by <span class="text-white font-semibold"
                                        x-text="(sealed.entries || []).find(e => e.team_id === sealed.winner_team_id)?.team_name || 'the winning team'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

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

            {{-- ── POOL FINISHED ──
                 Takes the place of Ready to Auction once the running pool has nothing left
                 and nobody on the block. The generic empty state invited the organizer to
                 press START or N for a player that cannot come — there is none left in this
                 pool — so the two ways on are named instead: run the pool again, or close it
                 and move to the next. --}}
            <div x-show="displayState === 'waiting' && activePool && activePool.finished" x-transition class="text-center">
                <div class="w-32 h-32 mx-auto mb-6 rounded-full border-2 border-emerald-600/40 flex items-center justify-center">
                    <svg class="w-16 h-16 text-emerald-500/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-white mb-1">
                    <span x-text="activePool?.name"></span> complete
                </h2>
                <p class="text-gray-500 mb-6">
                    <span x-text="activePool?.total"></span> players auctioned &middot;
                    <span x-text="activePool?.sold"></span> sold
                </p>

                <div class="flex items-center justify-center gap-3">
                    <button @click="restartActivePool()"
                            class="px-5 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm font-bold">
                        Restart <span x-text="activePool?.name"></span>
                    </button>
                    <button @click="completeActivePool()"
                            class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold">
                        Close <span x-text="activePool?.name"></span>
                    </button>
                    <template x-if="nextPool">
                        <button @click="activatePool(nextPool.id)"
                                class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold">
                            Start <span x-text="nextPool.name"></span>
                        </button>
                    </template>
                </div>

                <p class="text-gray-600 text-xs mt-4">
                    Restarting puts this pool's players back on the block and undoes its sales.
                </p>
            </div>

            {{-- ── EMPTY STATE ──
                 Three situations, not one. This was a single fixed message — "Ready to Auction,
                 hit START" — shown for every `waiting` state, so an auction thirty players in
                 kept inviting the operator to start it, and a PAUSED auction offered a NEXT the
                 server would refuse. Same split the pool-finished state above already makes. --}}
            <div x-show="displayState === 'waiting' && !(activePool && activePool.finished)" x-transition class="text-center">
                <div class="w-32 h-32 mx-auto mb-6 rounded-full border-2 border-dashed border-gray-700 flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>

                {{-- Paused: NEXT is refused server-side, so do not offer it. --}}
                <template x-if="auctionStatus === 'paused'">
                    <div>
                        <h2 class="text-3xl font-bold text-amber-500/80 mb-2">Paused</h2>
                        <p class="text-gray-600">
                            Press <span class="px-2 py-1 bg-amber-600/80 rounded text-white text-sm font-bold">Resume</span>
                            below to carry on.
                        </p>
                    </div>
                </template>

                {{-- Under way, nobody on the block: the action is the next player, not a start. --}}
                <template x-if="auctionStatus === 'running'">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-500 mb-2">Between players</h2>
                        <p class="text-gray-600">
                            Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd>
                            or hit <span class="px-2 py-1 bg-blue-600/80 rounded text-white text-sm font-bold" x-text="nextActionLabel"></span>
                            for the next player, or enter a player ID
                        </p>
                    </div>
                </template>

                {{-- Not started yet. The one case this screen was always right about. --}}
                <template x-if="auctionStatus !== 'running' && auctionStatus !== 'paused'">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-500 mb-2">Ready to Auction</h2>
                        <p class="text-gray-600">
                            Hit <span class="px-2 py-1 bg-blue-600/80 rounded text-white text-sm font-bold" x-text="nextActionLabel"></span>
                            below, press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd>, or enter a player ID
                        </p>
                    </div>
                </template>
            </div>

            {{-- ── RESTARTING ──
                 Announced for the same server-measured window as the public wall, so the
                 organizer and the hall see the same thing at the same moment. Before this
                 existed the panel fell through to the UNSOLD stamp, which read as though
                 the player who was up had been passed on. --}}
            <div x-show="displayState === 'restarting'" x-transition class="text-center">
                <div class="w-32 h-32 mx-auto mb-6 rounded-full border-4 border-amber-500/40 border-t-amber-400 flex items-center justify-center animate-spin"></div>
                <h2 class="text-4xl font-black text-amber-400 mb-2 tracking-wide">RESTARTING AUCTION</h2>
                <p class="text-gray-400">All players and bids have been reset.</p>
                <p class="text-gray-500 text-sm mt-3"
                   x-show="restartSeconds > 0"
                   x-text="`Next player in ${restartSeconds}s…`"></p>
                <p class="text-gray-500 text-sm mt-3" x-show="restartSeconds <= 0">
                    Press <kbd class="px-2 py-1 bg-gray-800 rounded text-gray-400 text-sm font-mono">N</kbd> for the first player
                </p>
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
            <div x-show="displayState === 'bidding' && showLiveStage" x-transition class="flex items-stretch px-12 w-full h-full">
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

                            {{-- Travel plan, when the player has one. Read from the model's
                                 travel_plan_label accessor rather than assembled here, so the
                                 panel, the LED wall and the downloaded card cannot answer the
                                 same question three different ways. --}}
                            <template x-if="currentPlayer?.player?.travel_plan_label">
                                <span class="flex items-center gap-1.5 text-sky-300">
                                    <span class="text-gray-600">&bull;</span>
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                    </svg>
                                    <span x-text="currentPlayer.player.travel_plan_label"></span>
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

                        {{-- Base price, and how much of the room is actually in this player.
                             Two matching cards rather than a card and a line of grey text
                             underneath it: this is the stage the hall is watching, and the
                             second figure was small, unlabelled and easy to miss. --}}
                        <div class="mt-4 flex items-stretch gap-3">
                            <div class="border rounded-xl px-6 py-3 border-blue-500/30 bg-blue-500/10">
                                <div class="text-xs uppercase tracking-widest mb-0.5 text-blue-400">Base Price</div>
                                <div class="text-3xl font-black text-blue-400" x-text="formatCurrency(currentPlayer?.base_price)"></div>
                            </div>

                            {{-- TEAMS, not bids. fetchSealedBids() collapses the append-only
                                 bid log to one standing row per team, so this length has
                                 always been a count of teams — it read "7 bid(s) received"
                                 for seven teams, and would have read "1 bid(s) received"
                                 for one team that had raised twenty times. --}}
                            <div x-show="sealedBids.length > 0"
                                 class="border rounded-xl px-6 py-3 border-emerald-500/30 bg-emerald-500/10">
                                <div class="text-xs uppercase tracking-widest mb-0.5 text-emerald-400">In The Bidding</div>
                                <div class="text-3xl font-black text-emerald-400">
                                    <span x-text="sealedBids.length"></span>
                                    <span class="text-base font-bold opacity-80"
                                          x-text="sealedBids.length === 1 ? 'team' : 'teams'"></span>
                                </div>
                            </div>
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
                            {{-- The figure changes and nothing else happens.
                                 It counted up to each new amount first, then dipped and faded —
                                 both read as a flicker on the screen driving the room, and both
                                 put the true number a moment behind the click. tabular-nums keeps
                                 every digit one width, so "4.5M" becoming "11.1M" cannot nudge
                                 the panel sideways. --}}
                            <div class="text-5xl font-black text-emerald-400 tabular-nums"
                                 x-text="formatCurrency(displayBid)"></div>
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
            <div x-show="displayState === 'bidding' && !showLiveStage" x-transition class="w-full px-6 py-4 overflow-y-auto h-full">
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
                        {{-- No OFFLINE badge here.
                             The header carries the phase badge already, unconditionally and
                             for every phase. A second hard-coded one in this bar sat directly
                             under it and read as two overlapping OFFLINE pills — and unlike
                             the header's, it could never say anything else, so it would still
                             claim OFFLINE if the mode changed underneath it. --}}
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
                        <template x-for="team in selectableTeams" :key="team.id">
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
                                    <p class="text-xs" :class="team.remaining_budget < team.auction_purse * 0.2 ? 'text-red-400' : 'text-gray-400'" x-text="formatCurrency(team.remaining_budget) + ' left'"></p>
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
                                            {{-- The value is converted with toM()/fromM(), so this
                                                 field is in MILLIONS. It was labelled "L" for Lakhs:
                                                 an organizer reading that types 45 meaning 45 lakh
                                                 (4.5M) and sells the player for 45M instead — a 10x
                                                 error, entered on their behalf, mid-auction. The
                                                 sell modal above had exactly this bug and was fixed;
                                                 this copy was missed.

                                                 step="any" because the server validates the amount
                                                 against the increment ladder; a hardcoded 0.5 made
                                                 the browser reject ordinary values like 4.7. --}}
                                            <input type="number"
                                                   :value="toM(offlineTeamBids[tid])"
                                                   @input="offlineTeamBids[tid] = fromM($event.target.value)"
                                                   class="w-full bg-transparent px-3 py-2 text-white text-sm text-right outline-none"
                                                   placeholder="0" min="0" step="any">
                                            <span class="pr-3 text-xs text-gray-400 whitespace-nowrap">M</span>
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
            <div x-show="displayState === 'bidding' && showLiveStage && timerEnabled" class="h-1 bg-gray-800">
                <div class="h-full transition-all duration-1000 ease-linear"
                     :class="biddingTimerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500'"
                     :style="`width: ${timerWidth}%`"></div>
            </div>

            {{-- ══ POOL CONTROL STRIP ══
                 The auction is locked to one pool at a time, so which pool is running
                 and how far through it we are belongs on screen at all times. --}}
            <div class="bg-gray-950 border-t border-gray-800 px-4 py-2 flex items-center gap-3 overflow-x-auto">
                <span class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold flex-shrink-0">Pool</span>

                {{-- No pool running — and only once that has actually been checked.
                     Gated on stateLoaded as well as the seeded value: an auction whose pools
                     genuinely are all idle still gets this message, but a reload no longer
                     announces it before any server state has arrived. --}}
                <template x-if="!activePool && stateLoaded">
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

                        {{-- Any pool, at any time — including the ones already finished and the
                             unsold pile. "No enabled pool has players left" was the end of the
                             road: the remaining pools were closed or disabled, activatePool
                             refuses both, and the only way on was the pools admin screen
                             mid-auction.

                             One button per pool, NAMED. "Reopen a pool (2)" made an organizer
                             open a dialog to find out which two — a count is not a choice, and
                             the buttons beside it already say "Start Pool A (6)". --}}
                        <template x-for="p in reopenablePools" :key="'reopen-' + p.id">
                            <button @click="reopenPool(p)"
                                    class="px-2.5 py-1 border text-xs font-semibold rounded transition whitespace-nowrap"
                                    :class="p.is_unsold_pool
                                        ? 'bg-amber-900/30 hover:bg-amber-900/50 border-amber-700/60 text-amber-300'
                                        : 'bg-gray-800 hover:bg-gray-700 border-gray-600 text-gray-300'">
                                <span x-text="(p.is_unsold_pool ? 'Run ' : 'Reopen ') + p.name"></span>
                                <span class="opacity-70"
                                      x-text="'(' + ((p.waiting || 0) + (p.unsold_from || 0)) + ')'"></span>
                            </button>
                        </template>
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

                        {{-- Finished: offer the next pool without auto-advancing.
                             Keyed on `finished` rather than `exhausted` — the queue empties
                             as the LAST player goes up, so exhausted alone announced "Pool
                             complete" over a player still being auctioned. --}}
                        <template x-if="activePool.finished">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-emerald-400 font-semibold">Pool complete</span>
                                <button @click="restartActivePool()"
                                        class="px-2.5 py-1 bg-gray-700 hover:bg-gray-600 text-white text-xs font-semibold rounded transition whitespace-nowrap"
                                        title="Put this pool's players back on the block and run it again">
                                    Restart pool
                                </button>
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
                {{-- A table of teams, not a row of bubbles spread to the edges.

                     `grid-rows-2 grid-flow-col auto-cols-fr` meant ALWAYS two rows with columns
                     stretched across the whole band — six teams became three columns each a
                     third of the panel wide, with the chips floating in the middle of enormous
                     gaps. Rows now follow the count and the block is centred at its natural
                     width, so four teams read as one tidy row and twenty as two rows of ten.

                     Each team is a CELL: square logo on top, purse beneath it in flow. The purse
                     used to be an absolutely-positioned pill far wider than the 52px chip, which
                     is what stopped the columns from ever sitting close together. --}}
                <div class="flex justify-center overflow-x-auto">
                    <div class="inline-grid gap-x-2 gap-y-3"
                         :style="`grid-template-columns: repeat(${teamGridColumns}, minmax(0, 1fr))`">
                        <template x-for="(team, idx) in biddableTeams" :key="team.id">
                            <div class="flex flex-col items-center gap-1.5 px-0.5">
                                <button @click="bidForTeam(team.id)"
                                        :disabled="isTeamBidDisabled(team)"
                                        {{-- aspect-square + rounded-xl: a true square, never
                                             squashed by its grid column. --}}
                                        class="relative group aspect-square rounded-xl border-2 overflow-visible bg-gray-800 flex items-center justify-center transition-all duration-200 flex-shrink-0"
                                        {{-- 72px, not 50: these are logos being read from across a
                                             hall and tapped on a touchscreen. Two rows of ten at
                                             this size is ~790px, so twenty teams still fit. --}}
                                        :class="[
                                            winningTeamName === team.name
                                                ? 'w-[82px] border-emerald-400 team-pulse scale-105'
                                                : 'w-[72px] border-gray-600 hover:border-gray-300 hover:scale-105',
                                            team.excluded ? 'border-amber-600/70' : '',
                                            isTeamBidDisabled(team) ? 'opacity-45 cursor-not-allowed hover:scale-100' : ''
                                        ]"
                                        :title="teamTooltip(team)">

                                    {{-- Logo fills the square. --}}
                                    <span class="absolute inset-0 rounded-[10px] overflow-hidden flex items-center justify-center"
                                          :class="team.excluded ? 'grayscale' : ''">
                                        <template x-if="team.logo_url">
                                            <img :src="team.logo_url" :alt="team.name" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!team.logo_url">
                                            <span class="text-base font-bold text-gray-300 leading-none"
                                                  x-text="(team.short_name || team.name).substring(0, 3).toUpperCase()"></span>
                                        </template>

                                        {{-- Priced out of this player under the squad-reserve rule. --}}
                                        <template x-if="team.excluded">
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/65 text-amber-400 text-sm">&#128274;</span>
                                        </template>
                                    </span>

                                    {{-- Squad count, riding the top edge. --}}
                                    <span x-show="team.slots_required"
                                          class="absolute -top-2 -right-1.5 z-10 px-1.5 rounded-full text-[10px] font-mono font-bold leading-[16px] border border-gray-900"
                                          :class="(team.slots_remaining || 0) > 0 ? 'bg-gray-700 text-gray-200' : 'bg-emerald-600 text-white'"
                                          x-text="(team.players_bought || 0) + '/' + team.slots_required"></span>

                                    {{-- Keyboard shortcut hint (1-9, 0). --}}
                                    <span class="absolute -top-1.5 -left-1 z-10 w-[15px] h-[15px] bg-gray-700 border border-gray-900 rounded-full text-[9px] font-mono flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                          x-text="idx < 9 ? String(idx + 1) : (idx === 9 ? \'0\' : \'\')"></span>
                                </button>

                                {{-- Purse, in flow under the square rather than overlapping the
                                     neighbouring cell. The unit word is dropped once there are
                                     more than ten teams, so ten columns still fit a laptop. --}}
                                <span class="px-2 rounded-full text-[11px] font-mono font-bold leading-[17px] whitespace-nowrap border border-gray-900"
                                      :class="team.excluded ? \'bg-amber-500 text-black\' : \'bg-emerald-600 text-white\'"
                                      x-text="teams.length > 10 ? formatFigure(team.remaining_budget) : formatCurrency(team.remaining_budget)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Why a team cannot bid, stated rather than hidden in a tooltip.
                     The chip already greys out and the control is genuinely :disabled, but
                     the reason only appeared on hover — so on a projector, or to anyone not
                     holding a mouse over the right circle, a team simply looked broken. The
                     squad-reserve rule is the auction's own rule and the room is entitled to
                     see it applied. --}}
                <template x-if="excludedTeams.length">
                    <div class="mt-3 pt-3 border-t border-gray-800 space-y-1">
                        <template x-for="team in excludedTeams" :key="'ex-' + team.id">
                            <p class="text-[11px] text-amber-300/90 leading-snug">
                                <span class="font-bold" x-text="team.name"></span>
                                <span class="text-amber-200/70" x-text="' — ' + (team.exclusion_reason || 'cannot bid on this player.')"></span>
                            </p>
                        </template>
                    </div>
                </template>

                <p x-show="!teams.length" class="text-center text-xs text-gray-500 py-2">
                    No teams in this tournament yet.
                </p>
            </div>

            {{-- overflow-x-auto: this row has grown (phase buttons, the Live/Batch toggle,
                 quick-bid jumps) past the width of a laptop, and with no wrap and no scroll
                 whatever sat at the end was simply cut off the screen and unreachable. The
                 export button was the casualty. --}}
            {{-- Say which seat this is.
                 Without it, an auctioneer's panel just looks like a control panel with pieces
                 missing — the difference between "read only" and "broken" has to be stated, not
                 inferred from an absence. --}}
            <div x-show="!canControl" x-cloak
                 class="bg-amber-500/10 border-t border-amber-500/30 px-4 py-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-300">Auctioneer view</span>
                <span class="text-[11px] text-amber-200/80">
                    You are following this auction, not running it &mdash; the board, the queue and every
                    team's purse update live. Bidding, selling, passing, skipping and undo belong to the organizer.
                </span>
            </div>

            <div class="h-14 bg-gray-900 border-t border-gray-800 flex items-center px-4 gap-2 overflow-x-auto">

                {{-- Everything that CHANGES the auction, in one container.
                     An Auctioneer reaches this panel to watch it — see canControl in the
                     Alpine state — so the controls come out and the read-only affordances
                     below them (fullscreen, the side panels, the export) stay. Wrapped as a
                     block rather than marked one button at a time, because a per-button list
                     goes stale the first time somebody adds another one. The routes refuse
                     these actions regardless; this is so the seat is not offered them. --}}
                <div class="flex items-center gap-2" x-show="canControl" x-cloak>
                {{-- 1. Player Input --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-500 font-mono text-lg">#</span>
                    <input type="text" x-model="playerNumberInput"
                           @keydown.enter="loadPlayerByNumber()"
                           placeholder="ID"
                           class="w-14 bg-gray-800 border border-gray-700 rounded px-2 py-1.5 text-sm text-white focus:outline-none focus:border-blue-500 font-mono">
                </div>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 2. NEXT button.
                     Named from the AUCTION's status, not from whether somebody is on the
                     block. Reading `currentPlayer` meant the label fell back to START in the
                     gap after every sale — so an auction thirty players in kept offering to
                     start itself. Same action either way; see nextActionLabel. --}}
                <button @click="loadNextPlayer()"
                        :disabled="isTumbling || displayState === 'bidding' || availablePlayers.length === 0"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:cursor-not-allowed text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        x-text="nextActionLabel + ' (N)'">
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

                {{-- How an offline room is run. Live is the auctioneer's way: tap a team's
                     logo and the price rises by the configured increment. Batch is the older
                     three-step form -- tick the teams, type each amount, end bidding -- kept
                     for rooms that work that way. Only meaningful while offline, so it is
                     hidden otherwise rather than sitting there doing nothing. --}}
                <div class="flex gap-1" x-show="displayState === 'bidding' && openBidMode === 'offline'" x-cloak>
                    <div class="w-px h-8 bg-gray-700"></div>
                    <button @click="offlineStageMode = 'live'"
                            class="px-2.5 py-1.5 rounded text-xs font-semibold transition"
                            :class="offlineStageMode === 'live' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'"
                            title="Tap a team logo to raise the price by the increment">Live</button>
                    <button @click="offlineStageMode = 'batch'"
                            class="px-2.5 py-1.5 rounded text-xs font-semibold transition"
                            :class="offlineStageMode === 'batch' ? 'bg-orange-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'"
                            title="Tick the participating teams and type each one's amount">Batch</button>
                </div>

                <div class="flex-1"></div>

                {{-- Quick-bid jumps: applied to whichever team is currently leading,
                     for when the room moves faster than the standard increment. --}}
                <template x-if="quickBidSteps.length && displayState === 'bidding' && showLiveStage">
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
                        :disabled="!canUndo || isUndoing || auctionStatus === 'paused'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap flex items-center gap-1"
                        :class="(canUndo && !isUndoing && auctionStatus !== 'paused') ? 'bg-orange-600 hover:bg-orange-500' : 'bg-gray-700 cursor-not-allowed opacity-50'"
                        :title="auctionStatus === 'paused'
                            ? 'Paused — resume before undoing'
                            : (canUndo ? ('Undo (U): ' + (nextUndoLabel || 'last action')) : 'Nothing to undo')">
                    <span>&#8630;</span> UNDO
                </button>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 5. Action Buttons --}}
                {{-- Greyed while the room is on hold: bidding is refused during a pause, so
                     letting the hammer fall would award a player at a price nobody was
                     allowed to answer. The server refuses it as well — the S shortcut gets
                     here without the button. --}}
                <button @click="sellPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding' || auctionStatus === 'paused'"
                        :title="auctionStatus === 'paused' ? 'Paused — resume before selling' : 'Sell (S)'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding' && auctionStatus !== 'paused') ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">SELL</button>
                {{-- Greyed while paused for the same reason as SELL: passing a player is as
                     final as selling one, and the teams cannot bid to stop it. --}}
                <button @click="passPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding' || !!currentPlayer?.current_bid_team_id || auctionStatus === 'paused'"
                        :title="auctionStatus === 'paused' ? 'Paused — resume before passing' : 'Pass (P)'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding' && !currentPlayer?.current_bid_team_id && auctionStatus !== 'paused') ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">PASS</button>
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
                {{-- Restarts the RUNNING POOL when the auction is locked to one, and only
                     falls back to wiping the whole auction when it is not pool-locked.
                     Re-running one pool used to mean resetting every pool, so a finished
                     pool could not be redone without throwing away the ones after it. --}}
                {{-- An ended auction gets the pool chooser; a running one keeps the controls it
                     has always had. Restarting everything is one option among several rather
                     than the only way back. --}}
                <button @click="auctionStatus === 'completed' ? restartAfterEnd() : (activePool ? restartActivePool() : restartAuction())"
                        x-show="auctionStatus === 'completed' || auctionStatus === 'running' || auctionStatus === 'paused'"
                        :title="activePool ? ('Restart ' + activePool.name + ' — its players go back on the block') : 'Restart the whole auction'"
                        class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs font-semibold transition">Restart</button>

                <div class="w-px h-8 bg-gray-700"></div>
                </div>

                {{-- 7. Fullscreen Toggle --}}
                <button @click="toggleFullscreen()" class="w-8 h-8 rounded flex items-center justify-center bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition-colors" title="Toggle Fullscreen (F)">
                    <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
                    <svg x-show="isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4"/></svg>
                </button>

                {{-- 8. Side Panel Toggles --}}
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button @click="showSidePanelFn('queue')" :class="sidePanel === 'queue' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Queue">Q</button>
                    <button @click="showSidePanelFn('teams')" :class="sidePanel === 'teams' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Teams">T</button>
                    <button @click="showSidePanelFn('bids')" :class="sidePanel === 'bids' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="Bids">B</button>
                    <button @click="showSidePanelFn('allPlayers')" :class="sidePanel === 'allPlayers' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors" title="All Players">A</button>

                    {{-- Rescue hatch. A plain link, not a fetch: a download must work even
                         if the panel's polling has fallen over, which is exactly when it is
                         wanted. Read-only, so it is always safe to press. --}}
                    <a href="/admin/organizer/auction/{{ $auction->id }}/api/export"
                       class="w-8 h-8 rounded flex items-center justify-center text-xs font-bold transition-colors bg-gray-800 text-gray-400 hover:bg-emerald-600 hover:text-white"
                       title="Download this auction as a spreadsheet (players, teams, spend and what is left)">&darr;</a>
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

                {{-- ═══ TEAMS PANEL ═══
                     Every team, including the full ones. This is a scoreboard rather than a
                     control: "who has finished" is exactly what an organizer opens it to see,
                     and hiding a completed squad here would make it look as though the team had
                     left the auction. Only the CONTROLS drop them. --}}
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
                                {{-- Fetched on demand. A roster per team on the two-second
                                     poll would multiply its cost by the squad size. --}}
                                <button type="button" @click="toggleSquad(team.id)"
                                        class="px-2 py-1 rounded text-[11px] font-semibold text-gray-300 bg-gray-700 hover:bg-gray-600 whitespace-nowrap">
                                    <span x-show="openSquad !== team.id">Squad</span>
                                    <span x-show="openSquad === team.id">Hide</span>
                                </button>
                            </div>

                            {{-- Who the team actually holds, and what each cost.
                                 AUCTION vs RETAINED comes from the auction rows, not from
                                 players.player_mode — selling sets that to `retained` too,
                                 so it cannot tell a buy from a keep. --}}
                            <template x-if="openSquad === team.id">
                                <div class="mb-3 rounded-lg bg-gray-900/70 border border-gray-700 divide-y divide-gray-800">
                                    <p x-show="squadLoading" class="px-3 py-3 text-xs text-gray-500">Loading squad…</p>

                                    <template x-for="p in squadPlayers" :key="p.id">
                                        <div class="flex items-center gap-2 px-3 py-2">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black tracking-wider"
                                                  :class="p.acquisition === 'auction' ? 'bg-emerald-600/30 text-emerald-300' : 'bg-amber-600/30 text-amber-300'"
                                                  x-text="p.acquisition === 'auction' ? 'AUC' : 'RET'"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs text-white truncate" x-text="p.name"></p>
                                                <p class="text-[10px] text-gray-500">
                                                    <span x-show="p.role" x-text="p.role"></span>
                                                    <span x-show="p.matches !== null && p.matches !== undefined">
                                                        <span x-show="p.role"> · </span><span x-text="p.matches"></span>M
                                                    </span>
                                                    <span x-show="p.runs !== null && p.runs !== undefined"> · <span x-text="p.runs"></span>R</span>
                                                    <span x-show="p.wickets !== null && p.wickets !== undefined"> · <span x-text="p.wickets"></span>W</span>
                                                </p>
                                            </div>
                                            <span class="text-[11px] font-bold whitespace-nowrap"
                                                  :class="p.acquisition === 'auction' ? 'text-emerald-400' : 'text-amber-400'"
                                                  x-text="formatCurrency(p.price)"></span>
                                        </div>
                                    </template>

                                    <p x-show="!squadLoading && !squadPlayers.length" class="px-3 py-3 text-xs text-gray-500">
                                        No players acquired yet.
                                    </p>
                                </div>
                            </template>
                            <div class="space-y-1">
                                {{-- The configured total for THIS team, then what is actually
                                     left to bid with once its retentions are paid for. --}}
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500">Total budget</span>
                                    <span class="text-gray-300" x-text="formatCurrency(team.allocated)"></span>
                                </div>
                                <div class="flex justify-between text-xs" x-show="team.retained_spent > 0">
                                    <span class="text-gray-500">
                                        Icon Player
                                        <span x-show="team.retained_count > 0" class="text-gray-600">(<span x-text="team.retained_count"></span>)</span>
                                    </span>
                                    <span class="text-amber-400">− <span x-text="formatCurrency(team.retained_spent)"></span></span>
                                </div>
                                <div class="flex justify-between text-xs" x-show="team.retained_spent > 0">
                                    <span class="text-gray-500">Auction purse</span>
                                    <span class="text-gray-300" x-text="formatCurrency(team.auction_purse)"></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">Remaining</span>
                                    {{-- Threshold against the post-retention purse: a team that
                                         retained most of its budget is committed, not broke. --}}
                                    <span class="font-bold" :class="team.remaining_budget < team.auction_purse * 0.2 ? 'text-red-400' : 'text-green-400'"
                                          x-text="formatCurrency(team.remaining_budget)"></span>
                                </div>
                                <div class="w-full bg-gray-600 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                         :class="team.remaining_budget < team.auction_purse * 0.2 ? 'bg-red-500' : 'bg-green-500'"
                                         :style="`width: ${Math.max(0, Math.min(100, (team.remaining_budget / (team.allocated || 1)) * 100))}%`"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>
                                        Spent: <span x-text="formatCurrency(team.total_spent)"></span>
                                        <span x-show="team.retained_spent > 0" class="text-gray-600">
                                            (<span x-text="formatCurrency(team.retained_spent)"></span> retained
                                            + <span x-text="formatCurrency(team.auction_spent)"></span> auction)
                                        </span>
                                    </span>
                                    <span x-show="team.retained_expected > 0"
                                          :class="team.retained_count !== team.retained_expected ? 'text-amber-400' : 'text-gray-500'"
                                          :title="`${team.retained_count} retained; ${team.retained_expected} expected.`">
                                        <span x-text="team.retained_count"></span>/<span x-text="team.retained_expected"></span> kept
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ═══ BIDS PANEL ═══ --}}
                <div x-show="sidePanel === 'bids'" class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-gray-400">Current player bids</span>
                        {{-- One row per team, so this counts teams (see above). --}}
                        <span class="text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-400"
                              x-text="sealedBids.length + (sealedBids.length === 1 ? ' team' : ' teams')"></span>
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

    {{-- ── Toasts and confirmations ──
         Deliberately plain DOM rather than alert()/confirm(). A native dialog forces the
         browser out of fullscreen to show itself, so on the projector every confirmation
         — restart most of all — dropped the hall's screen back to a windowed browser
         mid-auction. Nothing here leaves the document, so fullscreen is untouched.

         Every class below is already used elsewhere in resources/views, so the server's
         Tailwind build has them; z-[9999]/z-[99999] clear this file's z-50 ceiling. --}}
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
                    <p class="text-sm text-white" x-text="t.message"></p>
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
         {{-- A multi-choice dialog has no "Cancel" among its answers, so clicking
             outside it must not stand in for one. --}}
         @click.self="if (!confirmBox.choices) _settleConfirm(false)"
         x-transition.opacity>
        <div class="w-full max-w-md rounded-2xl bg-gray-900 border border-white/10 shadow-2xl p-6">
            <p class="text-sm font-bold uppercase tracking-wide"
               :class="confirmBox.danger ? 'text-red-400' : 'text-gray-400'"
               x-text="confirmBox.title"></p>
            {{-- pre-line: some confirmations are a short summary block, not one sentence. --}}
            <p class="mt-3 text-white text-sm whitespace-pre-line" x-text="confirmBox.message"></p>

            {{-- Some confirmations are not yes/no but "yes, to these parts".
                 A pool restart is the case that forced this: it used to reset sold, unsold and
                 skipped players together with no way to say which, so an organizer who wanted
                 the unsold players back had to accept unwinding every sale in the pool too. --}}
            <div class="mt-5 space-y-2" x-show="confirmBox.checkboxes" x-cloak>
                <template x-for="box in (confirmBox.checkboxes || [])" :key="box.value">
                    <label class="flex items-start gap-3 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 cursor-pointer transition"
                           :class="box.disabled ? 'opacity-40 cursor-not-allowed' : ''">
                        <input type="checkbox" class="mt-0.5 w-4 h-4 accent-emerald-500"
                               :disabled="box.disabled"
                               :checked="confirmBox.selected.includes(box.value)"
                               @change="_toggleConfirmBox(box.value)">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-white" x-text="box.label"></span>
                            <span class="block text-xs text-gray-400 mt-0.5" x-show="box.hint" x-text="box.hint"></span>
                        </span>
                    </label>
                </template>
                <p class="text-[11px] text-amber-400" x-show="confirmBox.selected.length === 0">
                    Nothing selected — there would be nothing to restart.
                </p>
            </div>

            {{-- Plain yes/no: every action button on this panel. --}}
            <div class="mt-6 flex items-center justify-end gap-3" x-show="!confirmBox.choices">
                <button type="button" @click="_settleConfirm(false)"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-white/10 hover:bg-white/20 transition">
                    Cancel
                </button>
                <button type="button"
                        @click="_settleConfirm(confirmBox.checkboxes ? [...confirmBox.selected] : true)"
                        :disabled="confirmBox.checkboxes && confirmBox.selected.length === 0"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="confirmBox.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'">
                    Confirm
                </button>
            </div>

            {{-- More than two ways out. Stacked full-width rather than in a row: the
                 sealed-threshold question has three answers with real sentences on them,
                 and side by side they truncate to nothing on the panel's own width. --}}
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

<script>
function auctionOrganizerPanel() {
    return {
        // Constants from DB
        BID_TIMER_DURATION: {{ $auction->bid_timer_seconds ?? 30 }},
        BID_TIMER_RESET_TO: {{ $auction->bid_timer_reset_seconds ?? 15 }},

        /*
         * May this seat change the auction, or only watch it?
         *
         * An Auctioneer reaches this panel with `auction.observe` and no `auction.control`:
         * they call the lots in the room and need the board, the queue and every team's purse
         * in front of them, and must not be able to sell, pass, skip or undo. Every POST route
         * in the organizer group enforces that server-side — this flag exists so the panel
         * does not offer buttons that would come back 403.
         */
        canControl: {{ ($canControl ?? true) ? 'true' : 'false' }},

        // State
        auctionId: null,
        auctionStatus: 'scheduled',
        availablePlayers: [],
        teams: [],

        // Display states: 'waiting', 'tumbling', 'bidding', 'sold', 'unsold'
        displayState: 'waiting',
        statusText: 'Click "Next Player" to begin',
        tumblerText: '',

        currentPlayer: null,
        lastSoldPlayer: null,
        currentBid: 0,

        /**
         * The figure actually on screen, which chases `currentBid` rather than snapping to it.
         *
         * A raise is optimistic — the price changes the instant a chip is tapped — but a number
         * that simply replaces itself gives the eye nothing to follow, so the room reads the jump
         * as the screen having lagged and then caught up. Counting up occupies the same moment
         * with motion: the value is already correct, and the animation is what makes it look it.
         *
         * Kept separate from `currentBid` so nothing else has to care. Every guard, comparison
         * and request still reads the real value; only the display reads this.
         */
        displayBid: 0,
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
        /* ── In-page toast and confirm ──
           Native alert()/confirm() are unusable on this screen: the browser drops
           fullscreen to show them, so every confirmation kicked a projector out of
           fullscreen mid-auction. These are ordinary DOM, so fullscreen survives. */
        toasts: [],
        _toastSeq: 0,

        confirmBox: { open: false, title: '', message: '', danger: false, choices: null, checkboxes: null, selected: [], _resolve: null },

        toast(message, type = 'info', title = null) {
            const id = ++this._toastSeq;
            this.toasts.push({ id, message, type, title });
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, type === 'error' ? 6000 : 3600);
        },

        /**
         * Awaitable replacement for confirm(). Resolves true on confirm, false on cancel.
         *
         * Pass `choices` for a question with more than two answers — each is
         * {value, label, hint?, class?} and the promise resolves with the chosen `value`,
         * or false if the dialog is dismissed. Everything on this panel goes through here
         * rather than native confirm(), which drops fullscreen the moment it opens.
         */
        askConfirm(message, { title = 'Confirm', danger = false, choices = null, checkboxes = null } = {}) {
            return new Promise((resolve) => {
                this.confirmBox = {
                    open: true, title, message, danger, choices, checkboxes,
                    // Everything not explicitly opted out of starts ticked, so Confirm on a
                    // glance does the whole thing — the same answer the dialog gave before it
                    // had checkboxes at all.
                    selected: (checkboxes || []).filter(b => b.checked !== false && ! b.disabled).map(b => b.value),
                    _resolve: resolve,
                };
            });
        },

        _toggleConfirmBox(value) {
            this.confirmBox.selected = this.confirmBox.selected.includes(value)
                ? this.confirmBox.selected.filter(v => v !== value)
                : [...this.confirmBox.selected, value];
        },

        _settleConfirm(answer) {
            const resolve = this.confirmBox._resolve;
            this.confirmBox = { open: false, title: '', message: '', danger: false, choices: null, checkboxes: null, selected: [], _resolve: null };
            if (resolve) resolve(answer);
        },

        // Sealed round, as the organizer may see it. Amounts are absent from this
        // payload until the round is revealed — the panel is often on a projector.
        sealed: { active: false },
        /*
         * Sealed amounts are masked on the board until the organizer asks for them.
         *
         * Defaults to hidden and is NOT remembered between rounds: a panel left revealing amounts
         * from the last player is exactly the projector accident this guards against.
         */
        sealedAmountsVisible: false,
        sealedAdjustAmount: {},
        sealedManualReason: '',
        // Briefly rings the reason box when a Pick is refused for want of one.
        sealedReasonFlash: false,
        // The usual three, offered as one tap each — see the note beside them in the markup.
        LOT_REASONS: ['Slips drawn at the desk', 'Coin toss', 'Organizer decision'],

        /*
         * Which teams to invite into the NEXT sealed round, before Open Entry is pressed.
         *
         * Open Entry used to invite every participating team unconditionally — an
         * expensive player does not always need every team weighing in, and the organizer
         * had no way to leave some out before the board was already built.
         *
         * null means "everyone", so a round opened with no selection made behaves exactly
         * as before. Reset to null whenever a new player takes the block, so a choice made
         * for one player's round never carries into the next.
         */
        sealedTeamSelection: null,

        /**
         * Nothing is ticked until the organizer ticks it.
         *
         * `null` used to mean "everybody", so the board opened with every team already in the
         * round and choosing a subset meant UNticking the ones you did not want. That is the
         * wrong way round for a control whose whole purpose is to leave teams out: the common
         * case is a handful of interested sides, and starting from all of them makes the
         * expensive mistake — a team in a round it was never meant to be in — the one that
         * happens by doing nothing.
         *
         * `null` and `[]` now mean the same thing here, so a panel that has not yet been
         * touched and one that has been cleared both read as empty.
         */
        isSealedTeamSelected(teamId) {
            return (this.sealedTeamSelection || []).includes(teamId);
        },

        toggleSealedTeam(teamId) {
            if (this.sealedTeamSelection === null) {
                // First tick on an untouched panel: this team, and only this team.
                this.sealedTeamSelection = [teamId];
                return;
            }
            if (this.sealedTeamSelection.includes(teamId)) {
                this.sealedTeamSelection = this.sealedTeamSelection.filter(id => id !== teamId);
            } else {
                this.sealedTeamSelection.push(teamId);
            }
        },

        get sealedSelectedCount() {
            return (this.sealedTeamSelection || []).length;
        },

        showShuffleOverlay: false,
        shufflePhase: 'spinning',
        shuffleDisplayName: '',
        shuffleSelectedPlayer: null,
        _shuffleInterval: null,
        _shuffleTimeout: null,
        // The player last seen on the block, so an overlay can name them once they are gone.
        _lastOnBlockPlayer: null,

        // Ordering token for pushed raises — socket frames arrive unordered, so anything not
        // newer than this is dropped rather than applied.
        _lastAppliedBidId: 0,
        // When a pushed frame last arrived, so the poll can tell a working socket from a dead one.
        _lastPushAt: 0,
        /*
         * The raise this panel has shown but the server has not confirmed yet.
         *
         * `{ teamId, amount }` from the moment a chip is clicked until the POST answers. Every
         * poll that lands in that window carries a snapshot taken BEFORE the click, and applying
         * it wipes the leader off the board — see _snapshotPredatesLocalBid().
         */
        _pendingBid: null,
        _pendingBidTimeout: null,

        // How long a lot draw cycles the tied team names before landing. The draw is
        // decided on the server before any of this starts; the spin only shows it.
        LOT_SPIN_MS: 15000,

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
        /** The consequences of the next undo, as lines for its confirm dialog. */
        nextUndoNotes: [],
        isUndoing: false,

        // Increment ladder, resolved server-side.
        nextBidAmount: null,
        // The sealed threshold, while it still applies to this player — see the server's
        // BidIncrementService::openBidCeilingFor().
        openBidCeiling: null,
        bidIncrement: null,
        maxBidReached: false,
        quickBidSteps: [],
        // Armed jump amount: the next team click uses this instead of the increment.
        armedStepIndex: null,

        // Pool lock: the auction runs one pool at a time.
        /*
         * Seeded from the server render, not left empty until the first poll.
         *
         * `activePool: null` + `pools: []` is indistinguishable from "this auction has no pool
         * running", and the strip below says exactly that — in amber — so every reload accused a
         * perfectly healthy auction of having no pool for as long as the first poll took.
         */
        activePool: @json($poolProgress['active_pool'] ?? null),
        nextPool: @json($poolProgress['next_pool'] ?? null),
        pools: @json($poolProgress['pools'] ?? []),

        /*
         * Has real server state landed yet?
         *
         * Anything that ASSERTS something about the auction has to wait for this. An empty
         * default is not a fact, and rendering it as one is how a reload came to announce
         * conclusions the panel had not yet checked.
         */
        stateLoaded: false,

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

        init(auctionId, status, players, teams, currentPlayer) {
            this.auctionId = auctionId;
            this.auctionStatus = status;
            this.availablePlayers = players;
            this.teams = teams;

            /*
             * One hook instead of thirteen assignment sites.
             *
             * `currentBid` is set from the optimistic raise, the poll, a pushed frame, an undo,
             * a restart and several resets — watching it means the display follows all of them
             * and no future one can be forgotten. countBidTo() decides for itself whether a
             * given change is worth animating.
             */
            this.$watch('currentBid', (value) => this.countBidTo(value));

            if (currentPlayer) {
                this.currentPlayer = currentPlayer;
                // Also on load, not only when the poll adopts a new player: a panel opened with
                // somebody already up must be able to name them when they leave the block.
                this._lastOnBlockPlayer = currentPlayer;
                this.currentBid = currentPlayer.current_price || currentPlayer.base_price;
                this._lastKnownBid = this.currentBid;
                // Already up when the panel opened — the figure is history, not a raise.
                this.countBidTo(this.currentBid, { instant: true });
                this.displayState = 'bidding';
                this.sealedBids = [];

                /*
                 * Seeded from the server's clock, not from the top.
                 *
                 * startBiddingTimer() with no argument counts down from the FULL duration, so
                 * every page load showed a healthy timer regardless of what the clock actually
                 * said, and only the first poll — up to two seconds later — corrected it.
                 * Refreshing a screen whose timer had already run out therefore showed it
                 * running again, which is the opposite of the truth at the moment an operator
                 * reloads precisely because something looks wrong.
                 */
                const seed = @json($timerState ?? null);

                this.timerEnabled = seed ? !!seed.applies : true;
                this.timerExpired = seed ? !!seed.expired : false;
                this.timerPaused = seed ? !!seed.paused : false;

                if (seed && seed.applies === false) {
                    // No clock on this auction at all; nothing to count.
                    this.biddingTimerSeconds = 0;
                } else if (seed && (seed.remaining === null || seed.remaining <= 0)) {
                    // Already run out: show that, and do not start ticking.
                    this.biddingTimerSeconds = 0;
                    this.timerWidth = 0;
                } else if (seed && seed.remaining !== undefined) {
                    this.startBiddingTimer(seed.remaining);
                    const limit = seed.limit || this.BID_TIMER_DURATION;
                    this.timerWidth = limit > 0 ? (seed.remaining / limit) * 100 : 0;
                } else {
                    this.startBiddingTimer();
                }
            }

            this.startStatePolling();
            this.subscribeToRaises();

            // Listen for fullscreen changes (e.g. user presses Esc to exit)
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
        },

        /**
         * Take the price straight off the wire instead of waiting for the next poll.
         *
         * Additive: the 2-second poll keeps running and stays the source of truth. If the
         * socket never connects or later drops, this simply never fires and the panel
         * behaves exactly as it did before.
         */
        subscribeToRaises() {
            // One deferred retry, not a loop: the CDN scripts are parser-blocking so they
            // normally run before Alpine boots, but if they have not yet, `load` is the last
            // moment they could. A retry loop is the defect in auctions/show.blade.php.
            if (!window.auctionChannel) {
                window.addEventListener('load', () => this.subscribeToRaises(), { once: true });
                return;
            }

            const channel = window.auctionChannel(this.auctionId);
            if (!channel) return;

            channel.listen('.bid.raised', (e) => {
                // A frame arriving is the only proof the socket is actually delivering, which is
                // what lets the poll below back off — see pollDelay().
                this._lastPushAt = Date.now();
                this.applyRaise(e);
            });
        },

        /**
         * Is this poll snapshot older than a raise this panel already knows about?
         *
         * The panel polls every two seconds and a click almost always lands inside an open
         * request. That response was built before the bid existed, so it carries
         * `current_bid_team_id: null` — and the poll handler used to apply it wholesale, which
         * un-selected the team a moment after it was pressed. Worse, `bidForTeam` decides "this
         * team already leads" from that same field, so the operator's natural second click was
         * not a retry: it placed a SECOND bid and moved the price two rungs.
         *
         * Two independent tests, because a raise can reach us by two routes:
         *  - a bid of ours is still unconfirmed and the snapshot does not show it; or
         *  - the snapshot's newest bid id is behind one already applied — the same `bid_id`
         *    ordering the pushed-frame handler above trusts, reused rather than reinvented.
         */
        _snapshotPredatesLocalBid(newPlayer) {
            if (! newPlayer) return false;

            if (this._pendingBid && newPlayer.current_bid_team_id != this._pendingBid.teamId) {
                return true;
            }

            const newestId = (newPlayer.bids || []).reduce(
                (max, b) => Math.max(max, Number(b?.id) || 0),
                0
            );

            return newestId > 0 && newestId < (this._lastAppliedBidId || 0);
        },

        /** Forget the unconfirmed raise, however it resolved. */
        _clearPendingBid() {
            this._pendingBid = null;

            if (this._pendingBidTimeout) {
                clearTimeout(this._pendingBidTimeout);
                this._pendingBidTimeout = null;
            }
        },

        /**
         * Apply a pushed raise.
         *
         * Socket frames are neither ordered nor deduplicated, so this trusts `bid_id` and
         * nothing else: a frame that is not newer than the last one applied is dropped.
         * Without that, a late frame can drag a price back below where the bidding has
         * already reached.
         *
         * Deliberately narrow — it moves the price, the leader and the clock, and never
         * resolves a player. Sell, pass and unsold stay with the poll and the organizer's
         * own action, so a stale frame cannot stamp a result on the board.
         */
        applyRaise(e) {
            if (!e || !this.currentPlayer) return;
            if (Number(e.auction_player_id) !== Number(this.currentPlayer.id)) return;

            const bidId = Number(e.bid_id) || 0;
            if (bidId <= (this._lastAppliedBidId || 0)) return;
            this._lastAppliedBidId = bidId;

            const price = Number(e.current_price);
            if (!isFinite(price)) return;

            this.currentBid = price;
            this.currentPlayer.current_price = price;
            this.currentPlayer.current_bid_team_id = e.current_bid_team_id ?? null;

            // Keep the poll's own change-detection in step, or it will treat this as a new
            // bid on the next tick and reset the clock a second time.
            this._lastKnownBid = price;

            const team = (this.teams || []).find(t => t.id == e.current_bid_team_id);
            this.winningTeamName = team?.name || e.team_name || 'No Bids';

            // A raise restarts the clock server-side; mirror it through the existing reset
            // rather than keeping a second countdown of our own.
            this.resetBiddingTimer();
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
        // Seconds left in the restart announcement, from the server's window.
        restartSeconds: 0,
        // Whether the SERVER says the bid clock is frozen.
        timerPaused: false,
        _pollInterval: null,

        /*
         * Poll on a CHAIN, never on an interval.
         *
         * setInterval fired every 2s whether or not the previous request had come back, so
         * one slow response meant the next was sent on top of it, and the next. On a live
         * panel the network tab filled with poll-state requests all stuck at (pending) and
         * 0 B transferred. A browser allows about six connections per host: once they are
         * all held by stalled polls, NOTHING else can go out -- not a bid, not a sale, not
         * the export. The panel appears to hang, and it hangs hardest exactly when the
         * server is under load, which is the middle of an auction.
         *
         * Chaining removes the failure mode by construction: the next poll is scheduled
         * only once the previous one has settled, so at most one is ever in flight.
         */
        _pollTimer: null,
        _pollInFlight: false,

        startStatePolling() {
            this._lastCurrentPlayerId = this.currentPlayer?.id || null;

            const tick = async () => {
                try {
                    await this.pollAuctionState();
                } finally {
                    // finally, not then: a thrown poll must still schedule the next one, or
                    // a single failure silently ends the loop for the rest of the auction.
                    this._pollTimer = setTimeout(tick, this.pollDelay());
                }
            };

            tick();
        },

        /**
         * How long to wait before polling again.
         *
         * This was a flat two seconds whether or not Pusher was connected — so on a healthy
         * socket the panel was fetching a full state payload thirty times a minute to learn
         * what the socket had already told it. Worse for correctness than for bandwidth: every
         * one of those requests is a window in which a snapshot taken before a click comes back
         * and contradicts it, which is the bug this guard exists for. Fewer requests, fewer
         * windows.
         *
         * Proof of health is a frame actually arriving, not the socket claiming to be
         * connected — a subscription that connects and then delivers nothing is exactly the
         * venue failure this has to survive. If nothing has been pushed recently the panel goes
         * straight back to two seconds, on its own, with no flag to get stuck.
         *
         * The team screens already work this way (bidding-page.blade.php); this brings the
         * panel into line with them.
         */
        pollDelay() {
            const PUSH_FRESH_MS = 20000;
            const healthy = this._lastPushAt && (Date.now() - this._lastPushAt) < PUSH_FRESH_MS;

            // Still a poll on a healthy socket: it is what reconciles sales, passes, pool
            // changes and purse figures, none of which the raise frame carries.
            return healthy ? 6000 : 2000;
        },

        stopStatePolling() {
            if (this._pollTimer) { clearTimeout(this._pollTimer); this._pollTimer = null; }
        },

        async pollAuctionState() {
            // A second caller (a command finishing, say) must not start another request
            // while one is already open — that is the pile-up this exists to prevent.
            if (this._pollInFlight) return;
            this._pollInFlight = true;

            /*
             * And a hung request must not hold the slot for ever. Without this, one poll
             * that never resolves stops the chain permanently and the panel goes quiet
             * with no error anywhere.
             */
            const abort = new AbortController();
            const killer = setTimeout(() => abort.abort(), 8000);

            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/poll-state`, {
                    headers: { 'Accept': 'application/json' },
                    signal: abort.signal,
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

                this.maybeAskSealedThreshold(data);

                /*
                 * The server now sends these ten fields flat, instead of whole models this
                 * mapper then threw 90% of away — 286 KB of a 314 KB poll, every two seconds.
                 *
                 * The nested fallbacks are kept on purpose: a panel loaded BEFORE that change
                 * deployed is still running this code against the old shape, and an auction is
                 * not the moment to require a hard refresh.
                 */
                this.availablePlayers = (data.available_players || []).map(ap => ({
                    id: ap.id,
                    name: ap.name || ap.player?.name || 'Unknown',
                    base_price: ap.base_price,
                    image_path: ap.image_path ?? ap.player?.image_path ?? null,
                    player_type: ap.player_type || ap.player?.player_type?.name || ap.player?.player_type?.type || 'Player',
                    batting_style: ap.batting_style ?? ap.player?.batting_profile?.name ?? ap.player?.batting_profile?.style ?? null,
                    bowling_style: ap.bowling_style ?? ap.player?.bowling_profile?.name ?? ap.player?.bowling_profile?.style ?? null,
                    total_matches: ap.total_matches ?? ap.player?.total_matches ?? null,
                    total_runs: ap.total_runs ?? ap.player?.total_runs ?? null,
                    total_wickets: ap.total_wickets ?? ap.player?.total_wickets ?? null,
                }));

                // Purse figures come from the server (AuctionPoolService) — no local
                // fallback formula, which used to be able to disagree with what the
                // server would actually accept at SELL.
                this.teams = data.teams || [];

                this.canUndo = !!data.can_undo;
                this.nextUndoLabel = data.next_undo || null;
                this.nextUndoNotes = data.next_undo_notes || [];
                this.unsoldPool = data.unsold_pool || null;
                this.activePool = data.active_pool || null;
                // From here on the panel is describing the server, not its own defaults.
                this.stateLoaded = true;
                this.syncParticipantsToPool();
                this.nextPool = data.next_pool || null;
                this.pools = data.pools || [];
                this.quickBidSteps = data.quick_bid_steps || [];
                this.timerEnabled = !!data.timer_enabled;
                this.timerExpiryAction = data.timer_expiry_action || 'manual';

                // The server owns the clock; the local countdown only renders it.
                /* One clock, three screens. The panel already re-read the server value each
                   poll, but its local tick kept running through a pause — so the operator's
                   number drifted away from the wall and the stream until the next poll
                   yanked it back. */
                this.timerPaused = !!data.timer_paused;

                if (this.timerPaused) {
                    this.stopBiddingTimer();
                } else if (! this.biddingTimerInterval
                    && ! this.biddingClosed
                    && this.currentPlayer
                    && this.displayState === 'bidding'
                    && data.auction_status === 'running'
                    && data.timer_enabled
                    && (data.timer_seconds_remaining ?? 0) > 0) {
                    /*
                     * Pick the local tick back up when the server's clock is running and
                     * ours is not.
                     *
                     * The tick only ever started as a player took the block, so a pause
                     * killed it for good: resuming left the same player up, nothing
                     * restarted it, and the number moved only when a poll landed — in
                     * two-second jumps, with the closing calls (which escalate off the
                     * local tick between polls) never firing at all.
                     *
                     * Written as "the server says running, we are not ticking" rather than
                     * as a resume handler, so any other way the tick dies also recovers.
                     */
                    this.startBiddingTimer(data.timer_seconds_remaining);
                }

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
                // Where open bidding stops, when a sealed threshold is still in force.
                this.openBidCeiling = data.open_bid_ceiling ?? null;
                this.bidIncrement = data.bid_increment ?? null;
                this.maxBidReached = !!data.max_bid_reached;

                /* Restart notice, measured by the server so this panel and the wall count
                   the same seconds. Once the window closes the panel drops back to its
                   ordinary empty state rather than sitting on the announcement. */
                this.restartSeconds = Number(data.restart_seconds || 0);
                if (this.displayState === 'restarting' && !data.restarting) {
                    this.displayState = 'waiting';
                }

                const newPlayer = data.current_player;
                const prevId = this._lastCurrentPlayerId;

                if (newPlayer) {
                    /*
                     * Adopt the server's player when the id changed OR when this panel is not
                     * showing that player as live.
                     *
                     * A matching id used to mean "nothing to do" — including when the panel was
                     * sitting on `waiting` while the server had that same player on the block.
                     * Nothing ever recovered it: every later poll saw the same id and did
                     * nothing, so the operator was left on an empty screen for the rest of the
                     * lot. Guarded on the two states that legitimately cover a live player, so
                     * this cannot interrupt the shuffle overlay or the restart notice.
                     */
                    const mustAdopt = newPlayer.id !== prevId
                        || (this.displayState !== 'bidding'
                            && ! this.isTumbling
                            && ! this.showShuffleOverlay
                            && this.displayState !== 'restarting');

                    if (mustAdopt) {
                        const isNewPlayer = newPlayer.id !== prevId;

                        this.currentPlayer = newPlayer;
                        this.currentBid = newPlayer.current_price || newPlayer.base_price;
                        this._lastKnownBid = this.currentBid;
                        this.displayState = 'bidding';
                        this.biddingClosed = false;
                        this._lastCurrentPlayerId = newPlayer.id;
                        /* Kept so the overlay that follows can name the player who just left
                           the block. Without it the UNSOLD branch below had nothing to show and
                           reused whoever was stamped last. */
                        this._lastOnBlockPlayer = newPlayer;

                        /*
                         * Only a genuinely NEW player resets the room around them.
                         *
                         * Recovering a panel that lost track of a player already up must not
                         * clear a sealed team selection the organizer just made, wipe the offline
                         * entry they are part-way through, or restart the clock from full — the
                         * server's own countdown is seeded further down from
                         * `timer_seconds_remaining`. Recovery adopts the player; it does not
                         * start the lot over.
                         */
                        if (isNewPlayer) {
                            /*
                             * A new player arrives AT their base price; they do not climb to it
                             * from the last lot's final bid, which is what a roll would show.
                             */
                            this.countBidTo(this.currentBid, { instant: true });

                            // Allow a fresh time-up announcement for this player.
                            this._timerFiredForPlayer = null;
                            this.sealedBids = [];
                            // A team selection made for the last player's sealed round must not
                            // silently carry into this one.
                            this.sealedTeamSelection = null;
                            // Re-mask. A panel left revealing the last player's amounts is
                            // exactly the projector accident the mask exists for.
                            this.sealedAmountsVisible = false;
                            this.resetOfflinePanel();
                            this.statusText = `${newPlayer.player?.name} is now live!`;
                            this.startBiddingTimer();
                        }
                    }

                    /*
                     * A snapshot taken before a raise this panel already knows about must not
                     * speak for the price or the leader. It is still the truth about everything
                     * else — squad counts, purses, stats, the pool, the sealed round — so it is
                     * adopted for those and held back only where it is behind.
                     */
                    const stale = this._snapshotPredatesLocalBid(newPlayer);

                    if (! mustAdopt) {
                        if (stale) {
                            /*
                             * Keep our figure and our leader. `currentPlayer` is still replaced,
                             * because everything else on it has moved on — but the field the
                             * board reads for the leading team is stamped back onto the new
                             * object, which is exactly what the wholesale assignment used to
                             * drop.
                             */
                            const heldTeamId = this._pendingBid
                                ? this._pendingBid.teamId
                                : this.currentPlayer?.current_bid_team_id;

                            this.currentPlayer = newPlayer;
                            this.currentPlayer.current_price = this.currentBid;
                            this.currentPlayer.current_bid_team_id = heldTeamId ?? null;
                        } else {
                            const newBid = newPlayer.current_price || this.currentBid;
                            if (newBid !== this._lastKnownBid) {
                                this._lastKnownBid = newBid;
                                this.resetBiddingTimer();
                            }
                            this.currentBid = newBid;
                            this.currentPlayer = newPlayer;
                        }
                    }

                    // The leading team, from whichever of the two the panel is trusting.
                    const leaderId = this.currentPlayer?.current_bid_team_id;

                    if (leaderId) {
                        const bidTeam = this.teams.find(t => t.id == leaderId);
                        if (bidTeam) this.winningTeamName = bidTeam.name;
                    } else if (! stale) {
                        this.winningTeamName = 'No Bids';
                    }

                    this.fetchSealedBids();
                    this.fetchSealedState();
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
                    } else if (data.restarting) {
                        /* A restart empties the block and wipes the sale, so the player who
                           was up is neither on_auction nor in sold_players. Reading that as
                           UNSOLD stamped a red UNSOLD over a player nobody had passed on —
                           and over an auction that had just been reset to zero. */
                        this.displayState = 'restarting';
                        this.lastSoldPlayer = null;
                    } else {
                        /*
                         * The player left the block and is not in sold_players — a PASS, most
                         * often, since an unsold player never enters that list.
                         *
                         * This set the state and nothing else, so the overlay rendered with
                         * whatever lastSoldPlayer still held from the PREVIOUS player: pass one
                         * player and the wall stamped UNSOLD across someone else's face, with
                         * their name under it. Named from the player who was actually up.
                         */
                        const wasUp = this._lastOnBlockPlayer;

                        this.lastSoldPlayer = wasUp
                            ? { player: wasUp.player, final_price: null, winning_team: null }
                            : null;

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
                // An abort is this code timing itself out, not a fault worth logging on
                // every slow network.
                if (e?.name !== 'AbortError') {
                    console.error('[OrganizerPanel] Poll error:', e);
                }
            } finally {
                clearTimeout(killer);
                this._pollInFlight = false;
            }
        },

        /* ── Sealed round ──────────────────────────────────────────────────────
           Every control here reports a no-op as success: two panels both pressing
           Lock is ordinary operation, not something to raise a red toast about. */

        /**
         * Clear everything the panel is holding for the player just finished, and stand ready
         * for the next one.
         *
         * Written out by hand in three places, and the two "reopen a pool" paths had a shortened
         * copy that reset the pointer and the display state but nothing else. So pressing Run
         * Unsold left the previous player's sealed board on screen — a tie, its DRAW LOT and its
         * Pick buttons — over a pool that had already moved on, along with their last bid, the
         * winning-team caption and a running clock. The only way to a clean panel was a reload,
         * mid-auction, in front of the room.
         *
         * The stale pointer is the dangerous half: `_lastCurrentPlayerId` is what the next poll
         * compares against, and left pointing at the finished player it stamps UNSOLD over
         * somebody who has merely gone back into the queue.
         */
        _clearForNextPlayer() {
            this.auctionStatus = 'running';
            this.displayState = 'waiting';
            this.currentPlayer = null;
            this.stopBiddingTimer();
            this._lastCurrentPlayerId = null;
            this._lastKnownBid = 0;
            this.lastSoldPlayer = null;
            this.currentBid = 0;
            this.winningTeamName = 'No Bids';
            this.sealedBids = [];
            // Shape, not null — the markup reads sealed.active unguarded.
            this.sealed = { active: false };
        },

        async fetchSealedState() {
            if (this.bidType !== 'closed' || !this.currentPlayer) {
                if (this.sealed.active) this.sealed = { active: false };
                return;
            }
            try {
                const res = await fetch(
                    `/admin/organizer/auction/${this.auctionId}/api/closed-bid/state?auction_player_id=${this.currentPlayer.id}`,
                    { headers: { Accept: 'application/json' } }
                );
                if (!res.ok) return;
                const data = await res.json();
                this.sealed = data.closed_bid || { active: false };
            } catch (e) { /* a dropped poll is not worth surfacing */ }
        },

        async sealedCommand(path, body = {}) {
            // `state` is the only sealed GET, and it is fetched elsewhere; everything routed
            // through here changes the round.
            if (! this.guardControl('act on the sealed round')) return null;

            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/closed-bid/${path}`, {
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
                // A refusal is reported, but a no-op is not an error: two panels both
                // pressing Lock is ordinary operation, not a mistake to shout about.
                if (data.message) {
                    this.statusText = data.message;
                    this.toast(data.message, data.handled ? 'success' : 'info', 'Sealed round');
                }
                return data;
            } catch (e) {
                this.toast('That did not go through.', 'error', 'Sealed round');
                return null;
            }
        },

        /** Give a held round more time. Refused once it is locked or revealed. */
        async sealedExtend() {
            await this.sealedCommand('extend-timer');
        },

        /** Back to the team-selection step. The server refuses it once a team has responded. */
        async sealedReopenSelection() {
            const result = await this.sealedCommand('reopen-selection');

            if (result?.handled) {
                // The selection is the organizer's again, so start from every eligible team
                // rather than from whoever happened to be invited a moment ago.
                this.sealedTeamSelection = null;
            }
        },

        async sealedOpenEntry() {
            const ids = this.sealedTeamSelection || [];

            if (ids.length === 0) {
                // Nothing is ticked by default now, so this is the ordinary first press rather
                // than an odd one — say what to do rather than only that it failed.
                this.toast('Tick the teams that should take part in this round first.', 'error');
                return null;
            }

            const result = await this.sealedCommand('open-entry', { team_ids: ids });
            // Handled once, so a stray re-selection doesn't linger for whatever round
            // comes after this one.
            if (result?.handled) this.sealedTeamSelection = null;
            return result;
        },
        /*
         * Start carries the selection too.
         *
         * Pressing Start on a pending round skips Open Entry, and the server invites
         * everyone when a round has no entries yet — so a selection made in the picker
         * was silently discarded by taking the quicker of the two buttons.
         */
        async sealedStart() {
            if (this.sealed.state === 'pending') {
                const ids = this.sealedTeamSelection || [];

                if (ids.length === 0) {
                    this.toast('Tick the teams that should take part in this round first.', 'error');
                    return null;
                }

                const result = await this.sealedCommand('start', { team_ids: ids });
                if (result?.handled) this.sealedTeamSelection = null;
                return result;
            }

            return this.sealedCommand('start');
        },
        sealedLock() { return this.sealedCommand('lock'); },
        sealedStartRebid() { return this.sealedCommand('start-rebid'); },

        async sealedAward() {
            const data = await this.sealedCommand('award');
            if (data?.handled) this._fireConfetti();
        },

        sealedNoEntries(choice) { return this.sealedCommand('no-entries-decision', { choice }); },

        sealedEntryCommand(entryId, action, body = {}) {
            return this.sealedCommand(`entries/${entryId}/${action}`, body);
        },

        /**
         * The figure a step will land on, in raw units.
         *
         * Stepped on the client and sent as an explicit amount rather than asking the
         * server to step: the server starts an untouched entry from the top standing bid,
         * which is deliberately never sent here, so the box could not show where a press
         * had landed. It showed the floor as a placeholder throughout while each press
         * recorded a higher figure — the organizer pressed + eight times because nothing
         * on screen moved.
         *
         * An empty box means nothing has been set yet, so the first press lands on the
         * floor itself — the minimum legal bid, and the figure the placeholder was already
         * promising — rather than a step above it.
         *
         * Integer cents, because the grain is two decimal places and a float would drift
         * off the step grid after a few presses.
         */
        sealedSteppedAmount(entry, direction) {
            const floorC = Math.round(Number(this.sealed.floor || 0) * 100);
            const stepC = Math.round(Number(this.sealed.step || 0) * 100);
            const typed = this.sealedAdjustAmount[entry.entry_id];

            if (typed === undefined || typed === '') return floorC / 100;

            const currentC = Math.round(this.fromM(typed) * 100);
            const nextC = direction === 'up' ? currentC + stepC : currentC - stepC;

            // Snap onto the grid as it steps, so a press also rescues an amount typed off
            // it, and never below the round's own floor.
            const snappedC = stepC > 0 ? Math.round(nextC / stepC) * stepC : nextC;

            return Math.max(floorC, snappedC) / 100;
        },

        sealedAdjust(entry, direction) {
            const amount = this.sealedSteppedAmount(entry, direction);

            // Shown before the round-trip so the control responds to the press itself.
            this.sealedAdjustAmount[entry.entry_id] = String(this.toM(amount));

            return this.sealedEntryCommand(entry.entry_id, 'adjust', { amount });
        },

        sealedAdjustCustom(entry) {
            const typed = this.sealedAdjustAmount[entry.entry_id];
            if (typed === undefined || typed === '') return;

            const amount = this.fromM(typed);

            /*
             * Checked here as well as on the server, which has always refused these — but it
             * refused them after the round trip, so an amount over a team's ceiling looked like
             * it had been accepted until the board failed to change. There was no limit on the
             * box at all.
             */
            const ceiling = Number(entry.binding_ceiling ?? 0);
            const floor = Number(this.sealed.floor ?? 0);

            if (ceiling > 0 && amount > ceiling) {
                this.toast(`${entry.team_name} cannot go above ${this.formatCurrency(ceiling)} on this player.`, 'error', 'Over the limit');
                return;
            }

            if (floor > 0 && amount < floor) {
                this.toast(`Bids in this round start at ${this.formatCurrency(floor)}.`, 'error', 'Below the floor');
                return;
            }

            // Entered in millions like every other money field on this screen. The figure
            // stays in the box afterwards — clearing it put the floor placeholder back and
            // left no sign of what had just been recorded.
            return this.sealedEntryCommand(entry.entry_id, 'adjust', { amount });
        },

        /**
         * Draw the lot.
         *
         * The winner comes from the server BEFORE the animation starts. Spinning first
         * and then revealing a locally-chosen name would be a fabricated draw, which is
         * worse than showing no animation at all — so a failed request shows the error
         * and does not animate.
         */
        async sealedDrawLot() {
            const tied = (this.sealed.entries || []).filter(e => (this.sealed.tied_team_ids || []).includes(e.team_id));

            const data = await this.sealedCommand('lot');
            if (!data?.handled) return;

            const winnerId = data.closed_bid?.winner_team_id;
            const entrants = tied.map(e => ({ id: e.team_id, name: e.team_name, image_path: e.team_logo || null }));
            const winner = entrants.find(e => e.id === winnerId) || entrants[0];

            if (winner) {
                // Long enough to read as a draw rather than a flicker. The winner is already
                // decided and recorded server-side, so the length of the spin changes only
                // what the room watches, never the result.
                await this._runShuffleAnimation(winner, entrants, { spinMs: this.LOT_SPIN_MS });
            }
            this._fireConfetti();
        },

        async sealedResolveManual(teamId) {
            if (!this.sealedManualReason.trim()) {
                /*
                 * Point at the box, do not just name the rule. The reason field is in a panel
                 * below the team rows, so a toast on its own left the organizer looking for
                 * something the message never mentioned.
                 */
                this.toast('An unexplained override cannot be defended later.', 'error', 'Reason required');
                this.sealedReasonFlash = true;
                setTimeout(() => { this.sealedReasonFlash = false; }, 1600);
                this.$refs.sealedManualReason?.focus();

                return;
            }
            const data = await this.sealedCommand('resolve-manual', { team_id: teamId, reason: this.sealedManualReason });
            if (data?.handled) {
                this.sealedManualReason = '';
                this._fireConfetti();
            }
        },

        async fetchSealedBids() {
            if (!this.currentPlayer) return;

            // Only in a sealed auction. This ran on every 2-second tick regardless of
            // bid_type, so a pure open-bid auction was making an extra round trip per tick,
            // per open panel, for a board that was not on screen. fetchSealedState() beside
            // it has always been guarded this way.
            if (this.bidType !== 'closed') return;

            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/sealed-bids?auction_player_id=${this.currentPlayer.id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.bids) this.sealedBids = data.bids;
            } catch (e) { console.error('Error fetching sealed bids:', e); }
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
            if (! await this.askConfirm(confirmMsg)) return;

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
            if (! await this.askConfirm(confirmMsg)) return;
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
        /*
         * Raise the price for one team by the configured increment.
         *
         * This is the auctioneer's gesture, and it is the ONLY one an offline room needs:
         * the organizer taps a logo, the ladder in AuctionAdminController::addBid() decides
         * the increment, and the squad-reserve ceiling is enforced there too. It used to
         * refuse outright when the mode was offline, which left every logo dead on the one
         * screen driving the room -- the standalone offline panel has always allowed it.
         */
        _isBidding: false,
        /**
         * Raises clicked while one is already in flight.
         *
         * Requests go out strictly one at a time — two concurrent posts would each read the
         * same current price and produce the same figure — but a click is queued rather than
         * discarded, so an auctioneer taking raises at the speed a room calls them loses none.
         */
        _bidQueue: [],

        /**
         * The auction's unsold pile, when it has anybody in it.
         *
         * Kept apart from `pools` for the same reason the server keeps it apart: that list is
         * "the pools of this auction" and is read by the next-pool suggestion, which must never
         * pick this up on its own. Running it is always a deliberate choice.
         */
        unsoldPool: null,

        async bidForTeam(teamId, stepIndex = null) {
            if (!this.currentPlayer || this.displayState !== 'bidding') return;
            if (this.currentPlayer?.current_bid_team_id == teamId) return;
            /*
             * And not while our own raise for that team is still unconfirmed.
             *
             * The guard above reads `current_bid_team_id`, which a poll answered before the
             * click can null out — so a second click on the same chip used to sail past it and
             * place a SECOND bid, moving the price two rungs with nothing on screen saying so.
             */
            if (this._pendingBid && this._pendingBid.teamId == teamId) return;
            // This one posts to /admin/auctions/add-bid rather than through sendCommand, so it
            // needs its own check — the endpoint refuses it too, this just says why.
            if (! this.guardControl('enter bids')) return;

            const team = this.teams.find(t => t.id == teamId);

            /*
             * Every refusal is decided BEFORE the lock is taken.
             *
             * The excluded-team check used to sit after `_isBidding = true` and return without
             * releasing it — so one tap on a priced-out team wedged the flag on and the panel
             * silently ignored every bid for the rest of the lot. Clicks looked like they were
             * doing nothing, which is exactly what it feels like from the operator's chair.
             */
            const blocked = this.teamBidBlockReason(team);

            if (blocked) {
                this.statusText = blocked;
                this.toast(blocked, 'info', team?.name || 'Team');

                return;
            }

            /*
             * A raise already in flight does not block this one — it queues behind it.
             *
             * Rejecting the click meant an auctioneer taking raises at the speed a room calls
             * them lost every second one, and the board sat unresponsive in between. Requests
             * still go out strictly one at a time, because two concurrent posts would each read
             * the same current price and produce the same figure; but the click is remembered
             * rather than discarded, and the optimistic update below makes the board move now.
             *
             * The double-tap this used to guard is handled by that optimistic update instead:
             * it sets the new leader synchronously, and a team that is already leading cannot
             * be bid for. Capped so a stuck finger cannot run the price away.
             */
            if (this._isBidding) {
                if (this._bidQueue.length < 4) {
                    this._bidQueue.push({ teamId, stepIndex });
                    this._applyOptimisticRaise(team, teamId, stepIndex);
                }

                return;
            }

            // An armed jump applies to this one bid, then disarms.
            if (stepIndex === null && this.armedStepIndex !== null) {
                stepIndex = this.armedStepIndex;
            }
            this.armedStepIndex = null;

            /*
             * Show the raise NOW, then reconcile with the server.
             *
             * The price and the leader used to change only once the round trip came back, so in
             * a hall the chip was pressed and nothing happened for as long as the request took.
             * The server remains the authority: its `current_price` overwrites this a moment
             * later, and a refusal puts back exactly what was on screen before.
             */
            this._applyOptimisticRaise(team, teamId, stepIndex);

            this._postBid(teamId, stepIndex);
        },

        /**
         * Send one raise, and reconcile the board with what the server says.
         *
         * Separate from bidForTeam because a queued click has already been validated and has
         * already moved the board — re-entering the front door would re-run the "that team
         * already leads" refusal against the optimistic state the click itself produced, and
         * quietly drop it.
         */
        async _postBid(teamId, stepIndex) {
            if (! this.currentPlayer) return;

            this._isBidding = true;

            /*
             * Snapshotted AFTER the optimistic update, because that is the state a failure has
             * to return to — not the state before whichever queued raise happened to be first.
             * The two-second poll reconciles anything this cannot.
             */
            const previous = {
                bid: this.currentBid,
                leader: this.winningTeamName,
                teamId: this.currentPlayer.current_bid_team_id,
            };

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
                    // The server's figure wins over the guess above.
                    this.currentBid = data.current_price;
                    this._lastKnownBid = data.current_price;

                    /*
                     * And its leader, stamped back onto the board.
                     *
                     * The success branch used to set the price and nothing else, so if a stale
                     * poll had already wiped the leader in the meantime the chip stayed
                     * un-selected even though the bid had gone through — and the next click,
                     * reading that same nulled field, placed a second bid.
                     *
                     * `bid_id` is monotonic, so recording it here also makes every earlier
                     * snapshot recognisable as stale, and makes this bid's own pushed frame the
                     * no-op it should be.
                     */
                    if (this.currentPlayer) {
                        this.currentPlayer.current_price = data.current_price;

                        if (data.current_bid_team_id !== undefined) {
                            this.currentPlayer.current_bid_team_id = data.current_bid_team_id;
                            const team = this.teams.find(t => t.id == data.current_bid_team_id);
                            if (team) this.winningTeamName = team.name;
                        }
                    }

                    this._lastAppliedBidId = Math.max(this._lastAppliedBidId || 0, Number(data.bid_id) || 0);
                } else {
                    this.currentBid = previous.bid;
                    this.winningTeamName = previous.leader;
                    this.currentPlayer.current_bid_team_id = previous.teamId;
                    this.toast(data.message || 'Bid failed', 'error');
                }
            } catch (e) {
                this.currentBid = previous.bid;
                this.winningTeamName = previous.leader;
                this.currentPlayer.current_bid_team_id = previous.teamId;
                console.error('Bid error:', e);
                this.toast('That bid did not go through.', 'error');
            } finally {
                // However it went, this raise is no longer waiting on an answer.
                this._clearPendingBid();
                this._isBidding = false;
                this._drainBidQueue();
            }
        },

        /**
         * Roll the displayed figure to a new one.
         *
         * Interruptible by construction: a second raise mid-roll cancels the frame in flight and
         * starts again from wherever the number had got to, so rapid bidding reads as one
         * continuous climb rather than a series of restarts from the old value.
         *
         * Instant when there is nothing to animate between — a new player arriving at their base
         * price should simply be at it, not count up from the last lot's final bid — and instant
         * for anyone who has asked their system for reduced motion.
         */
        countBidTo(target, { instant = false } = {}) {
            /*
             * Straight to the figure, with a fade — no counting up to it.
             *
             * This used to roll from the old amount to the new one over 320ms. On the screen
             * driving the room that means the number on the wall is a number nobody has bid for
             * a third of a second after every raise, and rolling digits change width as they
             * pass through 9s and 1s, so the panel twitched sideways on each one. The value is
             * right immediately now and only its opacity moves.
             */
            this.displayBid = Number(target) || 0;
        },

        /**
         * Show a raise before the server has confirmed it.
         *
         * Split out so a queued click can move the board at the moment it is clicked rather
         * than when its turn comes — which is the whole point of queueing rather than dropping.
         * The server's figure overwrites this either way.
         */
        _applyOptimisticRaise(team, teamId, stepIndex) {
            if (team) this.winningTeamName = team.name;
            if (this.currentPlayer) this.currentPlayer.current_bid_team_id = teamId;

            /*
             * Remember that this raise is ours and unconfirmed, so a poll answered before the
             * click cannot speak for the leader — see _snapshotPredatesLocalBid().
             *
             * The timeout is a backstop only: if the POST never answers at all, the board must
             * eventually go back to believing the server rather than holding a bid that may not
             * exist.
             */
            this._clearPendingBid();
            this._pendingBid = { teamId, amount: this.nextBidAmount };
            this._pendingBidTimeout = setTimeout(() => { this._pendingBid = null; }, 8000);

            // Only for an ordinary increment: a quick-bid jump has an amount this panel does
            // not compute, so it shows the new leader and leaves the figure to the response.
            if (stepIndex === null && this.nextBidAmount) {
                this.currentBid = this.nextBidAmount;

                /*
                 * And the player's own price, which everything else reads.
                 *
                 * This moved `currentBid` alone, so `currentPlayer.current_price` and its `bids`
                 * list stayed at whatever the last poll said — and with push healthy that poll is
                 * fifteen seconds apart. The Sell dialog builds its amount from those, so an
                 * organizer who had raised to 24M was offered a sale at 9M with the wall behind
                 * them reading 24M.
                 */
                if (this.currentPlayer) {
                    this.currentPlayer.current_price = this.nextBidAmount;
                }
            }

            this.resetBiddingTimer();
        },

        /**
         * Pools the auction could go back to.
         *
         * Anything not currently running that still has somebody in it — including pools that
         * were closed early (their uncalled players are in the unsold pile with a source_pool_id
         * pointing here) and pools taken out of play. `activatePool` refuses both, which is why
         * "no enabled pool has players left" used to be the end of the road with the only way on
         * being the pools admin screen in the middle of a live auction.
         *
         * `unsold_from` comes from the server, because only it can count a pile the client never
         * sees.
         */
        get reopenablePools() {
            const pools = (this.pools || []).filter(p => {
                if (this.activePool && p.id === this.activePool.id) return false;

                return (p.waiting || 0) > 0 || (p.unsold_from || 0) > 0;
            });

            /*
             * And the unsold pile itself, last.
             *
             * "One more round for everybody nobody took" is what an organizer reaches for near
             * the end, and it used to be refused outright — leaving the re-auction round, which
             * scatters those players back across their original pools, as the only way to offer
             * them again.
             */
            if (this.unsoldPool && (! this.activePool || this.activePool.id !== this.unsoldPool.id)) {
                pools.push(this.unsoldPool);
            }

            return pools;
        },

        /**
         * Pick a finished pool and run it again.
         *
         * Confirmed twice on purpose. This changes which pool the auction is serving, in front of
         * a room, and the second dialog is the one that says how many players are coming back —
         * a number the operator cannot see from the strip and would otherwise discover only
         * after the pool was already live.
         */
        /**
         * Reopen one named pool, straight from its button.
         *
         * The strip names each pool, so the "which one" dialog it used to open first is gone —
         * a dialog that only repeats what the button already said is a click, not a safeguard.
         * The safeguard is the confirmation below, which states what will happen and what will
         * NOT, and then asks a second time.
         */
        async reopenPool(pool) {
            if (! pool) return;

            const coming = (pool.waiting || 0) + (pool.unsold_from || 0);

            const detail = pool.is_unsold_pool
                ? `Run ${pool.name} now?\n\n`
                    + `\u2022 ${coming} player(s) nobody took go back on the block\n`
                    + `\u2022 They stay in the unsold list — nothing is moved\n`
                    + `\u2022 The pool running now stops`
                : `Reopen ${pool.name} and start it now?\n\n`
                    + `\u2022 ${coming} player(s) go back on the block\n`
                    + `\u2022 Its sales are KEPT — teams keep the players they bought\n`
                    + `\u2022 The pool running now stops`;

            if (! await this.askConfirm(detail, { title: pool.name, danger: true })) return;

            // Asked twice, because this changes which pool the auction is serving in front of
            // a room.
            if (! await this.askConfirm(`Start ${pool.name} now?`, { title: 'Confirm', danger: true })) return;

            const result = await this.sendCommand(`pools/${pool.id}/reopen`);

            if (result?.success) {
                this._clearForNextPlayer();
                this.statusText = result.message;
                this.toast(result.message, 'success', 'Pool reopened');
                await this.pollAuctionState();
            }
        },

        async choosePoolToReopen() {
            const choices = this.reopenablePools.map(p => ({
                value: String(p.id),
                label: `${p.name}`,
                hint: p.is_unsold_pool
                    ? `${p.waiting} player(s) nobody took — run them all again`
                    : [
                        (p.waiting || 0) > 0 ? `${p.waiting} still waiting` : null,
                        (p.unsold_from || 0) > 0 ? `${p.unsold_from} to bring back from unsold` : null,
                        p.status === 'completed' ? 'closed' : (p.is_enabled ? null : 'disabled'),
                    ].filter(Boolean).join(' · '),
                class: 'bg-indigo-600 hover:bg-indigo-700',
            }));

            if (! choices.length) return;

            const answer = await this.askConfirm('Which pool should be reopened?', {
                title: 'Reopen a pool',
                choices,
            });

            if (! answer) return;

            const pool = this.reopenablePools.find(p => String(p.id) === String(answer));

            if (! pool) return;

            const coming = (pool.waiting || 0) + (pool.unsold_from || 0);

            /*
             * The second confirmation. Says what will actually happen, and what will NOT —
             * "sales are kept" is the distinction between this and Restart, and getting the two
             * confused costs a team its squad.
             */
            if (! await this.askConfirm(
                `Reopen ${pool.name} and start it now?\n\n`
                + `\u2022 ${coming} player(s) go back on the block\n`
                + `\u2022 Its sales are KEPT — teams keep the players they bought\n`
                + `\u2022 The pool running now stops`,
                { title: `Reopen ${pool.name}`, danger: true }
            )) return;

            const result = await this.sendCommand(`pools/${pool.id}/reopen`);

            if (result?.success) {
                this.statusText = result.message;
                this.toast(result.message, 'success', 'Pool reopened');
                this.displayState = 'waiting';
                this.currentPlayer = null;
                this._lastCurrentPlayerId = null;
                await this.pollAuctionState();
            }
        },

        /** Send the next queued raise, now that the previous one has settled. */
        _drainBidQueue() {
            const next = this._bidQueue.shift();

            if (! next) return;

            /*
             * `_postBid` rather than `bidForTeam`: the click has already been validated and
             * already moved the board. Re-entering the front door would re-run the
             * "that team already leads" refusal against the optimistic state this very click
             * produced, and silently drop itself.
             */
            this._postBid(next.teamId, next.stepIndex);
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
            /*
             * Drop anyone who can no longer act on THIS player before opening the round.
             *
             * The selection is now kept across a whole pool, so a team that was in the room
             * for the last player may since have filled its squad or spent down past the
             * squad-reserve ceiling. Carrying them in would open a bid row for a team whose
             * bid the server refuses.
             */
            const blocked = new Set((this.teams || []).filter(t => t.excluded).map(t => t.id));
            const dropped = this.offlineParticipants.filter(id => blocked.has(id));

            if (dropped.length) {
                this.offlineParticipants = this.offlineParticipants.filter(id => !blocked.has(id));
                dropped.forEach(id => delete this.offlineTeamBids[id]);
                const names = dropped.map(id => this.getTeamById(id)?.name || 'A team').join(', ');
                this.toast(`${names} cannot bid on this player and was left out.`, 'info', 'Teams updated');
            }

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
            if (! await this.askConfirm(`Sell ${this.currentPlayer?.player?.name} to ${teamName} for ${this.formatCurrency(amount)}?`, { title: 'Confirm sale' })) return;
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

        /*
         * Between players in the same pool, KEEP who is in the room.
         *
         * This used to clear offlineParticipants after every sale, so the organizer
         * re-ticked the same teams for every single player — fourteen times through a pool
         * of fourteen, with the room waiting. The teams sitting at the table do not change
         * between one player and the next; the pool is what changes them.
         *
         * The amounts are cleared, because those are per player and carrying one over would
         * silently open the next player at the last one's price.
         */
        resetOfflinePanel() {
            this.offlinePhase = 'selection';
            this.offlineTeamBids = {};
            this.offlineHighestBidder = null;
            this.offlineHighestAmount = 0;
        },

        /** Which pool the current selection was made for. */
        _participantsPoolId: null,

        /**
         * Drop the selection when the pool changes.
         *
         * A new pool is a new set of players and usually a different set of teams still
         * needing them, so carrying a selection across that boundary would quietly bid on
         * behalf of teams nobody chose. Called from the poll, which is the only thing that
         * knows the active pool has moved.
         */
        syncParticipantsToPool() {
            const poolId = this.activePool?.id ?? null;

            if (this._participantsPoolId === poolId) return;

            this._participantsPoolId = poolId;
            this.offlineParticipants = [];
            this.offlineTeamBids = {};
        },

        /*
         * Only teams that can still take part.
         *
         * The server already withholds unapproved registrations (see
         * AuctionOrganizerController::teamsWithPurse), so what is left to filter here is a
         * team that cannot act on THIS player — no purse left under the squad-reserve rule,
         * or a squad already full. Offering them in the picker invites a bid the server
         * would refuse.
         */
        get selectableTeams() {
            return (this.teams || []).filter(t => !t.excluded);
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
                if (! await this.askConfirm('Pass current player and load next?', { title: 'Pass player' })) return;
                await this.sendCommand('pass-player', { auction_player_id: this.currentPlayer.id });
                this.currentPlayer = null;
                this.currentBid = 0;
                this.winningTeamName = 'No Bids';
            }

            /*
             * Cover the screen BEFORE the first await.
             *
             * This used to set displayState = 'waiting' here and then await a poll — so for the
             * whole round trip the never-started "Ready to Auction" screen was on the panel,
             * flashing between the last result and the next player. The overlay goes up first
             * and _runShuffleAnimation() takes it from there; the previous result stays
             * underneath it rather than being replaced by an empty state.
             */
            this.shufflePhase = 'spinning';
            this.shuffleSelectedPlayer = null;
            this.shuffleDisplayName = '';
            this.showShuffleOverlay = true;

            await this.pollAuctionState();
            if (this.availablePlayers.length === 0) {
                // Nothing to show: put the overlay back down, or the panel sits behind a
                // spinner that will never land.
                this.showShuffleOverlay = false;
                if (this.displayState === 'sold' || this.displayState === 'unsold') {
                    this.displayState = 'waiting';
                }
                this.toast('No more players waiting.', 'info');
                return;
            }

            // Next player in drawn lot order — the server returns availablePlayers
            // sorted by pool sequence then lot number, scoped to the active pool.
            // This used to pick at random, discarding the draw entirely.
            const chosenPlayer = this.availablePlayers[0];

            try {
                // The animation is theatre; the player is already decided. The cover stays up
                // past the reveal — see keepOverlay — so nothing shows between landing on the
                // name and the player actually being live.
                await this._runShuffleAnimation(chosenPlayer, null, { keepOverlay: true });

                this.selectedPlayerId = chosenPlayer.id;
                await this.putPlayerOnBid();

                /*
                 * Hold the cover until the panel is genuinely showing this player.
                 *
                 * putPlayerOnBid()'s own poll can be swallowed — pollAuctionState() refuses to
                 * run while another is in flight, and a background poll can easily own the slot
                 * across a four-second animation. Without this wait the overlay dropped onto an
                 * empty state and stayed there until the next chained poll two seconds later.
                 * Bounded, so a genuine failure costs a moment rather than the screen.
                 */
                await this._waitForPlayerLive(chosenPlayer.id);
            } finally {
                // In a finally: a refused player, a dropped request or a thrown poll must never
                // leave the panel behind a spinner that will not land.
                this.showShuffleOverlay = false;
            }
        },

        /**
         * Wait for the poll to report a given player as the one on the block.
         *
         * Polls rather than trusting the POST, because the endpoint answers only success/message
         * — the panel learns who is live from pollAuctionState(), and that is also what sets
         * displayState. Gives up after ~3s so a failure surfaces as the ordinary empty state
         * instead of a permanent cover.
         */
        async _waitForPlayerLive(auctionPlayerId, timeoutMs = 3000) {
            const deadline = Date.now() + timeoutMs;

            while (Date.now() < deadline) {
                if (this.currentPlayer?.id === auctionPlayerId && this.displayState === 'bidding') {
                    return true;
                }

                await new Promise((r) => setTimeout(r, 150));
                await this.pollAuctionState();
            }

            return false;
        },

        /**
         * Spin, then land on a result the caller already holds.
         *
         * `pool` lets a lot draw cycle the tied TEAMS instead of the player queue.
         * Entrants are normalised to {id, name, image_path} at the call site so the
         * overlay markup does not have to know which it is showing.
         */
        /**
         * @param {object} chosenPlayer  The result, already decided by the server.
         * @param {Array|null} pool      Entrants to cycle; the player queue when omitted.
         * @param {object} opts          spinMs — how long to cycle before landing.
         *
         * A lot draw in front of a hall needs to look like a draw, so it passes a much
         * longer spinMs than picking the next player does. The cycle is driven by elapsed
         * time rather than a tick count, so the length is stated in seconds instead of
         * being an emergent property of an interval times a magic number, and the last few
         * seconds stretch out so the final names land one at a time.
         */
        _runShuffleAnimation(chosenPlayer, pool = null, opts = {}) {
            return new Promise((resolve) => {
                const spinMs = Math.max(600, opts.spinMs ?? 2800);
                const holdMs = opts.holdMs ?? 1500;
                /*
                 * `keepOverlay` leaves the cover up when the reveal ends.
                 *
                 * The animation used to lower it the moment it landed on a name — but the player
                 * is not on the block until the POST that follows has been applied, so for that
                 * gap the panel fell back to its empty state: a default screen appearing right
                 * after the selection, which is the flash being reported. The caller that knows
                 * when the player is genuinely live lowers it instead.
                 */
                const release = () => {
                    if (! opts.keepOverlay) this.showShuffleOverlay = false;
                    resolve();
                };

                /* Idempotent on the flag: loadNextPlayer() raises the overlay before its first
                   await so no empty state can flash, and a lot draw calls in with it down. */
                this.shufflePhase = 'spinning';
                this.showShuffleOverlay = true;
                this.shuffleSelectedPlayer = null;
                this.shuffleDisplayName = '';

                const players = pool || this.availablePlayers;
                if (players.length <= 1) {
                    this.shuffleSelectedPlayer = chosenPlayer;
                    this.shufflePhase = 'reveal';
                    setTimeout(release, 1200);
                    return;
                }

                const nameOf = (entrant) => entrant.name || 'Player ' + entrant.id;
                const started = Date.now();
                let lastIdx = -1;

                const land = () => {
                    this.shuffleDisplayName = nameOf(chosenPlayer);
                    setTimeout(() => {
                        this.shuffleSelectedPlayer = chosenPlayer;
                        this.shufflePhase = 'reveal';
                        setTimeout(release, holdMs);
                    }, 300);
                };

                const step = () => {
                    const elapsed = Date.now() - started;

                    if (elapsed >= spinMs) {
                        this._shuffleTimeout = null;
                        land();
                        return;
                    }

                    // Never the same name twice running: over a long spin a repeat reads as
                    // the animation having frozen.
                    let idx = Math.floor(Math.random() * players.length);
                    if (idx === lastIdx) idx = (idx + 1) % players.length;
                    lastIdx = idx;
                    this.shuffleDisplayName = nameOf(players[idx]);

                    // Steady flicker, easing out over the closing seconds.
                    const remaining = spinMs - elapsed;
                    const easeWindow = Math.min(3000, spinMs / 2);
                    const delay = remaining > easeWindow
                        ? 80
                        : 80 + ((easeWindow - remaining) / easeWindow) * 340;

                    this._shuffleTimeout = setTimeout(step, delay);
                };

                step();
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
                this.toast('Finish the current player first.', 'error');
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
        /** Teams the squad-reserve rule has priced out of the player on the block. */
        get excludedTeams() {
            if (!this.currentPlayer || this.displayState !== 'bidding') return [];

            return (this.teams || []).filter(t => t.excluded && t.exclusion_reason);
        },

        /* ── A team's roster, loaded when asked for ── */
        openSquad: null,
        squadPlayers: [],
        squadLoading: false,

        async toggleSquad(teamId) {
            if (this.openSquad === teamId) { this.openSquad = null; return; }

            this.openSquad = teamId;
            this.squadPlayers = [];
            this.squadLoading = true;

            try {
                const res = await fetch(`/admin/organizer/auction/${this.auctionId}/api/team/${teamId}/squad`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();
                // Ignore a reply for a team the organizer has already navigated away from.
                if (this.openSquad === teamId) this.squadPlayers = data.players || [];
            } catch (e) {
                this.toast('Could not load that squad.', 'error');
            } finally {
                this.squadLoading = false;
            }
        },

        /*
         * Which bidding stage is on screen.
         *
         * Offline used to REPLACE the live auctioneer view with a three-step form (tick the
         * participating teams, type each one's amount, end bidding). That form is kept --
         * some rooms are run that way -- but it is no longer the only option, so an offline
         * auction defaults to the same live stage as an online one and the organizer bids by
         * tapping logos. One predicate for both gates so they cannot drift apart and show
         * two stages at once, or none, on the screen driving the room.
         */
        offlineStageMode: 'live',

        get showLiveStage() {
            return this.openBidMode !== 'offline' || this.offlineStageMode === 'live';
        },

        /**
         * Whether this team could afford the raise being offered RIGHT NOW.
         *
         * `team.excluded` is computed by the server for the next-bid amount as it stood when the
         * poll was answered — and with push healthy that poll is fifteen seconds apart. So after
         * a raise, a team that can no longer reach the new amount stayed clickable and looking
         * available until the next tick, and the refusal arrived from the server after the
         * click. `max_bid_allowed` is already in the payload, so the same question can be
         * answered against the current amount without waiting to be told.
         *
         * Returns a reason rather than a boolean, so the tooltip, the disabled state and the
         * refusal toast all say the same thing.
         */
        teamBidBlockReason(team) {
            if (! team) return null;

            if (team.squad_full) return `${team.name}'s squad is full.`;

            /*
             * At the sealed threshold there is no further open bid, so the click had nothing to
             * do — but it still posted, waited for the round trip and came back refused, which
             * from the operator's chair is a chip that does nothing. The server sends the
             * ceiling with the rest of the bid state, so the answer is already here.
             */
            if (! this.nextBidAmount && this.openBidCeiling) {
                return `Open bidding stops at ${this.formatCurrency(this.openBidCeiling)}. Move to a sealed bid, sell to the leading team, or keep open bidding.`;
            }

            if (team.excluded) return team.exclusion_reason || `${team.name} cannot bid on this player.`;

            const amount = Number(this.nextBidAmount) || 0;
            const ceiling = Number(team.max_bid_allowed);

            if (amount > 0 && isFinite(ceiling) && amount > ceiling) {
                return `${team.name} cannot reach ${this.formatCurrency(amount)} — ${this.formatCurrency(ceiling)} is their limit for this player.`;
            }

            return null;
        },

        isTeamBidDisabled(team) {
            // No offline clause: the organizer bidding for a team IS how an offline room
            // works. Everything left here is mode-independent -- no player up, the wrong
            // display state, the team already leading, or the team being unable to bid.
            return !this.currentPlayer
                || this.displayState !== 'bidding'
                || this.currentPlayer?.current_bid_team_id == team.id
                || !! this.teamBidBlockReason(team);
            /*
             * NOT disabled while a bid is in flight.
             *
             * The whole strip used to go inert for the round trip, so an auctioneer taking
             * raises at the speed a room actually calls them found the board unresponsive
             * between every pair of clicks, and the second click was dropped rather than
             * queued. That is the lag being reported.
             *
             * The double-tap it was guarding against is already impossible: `bidForTeam` applies
             * the new leader optimistically before it posts, so the clause directly above —
             * a team that is already leading cannot be bid for — refuses the second tap on the
             * same chip immediately. A tap on a DIFFERENT chip is a real next bid, and now
             * queues behind the one in flight instead of being thrown away.
             */
        },

        teamTooltip(team) {
            // The same reason the chip is disabled for, so hovering explains the grey.
            const blocked = this.teamBidBlockReason(team);

            if (blocked) {
                return blocked;
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
                this.toast(`No waiting player with id ${id} in this queue.`, 'error');
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

            // Which pool this is happening in. With several pools in an evening, "undo the last
            // action" is a different sentence depending on where the auction currently is.
            const pool = this.activePool?.name ? `\n\nPool: ${this.activePool.name}` : '';
            const label = this.nextUndoLabel ? `\n\nWill undo: ${this.nextUndoLabel}` : '';
            /* What ELSE the click does — the price it falls back to, and a sealed round that
               goes with it. Worked out server-side from the state as it is now, because the
               log's own description was written when the action happened and cannot know. */
            const notes = (this.nextUndoNotes || []).length
                ? '\n\n' + this.nextUndoNotes.map(n => `\u2022 ${n}`).join('\n')
                : '';
            if (! await this.askConfirm(`Undo the last action?${pool}${label}${notes}`, { title: 'Undo', danger: true })) return;

            this.isUndoing = true;
            try {
                const result = await this.sendCommand('undo');
                if (result?.success) {
                    this.statusText = result.message;
                    this.toast(result.message, 'success', 'Undone');
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

        /**
         * One place every mutating call has to pass.
         *
         * Gating ~30 buttons individually is a list that goes stale the first time somebody
         * adds a thirty-first. The three transports below (sendCommand, sealedCommand and the
         * direct add-bid fetch) all check here instead, so a read-only seat cannot act however
         * it got at the control — including through the console. The routes refuse it anyway;
         * this turns a 403 into a sentence.
         */
        guardControl(what = 'change the auction') {
            if (this.canControl) return true;
            this.toast(`You are watching this auction, not running it — you cannot ${what}.`, 'info', 'Read only');
            return false;
        },

        /**
         * What the one blue control does next: START a run, or bring the NEXT player.
         *
         * Driven by the auction's status rather than by `currentPlayer`, which is null in the
         * gap between players — so the button used to revert to START after every single sale,
         * on an auction that was well under way. The empty state's keyboard hint reads the same
         * getter, so the two cannot describe the same key differently.
         */
        get nextActionLabel() {
            return (this.auctionStatus === 'running' || this.auctionStatus === 'paused')
                ? 'NEXT'
                : 'START';
        },

        // API Calls
        async sendCommand(endpoint, body = {}) {
            try {
                const readOnly = ['sealed-bids', 'all-players', 'action-log'].includes(endpoint);

                if (! readOnly && ! this.guardControl()) return null;

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
                this.toast(error.message, 'error', 'Something went wrong');
                return null;
            }
        },

        /**
         * Open the auction to the room.
         *
         * Resuming is NOT this, and used to run through it because one button covered both
         * `scheduled` and `paused`. Two things went wrong on every resume:
         *
         *  - The server's start path calls stopTimer(), which clears the clock outright.
         *    That is right when starting — nobody is on the block yet — and wrong when
         *    resuming, where the player who was up is owed exactly the time that was left.
         *    resumeTimer(), on the pause endpoint, is what gives it back.
         *  - It forced displayState to 'waiting', so resuming dropped the live player off
         *    the panel and showed Ready to Auction as though nobody were up.
         */
        async startAuction() {
            if (this.auctionStatus === 'paused') return this.resumeAuction();

            // Opens the auction to the room. Ending it already asks; starting it did not.
            if (! await this.askConfirm('Start the auction now?', { title: 'Start auction' })) return;

            const result = await this.sendCommand('start');
            if (result) {
                this.auctionStatus = 'running';
                this.statusText = 'Auction started! Select first player.';
                this.displayState = 'waiting';
            }
        },

        /**
         * Pick the room back up where it was left.
         *
         * Goes through the same endpoint as Pause, so the clock is released by resumeTimer()
         * and the player on the block keeps the seconds they had. Nothing about the display
         * is assumed here — the poll that follows reports what is actually on the block.
         */
        async resumeAuction() {
            const result = await this.sendCommand('toggle-pause');
            if (result) {
                this.auctionStatus = 'running';
                this.statusText = 'Auction resumed.';
                await this.pollAuctionState();
            }
        },

        async endAuction() {
            if (! await this.askConfirm('End the auction now?', { title: 'End auction', danger: true })) return;
            const result = await this.sendCommand('end');
            if (result) {
                this.auctionStatus = 'completed';
            }
        },

        /**
         * Restart, once the auction has ended.
         *
         * Ending it used to leave exactly one way back: `restartAuction`, which resets every
         * player and every bid in the whole auction. That is almost never what a finished
         * auction needs — the usual reason to come back is one pool that wants running again,
         * or a pool that was closed early and should now be revisited. Nuking three finished
         * pools to redo the fourth is not a recovery, it is a second auction.
         *
         * So: pick the pool. The whole-auction reset is still here, last and marked as such,
         * because sometimes that genuinely is the answer.
         */
        async restartAfterEnd() {
            // `pools` in the poll payload is already biddable()-scoped, so the unsold pile is
            // not in it — there is nothing to filter out here, and pretending otherwise would
            // suggest the payload carries a flag it does not.
            const pools = this.pools || [];

            if (! pools.length) {
                // Nothing to choose between — the old behaviour is the only behaviour.
                return this.restartAuction();
            }

            const choices = [
                /*
                 * Reopening comes FIRST, because it is almost always what is wanted: carry on
                 * with a pool that still has players, keeping every sale already made. Restart
                 * is the destructive cousin and sits below it.
                 */
                ...pools
                    .filter(p => (p.waiting || 0) > 0 || (p.unsold_from || 0) > 0)
                    .map(p => ({
                        value: `reopen:${p.id}`,
                        label: `Carry on with ${p.name}`,
                        hint: `${(p.waiting || 0) + (p.unsold_from || 0)} player(s) back on the block — its sales are kept.`,
                        class: 'bg-emerald-600 hover:bg-emerald-700',
                    })),
                ...pools.map(p => ({
                    value: `pool:${p.id}`,
                    label: `Run ${p.name} again from scratch`,
                    hint: `${p.sold ?? 0} sold of ${p.total ?? 0} — its sales are undone and the teams get their purse back.`,
                    class: 'bg-indigo-600 hover:bg-indigo-700',
                })),
                {
                    value: 'all',
                    label: 'Reset the entire auction',
                    hint: 'Every player and every bid in every pool. There is no undo for this.',
                    class: 'bg-red-600 hover:bg-red-700',
                },
            ];

            const answer = await this.askConfirm(
                'The auction has ended. What happens next?',
                { title: 'Next', danger: true, choices }
            );

            if (! answer) return;

            if (answer === 'all') {
                return this.restartAuction();
            }

            // Carry on with a pool: the same reopen path the strip uses mid-auction, so an ended
            // auction and a running one behave identically once a pool is chosen.
            if (String(answer).startsWith('reopen:')) {
                const pool = pools.find(p => String(p.id) === String(answer).split(':')[1]);

                if (! pool) return;

                const result = await this.sendCommand(`pools/${pool.id}/reopen`);

                if (result?.success) {
                    this._clearForNextPlayer();
                    this.statusText = result.message;
                    this.toast(result.message, 'success', 'Pool reopened');
                    await this.pollAuctionState();
                }

                return;
            }

            const poolId = Number(String(answer).split(':')[1]);

            if (! poolId) return;

            /*
             * No resume first: restartPool already accepts a `completed` auction and sets it
             * running itself, and it activates the pool in the same transaction. Doing either
             * here as well would be a second opinion about state the server has just settled.
             */
            const result = await this.sendCommand(`pools/${poolId}/restart`);

            if (! result?.success) return;

            // What the panel is holding belongs to the run just wiped.
            this._clearForNextPlayer();

            this.statusText = result.message;
            this.toast(result.message, 'success', 'Pool restarted');

            await this.pollAuctionState();
        },

        async restartAuction() {
            const isRunning = this.auctionStatus === 'running' || this.auctionStatus === 'paused';
            const msg = isRunning
                ? 'WARNING: This will reset ALL players and bids! The auction is still in progress. Are you sure you want to restart from scratch?'
                : 'Are you sure you want to restart this auction? All players and bids will be reset.';
            if (! await this.askConfirm(msg, { title: 'Restart auction', danger: true })) return;
            const result = await this.sendCommand('restart');
            if (result && result.success) {
                this.auctionStatus = 'running';
                this.displayState = 'restarting';
                this.currentPlayer = null;
                this.stopBiddingTimer();

                /* Clear the "who was on the block" pointer too. Leaving it set made the
                   very next poll see a player that had vanished without a sale and stamp
                   UNSOLD across a freshly reset auction. */
                this._lastCurrentPlayerId = null;
                this._lastKnownBid = 0;
                this.lastSoldPlayer = null;
                this.currentBid = 0;
                this.winningTeamName = 'No Bids';
                this.sealedBids = [];
                // Shape, not null — the markup reads sealed.active unguarded.
                this.sealed = { active: false };

                this.statusText = 'Auction restarted — all players reset.';
                this.toast('All players and bids were reset.', 'success', 'Auction restarted');
                await this.pollAuctionState();
            }
        },

        /**
         * Pause. Only ever reached from the running state — the button is hidden otherwise,
         * and Resume has its own path.
         *
         * Set outright rather than flipped off whatever the panel last believed: a stale
         * status flipped the wrong way and left the controls showing the opposite of what
         * the server had. The poll then re-reads it from the server either way.
         */
        async togglePause() {
            const result = await this.sendCommand('toggle-pause');
            if (result) {
                this.auctionStatus = 'paused';
                this.stopBiddingTimer();
                await this.pollAuctionState();
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
        /*
         * "The bidding has reached the sealed threshold — what now?"
         *
         * Crossing 8M used to swing the whole room into a sealed round on its own, with no
         * way back: the organizer had lost the option of just selling to the team already
         * leading, and a threshold set slightly too low turned ordinary sales into sealed
         * rounds. The server now reports the crossing instead of acting on it, and this is
         * where it is put to the organizer.
         *
         * Asked once per player. Dismissing means "not yet" and open bidding carries on, so
         * the question does not reappear on the next 2-second poll and interrupt a live
         * room; it comes back when the next player reaches the threshold.
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
            const amount = this.formatCurrency(data.sealed_threshold_amount ?? this.currentPlayer?.current_price ?? 0);
            const threshold = this.formatCurrency(data.closed_bid_starts_at ?? 0);
            const leader = data.sealed_threshold_leader;

            let answer = false;
            try {
                answer = await this.askConfirm(
                    `${name} has reached ${amount}, the sealed-bid threshold of ${threshold}.` +
                    (leader ? `\n\nThe leading team is ${leader}.` : '\n\nNo team is currently leading.'),
                    {
                        title: 'Sealed bid threshold',
                        choices: [
                            {
                                value: 'sealed',
                                label: 'Move to sealed bid',
                                hint: 'Teams submit written amounts. Highest wins.',
                                class: 'bg-indigo-600 hover:bg-indigo-700',
                            },
                            // Only offered when there IS somebody to sell to. With no leader
                            // this button would sell the player to nobody.
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
                const result = await this.sendCommand('closed-bid/confirm-threshold', {
                    auction_player_id: playerId,
                });
                // sendCommand() returns null on a non-2xx (it has already raised its own
                // toast), so null is a failure too — not "no opinion".
                if (! result || result.success === false) {
                    // Let them be asked again — nothing happened.
                    this._sealedPromptAskedFor = null;
                    if (result) this.toast(result.message || 'Could not open the sealed round.', 'error', 'Sealed round');
                    return;
                }
                if (result?.closed_bid) this.sealed = result.closed_bid;
                this.toast('Sealed round opened.', 'success', 'Sealed round');
                await this.pollAuctionState();
                return;
            }

            if (answer === 'sell') {
                /*
                 * A second, explicit confirmation before the sale actually happens.
                 *
                 * Choosing the button used to be treated as confirmation enough. A sale is
                 * the one action here that cannot be walked back by pressing the button
                 * again — undoing it is possible but does real work — so it gets the same
                 * two-step confirmation every other sale on this panel already has, rather
                 * than resolving on the first click a mis-tap could also have produced.
                 */
                if (! await this.askConfirm(
                    `Sell ${name} to ${leader} for ${amount}?`,
                    { title: 'Confirm sale' }
                )) {
                    this._sealedPromptAskedFor = null;
                    return;
                }

                const result = await this.sendCommand('sell-player', { auction_player_id: playerId });
                if (result && result.success !== false) {
                    this._fireConfetti();
                    await this.pollAuctionState();
                }
                return;
            }

            if (answer === 'keep') {
                /*
                 * Tell the server, or a refresh asks again immediately — and again on every
                 * raise after that, because the suppression only ever lived in this tab.
                 * From here the phase is the organizer's: the Closed button starts a sealed
                 * round when they want one.
                 */
                await this.sendCommand('closed-bid/confirm-threshold', {
                    auction_player_id: playerId,
                    decision: 'keep',
                });
                await this.pollAuctionState();
                return;
            }

            // Dismissed without choosing. Left un-recorded on purpose: closing a dialog is
            // not a decision, so the question stands and returns on the next player.
        },

        async sellPlayer() {
            if (!this.currentPlayer) return;

            const highestBid = this.currentPlayer.bids?.length
                ? this.currentPlayer.bids.reduce((a, b) => (a.amount > b.amount ? a : b), this.currentPlayer.bids[0])
                : null;

            /*
             * The highest of everything the panel knows — never below the figure on screen.
             *
             * `bids` and `current_price` both come from the last poll, so between polls they lag
             * the raises already taken; `currentBid` is what the room has actually been shown.
             * Taking the maximum means the dialog cannot offer a sale for less than the standing
             * bid, whichever of the three is freshest.
             */
            const saleAmount = Math.max(
                Number(this.currentBid) || 0,
                Number(highestBid?.amount) || 0,
                Number(this.currentPlayer.current_price) || 0,
            ) || Number(this.currentPlayer.base_price) || 0;

            const leadingTeamId = this.currentPlayer.current_bid_team_id || highestBid?.team_id || null;
            const isOpenLive = this.bidType === 'open' && this.openBidMode !== 'offline';

            // Nobody bid — this is a PASS, not a sale.
            if (!leadingTeamId) {
                const name = this.currentPlayer.player?.name || 'this player';
                if (! await this.askConfirm(`No bids for ${name}. Mark them unsold and set them aside for final allotment?`, { title: 'No bids', danger: true })) return;
                await this.passPlayer();
                return;
            }

            // Open bidding: award the leading bidder directly.
            if (isOpenLive) {
                const team = this.getTeamById(leadingTeamId);
                const amount = saleAmount;
                if (! await this.askConfirm(`Sell ${this.currentPlayer.player?.name} to ${team?.name || 'the leading team'} for ${this.formatCurrency(amount)}?`, { title: 'Confirm sale' })) return;

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
                amount: saleAmount,
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
            // Names the player and the pool. In an evening of four pools, "this player" is
            // whoever the operator believes is up, and that is exactly what a re-bid is for
            // when they are wrong.
            const who = this.currentPlayer.player?.name ? ` for ${this.currentPlayer.player.name}` : '';
            const pool = this.activePool?.name ? `\n\nPool: ${this.activePool.name}` : '';

            if (! await this.askConfirm(
                `Reset the bids${who} and start fresh? All current bids will be cleared.${pool}`,
                { title: 'Re-bid', danger: true }
            )) return;
            const result = await this.sendCommand('re-bid-player', { auction_player_id: this.currentPlayer.id });
            if (result && result.success) {
                this.statusText = 'Player re-bid started!';
                this.winningTeamName = 'No Bids';
                await this.pollAuctionState();
            }
        },

        async reAuctionPlayer(player) {
            if (! await this.askConfirm(`Re-auction ${player.name}? They go back on the block at base price.`, { title: 'Re-auction' })) return;
            const result = await this.sendCommand('re-auction-player', { auction_player_id: player.id });
            if (result && result.success) {
                this.statusText = `${player.name} is back on auction!`;
                await this.pollAuctionState();
            }
        },

        // ── KEYBOARD SHORTCUTS ──
        handleKeydown(e) {
            /* A confirmation is modal, so it must swallow the shortcuts. Native confirm()
               froze the whole page for free; this one does not, and S / N / F would
               otherwise sell, skip or unfullscreen behind an open dialog. */
            if (this.confirmBox.open) {
                e.preventDefault();
                /* Enter confirms a yes/no question only. With three answers on screen there
                   is no "the" confirm, and guessing one would sell a player nobody chose to
                   sell. Escape now matches: a multi-choice dialog has no "Cancel" among its
                   answers, so Escape must not stand in for one either — the organizer has to
                   press an actual button, same as the backdrop click just above. */
                if (e.key === 'Enter' && ! this.confirmBox.choices) this._settleConfirm(true);
                if (e.key === 'Escape' && ! this.confirmBox.choices) this._settleConfirm(false);
                return;
            }

            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

            const key = e.key.toUpperCase();

            if (e.key === 'Escape') {
                if (this.sidePanel) { this.sidePanel = null; return; }
                if (this.showSellModal) { this.showSellModal = false; return; }
                return;
            }

            if (key === 'F' && !e.ctrlKey && !e.metaKey) { e.preventDefault(); this.toggleFullscreen(); return; }
            if (key === 'N') { e.preventDefault(); this.loadNextPlayer(); return; }
            // Matches the button, pause included — a greyed SELL that the shortcut still
            // fires is not a block.
            if (key === 'S' && !e.ctrlKey && !e.metaKey && this.currentPlayer
                && this.displayState === 'bidding' && this.auctionStatus !== 'paused') {
                e.preventDefault(); this.sellPlayer(); return;
            }
            // Carries the pause condition too, like S above.
            if (key === 'P' && this.currentPlayer && this.displayState === 'bidding'
                && !this.currentPlayer?.current_bid_team_id && this.auctionStatus !== 'paused') {
                e.preventDefault(); this.passPlayer(); return;
            }
            // Undo the last action. Ctrl/Cmd+Z works too, for muscle memory. Both carry the
            // pause condition, like S and P — the shortcut must not do what the greyed
            // button refuses.
            if ((key === 'U' || ((e.ctrlKey || e.metaKey) && key === 'Z')) && this.auctionStatus !== 'paused') {
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

        /*
         * Starting a pool commits the room to a queue: while one is running the panel will
         * only offer players from it, so starting the wrong one mid-auction means closing
         * it again and leaving whoever it had left unsold. Closing a pool has always asked;
         * opening one — the decision that causes it — did not.
         *
         * Named with its size, because "Pool A" and "Pool B" are one key apart on the
         * screen and the player count is what tells them apart at a glance.
         */
        async activatePool(poolId) {
            const pool = (this.pools || []).find(p => p.id == poolId);

            if (pool) {
                const left = pool.waiting ?? 0;
                if (! await this.askConfirm(
                    `Start ${pool.name}?\n\n${left} player${left === 1 ? '' : 's'} will be auctioned from this pool, and only this pool, until it is closed.`,
                    { title: 'Start pool' }
                )) return;
            }

            const result = await this.sendCommand(`pools/${poolId}/activate`);
            if (result?.success) {
                this.statusText = result.message;
                this.toast(result.message, 'success', 'Pool started');
                await this.pollAuctionState();
            }
        },

        async completeActivePool() {
            if (!this.activePool) return;

            // Closing early leaves players in the pool unsold, so say so.
            if (!this.activePool.exhausted) {
                const left = this.activePool.waiting;
                if (! await this.askConfirm(`Close ${this.activePool.name} now? ${left} player(s) still in it will be left unsold.`, { title: 'Close pool', danger: true })) return;
            }

            const result = await this.sendCommand(`pools/${this.activePool.id}/complete`);
            if (result?.success) {
                this.statusText = result.message;
                this.toast(result.message, 'success', 'Pool closed');
                await this.pollAuctionState();
            }
        },

        /**
         * Run the current pool again, and only it.
         *
         * Its sales are undone and the teams get their purse back, which is what separates
         * this from a second pass over whoever went unsold. Pools before and after it keep
         * their results, their bids and their undo history.
         */
        async restartActivePool() {
            if (!this.activePool) return;

            const pool = this.activePool;
            const sold = pool.sold || 0;

            /*
             * A player still on the block normally has to be finished first — but sometimes
             * cannot be. A player whose clock has run out, or who was left up by an undo, has
             * no way off: Sell needs a bid, Pass refuses a player who has one, and the timer
             * will not expire twice. That made the pool unrestartable with nothing left to
             * press, so this offers the forced reset instead of refusing.
             *
             * Decided here rather than from the server's 422, because sendCommand() reports an
             * error as a toast and returns null — the reason never reaches the caller. The
             * panel already knows what is on the block, so it can ask the right question the
             * first time instead of failing and retrying.
             */
            const live = !! this.currentPlayer;
            const liveName = this.currentPlayer?.player?.name || 'the player on the block';

            let warning = sold > 0
                ? `Restart ${pool.name}?\n\nAll ${pool.total} of its players go back on the block, and its ${sold} sale(s) are UNDONE — those teams get their money back and lose those players.\n\nOther pools are not affected.`
                : `Restart ${pool.name}?\n\nAll ${pool.total} of its players go back on the block. Other pools are not affected.`;

            if (live) {
                warning = `FORCE RESTART ${pool.name}?\n\n${liveName} is still on the block`
                    + `${this.timerExpired ? ' with the clock already run out' : ''}`
                    + ` — bidding on them will be discarded and they go back in the queue with everyone else.\n\n`
                    + warning;
            }

            /*
             * Which players come back, chosen rather than assumed.
             *
             * A restart used to reset sold, unsold and skipped together with no way to say
             * which — so an organizer who only wanted the unsold players back on the block had
             * to accept unwinding every sale in the pool as the price. All three start ticked,
             * so pressing Confirm still does what it always did.
             */
            const include = await this.askConfirm(warning, {
                title: live ? 'Force restart pool' : 'Restart pool',
                danger: true,
                checkboxes: [
                    {
                        value: 'unsold',
                        label: 'Unsold players',
                        hint: 'Go back on the block to be offered again.',
                    },
                    {
                        value: 'skipped',
                        label: 'Passed / skipped players',
                        hint: 'Return to the queue in their lot order.',
                    },
                    {
                        value: 'sold',
                        label: sold > 0 ? `Sold players (${sold})` : 'Sold players',
                        hint: sold > 0
                            ? 'Sales are UNDONE — those teams get their money back and lose those players.'
                            : 'Nothing in this pool has been sold.',
                        disabled: sold === 0,
                    },
                ],
            });

            if (! include || ! include.length) return;

            // force only when there is genuinely something to force past, so the guard keeps
            // protecting the ordinary case.
            const result = await this.sendCommand(`pools/${pool.id}/restart`, {
                include,
                ...(live ? { force: true } : {}),
            });
            if (! result?.success) return;

            // What the panel was holding belongs to the run just wiped.
            this._clearForNextPlayer();

            this.statusText = result.message;
            this.toast(result.message, 'success', 'Pool restarted');
            await this.pollAuctionState();
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
            /*
             * A read-only seat must not report expiry.
             *
             * This fires automatically off the clock rather than off a button, so on an
             * auctioneer's screen it would post — and be refused — on every single player,
             * one 403 per lot, with a toast each time. The seat that actually runs the
             * auction reports it; that is the one whose clock counts.
             */
            if (! this.canControl) return;

            this._timerFiring = true;
            this._timerFiredForPlayer = auctionPlayerId;

            try {
                const result = await this.sendCommand('timer-expired', { auction_player_id: auctionPlayerId });
                if (result?.handled) {
                    this.statusText = result.message;
                    this.toast(result.message, result.action === 'sold' ? 'success' : 'info', 'Time up');
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

        /**
         * The figure with no unit word — "100M", not "100M Points".
         *
         * For the team strip once there are more than ten teams: ten columns of
         * "100M Points" does not fit a laptop, and the unit is stated everywhere else on
         * the panel already.
         */
        formatFigure(amount) {
            return window.auctionFigure
                ? window.auctionFigure(amount)
                : String(Number(amount) || 0);
        },

        /**
         * How many columns the team strip uses.
         *
         * Rows follow the count instead of being fixed at two: up to ten teams read best as a
         * single centred row, and beyond that two rows keep the squares big enough to hit.
         * Twenty teams therefore become two rows of ten rather than ten columns of two.
         */
        /**
         * The teams still in the auction.
         *
         * A side that has filled every place cannot bid — the server's ceiling for it is zero —
         * so leaving it on the strip is an invitation to a click that will be refused, and a
         * hall of ten logos where two are decorative. They come off as they fill up, which also
         * keeps the remaining chips as large as possible on the wall-facing panel.
         *
         * `squad_full` comes from the server (AuctionPoolService::purseFrom), not from counting
         * on the client: the squad size can be a tournament setting and retained players occupy
         * places, and getting that arithmetic wrong here would hide a team that may still bid.
         */
        get biddableTeams() {
            return (this.teams || []).filter(t => ! t.squad_full);
        },

        get teamGridColumns() {
            const count = this.biddableTeams.length || 1;
            const rows = count > 10 ? 2 : 1;

            return Math.ceil(count / rows);
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

{{-- Loaded after the component definition so window.auctionChannel exists before init()
     runs and calls subscribeToRaises(). --}}
@include('backend.pages.auction.partials.echo-init')
@endsection
